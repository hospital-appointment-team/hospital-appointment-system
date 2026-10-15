<?php
declare(strict_types=1);
namespace Hospital\Models;

abstract class Model
{
    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);
        return $statement;
    }
}
