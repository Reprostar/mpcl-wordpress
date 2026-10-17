<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Cli;

use Reprostar\MpclWordpress\Machine\MachineRepository;
use Reprostar\MpclWordpress\Machine\SyncResult;
use Reprostar\MpclWordpress\Machine\SyncScheduler;

defined('ABSPATH') || exit;

final class SyncCommand
{
    private SyncScheduler $scheduler;
    private MachineRepository $machines;
    private Console $console;

    public function __construct(SyncScheduler $scheduler, MachineRepository $machines, Console $console)
    {
        $this->scheduler = $scheduler;
        $this->machines = $machines;
        $this->console = $console;
    }

    /**
     * Synchronises the collection from mypclist.net now, in the foreground.
     *
     * Runs regardless of the auto-update setting and interval, and records the
     * result like a scheduled run. A failing API keeps the current copy. On sites
     * with DISABLE_WP_CRON this can replace the cron job: `wp mpcl sync --quiet`.
     *
     * ## OPTIONS
     *
     * [--reset]
     * : Empty the local copy first, like "Clear cache" on the settings page. The catalogue is empty until the import finishes, and stays empty if it fails.
     *
     * ## EXAMPLES
     *
     *     wp mpcl sync
     *     wp mpcl sync --reset
     *
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        if (isset($assocArgs['reset'])) {
            $this->machines->deleteAll();
            $this->console->log(__('The local copy was emptied.', 'mpcl'));
        }

        $started = microtime(true);
        $result = $this->scheduler->runNow();

        if (SyncResult::TOKEN_UNREADABLE === $result->status) {
            $this->console->fail(__('The saved API token cannot be decrypted with the current key. See "wp mpcl secrets status".', 'mpcl'));
        }
        if (SyncResult::NOT_CONFIGURED === $result->status) {
            $this->console->fail(__('Not configured: the API key or token is missing. Use "wp mpcl connect".', 'mpcl'));
        }
        if (SyncResult::FAILED === $result->status) {
            /* translators: %s: error reported by the API or the HTTP client. */
            $this->console->fail(sprintf(__('Synchronisation failed: %s', 'mpcl'), $result->error));
        }

        $this->console->success(sprintf(
            /* translators: 1: number of machines, 2: duration in seconds. */
            _n('Synchronised %1$d machine in %2$s s.', 'Synchronised %1$d machines in %2$s s.', $result->machineCount, 'mpcl'),
            $result->machineCount,
            number_format_i18n(microtime(true) - $started, 1)
        ));
    }
}
