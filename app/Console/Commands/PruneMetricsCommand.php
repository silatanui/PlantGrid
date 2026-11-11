<?php

namespace App\Console\Commands;

use App\Models\MonitoringSample;
use App\Models\SystemSetting;
use Illuminate\Console\Command;

class PruneMetricsCommand extends Command
{
    protected $signature = 'plantgrid:prune-metrics';

    protected $description = 'Delete simulated metric samples older than the retention setting';

    public function handle(): int
    {
        $days = (int) (SystemSetting::getValue('monitoring_retention_days', '30') ?: 30);
        $deleted = MonitoringSample::query()->where('collected_at', '<', now()->subDays($days))->delete();
        $this->info('Removed '.$deleted.' old simulated samples. Alerts and audit history were kept.');

        return self::SUCCESS;
    }
}
