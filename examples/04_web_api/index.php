<?php

declare(strict_types=1);

$port = getenv('PORT') ?: '8080';
$host = getenv('HOST') ?: '127.0.0.1';

if (php_sapi_name() === 'cli') {
    $action = $argv[1] ?? 'serve';
    if ($action === 'serve') {
        echo "⚡ Starting Single-PHP Micro Web Server on http://{$host}:{$port} ...\n";
        echo "Press Ctrl+C to terminate.\n";
        passthru(PHP_BINARY . " -S {$host}:{$port} " . escapeshellarg(__FILE__));
        exit(0);
    }
}

// HTTP API Handling
header('Content-Type: application/json; charset=utf-8');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($path === '/api/health') {
    echo json_encode([
        'status'  => 'healthy',
        'uptime'  => microtime(true),
        'version' => '1.0.0',
        'php'     => PHP_VERSION,
    ]);
    exit(0);
}

echo json_encode([
    'app'       => 'Single-PHP Micro API Server',
    'timestamp' => date('c'),
    'routes'    => ['/api/health'],
]);
