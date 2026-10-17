<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Cli;

use Reprostar\MpclWordpress\Machine\SyncScheduler;
use Reprostar\MpclWordpress\Settings\ConnectionChecker;
use Reprostar\MpclWordpress\Settings\SettingsInput;
use Reprostar\MpclWordpress\Settings\SettingsRepository;

defined('ABSPATH') || exit;

final class ConnectCommand
{
    private SettingsRepository $settings;
    private SettingsInput $input;
    private ConnectionChecker $checker;
    private SyncScheduler $scheduler;
    private SyncCommand $sync;
    private Console $console;

    public function __construct(
        SettingsRepository $settings,
        SettingsInput $input,
        ConnectionChecker $checker,
        SyncScheduler $scheduler,
        SyncCommand $sync,
        Console $console
    ) {
        $this->settings = $settings;
        $this->input = $input;
        $this->checker = $checker;
        $this->scheduler = $scheduler;
        $this->sync = $sync;
        $this->console = $console;
    }

    /**
     * Saves the API key and token from mypclist.net/settings/api.
     *
     * The token is read from STDIN, never from the command line, so it does not
     * end up in the shell history or the process list. It is stored encrypted,
     * like on the settings page. The credentials are checked first and nothing is
     * saved when mypclist.net rejects them.
     *
     * ## OPTIONS
     *
     * --key=<api_key>
     * : API key.
     *
     * [--skip-check]
     * : Save without checking the credentials.
     *
     * [--sync]
     * : Synchronise the collection right after saving. Without it a synchronisation is scheduled.
     *
     * ## EXAMPLES
     *
     *     printf %s "$MPCL_TOKEN" | wp mpcl connect --key=abc123 --sync
     *
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $apiKey = sanitize_text_field(is_string($assocArgs['key'] ?? null) ? $assocArgs['key'] : '');
        if ('' === $apiKey) {
            $this->console->fail(__('Pass the API key with --key.', 'mpcl'));
        }

        if ($this->console->stdinIsTerminal()) {
            $this->console->fail(__('Pipe the API token to STDIN, e.g. printf %s "$MPCL_TOKEN" | wp mpcl connect --key=<key>', 'mpcl'));
        }
        $apiToken = sanitize_text_field($this->console->readStdin());
        if ('' === $apiToken) {
            $this->console->fail(__('No API token was given on STDIN.', 'mpcl'));
        }

        if (!isset($assocArgs['skip-check'])) {
            $result = $this->checker->check($apiKey, $apiToken);
            if (!$result->ok) {
                /* translators: %s: error reported by the API or the HTTP client. */
                $this->console->fail(sprintf(__('Connection failed: %s. Nothing was saved.', 'mpcl'), $result->error));
            }
        }

        $settings = $this->settings->load();
        $this->input->apply($settings, ['api_key' => $apiKey]);
        $settings->apiToken = $apiToken;
        $this->settings->save($settings);
        $this->console->success(__('The API key and token were saved.', 'mpcl'));

        if (isset($assocArgs['sync'])) {
            ($this->sync)([], []);

            return;
        }

        $this->scheduler->requestImmediate();
        $this->console->log(__('A synchronisation has been scheduled. Run "wp mpcl sync" to do it now.', 'mpcl'));
    }
}
