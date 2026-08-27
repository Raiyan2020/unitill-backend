<?php

namespace App\Mail;

use App\Models\ContactUsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Support-inbox notification for a Contact Us submission. Deliberately not
 * queued: the app is told whether delivery succeeded, so it must not be a guess.
 */
class ContactUsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ContactUsMessage $contactMessage)
    {
    }

    public function envelope(): Envelope
    {
        $reason = $this->contactMessage->contactReason?->nameForLanguageCode('en') ?: 'Contact Us';
        $sender = $this->contactMessage->user;
        $replyEmail = $sender?->email ?: $this->contactMessage->guest_email;
        $replyName = $sender ? ($sender->name ?: $sender->first_name) : $this->contactMessage->guest_name;

        $envelope = new Envelope(
            subject: '['.setting('app_name', 'UniTill').'] '.$reason.' — #'.$this->contactMessage->id,
        );

        if ($replyEmail) {
            $envelope->replyTo = [new Address($replyEmail, (string) $replyName)];
        }

        return $envelope;
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-us',
            with: [
                'contactMessage' => $this->contactMessage,
                'sender' => $this->contactMessage->user,
                'reason' => $this->contactMessage->contactReason?->nameForLanguageCode('en'),
            ],
        );
    }
}
