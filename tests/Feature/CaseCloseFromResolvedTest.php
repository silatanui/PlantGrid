<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseCloseFromResolvedTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolved_case_can_close_reusing_existing_resolution_notes(): void
    {
        $this->seed();

        $tech = User::query()->where('email', 'faith.cheruiyot@northridge.example')->firstOrFail();
        $ticket = SupportTicket::query()->where('status', 'resolved')->first()
            ?: SupportTicket::query()->whereNotIn('status', ['closed'])->firstOrFail();

        $ticket->forceFill([
            'status' => 'resolved',
            'resolution_notes' => 'Verified on the packaging line. Ready to close.',
            'resolved_at' => now()->subHours(3),
            'closed_at' => null,
            'assigned_to' => $tech->id,
        ])->save();

        $this->actingAs($tech)
            ->post(route('helpdesk.transition', $ticket), [
                'status' => 'closed',
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame('closed', $ticket->status);
        $this->assertNotNull($ticket->closed_at);
        $this->assertSame('Verified on the packaging line. Ready to close.', $ticket->resolution_notes);
    }
}
