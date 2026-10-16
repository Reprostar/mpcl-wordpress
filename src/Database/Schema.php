<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Database;

use Reprostar\MpclWordpress\Support\Cast;

defined('ABSPATH') || exit;

/**
 * Creates and migrates the plugin's tables. Version 1 is everything 1.x created
 * (no version option was stored then); version 3 adds the server-rendered
 * description columns. Every migration keeps the machines table
 * and its rows.
 */
final class Schema
{
    public const VERSION = 3;
    private const VERSION_OPTION = 'mpcl_db_version';

    private \wpdb $wpdb;
    private string $machinesTable;
    private string $legacyOptionsTable;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->machinesTable = $wpdb->prefix . 'mpcl_machines';
        $this->legacyOptionsTable = $wpdb->prefix . 'mpcl_options';
    }

    public function getMachinesTable(): string
    {
        return $this->machinesTable;
    }

    public function isCurrent(): bool
    {
        return self::VERSION <= Cast::int(get_option(self::VERSION_OPTION, 0));
    }

    public function migrate(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($this->machinesTableDefinition());
        $this->convertToUtf8mb4();
        $this->dropDuplicateIdIndexes();
        $this->wpdb->query("DROP TABLE IF EXISTS `{$this->legacyOptionsTable}`");

        update_option(self::VERSION_OPTION, self::VERSION);
    }

    private function machinesTableDefinition(): string
    {
        return "CREATE TABLE {$this->machinesTable} (
  id int(11) NOT NULL,
  uid int(11) DEFAULT NULL,
  created int(11) DEFAULT NULL,
  modified int(11) DEFAULT NULL,
  slug tinytext,
  is_visible tinyint(1) NOT NULL DEFAULT '0',
  description text,
  description_format varchar(16) DEFAULT NULL,
  description_html mediumtext,
  physical_state int(11) DEFAULT NULL,
  custom_name tinytext,
  name tinytext,
  manufacturer tinytext,
  manufacturer_id int(11) DEFAULT NULL,
  year_of_production tinytext,
  type_name tinytext,
  type_id int(11) DEFAULT NULL,
  serial_number tinytext,
  price tinytext,
  photos text,
  is_extension tinyint(1) NOT NULL DEFAULT '0',
  is_standalone tinyint(1) NOT NULL DEFAULT '0',
  synchronized int(11) DEFAULT NULL,
  PRIMARY KEY  (id)
) {$this->wpdb->get_charset_collate()};";
    }

    /**
     * 1.x created the table with the server's default charset, which on many
     * hosts cannot store 4-byte characters such as emoji. Converting in place
     * keeps the rows.
     */
    private function convertToUtf8mb4(): void
    {
        if ('utf8mb4' !== $this->wpdb->charset) {
            return;
        }

        $collation = $this->wpdb->get_var($this->wpdb->prepare(
            'SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
            $this->machinesTable
        ));

        if (!is_string($collation) || 0 === strpos($collation, 'utf8mb4_')) {
            return;
        }

        $collate = '' !== (string) $this->wpdb->collate ? ' COLLATE ' . $this->wpdb->collate : '';
        $this->wpdb->query("ALTER TABLE `{$this->machinesTable}` CONVERT TO CHARACTER SET utf8mb4{$collate}");
    }

    /**
     * 1.x added UNIQUE(id) next to the primary key every time it recreated the table.
     */
    private function dropDuplicateIdIndexes(): void
    {
        $indexes = $this->wpdb->get_col(
            "SHOW INDEX FROM `{$this->machinesTable}` WHERE Key_name <> 'PRIMARY' AND Non_unique = 0 AND Column_name = 'id'",
            2
        );

        foreach (array_unique(array_filter($indexes, 'is_string')) as $index) {
            $this->wpdb->query("ALTER TABLE `{$this->machinesTable}` DROP INDEX `" . esc_sql($index) . '`');
        }
    }
}
