<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiLoginRequest;
use App\Http\Requests\RefreshTokenRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Mail\OtpMail;
use App\Models\User;
use App\Models\UserLoginLog;
use App\Services\AccountDeletionService;
use App\Services\TwilioService;
use App\Services\PushNotificationService;
use App\Support\LoginLogger;
use App\Support\MobileAuthTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

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
            return $this->loginWithFingerprint($request, $lang);
        }

        return $this->loginWithCredentials($request, $lang);
    }

    protected function loginWithCredentials(ApiLoginRequest $request, bool $lang)
    {
        $login = $request->input('email') ?? $request->input('login');

        // withTrashed so a deleted account can be reactivated by signing in,
        // rather than looking like it never existed.
        $user = User::withTrashed()->where(function ($q) use ($login) {
            $q->where('email', $login)
                ->orWhere('phone', $login);
        })->first();

        if (! $user) {
            return sendError($lang ? 'المستخدم غير موجود' : 'User not found', [], 404);
        }

        if ($user->trashed()) {
            // The password is checked before restoring so a deleted account
            // cannot be revived by anyone who merely knows the email.
            if (! Hash::check($request->password, $user->password)) {
                return sendError($lang ? 'كلمة المرور غير صحيحة' : 'Incorrect password', [], 400);
            }

            // Restores the account together with the ads and chats that were
            // soft-deleted alongside it.
            app(AccountDeletionService::class)->restore($user);
        }

        $statusError = $this->validateActiveUser($user, $lang);
        if ($statusError) {
            return $statusError;
        }

        if (! Hash::check($request->password, $user->password)) {
            return sendError($lang ? 'كلمة المرور غير صحيحة' : 'Incorrect password', [], 400);
        }

        return $this->completeLogin($user, $request, $lang, UserLoginLog::TYPE_DATA);
    }

    protected function loginWithFingerprint(ApiLoginRequest $request, bool $lang)
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

        return $this->completeLogin($user, $request, $lang, UserLoginLog::TYPE_FINGERPRINT);
    }

    public function refresh(RefreshTokenRequest $request)
    {
        $lang = $request->header('lang') === 'ar';
        $deviceId = MobileAuthTokenService::resolveDeviceId($request);
        $result = $this->tokens->refresh($request->input('refresh_token'), $deviceId);

        if (isset($result['error'])) {
            return $this->tokenErrorResponse($result, $lang);
        }

        return sendResponse($result, $lang ? 'تم تجديد رمز الدخول' : 'Access token refreshed');
    }

    public function issueBiometricToken(Request $request)
    {
        $request->validate([
            'device_id' => 'required_without:device_identifier|string|max:191',
            'device_identifier' => 'required_without:device_id|string|max:191',
        ]);

        $lang = $request->header('lang') === 'ar';
        $user = $request->user();
        $deviceId = MobileAuthTokenService::resolveDeviceId($request);
        $result = $this->tokens->issueBiometricToken($user, $deviceId);

        if (isset($result['error'])) {
            return sendError(
                $lang ? 'الجهاز غير مسجّل لهذا الحساب' : $result['message'],
                ['error_code' => $result['error']],
                403
            );
        }

        return sendResponse([
            'biometric_token' => $result['biometric_token'],
            'biometric_token_expires_at' => $result['biometric_token_expires_at'],
        ], $lang ? 'تم إنشاء رمز البصمة' : 'Biometric token issued');
    }

    public function revokeBiometric(Request $request)
    {
        $lang = $request->header('lang') === 'ar';
        $user = $request->user();
        $deviceId = MobileAuthTokenService::resolveDeviceId($request);

        $this->tokens->revokeBiometricTokens($user, $deviceId);

        return sendResponse(
            null,
            $lang ? 'تم إلغاء رمز البصمة' : 'Biometric token revoked'
        );
    }

    protected function tokenErrorResponse(array $result, bool $lang)
    {
        $messages = [
            'biometric_token_invalid' => $lang ? 'رمز البصمة غير صالح' : 'Invalid biometric token',
            'biometric_token_revoked' => $lang ? 'تم إلغاء رمز البصمة. سجّل الدخول بالبيانات' : 'Biometric token revoked. Please sign in with credentials',
            'biometric_token_expired' => $lang ? 'انتهت صلاحية رمز البصمة. سجّل الدخول بالبيانات' : 'Biometric token expired. Please sign in with credentials',
            'refresh_token_invalid' => $lang ? 'رمز التجديد غير صالح' : 'Invalid refresh token',
            'refresh_token_revoked' => $lang ? 'تم إلغاء رمز التجديد. سجّل الدخول مجدداً' : 'Refresh token revoked. Please sign in again',
            'refresh_token_expired' => $lang ? 'انتهت صلاحية رمز التجديد. سجّل الدخول مجدداً' : 'Refresh token expired. Please sign in again',
        ];

        $code = $result['error'];
        $message = $messages[$code] ?? ($result['message'] ?? 'Unauthorized');

        return sendError($message, ['error_code' => $code], 401);
    }

    protected function validateActiveUser(User $user, bool $lang)
    {
        if ($user->status === '3') {
            return sendError($lang ? 'الحساب معطّل' : 'Account disabled', [], 403);
        }

        if ($user->status === '2') {
            return sendError(
                $lang ? 'يجب التحقق من بريد الطالب الجامعي أولاً' : 'Please verify your email first.',
                [
                    'needs_verification' => true,
                    'student_email_masked' => $this->maskEmail($user->student_email),
                ]
            );
        }

        if ($user->status !== '1') {
            return sendError($lang ? 'الحساب غير مفعّل' : 'Account not active', [], 403);
        }

        return null;
    }

    protected function completeLogin(User $user, ApiLoginRequest $request, bool $lang, string $type)
    {
        // Only overwrite the stored device data when the request actually
        // carries it. A biometric login may arrive before the FCM token is
        // ready; updating it unconditionally would wipe the real push token.
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

        LoginLogger::record($user, $request, $type);

        $issueBiometric = $type === UserLoginLog::TYPE_DATA && $request->boolean('enable_biometric');
        $tokens = $this->tokens->issue($user, $request, $issueBiometric);

        if ($request->filled('device_token')) {
            app(PushNotificationService::class)
                ->syncUserTopicSubscription($user->fresh(), $request->input('device_token'));
        }

        return sendResponse(array_merge([
            'user' => new UserResource($user),
        ], $tokens), $lang ? 'تم تسجيل الدخول بنجاح' : __('login success'));
    }

    public function register(RegisterRequest $request)
    {
        $data = $request->validated();
        $lang = $request->header('lang') === 'ar';

        $base = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'name' => $data['first_name'].' '.$data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'country_code' => $data['country_code'] ?? null,
            'city_id' => $data['city_id'] ?? null,
            'password' => $data['password'],
            'device_token' => $data['device_token'] ?? null,
            'device_type' => $data['device_type'] ?? null,
        ];

        $otp = 123456;
        $user = User::create(array_merge($base, [
            'student_email' => $data['student_email'] ?? null,
            'status' => '2',
            'activation_code' => (string) $otp,
            'activation_code_expires_at' => now()->addMinutes(15),
            'activation_sent_at' => now(),
            'terms_accepted_at' => now(),
        ]));
        try {
            // OTP must be sent to the UNIVERSITY email (.ac.uk) — that is what
            // proves the user owns a valid student account.
            Mail::to($user->student_email)->send(new OtpMail($otp));
        } catch (\Throwable $e) {
            Log::error('Registration OTP mail failed', ['error' => $e->getMessage()]);
        }

        return sendResponse([
            'needs_verification' => true,
            'user_id' => $user->id,
            'student_email_masked' => $this->maskEmail($user->student_email),
            'activation_expires_at' => $user->activation_code_expires_at?->toIso8601String(),
        ], $lang
            ? 'تم إرسال رمز التحقق إلى بريدك الجامعي'
            : 'Verification code sent to your student email');
    }

    /**
     * التحقق من رمز 4 أرقام المرسل إلى بريد الطالب (.ac.uk).
     */
    public function verifyStudentEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'student_email' => 'nullable|email',
            'email' => 'nullable|email',
            'activation_code' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $lookup = $request->input('student_email') ?? $request->input('email');
        if (! $lookup) {
            return sendError(
                $request->header('lang') === 'ar'
                    ? 'بريد الطالب أو البريد الشخصي مطلوب'
                    : 'student_email or email is required',
                [],
                422
            );
        }

        $user = User::where('student_email', $lookup)->first()
            ?? User::where('email', $lookup)->where('status', '2')->first();

        $lang = $request->header('lang') === 'ar';

        if (! $user) {
            return sendError($lang ? 'المستخدم غير موجود' : 'User not found', [], 404);
        }

        if ($user->status === '1') {
            return sendError($lang ? 'الحساب مفعّل مسبقاً' : 'Account already verified', [], 400);
        }

        if ($user->status === '3') {
            return sendError($lang ? 'الحساب معطّل' : 'Account disabled', [], 403);
        }

        if ($user->activation_code_expires_at && now()->greaterThan($user->activation_code_expires_at)) {
            return sendError(
                $lang ? 'انتهت صلاحية رمز التحقق. اطلب رمزاً جديداً' : 'Verification code expired. Request a new one.',
                ['expired' => true],
                400
            );
        }

        if ((string) $user->activation_code !== (string) $request->input('activation_code')) {
            return sendError($lang ? 'رمز التحقق غير صحيح' : 'Invalid verification code', [], 400);
        }

        $user->activation_code = null;
        $user->activation_code_expires_at = null;
        $user->status = '1';
        $user->save();

        $user->update([
            'device_type' => $request->device_type,
            'device_token' => $request->device_token,
        ]);

        return sendResponse(array_merge([
            'user' => new UserResource($user->fresh()),
        ], $this->tokens->issue($user, $request)), $lang ? 'تم التحقق من البريد بنجاح' : 'Email verified successfully');
    }

    /** Alias للتوافق مع المسارات القديمة */
    public function activateAccount(Request $request)
    {
        return $this->verifyStudentEmail($request);
    }

    /**
     * إعادة إرسال رمز التحقق (حد أدنى 60 ثانية بين المحاولات).
     */
    public function resendVerificationEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // The verification flow identifies the account by its student email,
        // so accept either identifier here (student email first, then personal).
        $user = User::where('student_email', $request->email)->first()
            ?? User::where('email', $request->email)->first();
        $lang = $request->header('lang') === 'ar';

        if (! $user) {
            return sendError($lang ? 'المستخدم غير موجود' : 'User not found', [], 404);
        }

        if ($user->status === '1') {
            return sendError($lang ? 'الحساب مفعّل مسبقاً' : 'Account already verified', [], 400);
        }

        if ($user->activation_sent_at) {
            $nextAllowed = $user->activation_sent_at->copy()->addSeconds(60);
            if (now()->lessThan($nextAllowed)) {
                $seconds = max(0, $nextAllowed->getTimestamp() - now()->getTimestamp());

                return sendError(
                    $lang ? "يمكنك إعادة الإرسال بعد {$seconds} ثانية" : "You can resend in {$seconds} seconds",
                    ['retry_after_seconds' => $seconds],
                    429
                );
            }
        }

        $otp = 123456;
        $user->activation_code = (string) $otp;
        $user->activation_code_expires_at = now()->addMinutes(15);
        $user->activation_sent_at = now();
        $user->save();

        try {
            // Resend to the university email (fall back to personal only for
            // legacy accounts created before the student email was mandatory).
            Mail::to($user->student_email ?: $user->email)->send(new OtpMail($otp));
        } catch (\Throwable $e) {
            Log::error('Resend OTP mail failed', ['error' => $e->getMessage()]);
        }

        return sendResponse([
            'student_email_masked' => $this->maskEmail($user->student_email ?: $user->email),
            'activation_expires_at' => $user->activation_code_expires_at?->toIso8601String(),
        ], $lang ? 'تم إعادة إرسال رمز التحقق' : 'Verification code resent');
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return sendError($request->header('lang') === 'ar' ? 'غير مسجّل الدخول' : 'Not authenticated', [], 401);
        }

        $deviceId = MobileAuthTokenService::resolveDeviceId($request);
        $this->tokens->revokeRefreshToken($request->input('refresh_token'), $deviceId);

        if ($request->boolean('revoke_biometric')) {
            $this->tokens->revokeBiometricTokens($user, $deviceId);
        }

        $user->currentAccessToken()?->delete();

        return sendResponse(
            null,
            $request->header('lang') === 'ar' ? 'تم تسجيل الخروج بنجاح' : 'Logged out successfully'
        );
    }

    public function forgotPassword(Request $request, TwilioService $twilio)
    {
        $request->validate([
            'phone' => 'nullable',
            'country_code' => 'nullable',
            'email' => 'nullable|email',
        ]);
        if (empty($request->email) && empty($request->phone)) {
            return sendError(__('Email or phone is required'), [], 422);
        }

        $user = User::query()
            ->when($request->filled('email'), function ($query) use ($request) {
                $query->where('email', $request->email);
            })
            ->when($request->filled('phone') && $request->filled('country_code'), function ($query) use ($request) {
                $query->orWhere(function ($q) use ($request) {
                    $q->where('phone', $request->phone)
                        ->where('country_code', $request->country_code);
                });
            })
            ->first();

        if (! $user) {
            return sendError(__('User not found'), [], 404);
        }

        $otp = 123456;

        $user->reset_code = (string) $otp;

        $user->reset_code_expire = now()->addMinutes(10);
        $user->save();

        if (! empty($user->email)) {
            Mail::to($user->email)->send(new OtpMail($otp));
        }

        return sendResponse('Reset code sent');
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'phone' => 'nullable',
            'reset_code' => 'required|digits:6',
            'password' => 'required|min:6',
            'country_code' => 'nullable|max:191',
            'email' => 'nullable|email|max:191',
        ]);

        $user = null;
        if ($request->filled('email')) {
            $user = User::where('email', $request->email)
                ->where('reset_code', $request->reset_code)
                ->where('reset_code_expire', '>=', now())
                ->first();
        }
        if (! $user && $request->filled('phone') && $request->filled('country_code')) {
            $user = User::where('phone', $request->phone)
                ->where('country_code', $request->country_code)
                ->where('reset_code', $request->reset_code)
                ->where('reset_code_expire', '>=', now())
                ->first();
        }

        if (! $user) {
            return sendError(__('Invalid or expired code'));
        }

        $user->password = $request->password;
        $user->reset_code = null;
        $user->reset_code_expire = null;
        $user->save();

        return sendResponse('Password reset successfully');
    }
}
