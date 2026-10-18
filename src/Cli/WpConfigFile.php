<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Cli;

defined('ABSPATH') || exit;

/**
 * Writes constants into wp-config.php with the transformer `wp config set` uses,
 * in this process, so the value never appears in the process list.
 */
final class WpConfigFile
{
    private const DEFAULT_ANCHOR = "/* That's all, stop editing!";

    /**
     * Adds the constant before "That's all, stop editing!", or, in files without
     * that line (common in Docker images), before the line loading wp-settings.php.
     *
     * @throws \RuntimeException with the reason when the file cannot be changed
     */
    public function defineConstant(string $name, string $value): void
    {
        $path = (string) \WP_CLI\Utils\locate_wp_config();
        if ('' === $path || !is_file($path)) {
            throw new \RuntimeException('wp-config.php was not found');
        }
        if (!is_writable($path)) {
            throw new \RuntimeException($path . ' is not writable');
        }

        $options = ['add' => true];
        $contents = (string) file_get_contents($path);
        $matches = [];
        if (false === strpos($contents, self::DEFAULT_ANCHOR) && 1 === preg_match('/^.*wp-settings\.php.*$/m', $contents, $matches)) {
            $options['anchor'] = $matches[0];
            $options['placement'] = 'before';
        }

        try {
            (new \WPConfigTransformer($path))->update('constant', $name, $value, $options);
        } catch (\Exception $e) {
            throw new \RuntimeException($e->getMessage(), 0, $e);
        }
    }
}
