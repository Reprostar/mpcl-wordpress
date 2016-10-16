<?php

namespace Reprostar\MpclWordpress;
use JBBCode\DefaultCodeDefinitionSet;
use JBBCode\Parser;
use Reprostar\MpclConnector\MpclPhotoRemoteModel;
use Smarty;

/**
 * Class MpclPlugin
 */
class MpclPlugin
{
    private static $cwd;

    private $wpdb;

    private $pluginSettings;
    private static $baseUrl;

    private $database;

    const SHORTCODE_CATALOG = "mypclist";
    const SHORTCODE_CATALOG_ALIAS = "mpcl-catalog";
    const SHORTCODE_SINGLE = "mpcl-single";

    /**
     * @return string
     */
    public static function getCwd(){
        return self::$cwd;
    }

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
        $this->database = MpclDatabase::getInstance();
        $this->pluginSettings = new MpclPluginSettings(self::getCwd(), self::$baseUrl, $this->database);

        add_filter('query_vars', array(&$this, 'queryVars'));
        $this->registerShortcodes();

        Configuration::getInstance();

        $this->connector = MpclSynchronisator::getInstance($this->database, Configuration::getInstance()->get("api_key"), Configuration::getInstance()->get("api_token"));

        // Perform database check (and recreate tables if needed)
        if (!$this->database->checkIfInitialized()) {
            $this->database->initDatabase();
        }
    }

    /**
     * Register required shortcodes in WP
     */
    private function registerShortcodes(){
        add_shortcode(self::SHORTCODE_CATALOG, array(&$this, 'tagHandlerCatalog'));
        add_shortcode(self::SHORTCODE_CATALOG_ALIAS, array(&$this, 'tagHandlerCatalog'));
        add_shortcode(self::SHORTCODE_SINGLE, array(&$this, 'tagHandlerSingle'));
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

    /**
     * @param $attr
     */
    public function tagHandlerSingle($attr){
        $controller = new BoxController();
        $controller->execute(array(
            "machineId" => isset($attr['machine_id']) ? (int) $attr['machine_id'] : null
        ));
    }

    /**
     * Handle mypclist shortcode
     * @param $attr
     */
    public function tagHandlerCatalog($attr)
    {
        $machineId = get_query_var('machine_id', null);

        $columns_amount = isset($attr['columns']) && is_numeric($attr['columns']) && $attr['columns'] > 0 ? (int) $attr['columns'] : 5;

        if ($machineId === null) {
            $controller = new CatalogListController();
            $params = array(
                "columnsAmount" => $columns_amount
            );
        } else {
            $controller = new CatalogSingleController();
            $params = array(
                "machineId" => $machineId
            );
        }

        $controller->execute($params);
    }

    public function controllerSingle(Smarty $smarty){
        $machine_id = get_query_var('machine_id', null);

        $machine = $this->database->getMachine($machine_id);
        if(!is_object($machine)){
            $machine = false;
        } else{
            $machine = $machine->toAssoc();
            $machine['thumbnail_uri'] = Utils::getPhotoURI(count($machine['photos']) ? $machine['photos'][0] : '', 300);
            $machine['name'] = !empty($machine['name']) ? $machine['name'] : __('Unnamed', 'mpcl');

            foreach($machine['photos'] as $k => $photo){
                $machine['photos'][$k] = array(
                    "raw" => Utils::getPhotoURI($photo),
                    100 => Utils::getPhotoURI($photo, 100)
                );
            }

            $parser = new Parser();
            $parser->addCodeDefinitionSet(new DefaultCodeDefinitionSet());
            $parser->parse($machine['description']);

            $html = $parser->getAsHtml();
            $pattern = array("\\r\\n", "\n", "\t", '  ', '  ');
            $replace = array('<br />', '<br />', '&#160; &#160; ', '&#160; ', ' &#160;');
            $html = str_replace($pattern, $replace, $html);

            $machine['description'] = $html;
            $machine['physical_state'] = Utils::getStateString($machine['physical_state']);
        }

        $smarty->assign("machine", $machine);

        $smarty->display("single.tpl");
    }

    /**
     * Get machines listing
     * @param int $limit
     * @param int $offset
     * @return bool|array[]
     */
    public function getMachinesListing($limit = 0, $offset = 0)
    {
        if (!is_numeric($limit) || !is_numeric($offset)) {
            return false;
        }

        if (Configuration::getInstance()->get("cache_autoupdate_enabled") && time() - $this->database->getOption('last_listing_cache') >= (int) Configuration::getInstance()->get("cache_autoupdate_interval")) {
            // Auto-update machines list
            $this->connector->importMachinesOneByOne();
        }

        $machines = $this->database->getMachines("id", "DESC", $limit, $offset);
        if(!is_array($machines)){
            $machines = array();
        }

        foreach($machines as $k => $machine){
            $machines[$k] = $machine->toAssoc();
        }

        return $machines;
    }


}