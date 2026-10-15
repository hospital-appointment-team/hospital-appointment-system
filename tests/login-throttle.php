<?php
declare(strict_types=1);
return static function (callable $check): void {
    $class = \Hospital\Services\LoginThrottle::class;
    $state = [];
    for ($i = 1; $i <= 5; $i++) {
        [$state, $result] = $class::attempt($state, 'Patient@example.com', '127.0.0.1', 1000, (string) $i);
        $check($result['allowed'], 'First five account attempts allowed');
    }
    [$state, $result] = $class::attempt($state, ' patient@example.COM ', '127.0.0.2', 1001, 'blocked');
    $check(!$result['allowed'] && $result['retry'] === 29, 'Normalized account blocked across IPs');
    [$state, $result] = $class::attempt($state, 'patient@example.com', '127.0.0.2', 1030, 'sixth');
    $check($result['allowed'], 'Account allowed after backoff');
    [$state, $result] = $class::attempt($state, 'patient@example.com', '127.0.0.2', 1031, 'blocked');
    $check(!$result['allowed'] && $result['retry'] === 59, 'Backoff increases without blocked requests extending it');
    [$state, $result] = $class::attempt($state, 'patient@example.com', '127.0.0.2', 1900, 'reset');
    $check($result['allowed'], 'Expired account window resets');
    $state = [];
    for ($i = 0; $i < 50; $i++) [$state, $result] = $class::attempt($state, "account$i@example.test", '127.0.0.1', 1000, (string) $i);
    [$state, $result] = $class::attempt($state, 'new@example.test', '127.0.0.1', 1001, 'blocked');
    $check(!$result['allowed'] && $result['retry'] === 899, 'IP budget covers different accounts');
    $check($class::sourceIp(['REMOTE_ADDR' => '::1', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4']) === '::1', 'Forwarded headers ignored');
    $check($class::sourceIp(['REMOTE_ADDR' => 'invalid']) === 'unknown', 'Invalid source uses shared restricted bucket');
    try { $class::attempt(['bad' => []], 'a', 'b', 1000, 'ticket'); $check(false, 'Corrupt state rejected'); }
    catch (\Hospital\Core\HttpException $error) { $check($error->status === 503, 'Corrupt state fails closed'); }
    $previous = getenv('AUTH_THROTTLE_DIR');
    $directory = sys_get_temp_dir() . '/hospital-auth-test-' . bin2hex(random_bytes(8));
    putenv('AUTH_THROTTLE_DIR=' . $directory);
    try {
        $old = $class::reserve('success@example.test');
        $new = $class::reserve('success@example.test');
        $class::success($old);
        $saved = json_decode(file_get_contents($directory . '/login-attempts.json'), true, 512, JSON_THROW_ON_ERROR);
        $check($saved[$new['key']]['count'] === 2, 'Earlier successful request cannot clear newer reservation');
        $class::success($new);
        $saved = json_decode(file_get_contents($directory . '/login-attempts.json'), true, 512, JSON_THROW_ON_ERROR);
        $check(!isset($saved[$new['key']]) && count($saved) === 1, 'Success clears latest account but retains IP budget');
        file_put_contents($directory . '/login-attempts.json', '{corrupt');
        try { $class::reserve('another@example.test'); $check(false, 'Invalid persisted JSON rejected'); }
        catch (\Hospital\Core\HttpException $error) { $check($error->status === 503, 'Invalid persisted JSON fails closed'); }
    } finally {
        putenv($previous === false ? 'AUTH_THROTTLE_DIR' : 'AUTH_THROTTLE_DIR=' . $previous);
        if (is_file($directory . '/login-attempts.json')) unlink($directory . '/login-attempts.json');
        if (is_dir($directory)) rmdir($directory);
    }
};
