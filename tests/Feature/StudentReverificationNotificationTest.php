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
            'password' => Hash::make('password'),
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

    public function test_student_within_grace_period_keeps_account_access_but_cannot_create_new_listings(): void
    {
        $user = User::create([
            'name' => 'Grace Period Student',
            'email' => 'grace.personal@example.com',
            'password' => Hash::make('password'),
            'status' => '1',
            'student_email' => 'grace@example.ac.uk',
            'student_verified_at' => now()->subYear()->subDays(10),
            'student_reverify_due_at' => now()->subDays(10),
        ]);

        $token = $user->createToken('mobile-access');

        // Account access is untouched: status stays active, token still works.
        $this->assertSame('1', $user->fresh()->status);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
        ]);

        // But creating a new listing is blocked from the due date onward,
        // even while still inside the 60-day grace window.
        $this->postJson('/api/ads/draft', [], [
            'Authorization' => 'Bearer '.$token->plainTextToken,
        ])
            ->assertStatus(403)
            ->assertJsonPath('data.needs_reverify', true);
    }

    public function test_v2_login_issues_tokens_directly_with_no_otp_step(): void
    {
        $user = User::create([
            'name' => 'Active Student',
            'email' => 'active.personal@example.com',
            'password' => Hash::make('password'),
            'status' => '1',
            'student_email' => 'active@example.ac.uk',
            'student_verified_at' => now()->subDays(10),
            'student_reverify_due_at' => now()->addMonths(12)->subDays(10),
        ]);

        $this->postJson('/api/v2/login', [
            'type' => 'data',
            'email' => $user->email,
            'password' => 'password',
            'device_id' => 'active-device',
        ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonStructure(['data' => ['access_token', 'refresh_token']])
            ->assertJsonMissingPath('data.needs_otp');
    }

    public function test_v2_login_for_a_locked_out_student_sends_a_recovery_code_instead_of_tokens(): void
    {
        Mail::fake();

        $user = User::create([
            'name' => 'Reverifying Student',
            'email' => 'reverify.personal@example.com',
            'password' => Hash::make('password'),
            'status' => '2',
            'student_email' => 'reverify@example.ac.uk',
            'student_verified_at' => now()->subYear()->subDays(61),
            'student_reverify_due_at' => now()->subDays(61),
        ]);

        $this->postJson('/api/v2/login', [
            'type' => 'data',
            'email' => $user->email,
            'password' => 'password',
            'device_id' => 'renewed-device',
        ])
            ->assertOk()
            ->assertJsonPath('data.needs_verification', true)
            ->assertJsonMissingPath('data.access_token');

        $user->refresh();
        $this->assertNotNull($user->activation_code);

        // Completes through the existing /verify-student-email endpoint —
        // no v2-specific recovery endpoint needed.
        $this->postJson('/api/verify-student-email', [
            'email' => $user->email,
            'activation_code' => $user->activation_code,
        ])->assertOk()->assertJsonPath('status', true);

        $user->refresh();
        $this->assertSame('1', $user->status);
        $this->assertTrue($user->student_verified_at->isToday());
        $this->assertTrue($user->student_reverify_due_at->isBetween(
            now()->addYear()->subMinute(),
            now()->addYear()->addMinute(),
        ));
    }

    public function test_v2_login_with_wrong_password_does_not_leak_or_trigger_recovery_for_a_locked_account(): void
    {
        Mail::fake();

        $user = User::create([
            'name' => 'Locked Student',
            'email' => 'locked.personal@example.com',
            'password' => Hash::make('password'),
            'status' => '2',
            'student_email' => 'locked@example.ac.uk',
            'student_verified_at' => now()->subYear()->subDays(61),
            'student_reverify_due_at' => now()->subDays(61),
        ]);

        $this->postJson('/api/v2/login', [
            'type' => 'data',
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_id' => 'attacker-device',
        ])->assertStatus(400);

        $this->assertNull($user->fresh()->activation_code);
        Mail::assertNothingSent();
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
