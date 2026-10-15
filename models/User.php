<?php
declare(strict_types=1);
namespace Hospital\Models;
use Hospital\Core\Passwords;

final class User extends Model
{
    public static function find(int $id): ?array
    {
        return self::query('SELECT * FROM users WHERE id = ?', [$id])->fetch() ?: null;
    }

    public static function byEmail(string $email): ?array
    {
        return self::query('SELECT * FROM users WHERE email = ?', [$email])->fetch() ?: null;
    }

    public static function create(array $data, string $role = 'patient'): int
    {
        self::query('INSERT INTO users (name,email,password_hash,role,dob,blood_group,phone,is_active) VALUES (?,?,?,?,?,?,?,1)', [
            $data['name'], $data['email'], Passwords::hash($data['password']), $role,
            $data['dob'] ?? null, $data['blood_group'] ?? null, $data['phone'] ?? null,
        ]);
        return (int) Database::connection()->lastInsertId();
    }
}
