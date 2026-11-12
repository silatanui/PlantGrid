<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceTabTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_a_case_adds_a_workspace_tab(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'daniel.mwangi@northridge.example')->firstOrFail();
        $ticket = SupportTicket::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route('helpdesk.show', $ticket))
            ->assertOk();

        $tabs = session('workspace.tabs', []);
        $this->assertTrue(collect($tabs)->contains(fn ($tab) => ($tab['key'] ?? '') === 'case:'.$ticket->id));
        $this->assertTrue(collect($tabs)->contains(fn ($tab) => ($tab['label'] ?? '') === $ticket->ticket_number));
    }

    public function test_opening_a_contact_and_messages_adds_workspace_tabs(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'daniel.mwangi@northridge.example')->firstOrFail();
        $person = User::query()->where('email', 'grace.wanjiku@northridge.example')->firstOrFail();

        $this->actingAs($user)->get(route('people.show', $person))->assertOk();
        $this->actingAs($user)->get(route('messages.index'))->assertOk();

        $tabs = collect(session('workspace.tabs', []));
        $this->assertTrue($tabs->contains(fn ($tab) => ($tab['key'] ?? '') === 'contact:'.$person->id));
        $this->assertTrue($tabs->contains(fn ($tab) => ($tab['key'] ?? '') === 'messages'));
    }

    public function test_closing_an_active_workspace_tab_redirects_to_another_open_tab(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'daniel.mwangi@northridge.example')->firstOrFail();
        $ticket = SupportTicket::query()->firstOrFail();

        $this->actingAs($user)->get(route('helpdesk.show', $ticket))->assertOk();
        $this->actingAs($user)->get(route('messages.index'))->assertOk();

        $this->actingAs($user)
            ->from(route('messages.index'))
            ->post(route('workspace.tabs.close'), [
                'key' => 'messages',
                'was_active' => '1',
            ])
            ->assertRedirect(route('helpdesk.show', $ticket));

        $this->assertFalse(collect(session('workspace.tabs', []))->contains(fn ($tab) => ($tab['key'] ?? '') === 'messages'));
    }
}
