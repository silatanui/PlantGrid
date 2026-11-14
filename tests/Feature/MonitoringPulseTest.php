<?php

namespace Tests\Feature;

use App\Models\MonitoringAlert;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\MonitoringPulseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MonitoringPulseTest extends TestCase
{
    use RefreshDatabase;

    public function test_deploy_trigger_starts_outage_and_notifies_monitoring_viewers(): void
    {
        $this->seed();
        Notification::fake();

        config([
            'plantgrid.monitoring_pulse.enabled' => true,
            'plantgrid.monitoring_pulse.trigger' => 'test-trigger-outage',
            'plantgrid.monitoring_pulse.demo_minutes' => 8,
        ]);

        SystemSetting::putValue(MonitoringPulseService::SETTING_TRIGGER, 'old-trigger');

        $result = app(MonitoringPulseService::class)->run();

        $this->assertTrue($result['ran']);
        $this->assertNotEmpty($result['events']);
        $this->assertSame('outage', SystemSetting::getValue('monitoring_scenario'));
        $this->assertSame('test-trigger-outage', SystemSetting::getValue(MonitoringPulseService::SETTING_TRIGGER));
        $this->assertNotNull(SystemSetting::getValue(MonitoringPulseService::SETTING_RECOVER_AT));

        $this->assertTrue(
            MonitoringAlert::query()
                ->where('severity', 'critical')
                ->whereIn('status', ['open', 'acknowledged'])
                ->exists()
        );

        $viewer = User::query()->where('email', 'daniel.mwangi@northridge.example')->firstOrFail();
        Notification::assertSentTo($viewer, \App\Notifications\MonitoringAlertNotification::class);
    }

    public function test_owned_outage_recovers_when_window_ends(): void
    {
        $this->seed();

        config([
            'plantgrid.monitoring_pulse.enabled' => true,
            'plantgrid.monitoring_pulse.trigger' => 'already-seen',
        ]);

        SystemSetting::putValue(MonitoringPulseService::SETTING_TRIGGER, 'already-seen');
        SystemSetting::putValue('monitoring_scenario', 'outage');
        SystemSetting::putValue(MonitoringPulseService::SETTING_OWNED, '1');
        SystemSetting::putValue(
            MonitoringPulseService::SETTING_RECOVER_AT,
            now(config('plantgrid.display_timezone'))->subMinute()->toIso8601String()
        );
        SystemSetting::putValue(
            MonitoringPulseService::SETTING_NEXT_DUE,
            now(config('plantgrid.display_timezone'))->addDays(7)->toIso8601String()
        );

        $result = app(MonitoringPulseService::class)->run();

        $this->assertTrue($result['ran']);
        $this->assertSame('normal', SystemSetting::getValue('monitoring_scenario'));
        $this->assertSame('0', SystemSetting::getValue(MonitoringPulseService::SETTING_OWNED));
        $this->assertNull(SystemSetting::getValue(MonitoringPulseService::SETTING_RECOVER_AT));
    }
}
