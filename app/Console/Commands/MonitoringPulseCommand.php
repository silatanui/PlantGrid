<?php

namespace App\Console\Commands;

use App\Services\MonitoringPulseService;
use Illuminate\Console\Command;

class MonitoringPulseCommand extends Command
{
    protected $signature = 'plantgrid:monitoring-pulse
                            {--demo : Force the post-deploy style outage demo now}
                            {--status : Show monitoring pulse status without running}';

    protected $description = 'Autonomous simulated monitoring instability (rare outages + post-deploy demo)';

    public function handle(MonitoringPulseService $pulse): int
    {
        if ($this->option('status')) {
            $status = $pulse->status();
            $this->table(
                ['Key', 'Value'],
                collect($status)->map(fn ($value, $key) => [
                    $key,
                    is_bool($value) ? ($value ? 'true' : 'false') : (string) ($value ?? ''),
                ])->values()->all()
            );

            return self::SUCCESS;
        }

        $result = $pulse->run(forceDemo: (bool) $this->option('demo'));

        if (! ($result['ran'] ?? false)) {
            $this->warn('Monitoring pulse skipped: '.($result['reason'] ?? 'unknown'));

            return self::SUCCESS;
        }

        $events = $result['events'] ?? [];
        if ($events === []) {
            $this->info('Monitoring pulse idle ('.($result['reason'] ?? 'waiting').').');

            return self::SUCCESS;
        }

        foreach ($events as $event) {
            $this->info('· '.$event);
        }

        return self::SUCCESS;
    }
}
