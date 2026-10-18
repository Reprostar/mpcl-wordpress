<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Privacy;

defined('ABSPATH') || exit;

/**
 * Declares compliance with the WP Consent API, which Site Health checks for every
 * active plugin. The plugin sets no cookies and does no tracking, and the photos
 * it embeds from mypclist.net come without cookies, so there is nothing to
 * register with wp_add_cookie_info() or to gate with wp_has_consent().
 */
final class ConsentApi
{
    private string $pluginFile;

    public function __construct(string $pluginFile)
    {
        $this->pluginFile = $pluginFile;
    }

    public function register(): void
    {
        add_filter('wp_consent_api_registered_' . plugin_basename($this->pluginFile), '__return_true');
    }
}
