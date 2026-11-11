<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NetworkTopologyMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_physical_topology_renders_lanes_and_device_cards(): void
    {
        $this->seed();

        $manager = User::query()->where('email', 'daniel.mwangi@northridge.example')->firstOrFail();

        $this->actingAs($manager)
            ->get(route('network.index', ['tab' => 'topology', 'map' => 'physical']))
            ->assertOk()
            ->assertSee('Physical topology')
            ->assertSee('Edge / perimeter')
            ->assertSee('Core')
            ->assertSee('Access')
            ->assertSee('Wireless')
            ->assertSee('PLT-FW01')
            ->assertSee('PLT-SW-CORE')
            ->assertSee('PLT-SW-PACK')
            ->assertSee('PLT-AP-PACK')
            ->assertSee('Map legend', false)
            ->assertSee('Firewall')
            ->assertSee('pg-map-device-icon', false)
            ->assertDontSee('pg-map-node-accent', false);
    }
}
