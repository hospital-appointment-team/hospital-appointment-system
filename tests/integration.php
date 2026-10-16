<?php
declare(strict_types=1);
// Explicitly provision an isolated database ending in _test and import schema.sql first.
if (PHP_SAPI !== 'cli') exit(2);
$testDatabase = getenv('TEST_DB_DATABASE');
if (!is_string($testDatabase) || !preg_match('/^[a-zA-Z0-9_]+_test$/D', $testDatabase) || getenv('TEST_DB_FRESH_CONFIRM') !== 'true') {
    fwrite(STDERR, "Provision a NEW empty _test database and set TEST_DB_DATABASE and TEST_DB_FRESH_CONFIRM=true. No tests ran.\n"); exit(2);
}
$stateDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hospital-integration-' . bin2hex(random_bytes(12));
if (!mkdir($stateDirectory, 0700)) exit(2);
putenv('STATE_STORAGE_DIR=' . $stateDirectory);
putenv('DB_DATABASE=' . $testDatabase);
putenv('BOOKING_POLICY_CONFIRMED=true'); putenv('CLINIC_OPEN=09:00'); putenv('CLINIC_CLOSE=12:00'); putenv('SLOT_MINUTES=30'); putenv('BOOKING_INCLUDE_TODAY=true');
require dirname(__DIR__) . '/config/bootstrap.php';

use Hospital\Core\HttpException;
use Hospital\Models\{Database, Doctor, Model, User};
use Hospital\Services\{AppointmentStatusService, AvailabilityService, BookingService};

