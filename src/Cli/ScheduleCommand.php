<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Cli;

use Reprostar\MpclWordpress\Machine\SyncScheduler;
use Reprostar\MpclWordpress\Settings\SettingsInput;
use Reprostar\MpclWordpress\Settings\SettingsRepository;
use Reprostar\MpclWordpress\Status\StatusReporter;

defined('ABSPATH') || exit;

/**
 * Turns the automatic synchronisation on and off.
 *
 * WP-Cron checks every five minutes whether the interval has passed since the
 * last attempt; the interval is therefore at least five minutes.
 */
final class ScheduleCommand
{
    public const MINIMUM_INTERVAL = 300;
    private const UNITS = ['' => 1, 's' => 1, 'm' => 60, 'h' => 3600, 'd' => 86400];

    private SettingsRepository $settings;
    private SettingsInput $input;
    private SyncScheduler $scheduler;
    private StatusReporter $reporter;
    private Console $console;

    public function __construct(
        SettingsRepository $settings,
        SettingsInput $input,
        SyncScheduler $scheduler,
        StatusReporter $reporter,
        Console $console
    ) {
        $this->settings = $settings;
        $this->input = $input;
        $this->scheduler = $scheduler;
        $this->reporter = $reporter;
        $this->console = $console;
    }

    /**
     * Enables the automatic synchronisation.
     *
     * ## OPTIONS
     *
     * [--interval=<interval>]
     * : Time between synchronisations: seconds, or a number with s, m, h or d. At least 5 minutes.
     *
     * ## EXAMPLES
     *
     *     wp mpcl schedule enable --interval=1h
     *
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function enable(array $args, array $assocArgs): void
    {
        $input = ['cache_autoupdate_enabled' => '1'];
        if (isset($assocArgs['interval'])) {
            $input['cache_autoupdate_interval'] = (string) $this->parseInterval($assocArgs['interval']);
        }

        $settings = $this->settings->load();
        $this->input->apply($settings, $input);
        $this->settings->save($settings);
        $this->scheduler->ensureScheduled();

        $this->console->success(sprintf(
            /* translators: %d: interval in seconds. */
            __('Automatic synchronisation is on, every %d seconds.', 'mpcl'),
            $settings->cacheAutoupdateInterval
        ));
    }

    /**
     * Disables the automatic synchronisation.
     *
     * The five-minute WP-Cron check stays scheduled but does nothing; `wp mpcl
     * sync` still works.
     *
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function disable(array $args, array $assocArgs): void
    {
        $settings = $this->settings->load();
        $this->input->apply($settings, ['cache_autoupdate_enabled' => '0']);
        $this->settings->save($settings);

        $this->console->success(__('Automatic synchronisation is off.', 'mpcl'));
    }

    /**
     * Shows whether the automatic synchronisation is on and when it runs.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - yaml
     * ---
     *
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function status(array $args, array $assocArgs): void
    {
        $status = $this->reporter->collect();

        $this->console->record(is_string($assocArgs['format'] ?? null) ? $assocArgs['format'] : 'table', [
            'auto_update' => $status->autoUpdate,
            'auto_update_interval' => $status->autoUpdateInterval,
            'next_check' => 0 < $status->nextCheck ? gmdate('Y-m-d\TH:i:s\Z', $status->nextCheck) : 'never',
            'sync_pending' => $status->syncPending,
            'wp_cron_disabled' => $status->wpCronDisabled,
        ]);
    }

    /**
     * @param mixed $value
     */
    private function parseInterval($value): int
    {
        $matches = [];
        if (!is_scalar($value) || 1 !== preg_match('/^(\d+)([smhd]?)$/', strtolower((string) $value), $matches)) {
            $this->console->fail(__('--interval takes seconds, or a number followed by s, m, h or d, e.g. 30m.', 'mpcl'));
        }

        $seconds = (int) $matches[1] * self::UNITS[$matches[2]];
        if ($seconds < self::MINIMUM_INTERVAL) {
            $this->console->fail(__('--interval must be at least 5 minutes: WP-Cron checks every five minutes.', 'mpcl'));
        }

        return $seconds;
    }
}
