<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress;

use Reprostar\MpclWordpress\Api\ApiClient;
use Reprostar\MpclWordpress\Database\Schema;
use Reprostar\MpclWordpress\Formatting\BbCode;
use Reprostar\MpclWordpress\Machine\MachineRepository;
use Reprostar\MpclWordpress\Machine\Synchronizer;
use Reprostar\MpclWordpress\Settings\SettingsPage;
use Reprostar\MpclWordpress\Settings\SettingsRepository;
use Reprostar\MpclWordpress\Shortcode\ShortcodeHandler;
use Reprostar\MpclWordpress\View\MachinePresenter;
use Reprostar\MpclWordpress\View\Renderer;

/**
 * Composition root: builds the services once and hooks them into WordPress.
 */
final class Plugin
{
    private string $file;
    private \wpdb $wpdb;

    public function __construct(string $file, \wpdb $wpdb)
    {
        $this->file = $file;
        $this->wpdb = $wpdb;
    }

    public function boot(): void
    {
        $wpdb = $this->wpdb;
        $directory = plugin_dir_path($this->file);
        $baseUrl = plugin_dir_url($this->file);

        $schema = new Schema($wpdb);
        $settingsRepository = new SettingsRepository();
        $settings = $settingsRepository->load();
        $machines = new MachineRepository($wpdb, $schema->getMachinesTable());
        $apiUrl = defined('MPCL_API_URL') ? (string) constant('MPCL_API_URL') : ApiClient::DEFAULT_URL;
        $synchronizer = new Synchronizer(new ApiClient($apiUrl, $settings->apiKey, $settings->apiToken), $machines);
        $presenter = new MachinePresenter(new BbCode(), $baseUrl . 'img/sample_machine.png');

        load_plugin_textdomain('mpcl', false, dirname(plugin_basename($this->file)) . '/languages/');

        (new Assets($baseUrl))->register();
        (new ShortcodeHandler(
            $settingsRepository,
            $machines,
            $synchronizer,
            $presenter,
            new Renderer($directory . 'templates')
        ))->register();

        if (is_admin()) {
            (new SettingsPage($settingsRepository, $machines, $directory . 'templates'))->register();
        }

        if (!$schema->isCurrent()) {
            $schema->migrate();
        }
    }

    public function activate(): void
    {
        (new Schema($this->wpdb))->migrate();
        (new SettingsRepository())->load();
    }
}
