<?php
declare(strict_types=1);

namespace Hospital\Core;

final class Security
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $secure = Environment::get('SESSION_SECURE', 'true') === 'true';
        if (Environment::get('APP_ENV', 'production') !== 'local' && !$secure) {
            throw new \RuntimeException('Secure session cookies are required outside local development.');
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name('hospital_session');
        session_set_cookie_params([
            'lifetime' => 0, 'path' => '/', 'secure' => $secure,
            'httponly' => true, 'samesite' => 'Lax',
        ]);
        if (!session_start()) {
            throw new \RuntimeException('Cannot start session.');
        }
    }

    public static function csrfToken(): string
    {
        self::startSession();
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validCsrf(mixed $token): bool
    {
        self::startSession();
        return is_string($token) && $token !== ''
            && isset($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
