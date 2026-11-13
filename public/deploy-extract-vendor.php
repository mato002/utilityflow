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

if (! class_exists(ZipArchive::class)) {
    http_response_code(500);
    exit("PHP ZipArchive extension is not enabled on this host.\n");
}

$archivePath = $laravelRoot.'/vendor.zip';
$parts = glob($laravelRoot.'/vendor.part.*') ?: [];
if ($parts !== []) {
    sort($parts, SORT_NATURAL);
    $archive = fopen($archivePath, 'wb');
    if ($archive === false) {
        http_response_code(500);
        exit("Could not create vendor archive.\n");
    }

    foreach ($parts as $part) {
        $chunk = fopen($part, 'rb');
        if ($chunk === false) {
            fclose($archive);
            @unlink($archivePath);
            http_response_code(500);
            exit("Could not read an uploaded vendor chunk.\n");
        }
        stream_copy_to_stream($chunk, $archive);
        fclose($chunk);
    }
    fclose($archive);
} elseif (! is_file($archivePath)) {
    http_response_code(404);
    exit("No vendor archive or chunks found.\n");
}

$zip = new ZipArchive;
if ($zip->open($archivePath) !== true) {
    http_response_code(500);
    exit("Could not open vendor archive.\n");
}

for ($index = 0; $index < $zip->numFiles; $index++) {
    $entry = $zip->getNameIndex($index);
    if (! is_string($entry)
        || (! str_starts_with($entry, 'vendor/') && $entry !== 'vendor')
        || preg_match('#(^|/)\.\.?(/|$)#', $entry) === 1) {
        $zip->close();
        http_response_code(400);
        exit("Vendor archive contains an unexpected path; extraction was stopped.\n");
    }
}

if (! $zip->extractTo($laravelRoot)) {
    $zip->close();
    http_response_code(500);
    exit("Vendor archive extraction failed.\n");
}

$zip->close();
@unlink($archivePath);
foreach ($parts as $part) {
    @unlink($part);
}

echo "Production vendor dependencies extracted.\n";
