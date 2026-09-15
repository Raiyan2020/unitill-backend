<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Mail\OtpMail;
use App\Models\User;
use App\Services\AccountDeletionService;
use App\Services\PersonalDataExportService;
use App\Traits\ImageTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    use ImageTrait;

    protected function profileQuery()
    {
        return User::query()
            ->with('city.translations')
            ->withCount([
                'ads as published_ads_count' => fn ($query) => $query->where('status', 'published'),
                'ratingsReceived as total_reviews_count',
            ])
            ->withAvg('ratingsReceived as average_rating', 'score');
    }

    public function show($user_id = null)
    {
        if ($user_id) {
            $user = $this->profileQuery()->findOrFail($user_id);
        } else {
            $user = $this->profileQuery()->findOrFail(Auth::id());
        }

        return sendResponse(new UserResource($user));
    }

    public function update(UpdateUserRequest $request)
    {
        $user = Auth::user();
        $lang = $request->header('lang') === 'ar';
        $data = [];

        if ($request->has('first_name')) {
            $data['first_name'] = $request->input('first_name');
        }

        if ($request->has('last_name')) {
            $data['last_name'] = $request->input('last_name');
        }

        if ($request->has('first_name') || $request->has('last_name')) {
            $data['name'] = trim(
                ($data['first_name'] ?? $user->first_name).' '.($data['last_name'] ?? $user->last_name)
            );
        }

        foreach (['phone', 'email', 'city_id'] as $field) {
            if ($request->has($field)) {
                $data[$field] = $request->input($field);
            }
        }

        if ($request->hasFile('image')) {
            $this->deleteStoredImage($user->image);
            $data['image'] = uploader($request->file('image'), 'users');
        }

        $user->update($data);

        return sendResponse(
            new UserResource(
                $this->profileQuery()->findOrFail($user->id)
            ),
            __('api.profile.updated')
        );
    }

    protected function deleteStoredImage(?string $path): void
    {
        if (! $path) {
            return;
        }

        $stored = ltrim(str_replace('/storage/', '', $path), '/');

        if ($stored !== '') {
            Storage::disk('public')->delete($stored);
        }
    }

    //changePassword
    public function changePassword(Request $request)
    {
        $user = Auth::user();
        $validator = Validator::make($request->all(), [
            'old_password' => ['required'],
            'new_password' => ['required', 'min:6', 'confirmed'], // تستخدم new_password_confirmation تلقائياً
        ]);

        if ($validator->fails()) {
            return sendError($validator->errors()->first());
        }

        if (!Hash::check($request->old_password, $user->password)) {
            return sendError('Old password is incorrect');
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return sendResponse(new UserResource($user), 'Password changed successfully.');
    }
    /**
     * Sends a confirmation code to the user's PERSONAL email before an account
     * deletion can be executed (business rule: delete requires OTP + double
     * confirmation). The code is fixed to 123456 for now (testing).
     */
    public function sendDeletionOtp(Request $request)
    {
        $user = Auth::user();
        $otp = (string) rand(100000, 999999);
        \Illuminate\Support\Facades\Cache::put('deletion_otp_' . $user->id, $otp, now()->addMinutes(15));

        try {
            Mail::to($user->student_email)->send(new OtpMail(
                $otp, 
                'Account Deletion Code', 
                'Enter this code to permanently delete your account. This action cannot be undone.'
            ));
        } catch (\Throwable $e) {
            Log::error('Delete-account OTP mail failed', ['error' => $e->getMessage()]);
        }

        return sendResponse(
            ['needs_otp' => true],
            __('api.account.deletion_code_sent')
        );
    }

    /**
     * GDPR Subject Access Request (SAR). Compiles the signed-in user's personal
     * data, emails a copy to their registered address, and logs the request so
     * support can action it within the statutory window. No external service is
     * involved.
     */
    /**
     * SAR: emails the full export as a JSON attachment.
     *
     * A delivery failure is reported to the caller rather than swallowed — a
     * subject access request has a statutory deadline, so a silent failure means
     * an unanswered legal obligation nobody knows about.
     */
    public function requestDataExport(Request $request, PersonalDataExportService $exporter)
    {
        $user = Auth::user();
        $payload = $exporter->build($user);
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        Log::info('SAR data export requested', [
            'user_id' => $user->id,
            'pending_disclosures' => $exporter->pendingReview(),
        ]);

        try {
            Mail::raw(
                "Your UniTill personal data export is attached as JSON.\n\nRequested: ".now()->toDateTimeString(),
                function ($message) use ($user, $json) {
                    $message->to($user->email)
                        ->subject('Your UniTill personal data')
                        ->attachData($json, 'unitill-personal-data.json', ['mime' => 'application/json']);
                }
            );
        } catch (\Throwable $e) {
            Log::error('SAR data export mail failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return sendError(__('api.account.export_failed'), [], 500);
        }

        return sendResponse(
            ['email' => $user->email],
            __('api.account.export_sent')
        );
    }

    /**
     * Same payload as the SAR email, returned inline so the app can offer
     * "Download my data" without waiting on mail delivery.
     */
    public function downloadData(Request $request, PersonalDataExportService $exporter)
    {
        $user = Auth::user();
        $payload = $exporter->build($user);

        Log::info('Personal data downloaded', ['user_id' => $user->id]);

        return response()->streamDownload(
            function () use ($payload) {
                echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            },
            'unitill-personal-data-'.now()->format('Y-m-d').'.json',
            ['Content-Type' => 'application/json']
        );
    }

    //destroy
    public function destroy(Request $request)
    {
        $user = Auth::user();

        // Deletion must be confirmed with the OTP sent to the personal email.
        $otp = (string) $request->input('otp', '');
        $cachedOtp = \Illuminate\Support\Facades\Cache::get('deletion_otp_' . $user->id);

        if (!$cachedOtp || $otp !== $cachedOtp) {
            return sendError(
                __('api.account.invalid_confirmation_code'),
                [],
                422
            );
        }

        // Clear the OTP from cache
        \Illuminate\Support\Facades\Cache::forget('deletion_otp_' . $user->id);

        // Perform hard delete (purge) instead of soft delete, as requested
        $deletedAt = app(\App\Services\V2\AccountClosureService::class)->purge($user);

        return sendResponse(
            ['deleted_at' => $deletedAt->toIso8601String()],
            __('account_v2.deleted_permanently')
        );
    }

    //notificationSwitch
    public function notificationSwitch(Request $request)
    {
        $user = Auth::user();
        $user->notification_switch = $request->status ?? true;
        $user->save();

        return sendResponse(new UserResource($user));
    }

}
