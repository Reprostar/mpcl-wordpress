<?php

// Bundled with WP-CLI (wp-cli/wp-config-transformer) but not in php-stubs/wp-cli-stubs.
class WPConfigTransformer
{
    /**
     * @throws Exception
     */
    public function __construct(string $wp_config_path, bool $read_only = false)
    {
    }

    /**
     * @param array<string, mixed> $options
     * @throws Exception
     */
    public function update(string $type, string $name, string $value, array $options = []): bool
    {
        return true;
    }
}
