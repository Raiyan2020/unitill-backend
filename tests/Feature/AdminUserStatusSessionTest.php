<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\BiometricToken;
use App\Models\RefreshToken;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Tests\TestCase;

class AdminUserStatusSessionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_create_a_user_with_the_existing_admin_response_shape(): void
    {
        $this->withoutMiddleware(PermissionMiddleware::class);
        Sanctum::actingAs(Admin::create([
            'name' => 'Create User Admin',
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('password'),
        ]));

        $email = Str::uuid().'@example.test';

        $this->postJson('/api/admin/users', [
            'first_name' => 'New',
            'last_name' => 'User',
            'email' => $email,
            'phone' => '1098089941',
            'country_code' => '+20',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'status' => '1',
        ])->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.name', 'New User')
            ->assertJsonPath('data.email', $email)
            ->assertJsonStructure(['status', 'message', 'data']);

        $user = User::where('email', $email)->firstOrFail();
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_admin_user_creation_requires_matching_password_confirmation(): void
    {
        $this->withoutMiddleware(PermissionMiddleware::class);
        Sanctum::actingAs(Admin::create([
            'name' => 'Validation Admin',
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('password'),
        ]));

        $this->postJson('/api/admin/users', [
            'first_name' => 'Invalid',
            'last_name' => 'User',
            'email' => Str::uuid().'@example.test',
            'password' => 'password123',
            'password_confirmation' => 'different-password',
        ])->assertStatus(422)
            ->assertJsonPath('status', false);
    }

    public function test_pending_and_disabled_statuses_revoke_mobile_sessions_without_changing_the_admin_response(): void
    {
        $this->withoutMiddleware(PermissionMiddleware::class);
        Sanctum::actingAs(Admin::create([
            'name' => 'Session Admin',
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('password'),
        ]));

        foreach (['2', '3'] as $status) {
            $user = User::create([
                'name' => 'Mobile User',
                'email' => Str::uuid().'@example.test',
                'password' => Hash::make('password'),
                'status' => '1',
            ]);

            $deviceId = 'device-'.$status;
            $user->createToken('mobile-access');
            RefreshToken::create([
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'token_hash' => hash('sha256', Str::random(80)),
                'expires_at' => now()->addDay(),
            ]);
            BiometricToken::create([
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'token_hash' => hash('sha256', Str::random(80)),
                'expires_at' => now()->addDay(),
            ]);
            UserDevice::create([
                'user_id' => $user->id,
                'device_identifier' => $deviceId,
                'last_seen_at' => now(),
                'is_active' => true,
            ]);

            $this->putJson('/api/admin/users/'.$user->id, ['status' => $status])
                ->assertOk()
                ->assertJsonPath('status', true)
                ->assertJsonPath('data.status', $status)
                ->assertJsonStructure(['status', 'message', 'data']);

            $this->assertDatabaseMissing('personal_access_tokens', [
                'tokenable_type' => User::class,
                'tokenable_id' => $user->id,
            ]);
            $this->assertNotNull($user->refreshTokens()->first()->revoked_at);
            $this->assertNotNull($user->biometricTokens()->first()->revoked_at);
            $this->assertFalse($user->userDevices()->first()->is_active);
        }
    }
}
