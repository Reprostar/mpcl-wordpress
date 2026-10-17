<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Settings;

use Reprostar\MpclWordpress\Support\Cast;

defined('ABSPATH') || exit;

/**
 * Applies submitted values of the plain (non-secret) settings, with the same
 * rules for the settings page and WP-CLI. Keys missing from the input are left
 * as they are; the API token is handled by its callers.
 */
final class SettingsInput
{
    public const TEXT_FIELDS = ['api_key'];
    public const NUMBER_FIELDS = ['catalog_page_id', 'entries_per_page', 'cache_autoupdate_interval', 'last_listing_cache'];
    public const BOOLEAN_FIELDS = ['box_full_width', 'cache_autoupdate_enabled'];

    /**
     * @param array<mixed> $input
     */
    public function apply(Settings $settings, array $input): void
    {
        if (array_key_exists('api_key', $input)) {
            $settings->apiKey = sanitize_text_field(Cast::string($input['api_key']));
        }
        if (array_key_exists('catalog_page_id', $input)) {
            $settings->catalogPageId = absint(Cast::string($input['catalog_page_id']));
        }
        if (array_key_exists('entries_per_page', $input)) {
            $settings->entriesPerPage = absint(Cast::string($input['entries_per_page']));
        }
        if (array_key_exists('cache_autoupdate_interval', $input)) {
            $settings->cacheAutoupdateInterval = absint(Cast::string($input['cache_autoupdate_interval']));
        }
        if (array_key_exists('last_listing_cache', $input)) {
            $settings->lastListingCache = absint(Cast::string($input['last_listing_cache']));
        }
        if (array_key_exists('box_full_width', $input)) {
            $settings->boxFullWidth = (bool) $input['box_full_width'];
        }
        if (array_key_exists('cache_autoupdate_enabled', $input)) {
            $settings->cacheAutoupdateEnabled = (bool) $input['cache_autoupdate_enabled'];
        }
    }
}
