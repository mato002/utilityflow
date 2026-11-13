<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

require __DIR__.'/deploy-token.php';

header('Content-Type: text/plain; charset=utf-8');

$laravelRoot = utilityflow_deploy_resolve_root();
if ($laravelRoot === null) {
    http_response_code(500);
    exit("Laravel application root not found.\n");
}

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ! utilityflow_deploy_authorized($laravelRoot)) {
    utilityflow_deploy_forbidden();
}

if (! is_file($laravelRoot.'/vendor/autoload.php')) {
    http_response_code(409);
    exit("Production vendor dependencies are not installed. Run the vendor upload workflow, then run migrations manually once.\n");
}

if (function_exists('opcache_reset')) {
    @opcache_reset();
}

try {
    require_once $laravelRoot.'/vendor/autoload.php';

    /** @var Application $app */
    $app = require $laravelRoot.'/bootstrap/app.php';
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();
    $exitCode = $kernel->call('migrate', [
        '--force' => true,
        '--no-ansi' => true,
        '--no-interaction' => true,
    ]);

    http_response_code($exitCode === 0 ? 200 : 500);
    echo trim($kernel->output())."\n";
    exit($exitCode === 0 ? 0 : 1);
} catch (Throwable $exception) {
    http_response_code(500);
    exit(get_class($exception).': '.$exception->getMessage()."\n");
}
