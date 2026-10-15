<?php
declare(strict_types=1);
namespace Hospital\Controllers;

use Hospital\Core\{Auth, Controller, HttpException, Request};
use Hospital\Models\{Database, User};

final class UserController extends Controller
{
    public function index(): void
    {
        Auth::requireRole('admin');
        $users = User::query('SELECT id,name,email,role,is_active FROM users ORDER BY name,id')->fetchAll();
        $this->view('admin/users', compact('users') + ['title' => 'Account control']);
    }

    public function toggle(): void
    {
        $admin = Auth::requireRole('admin');
        $id = Request::id(Request::body()['user_id'] ?? null);
        if ($id === (int) $admin['id']) throw new HttpException(422, 'You cannot deactivate your own account.');
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $user = User::query('SELECT id FROM users WHERE id=? FOR UPDATE', [$id])->fetch();
            if (!$user) throw new HttpException(404, 'Account not found.');
            User::query('UPDATE users SET is_active=1-is_active WHERE id=?', [$id]);
            $active = (bool) User::query('SELECT is_active FROM users WHERE id=?', [$id])->fetchColumn();
            $db->commit();
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }
        $this->json(['ok' => true, 'is_active' => $active]);
    }
}
