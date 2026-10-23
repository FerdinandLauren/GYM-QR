<?php

/**
 * Satu panggilan LockerService::assign per proses (AT-08).
 * Argumen: <staff_id> <log_id> <locker_id> <start_at>.
 */

use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\LockerService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $staffId, $logId, $lockerId, $startAt] = $argv;

$delay = (float) $startAt - microtime(true);

if ($delay > 0) {
    usleep((int) ($delay * 1000000));
}

$staff = User::query()->findOrFail((int) $staffId);

try {
    $app->make(LockerService::class)->assign($staff, (int) $logId, (int) $lockerId);
    echo json_encode(['status' => 'success'], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (ApiException $exception) {
    echo json_encode(['status' => 'error', 'code' => $exception->errorCode], JSON_THROW_ON_ERROR).PHP_EOL;
}
