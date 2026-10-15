<?php
declare(strict_types=1);

return [
    'GET /' => [Hospital\Controllers\HomeController::class, 'index'],
    'GET /register' => [Hospital\Controllers\AuthController::class, 'register'],
    'POST /register' => [Hospital\Controllers\AuthController::class, 'register'],
    'GET /login' => [Hospital\Controllers\AuthController::class, 'login'],
    'POST /login' => [Hospital\Controllers\AuthController::class, 'login'],
    'POST /logout' => [Hospital\Controllers\AuthController::class, 'logout'],
    'GET /patient/profile' => [Hospital\Controllers\ProfileController::class, 'index'],
    'POST /patient/profile' => [Hospital\Controllers\ProfileController::class, 'index'],
    'GET /admin' => [Hospital\Controllers\HomeController::class, 'admin'],
    'GET /admin/users' => [Hospital\Controllers\UserController::class, 'index'],
    'POST /api/users/toggle-active' => [Hospital\Controllers\UserController::class, 'toggle'],
    'GET /admin/specializations' => [Hospital\Controllers\SpecializationController::class, 'index'],
    'POST /admin/specializations' => [Hospital\Controllers\SpecializationController::class, 'index'],
    'GET /admin/doctors' => [Hospital\Controllers\DoctorAdminController::class, 'index'],
    'GET /admin/doctors/create' => [Hospital\Controllers\DoctorAdminController::class, 'edit'],
    'POST /admin/doctors/create' => [Hospital\Controllers\DoctorAdminController::class, 'edit'],
    'GET /admin/doctors/{id}/edit' => [Hospital\Controllers\DoctorAdminController::class, 'edit'],
    'POST /admin/doctors/{id}/edit' => [Hospital\Controllers\DoctorAdminController::class, 'edit'],
    'POST /admin/doctors/{id}/deactivate' => [Hospital\Controllers\DoctorAdminController::class, 'deactivate'],
    'GET /api/doctors/stats' => [Hospital\Controllers\DoctorAdminController::class, 'stats'],
    'GET /patient' => [Hospital\Controllers\HomeController::class, 'patient'],
    'GET /doctor/dashboard' => [Hospital\Controllers\HomeController::class, 'doctor'],
];
