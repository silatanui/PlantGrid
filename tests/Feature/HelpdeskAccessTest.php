<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Department;
use App\Models\SupportTicket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpdeskAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_sent_to_the_sign_in_page(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_an_employee_only_sees_their_own_ticket(): void
    {
        $this->seed();

        $grace = User::query()->where('email', 'grace.wanjiku@northridge.example')->firstOrFail();
        $someoneElses = SupportTicket::query()->where('requester_id', '!=', $grace->id)->firstOrFail();

        $this->actingAs($grace)
            ->get(route('helpdesk.show', $someoneElses))
            ->assertForbidden();

        $own = SupportTicket::query()->where('requester_id', $grace->id)->firstOrFail();
        $this->actingAs($grace)
            ->get(route('helpdesk.show', $own))
            ->assertOk()
            ->assertSee($own->ticket_number);
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
