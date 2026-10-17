<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Status;

use Reprostar\MpclWordpress\Database\Schema;
use Reprostar\MpclWordpress\Machine\MachineRepository;
use Reprostar\MpclWordpress\Machine\SyncScheduler;
use Reprostar\MpclWordpress\Settings\CatalogPages;
use Reprostar\MpclWordpress\Settings\SettingsRepository;
use Reprostar\MpclWordpress\Support\Cast;

defined('ABSPATH') || exit;

final class StatusReporter
{
    private SettingsRepository $settings;
    private MachineRepository $machines;
    private Schema $schema;
    private CatalogPages $catalogPages;
    private string $version;

    public function __construct(
        SettingsRepository $settings,
        MachineRepository $machines,
        Schema $schema,
        CatalogPages $catalogPages,
        string $version
    ) {
        $this->settings = $settings;
        $this->machines = $machines;
        $this->schema = $schema;
        $this->catalogPages = $catalogPages;
        $this->version = $version;
    }

    public function collect(): PluginStatus
    {
        $settings = $this->settings->load();
        $status = new PluginStatus();

        $status->pluginVersion = $this->version;
        $status->schemaVersion = $this->schema->storedVersion();
        $status->schemaCurrent = $this->schema->isCurrent();
        $status->apiKeySet = '' !== $settings->apiKey;
        $status->apiToken = $settings->getApiTokenState();
        $status->machineCount = $this->machines->count();
        $status->lastSync = $settings->lastListingCache;
        $status->lastAttempt = Cast::int(get_option(SyncScheduler::ATTEMPT_OPTION, 0));
        $status->autoUpdate = $settings->cacheAutoupdateEnabled;
        $status->autoUpdateInterval = $settings->cacheAutoupdateInterval;
        $status->nextCheck = Cast::int(wp_next_scheduled(SyncScheduler::HOOK, [false]));
        $status->syncPending = false !== wp_next_scheduled(SyncScheduler::HOOK, [true]);
        $status->wpCronDisabled = defined('DISABLE_WP_CRON') && (bool) constant('DISABLE_WP_CRON');
        $status->catalogPageId = $settings->catalogPageId;

        if (0 < $settings->catalogPageId) {
            $page = $this->catalogPages->find($settings->catalogPageId);
            $status->catalogPage = null !== $page ? $page->post_title : PluginStatus::CATALOG_MISSING;
            $url = null !== $page ? get_permalink($page) : false;
            $status->catalogUrl = is_string($url) ? $url : '';
        }

        return $status;
    }
}
