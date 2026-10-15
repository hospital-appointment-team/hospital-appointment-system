<?php
declare(strict_types=1);
namespace Hospital\Core;

final class Validation
{
    public const BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    public const WEEKDAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

    public static function date(string $value): bool
    {
        if (!preg_match('/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/D', $value)) return false;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    public static function name(string $value): bool
    {
        return mb_strlen($value) >= 2 && mb_strlen($value) <= 100;
    }

    public static function password(string $value): bool
    {
        return strlen($value) >= 8 && strlen($value) <= 72 && !str_contains($value, "\0");
    }

    public static function patient(array $data, bool $registration): array
    {
        $errors = [];
        if (!self::name(Request::text($data, 'name'))) $errors['name'] = 'Use a name between 2 and 100 characters.';
        $phone = Request::text($data, 'phone');
        if (!preg_match('/^\+?[0-9 ()-]{7,25}$/D', $phone) || strlen(preg_replace('/[^0-9]/', '', $phone)) < 7) $errors['phone'] = 'Enter a valid phone number with at least seven digits.';
        $dob = Request::text($data, 'dob');
        if (!self::date($dob) || $dob > date('Y-m-d')) $errors['dob'] = 'Enter a valid date of birth, no later than today.';
        if ($registration) {
            if (!filter_var(Request::text($data, 'email'), FILTER_VALIDATE_EMAIL) || strlen(Request::text($data, 'email')) > 190) $errors['email'] = 'Enter a valid email address.';
            if (!self::password(is_string($data['password'] ?? null) ? $data['password'] : '')) $errors['password'] = 'Use 8–72 bytes without null characters.';
            if (!in_array(Request::text($data, 'blood_group'), self::BLOOD_GROUPS, true)) $errors['blood_group'] = 'Select your blood group.';
        }
        return $errors;
    }
}
