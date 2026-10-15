<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'Hospital\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $parts = explode('\\', $relative);
    $parts[0] = strtolower($parts[0]);
    $file = BASE_PATH . '/' . implode('/', $parts) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

Hospital\Core\Environment::load(BASE_PATH . '/.env');
date_default_timezone_set(Hospital\Core\Environment::get('APP_TIMEZONE', 'Asia/Dhaka'));
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
