<?php
declare(strict_types=1);

namespace Hospital\Core;

final class Environment
{
    /** Local configuration only. Existing process environment takes precedence. */
    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new \RuntimeException('Cannot read local configuration.');
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/', $line, $matches)) {
                throw new \RuntimeException('Invalid local configuration entry.');
            }
            [, $name, $value] = $matches;
            if (getenv($name) === false) {
                putenv($name . '=' . $value);
            }
        }
    }

    public static function get(string $name, string $default = ''): string
    {
        $value = getenv($name);
        return $value === false ? $default : $value;
    }
}
