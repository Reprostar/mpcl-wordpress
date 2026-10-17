<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Security;

defined('ABSPATH') || exit;

/**
 * Where the secret key comes from: MPCL_ENCRYPTION_KEY when it is defined and
 * not empty, otherwise the site's auth salt.
 */
final class KeyMaterial
{
    public const SOURCE_CONSTANT = 'constant';
    public const SOURCE_SALT = 'salt';
    public const SOURCES = [self::SOURCE_CONSTANT, self::SOURCE_SALT];

    public function activeSource(): string
    {
        return null !== $this->constant() ? self::SOURCE_CONSTANT : self::SOURCE_SALT;
    }

    public function active(): string
    {
        return $this->constant() ?? $this->salt();
    }

    /**
     * @return string|null null for the constant when it is not defined
     */
    public function forSource(string $source): ?string
    {
        return self::SOURCE_CONSTANT === $source ? $this->constant() : $this->salt();
    }

    public function constant(): ?string
    {
        $configured = defined('MPCL_ENCRYPTION_KEY') ? constant('MPCL_ENCRYPTION_KEY') : null;

        return is_string($configured) && '' !== $configured ? $configured : null;
    }

    public function salt(): string
    {
        return wp_salt('auth');
    }
}
