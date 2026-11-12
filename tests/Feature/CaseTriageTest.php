<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseTriageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_get_triage_suggestions_for_a_new_case(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'amina.okello@northridge.example')->firstOrFail();
        $ticket = SupportTicket::query()->where('status', 'new')->first()
            ?? SupportTicket::query()->whereNull('assigned_to')->firstOrFail();

        $response = $this->actingAs($admin)
            ->postJson(route('ai.triage', $ticket));

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure([
                'suggestion' => [
                    'source',
                    'owner',
                    'partners',
                    'next_steps',
                    'resolution_outline',
                    'internal_note',
                    'candidates',
                ],
            ]);

        $this->assertNotEmpty($response->json('suggestion.next_steps'));
        $this->assertNotEmpty($response->json('suggestion.internal_note'));
    }

    public function test_admin_can_apply_triage_owner_partners_and_note(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'amina.okello@northridge.example')->firstOrFail();
        $ticket = SupportTicket::query()->where('status', 'new')->first()
            ?? SupportTicket::query()->whereNull('assigned_to')->firstOrFail();
        $owner = User::query()->where('email', 'faith.cheruiyot@northridge.example')->firstOrFail();
        $partner = User::query()->where('email', 'daniel.mwangi@northridge.example')->firstOrFail();

        $response = $this->actingAs($admin)->postJson(route('ai.triage.apply', $ticket), [
            'owner_id' => $owner->id,
            'partner_ids' => [$partner->id],
            'internal_note' => "AI triage plan\n1. Inspect the device\n2. Update the case",
            'resolution_outline' => 'Restore service and document the fix.',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('ticket.assigned_to', $owner->id)
            ->assertJsonMissing(['redirect']);

        $ticket->refresh();
        $this->assertSame($owner->id, $ticket->assigned_to);
        $this->assertTrue($ticket->partners()->where('users.id', $partner->id)->exists());
        $this->assertTrue(
            $ticket->comments()
                ->where('visibility', 'internal')
                ->where('comment', 'like', '%AI triage plan%')
                ->exists()
        );
        $this->assertSame('Restore service and document the fix.', $ticket->resolution_notes);
        $this->assertNotEmpty($response->json('comment.html'));
    }

    public function test_employee_cannot_run_triage(): void
    {
        $this->seed();

        $employee = User::query()->where('email', 'grace.wanjiku@northridge.example')->firstOrFail();
        $ticket = SupportTicket::query()->firstOrFail();

        $this->actingAs($employee)
            ->postJson(route('ai.triage', $ticket))
            ->assertForbidden();
    }
}
