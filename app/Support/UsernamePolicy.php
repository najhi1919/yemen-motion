<?php

namespace App\Support;

final class UsernamePolicy
{
    public const MIN_LENGTH = 4;

    public const PRIVILEGED_MIN_LENGTH = 2;

    public const MAX_LENGTH = 24;

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower(trim($value));

        return $normalized === '' ? null : $normalized;
    }

    public static function isValid(string $value, bool $allowPrivileged = false): bool
    {
        $normalized = self::normalize($value);
        $minimumLength = $allowPrivileged
            ? self::PRIVILEGED_MIN_LENGTH
            : self::MIN_LENGTH;

        if (
            $normalized === null
            || strlen($normalized) < $minimumLength
            || strlen($normalized) > self::MAX_LENGTH
        ) {
            return false;
        }

        if (! preg_match('/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/D', $normalized)) {
            return false;
        }

        if (! preg_match('/[a-z]/', $normalized)) {
            return false;
        }

        return ! self::isReserved($normalized);
    }

    public static function isReserved(string $value): bool
    {
        $normalized = self::normalize($value);
        $reserved = array_map(
            static fn (mixed $name): ?string => self::normalize((string) $name),
            config('usernames.reserved', []),
        );

        return in_array($normalized, $reserved, true);
    }
}
