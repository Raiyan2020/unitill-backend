<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserNotification;
use App\Models\BiometricToken;
use App\Models\RefreshToken;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentReverificationNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_student_receives_a_push_notification_instead_of_email(): void
    {
        $user = User::create([
            'name' => 'Due Student',
            'email' => 'student.personal@example.com',
            'password' => 'password',
            'status' => '1',
            'student_email' => 'student@example.ac.uk',
            'student_verified_at' => now()->subYear()->subDay(),
            'student_reverify_due_at' => now()->subDay(),
            'reverify_notified_at' => null,
        ]);

        $this->mock(PushNotificationService::class)
            ->shouldReceive('notifyUser')
            ->once()
            ->withArgs(fn (User $recipient, string $title, string $body, array $data) =>
                $recipient->is($user) &&
                $title === 'Reconfirm your UniTill student status' &&
                $data['type'] === 'student_reverification'
            )
            ->andReturn(new UserNotification);

        $this->artisan('students:require-reverification')
            ->expectsOutput('Reverification notifications sent: 1')
            ->assertSuccessful();

        $this->assertNotNull($user->fresh()->reverify_notified_at);
    }

    public function test_student_is_logged_out_after_the_grace_period(): void
    {
        $user = User::create([
            'name' => 'Expired Student',
            'email' => 'expired.personal@example.com',
            'password' => 'password',
            'status' => '1',
            'student_email' => 'expired@example.ac.uk',
            'student_verified_at' => now()->subYear()->subDays(61),
            'student_reverify_due_at' => now()->subDays(61),
        ]);

        $user->createToken('mobile-access');
        RefreshToken::create([
            'user_id' => $user->id,
            'device_id' => 'expired-device',
            'token_hash' => hash('sha256', Str::random(80)),
            'expires_at' => now()->addYear(),
        ]);
        BiometricToken::create([
            'user_id' => $user->id,
            'device_id' => 'expired-device',
            'token_hash' => hash('sha256', Str::random(80)),
            'expires_at' => now()->addYear(),
        ]);

        $this->mock(PushNotificationService::class)
            ->shouldReceive('notifyUser')
            ->once()
            ->andReturn(new UserNotification);

        $this->artisan('students:require-reverification')
            ->expectsOutput('Students logged out after grace period: 1')
            ->assertSuccessful();

        $user->refresh();
        $this->assertSame('2', $user->status);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
        ]);
        $this->assertNotNull($user->refreshTokens()->first()->revoked_at);
        $this->assertNotNull($user->biometricTokens()->first()->revoked_at);
    }

    public function test_v2_login_otp_reactivates_student_and_starts_a_new_year(): void
    {
        $user = User::create([
            'name' => 'Reverifying Student',
            'email' => 'reverify.personal@example.com',
            'password' => 'password',
            'status' => '2',
            'student_email' => 'reverify@example.ac.uk',
            'student_verified_at' => now()->subYear()->subDays(61),
            'student_reverify_due_at' => now()->subDays(61),
            'login_otp' => '123456',
            'login_otp_expires_at' => now()->addMinutes(15),
        ]);

        $this->postJson('/api/v2/login/verify-otp', [
            'user_id' => $user->id,
            'otp' => '123456',
            'device_id' => 'renewed-device',
        ])->assertOk()->assertJsonPath('status', true);

        $user->refresh();
        $this->assertSame('1', $user->status);
        $this->assertTrue($user->student_verified_at->isToday());
        $this->assertTrue($user->student_reverify_due_at->isBetween(
            now()->addYear()->subMinute(),
            now()->addYear()->addMinute(),
        ));
    }

    public function test_v1_login_requires_existing_verification_flow_after_forced_logout(): void
    {
        Mail::fake();

        $user = User::create([
            'name' => 'Legacy Student',
            'email' => 'legacy.personal@example.com',
            'password' => Hash::make('password'),
            'status' => '2',
            'student_email' => 'legacy@example.ac.uk',
            'student_verified_at' => now()->subYear()->subDays(61),
            'student_reverify_due_at' => now()->subDays(61),
        ]);

        $this->postJson('/api/login', [
            'type' => 'data',
            'email' => $user->email,
            'password' => 'password',
            'device_id' => 'legacy-device',
        ])->assertOk()
            ->assertJsonPath('data.needs_verification', true)
            ->assertJsonMissingPath('data.access_token');

        $this->assertNotNull($user->fresh()->activation_code);
    }
}
