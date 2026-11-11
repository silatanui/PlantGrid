<?php

/**
 * cPanel subdomain entrypoint.
 *
 * Document root:  ~/plantgrid.tanuisila.dev
 * Laravel app:    ~/plantgrid_app
 *
 * After pull, install with:
 *   cp -a public/. ~/plantgrid.tanuisila.dev/
 *   cp deploy/cpanel-index.php ~/plantgrid.tanuisila.dev/index.php
 *   ln -sfn ../plantgrid_app/storage/app/public ~/plantgrid.tanuisila.dev/storage
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
