<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress;

use Reprostar\MpclWordpress\Api\ApiClient;
use Reprostar\MpclWordpress\Database\Schema;
use Reprostar\MpclWordpress\Formatting\BbCode;
use Reprostar\MpclWordpress\Machine\MachineRepository;
use Reprostar\MpclWordpress\Machine\SyncScheduler;
use Reprostar\MpclWordpress\Machine\Synchronizer;
use Reprostar\MpclWordpress\Settings\SettingsPage;
use Reprostar\MpclWordpress\Settings\SettingsRepository;
use Reprostar\MpclWordpress\Shortcode\ShortcodeHandler;
use Reprostar\MpclWordpress\View\MachinePresenter;
use Reprostar\MpclWordpress\View\Renderer;

defined('ABSPATH') || exit;

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
        $machines = new MachineRepository($wpdb, $schema->getMachinesTable());
        $scheduler = $this->createScheduler($settingsRepository, $machines);
        $presenter = new MachinePresenter(new BbCode(), $baseUrl . 'img/sample_machine.png');

        $languagesPath = dirname(plugin_basename($this->file)) . '/languages/';
        add_action('init', static function () use ($languagesPath): void {
            load_plugin_textdomain('mpcl', false, $languagesPath);
        });

        $assets = new Assets($baseUrl, MPCL_VERSION);
        $assets->register();
        $scheduler->register();
        $scheduler->ensureScheduled();

        (new ShortcodeHandler(
            $settingsRepository,
            $machines,
            $presenter,
            new Renderer($directory . 'templates'),
            $assets
        ))->register();

        if (is_admin()) {
            (new SettingsPage($settingsRepository, $machines, $scheduler, $directory . 'templates'))->register();
        }

        if (!$schema->isCurrent()) {
            $schema->migrate();
        }
    }

    public function activate(): void
    {
        $schema = new Schema($this->wpdb);
        $schema->migrate();

        $settingsRepository = new SettingsRepository();
        $settingsRepository->load();

        $scheduler = $this->createScheduler($settingsRepository, new MachineRepository($this->wpdb, $schema->getMachinesTable()));
        $scheduler->register();
        $scheduler->ensureScheduled();
    }

    public function deactivate(): void
    {
        $schema = new Schema($this->wpdb);
        $this->createScheduler(new SettingsRepository(), new MachineRepository($this->wpdb, $schema->getMachinesTable()))
            ->unschedule();
    }

    private function createScheduler(SettingsRepository $settingsRepository, MachineRepository $machines): SyncScheduler
    {
        $settings = $settingsRepository->load();
        $apiUrl = defined('MPCL_API_URL') ? (string) constant('MPCL_API_URL') : ApiClient::DEFAULT_URL;
        $client = new ApiClient($apiUrl, $settings->apiKey, $settings->apiToken);

        return new SyncScheduler(new Synchronizer($client, $machines), $settingsRepository);
    }
}
