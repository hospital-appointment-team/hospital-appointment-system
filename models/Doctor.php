<?php
declare(strict_types=1);
namespace Hospital\Models;

final class Doctor extends Model
{
    public const SELECT = 'SELECT d.*,u.name,u.email,u.is_active,s.name AS specialization FROM doctors d JOIN users u ON u.id=d.user_id JOIN specializations s ON s.id=d.specialization_id';
    public static function find(int $id): ?array
    {
        return self::query(self::SELECT . ' WHERE d.id=?', [$id])->fetch() ?: null;
    }
}
