<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Cli;

use Reprostar\MpclWordpress\Machine\Machine;
use Reprostar\MpclWordpress\Machine\MachineRepository;
use Reprostar\MpclWordpress\Machine\Photo;
use Reprostar\MpclWordpress\Settings\CatalogPages;
use Reprostar\MpclWordpress\Settings\SettingsRepository;
use Reprostar\MpclWordpress\Support\Cast;

defined('ABSPATH') || exit;

/**
 * Shows the machines synchronised from mypclist.net.
 */
final class MachinesCommand
{
    private const LIST_FIELDS = ['id', 'name', 'manufacturer', 'type', 'year', 'photos', 'visible', 'synchronized'];
    private const ORDER_COLUMNS = ['id', 'name', 'created'];

    private MachineRepository $machines;
    private SettingsRepository $settings;
    private CatalogPages $catalogPages;
    private Console $console;

    public function __construct(MachineRepository $machines, SettingsRepository $settings, CatalogPages $catalogPages, Console $console)
    {
        $this->machines = $machines;
        $this->settings = $settings;
        $this->catalogPages = $catalogPages;
        $this->console = $console;
    }

    /**
     * Lists the synchronised machines.
     *
     * ## OPTIONS
     *
     * [--fields=<fields>]
     * : Comma-separated fields. Available: id, name, manufacturer, type, year, photos, visible, synchronized, created, modified, slug, serial_number, physical_state, extension, standalone.
     *
     * [--orderby=<column>]
     * : Sort column.
     * ---
     * default: id
     * options:
     *   - id
     *   - name
     *   - created
     * ---
     *
     * [--order=<order>]
     * : Sort direction.
     * ---
     * default: desc
     * options:
     *   - asc
     *   - desc
     * ---
     *
     * [--limit=<number>]
     * : Maximum number of machines; 0 lists all.
     * ---
     * default: 0
     * ---
     *
     * [--offset=<number>]
     * : Number of machines to skip.
     * ---
     * default: 0
     * ---
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
     *   - count
     * ---
     *
     * ## EXAMPLES
     *
     *     wp mpcl machines list --orderby=name --order=asc --fields=id,name
     *     wp mpcl machines list --format=count
     *
     * @subcommand list
     *
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function listMachines(array $args, array $assocArgs): void
    {
        $orderBy = is_string($assocArgs['orderby'] ?? null) && in_array($assocArgs['orderby'], self::ORDER_COLUMNS, true)
            ? $assocArgs['orderby']
            : 'id';
        $order = 'asc' === ($assocArgs['order'] ?? 'desc') ? 'ASC' : 'DESC';
        $limit = $this->nonNegative($assocArgs, 'limit');
        $offset = $this->nonNegative($assocArgs, 'offset');

        $machines = $this->machines->findAll($orderBy, $order, 0 < $limit ? $limit : max(1, $this->machines->count()), $offset);
        $rows = array_map([$this, 'row'], $machines);

        $fields = is_string($assocArgs['fields'] ?? null) ? array_values(array_filter(array_map('trim', explode(',', $assocArgs['fields'])))) : self::LIST_FIELDS;
        $available = array_keys($this->row(new Machine()));
        $unknown = array_diff($fields, $available);
        if ([] !== $unknown) {
            /* translators: %s: comma-separated field names. */
            $this->console->fail(sprintf(__('Unknown fields: %s', 'mpcl'), implode(', ', $unknown)));
        }

        $this->console->items(is_string($assocArgs['format'] ?? null) ? $assocArgs['format'] : 'table', $rows, $fields);
    }

    /**
     * Shows one synchronised machine with its description, photos and its
     * address on the catalogue page.
     *
     * ## OPTIONS
     *
     * <id>
     * : Machine ID on mypclist.net.
     *
     * [--field=<field>]
     * : Print only this field.
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
    public function get(array $args, array $assocArgs): void
    {
        $id = (int) ($args[0] ?? 0);
        $machine = 0 < $id ? $this->machines->find($id) : null;
        if (null === $machine) {
            /* translators: %d: machine ID. */
            $this->console->fail(sprintf(__('Machine %d is not in the synchronised collection.', 'mpcl'), $id));
        }

        $record = $this->row($machine) + [
            'description_format' => (string) $machine->descriptionFormat,
            'description' => (string) $machine->description,
            'photo_urls' => implode("\n", array_map(static function (Photo $photo): string {
                return (string) $photo->origUri;
            }, $machine->photos)),
            'catalog_url' => (string) $this->catalogPages->machineUrl($this->settings->load()->catalogPageId, $machine->id),
        ];

        $field = is_string($assocArgs['field'] ?? null) ? $assocArgs['field'] : null;
        if (null !== $field) {
            if (!array_key_exists($field, $record)) {
                /* translators: %s: field name. */
                $this->console->fail(sprintf(__('Unknown field: %s', 'mpcl'), $field));
            }
            $this->console->line((string) $record[$field]);

            return;
        }

        $this->console->record(is_string($assocArgs['format'] ?? null) ? $assocArgs['format'] : 'table', $record);
    }

    /**
     * @return array<string, scalar|null>
     */
    private function row(Machine $machine): array
    {
        return [
            'id' => $machine->id,
            'name' => Cast::isBlank($machine->customName) ? $machine->name : $machine->customName,
            'manufacturer' => $machine->manufacturer,
            'type' => $machine->typeName,
            'year' => $machine->yearOfProduction,
            'photos' => count($machine->photos),
            'visible' => (int) $machine->isVisible,
            'synchronized' => null !== $machine->synchronized ? gmdate('Y-m-d\TH:i:s\Z', $machine->synchronized) : null,
            'created' => null !== $machine->created ? gmdate('Y-m-d\TH:i:s\Z', $machine->created) : null,
            'modified' => null !== $machine->modified ? gmdate('Y-m-d\TH:i:s\Z', $machine->modified) : null,
            'slug' => $machine->slug,
            'serial_number' => $machine->serialNumber,
            'physical_state' => $machine->physicalState,
            'extension' => (int) $machine->isExtension,
            'standalone' => (int) $machine->isStandalone,
        ];
    }

    /**
     * @param array<string, mixed> $assocArgs
     */
    private function nonNegative(array $assocArgs, string $name): int
    {
        $value = $assocArgs[$name] ?? '0';
        if (!is_scalar($value) || 1 !== preg_match('/^\d+$/', (string) $value)) {
            /* translators: %s: option name. */
            $this->console->fail(sprintf(__('--%s takes a whole number of 0 or more.', 'mpcl'), $name));
        }

        return (int) $value;
    }
}
