<?php
declare(strict_types=1);
namespace Hospital\Services;

use Hospital\Core\HttpException;

/** Durable account/IP reservations, taken before password verification. */
final class LoginThrottle
{
    public static function sourceIp(array $server): string
    {
        // Forwarded headers are client-controlled. A trusted proxy must configure REMOTE_ADDR itself.
        $ip = $server['REMOTE_ADDR'] ?? '';
        return is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) ? inet_ntop(inet_pton($ip)) : 'unknown';
    }

    public static function reserve(string $email): array
    {
        return self::update(fn(array $state): array => self::attempt($state, $email, self::sourceIp($_SERVER), time(), bin2hex(random_bytes(16))));
    }

    public static function success(array $reservation): void
    {
        self::update(function (array $state) use ($reservation): array {
            $key = $reservation['key'];
            if (($state[$key]['ticket'] ?? null) === $reservation['ticket']) unset($state[$key]);
            return [$state, null];
        });
    }

    /** Pure policy: five attempts/account, exponential delay; fifty requests/IP per fifteen minutes. */
    public static function attempt(array $state, string $email, string $ip, int $now, string $ticket): array
    {
        foreach ($state as $key => $bucket) {
            if (!is_array($bucket) || !isset($bucket['expires'], $bucket['count'], $bucket['wait'])
                || !is_int($bucket['expires']) || !is_int($bucket['count']) || !is_int($bucket['wait'])
                || $bucket['count'] < 0 || !is_string($bucket['ticket'] ?? null)) {
                throw new HttpException(503, 'Sign-in temporarily unavailable.');
            }
            if ($bucket['expires'] <= $now) unset($state[$key]);
        }
        $account = 'account:' . hash('sha256', strtolower(trim($email)));
        $source = 'ip:' . hash('sha256', $ip);
        if (count($state) >= 10000 && (!isset($state[$account]) || !isset($state[$source]))) {
            throw new HttpException(503, 'Sign-in temporarily unavailable.');
        }
        $empty = ['expires' => $now + 900, 'count' => 0, 'wait' => 0, 'ticket' => ''];
        $ipBucket = $state[$source] ?? $empty;
        $accountBucket = $state[$account] ?? $empty;
        if ($ipBucket['count'] >= 50) return [$state, ['allowed' => false, 'retry' => max(1, $ipBucket['expires'] - $now)]];
        $ipBucket['count']++;
        $state[$source] = $ipBucket;
        if ($accountBucket['wait'] > $now) return [$state, ['allowed' => false, 'retry' => $accountBucket['wait'] - $now]];
        $accountBucket['count']++;
        $accountBucket['ticket'] = $ticket;
        if ($accountBucket['count'] >= 5) {
            $delay = min(900, 30 * (2 ** min(5, $accountBucket['count'] - 5)));
            $accountBucket['wait'] = min($accountBucket['expires'], $now + $delay);
        }
        $state[$account] = $accountBucket;
        return [$state, ['allowed' => true, 'retry' => 0, 'key' => $account, 'ticket' => $ticket]];
    }

    private static function update(callable $callback): mixed
    {
        $directory = getenv('AUTH_THROTTLE_DIR') ?: BASE_PATH . '/storage/auth';
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new HttpException(503, 'Sign-in temporarily unavailable.');
        $root = str_replace('\\', '/', (string) realpath($directory));
        $public = str_replace('\\', '/', (string) realpath(BASE_PATH . '/public'));
        if ($root === '' || str_starts_with(strtolower($root) . '/', strtolower($public) . '/')) throw new HttpException(503, 'Sign-in temporarily unavailable.');
        $path = $directory . '/login-attempts.json';
        // Lock a stable inode: replacing the state file must not replace the mutex.
        $file = fopen($directory . '/login-attempts.lock', 'c+b');
        if ($file === false) throw new HttpException(503, 'Sign-in temporarily unavailable.');
        chmod($directory . '/login-attempts.lock', 0600);
        $temporary = null;
        try {
            if (!flock($file, LOCK_EX)) throw new \RuntimeException('Lock unavailable');
            clearstatcache(true, $path);
            if (is_file($path)) {
                if (filesize($path) > 8388608) throw new \RuntimeException('State too large');
                $raw = file_get_contents($path);
                if ($raw === false) throw new \RuntimeException('Read failed');
                // An existing empty file is corruption, never a fresh counter window.
                $state = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } else $state = [];
            if (!is_array($state)) throw new \RuntimeException('Invalid state');
            [$updated, $result] = $callback($state);
            $encoded = json_encode($updated, JSON_THROW_ON_ERROR);
            $temporary = tempnam($directory, '.login-');
            if ($temporary === false) throw new \RuntimeException('Temporary state unavailable');
            chmod($temporary, 0600);
            $output = fopen($temporary, 'wb');
            if ($output === false) throw new \RuntimeException('Temporary state unavailable');
            try {
                if (fwrite($output, $encoded) !== strlen($encoded) || !fflush($output) || !fsync($output)) throw new \RuntimeException('Write failed');
            } finally { fclose($output); }
            if (!rename($temporary, $path)) throw new \RuntimeException('Atomic replacement failed');
            $temporary = null;
            return $result;
        } catch (\Throwable $error) {
            throw new HttpException(503, 'Sign-in temporarily unavailable.');
        } finally {
            if (is_string($temporary) && is_file($temporary)) unlink($temporary);
            flock($file, LOCK_UN);
            fclose($file);
        }
    }
}
