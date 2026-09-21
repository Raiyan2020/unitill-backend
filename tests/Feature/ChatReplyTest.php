<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\Conversation;
use App\Models\Country;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatReplyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sending_a_reply_returns_a_reply_to_summary(): void
    {
        [$buyer, $seller, $conversation] = $this->conversation();

        Sanctum::actingAs($buyer);
        $original = $this->postJson("/api/v2/conversations/{$conversation->id}/messages", [
            'body' => 'Is the bike still available?',
        ])->assertOk()->json('data');

        Sanctum::actingAs($seller);
        $this->postJson("/api/v2/conversations/{$conversation->id}/messages", [
            'body' => 'Yes, it is still available',
            'reply_to_message_id' => $original['id'],
        ])
            ->assertOk()
            ->assertJsonPath('data.reply_to.id', $original['id'])
            ->assertJsonPath('data.reply_to.sender_id', $buyer->id)
            ->assertJsonPath('data.reply_to.sender_name', trim($buyer->first_name.' '.$buyer->last_name))
            ->assertJsonPath('data.reply_to.body', 'Is the bike still available?')
            ->assertJsonPath('data.reply_to.attachment_type', null)
            ->assertJsonPath('data.reply_to.is_deleted', false);
    }

    public function test_an_ordinary_message_has_a_null_reply_to_key_not_a_missing_one(): void
    {
        [$buyer, , $conversation] = $this->conversation();

        Sanctum::actingAs($buyer);

        $response = $this->postJson("/api/v2/conversations/{$conversation->id}/messages", [
            'body' => 'Hello there',
        ])->assertOk();

        $response->assertJsonPath('data.reply_to', null);
        $this->assertArrayHasKey('reply_to', $response->json('data'));
    }

    public function test_the_transcript_still_carries_reply_to_after_a_refetch(): void
    {
        [$buyer, $seller, $conversation] = $this->conversation();

        Sanctum::actingAs($buyer);
        $original = $this->postJson("/api/v2/conversations/{$conversation->id}/messages", [
            'body' => 'Is the bike still available?',
        ])->json('data');

        Sanctum::actingAs($seller);
        $reply = $this->postJson("/api/v2/conversations/{$conversation->id}/messages", [
            'body' => 'Yes, it is still available',
            'reply_to_message_id' => $original['id'],
        ])->json('data');

        // The step the feature exists for: this cannot work from local device
        // state alone, only a re-fetch from the server proves it persisted.
        $list = $this->getJson("/api/conversations/{$conversation->id}/messages")
            ->assertOk()
            ->json('data.data');

        $stored = collect($list)->firstWhere('id', $reply['id']);
        $this->assertNotNull($stored);
        $this->assertSame($original['id'], $stored['reply_to']['id']);
        $this->assertSame('Is the bike still available?', $stored['reply_to']['body']);
    }

    public function test_the_broadcast_carries_reply_to_too(): void
    {
        Event::fake([MessageSent::class]);

        [$buyer, $seller, $conversation] = $this->conversation();

        Sanctum::actingAs($buyer);
        $original = $this->postJson("/api/v2/conversations/{$conversation->id}/messages", [
            'body' => 'Is the bike still available?',
        ])->json('data');

        Sanctum::actingAs($seller);
        $reply = $this->postJson("/api/v2/conversations/{$conversation->id}/messages", [
            'body' => 'Yes, it is still available',
            'reply_to_message_id' => $original['id'],
        ])->json('data');

        Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($reply, $original) {
            if ($event->message->id !== $reply['id']) {
                return false;
            }

            $payload = $event->broadcastWith();

            return $payload['reply_to']['id'] === $original['id']
                && $payload['reply_to']['is_deleted'] === false;
        });
    }

    public function test_a_reply_target_from_another_conversation_is_rejected_with_422(): void
    {
        [$buyer, , $conversation] = $this->conversation();
        [, , $otherConversation] = $this->conversation();

        Sanctum::actingAs($buyer);
        $foreignMessage = Message::create([
            'conversation_id' => $otherConversation->id,
            'sender_id' => $otherConversation->buyer_id,
            'body' => 'A message in a different conversation',
            'type' => 'text',
        ]);

        $this->postJson("/api/v2/conversations/{$conversation->id}/messages", [
            'body' => 'Quoting a stranger\'s message',
            'reply_to_message_id' => $foreignMessage->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.reply_to_message_id.0', 'The message is not in this conversation.');
    }

    public function test_a_reply_target_that_does_not_exist_is_rejected_with_422(): void
    {
        [$buyer, , $conversation] = $this->conversation();

        Sanctum::actingAs($buyer);

        $this->postJson("/api/v2/conversations/{$conversation->id}/messages", [
            'body' => 'Quoting nothing',
            'reply_to_message_id' => 999999999,
        ])->assertStatus(422);
    }

    public function test_a_deleted_original_still_renders_reply_to_flagged_as_deleted(): void
    {
        [$buyer, $seller, $conversation] = $this->conversation();

        Sanctum::actingAs($buyer);
        $original = $this->postJson("/api/v2/conversations/{$conversation->id}/messages", [
            'body' => 'Is the bike still available?',
        ])->json('data');

        Sanctum::actingAs($seller);
        $reply = $this->postJson("/api/v2/conversations/{$conversation->id}/messages", [
            'body' => 'Yes, it is still available',
            'reply_to_message_id' => $original['id'],
        ])->json('data');

        // No delete-message endpoint exists yet; this simulates whatever future
        // mechanism (moderation, user-initiated delete) soft-deletes a message.
        Message::find($original['id'])->delete();

        Sanctum::actingAs($buyer);
        $list = $this->getJson("/api/conversations/{$conversation->id}/messages")
            ->assertOk()
            ->json('data.data');

        $stored = collect($list)->firstWhere('id', $reply['id']);
        $this->assertNotNull($stored['reply_to'], 'reply_to must not be dropped for a deleted original');
        $this->assertSame($original['id'], $stored['reply_to']['id']);
        $this->assertTrue($stored['reply_to']['is_deleted']);
    }

    /** @return array{0: User, 1: User, 2: Conversation} */
    private function conversation(): array
    {
        $country = Country::create(['country_code' => strtoupper(Str::random(2)), 'status' => 'active']);
        $city = City::create([
            'country_id' => $country->id,
            'country_code' => $country->country_code,
            'status' => 'active',
            'code' => Str::upper(Str::random(8)),
        ]);
        $category = Category::create(['status' => 'active', 'sort' => 0]);

        $seller = User::create([
            'name' => 'Seller',
            'first_name' => 'Seller',
            'last_name' => 'User',
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('password'),
            'status' => '1',
            'city_id' => $city->id,
        ]);

        $buyer = User::create([
            'name' => 'Buyer',
            'first_name' => 'Buyer',
            'last_name' => 'User',
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('password'),
            'status' => '1',
            'city_id' => $city->id,
        ]);

        $ad = Ad::create([
            'user_id' => $seller->id,
            'title' => 'Bike for sale',
            'country_id' => $country->id,
            'city_id' => $city->id,
            'main_category_id' => $category->id,
            'price' => 100,
            'currency' => 'GBP',
            'status' => 'published',
            'published_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        $conversation = Conversation::create([
            'ad_id' => $ad->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => 'active',
        ]);

        return [$buyer, $seller, $conversation];
    }
}
