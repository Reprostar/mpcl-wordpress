<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Settings;

use Reprostar\MpclWordpress\Security\Secrets;

defined('ABSPATH') || exit;

/**
 * Maps the `mpcl-options` option to Settings. The API token is stored encrypted
 * under the same key; a plain-text token left by 1.x is read as is and encrypted
 * on the next load.
 */
final class SettingsRepository
{
    public const OPTION = 'mpcl-options';

    private Secrets $secrets;

    public function __construct(Secrets $secrets)
    {
        $this->secrets = $secrets;
    }

    public function load(): Settings
    {
        $option = get_option(self::OPTION);

        if (!is_array($option)) {
            $settings = new Settings();
            $this->save($settings);

            return $settings;
        }

        $settings = $this->fromOption($option);

        $storedToken = $option['api_token'] ?? '';
        if (is_string($storedToken) && '' !== $storedToken && !$this->secrets->isEncrypted($storedToken)) {
            $this->save($settings);
        }

        return $settings;
    }

    /**
     * @param Secrets|null $secrets encrypts the token with other key material (re-keying)
     */
    public function save(Settings $settings, ?Secrets $secrets = null): void
    {
        update_option(self::OPTION, $this->toOption($settings, $secrets));
    }

    /**
     * The raw stored token: ciphertext, plain text left by 1.x, or empty.
     */
    public function loadStoredToken(): string
    {
        $option = get_option(self::OPTION);

        return is_array($option) && is_string($option['api_token'] ?? null) ? $option['api_token'] : '';
    }

    /**
     * @param array<mixed> $option
     */
    public function fromOption(array $option): Settings
    {
        $settings = Settings::fromArray($option);

        if ($this->secrets->isEncrypted($settings->apiToken)) {
            $stored = $settings->apiToken;
            $token = $this->secrets->decrypt($stored);
            $fallback = $this->secrets->fallback();
            if (null === $token && null !== $fallback) {
                $token = $fallback->decrypt($stored);
            }

            $settings->apiToken = $token ?? '';
            $settings->apiTokenUnreadable = null === $token;
            $settings->storedApiToken = $stored;
            $settings->loadedApiToken = $settings->apiToken;
        }

        return $settings;
    }

    /**
     * An unchanged token is written back as it was stored, so saving other
     * settings never re-encrypts it: not with a key the owner is moving away
     * from, and not over an unreadable token that a rekey could still recover.
     *
     * @return array<string, mixed>
     */
    public function toOption(Settings $settings, ?Secrets $secrets = null): array
    {
        $option = $settings->toArray();
        if (null === $secrets && '' !== $settings->storedApiToken && $settings->apiToken === $settings->loadedApiToken) {
            $option['api_token'] = $settings->storedApiToken;
        } else {
            $option['api_token'] = '' === $settings->apiToken ? '' : ($secrets ?? $this->secrets)->encrypt($settings->apiToken);
        }

        return $option;
    }
}
