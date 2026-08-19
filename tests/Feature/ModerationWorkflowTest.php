<?php

namespace Tests\Feature;

use App\Models\ModerationAppeal;
use App\Models\User;
use App\Models\UserModerationAction;
use App\Models\UserNotification;
use App\Services\PushNotificationService;
use App\Services\UserModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ModerationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_v2_allows_reporting_a_user_outside_a_conversation_without_changing_existing_routes(): void
    {
        [$reporter, $reported] = $this->users();
        Sanctum::actingAs($reporter);

        $this->postJson("/api/v2/users/{$reported->id}/reports", [
            'reason' => 'threats_or_violence',
            'description' => 'Threatening messages sent outside the current chat.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonMissingPath('data.priority');

        $this->assertDatabaseHas('chat_reports', [
            'reported_user_id' => $reported->id,
            'conversation_id' => null,
            'priority' => 'critical',
        ]);
    }

    public function test_warning_and_temporary_suspension_are_audited_and_suspension_revokes_sessions(): void
    {
        [, $user] = $this->users();
        $user->createToken('mobile-access');

        $push = $this->mock(PushNotificationService::class);
        $push->shouldReceive('notifyUser')->twice()->andReturn(new UserNotification);
        $service = app(UserModerationService::class);

        $service->apply($user, 'warning', 'First policy warning.');
        $service->apply($user, 'temporary_suspension', 'Repeated violation.', null, 7);

        $user->refresh();
        $this->assertSame(1, $user->warning_count);
        $this->assertSame('3', $user->status);
        $this->assertNotNull($user->suspended_until);
        $this->assertCount(2, $user->moderationActions);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
        ]);
    }

    public function test_suspended_user_can_submit_one_appeal_through_the_new_v2_endpoint(): void
    {
        [, $user] = $this->users();
        $action = UserModerationAction::create([
            'user_id' => $user->id,
            'action' => 'permanent_suspension',
            'reason' => 'Serious policy violation.',
            'starts_at' => now(),
        ]);

        $payload = [
            'email' => $user->email,
            'password' => 'password',
            'moderation_action_id' => $action->id,
            'message' => 'Please review the evidence again.',
        ];

        $this->postJson('/api/v2/moderation-appeals', $payload)
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');
        $this->postJson('/api/v2/moderation-appeals', $payload)->assertStatus(409);

        $this->assertSame(1, ModerationAppeal::count());
    }

    private function users(): array
    {
        return [
            User::create(['name' => 'Reporter', 'email' => 'reporter@example.test', 'password' => Hash::make('password'), 'status' => '1']),
            User::create(['name' => 'Reported', 'email' => 'reported@example.test', 'password' => Hash::make('password'), 'status' => '1']),
        ];
    }
}
