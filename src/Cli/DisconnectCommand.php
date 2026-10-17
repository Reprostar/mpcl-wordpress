<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Cli;

use Reprostar\MpclWordpress\Machine\MachineRepository;
use Reprostar\MpclWordpress\Machine\SyncScheduler;
use Reprostar\MpclWordpress\Settings\SettingsRepository;

defined('ABSPATH') || exit;

final class DisconnectCommand
{
    private SettingsRepository $settings;
    private MachineRepository $machines;
    private SyncScheduler $scheduler;
    private Console $console;

    public function __construct(SettingsRepository $settings, MachineRepository $machines, SyncScheduler $scheduler, Console $console)
    {
        $this->settings = $settings;
        $this->machines = $machines;
        $this->scheduler = $scheduler;
        $this->console = $console;
    }

    /**
     * Removes the API key and token.
     *
     * Other settings and the synchronised machines stay, so the catalogue keeps
     * showing the last copy. With --purge the machines are removed too. Tables
     * and options themselves are only removed when the plugin is deleted.
     *
     * ## OPTIONS
     *
     * [--purge]
     * : Also empty the synchronised machines and the synchronisation times.
     *
     * [--yes]
     * : Do not ask for confirmation.
     *
     * ## EXAMPLES
     *
     *     wp mpcl disconnect --yes
     *     wp mpcl disconnect --purge --yes
     *
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $purge = isset($assocArgs['purge']);
        $this->console->confirm(
            $purge
                ? __('Remove the API credentials and all synchronised machines?', 'mpcl')
                : __('Remove the API credentials?', 'mpcl'),
            $assocArgs
        );

        $settings = $this->settings->load();
        $settings->apiKey = '';
        $settings->clearApiToken();
        if ($purge) {
            $settings->lastListingCache = 0;
        }
        $this->settings->save($settings);
        $this->scheduler->cancelImmediate();
        $this->console->log(__('Removed the API key and token.', 'mpcl'));

        if ($purge) {
            $count = $this->machines->count();
            $this->machines->deleteAll();
            delete_option(SyncScheduler::ATTEMPT_OPTION);
            /* translators: %d: number of machines. */
            $this->console->log(sprintf(_n('Removed %d synchronised machine.', 'Removed %d synchronised machines.', $count, 'mpcl'), $count));
        }

        $this->console->success(__('Disconnected from mypclist.net.', 'mpcl'));
    }
}
