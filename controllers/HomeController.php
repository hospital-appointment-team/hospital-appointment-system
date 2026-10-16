<?php
declare(strict_types=1);

namespace Hospital\Controllers;

use Hospital\Core\{Auth, Controller};

final class HomeController extends Controller
{
    public function index(): void
    {
        $this->view('home', ['title' => 'Care that fits your day']);
    }

    public function admin(): void
    {
        Auth::requireRole('admin');
        $this->view('admin/home', ['title' => 'Hospital overview']);
    }
}
