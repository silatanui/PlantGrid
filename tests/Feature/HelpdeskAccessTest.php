<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Department;
use App\Models\SupportTicket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpdeskAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_sent_to_the_sign_in_page(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_any_signed_in_user_can_open_a_case(): void
    {
        $this->seed();

        $grace = User::query()->where('email', 'grace.wanjiku@northridge.example')->firstOrFail();
        $someoneElses = SupportTicket::query()->where('requester_id', '!=', $grace->id)->firstOrFail();

        $this->actingAs($grace)
            ->get(route('helpdesk.show', $someoneElses))
            ->assertOk()
            ->assertSee($someoneElses->ticket_number);

        $own = SupportTicket::query()->where('requester_id', $grace->id)->firstOrFail();
        $this->actingAs($grace)
            ->get(route('helpdesk.show', $own))
            ->assertOk()
            ->assertSee($own->ticket_number);
    }

    public function test_a_partner_can_open_and_claim_an_unassigned_case(): void
    {
        $this->seed();

        $peter = User::query()->where('email', 'peter.kamau@northridge.example')->firstOrFail();
        $ticket = SupportTicket::query()->whereNull('assigned_to')->where('status', 'new')->first()
            ?? SupportTicket::query()->firstOrFail();
        $ticket->forceFill(['assigned_to' => null, 'status' => 'new'])->save();

        app(NotificationService::class)->syncPartners($ticket, [$peter->id], 'manual');

        $this->actingAs($peter)
            ->get(route('helpdesk.show', $ticket))
            ->assertOk()
            ->assertSee('Take ownership');

        $this->actingAs($peter)
            ->post(route('helpdesk.take-ownership', $ticket))
            ->assertRedirect();

        $this->assertSame($peter->id, $ticket->fresh()->assigned_to);
    }

    public function test_a_case_partner_without_assign_permission_can_add_another_partner(): void
    {
        $this->seed();

        $faith = User::query()->where('email', 'faith.cheruiyot@northridge.example')->firstOrFail();
        $grace = User::query()->where('email', 'grace.wanjiku@northridge.example')->firstOrFail();
        $ticket = SupportTicket::query()->firstOrFail();

        app(NotificationService::class)->syncPartners($ticket, [$faith->id], 'manual');

        $this->assertFalse($faith->hasPermission('tickets.assign'));

        $this->actingAs($faith)
            ->post(route('helpdesk.partners.store', $ticket), ['user_id' => $grace->id])
            ->assertRedirect();

        $this->assertTrue($ticket->fresh()->isPartner($grace));
    }

    public function test_a_non_partner_without_assign_permission_cannot_add_partners(): void
    {
        $this->seed();

        $grace = User::query()->where('email', 'grace.wanjiku@northridge.example')->firstOrFail();
        $peter = User::query()->where('email', 'peter.kamau@northridge.example')->firstOrFail();
        $ticket = SupportTicket::query()
            ->where('requester_id', '!=', $grace->id)
            ->where(fn ($query) => $query->whereNull('assigned_to')->orWhere('assigned_to', '!=', $grace->id))
            ->firstOrFail();

        $this->actingAs($grace)
            ->post(route('helpdesk.partners.store', $ticket), ['user_id' => $peter->id])
            ->assertForbidden();
    }

    public function test_admin_can_remove_a_partner_from_a_case(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'amina.okello@northridge.example')->firstOrFail();
        $partner = User::query()->where('email', 'grace.wanjiku@northridge.example')->firstOrFail();
        $ticket = SupportTicket::query()
            ->where('requester_id', '!=', $partner->id)
            ->where(fn ($query) => $query->whereNull('assigned_to')->orWhere('assigned_to', '!=', $partner->id))
            ->firstOrFail();

        app(NotificationService::class)->syncPartners($ticket, [$partner->id], 'manual');
        $this->assertTrue($ticket->fresh()->partners()->where('users.id', $partner->id)->exists());

        $this->actingAs($admin)
            ->deleteJson(route('helpdesk.partners.destroy', [$ticket, $partner]))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertFalse($ticket->fresh()->partners()->where('users.id', $partner->id)->exists());
    }

    public function test_employee_cannot_remove_a_partner_from_a_case(): void
    {
        $this->seed();

        $grace = User::query()->where('email', 'grace.wanjiku@northridge.example')->firstOrFail();
        $partner = User::query()->where('email', 'peter.kamau@northridge.example')->firstOrFail();
        $ticket = SupportTicket::query()->where('requester_id', $grace->id)->firstOrFail();

        app(NotificationService::class)->syncPartners($ticket, [$partner->id], 'manual');

        $this->actingAs($grace)
            ->deleteJson(route('helpdesk.partners.destroy', [$ticket, $partner]))
            ->assertForbidden();
    }

    public function test_admin_cannot_remove_the_case_owner_as_a_partner(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'amina.okello@northridge.example')->firstOrFail();
        $owner = User::query()->where('email', 'faith.cheruiyot@northridge.example')->firstOrFail();
        $ticket = SupportTicket::query()->firstOrFail();
        $ticket->update(['assigned_to' => $owner->id]);
        app(NotificationService::class)->syncPartners($ticket, [$owner->id], 'ownership');

        $this->actingAs($admin)
            ->deleteJson(route('helpdesk.partners.destroy', [$ticket, $owner]))
            ->assertStatus(422);

        $this->assertTrue($ticket->fresh()->isPartner($owner));
    }

    public function test_an_employee_lands_on_my_cases_not_the_ict_queue(): void
    {
        $this->seed();

        $grace = User::query()->where('email', 'grace.wanjiku@northridge.example')->firstOrFail();

        $this->actingAs($grace)
            ->get(route('helpdesk.index'))
            ->assertOk()
            ->assertSee('My cases')
            ->assertSee('Self-service')
            ->assertDontSee('Case queue', false);
    }

    public function test_an_employee_can_open_a_ticket_for_their_department(): void
    {
        $this->seed();

        $grace = User::query()->where('email', 'grace.wanjiku@northridge.example')->firstOrFail();
        $category = TicketCategory::query()->where('name', 'Hardware')->firstOrFail();
        $asset = Asset::query()->where('asset_tag', 'PG-LBL-0142')->firstOrFail();

        $this->actingAs($grace)->post(route('helpdesk.store'), [
            'department_id' => $grace->department_id,
            'ticket_category_id' => $category->id,
            'asset_id' => $asset->id,
            'subject' => 'Label printer still dark after restart',
            'description' => 'Grace tried the power switch again. The display stays off.',
            'priority' => 'high',
        ])->assertRedirect();

        $this->assertDatabaseHas('support_tickets', [
            'subject' => 'Label printer still dark after restart',
            'requester_id' => $grace->id,
            'priority' => 'high',
            'department_id' => Department::query()->where('code', 'PRD')->value('id'),
        ]);
    }

    public function test_a_duplicate_active_address_is_rejected(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'amina.okello@northridge.example')->firstOrFail();
        $subnetId = \App\Models\NetworkSubnet::query()->where('network_address', '10.10.10.0')->value('id');

        $this->actingAs($admin)->post(route('network.ips.store'), [
            'ip_address' => '10.10.10.11',
            'subnet_id' => $subnetId,
            'address_type' => 'static',
            'status' => 'active',
        ])->assertSessionHasErrors('ip_address');
    }
}
