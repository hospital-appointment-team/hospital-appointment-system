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
use Hospital\Services\{AppointmentStatusService, AvailabilityService};
$check(AppointmentStatusService::permitted('patient', 'Pending', 'Cancelled', true), 'Patient can cancel own pending appointment');
$check(!AppointmentStatusService::permitted('patient', 'Confirmed', 'Cancelled', true), 'Patient cannot cancel confirmed appointment');
$check(!AppointmentStatusService::permitted('patient', 'Pending', 'Cancelled', false), 'Patient cannot cancel another patient appointment');
$check(AppointmentStatusService::permitted('doctor', 'Confirmed', 'Completed', true), 'Doctor can complete own confirmed appointment');
$check(!AppointmentStatusService::permitted('doctor', 'Confirmed', 'Completed', false), 'Doctor cannot change another doctor appointment');
$check(!AppointmentStatusService::permitted('doctor', 'Completed', 'No-Show', true), 'Doctor terminal state cannot be overwritten');
$check(!AppointmentStatusService::permitted('doctor', 'Pending', 'Confirmed', true), 'Doctor cannot confirm appointments');
$check(AppointmentStatusService::permitted('admin', 'Completed', 'Cancelled', false), 'Admin may cancel any status');
$check(!AppointmentStatusService::permitted('admin', 'Pending', 'unknown', true), 'Invalid status is rejected');
$check(AvailabilityService::overlaps('09:00','09:15',30), 'Overlapping intervals detected');
$check(!AvailabilityService::overlaps('09:00','09:30',30), 'Adjacent intervals do not overlap');
$check(AvailabilityService::overlaps('09:15','09:00',30), 'Overlap detection is symmetric');
putenv('BOOKING_POLICY_CONFIRMED=false');
try { AvailabilityService::times(); $check(false, 'Unconfigured booking is disabled'); }
catch (\Hospital\Core\HttpException $exception) { $check($exception->status === 503, 'Unconfigured booking is disabled'); }
// Explicit fixture policy, not hospital defaults.
putenv('BOOKING_POLICY_CONFIRMED=true'); putenv('CLINIC_OPEN=09:00'); putenv('CLINIC_CLOSE=10:00'); putenv('SLOT_MINUTES=30');
$check(AvailabilityService::times() === ['09:00','09:30'], 'Full slots fit within clinic hours');
putenv('SLOT_MINUTES=0');
try { AvailabilityService::times(); $check(false, 'Zero slot length rejected'); }
catch (\Hospital\Core\HttpException $exception) { $check($exception->status === 503, 'Zero slot length rejected'); }
putenv('SLOT_MINUTES=30'); putenv('BOOKING_INCLUDE_TODAY=true');
$fixtureNow = new DateTimeImmutable('2026-10-16 09:10:00');
$fixtureDoctor = ['available_days' => 'Monday,Wednesday,Friday'];
$dates = AvailabilityService::dates($fixtureDoctor, $fixtureNow);
$check(count($dates) === 7 && array_key_first($dates) === '2026-10-16', 'Seven-day window includes today');
$check($dates['2026-10-16'] && !$dates['2026-10-17'] && $dates['2026-10-19'], 'Weekdays follow doctor availability');
$check(!AvailabilityService::eligible($fixtureDoctor, '2026-10-16', '09:00', $fixtureNow), 'Elapsed slots rejected');
$check(!AvailabilityService::eligible($fixtureDoctor, '2026-10-16', '09:15', $fixtureNow), 'Off-grid slot rejected');
$check(AvailabilityService::eligible($fixtureDoctor, '2026-10-16', '09:30', $fixtureNow), 'Future aligned slot accepted');
$check(!AvailabilityService::eligible($fixtureDoctor, '2026-10-17', '09:30', $fixtureNow), 'Unavailable weekday rejected');
require __DIR__ . '/view-rendering.php';
(require __DIR__ . '/duration-policy.php')($check);
session_destroy();

foreach ($failures as $failure) {
    fwrite(STDERR, "FAIL: $failure\n");
}
echo sprintf("%d checks; %d failures\n", $checks, count($failures));
exit($failures === [] ? 0 : 1);
