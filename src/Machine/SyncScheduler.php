<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Machine;

use Reprostar\MpclWordpress\Settings\Settings;
use Reprostar\MpclWordpress\Settings\SettingsRepository;

/**
 * Runs the import from WP-Cron instead of inside visitors' requests.
 *
 * A recurring event checks every few minutes whether the configured update
 * interval has passed since the last attempt; a forced single event refreshes
 * right away after the cache is cleared or the API credentials change.
 */
final class SyncScheduler
{
    public const HOOK = 'mpcl_sync';
    private const SCHEDULE = 'mpcl_every_five_minutes';
    private const CHECK_INTERVAL = 300;
    private const ATTEMPT_OPTION = 'mpcl_sync_attempted_at';

    private Synchronizer $synchronizer;
    private SettingsRepository $settings;

    public function __construct(Synchronizer $synchronizer, SettingsRepository $settings)
    {
        $this->synchronizer = $synchronizer;
        $this->settings = $settings;
    }

    public function register(): void
    {
        add_filter('cron_schedules', [$this, 'addSchedule']);
        add_action(self::HOOK, [$this, 'run']);
    }

    /**
     * @param array<string, array{interval: int, display: string}> $schedules
     * @return array<string, array{interval: int, display: string}>
     */
    public function addSchedule(array $schedules): array
    {
        $schedules[self::SCHEDULE] = [
            'interval' => self::CHECK_INTERVAL,
            'display' => 'MyPCList: every five minutes',
        ];

        return $schedules;
    }

    public function ensureScheduled(): void
    {
        if (false === wp_next_scheduled(self::HOOK, [false])) {
            wp_schedule_event(time(), self::SCHEDULE, self::HOOK, [false]);
        }
    }

    public function unschedule(): void
    {
        wp_clear_scheduled_hook(self::HOOK, [false]);
        wp_clear_scheduled_hook(self::HOOK, [true]);
    }

    public function requestImmediate(): void
    {
        if (false === wp_next_scheduled(self::HOOK, [true])) {
            wp_schedule_single_event(time(), self::HOOK, [true]);
        }
    }

    /**
     * @param bool|string $force WP-Cron passes the scheduled argument back
     */
    public function run($force = false): void
    {
        $settings = $this->settings->load();
        if (!$this->isDue($settings, (bool) $force)) {
            return;
        }

        update_option(self::ATTEMPT_OPTION, time(), false);

        if (null === $this->synchronizer->importAll()) {
            return;
        }

        $settings = $this->settings->load();
        $settings->lastListingCache = time();
        $this->settings->save($settings);
    }

    private function isDue(Settings $settings, bool $force): bool
    {
        if ('' === $settings->apiKey || '' === $settings->apiToken) {
            return false;
        }

        if ($force) {
            return true;
        }

        if (!$settings->cacheAutoupdateEnabled) {
            return false;
        }

        $lastAttempt = max($settings->lastListingCache, (int) get_option(self::ATTEMPT_OPTION, 0));

        return time() - $lastAttempt >= $settings->cacheAutoupdateInterval;
    }
}
