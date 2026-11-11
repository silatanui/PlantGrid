<?php

/**
 * cPanel subdomain entrypoint.
 *
 * Document root:  ~/plantgrid.tanuisila.dev
 * Laravel app:    ~/repositories/PlantGrid
 *
 * After pull, copy this over the subdomain index:
 *   cp ~/repositories/PlantGrid/deploy/cpanel-index.php ~/plantgrid.tanuisila.dev/index.php
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/../repositories/PlantGrid/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../repositories/PlantGrid/vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/../repositories/PlantGrid/bootstrap/app.php';

$app->handleRequest(Request::capture());
