<?php

namespace App\Services;

use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FirebaseService
{
    protected $messaging;

    public function __construct()
    {
        $credentials = config('fcm.credentials');

        if (! is_string($credentials) || ! is_file($credentials)) {
            logger()->warning('Firebase credentials file not found', ['path' => $credentials]);

            return;
        }

        $factory = (new Factory)->withServiceAccount($credentials);
        $this->messaging = $factory->createMessaging();
    }

    public function isConfigured(): bool
    {
        return $this->messaging !== null;
    }

    public function sendNotificationToToken(string $token, string $title, string $body, array $data = []): array
    {
        if (! $this->messaging) {
            return ['success' => false, 'error' => 'Firebase is not configured'];
        }

        try {
            $message = CloudMessage::withTarget('token', $token)
                ->withNotification(Notification::create($title, $body))
                ->withData($this->stringifyData($data));

            $messageId = $this->messaging->send($message);

            return ['success' => true, 'message_id' => $messageId];
        } catch (FirebaseException|MessagingException $e) {
            logger()->error('Firebase token send error: '.$e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function sendNotificationToTopic(string $topic, string $title, string $body, array $data = []): array
    {
        if (! $this->messaging) {
            return ['success' => false, 'error' => 'Firebase is not configured'];
        }

        try {
            $message = CloudMessage::withTarget('topic', $topic)
                ->withNotification(Notification::create($title, $body))
                ->withData($this->stringifyData($data));

            $messageId = $this->messaging->send($message);

            return ['success' => true, 'message_id' => $messageId];
        } catch (FirebaseException|MessagingException $e) {
            logger()->error('Firebase topic send error: '.$e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function subscribeTokenToTopic(string $token, string $topic): array
    {
        if (! $this->messaging) {
            return ['success' => false, 'error' => 'Firebase is not configured'];
        }

        try {
            $result = $this->messaging->subscribeToTopic($topic, [$token]);

            return ['success' => true, 'result' => $result];
        } catch (FirebaseException|MessagingException $e) {
            logger()->error('Firebase subscribe error: '.$e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function unsubscribeTokenFromTopic(string $token, string $topic): array
    {
        if (! $this->messaging) {
            return ['success' => false, 'error' => 'Firebase is not configured'];
        }

        try {
            $result = $this->messaging->unsubscribeFromTopic($topic, [$token]);

            return ['success' => true, 'result' => $result];
        } catch (FirebaseException|MessagingException $e) {
            logger()->error('Firebase unsubscribe error: '.$e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function stringifyData(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            $normalized[(string) $key] = is_scalar($value)
                ? (string) $value
                : json_encode($value);
        }

        return $normalized;
    }
}
