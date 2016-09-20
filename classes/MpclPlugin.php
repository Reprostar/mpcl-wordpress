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
    private $wpdb;

    private $connector;
    private $options;
    private $pluginSettings;
    private $cwd;
    private $baseUrl;

    private $database;

    private $defaultOptions = array(
        'api_key' => '',
        'api_token' => '',
        'cache_images' => 0,
        'cache_autoupdate_enabled' => 1,
        'cache_autoupdate_interval' => 3600,
        'last_listing_cache' => 0
    );

    /**
     * MpclPlugin constructor.
     * @param string $cwd
     * @param string $baseUrl
     */
    public function __construct($cwd, $baseUrl)
    {
        global $wpdb;

        add_action('plugins_loaded', array(&$this, 'load_textdomain'));

        $this->wpdb = $wpdb;
        $this->cwd = $cwd;
        $this->baseUrl = $baseUrl;
        $this->database = new MpclDatabase();
        $this->pluginSettings = new MpclPluginSettings($this->cwd, $this->baseUrl, $this->database, $this->defaultOptions);

        add_filter('query_vars', array(&$this, 'query_vars'));
        add_shortcode("mypclist", array(&$this, 'tag_handler'));

        $this->options = get_option('mpcl-options');
        if (!is_array($this->options)) {
            update_option('mpcl-options', $this->defaultOptions);
            $this->options = $this->defaultOptions;
        }

        $this->connector = new MpclSynchronisator($this->database, $this->options['api_key'], $this->options['api_token']);

        // Perform database check (and recreate tables if needed)
        if (!$this->database->checkIfInitialized()) {
            $this->database->initDatabase();
        }
    }

    public function load_textdomain()
    {
        load_plugin_textdomain('mpcl', false, plugin_basename($this->cwd) . '/languages/');
    }

    /**
     * Register 'machine_id' query variable
     * @param $qvars
     * @return array
     */
    public function query_vars($qvars)
    {
        $qvars[] = 'machine_id';
        return $qvars;
    }

    /**
     * Handle mypclist shortcode
     * @param $attr
     */
    public function tag_handler($attr)
    {
        $machine_id = get_query_var('machine_id', null);

        $smarty = new Smarty();
        $smarty->force_compile = true;
        $smarty->debugging = false;
        $smarty->caching = true;
        $smarty->cache_lifetime = 120;

        $columns_amount = isset($attr['columns']) && is_numeric($attr['columns']) && $attr['columns'] > 0 ? (int) $attr['columns'] : 5;

        $smarty->setTemplateDir($this->cwd . "/templates");

        if ($machine_id === null) {
            $this->controllerListing($smarty, $columns_amount);
        } else {
            $this->controllerSingle($smarty);
        }
    }

    public function controllerSingle(Smarty $smarty){
        $machine_id = get_query_var('machine_id', null);

        $machine = $this->database->getMachine($machine_id);
        if(!is_object($machine)){
            $machine = false;
        } else{
            $machine = $machine->toAssoc();
            $machine['thumbnail_uri'] = $this->getPhotoURI(count($machine['photos']) ? $machine['photos'][0] : '', 300);
            $machine['name'] = !empty($machine['name']) ? $machine['name'] : __('Unnamed', 'mpcl');

            foreach($machine['photos'] as $k => $photo){
                $machine['photos'][$k] = array(
                    "raw" => $this->getPhotoURI($photo),
                    100 => $this->getPhotoURI($photo, 100)
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
            $machine['physical_state'] = $this->getStateString($machine['physical_state']);
        }

        $smarty->assign("machine", $machine);

        $smarty->display("single.tpl");
    }

    public function controllerListing(Smarty $smarty, $columns_amount){
        $columns_width = ( (string) (100 / $columns_amount) ) . "%";

        $limit = 20;
        $offset = 0;
        if ($this->options['cache_autoupdate_enabled'] && time() - $this->database->getOption('last_listing_cache') >= (int)$this->options['cache_autoupdate_interval']) {
            // Auto-update machines list
            $this->connector->importMachinesOneByOne();
        }

        $machines = $this->database->getMachines("id", "DESC", $limit, $offset);
        if(!is_array($machines)){
            $machines = array();
        }

        $rows = array();
        for($i = 0; $i < count($machines); $i++){
            $machine = $machines[$i]->toAssoc();
            $row_idx = (int) floor($i / $columns_amount);

            if(!isset($rows[$row_idx]) || !is_array($rows[$row_idx])){
                $rows[$row_idx] = array();
            }

            $machine['thumbnail_uri'] = $this->getPhotoURI(count($machine['photos']) ? $machine['photos'][0] : '', 300);
            $machine['name'] = !empty($machine['name']) ? $machine['name'] : __('Unnamed', 'mpcl');

            $rows[$row_idx][] = $machine;
        }

        $smarty->assign("columns_width", $columns_width);
        $smarty->assign("columns_amount", $columns_amount);
        $smarty->assign("rows", $rows);

        $smarty->display("listing.tpl");
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

        if ($this->options['cache_autoupdate_enabled'] && time() - $this->database->getOption('last_listing_cache') >= (int)$this->options['cache_autoupdate_interval']) {
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

    /**
     * Parse Photo model/array and return image URI
     * @param MpclPhotoRemoteModel|array $photo
     * @param int $size
     * @return string
     */
    public function getPhotoURI($photo, $size = -1){
        $fallback = $this->baseUrl . "/img/sample_machine.png";
        
        if(is_object($photo) && $photo instanceof MpclPhotoRemoteModel){
            /**
             * @var $photo MpclPhotoRemoteModel
             */
            if($size > 0){
                if(is_array($photo->thumbnails) && isset($photo->thumbnails[$size])){
                    return $photo->thumbnails[$size];  
                } else{
                    return $fallback;
                }
            } else{
                return $photo->orig_uri;
            }
        } else if(is_array($photo)){
            if($size > 0){
                if(isset($photo['thumbnails']) && is_array($photo['thumbnails']) && isset($photo['thumbnails'][$size])){
                    return $photo['thumbnails'][$size];
                } else{
                    return $fallback;
                }
            } else if(isset($photo['orig_uri'])){
                return $photo['orig_uri'];
            } else{
                return $fallback;
            }
        } else{
            return $fallback;
        }
    }

    /**
     * @param $state
     * @return string|void
     */
    public function getStateString($state)
    {
        switch ($state) {
            default:
                return __("Unknown", "mpcl");
                break;
            case '1':
                return "1/5 - " . __("Broken", "mpcl");
                break;
            case '2':
                return "2/5 - " . __("Needs repair", "mpcl");
                break;
            case '3':
                return "3/5 - " . __("Partially broken", "mpcl");
                break;
            case '4':
                return "4/5 - " . __("Good", "mpcl");
                break;
            case '5':
                return "5/5 - " . __("Very good", "mpcl");
                break;
        }
    }
}