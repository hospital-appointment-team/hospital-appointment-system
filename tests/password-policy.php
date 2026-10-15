<?php
declare(strict_types=1);

$standalonePolicy = !isset($check);
if ($standalonePolicy) {
    require dirname(__DIR__) . '/config/bootstrap.php';
    $checks = 0; $failures = [];
    $check = static function (bool $result, string $message) use (&$checks, &$failures): void {
        $checks++; if (!$result) $failures[] = $message;
    };
}
$realHash = \Hospital\Core\Passwords::hash('Policy-regression-password');
$dummyHash = \Hospital\Core\Passwords::dummyHash();
$realInfo = password_get_info($realHash); $dummyInfo = password_get_info($dummyHash);
$check($realInfo['algo'] === PASSWORD_BCRYPT, 'Real password hashing explicitly uses bcrypt');
$check($realInfo['options'] === \Hospital\Core\Passwords::OPTIONS, 'Real hashing uses centrally configured options');
$check($dummyInfo === $realInfo, 'Dummy and real hashes have identical algorithm and work factor');
$check(!password_needs_rehash($dummyHash, \Hospital\Core\Passwords::ALGORITHM, \Hospital\Core\Passwords::OPTIONS), 'Dummy hash cannot silently drift from policy');
$check((bool) preg_match('/^\$2y\$12\$[.\/A-Za-z0-9]{53}$/D', $dummyHash), 'Dummy is a valid configured bcrypt hash');
$check(\Hospital\Core\Passwords::verifyLogin('Policy-regression-password', $realHash), 'Correct existing-account password verifies');
$check(!\Hospital\Core\Passwords::verifyLogin('Wrong-regression-password', $realHash), 'Wrong existing-account password fails');
$check(!\Hospital\Core\Passwords::verifyLogin('Wrong-regression-password', null), 'Unknown-account password verification fails');
$check(!\Hospital\Core\Passwords::verifyLogin("wrong\0password", $realHash) && !\Hospital\Core\Passwords::verifyLogin("wrong\0password", null), 'Null-byte passwords fail for both paths');
$check(!\Hospital\Core\Passwords::verifyLogin(str_repeat('x', 73), $realHash) && !\Hospital\Core\Passwords::verifyLogin(str_repeat('x', 73), null), 'Overlong passwords fail for both paths');
if ($standalonePolicy) {
    foreach ($failures as $failure) fwrite(STDERR, "FAIL: $failure\n");
    echo "$checks password-policy checks; " . count($failures) . " failures\n";
    exit($failures ? 1 : 0);
}