$users = []; $doctorId = null; $secondDoctorId = null; $specializationId = null; $checks = 0;
$check = static function (bool $result, string $message) use (&$checks): void {
    $checks++;
    if (!$result) throw new RuntimeException($message);
};
$expect = static function (callable $action, int $status) use ($check): void {
    try { $action(); $check(false, "Expected HTTP $status"); }
    catch (HttpException $exception) { $check($exception->status === $status, "Expected HTTP $status; received {$exception->status}"); }
};
$suffix = bin2hex(random_bytes(8)); $exit = 0;
try {
    foreach (['users','specializations','doctors','appointments'] as $table) {
        // Table names are fixed literals, never request input.
        if ((int) Model::query('SELECT COUNT(*) FROM ' . $table)->fetchColumn() !== 0) throw new RuntimeException('Test database must be newly provisioned and empty.');
    }
    Model::query('INSERT INTO specializations (name) VALUES (?)', ['Test ' . $suffix]);
    $specializationId = (int) Database::connection()->lastInsertId();
    foreach (['patient','patient','doctor','admin','doctor'] as $index => $role) {
        $users[] = User::create(['name' => "Test $role",'email' => "$index-$suffix@example.test",'password' => 'Test-only-password-492','dob' => '1990-01-01','blood_group' => 'O+','phone' => '+8801700000000'], $role);
    }
    $patient = User::find($users[0]);
    $check(password_verify('Test-only-password-492', $patient['password_hash']), 'Stored password hash verifies');
    $check(!password_needs_rehash($patient['password_hash'], \Hospital\Core\Passwords::ALGORITHM, \Hospital\Core\Passwords::OPTIONS), 'Persisted account hash matches the configured password policy');
    $check($patient['role'] === 'patient', 'Patient role persisted');
    Model::query('UPDATE users SET name=?,phone=?,dob=? WHERE id=?', ['Updated patient','01234567890','1991-02-03',$users[0]]);
    $check(User::find($users[0])['name'] === 'Updated patient', 'Profile fields persist');
    Model::query('INSERT INTO doctors (user_id,specialization_id,bio,consultation_fee,available_days) VALUES (?,?,?,?,?)', [$users[2],$specializationId,'Test doctor','500.00','Monday,Tuesday,Wednesday,Thursday,Friday']);
    $doctorId = (int) Database::connection()->lastInsertId();
    Model::query('INSERT INTO doctors (user_id,specialization_id,bio,consultation_fee,available_days) VALUES (?,?,?,?,?)', [$users[4],$specializationId,'Other doctor','500.00','Monday,Tuesday,Wednesday,Thursday,Friday']);
    $secondDoctorId = (int) Database::connection()->lastInsertId();
    try { Model::query('DELETE FROM specializations WHERE id=?', [$specializationId]); $check(false, 'Assigned specialization must not delete'); }
    catch (PDOException $exception) { $check($exception->getCode() === '23000', 'Foreign key protects assigned specialization'); }
    $doctor = Doctor::find($doctorId); $date = null;
    foreach (AvailabilityService::dates($doctor) as $candidate => $eligible) if ($eligible && new DateTimeImmutable($candidate . ' 09:00') > new DateTimeImmutable()) { $date = $candidate; break; }
    $check($date !== null, 'Future eligible test date exists');
    $id = BookingService::book($users[0],$doctorId,$date,'09:00','Test visit');
    $expect(fn () => AppointmentStatusService::update(User::find($users[4]),$id,'Completed'),403);
    $secondId = BookingService::book($users[0],$secondDoctorId,$date,'09:00','Different doctor same time');
    $check($secondId !== $id, 'Two doctors can reserve the same time independently');
    putenv('SLOT_MINUTES=15');
    $expect(fn () => AvailabilityService::slots($doctor,$date),503);
    $expect(fn () => BookingService::book($users[1],$doctorId,$date,'09:15','Duration drift must fail'),503);
    putenv('SLOT_MINUTES=30');
    $check(in_array('09:30',AvailabilityService::slots($doctor,$date),true), 'Adjacent original-duration slot is available');
    $check(!in_array('09:00',AvailabilityService::slots($doctor,$date),true), 'Booked slot excluded');
    $expect(fn () => BookingService::book($users[1],$doctorId,$date,'09:00','Duplicate'),409);
    $expect(fn () => AppointmentStatusService::update(User::find($users[1]),$id,'Cancelled'),403);
    AppointmentStatusService::update(User::find($users[0]),$id,'Cancelled');
    $replacement = BookingService::book($users[1],$doctorId,$date,'09:00','Replacement');
    $check($replacement !== $id, 'Cancelled slot can be rebooked');
    $expect(fn () => AppointmentStatusService::update(User::find($users[3]),$id,'Confirmed'),409);
    $expect(fn () => AppointmentStatusService::update(User::find($users[3]),$replacement,'Cancelled',''),422);
    putenv('BOOKING_POLICY_CONFIRMED=false');
    $expect(fn () => AppointmentStatusService::update(User::find($users[3]),$id,'No-Show'),503);
    AppointmentStatusService::update(User::find($users[2]),$replacement,'Completed');
    putenv('BOOKING_POLICY_CONFIRMED=true');
    $check(Model::query('SELECT status FROM appointments WHERE id=?',[$replacement])->fetchColumn() === 'Completed', 'Doctor status update persists');
    $expect(fn () => AppointmentStatusService::update(User::find($users[2]),$replacement,'No-Show'),403);
    Model::query('UPDATE users SET is_active=0 WHERE id=?',[$users[2]]);
    $expect(fn () => BookingService::book($users[0],$doctorId,$date,'09:30','Inactive doctor'),409);
    Model::query('UPDATE users SET is_active=1 WHERE id=?',[$users[2]]);
    // Nonaligned existing time must block an overlapping grid slot, not just an exact match.
    Model::query("INSERT INTO appointments (patient_id,doctor_id,appointment_date,appointment_time,reason,status) VALUES (?,?,?,?,?,'Pending')",[$users[0],$doctorId,$date,'09:45','Legacy overlap fixture']);
    $expect(fn () => BookingService::book($users[0],$doctorId,$date,'09:30','Overlapping visit'),409);
    $check(!in_array('09:30',AvailabilityService::slots($doctor,$date),true), 'Overlapping slot excluded from API service');
    if (!function_exists('proc_open')) throw new RuntimeException('proc_open is required for the concurrency test.');
    $gate = tempnam(sys_get_temp_dir(),'hospital-gate-'); unlink($gate); $jobs = [];
    try {
        foreach ([$users[0],$users[1]] as $patientId) {
            $pipes = [];
            $process = proc_open([PHP_BINARY,__DIR__.'/booking-worker.php',(string)$patientId,(string)$doctorId,$date,'11:00',$gate], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,BASE_PATH);
            if (!is_resource($process)) throw new RuntimeException('Cannot start concurrency worker.');
            fclose($pipes[0]); $jobs[] = [$process,$pipes];
        }
        touch($gate); $results = [];
        foreach ($jobs as [$process,$pipes]) {
            $results[] = stream_get_contents($pipes[1]); fclose($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[2]);
            $check(proc_close($process) === 0, 'Concurrency worker exits successfully');
        }
        $jobs = []; sort($results);
        $check($results === ['booked','conflict'], 'Exactly one simultaneous booking succeeds');
        $check((int) Model::query("SELECT COUNT(*) FROM appointments WHERE doctor_id=? AND appointment_date=? AND appointment_time='11:00' AND status<>'Cancelled'",[$doctorId,$date])->fetchColumn() === 1, 'Database contains only one reserved appointment');
    } finally {
        foreach ($jobs as [$process,$pipes]) { if (is_resource($process)) proc_terminate($process); foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe); if (is_resource($process)) proc_close($process); }
        if (is_file($gate)) unlink($gate);
    }
    // A cancelled interval is contested by reactivation and a new booking.
    $raceId = BookingService::book($users[0],$doctorId,$date,'11:30','Reactivation race');
    AppointmentStatusService::update(User::find($users[0]),$raceId,'Cancelled');
    $race = static function (array $commands) use ($check): array {
        $gate = tempnam(sys_get_temp_dir(),'hospital-status-gate-'); unlink($gate); $jobs = [];
        try {
            foreach ($commands as $arguments) {
                $arguments[4] = $gate;
                $pipes = [];
                $process = proc_open(array_merge([PHP_BINARY,__DIR__.'/booking-worker.php'],$arguments),[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,BASE_PATH);
                if (!is_resource($process)) throw new RuntimeException('Cannot start race worker.');
                fclose($pipes[0]); $jobs[] = [$process,$pipes];
            }
            touch($gate); $results = [];
            foreach ($jobs as [$process,$pipes]) {
                $results[] = stream_get_contents($pipes[1]); fclose($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[2]);
                $check(proc_close($process) === 0, 'Status race worker exits successfully');
            }
            $jobs = []; sort($results); return $results;
        } finally {
            foreach ($jobs as [$process,$pipes]) { if (is_resource($process)) proc_terminate($process); foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe); if (is_resource($process)) proc_close($process); }
            if (is_file($gate)) unlink($gate);
        }
    };
    $results = $race([
        [(string)$users[3],(string)$doctorId,$date,'11:30','', 'restore',(string)$raceId],
        [(string)$users[1],(string)$doctorId,$date,'11:30','', 'book','0'],
    ]);
    $check($results === ['booked','conflict'] || $results === ['conflict','restored'], 'Exactly one new booking or reactivation succeeds');
    $check((int) Model::query("SELECT COUNT(*) FROM appointments WHERE doctor_id=? AND appointment_date=? AND appointment_time='11:30' AND status<>'Cancelled'",[$doctorId,$date])->fetchColumn() === 1, 'Reactivation race preserves one reservation');
    $cancelRaceId = BookingService::book($users[0],$doctorId,$date,'10:30','Cancellation/reactivation race');
    $results = $race([
        [(string)$users[3],(string)$doctorId,$date,'10:30','', 'cancel',(string)$cancelRaceId],
        [(string)$users[3],(string)$doctorId,$date,'10:30','', 'restore',(string)$cancelRaceId],
    ]);
    $check($results === ['cancelled','restored'], 'Concurrent cancellation and confirmation serialize successfully');
    $check(in_array(Model::query('SELECT status FROM appointments WHERE id=?',[$cancelRaceId])->fetchColumn(),['Cancelled','Confirmed'],true), 'Final race status follows one serialized ordering');
    echo "$checks integration checks passed.\n";
} catch (Throwable $exception) {
    fwrite(STDERR,'Integration suite failed: ' . get_class($exception) . ". Check local test configuration.\n"); $exit = 1;
} finally {
    if ($doctorId !== null) { Model::query('DELETE FROM appointments WHERE doctor_id=?',[$doctorId]); Model::query('DELETE FROM doctors WHERE id=?',[$doctorId]); }
    if ($secondDoctorId !== null) { Model::query('DELETE FROM appointments WHERE doctor_id=?',[$secondDoctorId]); Model::query('DELETE FROM doctors WHERE id=?',[$secondDoctorId]); }
    if ($specializationId !== null) Model::query('DELETE FROM specializations WHERE id=?',[$specializationId]);
    foreach ($users as $userId) Model::query('DELETE FROM users WHERE id=?',[$userId]);
    $temporaryRoot = realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR;
    $resolvedState = realpath($stateDirectory);
    if ($resolvedState !== false && str_starts_with($resolvedState, $temporaryRoot)) {
        foreach (glob($resolvedState . DIRECTORY_SEPARATOR . '*.json') as $file) unlink($file);
        rmdir($resolvedState);
    }
}
exit($exit);
