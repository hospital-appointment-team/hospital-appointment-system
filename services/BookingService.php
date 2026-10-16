<?php
declare(strict_types=1);
namespace Hospital\Services;

use Hospital\Core\{Environment, HttpException};
use Hospital\Models\{Database, Model};

final class BookingService
{
    public static function book(int $patientId, int $doctorId, string $date, string $time, string $reason): int
    {
        return DurationPolicy::synchronized(static fn (): int => self::bookLocked($patientId, $doctorId, $date, $time, $reason));
    }

    private static function bookLocked(int $patientId, int $doctorId, string $date, string $time, string $reason): int
    {
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 4000) throw new HttpException(422, 'Reason must contain 3–4000 characters.');
        $db = Database::connection(); $db->beginTransaction();
        try {
            // A single per-doctor mutex serializes all slot inserts/status changes, even when no appointment exists yet.
            $doctor = Model::query('SELECT * FROM doctors WHERE id=? FOR UPDATE', [$doctorId])->fetch();
            if (!$doctor) throw new HttpException(404, 'Doctor not found.');
            $staff = Model::query('SELECT is_active,role FROM users WHERE id=? FOR UPDATE', [$doctor['user_id']])->fetch();
            if (!$staff || !$staff['is_active'] || $staff['role'] !== 'doctor') throw new HttpException(409, 'This doctor is no longer available.');
            $patient = Model::query("SELECT id FROM users WHERE id=? AND role='patient' AND is_active=1 FOR UPDATE", [$patientId])->fetch();
            if (!$patient) throw new HttpException(403, 'An active patient account is required.');
            if (!AvailabilityService::eligible($doctor, $date, $time)) throw new HttpException(422, 'Choose an available future date and time.');
            self::assertFree($doctorId, $date, $time);
            // Persist before insert/commit: a crash can freeze early, never permit duration drift.
            DurationPolicy::verify((int) Environment::get('SLOT_MINUTES'), true);
            Model::query("INSERT INTO appointments (patient_id,doctor_id,appointment_date,appointment_time,reason,status) VALUES (?,?,?,?,?,'Pending')", [$patientId,$doctorId,$date,$time,$reason]);
            $id = (int) $db->lastInsertId(); $db->commit();
            return $id;
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }
    }

    public static function assertFree(int $doctorId, string $date, string $time, int $except = 0): void
    {
        // Called only while holding the doctor row lock. Locking read avoids stale snapshots under REPEATABLE READ.
        // Never guess an interval when clinic settings are absent or invalid.
        AvailabilityService::times();
        DurationPolicy::verify((int) Environment::get('SLOT_MINUTES'));
        $duration = (int) Environment::get('SLOT_MINUTES') * 60;
        $parts = explode(':', $time); $start = (int) $parts[0] * 3600 + (int) $parts[1] * 60 + (int) ($parts[2] ?? 0);
        $conflict = Model::query("SELECT id FROM appointments WHERE doctor_id=? AND appointment_date=? AND status<>'Cancelled' AND id<>? AND TIME_TO_SEC(appointment_time)<? AND TIME_TO_SEC(appointment_time)+?>? FOR UPDATE", [$doctorId,$date,$except,$start+$duration,$duration,$start])->fetch();
        if ($conflict) throw new HttpException(409, 'This slot has already been booked. Please choose another time.');
    }
}
