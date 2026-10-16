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

    public function save(Settings $settings): void
    {
        update_option(self::OPTION, $this->toOption($settings));
    }

    /**
     * @param array<mixed> $option
     */
    public function fromOption(array $option): Settings
    {
        $settings = Settings::fromArray($option);

        if ($this->secrets->isEncrypted($settings->apiToken)) {
            $token = $this->secrets->decrypt($settings->apiToken);
            $settings->apiToken = $token ?? '';
            $settings->apiTokenUnreadable = null === $token;
        }

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    public function toOption(Settings $settings): array
    {
        $option = $settings->toArray();
        $option['api_token'] = '' === $settings->apiToken ? '' : $this->secrets->encrypt($settings->apiToken);

        return $option;
    }
}
