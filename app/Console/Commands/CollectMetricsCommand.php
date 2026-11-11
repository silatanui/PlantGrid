<?php

namespace App\Console\Commands;

use App\Monitoring\MetricCollectionService;
use Illuminate\Console\Command;

class CollectMetricsCommand extends Command
{
    protected $signature = 'plantgrid:collect-metrics';

    protected $description = 'Collect one simulated monitoring sample for each registered target';

    public function handle(MetricCollectionService $collector): int
    {
        $count = $collector->collect();
        $this->info('Stored '.$count.' simulated samples.');

        return self::SUCCESS;
    }
}
