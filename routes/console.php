<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('plantgrid:collect-metrics')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('plantgrid:prune-metrics')->daily()->withoutOverlapping();
Schedule::command('plantgrid:check-sla')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('plantgrid:live-pulse')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('plantgrid:refresh-demo-timeline')->daily()->withoutOverlapping();

