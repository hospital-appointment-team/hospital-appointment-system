<?php
declare(strict_types=1);

namespace Hospital\Models;

final class Database
{
    private static ?\PDO $connection = null;

    public static function connection(): \PDO
    {
        if (self::$connection === null) {
            $config = require BASE_PATH . '/config/database.php';
            foreach (['host', 'port', 'database'] as $key) {
                if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $config[$key])) {
                    throw new \RuntimeException('Invalid database configuration.');
                }
            }
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $config['host'], $config['port'], $config['database']);
            self::$connection = new \PDO($dsn, $config['username'], $config['password'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
        return self::$connection;
    }
}
