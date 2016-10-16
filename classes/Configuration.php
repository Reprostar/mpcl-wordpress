<?php
/**
 * Created by PhpStorm.
 * User: pfcode
 * Date: 08.10.16
 * Time: 11:52
 */

namespace Reprostar\MpclWordpress;


class Configuration
{
    /**
     * @var Configuration
     */
    private static $instance;

    /**
     * @return Configuration
     */
    public static function getInstance(){
        if(!is_object(self::$instance)){
            self::$instance = new Configuration();
        }

        return self::$instance;
    }

    /**
     * @var array
     */
    private $options;

    /**
     * Options constructor.
     */
    public function __construct()
    {
        $this->load();
    }

    /**
     * @param $key
     * @return null
     */
    public function get($key){
        if(isset($this->options[$key])){
            return $this->options[$key];
        }

        return null;
    }

    /**
     * @param $key
     * @param $value
     */
    public function set($key, $value){
        $this->options[$key] = $value;
    }

    /**
     * @return bool
     */
    public function load(){
        $this->options = get_option('mpcl-options');
        if (!is_array($this->options)) {
            $this->options = $this->getDefaults();
            return $this->save();
        } else{
            return true;
        }
    }

    /**
     * @return bool
     */
    public function save(){
       return update_option('mpcl-options', $this->options);
    }

    /**
     * @return array
     */
    private function getDefaults(){
        return array(
            'api_key' => '',
            'api_token' => '',
            'cache_images' => 0,
            'cache_autoupdate_enabled' => 1,
            'cache_autoupdate_interval' => 3600,
            'last_listing_cache' => 0
        );
    }
}