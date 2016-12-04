<?php

namespace Reprostar\MpclWordpress;

/**
 * Class MpclPlugin
 */
class Plugin
{

    const SHORTCODE_CATALOG = "mypclist";
    const SHORTCODE_CATALOG_ALIAS = "mpcl-catalog";
    const SHORTCODE_MPCL = "mpcl";

    private static $cwd;
    private static $baseUrl;

    /**
     * @var \wpdb
     */
    private $wpdb;

    /**
     * @var PluginSettings
     */
    private $pluginSettings;

    /**
     * @var Database
     */
    private $database;

    /**
     * @var TagHandler
     */
    private $tagHandler;

    /**
     * @return string
     */
    public static function getCwd(){
        return self::$cwd;
    }

    /**
     * @return string
     */
    public static function getBaseUrl(){
        return self::$baseUrl;
    }

    /**
     * MpclPlugin constructor.
     * @param string $cwd
     * @param string $baseUrl
     */
    public function __construct($cwd, $baseUrl)
    {
        global $wpdb;
        self::$cwd = $cwd;

        add_action('plugins_loaded', array(&$this, 'loadTextdomain'));

        $this->wpdb = $wpdb;
        self::$baseUrl = $baseUrl;
        $this->database = Database::getInstance();
        $this->pluginSettings = new PluginSettings(self::getCwd(), self::$baseUrl, $this->database);
        $this->tagHandler = new TagHandler();

        add_filter('query_vars', array(&$this, 'queryVars'));
        $this->registerShortcodes();

        Configuration::getInstance();

        $this->connector = Synchronizator::getInstance($this->database, Configuration::getInstance()->get("api_key"), Configuration::getInstance()->get("api_token"));

        // Perform database check (and recreate tables if needed)
        if (!$this->database->checkIfInitialized()) {
            $this->database->initDatabase();
        }
    }

    /**
     * Register required shortcodes in WP
     */
    private function registerShortcodes(){
        $handleCallback = [&$this->tagHandler, 'handle'];

        add_shortcode(self::SHORTCODE_CATALOG, $handleCallback);
        add_shortcode(self::SHORTCODE_CATALOG_ALIAS, $handleCallback);
        add_shortcode(self::SHORTCODE_MPCL, $handleCallback);
    }

    /**
     * Prepare plugin localisation
     */
    public function loadTextdomain()
    {
        load_plugin_textdomain('mpcl', false, plugin_basename(self::getCwd()) . '/languages/');
    }

    /**
     * Register 'machine_id' query variable
     * @param $qvars
     * @return array
     */
    public function queryVars($qvars)
    {
        $qvars[] = 'machine_id';
        return $qvars;
    }
}