<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Cli;

use Reprostar\MpclWordpress\Machine\SyncScheduler;
use Reprostar\MpclWordpress\Settings\CatalogPages;
use Reprostar\MpclWordpress\Settings\Settings;
use Reprostar\MpclWordpress\Settings\SettingsInput;
use Reprostar\MpclWordpress\Settings\SettingsRepository;

defined('ABSPATH') || exit;

/**
 * Reads and changes the plugin settings with the same validation as the
 * settings page. The API token is shown only as set, empty or unreadable; set it
 * with `wp mpcl connect`.
 */
final class ConfigCommand
{
    private const WRITABLE_KEYS = [
        'api_key',
        'catalog_page_id',
        'box_full_width',
        'entries_per_page',
        'cache_autoupdate_enabled',
        'cache_autoupdate_interval',
    ];
    private const TRUE_VALUES = ['1', 'true', 'yes', 'on'];
    private const FALSE_VALUES = ['0', 'false', 'no', 'off'];

    private SettingsRepository $settings;
    private SettingsInput $input;
    private CatalogPages $catalogPages;
    private SyncScheduler $scheduler;
    private Console $console;

    public function __construct(
        SettingsRepository $settings,
        SettingsInput $input,
        CatalogPages $catalogPages,
        SyncScheduler $scheduler,
        Console $console
    ) {
        $this->settings = $settings;
        $this->input = $input;
        $this->catalogPages = $catalogPages;
        $this->scheduler = $scheduler;
        $this->console = $console;
    }

    /**
     * Lists all settings.
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
     *   - csv
     * ---
     *
     * @subcommand list
     *
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function listSettings(array $args, array $assocArgs): void
    {
        $format = is_string($assocArgs['format'] ?? null) ? $assocArgs['format'] : 'table';
        $values = $this->values($this->settings->load());

        if (in_array($format, ['json', 'yaml'], true)) {
            $this->console->record($format, $values);

            return;
        }

        $rows = [];
        foreach ($values as $key => $value) {
            $rows[] = ['key' => $key, 'value' => $value];
        }
        $this->console->items($format, $rows, ['key', 'value']);
    }

    /**
     * Prints one setting.
     *
     * ## OPTIONS
     *
     * <key>
     * : Setting name, e.g. entries_per_page. `api_token` prints set, empty or unreadable.
     *
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function get(array $args, array $assocArgs): void
    {
        $values = $this->values($this->settings->load());
        $key = $args[0] ?? '';
        if (!array_key_exists($key, $values)) {
            /* translators: %s: setting name given on the command line. */
            $this->console->fail(sprintf(__('Unknown setting "%s".', 'mpcl'), $key));
        }

        $this->console->line((string) $values[$key]);
    }

    /**
     * Changes one setting.
     *
     * Writable: api_key, catalog_page_id (an existing page, or 0), box_full_width,
     * entries_per_page (0 = default), cache_autoupdate_enabled,
     * cache_autoupdate_interval (seconds). Booleans accept 1/0, true/false,
     * yes/no, on/off. The API token is set with `wp mpcl connect`.
     *
     * ## OPTIONS
     *
     * <key>
     * : Setting name.
     *
     * <value>
     * : New value.
     *
     * ## EXAMPLES
     *
     *     wp mpcl config set entries_per_page 24
     *     wp mpcl config set cache_autoupdate_enabled off
     *
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function set(array $args, array $assocArgs): void
    {
        $key = $args[0] ?? '';
        $value = $args[1] ?? '';

        if ('api_token' === $key) {
            $this->console->fail(__('The API token is not set here. Use "wp mpcl connect", which reads it from STDIN so that it never lands in the shell history.', 'mpcl'));
        }
        if (!in_array($key, self::WRITABLE_KEYS, true)) {
            /* translators: %s: setting name given on the command line. */
            $this->console->fail(sprintf(__('"%s" is not a setting that can be changed.', 'mpcl'), $key));
        }

        $settings = $this->settings->load();
        $previousKey = $settings->apiKey;
        $this->input->apply($settings, [$key => $this->normalise($key, $value)]);
        $this->settings->save($settings);

        if ($settings->apiKey !== $previousKey) {
            $this->scheduler->requestImmediate();
        }

        /* translators: 1: setting name, 2: its new value. */
        $this->console->success(sprintf(__('%1$s is now %2$s.', 'mpcl'), $key, (string) $this->values($settings)[$key]));
    }

    /**
     * @return array<string, scalar>
     */
    private function values(Settings $settings): array
    {
        return [
            'api_key' => $settings->apiKey,
            'api_token' => $settings->getApiTokenState(),
            'catalog_page_id' => $settings->catalogPageId,
            'box_full_width' => (int) $settings->boxFullWidth,
            'entries_per_page' => $settings->entriesPerPage,
            'cache_autoupdate_enabled' => (int) $settings->cacheAutoupdateEnabled,
            'cache_autoupdate_interval' => $settings->cacheAutoupdateInterval,
            'last_listing_cache' => $settings->lastListingCache,
        ];
    }

    private function normalise(string $key, string $value): string
    {
        if (in_array($key, SettingsInput::BOOLEAN_FIELDS, true)) {
            $lower = strtolower($value);
            if (in_array($lower, self::TRUE_VALUES, true)) {
                return '1';
            }
            if (in_array($lower, self::FALSE_VALUES, true)) {
                return '0';
            }
            /* translators: %s: setting name. */
            $this->console->fail(sprintf(__('%s takes 1/0, true/false, yes/no or on/off.', 'mpcl'), $key));
        }

        if (in_array($key, SettingsInput::NUMBER_FIELDS, true)) {
            if (1 !== preg_match('/^\d+$/', $value)) {
                /* translators: %s: setting name. */
                $this->console->fail(sprintf(__('%s takes a whole number of 0 or more.', 'mpcl'), $key));
            }
            if ('catalog_page_id' === $key && 0 < (int) $value && null === $this->catalogPages->find((int) $value)) {
                /* translators: %d: page ID given on the command line. */
                $this->console->fail(sprintf(__('There is no published, private or draft page with ID %d.', 'mpcl'), (int) $value));
            }
        }

        return $value;
    }
}
