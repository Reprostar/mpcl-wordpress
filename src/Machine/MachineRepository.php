<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Machine;

defined('ABSPATH') || exit;

final class MachineRepository
{
    private const ORDER_COLUMNS = ['id', 'name', 'created'];
    private const ORDER_DIRECTIONS = ['DESC', 'ASC'];

    private \wpdb $wpdb;
    private string $table;

    public function __construct(\wpdb $wpdb, string $table)
    {
        $this->wpdb = $wpdb;
        $this->table = $table;
    }

    /**
     * Replaces every stored machine inside one transaction (the table is InnoDB
     * on any current MySQL/MariaDB), so readers see either the old or the new set.
     *
     * @param list<Machine> $machines
     * @return int number of machines stored
     */
    public function replaceAll(array $machines, int $synchronizedAt): int
    {
        $this->wpdb->query('START TRANSACTION');
        $this->deleteAll();

        $stored = 0;
        foreach ($machines as $machine) {
            if ($this->save($machine, $synchronizedAt)) {
                ++$stored;
            }
        }

        $this->wpdb->query('COMMIT');

        return $stored;
    }

    public function save(Machine $machine, int $synchronizedAt): bool
    {
        $row = $machine->toRow();
        $row['synchronized'] = $synchronizedAt;

        if (false === $this->wpdb->replace($this->table, $row)) {
            error_log(sprintf('mpcl: could not save machine %d: %s', $machine->id, $this->wpdb->last_error));

            return false;
        }

        return true;
    }

    public function find(int $id): ?Machine
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM `{$this->table}` WHERE id = %d LIMIT 1", $id),
            ARRAY_A
        );

        return is_array($row) ? Machine::fromRow($row) : null;
    }

    /**
     * @return list<Machine>
     */
    public function findAll(string $orderBy, string $orderDirection, int $limit, int $offset): array
    {
        $query = "SELECT * FROM `{$this->table}`";

        if (in_array($orderBy, self::ORDER_COLUMNS, true) && in_array($orderDirection, self::ORDER_DIRECTIONS, true)) {
            $query .= " ORDER BY {$orderBy} {$orderDirection}";
        }

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare($query . ' LIMIT %d OFFSET %d', $limit, $offset),
            ARRAY_A
        );
        if (!is_array($rows)) {
            return [];
        }

        $machines = [];
        foreach ($rows as $row) {
            $machines[] = Machine::fromRow($row);
        }

        return $machines;
    }

    public function deleteAll(): void
    {
        $this->wpdb->query("DELETE FROM `{$this->table}`");
    }
}
