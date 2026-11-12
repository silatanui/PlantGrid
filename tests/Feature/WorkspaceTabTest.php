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

    public function test_opening_a_contact_adds_a_workspace_tab_but_modules_do_not(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'daniel.mwangi@northridge.example')->firstOrFail();
        $person = User::query()->where('email', 'grace.wanjiku@northridge.example')->firstOrFail();

        $this->actingAs($user)->get(route('people.show', $person))->assertOk();
        $this->actingAs($user)->get(route('messages.index'))->assertOk();
        $this->actingAs($user)->get(route('docs.index'))->assertOk();

        $tabs = collect(session('workspace.tabs', []));
        $this->assertTrue($tabs->contains(fn ($tab) => ($tab['key'] ?? '') === 'contact:'.$person->id));
        $this->assertFalse($tabs->contains(fn ($tab) => ($tab['key'] ?? '') === 'messages'));
        $this->assertFalse($tabs->contains(fn ($tab) => ($tab['key'] ?? '') === 'docs'));
    }

    public function test_closing_an_active_workspace_tab_redirects_to_another_open_tab(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'daniel.mwangi@northridge.example')->firstOrFail();
        $ticket = SupportTicket::query()->firstOrFail();
        $person = User::query()->where('email', 'grace.wanjiku@northridge.example')->firstOrFail();

        $this->actingAs($user)->get(route('helpdesk.show', $ticket))->assertOk();
        $this->actingAs($user)->get(route('people.show', $person))->assertOk();

        $this->actingAs($user)
            ->from(route('people.show', $person))
            ->post(route('workspace.tabs.close'), [
                'key' => 'contact:'.$person->id,
                'was_active' => '1',
            ])
            ->assertRedirect(route('helpdesk.show', $ticket));

        $this->assertFalse(collect(session('workspace.tabs', []))->contains(
            fn ($tab) => ($tab['key'] ?? '') === 'contact:'.$person->id
        ));
    }

    public function test_workspace_tabs_can_be_reordered(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'daniel.mwangi@northridge.example')->firstOrFail();
        $ticket = SupportTicket::query()->firstOrFail();
        $person = User::query()->where('email', 'grace.wanjiku@northridge.example')->firstOrFail();

        $this->actingAs($user)->get(route('helpdesk.show', $ticket))->assertOk();
        $this->actingAs($user)->get(route('people.show', $person))->assertOk();

        $keys = collect(session('workspace.tabs', []))->pluck('key')->all();
        $reordered = array_reverse($keys);

        $this->actingAs($user)
            ->postJson(route('workspace.tabs.reorder'), ['keys' => $reordered])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame($reordered, collect(session('workspace.tabs', []))->pluck('key')->all());
    }

    public function test_revisiting_a_tab_keeps_its_order(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'daniel.mwangi@northridge.example')->firstOrFail();
        $ticket = SupportTicket::query()->firstOrFail();
        $person = User::query()->where('email', 'grace.wanjiku@northridge.example')->firstOrFail();

        $this->actingAs($user)->get(route('helpdesk.show', $ticket))->assertOk();
        $this->actingAs($user)->get(route('people.show', $person))->assertOk();

        $before = collect(session('workspace.tabs', []))->pluck('key')->all();
        $this->actingAs($user)->get(route('helpdesk.show', $ticket))->assertOk();
        $after = collect(session('workspace.tabs', []))->pluck('key')->all();

        $this->assertSame($before, $after);
    }
}
