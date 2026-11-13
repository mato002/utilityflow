<?php

declare(strict_types=1);

function utilityflow_deploy_expected_token(string $root): string
{
    $tokenFile = $root.'/storage/app/.utilityflow-deploy-token';
    if (is_readable($tokenFile)) {
        $token = trim((string) file_get_contents($tokenFile));
        if ($token !== '') {
            return $token;
        }
    }

    $envFile = $root.'/.env';
    if (! is_readable($envFile)) {
        return '';
    }

    foreach (file($envFile, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || ! str_starts_with($line, 'DEPLOY_EXTRACT_TOKEN=')) {
            continue;
        }

        $value = trim(substr($line, strlen('DEPLOY_EXTRACT_TOKEN=')));
        if (preg_match('/^"(.*)"$/s', $value, $matches) || preg_match("/^'(.*)'$/s", $value, $matches)) {
            return $matches[1];
        }

        return $value;
    }

    return '';
}

function utilityflow_deploy_authorized(string $root): bool
{
    $providedToken = '';
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $providedToken = preg_replace('/^Bearer\s+/i', '', (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '')) ?? '';
    }

    $expectedToken = utilityflow_deploy_expected_token($root);

    return $providedToken !== '' && $expectedToken !== '' && hash_equals($expectedToken, $providedToken);
}

function utilityflow_deploy_resolve_root(): ?string
{
    foreach ([
        dirname(__DIR__),
        dirname(__DIR__).'/utilityflow',
        dirname(__DIR__, 2).'/utilityflow',
    ] as $candidate) {
        if (is_file($candidate.'/artisan') && is_file($candidate.'/.env')) {
            return $candidate;
        }
    }

    return null;
}

function utilityflow_deploy_forbidden(): never
{
    http_response_code(403);
    exit("Forbidden. Configure DEPLOY_EXTRACT_TOKEN in the production .env and matching GitHub Actions secret.\n");
}
