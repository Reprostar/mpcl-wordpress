<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Support;

defined('ABSPATH') || exit;

/**
 * Converts loosely typed values coming from the API, the database and WordPress
 * options into the types the DTOs declare.
 */
final class Cast
{
    /**
     * @param mixed $value
     */
    public static function nullableInt($value): ?int
    {
        if (null === $value || '' === $value || !is_scalar($value)) {
            return null;
        }

        return (int) $value;
    }

    /**
     * @param mixed $value
     */
    public static function nullableString($value): ?string
    {
        if (null === $value || !is_scalar($value)) {
            return null;
        }

        return (string) $value;
    }

    /**
     * @param mixed $value
     */
    public static function int($value, int $default = 0): int
    {
        return self::nullableInt($value) ?? $default;
    }

    /**
     * @param mixed $value
     */
    public static function string($value, string $default = ''): string
    {
        return self::nullableString($value) ?? $default;
    }

    /**
     * @param mixed $value
     */
    public static function bool($value): bool
    {
        return is_scalar($value) && (bool) $value;
    }

    /**
     * Mirrors PHP's empty() for the scalar values the views test.
     */
    public static function isBlank(?string $value): bool
    {
        return null === $value || '' === $value || '0' === $value;
    }
}
