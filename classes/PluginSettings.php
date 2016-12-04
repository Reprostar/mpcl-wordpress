<?php

namespace Reprostar\MpclWordpress;

/**
 * Class MpclPluginSettings
 */
class PluginSettings{
    private $cwd;
    private $baseUrl;
    private $database;

    /**
     * MpclPluginSettings constructor.
     * @param $cwd
     * @param $baseUrl
     * @param $dbHandler
     */
    public function __construct($cwd, $baseUrl, Database $dbHandler){
        $this->cwd = $cwd;
        $this->baseUrl = $baseUrl;
        $this->database = $dbHandler;

        add_action('init', array(&$this, 'register_assets'));

        if(is_admin()){
            add_action('admin_menu', array(&$this, 'register_options_page'));
            add_action('admin_init', array(&$this, 'register_settings'));
        }
    }


    public function register_options_page(){
        add_options_page('MyPCList Integration', 'MyPCList Integration', 'administrator', 'mpcl-settings', array(&$this, 'view_options_page'));
    }

    public function register_settings(){
        add_settings_section('mpcl-section-auth', __('Authorization', 'mpcl'), array(&$this, 'description_auth'), 'mpcl-options');

        add_settings_field('api_key', __('API key', 'mpcl'), array(&$this, 'field_text'), 'mpcl-options', 'mpcl-section-auth', array('mpcl-options', 'api_key'));
        add_settings_field('api_token', __('API token', 'mpcl'), array(&$this, 'field_text'), 'mpcl-options', 'mpcl-section-auth', array('mpcl-options', 'api_token'));

        add_settings_section('mpcl-section-catalog', __('Catalog page', 'mpcl'), array(&$this, 'description_catalog'), 'mpcl-options');

        add_settings_field('catalog_page_id', __('Catalog page ID', 'mpcl'), array(&$this, 'field_text'), 'mpcl-options', 'mpcl-section-catalog', array('mpcl-options', 'catalog_page_id'));

        add_settings_section('mpcl-section-appearance', __('Appearance', 'mpcl'), array(&$this, 'description_appearance'), 'mpcl-options');

        add_settings_field('box_full_width', __('Full-width boxes', 'mpcl'), array(&$this, 'field_checkbox'), 'mpcl-options', 'mpcl-section-appearance', array('mpcl-options', 'box_full_width'));

        add_settings_section('mpcl-section-cache', __('Data cache', 'mpcl'), array(&$this, 'description_cache'), 'mpcl-options');

        add_settings_field('cache_images', __('Enable image cache', 'mpcl'), array(&$this, 'field_checkbox'), 'mpcl-options', 'mpcl-section-cache', array('mpcl-options', 'cache_images'));
        add_settings_field('cache_autoupdate_enabled', __('Enable cache auto-update', 'mpcl'), array(&$this, 'field_checkbox'), 'mpcl-options', 'mpcl-section-cache', array('mpcl-options', 'cache_autoupdate_enabled'));
        add_settings_field('cache_autoupdate_interval', __('Update interval (seconds)', 'mpcl'), array(&$this, 'field_text'), 'mpcl-options', 'mpcl-section-cache', array('mpcl-options', 'cache_autoupdate_interval'));

        register_setting('mpcl-options', 'mpcl-options', array(&$this, 'save_theme_option'));
    }

    public function save_theme_option($input) {
        if (isset($_POST['reset'])) {
            add_settings_error('settingName', 'SettingSlug', __('Cache has been cleared.', 'mpcl'), 'updated');
            $this->database->initDatabase(true);
        }

        return $input;
    }

    public function register_assets(){
        wp_register_style('mpcl-front-css', $this->baseUrl.'/css/front.css');
        wp_register_style('mpcl-front-baguette-css', $this->baseUrl.'/css/baguetteBox.min.css');
        wp_enqueue_style('mpcl-front-baguette-css');
        wp_enqueue_style('mpcl-front-css');

        wp_register_script('mpcl-front-baguette', $this->baseUrl.'/js/baguetteBox.min.js');
        wp_register_script('mpcl-front-js', $this->baseUrl.'/js/front.js');
        wp_enqueue_script('mpcl-front-baguette');
        wp_enqueue_script('mpcl-front-js');
    }

    public function view_options_page(){
        require_once $this->cwd.'/views/options.php';
    }

    public function field_text($args){
        $opt = $args[0];
        $subopt = $args[1];

        $options = get_option($opt);
        if(is_array($options) && isset($options[$subopt])){
            $value = $options[$subopt];
        } else{
            $value = '';
        }

        echo '<input type="text" name="'.$opt.'['.$subopt.']" value="'.$value.'"/>';
    }

    public function field_checkbox($args){
        $opt = $args[0];
        $subopt = $args[1];

        $options = get_option($opt);
        if(is_array($options) && isset($options[$subopt])){
            $value = $options[$subopt];
        } else{
            $value = '';
        }

        echo '<input type="hidden" name="'.$opt.'['.$subopt.']" value="0"/>';
        echo '<input type="checkbox" name="'.$opt.'['.$subopt.']" value="1"'.($value ? ' checked="checked"' : '').'/>';
    }

    public function description_auth(){
        echo __('Enter your API authorization codes, which can be generated <a target="_blank" href="http://mypclist.net/settings/api">here</a>. They\'re required to connect with MyPCList server.', 'mpcl');
    }

    public function description_cache(){
        echo __('For less server-to-server bandwith usage you can enable cache of your data. Activating cache will make your blog loading faster.', 'mpcl');
    }

    public function description_catalog(){
        echo __('Please enter ID of the page that is used as a Catalog template. This reference is required to display links in embedded machine boxes in posts.', 'mpcl');
    }

    public function description_appearance(){
        echo __('There you can customize plugin appearance on yout blog.', 'mpcl');
    }
}