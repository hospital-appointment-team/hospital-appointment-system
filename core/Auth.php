<?php
declare(strict_types=1);
namespace Hospital\Core;

use Hospital\Models\User;

final class Auth
{
    private static ?array $current = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        Security::startSession();
        if (self::$loaded) {
            return self::$current;
        }
        self::$loaded = true;
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        $user = User::find((int) $_SESSION['user_id']);
        if ($user === null || !(bool) $user['is_active']) {
            self::logout();
            return null;
        }
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        return self::$current = $user;
    }

    public static function requireRole(string ...$roles): array
    {
        $user = self::user();
        if ($user === null) {
            if (!str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
                header('Location: /login', true, 303);
                exit;
            }
            throw new HttpException(401, 'Please sign in.');
        }
        if (!in_array($user['role'], $roles, true)) {
            throw new HttpException(403, 'You do not have access to this page.');
        }
        return $user;
    }

    public static function login(array $user): void
    {
        Security::startSession();
        session_regenerate_id(true);
        $_SESSION = ['user_id' => (int) $user['id'], 'name' => $user['name'], 'role' => $user['role']];
        self::$current = $user;
        self::$loaded = true;
    }

    public static function logout(): void
    {
        Security::startSession();
        $_SESSION = [];
        session_regenerate_id(true);
        self::$current = null;
        self::$loaded = true;
    }

    public static function home(string $role): string
    {
        return match ($role) {
            'admin' => '/admin', 'doctor' => '/doctor/dashboard', default => '/patient',
        };
    }
}
