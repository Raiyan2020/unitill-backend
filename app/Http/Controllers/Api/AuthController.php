<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiLoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Mail\OtpMail;
use App\Models\User;
use App\Services\TwilioService;
use App\Support\MobileTokenIssuer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
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
        $login = $request->input('email') ?? $request->input('login');
        $lang = $request->header('lang') === 'ar';

        $user = User::where(function ($q) use ($login) {
            $q->where('email', $login)
                ->orWhere('phone', $login);
        })->first();

        if (! $user) {
            return sendError($lang ? 'المستخدم غير موجود' : 'User not found', [], 404);
        }

        if ($user->status === '3') {
            return sendError($lang ? 'الحساب معطّل' : 'Account disabled', [], 403);
        }

        if ($user->status === '2') {
            return sendError(
                $lang ? 'يجب التحقق من بريد الطالب الجامعي أولاً' : 'Please verify your student email first.',
                [
                    'needs_verification' => true,
                    'student_email_masked' => $this->maskEmail($user->student_email),
                ]
            );
        }

        if ($user->status !== '1') {
            return sendError($lang ? 'الحساب غير مفعّل' : 'Account not active', [], 403);
        }

        if (! Hash::check($request->password, $user->password)) {
            return sendError($lang ? 'كلمة المرور غير صحيحة' : 'Incorrect password', [], 400);
        }

        $user->update([
            'device_type' => $request->device_type,
            'device_token' => $request->device_token,
        ]);

        return sendResponse([
            'user' => new UserResource($user),
            'token' => MobileTokenIssuer::issue($user, $request),
        ], $lang ? 'تم تسجيل الدخول بنجاح' : __('login success'));
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

        $otp = random_int(1000, 9999);
        $user = User::create(array_merge($base, [
            'student_email' => $data['student_email'],
            'status' => '2',
            'activation_code' => (string) $otp,
            'activation_code_expires_at' => now()->addMinutes(15),
            'activation_sent_at' => now(),
            'terms_accepted_at' => now(),
        ]));

        try {
            Mail::to($user->student_email)->send(new OtpMail($otp));
        } catch (\Throwable $e) {
            Log::error('Registration OTP mail failed', ['error' => $e->getMessage()]);

            return sendError(
                $lang
                    ? 'تعذر إرسال رمز التحقق. حاول لاحقاً.'
                    : 'Could not send verification email.',
                [],
                500
            );
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
            'activation_code' => 'required|digits:4',
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

        return sendResponse([
            'user' => new UserResource($user->fresh()),
            'token' => MobileTokenIssuer::issue($user, $request),
        ], $lang ? 'تم التحقق من البريد بنجاح' : 'Email verified successfully');
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
            'student_email' => 'required|email',
        ]);

        $user = User::where('student_email', $request->student_email)->first();
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

        $otp = random_int(1000, 9999);
        $user->activation_code = (string) $otp;
        $user->activation_code_expires_at = now()->addMinutes(15);
        $user->activation_sent_at = now();
        $user->save();

        try {
            Mail::to($user->student_email)->send(new OtpMail($otp));
        } catch (\Throwable $e) {
            Log::error('Resend OTP mail failed', ['error' => $e->getMessage()]);

            return sendError(
                $lang ? 'تعذر إرسال البريد. حاول لاحقاً.' : 'Could not send email. Try again later.',
                [],
                500
            );
        }

        return sendResponse([
            'student_email_masked' => $this->maskEmail($user->student_email),
            'activation_expires_at' => $user->activation_code_expires_at?->toIso8601String(),
        ], $lang ? 'تم إعادة إرسال رمز التحقق' : 'Verification code resent');
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return sendError($request->header('lang') === 'ar' ? 'غير مسجّل الدخول' : 'Not authenticated', [], 401);
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

        $otp = random_int(1000, 9999);

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
            'reset_code' => 'required|digits:4',
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
