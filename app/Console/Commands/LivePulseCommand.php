<?php

namespace App\Console\Commands;

use App\Services\LivePulse\LivePulseService;
use Illuminate\Console\Command;

class LivePulseCommand extends Command
{
    protected $signature = 'plantgrid:live-pulse
                            {--once : Force exactly one event when the pulse window is active}
                            {--status : Show Live Pulse status without running}';

    protected $description = 'Insert curated portfolio demo activity (cases, notes, messages)';

    public function handle(LivePulseService $pulse): int
    {
        if ($this->option('status')) {
            $status = $pulse->status();
            $this->table(
                ['Key', 'Value'],
                collect($status)->map(fn ($value, $key) => [
                    $key,
                    is_array($value) ? json_encode($value) : (is_bool($value) ? ($value ? 'true' : 'false') : (string) $value),
                ])->values()->all()
            );

            return self::SUCCESS;
        }

        $result = $pulse->run(forceOne: (bool) $this->option('once'));

        if (! ($result['ran'] ?? false)) {
            $this->warn('Live Pulse skipped: '.($result['reason'] ?? 'unknown'));

            return self::SUCCESS;
        }

        $events = $result['events'] ?? [];
        if ($events === []) {
            $this->info('Live Pulse tick completed with no events (random quiet interval).');

            return self::SUCCESS;
        }

        foreach ($events as $event) {
            $this->info('· '.$event);
        }

        return self::SUCCESS;
    }
}
