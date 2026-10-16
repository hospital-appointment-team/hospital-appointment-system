<?php
declare(strict_types=1);
namespace Hospital\Core;

final class PrivateState
{
    public static function path(string $name): string
    {
        if (!preg_match('/^[a-z0-9.-]+$/D', $name)) throw new \LogicException('Invalid private state name.');
        $directory = Environment::get('STATE_STORAGE_DIR') ?: BASE_PATH . '/storage/state';
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) throw new HttpException(503, 'Protected application storage is unavailable.');
        $resolved = realpath($directory);
        $public = realpath(BASE_PATH . '/public');
        if ($resolved === false || $public === false) throw new HttpException(503, 'Protected storage cannot be resolved.');
        $normalized = strtolower(str_replace('\\', '/', $resolved));
        $public = strtolower(str_replace('\\', '/', $public));
        if ($normalized === $public || str_starts_with($normalized, $public . '/')) throw new HttpException(503, 'Application state must be outside the public directory.');
        return $resolved . DIRECTORY_SEPARATOR . $name;
    }

    /** Callback returns [new state, result]. Corrupt/unwritable state fails closed. */
    public static function update(string $name, callable $operation): mixed
    {
        $path = self::path($name);
        $file = @fopen($path, 'c+b');
        if ($file === false) throw new HttpException(503, 'Protected application state is unavailable.');
        @chmod($path, 0600); // On Windows, deployment must also set service-account NTFS ACLs.
        try {
            if (!flock($file, LOCK_EX)) throw new HttpException(503, 'Protected application state is busy.');
            $raw = stream_get_contents($file);
            if ($raw === false) throw new HttpException(503, 'Protected application state could not be read.');
            try { $state = $raw === '' ? [] : json_decode($raw, true, 32, JSON_THROW_ON_ERROR); }
            catch (\JsonException) { throw new HttpException(503, 'Protected application state needs administrator attention.'); }
            if (!is_array($state)) throw new HttpException(503, 'Invalid protected application state.');
            [$updated, $result] = $operation($state);
            if ($updated !== $state) {
                $json = json_encode($updated, JSON_THROW_ON_ERROR) . "\n";
                if (!rewind($file) || !ftruncate($file, 0) || fwrite($file, $json) !== strlen($json) || !fflush($file)) throw new HttpException(503, 'Protected application state could not be saved.');
                if (function_exists('fsync') && !fsync($file)) throw new HttpException(503, 'Protected application state could not be persisted.');
            }
            return $result;
        } finally { flock($file, LOCK_UN); fclose($file); }
    }
}
