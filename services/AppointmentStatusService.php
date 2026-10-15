<?php
declare(strict_types=1);
namespace Hospital\Services;

use Hospital\Core\HttpException;
use Hospital\Models\{Appointment, Database, Model};

final class AppointmentStatusService
{
    public static function permitted(string $role, string $old, string $new, bool $owns): bool
    {
        if (!in_array($new, Appointment::STATUSES, true)) return false;
        return match ($role) {
            'admin' => true,
            'patient' => $owns && $old === 'Pending' && $new === 'Cancelled',
            'doctor' => $owns && in_array($old, ['Pending','Confirmed'], true) && in_array($new, ['Completed','No-Show'], true),
            default => false,
        };
    }

    public static function update(array $actor, int $id, string $status, string $reason = ''): void
    {
        if (!in_array($status, Appointment::STATUSES, true)) throw new HttpException(422, 'Invalid appointment status.');
        $db = Database::connection(); $db->beginTransaction();
        try {
            $ref = Model::query('SELECT doctor_id FROM appointments WHERE id=?', [$id])->fetch();
            if (!$ref) throw new HttpException(404, 'Appointment not found.');
            // Always lock in doctor -> appointment order, matching booking and avoiding lock-order inversions.
            $doctor = Model::query('SELECT * FROM doctors WHERE id=? FOR UPDATE', [$ref['doctor_id']])->fetch();
            $currentActor = Model::query('SELECT role,is_active FROM users WHERE id=? FOR UPDATE', [$actor['id']])->fetch();
            if (!$currentActor || !$currentActor['is_active'] || $currentActor['role'] !== $actor['role']) throw new HttpException(403, 'An active authorized account is required.');
            $appointment = Model::query('SELECT * FROM appointments WHERE id=? FOR UPDATE', [$id])->fetch();
            if (!$appointment || !$doctor) throw new HttpException(404, 'Appointment not found.');
            $owns = $actor['role'] === 'patient' ? (int) $appointment['patient_id'] === (int) $actor['id'] : (int) $doctor['user_id'] === (int) $actor['id'];
            if (!self::permitted($actor['role'], $appointment['status'], $status, $owns)) throw new HttpException(403, 'This status change is not allowed.');
            if ($actor['role'] === 'admin' && $status === 'Cancelled') {
                if (mb_strlen($reason) < 3 || mb_strlen($reason) > 4000) throw new HttpException(422, 'A cancellation reason of 3–4000 characters is required.');
            }
            if ($appointment['status'] === 'Cancelled' && in_array($status, ['Pending','Confirmed'], true)) {
                $active = Model::query('SELECT is_active FROM users WHERE id=? FOR UPDATE', [$doctor['user_id']])->fetchColumn();
                if (!$active || !AvailabilityService::eligible($doctor, $appointment['appointment_date'], substr($appointment['appointment_time'],0,5))) throw new HttpException(409, 'This appointment can no longer reserve a slot.');
            }
            // Only a Cancelled -> reserving transition claims an interval again.
            // Existing reservations may be completed/confirmed even while new booking is disabled.
            if ($appointment['status'] === 'Cancelled' && $status !== 'Cancelled') BookingService::assertFree((int) $doctor['id'], $appointment['appointment_date'], $appointment['appointment_time'], $id);
            if ($actor['role'] === 'admin' && $status === 'Cancelled') CancellationAudit::recordRequest((int) $actor['id'], $id, $reason);
            Model::query('UPDATE appointments SET status=? WHERE id=?', [$status,$id]);
            $db->commit();
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack(); throw $exception;
        }
    }
}
