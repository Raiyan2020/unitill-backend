<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class V3LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_v3_login_issues_tokens_directly_with_no_otp_step(): void
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

        $this->postJson('/api/v3/login', [
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

    public function test_v3_login_for_a_locked_out_student_sends_a_recovery_code_instead_of_tokens(): void
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

        $this->postJson('/api/v3/login', [
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
        // no v3-specific recovery endpoint needed.
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

    public function test_v3_login_with_wrong_password_does_not_leak_or_trigger_recovery_for_a_locked_account(): void
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

        $this->postJson('/api/v3/login', [
            'type' => 'data',
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_id' => 'attacker-device',
        ])->assertStatus(400);

        $this->assertNull($user->fresh()->activation_code);
        Mail::assertNothingSent();
    }

    public function test_v3_issued_token_can_be_refreshed_via_the_shared_v2_refresh_endpoint(): void
    {
        $user = User::create([
            'name' => 'Refreshing Student',
            'email' => 'refresh.personal@example.com',
            'password' => Hash::make('password'),
            'status' => '1',
            'student_email' => 'refresh@example.ac.uk',
            'student_verified_at' => now()->subDays(10),
            'student_reverify_due_at' => now()->addMonths(12)->subDays(10),
        ]);

        $login = $this->postJson('/api/v3/login', [
            'type' => 'data',
            'email' => $user->email,
            'password' => 'password',
            'device_id' => 'refresh-device',
        ])->assertOk();

        $refreshToken = $login->json('data.refresh_token');
        $this->assertNotEmpty($refreshToken);

        $this->postJson('/api/v2/auth/refresh', [
            'refresh_token' => $refreshToken,
            'device_id' => 'refresh-device',
        ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['access_token', 'refresh_token']]);
    }

    public function test_v2_login_still_requires_the_two_step_otp_flow_untouched(): void
    {
        $user = User::create([
            'name' => 'V2 Student',
            'email' => 'v2.personal@example.com',
            'password' => Hash::make('password'),
            'status' => '1',
            'student_email' => 'v2@example.ac.uk',
            'student_verified_at' => now()->subDays(10),
            'student_reverify_due_at' => now()->addMonths(12)->subDays(10),
        ]);

        $this->postJson('/api/v2/login', [
            'type' => 'data',
            'email' => $user->email,
            'password' => 'password',
            'device_id' => 'v2-device',
        ])
            ->assertOk()
            ->assertJsonPath('data.needs_otp', true)
            ->assertJsonMissingPath('data.access_token');
    }
}
