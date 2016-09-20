<?php

namespace Reprostar\MpclWordpress;

/**
 * Class MpclDatabase
 */
class MpclDatabase
{
    const TABLE_OPTIONS = "mpcl_options";
    const TABLE_MACHINES = "mpcl_machines";

    // Prefixed table names, for use in direct queries
    private $tableNameOptions;
    private $tableNameMachines;

    private $wpdb;

    /**
     * MpclDatabase constructor.
     */
    public function __construct()
    {
        global $wpdb;

        $this->wpdb = $wpdb;

        $this->tableNameOptions = $this->wpdb->prefix . self::TABLE_OPTIONS;
        $this->tableNameMachines = $this->wpdb->prefix . self::TABLE_MACHINES;
    }

    /**
     * @return bool
     */
    public function deleteAllMachines()
    {
        return (bool)$this->wpdb->get_results("DELETE FROM `" . $this->tableNameMachines . "`", OBJECT);
    }

    /**
     * Wrapper for real_escape_string MySQL function
     * @param $str
     * @return string
     */
    private function escapeString($str)
    {
        return $this->wpdb->_real_escape((string)$str);
    }

    /**
     * @param MpclMachineModel $machine
     * @return false|int
     */
    public function saveMachine(MpclMachineModel $machine)
    {
        return $this->wpdb->replace($this->tableNameMachines, array(
            "id" => $machine->id,
            "uid" => $machine->uid,
            "created" => $machine->created,
            "modified" => $machine->modified,
            "slug" => $machine->slug,
            "is_visible" => $machine->is_visible,
            "description" => $machine->description,
            "physical_state" => $machine->physical_state,
            "custom_name" => $machine->custom_name,
            "name" => $machine->name,
            "manufacturer" => $machine->manufacturer,
            "manufacturer_id" => $machine->manufacturer_id,
            "year_of_production" => $machine->year_of_production,
            "type_name" => $machine->type_name,
            "type_id" => $machine->type_id,
            "serial_number" => $machine->serial_number,
            "price" => $machine->price,
            "photos" => json_encode($machine->photos),
            "is_extension" => $machine->is_extension,
            "is_standalone" => $machine->is_standalone,
            "synchronized" => time()
        ));
    }

    /**
     * @param $id
     * @return bool|MpclMachineModel
     */
    public function getMachine($id)
    {
        $id = $this->escapeString($id);

        $results = $this->wpdb->get_results("SELECT * FROM `" . $this->tableNameMachines . "` WHERE id='" . $id . "' LIMIT 1", ARRAY_A);

        if (is_array($results) && count($results) == 1) {
            $model = new MpclMachineModel();
            $model->fromAssoc($results[0]);

            if (!is_array($model->photos)) {
                $model->photos = json_decode($model->photos, JSON_OBJECT_AS_ARRAY);

                if (!is_array($model->photos)) {
                    $model->photos = array();
                }
            }

            return $model;
        } else {
            return false;
        }
    }

    /**
     * @param string $orderBy
     * @param string $orderDir
     * @param int $limit
     * @param int $offset
     * @return MpclMachineModel[]
     */
    public function getMachines($orderBy = "id", $orderDir = "DESC", $limit = 20, $offset = 0)
    {
        $limit = $this->escapeString($limit);
        $offset = $this->escapeString($offset);

        $q = "SELECT * FROM `" . $this->tableNameMachines . "`";

        if (in_array($orderBy, array("id", "name", "created")) && in_array($orderDir, array("DESC", "ASC"))) {
            $q .= " ORDER BY " . $orderBy . " " . $orderDir;
        }

        $q .= " LIMIT " . $limit . " OFFSET " . $offset;

        $results = $this->wpdb->get_results($q, ARRAY_A);

        if (!is_array($results)) {
            $results = array();
        }

        foreach ($results as $k => $result) {
            $model = new MpclMachineModel();
            $model->fromAssoc($result);

            if (!is_array($model->photos)) {
                $model->photos = json_decode($model->photos, JSON_OBJECT_AS_ARRAY);

                if (!is_array($model->photos)) {
                    $model->photos = array();
                }
            }

            $results[$k] = $model;
        }

        return $results;
    }

    /**
     * @param $key
     * @return bool
     */
    public function getOption($key)
    {
        $key = $this->escapeString($key);
        $result = $this->wpdb->get_results("SELECT * FROM `" . $this->tableNameOptions . "` WHERE `name`='" . $key . "' LIMIT 1", ARRAY_A);
        if (!is_array($result) || !count($result)) {
            return false;
        }

        return $result[0]['value'];
    }

    /**
     * @param $key
     * @param $value
     */
    public function setOption($key, $value)
    {
        $key = $this->escapeString($key);

        $data = array(
            'name' => $key,
            'value' => $value
        );

        $result = $this->wpdb->get_results("SELECT * FROM `" . $this->tableNameOptions . "` WHERE `name`='" . $key . "' LIMIT 1", ARRAY_A);
        if (!$result) {
            $this->wpdb->insert($this->tableNameOptions, $data);
        } else {
            $this->wpdb->update($this->tableNameOptions,
                $data,
                array(
                    'name' => $key
                )
            );
        }
    }

    /**
     * Initialize MySQL tables required for plugin's functionality
     * @param bool $preserveOptions
     */
    public function initDatabase($preserveOptions = false)
    {
        $this->initStorageTables();

        if (!$preserveOptions) {
            $this->initOptionTables();
        }
    }

    /**
     * Recreate tables used for configuration storage
     */
    private function initOptionTables()
    {
        $queries = array(
            // mpcl_options
            "DROP TABLE `" . $this->tableNameOptions . "`;",
            "CREATE TABLE `" . $this->tableNameOptions . "` (
                `name` TINYTEXT NOT NULL ,
                `value` TEXT NOT NULL
              )  ENGINE = InnoDB DEFAULT CHARSET=utf8;"
        );

        foreach ($queries as $q) {
            $this->wpdb->get_results($q, OBJECT);
        }
    }

    /**
     * Recreate tables used for machines and other remote models storage
     */
    private function initStorageTables()
    {
        $queries = array(
            // mpcl_machines
            "DROP TABLE `" . $this->tableNameMachines . "`",
            "CREATE TABLE `" . $this->tableNameMachines . "`
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
            "ALTER TABLE `" . $this->tableNameMachines . "` ADD UNIQUE(`id`);"
        );

        foreach ($queries as $q) {
            $this->wpdb->get_results($q, OBJECT);
        }
    }

    /**
     * Return true when all required tables exists in the database
     * @return bool
     */
    public function checkIfInitialized(){
        $tableMachinesExists = $this->wpdb->get_var("SHOW TABLES LIKE '" .  $this->tableNameMachines . "'") == $this->tableNameMachines;
        $tableOptionsExists = $this->wpdb->get_var("SHOW TABLES LIKE '" .  $this->tableNameOptions . "'") == $this->tableNameOptions;

        return $tableMachinesExists && $tableOptionsExists;
    }
}