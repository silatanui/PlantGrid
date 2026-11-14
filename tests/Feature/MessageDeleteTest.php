<?php

namespace Tests\Feature;

use App\Models\DirectMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_can_delete_message_permanently(): void
    {
        $this->seed();

        $sender = User::query()->where('email', 'daniel.mwangi@northridge.example')->firstOrFail();
        $recipient = User::query()->where('email', 'grace.wanjiku@northridge.example')->firstOrFail();

        $message = DirectMessage::query()->create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'subject' => 'Spare laptop request',
            'body' => 'Do we still have a loaner available?',
        ]);

        $this->actingAs($recipient)
            ->delete(route('messages.destroy', $message))
            ->assertRedirect(route('messages.index', ['folder' => 'inbox']));

        $this->assertDatabaseMissing('direct_messages', ['id' => $message->id]);
    }

    public function test_outsider_cannot_delete_message(): void
    {
        $this->seed();

        $sender = User::query()->where('email', 'daniel.mwangi@northridge.example')->firstOrFail();
        $recipient = User::query()->where('email', 'grace.wanjiku@northridge.example')->firstOrFail();
        $outsider = User::query()->where('email', 'faith.cheruiyot@northridge.example')->firstOrFail();

        $message = DirectMessage::query()->create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'subject' => 'Private note',
            'body' => 'Keep this between us.',
        ]);

        $this->actingAs($outsider)
            ->delete(route('messages.destroy', $message))
            ->assertForbidden();

        $this->assertDatabaseHas('direct_messages', ['id' => $message->id]);
    }
}
