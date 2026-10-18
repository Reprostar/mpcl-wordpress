<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Cli;

use Reprostar\MpclWordpress\Status\StatusReporter;

defined('ABSPATH') || exit;

final class StatusCommand
{
    private StatusReporter $reporter;
    private Console $console;

    public function __construct(StatusReporter $reporter, Console $console)
    {
        $this->reporter = $reporter;
        $this->console = $console;
    }

    /**
     * Shows the connection, synchronisation and catalogue state.
     *
     * Does not call the API (use `wp mpcl test-connection` for that) and never
     * prints the token. Exits with 0 also when something is not configured.
     * Times are in UTC.
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
    public function __invoke(array $args, array $assocArgs): void
    {
        $status = $this->reporter->collect();

        $this->console->record(is_string($assocArgs['format'] ?? null) ? $assocArgs['format'] : 'table', [
            'plugin_version' => $status->pluginVersion,
            'schema_version' => $status->schemaVersion,
            'schema_current' => $status->schemaCurrent,
            'api_key' => $status->apiKeySet ? 'set' : 'empty',
            'api_token' => $status->apiToken,
            'machines' => $status->machineCount,
            'last_sync' => $this->time($status->lastSync),
            'last_attempt' => $this->time($status->lastAttempt),
            'last_attempt_failed' => $status->lastAttemptFailed(),
            'auto_update' => $status->autoUpdate,
            'auto_update_interval' => $status->autoUpdateInterval,
            'next_check' => $this->time($status->nextCheck),
            'sync_pending' => $status->syncPending,
            'wp_cron_disabled' => $status->wpCronDisabled,
            'catalog_page_id' => $status->catalogPageId,
            'catalog_page' => $status->catalogPage,
            'catalog_url' => $status->catalogUrl,
        ]);
    }

    /**
     * ISO 8601 with the distance from now, or "never". Untranslated, like the
     * other values here: WordPress' own Polish strings render "in 2 minutes"
     * as "2 minuty temu" (ago).
     */
    private function time(int $timestamp): string
    {
        if (0 === $timestamp) {
            return 'never';
        }

        $seconds = abs(time() - $timestamp);
        if ($seconds < 60) {
            $distance = $seconds . ' s';
        } elseif ($seconds < 3600) {
            $distance = intdiv($seconds, 60) . ' min';
        } elseif ($seconds < 86400) {
            $distance = intdiv($seconds, 3600) . ' h';
        } else {
            $distance = intdiv($seconds, 86400) . ' d';
        }

        return gmdate('Y-m-d\TH:i:s\Z', $timestamp) . ' (' . ($timestamp <= time() ? $distance . ' ago' : 'in ' . $distance) . ')';
    }
}
