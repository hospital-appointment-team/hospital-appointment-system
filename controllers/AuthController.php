<?php
declare(strict_types=1);
namespace Hospital\Controllers;

use Hospital\Core\{Auth, Controller, Request, Validation};
use Hospital\Models\User;

final class AuthController extends Controller
{
    public function register(): void
    {
        $data = []; $errors = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $body = Request::body();
            foreach (['name', 'email', 'dob', 'phone', 'blood_group'] as $key) $data[$key] = Request::text($body, $key);
            $data['password'] = is_string($body['password'] ?? null) ? $body['password'] : '';
            $errors = Validation::patient($data, true);
            if (!$errors) {
                try {
                    User::create($data);
                    $this->redirect('/login', 'Account created. Please sign in.');
                } catch (\PDOException $exception) {
                    if ($exception->getCode() !== '23000') throw $exception;
                    $errors['email'] = 'An account already uses that email address.';
                }
            }
            unset($data['password']);
        }
        $this->view('auth/register', compact('data', 'errors') + ['title' => 'Create your account'], $errors ? 422 : 200);
    }

    public function login(): void
    {
        $errors = []; $data = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $body = Request::body();
            $data['email'] = Request::text($body, 'email');
            $password = is_string($body['password'] ?? null) ? $body['password'] : '';
            $user = User::byEmail($data['email']);
            // A fixed valid hash keeps missing-user verification on the password-hashing path.
            $hash = $user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
            $verified = strlen($password) <= 72 && !str_contains($password, "\0") && password_verify($password, $hash);
            if (!$user || !$verified) {
                $errors['login'] = 'Email or password is incorrect.';
            } elseif (!(bool) $user['is_active']) {
                $errors['login'] = 'Your account has been deactivated. Contact admin.';
            } else {
                Auth::login($user);
                $this->redirect(Auth::home($user['role']));
            }
        }
        $this->view('auth/login', compact('errors', 'data') + ['title' => 'Sign in'], $errors ? 422 : 200);
    }

    public function logout(): void
    {
        Auth::logout();
        $this->redirect('/login', 'You have signed out.');
    }
}
