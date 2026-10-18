<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress;

use Reprostar\MpclWordpress\Api\ApiClientFactory;
use Reprostar\MpclWordpress\Cli\CatalogCommand;
use Reprostar\MpclWordpress\Cli\CommandRegistrar;
use Reprostar\MpclWordpress\Cli\ConfigCommand;
use Reprostar\MpclWordpress\Cli\ConnectCommand;
use Reprostar\MpclWordpress\Cli\MachinesCommand;
use Reprostar\MpclWordpress\Cli\Console;
use Reprostar\MpclWordpress\Cli\DisconnectCommand;
use Reprostar\MpclWordpress\Cli\ScheduleCommand;
use Reprostar\MpclWordpress\Cli\SecretsCommand;
use Reprostar\MpclWordpress\Cli\StatusCommand;
use Reprostar\MpclWordpress\Cli\SyncCommand;
use Reprostar\MpclWordpress\Cli\TestConnectionCommand;
use Reprostar\MpclWordpress\Cli\WpConfigFile;
use Reprostar\MpclWordpress\Database\Schema;
use Reprostar\MpclWordpress\Formatting\BbCode;
use Reprostar\MpclWordpress\Machine\MachineRepository;
use Reprostar\MpclWordpress\Machine\SyncScheduler;
use Reprostar\MpclWordpress\Machine\Synchronizer;
use Reprostar\MpclWordpress\Privacy\ConsentApi;
use Reprostar\MpclWordpress\Settings\SettingsPage;
use Reprostar\MpclWordpress\Security\KeyMaterial;
use Reprostar\MpclWordpress\Security\Secrets;
use Reprostar\MpclWordpress\Settings\ConnectionChecker;
use Reprostar\MpclWordpress\Settings\ConnectionTest;
use Reprostar\MpclWordpress\Settings\CatalogPages;
use Reprostar\MpclWordpress\Settings\SettingsInput;
use Reprostar\MpclWordpress\Settings\SettingsRepository;
use Reprostar\MpclWordpress\Shortcode\ShortcodeHandler;
use Reprostar\MpclWordpress\Status\StatusReporter;
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

    public static function create(string $file): self
    {
        global $wpdb;

        if (!$wpdb instanceof \wpdb) {
            throw new \RuntimeException('mpcl: $wpdb is not available');
        }

        return new self($file, $wpdb);
    }

    public function boot(): void
    {
        $wpdb = $this->wpdb;
        $directory = plugin_dir_path($this->file);
        $baseUrl = plugin_dir_url($this->file);

        $schema = new Schema($wpdb);
        $keys = new KeyMaterial();
        $secrets = new Secrets($keys);
        $settingsRepository = new SettingsRepository($secrets);
        $settingsInput = new SettingsInput();
        $catalogPages = new CatalogPages();
        $machines = new MachineRepository($wpdb, $schema->getMachinesTable());
        $scheduler = $this->createScheduler($settingsRepository, $machines);
        $presenter = new MachinePresenter(new BbCode(), $baseUrl . 'img/sample_machine.png');

        $languagesPath = dirname(plugin_basename($this->file)) . '/languages/';
        add_action('init', static function () use ($languagesPath): void {
            load_plugin_textdomain('mpcl', false, $languagesPath);
        });

        (new ConsentApi($this->file))->register();

        $assets = new Assets($baseUrl, MPCL_VERSION);
        $assets->register();
        $scheduler->register();
        $scheduler->ensureScheduled();

        (new ShortcodeHandler(
            $settingsRepository,
            $machines,
            $presenter,
            new Renderer($directory . 'templates'),
            $assets,
            $catalogPages
        ))->register();

        $connectionChecker = new ConnectionChecker(ApiClientFactory::fromEnvironment());

        if (is_admin()) {
            $connectionTest = new ConnectionTest($settingsRepository, $connectionChecker, $baseUrl, MPCL_VERSION);
            $connectionTest->register();
            (new SettingsPage($settingsRepository, $settingsInput, $secrets, $machines, $scheduler, $connectionTest, $directory . 'templates'))->register();
        }

        if (!$schema->isCurrent()) {
            $schema->migrate();
        }

        if (defined('WP_CLI') && WP_CLI) {
            $console = new Console();
            $syncCommand = new SyncCommand($scheduler, $machines, $console);
            $statusReporter = new StatusReporter($settingsRepository, $machines, $schema, $catalogPages, MPCL_VERSION);
            (new CommandRegistrar([
                'secrets' => new SecretsCommand($settingsRepository, $secrets, $keys, new WpConfigFile(), $console),
                'config' => new ConfigCommand($settingsRepository, $settingsInput, $catalogPages, $scheduler, $console),
                'test-connection' => new TestConnectionCommand($settingsRepository, $connectionChecker, $console),
                'sync' => $syncCommand,
                'status' => new StatusCommand($statusReporter, $console),
                'schedule' => new ScheduleCommand($settingsRepository, $settingsInput, $scheduler, $statusReporter, $console),
                'catalog' => new CatalogCommand($settingsRepository, $settingsInput, $catalogPages, $console),
                'machines' => new MachinesCommand($machines, $settingsRepository, $catalogPages, $console),
                'disconnect' => new DisconnectCommand($settingsRepository, $machines, $scheduler, $console),
                'connect' => new ConnectCommand($settingsRepository, $settingsInput, $connectionChecker, $scheduler, $syncCommand, $console),
            ]))->register();
        }
    }

    public function activate(): void
    {
        $schema = new Schema($this->wpdb);
        $schema->migrate();

        $settingsRepository = new SettingsRepository(new Secrets(new KeyMaterial()));
        $settingsRepository->load();

        $scheduler = $this->createScheduler($settingsRepository, new MachineRepository($this->wpdb, $schema->getMachinesTable()));
        $scheduler->register();
        $scheduler->ensureScheduled();
    }

    public function deactivate(): void
    {
        $schema = new Schema($this->wpdb);
        $this->createScheduler(new SettingsRepository(new Secrets(new KeyMaterial())), new MachineRepository($this->wpdb, $schema->getMachinesTable()))
            ->unschedule();
    }

    private function createScheduler(SettingsRepository $settingsRepository, MachineRepository $machines): SyncScheduler
    {
        return new SyncScheduler(new Synchronizer(ApiClientFactory::fromEnvironment(), $machines), $settingsRepository);
    }
}
