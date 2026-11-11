<?php

namespace Tests\Feature;

use App\Models\MonitoringSample;
use App\Models\NetworkDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitoringScenarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_outage_scenario_switches_chart_to_packaging_switch_and_shows_zero_availability(): void
    {
        $this->seed();

        $manager = User::query()->where('email', 'daniel.mwangi@northridge.example')->firstOrFail();
        $device = NetworkDevice::query()->where('hostname', 'PLT-SW-PACK')->firstOrFail();

        $this->actingAs($manager)
            ->post(route('monitoring.scenario'), ['scenario' => 'outage'])
            ->assertRedirect(route('monitoring.index', ['target' => 'network_device:'.$device->id]));

        $this->actingAs($manager)
            ->get(route('monitoring.index'))
            ->assertOk()
            ->assertSee('Outage scenario is active')
            ->assertSee('PLT-SW-PACK')
            ->assertSee('Outage visible')
            ->assertSee('Availability %')
            ->assertDontSee('CPU %', false);

        $availability = MonitoringSample::query()
            ->where('target_type', 'network_device')
            ->where('target_id', $device->id)
            ->whereHas('metric', fn ($query) => $query->where('metric_key', 'availability'))
            ->latest('collected_at')
            ->value('value');

        $this->assertSame(0.0, (float) $availability);
    }
}
