<?php
declare(strict_types=1);

return [
    'GET /' => [Hospital\Controllers\HomeController::class, 'index'],
    'GET /register' => [Hospital\Controllers\AuthController::class, 'register'],
    'POST /register' => [Hospital\Controllers\AuthController::class, 'register'],
    'GET /login' => [Hospital\Controllers\AuthController::class, 'login'],
    'POST /login' => [Hospital\Controllers\AuthController::class, 'login'],
    'POST /logout' => [Hospital\Controllers\AuthController::class, 'logout'],
    'GET /admin' => [Hospital\Controllers\HomeController::class, 'admin'],
    'GET /patient' => [Hospital\Controllers\HomeController::class, 'patient'],
    'GET /doctor/dashboard' => [Hospital\Controllers\HomeController::class, 'doctor'],
];
