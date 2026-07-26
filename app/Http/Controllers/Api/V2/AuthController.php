<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiLoginRequest;
use App\Http\Resources\UserResource;
use App\Mail\OtpMail;
use App\Models\User;
use App\Models\UserLoginLog;
use App\Services\AccountDeletionService;
use App\Support\LoginLogger;
use App\Support\MobileAuthTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * V2 login flow.
 *
 * Unlike v1 (which returns tokens straight from the credentials/biometric
 * check), v2 is a two-step flow: every login first sends a one-time code to
 * the student email, and tokens are only issued after that code is verified.
 * The issued access token also lives for 30 days instead of the v1 60 minutes.
 */
class AuthController extends Controller
{
    public function __construct(protected MobileAuthTokenService $tokens) {}

    protected function maskEmail(?string $email): ?string
    {
        if (! $email || ! str_contains($email, '@')) {
            return null;
        }
        [$local, $domain] = explode('@', $email, 2);
        $visible = strlen($local) <= 2 ? substr($local, 0, 1) : substr($local, 0, 2);

        return $visible.'***@'.$domain;
    }

    /**
     * Step 1 — authenticate the user (password or fingerprint) and send an OTP
     * to their student email. No tokens are issued here.
     */
    public function login(ApiLoginRequest $request)
    {
        $lang = $request->header('lang') === 'ar';
        $type = $request->input('type', UserLoginLog::TYPE_DATA);

        if ($type === UserLoginLog::TYPE_FINGERPRINT) {
            $result = $this->resolveFingerprintUser($request, $lang);
        } else {
            $result = $this->resolveCredentialUser($request, $lang);
        }

        // resolve* helpers return a JSON error response when they fail.
        if (! $result instanceof User) {
            return $result;
        }

        return $this->startOtpChallenge($result, $lang);
    }

    protected function resolveCredentialUser(ApiLoginRequest $request, bool $lang)
    {
        $login = $request->input('email') ?? $request->input('login');

        // withTrashed so a deleted account can be reactivated by signing in,
        // rather than looking like it never existed. Mirrors the v1 flow.
        $user = User::withTrashed()->where(function ($q) use ($login) {
            $q->where('email', $login)
                ->orWhere('phone', $login);
        })->first();

        if (! $user) {
            return sendError(__('api.auth.user_not_found'), [], 404);
        }

        if ($user->trashed()) {
            // The password is verified before restoring so a deleted account
            // cannot be revived by anyone who merely knows the email.
            if (! Hash::check($request->password, $user->password)) {
                return sendError(__('api.auth.wrong_password'), [], 400);
            }

            app(AccountDeletionService::class)->restore($user);
        }

        $statusError = $this->validateActiveUser($user, $lang);
        if ($statusError) {
            return $statusError;
        }

        if (! Hash::check($request->password, $user->password)) {
            return sendError(__('api.auth.wrong_password'), [], 400);
        }

        return $user;
    }

    protected function resolveFingerprintUser(ApiLoginRequest $request, bool $lang)
    {
        $deviceId = MobileAuthTokenService::resolveDeviceId($request);
        $auth = $this->tokens->authenticateBiometric(
            $request->input('biometric_token'),
            $deviceId
        );

        if (isset($auth['error'])) {
            return $this->tokenErrorResponse($auth, $lang);
        }

        $user = $auth['user'];
        $statusError = $this->validateActiveUser($user, $lang);
        if ($statusError) {
            return $statusError;
        }

        return $user;
    }

    /**
     * Generate + email a fresh login OTP for the resolved user.
     */
    protected function startOtpChallenge(User $user, bool $lang)
    {
        $target = $user->student_email ?: $user->email;

        if (! $target) {
            return sendError(
                __('api.auth.no_email_on_file'),
                [],
                422
            );
        }

        $this->sendLoginOtp($user, $target);

        return sendResponse([
            'needs_otp' => true,
            'user_id' => $user->id,
            'student_email_masked' => $this->maskEmail($target),
            'otp_expires_at' => $user->login_otp_expires_at?->toIso8601String(),
        ], __('api.auth.code_sent_student_email'));
    }

