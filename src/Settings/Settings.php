<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Settings;

use Reprostar\MpclWordpress\Support\Cast;

defined('ABSPATH') || exit;

/**
 * The `mpcl-options` option. Keys this class does not know are carried through
 * untouched so that a save never drops data written by another plugin version.
 */
final class Settings
{
    public const DEFAULT_ENTRIES_PER_PAGE = 100;
    public const TOKEN_EMPTY = 'empty';
    public const TOKEN_SET = 'set';
    public const TOKEN_UNREADABLE = 'unreadable';

    public string $apiKey = '';
    /** Plain text in memory; SettingsRepository encrypts it for storage. */
    public string $apiToken = '';

    /** The stored token exists but cannot be decrypted with the current key. */
    public bool $apiTokenUnreadable = false;
    /** The encrypted token as stored; saved back unchanged while $apiToken stays $loadedApiToken. */
    public string $storedApiToken = '';
    /** $apiToken as decrypted on load ('' when unreadable). */
    public string $loadedApiToken = '';

    public int $catalogPageId = 0;
    public bool $boxFullWidth = false;
    /** 0 means the default limit */
    public int $entriesPerPage = 0;
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
        'cache_autoupdate_enabled',
        'cache_autoupdate_interval',
        'last_listing_cache',
    ];

    /**
     * @param array<mixed> $option
     */
    public static function fromArray(array $option): self
    {
        $settings = new self();
        $settings->apiKey = Cast::string($option['api_key'] ?? null);
        $settings->apiToken = Cast::string($option['api_token'] ?? null);
        $settings->catalogPageId = Cast::int($option['catalog_page_id'] ?? null);
        $settings->boxFullWidth = Cast::bool($option['box_full_width'] ?? null);
        $settings->entriesPerPage = Cast::int($option['entries_per_page'] ?? null);
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

    public function getApiTokenState(): string
    {
        if ('' !== $this->apiToken) {
            return self::TOKEN_SET;
        }

        return $this->apiTokenUnreadable ? self::TOKEN_UNREADABLE : self::TOKEN_EMPTY;
    }

    public function clearApiToken(): void
    {
        $this->apiToken = '';
        $this->apiTokenUnreadable = false;
        $this->storedApiToken = '';
        $this->loadedApiToken = '';
    }

    public function getListingLimit(): int
    {
        return 0 < $this->entriesPerPage ? $this->entriesPerPage : self::DEFAULT_ENTRIES_PER_PAGE;
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
            'cache_autoupdate_enabled' => (int) $this->cacheAutoupdateEnabled,
            'cache_autoupdate_interval' => $this->cacheAutoupdateInterval,
            'last_listing_cache' => $this->lastListingCache,
        ]);
    }
}
