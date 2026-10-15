<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/config/bootstrap.php';

use Hospital\Core\Validation;
use Hospital\Models\User;

// Password is read from stdin, never from a command-line argument or source file.
if ($argc !== 3) {
    fwrite(STDERR, "Usage: php scripts/create-admin.php NAME EMAIL < password-file\nUse a protected temporary file; delete it after provisioning.\n");
    exit(1);
}
$password = rtrim(stream_get_contents(STDIN), "\r\n");
if (!Validation::name($argv[1]) || !filter_var($argv[2], FILTER_VALIDATE_EMAIL) || strlen($argv[2]) > 190 || !Validation::password($password)) {
    fwrite(STDERR, "Invalid name, email, or password (8–72 bytes).\n"); exit(1);
}
try {
    $id = User::create(['name' => $argv[1], 'email' => $argv[2], 'password' => $password], 'admin');
    echo "Admin account created: $id\n";
} catch (Throwable) {
    fwrite(STDERR, "Provisioning failed. Check configuration and whether the account already exists.\n"); exit(1);
}
