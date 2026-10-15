<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || $argc !== 6) exit(2);
require dirname(__DIR__) . '/config/bootstrap.php';
if (!str_ends_with(\Hospital\Core\Environment::get('DB_DATABASE'), '_test')) exit(2);
$deadline = microtime(true) + 10;
while (!is_file($argv[5])) {
    if (microtime(true) > $deadline) { echo 'timeout'; exit(2); }
    usleep(10000);
}
try {
    \Hospital\Services\BookingService::book((int) $argv[1], (int) $argv[2], $argv[3], $argv[4], 'Concurrent booking test');
    echo 'booked';
} catch (\Hospital\Core\HttpException $exception) { echo $exception->status === 409 ? 'conflict' : 'unexpected'; }
catch (Throwable) { echo 'error'; exit(1); }
