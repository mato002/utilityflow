<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$laravelRoot = null;

foreach ([
    dirname(__DIR__),
    dirname(__DIR__).'/utilityflow',
    dirname(__DIR__, 2).'/utilityflow',
] as $candidate) {
    if (is_file($candidate.'/vendor/autoload.php') && is_file($candidate.'/artisan')) {
        $laravelRoot = $candidate;
        break;
    }
}

if ($laravelRoot === null) {
    http_response_code(500);
    exit('Laravel application root not found.');
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $laravelRoot.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $laravelRoot.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $laravelRoot.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
