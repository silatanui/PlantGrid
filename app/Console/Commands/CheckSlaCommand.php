<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use App\Services\SlaService;
use Illuminate\Console\Command;

class CheckSlaCommand extends Command
{
    protected $signature = 'plantgrid:check-sla';

    protected $description = 'Mark SLA breaches and notify owners for open cases';

    public function handle(SlaService $sla, NotificationService $notifications): int
    {
        $result = $notifications->scanSla($sla);
        $this->info('SLA scan complete: at_risk='.$result['at_risk'].' breached='.$result['breached']);

        return self::SUCCESS;
    }
}
