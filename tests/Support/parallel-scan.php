<?php

/**
 * Satu request POST /api/v1/attendance/scan-qr per proses.
 * Dipakai CheckInIdempotencyConcurrencyTest: argumen <token> <idempotency_key> <payload_json> <start_at>.
 */

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';

[, $token, $key, $payload, $startAt] = $argv;

$request = Request::create(
    '/api/v1/attendance/scan-qr',
    'POST',
    server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
    content: $payload,
);
$request->headers->set('Authorization', 'Bearer '.$token);
$request->headers->set('Idempotency-Key', $key);

$kernel = $app->make(Kernel::class);

// Barrier: kedua proses menembak endpoint pada waktu yang sama setelah bootstrap selesai.
$delay = (float) $startAt - microtime(true);

if ($delay > 0) {
    usleep((int) ($delay * 1000000));
}

$response = $kernel->handle($request);
$kernel->terminate($request, $response);

echo json_encode([
    'status' => $response->getStatusCode(),
    'body' => json_decode((string) $response->getContent(), true),
], JSON_THROW_ON_ERROR).PHP_EOL;
