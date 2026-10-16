<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Database;

final class Schema
{
    private \wpdb $wpdb;
    private string $machinesTable;
    private string $optionsTable;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->machinesTable = $wpdb->prefix . 'mpcl_machines';
        $this->optionsTable = $wpdb->prefix . 'mpcl_options';
    }

    public function getMachinesTable(): string
    {
        return $this->machinesTable;
    }

    public function isInitialized(): bool
    {
        return $this->tableExists($this->machinesTable) && $this->tableExists($this->optionsTable);
    }

    /**
     * Recreates the machines table, and the options table unless asked to keep it.
     */
    public function initialize(bool $preserveOptions = false): void
    {
        $this->recreateMachinesTable();

        if (!$preserveOptions) {
            $this->recreateOptionsTable();
        }
    }

    private function recreateOptionsTable(): void
    {
        $this->wpdb->get_results("DROP TABLE `" . $this->optionsTable . "`;", OBJECT);
        $this->wpdb->get_results(
            "CREATE TABLE `" . $this->optionsTable . "` (
                `name` TINYTEXT NOT NULL ,
                `value` TEXT NOT NULL
              )  ENGINE = InnoDB DEFAULT CHARSET=utf8;",
            OBJECT
        );
    }

    private function recreateMachinesTable(): void
    {
        $this->wpdb->get_results("DROP TABLE `" . $this->machinesTable . "`", OBJECT);
        $this->wpdb->get_results(
            "CREATE TABLE `" . $this->machinesTable . "`
                (
                id INT(11) PRIMARY KEY NOT NULL,
                uid INT(11),
                created INT(11),
                modified INT(11),
                slug TINYTEXT,
                is_visible TINYINT(1) DEFAULT '0' NOT NULL,
                description TEXT,
                physical_state INT(11),
                custom_name TINYTEXT,
                name TINYTEXT,
                manufacturer TINYTEXT,
                manufacturer_id INT(11),
                year_of_production TINYTEXT,
                type_name TINYTEXT,
                type_id INT(11),
                serial_number TINYTEXT,
                price TINYTEXT,
                photos TEXT,
                is_extension TINYINT(1) DEFAULT '0' NOT NULL,
                is_standalone TINYINT(1) DEFAULT '0' NOT NULL,
                synchronized INT(11)
            );",
            OBJECT
        );
        $this->wpdb->get_results("ALTER TABLE `" . $this->machinesTable . "` ADD UNIQUE(`id`);", OBJECT);
    }

    private function tableExists(string $table): bool
    {
        return $table === $this->wpdb->get_var("SHOW TABLES LIKE '" . $table . "'");
    }
}
