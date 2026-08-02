<?php

namespace App\Support;

use App\Models\BiometricToken;
use App\Models\RefreshToken;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MobileAuthTokenService
{
    public static function resolveDeviceId(Request $request): ?string
    {
        $deviceId = $request->input('device_id') ?? $request->input('device_identifier');

        return $deviceId ? trim((string) $deviceId) : null;
    }

    public function issue(User $user, Request $request, bool $issueBiometric = false, ?int $accessTtlMinutes = null): array
    {
        $deviceId = self::resolveDeviceId($request);
        $deviceMeta = $this->deviceMeta($request);

        $accessExpiresAt = now()->addMinutes($accessTtlMinutes ?? config('mobile_auth.access_token_ttl', 60));
        $tokenResult = $user->createToken('mobile-access', ['*'], $accessExpiresAt);

        $tokenResult->accessToken->forceFill([
            'device_name' => $deviceMeta['device_name'],
            'device_identifier' => $deviceId,
            'city_name' => $deviceMeta['city_name'],
            'country_code' => $deviceMeta['country_code'],
        ])->save();

        if ($deviceId) {
            $this->syncUserDevice($user, $deviceId, $deviceMeta);
        }

        $refresh = $this->createRefreshToken($user, $deviceId);
        $payload = [
            'access_token' => $tokenResult->plainTextToken,
            'access_token_expires_at' => $accessExpiresAt->toIso8601String(),
            'refresh_token' => $refresh['plain'],
            'refresh_token_expires_at' => $refresh['expires_at']->toIso8601String(),
            'token' => $tokenResult->plainTextToken,
        ];

        if ($issueBiometric && $deviceId) {
            $biometric = $this->createBiometricToken($user, $deviceId);
            $payload['biometric_token'] = $biometric['plain'];
            $payload['biometric_token_expires_at'] = $biometric['expires_at']->toIso8601String();
        }

        return $payload;
    }

    public function refresh(string $plainRefreshToken, string $deviceId, ?int $accessTtlMinutes = null): array
    {
        $record = $this->findRefreshToken($plainRefreshToken, $deviceId);

        if (! $record) {
            return ['error' => 'refresh_token_invalid', 'message' => 'Refresh token is invalid'];
        }

        if ($record->revoked_at) {
            return ['error' => 'refresh_token_revoked', 'message' => 'Refresh token has been revoked'];
        }

        if ($record->expires_at->isPast()) {
            return ['error' => 'refresh_token_expired', 'message' => 'Refresh token has expired'];
        }

        $user = $record->user;
        $record->forceFill([
            'revoked_at' => now(),
            'last_used_at' => now(),
        ])->save();

        $accessExpiresAt = now()->addMinutes($accessTtlMinutes ?? config('mobile_auth.access_token_ttl', 60));
        $tokenResult = $user->createToken('mobile-access', ['*'], $accessExpiresAt);

        $tokenResult->accessToken->forceFill([
            'device_identifier' => $deviceId,
        ])->save();

        $refresh = $this->createRefreshToken($user, $deviceId);

        return [
            'access_token' => $tokenResult->plainTextToken,
            'access_token_expires_at' => $accessExpiresAt->toIso8601String(),
            'refresh_token' => $refresh['plain'],
            'refresh_token_expires_at' => $refresh['expires_at']->toIso8601String(),
            'token' => $tokenResult->plainTextToken,
        ];
    }

    public function authenticateBiometric(string $plainBiometricToken, string $deviceId): array
    {
        $record = $this->findBiometricToken($plainBiometricToken, $deviceId);

        if (! $record) {
            return ['error' => 'biometric_token_invalid', 'message' => 'Biometric token is invalid'];
        }

        if ($record->revoked_at) {
            return ['error' => 'biometric_token_revoked', 'message' => 'Biometric token has been revoked'];
        }

        if ($record->expires_at->isPast()) {
            return ['error' => 'biometric_token_expired', 'message' => 'Biometric token has expired'];
        }

        $record->forceFill(['last_used_at' => now()])->save();

        return ['user' => $record->user];
    }

    public function issueBiometricToken(User $user, string $deviceId): array
    {
        $knownDevice = UserDevice::query()
            ->where('user_id', $user->id)
            ->where('device_identifier', $deviceId)
            ->exists();

        if (! $knownDevice) {
            return ['error' => 'device_not_registered', 'message' => 'Device is not registered for this user'];
        }

        return $this->createBiometricToken($user, $deviceId);
    }

    public function revokeRefreshToken(?string $plainRefreshToken, ?string $deviceId = null): void
    {
        if (! $plainRefreshToken) {
            return;
        }

        $record = RefreshToken::query()
            ->where('token_hash', $this->hashToken($plainRefreshToken))
            ->when($deviceId, fn ($query) => $query->where('device_id', $deviceId))
            ->first();

        if ($record && ! $record->revoked_at) {
            $record->update(['revoked_at' => now()]);
        }
    }

    public function revokeBiometricTokens(User $user, ?string $deviceId = null): void
    {
        BiometricToken::query()
            ->where('user_id', $user->id)
            ->when($deviceId, fn ($query) => $query->where('device_id', $deviceId))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function revokeAllForUser(User $user): void
    {
        $user->tokens()->delete();

        $user->refreshTokens()
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $user->biometricTokens()
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $user->userDevices()->update(['is_active' => false]);
    }

    protected function createRefreshToken(User $user, ?string $deviceId): array
    {
        $plain = Str::random(80);
        $expiresAt = now()->addDays(config('mobile_auth.refresh_token_ttl', 30));

        RefreshToken::query()->create([
            'user_id' => $user->id,
            'device_id' => $deviceId ?? '',
            'token_hash' => $this->hashToken($plain),
            'expires_at' => $expiresAt,
        ]);

        return ['plain' => $plain, 'expires_at' => $expiresAt];
    }

    protected function createBiometricToken(User $user, string $deviceId): array
    {
        $this->revokeBiometricTokens($user, $deviceId);

        $plain = Str::random(80);
        $expiresAt = now()->addDays(config('mobile_auth.biometric_token_ttl', 90));

        BiometricToken::query()->create([
            'user_id' => $user->id,
            'device_id' => $deviceId,
            'token_hash' => $this->hashToken($plain),
            'expires_at' => $expiresAt,
        ]);

        return [
            'plain' => $plain,
            'expires_at' => $expiresAt,
            'biometric_token' => $plain,
            'biometric_token_expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    protected function findRefreshToken(string $plain, string $deviceId): ?RefreshToken
    {
        return RefreshToken::query()
            ->where('token_hash', $this->hashToken($plain))
            ->where('device_id', $deviceId)
            ->first();
    }

    protected function findBiometricToken(string $plain, string $deviceId): ?BiometricToken
    {
        return BiometricToken::query()
            ->where('token_hash', $this->hashToken($plain))
            ->where('device_id', $deviceId)
            ->first();
    }

    protected function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    protected function deviceMeta(Request $request): array
    {
        $deviceName = $request->input('device_name');

        if (! $deviceName && $request->filled('device_type')) {
            $deviceName = match ($request->input('device_type')) {
                'ios' => 'iPhone',
                'android' => 'Android Device',
                default => 'Mobile Device',
            };
        }

        return [
            'device_name' => $deviceName,
            'city_name' => $request->input('city_name'),
            'country_code' => $request->filled('country_code')
                ? strtoupper((string) $request->input('country_code'))
                : null,
        ];
    }

    protected function syncUserDevice(User $user, string $deviceId, array $meta): void
    {
        UserDevice::query()
            ->where('user_id', $user->id)
            ->where('device_identifier', '!=', $deviceId)
            ->update(['is_active' => false]);

        UserDevice::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'device_identifier' => $deviceId,
            ],
            [
                'device_name' => $meta['device_name'],
                'country_code' => $meta['country_code'],
                'last_seen_at' => now(),
                'is_active' => true,
            ]
        );
    }
}
