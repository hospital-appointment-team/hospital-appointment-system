<?php
declare(strict_types=1);

// Development server only. Never expose source files outside public/.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$decoded = is_string($path) ? rawurldecode($path) : '';
// realpath() rejects NUL bytes with ValueError before the front controller can handle errors.
$file = $decoded !== '' && !str_contains($decoded, "\0") ? realpath(__DIR__ . $decoded) : false;
$root = realpath(__DIR__) . DIRECTORY_SEPARATOR;
if ($file !== false && str_starts_with($file, $root) && is_file($file)
    && preg_match('/\.(?:css|js|png|jpe?g|webp|ico|svg)$/i', $file)) {
    return false;
}
require __DIR__ . '/index.php';
