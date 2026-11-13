<?php

declare(strict_types=1);

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

$removed = [];
foreach (glob($laravelRoot.'/bootstrap/cache/*.php') ?: [] as $cacheFile) {
    if (@unlink($cacheFile)) {
        $removed[] = basename($cacheFile);
    }
}

echo $removed === [] ? "No bootstrap cache files needed clearing.\n" : 'Removed: '.implode(', ', $removed)."\n";
