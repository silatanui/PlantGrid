<?php

/**
 * cPanel subdomain entrypoint.
 *
 * Document root:  ~/plantgrid.tanuisila.dev
 * Laravel app:    ~/plantgrid_app
 *
 * After every git pull that changes CSS/JS, sync the document root:
 *   bash deploy/sync-public.sh
 *
 * Or manually:
 *   cp -a public/. ~/plantgrid.tanuisila.dev/
 *   cp deploy/cpanel-index.php ~/plantgrid.tanuisila.dev/index.php
 *   ln -sfn ../plantgrid_app/storage/app/public ~/plantgrid.tanuisila.dev/storage
 *
 * Skipping the sync leaves the site pointing at new /build/assets hashes that
 * are not in the subdomain folder yet (blank / unstyled pages).
 *
 * Cron (required for Live Pulse + SLA):
 *   * * * * * cd ~/plantgrid_app && php artisan schedule:run >> /dev/null 2>&1
 * See deploy/CRON.md.
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/../plantgrid_app/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../plantgrid_app/vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/../plantgrid_app/bootstrap/app.php';

$app->handleRequest(Request::capture());
