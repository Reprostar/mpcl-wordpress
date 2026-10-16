<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress;

use Reprostar\MpclWordpress\Machine\SyncScheduler;
use Reprostar\MpclWordpress\Settings\SettingsRepository;

defined('ABSPATH') || exit;

/**
 * Removes everything the plugin stored, on every site of a network. Runs only
 * when the plugin is deleted; deactivation keeps all data.
 */
final class Uninstaller
{
    private const OPTIONS = [SettingsRepository::OPTION, 'mpcl_db_version', 'mpcl_sync_attempted_at'];
    private const TABLES = ['mpcl_machines', 'mpcl_options'];

    private \wpdb $wpdb;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function run(): void
    {
        if (!is_multisite()) {
            $this->cleanSite();

            return;
        }

        foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $siteId) {
            switch_to_blog((int) $siteId);
            $this->cleanSite();
            restore_current_blog();
        }
    }

    private function cleanSite(): void
    {
        foreach (self::TABLES as $table) {
            $query = $this->wpdb->prepare('DROP TABLE IF EXISTS %i', $this->wpdb->prefix . $table);
            if (is_string($query)) {
                $this->wpdb->query($query);
            }
        }

        foreach (self::OPTIONS as $option) {
            delete_option($option);
        }

        wp_unschedule_hook(SyncScheduler::HOOK);
    }
}
