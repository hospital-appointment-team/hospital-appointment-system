<?php
declare(strict_types=1);

putenv('APP_ENV=local');
putenv('SESSION_SECURE=false');
require dirname(__DIR__) . '/config/bootstrap.php';

use Hospital\Core\Security;

$failures = [];
$checks = 0;
$check = static function (bool $condition, string $description) use (&$failures, &$checks): void {
    $checks++;
    if (!$condition) {
        $failures[] = $description;
    }
};

// Check security behavior before emitting output, because sessions use headers.
$check(!Security::validCsrf(null), 'Missing CSRF token is rejected');
$check(!Security::validCsrf('forged'), 'Unissued CSRF token is rejected');
$token = Security::csrfToken();
$check(strlen($token) === 64 && ctype_xdigit($token), 'CSRF token has 256 bits');
$check(Security::validCsrf($token), 'Issued CSRF token is accepted');
$check(!Security::validCsrf(str_repeat('0', 64)), 'Incorrect token is rejected');
$check(!Security::validCsrf(['token']), 'Array token is rejected without a type error');
$check(Security::csrfToken() === $token, 'Token remains stable during the session');
$cookie = session_get_cookie_params();
$check($cookie['httponly'] && $cookie['samesite'] === 'Lax', 'Session cookie protections are configured');
$check(ini_get('session.use_strict_mode') === '1', 'Strict session IDs are enabled');
$check(Security::escape('<script>"&') === '&lt;script&gt;&quot;&amp;', 'HTML special characters are escaped');
$check(\Hospital\Core\Validation::date('2024-02-29'), 'Leap day accepted');
$check(!\Hospital\Core\Validation::date('2025-02-29'), 'Invalid leap day rejected');
$check(!\Hospital\Core\Validation::date('2026-2-01'), 'Noncanonical dates rejected');
$check(!\Hospital\Core\Validation::password(str_repeat('a', 73)), 'Bcrypt truncation prevented');
$check(!\Hospital\Core\Validation::password("password\0"), 'Null-byte password rejected');
$invalid = \Hospital\Core\Validation::patient(['name' => 'A', 'phone' => 'bad', 'dob' => '3000-01-01'], true);
$check(count($invalid) === 6, 'All registration fields are validated');
$hash = password_hash('correct-password', PASSWORD_DEFAULT);
$check(password_verify('correct-password', $hash) && !password_verify('wrong-password', $hash), 'Password hashing and verification');
require __DIR__ . '/view-rendering.php';
session_destroy();


foreach ($failures as $failure) {
    fwrite(STDERR, "FAIL: $failure\n");
}
echo sprintf("%d checks; %d failures\n", $checks, count($failures));
exit($failures === [] ? 0 : 1);
