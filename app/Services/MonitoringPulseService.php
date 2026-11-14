<?php

namespace App\Services;

use App\Models\MonitoringAlert;
use App\Models\NetworkDevice;
use App\Models\Server;
use App\Models\SystemSetting;
use App\Models\User;
use App\Monitoring\MetricCollectionService;
use App\Notifications\MonitoringAlertNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Throwable;

class MonitoringPulseService
{
    public const SETTING_TRIGGER = 'monitoring_pulse_trigger';

    public const SETTING_DEMO_UNTIL = 'monitoring_pulse_demo_until';

    public const SETTING_RECOVER_AT = 'monitoring_pulse_recover_at';

    public const SETTING_NEXT_DUE = 'monitoring_pulse_next_due';

    public const SETTING_LAST_EVENT = 'monitoring_pulse_last_event';

    public const SETTING_OWNED = 'monitoring_pulse_owned';

    public function __construct(
        private readonly MetricCollectionService $collector,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{ran: bool, reason?: string, events?: list<string>}
     */
    public function run(bool $forceDemo = false): array
    {
        if (! (bool) config('plantgrid.monitoring_pulse.enabled', true)) {
            return ['ran' => false, 'reason' => 'disabled'];
        }

        $events = [];
        $now = CarbonImmutable::now(config('plantgrid.display_timezone'));

        if ($forceDemo || $this->armDeployDemoIfNeeded($now)) {
            $events[] = $this->startIncident($now, demo: true);
        }

        $recoverAt = $this->parseSetting(self::SETTING_RECOVER_AT);
        if ($recoverAt !== null && $now->greaterThanOrEqualTo($recoverAt) && $this->isOwned()) {
            $events[] = $this->recover($now);
        }

        $nextDue = $this->parseSetting(self::SETTING_NEXT_DUE);
        if ($nextDue === null) {
            $this->scheduleNextWeekly($now->addDays($this->randomIntervalDays()));
        } elseif ($now->greaterThanOrEqualTo($nextDue) && $this->parseSetting(self::SETTING_RECOVER_AT) === null) {
            $events[] = $this->startIncident($now, demo: false);
        }

        if ($events === []) {
            return ['ran' => true, 'events' => [], 'reason' => 'idle'];
        }

        SystemSetting::putValue(
            self::SETTING_LAST_EVENT,
            $now->toIso8601String().' · '.implode('; ', $events),
            null,
            'Last autonomous monitoring pulse action'
        );

        return ['ran' => true, 'events' => array_values(array_filter($events))];
    }

    public function catchUpIfStale(): void
    {
        try {
            $this->run();
        } catch (Throwable) {
            // Demo convenience only.
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        return [
            'enabled' => (bool) config('plantgrid.monitoring_pulse.enabled', true),
            'trigger' => (string) config('plantgrid.monitoring_pulse.trigger'),
            'stored_trigger' => SystemSetting::getValue(self::SETTING_TRIGGER),
            'scenario' => SystemSetting::getValue('monitoring_scenario', 'normal'),
            'owned' => $this->isOwned(),
            'demo_until' => SystemSetting::getValue(self::SETTING_DEMO_UNTIL),
            'recover_at' => SystemSetting::getValue(self::SETTING_RECOVER_AT),
            'next_due' => SystemSetting::getValue(self::SETTING_NEXT_DUE),
            'last_event' => SystemSetting::getValue(self::SETTING_LAST_EVENT),
        ];
    }

    private function armDeployDemoIfNeeded(CarbonImmutable $now): bool
    {
        $trigger = (string) config('plantgrid.monitoring_pulse.trigger', '');
        if ($trigger === '') {
            return false;
        }

        $stored = SystemSetting::getValue(self::SETTING_TRIGGER);
        if ($stored === $trigger) {
            return false;
        }

        $minutes = max(3, (int) config('plantgrid.monitoring_pulse.demo_minutes', 8));
        $until = $now->addMinutes($minutes);

        SystemSetting::putValue(self::SETTING_TRIGGER, $trigger, null, 'Monitoring pulse deploy trigger token');
        SystemSetting::putValue(self::SETTING_DEMO_UNTIL, $until->toIso8601String(), null, 'Monitoring pulse post-deploy demo window');

        return true;
    }

    private function startIncident(CarbonImmutable $now, bool $demo): string
    {
        $scenario = 'outage';
        $host = (string) config('plantgrid.outage_hostname', 'PLT-SW-PACK');
        $previous = SystemSetting::getValue('monitoring_scenario', 'normal') ?? 'normal';

        SystemSetting::putValue('monitoring_scenario', $scenario, null, 'Active monitoring demonstration scenario');
        $this->collector->demonstrate($scenario);
        SystemSetting::putValue(self::SETTING_OWNED, '1', null, 'Monitoring scenario owned by autonomous pulse');

        $minutes = $demo
            ? max(3, (int) config('plantgrid.monitoring_pulse.demo_minutes', 8))
            : random_int(
                max(20, (int) config('plantgrid.monitoring_pulse.incident_minutes_min', 45)),
                max(30, (int) config('plantgrid.monitoring_pulse.incident_minutes_max', 120))
            );

        $recoverAt = $now->addMinutes($minutes);
        SystemSetting::putValue(self::SETTING_RECOVER_AT, $recoverAt->toIso8601String(), null, 'When monitoring pulse should restore normal');

        if (! $demo) {
            $this->scheduleNextWeekly($now->addDays($this->randomIntervalDays()));
        } elseif ($this->parseSetting(self::SETTING_NEXT_DUE) === null) {
            $this->scheduleNextWeekly($now->addDays($this->randomIntervalDays()));
        }

        $alert = $this->latestCriticalAlert($host) ?? $this->createFallbackAlert($host);
        $this->notifyTeams($alert, $scenario, $host);

        $this->audit->log(
            'monitoring.pulse',
            'system_setting',
            null,
            ['scenario' => $previous],
            [
                'scenario' => $scenario,
                'demo' => $demo,
                'hostname' => $host,
                'recover_at' => $recoverAt->toIso8601String(),
            ]
        );

        $label = $demo ? 'post-deploy demo' : 'weekly';
        return "outage ({$label}) on {$host} until ".$recoverAt->format('d M H:i');
    }

    private function recover(CarbonImmutable $now): string
    {
        $previous = SystemSetting::getValue('monitoring_scenario', 'normal') ?? 'normal';
        SystemSetting::putValue('monitoring_scenario', 'normal', null, 'Active monitoring demonstration scenario');
        $this->collector->demonstrate('normal');
        SystemSetting::putValue(self::SETTING_OWNED, '0');
        SystemSetting::putValue(self::SETTING_RECOVER_AT, null);
        SystemSetting::putValue(self::SETTING_DEMO_UNTIL, null);

        $this->audit->log(
            'monitoring.pulse.recover',
            'system_setting',
            null,
            ['scenario' => $previous],
            ['scenario' => 'normal', 'at' => $now->toIso8601String()]
        );

        return 'recovered to normal';
    }

    private function scheduleNextWeekly(CarbonImmutable $when): void
    {
        SystemSetting::putValue(
            self::SETTING_NEXT_DUE,
            $when->toIso8601String(),
            null,
            'Next autonomous monitoring instability window'
        );
    }

    private function randomIntervalDays(): int
    {
        $min = max(5, (int) config('plantgrid.monitoring_pulse.interval_days_min', 6));
        $max = max($min, (int) config('plantgrid.monitoring_pulse.interval_days_max', 9));

        return random_int($min, $max);
    }

    private function isOwned(): bool
    {
        return SystemSetting::getValue(self::SETTING_OWNED, '0') === '1';
    }

    private function parseSetting(string $key): ?CarbonImmutable
    {
        $raw = SystemSetting::getValue($key);
        if ($raw === null || $raw === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($raw, config('plantgrid.display_timezone'));
        } catch (Throwable) {
            return null;
        }
    }

    private function latestCriticalAlert(string $hostname): ?MonitoringAlert
    {
        $device = NetworkDevice::query()->where('hostname', $hostname)->first();
        if ($device) {
            $alert = MonitoringAlert::query()
                ->where('target_type', 'network_device')
                ->where('target_id', $device->id)
                ->where('severity', 'critical')
                ->whereIn('status', ['open', 'acknowledged'])
                ->latest('triggered_at')
                ->first();
            if ($alert) {
                return $alert;
            }
        }

        $server = Server::query()->where('hostname', $hostname)->first();
        if (! $server) {
            return null;
        }

        return MonitoringAlert::query()
            ->where('target_type', 'server')
            ->where('target_id', $server->id)
            ->where('severity', 'critical')
            ->whereIn('status', ['open', 'acknowledged'])
            ->latest('triggered_at')
            ->first();
    }

    private function createFallbackAlert(string $hostname): MonitoringAlert
    {
        $device = NetworkDevice::query()->where('hostname', $hostname)->first();
        $server = $device ? null : Server::query()->where('hostname', $hostname)->first();

        return MonitoringAlert::query()->create([
            'target_type' => $device ? 'network_device' : 'server',
            'target_id' => $device?->id ?? $server?->id ?? 0,
            'severity' => 'critical',
            'message' => 'Simulated outage: '.$hostname.' is unavailable.',
            'observed_value' => 0,
            'threshold_value' => 100,
            'status' => 'open',
            'source' => 'monitoring_pulse',
            'triggered_at' => now(),
        ]);
    }

    private function notifyTeams(MonitoringAlert $alert, string $scenario, string $hostname): void
    {
        $recipients = User::query()
            ->where('status', 'active')
            ->with('roles.permissions')
            ->get()
            ->filter(fn (User $user) => $user->hasPermission('monitoring.view'))
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new MonitoringAlertNotification($alert, $scenario, $hostname));
    }
}
