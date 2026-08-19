<?php

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiLoginRequest;
use App\Http\Resources\UserResource;
use App\Mail\OtpMail;
use App\Models\User;
use App\Models\UserLoginLog;
use App\Models\UserModerationAction;
use App\Services\AccountDeletionService;
use App\Services\PushNotificationService;
use App\Services\UserModerationService;
use App\Support\LoginLogger;
use App\Support\MobileAuthTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * V3 login flow.
 *
 * Unlike v2 (every login sends an OTP, verified in a second step), v3 issues
 * tokens directly from the password/fingerprint check, same as v1. An OTP is
 * only ever sent for (a) initial registration, unchanged, handled elsewhere,
 * and (b) recovering an account fully logged out after its 12-month student
 * re-verification plus 60-day grace period elapsed — see
 * startForcedReverification(), which reuses the existing
 * /verify-student-email endpoint to complete, no v3-specific recovery
 * endpoint needed. The annual re-verification itself, while the account is
 * still within its grace period, uses the existing /reverify/send-otp +
 * /reverify/confirm endpoints, not login.
 *
 * v2's /v2/login keeps its existing two-step OTP behaviour untouched — it is
 * already live in the published app. Token refresh is shared: v3-issued
 * tokens are refreshed the same way as v2's, via POST /v2/auth/refresh (the
 * refresh mechanism does not care which login flow issued the token).
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

    public function login(ApiLoginRequest $request)
    {
        $lang = $request->header('lang') === 'ar';
        $type = $request->input('type', UserLoginLog::TYPE_DATA);

        if ($type === UserLoginLog::TYPE_FINGERPRINT) {
            $result = $this->resolveFingerprintUser($request, $lang);
        } else {
            $result = $this->resolveCredentialUser($request, $lang);
        }

        // resolve* helpers return a JSON error (or recovery) response when
        // they can't hand back a ready-to-log-in user.
        if (! $result instanceof User) {
            return $result;
        }

        return $this->completeLogin($result, $request, $lang);
    }

    protected function resolveCredentialUser(ApiLoginRequest $request, bool $lang)
    {
        $login = $request->input('email') ?? $request->input('login');

        // withTrashed so a deleted account can be reactivated by signing in,
        // rather than looking like it never existed. Mirrors v1/v2.
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

        // A previously verified student locked out after their grace period
        // needs the password checked before we send a recovery code, so a
        // guessed email can't be used to spam their inbox. Mirrors v1.
        if ($user->status === '2' && $user->student_verified_at !== null) {
            if (! Hash::check($request->password, $user->password)) {
                return sendError(__('api.auth.wrong_password'), [], 400);
            }

            return $this->startForcedReverification($user);
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
     * Sends a fresh verification code and returns the same shape v1 uses for
     * both registration and its own forced-reverification recovery. The
     * client completes this with the existing /verify-student-email
     * endpoint — no v3-specific recovery endpoint needed.
     */
    protected function startForcedReverification(User $user)
    {
        if (! $user->student_email) {
            return sendError(__('api.auth.no_student_email'), [], 422);
        }

        $fixed = app()->environment('testing')
            ? config('mobile_auth.login_otp_test_code')
            : null;
        $otp = (int) ($fixed ?: random_int(100000, 999999));

        $user->forceFill([
            'activation_code' => (string) $otp,
            'activation_code_expires_at' => now()->addMinutes(15),
            'activation_sent_at' => now(),
        ])->save();

        try {
            Mail::to($user->student_email)->send(new OtpMail($otp));
        } catch (\Throwable $e) {
            Log::error('V3 forced reverification OTP mail failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return sendResponse([
            'needs_verification' => true,
            'user_id' => $user->id,
            'student_email_masked' => $this->maskEmail($user->student_email),
            'activation_expires_at' => $user->activation_code_expires_at?->toIso8601String(),
        ], __('api.auth.code_sent_student_email'));
    }

    protected function completeLogin(User $user, Request $request, bool $lang)
    {
        $deviceUpdates = [];
        if ($request->filled('device_type')) {
            $deviceUpdates['device_type'] = $request->device_type;
        }
        if ($request->filled('device_token')) {
            $deviceUpdates['device_token'] = $request->device_token;
            $deviceUpdates['device_token_updated_at'] = now();
        }
        if ($deviceUpdates) {
            $user->update($deviceUpdates);
        }

        LoginLogger::record($user, $request, UserLoginLog::TYPE_DATA);

        $tokens = $this->tokens->issue(
            $user,
            $request,
            $request->boolean('enable_biometric'),
            config('mobile_auth.v2_access_token_ttl', 43200)
        );

        if ($request->filled('device_token')) {
            app(PushNotificationService::class)
                ->syncUserTopicSubscription($user->fresh(), $request->input('device_token'));
        }

        return sendResponse(array_merge([
            'user' => new UserResource($user),
        ], $tokens), __('api.session.login_success'));
    }

    protected function validateActiveUser(User $user, bool $lang)
    {
        app(UserModerationService::class)->restoreExpiredSuspension($user);

        if ($user->status === '3') {
            $latestAction = UserModerationAction::query()
                ->where('user_id', $user->id)
                ->whereIn('action', ['temporary_suspension', 'permanent_suspension'])
                ->latest('id')
                ->first();

            return sendError(__('api.auth.account_disabled'), [
                'moderation_action_id' => $latestAction?->id,
                'moderation_reason' => $latestAction?->reason,
                'suspended_until' => $latestAction?->ends_at?->toIso8601String(),
            ], 403);
        }

        if ($user->status === '2') {
            // Locked-out-after-grace case: fingerprint already proves device
            // possession, so no password gate is needed here (the credential
            // path intercepts this earlier, before checking the password).
            if ($user->student_verified_at !== null) {
                return $this->startForcedReverification($user);
            }

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
