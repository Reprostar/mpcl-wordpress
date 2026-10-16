<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Settings;

final class SettingsRepository
{
    public const OPTION = 'mpcl-options';

    public function load(): Settings
    {
        $option = get_option(self::OPTION);

        if (!is_array($option)) {
            $settings = new Settings();
            $this->save($settings);

            return $settings;
        }

        return Settings::fromArray($option);
    }

    public function save(Settings $settings): void
    {
        update_option(self::OPTION, $settings->toArray());
    }
}
