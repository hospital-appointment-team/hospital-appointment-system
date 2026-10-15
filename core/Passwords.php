<?php
declare(strict_types=1);
namespace Hospital\Core;

final class Passwords
{
    public const ALGORITHM = PASSWORD_BCRYPT;
    public const OPTIONS = ['cost' => 12];
    // Generated from random, discarded input. This is a work factor, never an account credential.
    private const DUMMY_HASH = '$2y$12$ReOsPpwAzKJuvzYLBV094OfcXPKO3k.CfeBFwIMKFfzjhup85ByIa';

    public static function hash(string $password): string
    {
        return password_hash($password, self::ALGORITHM, self::OPTIONS);
    }

    public static function dummyHash(): string
    {
        // Fail closed if a future policy edit forgets to replace the precomputed dummy.
        if (password_needs_rehash(self::DUMMY_HASH, self::ALGORITHM, self::OPTIONS)) {
            throw new \LogicException('Dummy password hash must match the password policy.');
        }
        return self::DUMMY_HASH;
    }

    public static function verifyLogin(string $password, ?string $storedHash): bool
    {
        $dummy = self::dummyHash();
        $hash = $storedHash ?? $dummy;
        $verified = strlen($password) <= 72 && !str_contains($password, "\0") && password_verify($password, $hash);
        // Even a match against dummy work must never authenticate a nonexistent account.
        return $storedHash !== null && $verified;
    }
}
