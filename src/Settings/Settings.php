<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Settings;

use Reprostar\MpclWordpress\Support\Cast;

/**
 * The `mpcl-options` option. Keys this class does not know are carried through
 * untouched so that a save never drops data written by another plugin version.
 */
final class Settings
{
    public string $apiKey = '';
    public string $apiToken = '';
    public int $catalogPageId = 0;
    public bool $boxFullWidth = false;
    public int $entriesPerPage = 0;
    public bool $cacheImages = false;
    public bool $cacheAutoupdateEnabled = true;
    public int $cacheAutoupdateInterval = 3600;
    public int $lastListingCache = 0;

    /** @var array<string, mixed> */
    private array $unknown = [];

    private const KNOWN_KEYS = [
        'api_key',
        'api_token',
        'catalog_page_id',
        'box_full_width',
        'entries_per_page',
        'cache_images',
        'cache_autoupdate_enabled',
        'cache_autoupdate_interval',
        'last_listing_cache',
    ];

    /**
     * @param array<string, mixed> $option
     */
    public static function fromArray(array $option): self
    {
        $settings = new self();
        $settings->apiKey = Cast::string($option['api_key'] ?? null);
        $settings->apiToken = Cast::string($option['api_token'] ?? null);
        $settings->catalogPageId = Cast::int($option['catalog_page_id'] ?? null);
        $settings->boxFullWidth = Cast::bool($option['box_full_width'] ?? null);
        $settings->entriesPerPage = Cast::int($option['entries_per_page'] ?? null);
        $settings->cacheImages = Cast::bool($option['cache_images'] ?? null);
        $settings->cacheAutoupdateEnabled = Cast::bool($option['cache_autoupdate_enabled'] ?? null);
        $settings->cacheAutoupdateInterval = Cast::int($option['cache_autoupdate_interval'] ?? null);
        $settings->lastListingCache = Cast::int($option['last_listing_cache'] ?? null);

        foreach ($option as $key => $value) {
            if (!in_array($key, self::KNOWN_KEYS, true)) {
                $settings->unknown[(string) $key] = $value;
            }
        }

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge($this->unknown, [
            'api_key' => $this->apiKey,
            'api_token' => $this->apiToken,
            'catalog_page_id' => $this->catalogPageId,
            'box_full_width' => (int) $this->boxFullWidth,
            'entries_per_page' => $this->entriesPerPage,
            'cache_images' => (int) $this->cacheImages,
            'cache_autoupdate_enabled' => (int) $this->cacheAutoupdateEnabled,
            'cache_autoupdate_interval' => $this->cacheAutoupdateInterval,
            'last_listing_cache' => $this->lastListingCache,
        ]);
    }
}
