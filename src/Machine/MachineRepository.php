<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Machine;

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

    public function save(Machine $machine, int $synchronizedAt): bool
    {
        $row = $machine->toRow();
        $row['synchronized'] = $synchronizedAt;

        return false !== $this->wpdb->replace($this->table, $row);
    }

    public function find(int $id): ?Machine
    {
        $row = $this->wpdb->get_row(
            "SELECT * FROM `" . $this->table . "` WHERE id='" . $this->wpdb->_real_escape((string) $id) . "' LIMIT 1",
            ARRAY_A
        );

        return is_array($row) ? Machine::fromRow($row) : null;
    }

    /**
     * @return list<Machine>
     */
    public function findAll(string $orderBy, string $orderDirection, int $limit, int $offset): array
    {
        $query = "SELECT * FROM `" . $this->table . "`";

        if (in_array($orderBy, self::ORDER_COLUMNS, true) && in_array($orderDirection, self::ORDER_DIRECTIONS, true)) {
            $query .= " ORDER BY " . $orderBy . " " . $orderDirection;
        }

        $query .= " LIMIT " . $limit . " OFFSET " . $offset;

        $rows = $this->wpdb->get_results($query, ARRAY_A);
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
        $this->wpdb->get_results("DELETE FROM `" . $this->table . "`", OBJECT);
    }
}
