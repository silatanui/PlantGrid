<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\TicketAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MentionsAndAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_mention_suggestions_include_many_active_users(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'amina.okello@northridge.example')->firstOrFail();
        $activeCount = User::query()->where('status', 'active')->count();

        $response = $this->actingAs($admin)
            ->getJson(route('mentions.suggest', ['trigger' => '@', 'q' => '']));

        $response->assertOk()->assertJsonPath('ok', true);
        $this->assertGreaterThanOrEqual(min(20, $activeCount), count($response->json('items')));
    }

    public function test_hash_mention_suggestions_include_all_visible_assets(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'amina.okello@northridge.example')->firstOrFail();
        $assetCount = \App\Models\Asset::query()->visibleTo($admin)->count();

        $response = $this->actingAs($admin)
            ->getJson(route('mentions.suggest', ['trigger' => '#', 'q' => '']));

        $response->assertOk()->assertJsonPath('ok', true);
        $assetItems = collect($response->json('items'))->where('type', 'asset')->values();
        $this->assertSame($assetCount, $assetItems->count());
        $this->assertNotEmpty($assetItems->first()['label'] ?? null);
    }

    public function test_admin_can_delete_shared_media(): void
    {
        $this->seed();
        Storage::fake('local');

        $admin = User::query()->where('email', 'amina.okello@northridge.example')->firstOrFail();
        $ticket = SupportTicket::query()->firstOrFail();
        $path = 'tickets/'.$ticket->id.'/demo.txt';
        Storage::disk('local')->put($path, 'demo attachment');

        $attachment = TicketAttachment::query()->create([
            'support_ticket_id' => $ticket->id,
            'uploaded_by' => $admin->id,
            'original_name' => 'demo.txt',
            'stored_name' => 'demo.txt',
            'storage_path' => $path,
            'mime_type' => 'text/plain',
            'file_size' => 15,
        ]);

        $this->actingAs($admin)
            ->deleteJson(route('helpdesk.attachment.destroy', [$ticket, $attachment]))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('ticket_attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_employee_cannot_delete_someone_elses_media(): void
    {
        $this->seed();
        Storage::fake('local');

        $admin = User::query()->where('email', 'amina.okello@northridge.example')->firstOrFail();
        $employee = User::query()->where('email', 'grace.wanjiku@northridge.example')->firstOrFail();
        $ticket = SupportTicket::query()->where('requester_id', $employee->id)->firstOrFail();
        $path = 'tickets/'.$ticket->id.'/locked.txt';
        Storage::disk('local')->put($path, 'locked');

        $attachment = TicketAttachment::query()->create([
            'support_ticket_id' => $ticket->id,
            'uploaded_by' => $admin->id,
            'original_name' => 'locked.txt',
            'stored_name' => 'locked.txt',
            'storage_path' => $path,
            'mime_type' => 'text/plain',
            'file_size' => 6,
        ]);

        $this->actingAs($employee)
            ->deleteJson(route('helpdesk.attachment.destroy', [$ticket, $attachment]))
            ->assertForbidden();

        $this->assertDatabaseHas('ticket_attachments', ['id' => $attachment->id]);
    }
}
