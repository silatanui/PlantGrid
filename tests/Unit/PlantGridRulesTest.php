<?php

namespace Tests\Unit;

use App\Monitoring\Providers\SimulatedMonitoringProvider;
use App\Support\NetworkAddress;
use Tests\TestCase;

class PlantGridRulesTest extends TestCase
{
    public function test_an_address_must_sit_inside_its_subnet(): void
    {
        $this->assertTrue(NetworkAddress::contains('10.10.20.15', '10.10.20.0', 24));
        $this->assertFalse(NetworkAddress::contains('10.10.30.15', '10.10.20.0', 24));
        $this->assertFalse(NetworkAddress::contains('not-an-ip', '10.10.20.0', 24));
    }

    public function test_monitoring_scenarios_are_repeatable(): void
    {
        $provider = new SimulatedMonitoringProvider();

        $this->assertSame(
            $provider->reading('disk', 'PLT-FS01', 'degraded'),
            $provider->reading('disk', 'PLT-FS01', 'degraded')
        );
        $this->assertGreaterThanOrEqual(80, $provider->reading('disk', 'PLT-FS01', 'degraded'));
        $this->assertSame(0.0, $provider->reading('availability', 'PLT-SW-PACK', 'outage'));
        $this->assertSame(100.0, $provider->reading('availability', 'PLT-SW-CORE', 'outage'));
        $this->assertLessThan(80, $provider->reading('disk', 'PLT-FS01', 'normal'));
    }
}
