<?php
declare(strict_types=1);
namespace Hospital\Controllers;
use Hospital\Core\{Auth, Controller, HttpException, Passwords, Request, Validation};
use Hospital\Models\{Database, Doctor, Model, User};
use Hospital\Services\PhotoUpload;

final class DoctorAdminController extends Controller
{
    public function index(): void
    {
        Auth::requireRole('admin'); $doctors = Doctor::query(Doctor::SELECT . ' ORDER BY u.name,d.id')->fetchAll();
        $this->view('admin/doctors', compact('doctors') + ['title' => 'Doctor management']);
    }
    public function stats(): void
    {
        Auth::requireRole('admin');
        $stats = Model::query('SELECT d.id,COUNT(a.id) AS appointment_count FROM doctors d LEFT JOIN appointments a ON a.doctor_id=d.id GROUP BY d.id')->fetchAll();
        $this->json(['ok' => true, 'doctors' => $stats]);
    }
    public function edit(?string $id = null): void
    {
        Auth::requireRole('admin'); $doctor = $id !== null ? Doctor::find(Request::id($id)) : null;
        if ($id !== null && !$doctor) throw new HttpException(404, 'Doctor not found.');
        $data = $doctor ?? ['available_days' => '']; $errors = [];
        $specializations = Model::query('SELECT * FROM specializations ORDER BY name')->fetchAll();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $body = Request::body();
            foreach (['name','email','specialization_id','bio','consultation_fee'] as $key) $data[$key] = Request::text($body, $key);
            $password = is_string($body['password'] ?? null) ? $body['password'] : ''; $days = $body['available_days'] ?? [];
            if (!is_array($days) || count(array_filter($days, 'is_string')) !== count($days) || array_diff($days, Validation::WEEKDAYS)) { $errors['available_days'] = 'Select only Monday–Friday.'; $days = []; }
            $data['available_days'] = implode(',', array_intersect(Validation::WEEKDAYS, $days));
            if (!Validation::name($data['name'])) $errors['name'] = 'Use a name between 2 and 100 characters.';
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['email']) > 190) $errors['email'] = 'Enter a valid email.';
            if ((!$doctor || $password !== '') && !Validation::password($password)) $errors['password'] = 'Use 8–72 bytes without null characters.';
            if (!in_array($data['specialization_id'], array_map('strval', array_column($specializations, 'id')), true)) $errors['specialization_id'] = 'Select a specialization.';
            if ($data['bio'] === '' || mb_strlen($data['bio']) > 4000) $errors['bio'] = 'Use 1–4000 characters.';
            if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $data['consultation_fee'])) $errors['consultation_fee'] = 'Enter a nonnegative fee with at most two decimal places.';
            $newPhoto = null;
            if (!$errors) {
                try { $newPhoto = PhotoUpload::save(isset($_FILES['photo']) && is_array($_FILES['photo']) ? $_FILES['photo'] : null); }
                catch (HttpException $exception) { if ($exception->status !== 422) throw $exception; $errors['photo'] = $exception->getMessage(); }
                if (!$doctor && !$newPhoto && !$errors) $errors['photo'] = 'Choose a doctor photo.';
            }
            if (!$errors) {
                $db = null; $oldPhoto = null;
                try {
                    $db = Database::connection(); $db->beginTransaction();
                    if ($doctor) {
                        $lockedDoctor = Model::query('SELECT * FROM doctors WHERE id=? FOR UPDATE', [$doctor['id']])->fetch();
                        if (!$lockedDoctor) throw new HttpException(404, 'Doctor not found.');
                        $oldPhoto = $lockedDoctor['photo_path'];
                        $user = User::find((int) $doctor['user_id']);
                        Model::query('UPDATE users SET name=?,email=?,password_hash=? WHERE id=?', [$data['name'],$data['email'],$password !== '' ? Passwords::hash($password) : $user['password_hash'],$doctor['user_id']]);
                        Model::query('UPDATE doctors SET specialization_id=?,bio=?,consultation_fee=?,photo_path=?,available_days=? WHERE id=?', [$data['specialization_id'],$data['bio'],$data['consultation_fee'],$newPhoto ?? $oldPhoto,$data['available_days'],$doctor['id']]);
                    } else {
                        $userId = User::create($data + ['password' => $password], 'doctor');
                        Model::query('INSERT INTO doctors (user_id,specialization_id,bio,consultation_fee,photo_path,available_days) VALUES (?,?,?,?,?,?)', [$userId,$data['specialization_id'],$data['bio'],$data['consultation_fee'],$newPhoto,$data['available_days']]);
                    }
                    $db->commit();
                } catch (\Throwable $exception) {
                    if ($db !== null && $db->inTransaction()) $db->rollBack(); PhotoUpload::remove($newPhoto);
                    if ($exception instanceof \PDOException && $exception->getCode() === '23000') $errors['email'] = 'Email already exists or specialization is no longer available.';
                    else throw $exception;
                }
                if (!$errors) {
                    if ($newPhoto && $doctor) PhotoUpload::remove($oldPhoto);
                    $this->redirect('/admin/doctors', 'Doctor saved.');
                }
            }
        }
        $this->view('admin/doctor-form', compact('doctor','data','errors','specializations') + ['title' => $doctor ? 'Edit doctor' : 'Add doctor'], $errors ? 422 : 200);
    }
    public function deactivate(string $id): void
    {
        Auth::requireRole('admin'); $doctor = Doctor::find(Request::id($id));
        if (!$doctor) throw new HttpException(404, 'Doctor not found.');
        Model::query('UPDATE users SET is_active=0 WHERE id=?', [$doctor['user_id']]);
        $this->redirect('/admin/doctors', 'Doctor deactivated. Existing appointments are preserved.');
    }
}
