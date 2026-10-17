<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Settings;

use Reprostar\MpclWordpress\Shortcode\ShortcodeHandler;

defined('ABSPATH') || exit;

/**
 * Pages that can serve as the catalogue: the statuses the settings page offers.
 */
final class CatalogPages
{
    public const STATUSES = ['publish', 'private', 'draft'];

    public function find(int $pageId): ?\WP_Post
    {
        $page = 0 < $pageId ? get_post($pageId) : null;
        if (!$page instanceof \WP_Post || 'page' !== $page->post_type || !in_array($page->post_status, self::STATUSES, true)) {
            return null;
        }

        return $page;
    }

    public function hasCatalogShortcode(\WP_Post $page): bool
    {
        foreach (ShortcodeHandler::TAGS as $tag) {
            if (has_shortcode($page->post_content, $tag)) {
                return true;
            }
        }

        return false;
    }
}
