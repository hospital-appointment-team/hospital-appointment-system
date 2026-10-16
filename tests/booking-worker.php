<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || !in_array($argc, [6,8], true)) exit(2);
require dirname(__DIR__) . '/config/bootstrap.php';
if (!str_ends_with(\Hospital\Core\Environment::get('DB_DATABASE'), '_test')) exit(2);
$deadline = microtime(true) + 10;
while (!is_file($argv[5])) {
    if (microtime(true) > $deadline) { echo 'timeout'; exit(2); }
    usleep(10000);
}
try {
    $action = $argv[6] ?? 'book';
    if ($action === 'restore' || $action === 'cancel') {
        $actor = \Hospital\Models\User::find((int) $argv[1]);
        \Hospital\Services\AppointmentStatusService::update($actor, (int) $argv[7], $action === 'restore' ? 'Confirmed' : 'Cancelled', 'Concurrency test cancellation');
        echo $action === 'restore' ? 'restored' : 'cancelled';
    } elseif ($action === 'book') {
        \Hospital\Services\BookingService::book((int) $argv[1], (int) $argv[2], $argv[3], $argv[4], 'Concurrent booking test');
        echo 'booked';
    } else exit(2);
} catch (\Hospital\Core\HttpException $exception) { echo $exception->status === 409 ? 'conflict' : 'unexpected'; }
catch (Throwable) { echo 'error'; exit(1); }
