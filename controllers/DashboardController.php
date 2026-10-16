<?php
declare(strict_types=1);
namespace Hospital\Controllers;

use Hospital\Core\{Auth, Controller, HttpException, Request, Validation};
use Hospital\Models\{Appointment, Doctor};
use Hospital\Services\AvailabilityService;

final class DashboardController extends Controller
{
    public function doctor(): void
    {
        $user = Auth::requireRole('doctor');
        $doctor = Doctor::query('SELECT * FROM doctors WHERE user_id=?', [$user['id']])->fetch();
        if (!$doctor) throw new HttpException(404, 'Doctor profile not found. Contact admin.');
        $today = date('Y-m-d');
        $appointments = Appointment::query(Appointment::SELECT . ' WHERE a.doctor_id=? AND a.appointment_date=? ORDER BY a.appointment_time,a.id', [$doctor['id'],$today])->fetchAll();
        $monday = (new \DateTimeImmutable())->modify('monday this week')->setTime(0,0);
        $friday = $monday->modify('+4 days');
        $weekly = Appointment::query(Appointment::SELECT . ' WHERE a.doctor_id=? AND a.appointment_date BETWEEN ? AND ? ORDER BY a.appointment_time,a.id', [$doctor['id'],$monday->format('Y-m-d'),$friday->format('Y-m-d')])->fetchAll();
        $days = []; for ($i=0; $i<5; $i++) $days[] = $monday->modify("+$i days")->format('Y-m-d');
        $schedule = []; $times = [];
        foreach ($weekly as $item) {
            $time = substr($item['appointment_time'],0,5); $times[] = $time; $schedule[$time][$item['appointment_date']][] = $item;
        }
        $scheduleNotice = null;
        try { $times = array_merge($times, AvailabilityService::times()); }
        catch (HttpException $exception) { $scheduleNotice = $exception->getMessage(); }
        $times = array_values(array_unique($times)); sort($times);
        $this->view('doctor/dashboard', compact('appointments','days','schedule','times','scheduleNotice') + ['title' => 'My schedule']);
    }

    public function admin(): void
    {
        Auth::requireRole('admin'); $where = []; $params = []; $errors = [];
        $filters = []; foreach (['doctor_id','date','status'] as $key) $filters[$key] = Request::text($_GET, $key);
        if ($filters['doctor_id'] !== '') { $where[] = 'a.doctor_id=?'; $params[] = Request::id($filters['doctor_id']); }
        if ($filters['date'] !== '') {
            if (!Validation::date($filters['date'])) $errors['date'] = 'Enter a valid date.';
            else { $where[] = 'a.appointment_date=?'; $params[] = $filters['date']; }
        }
        if ($filters['status'] !== '') {
            if (!in_array($filters['status'], Appointment::STATUSES, true)) $errors['status'] = 'Select a valid status.';
            else { $where[] = 'a.status=?'; $params[] = $filters['status']; }
        }
        $doctors = Doctor::query(Doctor::SELECT . ' ORDER BY u.name')->fetchAll();
        $appointments = $errors ? [] : Appointment::query(Appointment::SELECT . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY a.appointment_date DESC,a.appointment_time,a.id', $params)->fetchAll();
        $this->view('admin/appointments', compact('appointments','doctors','filters','errors') + ['title' => 'Appointment management'], $errors ? 422 : 200);
    }
}
