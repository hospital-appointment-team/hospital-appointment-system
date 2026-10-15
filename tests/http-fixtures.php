<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);
$db = getenv('TEST_DB_DATABASE'); $run = getenv('HTTP_TEST_RUN');
if (!is_string($db) || !preg_match('/^[a-zA-Z0-9_]+_test$/D',$db) || !is_string($run) || !preg_match('/^[a-f0-9]{16}$/D',$run)) exit(2);
putenv('DB_DATABASE=' . $db);
require dirname(__DIR__) . '/config/bootstrap.php';
use Hospital\Models\{Database, Model, User};
use Hospital\Services\PhotoUpload;
$email = static fn (string $role): string => "$role-$run@example.test";
$action = $argv[1] ?? '';
if ($action === 'setup') {
    if (getenv('TEST_DB_FRESH_CONFIRM') !== 'true') exit(2);
    foreach (['users','specializations','doctors','appointments'] as $table) {
        if ((int) Model::query('SELECT COUNT(*) FROM ' . $table)->fetchColumn() !== 0) exit(2);
    }
    \Hospital\Services\DurationPolicy::synchronized(static fn () => \Hospital\Services\DurationPolicy::verify(30, true));
    $ids = [];
    foreach (['admin','doctor','patient'] as $role) $ids[$role] = User::create(['name'=>"HTTP $role",'email'=>$email($role),'password'=>'Http-test-only-839!','dob'=>'1990-01-01','phone'=>'01234567890','blood_group'=>'O+'],$role);
    Model::query('INSERT INTO specializations (name) VALUES (?)',["HTTP $run"]);
    $specialization = (int) Database::connection()->lastInsertId();
    Model::query('INSERT INTO doctors (user_id,specialization_id,bio,consultation_fee,available_days) VALUES (?,?,?,?,?)',[$ids['doctor'],$specialization,'HTTP fixture doctor','500.00','Monday,Tuesday,Wednesday,Thursday,Friday']);
    $ids['doctor_profile'] = (int) Database::connection()->lastInsertId();
    $ids['specialization'] = $specialization;
    foreach (['09:00','09:30'] as $index => $time) {
        Model::query("INSERT INTO appointments (patient_id,doctor_id,appointment_date,appointment_time,reason,status) VALUES (?,?,?,?,?,'Pending')",[$ids['patient'],$ids['doctor_profile'],date('Y-m-d'),$time,'HTTP test fixture']);
        $ids['appointment_' . $index] = (int) Database::connection()->lastInsertId();
    }
    echo json_encode($ids, JSON_THROW_ON_ERROR);
} elseif ($action === 'cleanup') {
    $users = User::query('SELECT id FROM users WHERE email LIKE ?', ['%-' . $run . '@example.test'])->fetchAll(PDO::FETCH_COLUMN);
    foreach ($users as $id) {
        Model::query('DELETE a FROM appointments a JOIN doctors d ON d.id=a.doctor_id WHERE d.user_id=?',[$id]);
        Model::query('DELETE FROM appointments WHERE patient_id=?',[$id]);
    }
    foreach ($users as $id) {
        $photo = Model::query('SELECT photo_path FROM doctors WHERE user_id=?',[$id])->fetchColumn();
        if (is_string($photo)) PhotoUpload::remove($photo);
        Model::query('DELETE FROM doctors WHERE user_id=?',[$id]);
    }
    foreach ($users as $id) Model::query('DELETE FROM users WHERE id=?',[$id]);
    Model::query('DELETE FROM specializations WHERE name LIKE ?',['% ' . $run]);
} else exit(2);
