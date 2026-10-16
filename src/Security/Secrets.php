<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Security;

defined('ABSPATH') || exit;

/**
 * Encrypts secrets stored in options with libsodium secretbox (always available:
 * WordPress ships the sodium_compat polyfill).
 *
 * The key comes from MPCL_ENCRYPTION_KEY when defined in wp-config.php, otherwise
 * from the site's auth salt. This protects a leaked database or backup, not a
 * compromised server: whoever can read wp-config.php can decrypt.
 */
final class Secrets
{
    private const PREFIX = 'mpcl:v1:';

    public function isEncrypted(string $value): bool
    {
        return 0 === strncmp($value, self::PREFIX, strlen(self::PREFIX));
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return self::PREFIX . base64_encode($nonce . sodium_crypto_secretbox($plaintext, $nonce, $this->key()));
    }

    /**
     * @return string|null null when the value is not ours or the key no longer matches
     */
    public function decrypt(string $value): ?string
    {
        if (!$this->isEncrypted($value)) {
            return null;
        }

        $payload = base64_decode(substr($value, strlen(self::PREFIX)), true);
        if (false === $payload || strlen($payload) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }

        $plaintext = sodium_crypto_secretbox_open(
            substr($payload, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($payload, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->key()
        );

        return false === $plaintext ? null : $plaintext;
    }

    private function key(): string
    {
        $configured = defined('MPCL_ENCRYPTION_KEY') ? constant('MPCL_ENCRYPTION_KEY') : null;
        $material = is_string($configured) && '' !== $configured ? $configured : wp_salt('auth');

        return sodium_crypto_generichash('mpcl-secrets|' . $material, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }
}
