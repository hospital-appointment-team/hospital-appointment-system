<?php
declare(strict_types=1);
namespace Hospital\Services;

use Hospital\Core\{HttpException, PrivateState};
use Hospital\Models\Model;

final class DurationPolicy
{
    private static bool $held = false;
    private static ?string $identity = null;

    private static function identity(): string
    {
        if (self::$identity === null) {
            $identity = Model::query('SELECT DATABASE() AS database_name, @@server_uuid AS server_uuid')->fetch();
            self::$identity = hash('sha256', $identity['server_uuid'] . ':' . $identity['database_name']);
        }
        return self::$identity;
    }

    /** Shared MySQL mutex is acquired before all booking/status transactions. */
    public static function synchronized(callable $operation): mixed
    {
        if (self::$held) return $operation();
        $name = 'hospital.duration.' . substr(self::identity(), 0, 40);
        if ((int) Model::query('SELECT GET_LOCK(?, 5)', [$name])->fetchColumn() !== 1) throw new HttpException(503, 'Scheduling is busy. Please retry shortly.');
        self::$held = true;
        try { return $operation(); }
        finally {
            self::$held = false;
            try { Model::query('SELECT RELEASE_LOCK(?)', [$name]); }
            catch (\Throwable) { error_log('Scheduling advisory lock release failed; connection closes at request end.'); }
        }
    }

    /** Pure policy rule, also used by unit tests. An absent legacy baseline is never inferred. */
    public static function evaluate(array $state, int $minutes, bool $appointmentsExist, bool $freeze): array
    {
        if ($minutes < 1 || $minutes > 1440) throw new HttpException(503, 'Invalid configured slot duration.');
        if ($state) {
            if (($state['version'] ?? null) !== 1 || !is_int($state['minutes'] ?? null)) throw new HttpException(503, 'Duration baseline is invalid. Contact the administrator.');
            if ($state['minutes'] !== $minutes) throw new HttpException(503, 'Slot duration is frozen. A deliberate migration is required to change it.');
            return $state;
        }
        if ($appointmentsExist) throw new HttpException(503, 'Appointments exist without a duration baseline. A deliberate migration is required.');
        return $freeze ? ['version' => 1, 'minutes' => $minutes] : [];
    }

    public static function verify(int $minutes, bool $freeze = false): void
    {
        if (!self::$held) throw new \LogicException('Duration verification requires the scheduling mutex.');
        // The mutex is held before the transaction starts, so prior app bookings are visible.
        $exists = (bool) Model::query('SELECT id FROM appointments LIMIT 1')->fetchColumn();
        PrivateState::update('duration-' . self::identity() . '.json', static function (array $state) use ($minutes, $exists, $freeze): array {
            return [self::evaluate($state, $minutes, $exists, $freeze), null];
        });
    }
}
