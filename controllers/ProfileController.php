<?php
declare(strict_types=1);
namespace Hospital\Controllers;

use Hospital\Core\{Auth, Controller, HttpException, Request, Validation};
use Hospital\Models\{Database, User};

final class ProfileController extends Controller
{
    public function index(): void
    {
        $user = Auth::requireRole('patient');
        $data = $user; $errors = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $body = Request::body();
            foreach (['name', 'phone', 'dob'] as $key) $data[$key] = Request::text($body, $key);
            $errors = Validation::patient($data, false);
            $new = is_string($body['new_password'] ?? null) ? $body['new_password'] : '';
            $current = is_string($body['current_password'] ?? null) ? $body['current_password'] : '';
            if ($new !== '') {
                if (!Validation::password($new)) $errors['new_password'] = 'Use 8–72 bytes without null characters.';
            }
            if (!$errors) {
                $db = Database::connection(); $db->beginTransaction();
                try {
                    $locked = User::query('SELECT password_hash,is_active,role FROM users WHERE id=? FOR UPDATE', [$user['id']])->fetch();
                    if (!$locked || !$locked['is_active'] || $locked['role'] !== 'patient') throw new HttpException(403, 'An active patient account is required.');
                    if ($new !== '' && !password_verify($current, $locked['password_hash'])) {
                        $errors['current_password'] = 'Current password is incorrect.';
                        $db->rollBack();
                    } else {
                        // A profile-only save must never restore a stale hash from another session.
                        User::query('UPDATE users SET name=?,phone=?,dob=? WHERE id=?', [$data['name'],$data['phone'],$data['dob'],$user['id']]);
                        if ($new !== '') User::query('UPDATE users SET password_hash=? WHERE id=?', [password_hash($new, PASSWORD_DEFAULT),$user['id']]);
                        $db->commit();
                    }
                } catch (\Throwable $exception) {
                    if ($db->inTransaction()) $db->rollBack();
                    throw $exception;
                }
                if (!$errors) {
                    if ($new !== '') session_regenerate_id(true);
                    $this->redirect('/patient/profile', 'Profile updated.');
                }
            }
        }
        $upcoming = (int) User::query("SELECT COUNT(*) FROM appointments WHERE patient_id=? AND status IN ('Pending','Confirmed') AND appointment_date>=?", [$user['id'], date('Y-m-d')])->fetchColumn();
        $this->view('patient/profile', compact('data', 'errors', 'upcoming') + ['title' => 'Your profile'], $errors ? 422 : 200);
    }
}
