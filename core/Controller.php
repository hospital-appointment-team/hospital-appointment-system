<?php
declare(strict_types=1);
namespace Hospital\Core;

abstract class Controller
{
    protected function view(string $template, array $data = [], int $status = 200): void
    {
        http_response_code($status);
        $currentUser = Auth::user();
        $e = [Security::class, 'escape'];
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        extract($data, EXTR_SKIP);
        ob_start();
        require BASE_PATH . '/views/' . $template . '.php';
        $content = ob_get_clean();
        require BASE_PATH . '/views/layout.php';
    }

    protected function redirect(string $path, ?string $message = null): never
    {
        if ($message !== null) {
            Security::startSession();
            $_SESSION['flash'] = $message;
        }
        header('Location: ' . $path, true, 303);
        exit;
    }

    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_THROW_ON_ERROR);
    }
}
