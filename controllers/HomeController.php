<?php
declare(strict_types=1);
namespace Hospital\Controllers;
final class HomeController
{
    public function index(): void
    {
        require BASE_PATH . '/views/home.php';
    }
}
