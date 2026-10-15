<?php
declare(strict_types=1);
namespace Hospital\Services;

use Hospital\Core\{Environment, HttpException, Validation};
use Hospital\Models\Model;

final class AvailabilityService
{
    public static function dates(array $doctor, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $today = $now->setTime(0, 0);
        $offset = Environment::get('BOOKING_INCLUDE_TODAY', 'true') === 'true' ? 0 : 1;
        $days = explode(',', $doctor['available_days']); $dates = [];
        for ($i = $offset; $i < 7 + $offset; $i++) {
            $date = $today->modify("+$i days");
            $dates[$date->format('Y-m-d')] = in_array($date->format('l'), $days, true)
                && in_array($date->format('l'), Validation::WEEKDAYS, true);
        }
        return $dates;
    }

    public static function times(): array
    {
        if (Environment::get('BOOKING_POLICY_CONFIRMED') !== 'true') throw new HttpException(503, 'Booking hours have not been configured by the hospital.');
        $open = Environment::get('CLINIC_OPEN'); $close = Environment::get('CLINIC_CLOSE');
        $minutes = filter_var(Environment::get('SLOT_MINUTES'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1440]]);
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $open) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $close) || $close <= $open || !$minutes) {
            throw new HttpException(503, 'Hospital booking hours are invalid. Contact the administrator.');
        }
        $start = new \DateTimeImmutable('2000-01-01 ' . $open);
        $end = new \DateTimeImmutable('2000-01-01 ' . $close); $times = [];
        for ($time = $start; $time->modify("+$minutes minutes") <= $end; $time = $time->modify("+$minutes minutes")) $times[] = $time->format('H:i');
        if (!$times) throw new HttpException(503, 'Slot duration must fit within clinic hours.');
        return $times;
    }

    public static function eligible(array $doctor, string $date, string $time, ?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable();
        return Validation::date($date) && (self::dates($doctor, $now)[$date] ?? false)
            && in_array($time, self::times(), true)
            && new \DateTimeImmutable($date . ' ' . $time) > $now;
    }

    public static function overlaps(string $first, string $second, int $minutes): bool
    {
        $seconds = static function (string $time): int { $parts = explode(':', $time); return (int) $parts[0] * 3600 + (int) $parts[1] * 60 + (int) ($parts[2] ?? 0); };
        return $seconds($first) < $seconds($second) + $minutes * 60 && $seconds($first) + $minutes * 60 > $seconds($second);
    }

    public static function slots(array $doctor, string $date): array
    {
        return DurationPolicy::synchronized(static fn (): array => self::slotsLocked($doctor, $date));
    }

    private static function slotsLocked(array $doctor, string $date): array
    {
        if (!Validation::date($date)) throw new HttpException(422, 'Use a date in YYYY-MM-DD format.');
        if (!(self::dates($doctor)[$date] ?? false)) return [];
        $times = self::times();
        $minutes = (int) Environment::get('SLOT_MINUTES');
        DurationPolicy::verify($minutes);
        $now = new \DateTimeImmutable();
        $booked = Model::query("SELECT appointment_time FROM appointments WHERE doctor_id=? AND appointment_date=? AND status<>'Cancelled'", [$doctor['id'], $date])->fetchAll(\PDO::FETCH_COLUMN);
        return array_values(array_filter($times, static function (string $time) use ($booked, $date, $minutes, $now): bool {
            if (new \DateTimeImmutable($date . ' ' . $time) <= $now) return false;
            foreach ($booked as $reserved) if (self::overlaps($time, $reserved, $minutes)) return false;
            return true;
        }));
    }
}
