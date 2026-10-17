<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Cli;

use Reprostar\MpclWordpress\Settings\ConnectionChecker;
use Reprostar\MpclWordpress\Settings\Settings;
use Reprostar\MpclWordpress\Settings\SettingsRepository;

defined('ABSPATH') || exit;

final class TestConnectionCommand
{
    private SettingsRepository $settings;
    private ConnectionChecker $checker;
    private Console $console;

    public function __construct(SettingsRepository $settings, ConnectionChecker $checker, Console $console)
    {
        $this->settings = $settings;
        $this->checker = $checker;
        $this->console = $console;
    }

    /**
     * Checks the saved API key and token against mypclist.net.
     *
     * Exits with status 1 when the plugin is not configured, the saved token
     * cannot be decrypted, or the API rejects the credentials or is unreachable.
     *
     * ## EXAMPLES
     *
     *     wp mpcl test-connection
     *
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $settings = $this->settings->load();

        if (Settings::TOKEN_UNREADABLE === $settings->getApiTokenState()) {
            $this->console->fail(__('The saved API token cannot be decrypted with the current key. See "wp mpcl secrets status".', 'mpcl'));
        }
        if ('' === $settings->apiKey || '' === $settings->apiToken) {
            $this->console->fail(__('Not configured: the API key or token is missing. Use "wp mpcl connect".', 'mpcl'));
        }

        $result = $this->checker->check($settings->apiKey, $settings->apiToken);
        if (!$result->ok) {
            /* translators: %s: error reported by the API or the HTTP client. */
            $this->console->fail(sprintf(__('Connection failed: %s', 'mpcl'), $result->error));
        }

        $this->console->success(sprintf(
            /* translators: %d: number of machines in the MyPCList collection. */
            _n('Connection works. Your collection has %d machine.', 'Connection works. Your collection has %d machines.', $result->machineCount, 'mpcl'),
            $result->machineCount
        ));
    }
}
