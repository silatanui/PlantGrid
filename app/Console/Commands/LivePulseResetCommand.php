<?php

namespace App\Console\Commands;

use App\Services\LivePulse\LivePulseService;
use Illuminate\Console\Command;

class LivePulseResetCommand extends Command
{
    protected $signature = 'plantgrid:live-pulse-reset {--force : Skip confirmation}';

    protected $description = 'Remove Live Pulse tagged demo cases, notes, messages, and alerts';

    public function handle(LivePulseService $pulse): int
    {
        if (! $this->option('force') && ! $this->confirm('Delete all [Live pulse] tagged demo activity? Seeded portfolio cases stay.')) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $result = $pulse->reset();
        $this->info(sprintf(
            'Removed tickets=%d comments=%d messages=%d alerts=%d',
            $result['tickets'],
            $result['comments'],
            $result['messages'],
            $result['alerts']
        ));

        return self::SUCCESS;
    }
}
