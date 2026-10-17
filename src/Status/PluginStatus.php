<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Status;

defined('ABSPATH') || exit;

/**
 * A snapshot of the plugin's state, collected without calling the API.
 * Timestamps are Unix time, 0 when never.
 */
final class PluginStatus
{
    public const CATALOG_NOT_SET = 'not set';
    public const CATALOG_MISSING = 'missing';

    public string $pluginVersion = '';
    public int $schemaVersion = 0;
    public bool $schemaCurrent = false;
    public bool $apiKeySet = false;
    /** One of the Settings::TOKEN_* states. */
    public string $apiToken = '';
    public int $machineCount = 0;
    public int $lastSync = 0;
    public int $lastAttempt = 0;
    public bool $autoUpdate = false;
    public int $autoUpdateInterval = 0;
    public int $nextCheck = 0;
    public bool $syncPending = false;
    public bool $wpCronDisabled = false;
    public int $catalogPageId = 0;
    /** The page title, or CATALOG_NOT_SET / CATALOG_MISSING. */
    public string $catalogPage = self::CATALOG_NOT_SET;
    public string $catalogUrl = '';

    public function lastAttemptFailed(): bool
    {
        return $this->lastAttempt > $this->lastSync;
    }
}
