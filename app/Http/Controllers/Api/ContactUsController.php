<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactUsRequest;
use App\Mail\ContactUsMail;
use App\Models\ContactUsMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactUsController extends Controller
{
    public function __invoke(ContactUsRequest $request)
    {
        $data = $request->validated();

        $message = ContactUsMessage::create([
            'user_id' => $request->user()->id,
            'contact_reason_id' => $data['contact_reason_id'],
            'message' => $data['message'],
        ]);

        // Storing the row used to be the whole implementation — no mail was
        // ever dispatched, which is why nothing reached the support inbox.
        $mailSent = $this->notifySupport($message);

        return sendResponse(
            [
                'id' => $message->id,
                // Distinguishes a delivered message from a stored-only one.
                'mail_sent' => $mailSent,
            ],
            __('api.contact_us.sent')
        );
    }

    /**
     * Mails the support inbox and records the outcome on the row. A delivery
     * failure never fails the request, but it is logged and persisted so
     * "stored but not mailed" is diagnosable.
     */
    protected function notifySupport(ContactUsMessage $message): bool
    {
        $recipient = trim((string) setting('contact_email'));

        if ($recipient === '' || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $error = 'No valid contact_email configured in settings.';
            Log::warning('Contact Us mail skipped', ['id' => $message->id, 'error' => $error]);
            $message->forceFill(['mail_error' => $error])->save();

            return false;
        }

        try {
            $message->load(['user', 'contactReason.translations']);
            Mail::to($recipient)->send(new ContactUsMail($message));
            $message->forceFill(['mail_sent_at' => now(), 'mail_error' => null])->save();

            return true;
        } catch (\Throwable $exception) {
            Log::error('Contact Us mail failed', [
                'id' => $message->id,
                'recipient' => $recipient,
                'error' => $exception->getMessage(),
            ]);
            $message->forceFill(['mail_error' => $exception->getMessage()])->save();

            return false;
        }
    }
}
