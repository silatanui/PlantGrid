<?php

namespace App\Console\Commands;

use App\Services\DemoTimelineService;
use Illuminate\Console\Command;

class RefreshDemoTimelineCommand extends Command
{
    protected $signature = 'plantgrid:refresh-demo-timeline {--force : Shift one day even when the anchor is already today}';

    protected $description = 'Slide demo case timestamps forward with the calendar so overdue gaps stay frozen';

    public function handle(DemoTimelineService $timeline): int
    {
        $result = $timeline->refreshIfNeeded((bool) $this->option('force'));

        if (! $result['shifted']) {
            $this->info('Demo timeline already aligned to '.$result['anchor'].'.');

            return self::SUCCESS;
        }

        $this->info('Shifted demo case timelines by '.$result['days'].' day(s). Anchor='.$result['anchor']);

        return self::SUCCESS;
    }
}