    protected function sendLoginOtp(User $user, string $target): void
    {
        // A fixed code is honoured only under the testing environment. Reading
        // it unconditionally meant a misconfigured or simply un-set production
        // environment fell back to a hard-coded default and accepted the same
        // OTP for every account.
        $fixed = app()->environment('testing')
            ? config('mobile_auth.login_otp_test_code')
            : null;

        $otp = (int) ($fixed ?: random_int(100000, 999999));

        $user->forceFill([
            'login_otp' => (string) $otp,
            'login_otp_expires_at' => now()->addMinutes(config('mobile_auth.login_otp_ttl', 15)),
            'login_otp_sent_at' => now(),
        ])->save();

        try {
            Mail::to($target)->send(new OtpMail($otp));
        } catch (\Throwable $e) {
            Log::error('Login OTP mail failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Step 2 — verify the OTP and issue tokens (30-day access token).
     */
    public function verifyOtp(Request $request)
    {
        $lang = $request->header('lang') === 'ar';

        // Same minimal contract as v1 verify-student-email: an identifier plus
        // the code. Device fields are optional — v1 login already collects
        // them at step 1, and MobileAuthTokenService tolerates their absence.
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'user_id' => 'nullable|integer|exists:users,id',
            'student_email' => 'nullable|email',
            'email' => 'required_without_all:user_id,student_email|nullable|email',
            'otp' => 'required|digits:6',
            'device_id' => 'nullable|string|max:191',
            'device_identifier' => 'nullable|string|max:191',
            'device_type' => 'nullable|string|in:ios,android',
            'device_token' => 'nullable|string',
            'device_name' => 'nullable|string|max:191',
            'city_name' => 'nullable|string|max:191',
            'country_code' => 'nullable|string|max:2',
            'enable_biometric' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $user = $this->resolveOtpUser($request);

        if (! $user) {
            return sendError(__('api.auth.user_not_found'), [], 404);
        }

        $statusError = $this->validateActiveUser($user, $lang);
        if ($statusError) {
            return $statusError;
        }

        if (! $user->login_otp) {
            return sendError(
                __('api.auth.no_code_requested'),
                [],
                400
            );
        }

        if ($user->login_otp_expires_at && now()->greaterThan($user->login_otp_expires_at)) {
            return sendError(
                __('api.auth.code_expired'),
                ['expired' => true],
                400
            );
        }

        if ((string) $user->login_otp !== (string) $request->input('otp')) {
            return sendError(__('api.auth.code_invalid'), [], 400);
        }

        // Consume the OTP so it cannot be replayed.
        $user->forceFill([
            'login_otp' => null,
            'login_otp_expires_at' => null,
            'login_otp_sent_at' => null,
        ])->save();

        return $this->completeLogin($user, $request, $lang);
    }

    /**
     * Same as v1 auth/refresh, but the new access token keeps the 30-day TTL.
     */
    public function refresh(\App\Http\Requests\RefreshTokenRequest $request)
    {
        $lang = $request->header('lang') === 'ar';
        $deviceId = MobileAuthTokenService::resolveDeviceId($request);
        $result = $this->tokens->refresh(
            $request->input('refresh_token'),
            $deviceId,
            config('mobile_auth.v2_access_token_ttl', 43200)
        );

        if (isset($result['error'])) {
            $messages = [
                'refresh_token_invalid' => __('api.auth.refresh_token_invalid'),
                'refresh_token_revoked' => __('api.auth.refresh_token_revoked'),
                'refresh_token_expired' => __('api.auth.refresh_token_expired'),
            ];
            $message = $messages[$result['error']] ?? ($result['message'] ?? 'Unauthorized');

            return sendError($message, ['error_code' => $result['error']], 401);
        }

        return sendResponse($result, __('api.auth.access_token_refreshed'));
    }

    /**
     * Resend the login OTP (rate limited).
     */
    public function resendOtp(Request $request)
    {
        $lang = $request->header('lang') === 'ar';

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'user_id' => 'nullable|integer|exists:users,id',
            'student_email' => 'nullable|email',
            'email' => 'required_without_all:user_id,student_email|nullable|email',
        ]);

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $user = $this->resolveOtpUser($request);

        if (! $user) {
            return sendError(__('api.auth.user_not_found'), [], 404);
        }

        $statusError = $this->validateActiveUser($user, $lang);
        if ($statusError) {
            return $statusError;
        }

        // Resend only works while a login challenge is pending. Without this
        // check anyone could plant an OTP for any account (no password) and
        // then verify it — a full authentication bypass.
        if (! $user->login_otp) {
            return sendError(
                __('api.auth.no_code_requested'),
                [],
                400
            );
        }

        $cooldown = (int) config('mobile_auth.login_otp_resend_cooldown', 60);
        if ($user->login_otp_sent_at) {
            $nextAllowed = $user->login_otp_sent_at->copy()->addSeconds($cooldown);
            if (now()->lessThan($nextAllowed)) {
                $seconds = max(0, $nextAllowed->getTimestamp() - now()->getTimestamp());

                return sendError(
                    __('api.auth.resend_cooldown', ['seconds' => $seconds]),
                    ['retry_after_seconds' => $seconds],
                    429
                );
            }
        }

        $target = $user->student_email ?: $user->email;
        if (! $target) {
            return sendError(
                __('api.auth.no_email_on_file'),
                [],
                422
            );
        }

        $this->sendLoginOtp($user, $target);

        return sendResponse([
            'student_email_masked' => $this->maskEmail($target),
            'otp_expires_at' => $user->login_otp_expires_at?->toIso8601String(),
        ], __('api.auth.code_resent'));
    }

    /**
     * Find the user for the OTP steps by user_id, student email, or personal
     * email — mirroring how v1 verify-student-email accepts either identifier.
     */
    protected function resolveOtpUser(Request $request): ?User
    {
        if ($request->filled('user_id')) {
            return User::find($request->input('user_id'));
        }

        $lookup = $request->input('student_email') ?? $request->input('email');

        return User::where('email', $lookup)
            ->orWhere('student_email', $lookup)
            ->first();
    }

    protected function completeLogin(User $user, Request $request, bool $lang)
    {
        $deviceUpdates = [];
        if ($request->filled('device_type')) {
            $deviceUpdates['device_type'] = $request->device_type;
        }
        if ($request->filled('device_token')) {
            $deviceUpdates['device_token'] = $request->device_token;
        }
        if ($deviceUpdates) {
            $user->update($deviceUpdates);
        }

        LoginLogger::record($user, $request, UserLoginLog::TYPE_DATA);

        // Same as v1: let the app opt into a biometric token at login so
        // fingerprint sign-in keeps working on the v2 flow.
        $tokens = $this->tokens->issue(
            $user,
            $request,
            $request->boolean('enable_biometric'),
            config('mobile_auth.v2_access_token_ttl', 43200)
        );

        if ($request->filled('device_token')) {
            app(\App\Services\PushNotificationService::class)
                ->syncUserTopicSubscription($user->fresh(), $request->input('device_token'));
        }

        return sendResponse(array_merge([
            'user' => new UserResource($user),
        ], $tokens), __('api.session.login_success'));
    }

    protected function validateActiveUser(User $user, bool $lang)
    {
        if ($user->status === '3') {
            return sendError(__('api.auth.account_disabled'), [], 403);
        }

        if ($user->status === '2') {
            return sendError(
                __('api.auth.verify_email_first'),
                [
                    'needs_verification' => true,
                    'student_email_masked' => $this->maskEmail($user->student_email),
                ]
            );
        }

        if ($user->status !== '1') {
            return sendError(__('api.auth.account_not_active'), [], 403);
        }

        return null;
    }

    protected function tokenErrorResponse(array $result, bool $lang)
    {
        $messages = [
            'biometric_token_invalid' => __('api.auth.biometric_token_invalid'),
            'biometric_token_revoked' => __('api.auth.biometric_token_revoked_signin'),
            'biometric_token_expired' => __('api.auth.biometric_token_expired'),
        ];

        $code = $result['error'];
        $message = $messages[$code] ?? ($result['message'] ?? 'Unauthorized');

        return sendError($message, ['error_code' => $code], 401);
    }
}
