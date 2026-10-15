<?php
declare(strict_types=1);

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header('Cache-Control: no-store');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($path) ? $path : '/';
try {
    require dirname(__DIR__) . '/config/bootstrap.php';
    $routes = require BASE_PATH . '/config/routes.php';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $route = null; $parameters = [];
    foreach ($routes as $pattern => $target) {
        $regex = str_replace('\\{id\\}', '([1-9][0-9]*)', preg_quote($pattern, '~'));
        if (preg_match('~^' . $regex . '$~D', $method . ' ' . $path, $matches)) {
            $route = $target; $parameters = array_slice($matches, 1); break;
        }
    }
    if ($route === null) {
        throw new Hospital\Core\HttpException(404, 'Page not found.');
    }
    Hospital\Core\Security::startSession();
    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) Hospital\Core\Request::csrf();
    header('Content-Type: text/html; charset=utf-8');
    [$controller, $action] = $route;
    (new $controller())->$action(...$parameters);
} catch (Throwable $exception) {
    // Keep exception details (which can contain configuration values) out of HTTP responses.
    $status = $exception instanceof Hospital\Core\HttpException ? $exception->status : 500;
    $message = $status === 500 ? 'The request could not be completed. Please try again later.' : $exception->getMessage();
    if ($status === 500) error_log('Hospital request failed: ' . get_class($exception));
    http_response_code($status);
    if (str_starts_with($path ?? '', '/api/')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => $message]);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Request unsuccessful</title><h1>Request unsuccessful</h1><p>' . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p><a href="/">Return home</a></html>';
    }
}
