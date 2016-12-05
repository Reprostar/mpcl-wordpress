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
        $settings = [
            'auth' => [
                'name' => __('Authorization', 'mpcl'),
                'fields' => [
                    'api_key' => [
                        'name' => __('API key', 'mpcl'),
                        'handler' => [&$this, 'field_text']
                    ],
                    'api_token' => [
                        'name' => __('API token', 'mpcl'),
                        'handler' => [&$this, 'field_text']
                    ]
                ]
            ],
            'catalog' => [
                'name' => __('Catalog page', 'mpcl'),
                'fields' => [
                    'catalog_page_id' => [
                        'name' => __('Catalog page ID', 'mpcl'),
                        'handler' => [&$this, 'field_text']
                    ],
                ]
            ],
            'appearance' => [
                'name' => __('Appearance', 'mpcl'),
                'fields' => [
                    'box_full_width' => [
                        'name' => __('Full-width boxes', 'mpcl'),
                        'handler' => [&$this, 'field_checkbox']
                    ],
                    'entries_per_page' => [
                        'name' => __('Entries per page', 'mpcl'),
                        'handler' => [&$this, 'field_text']
                    ]
                ]
            ],
            'cache' => [
                'name' => __('Data cache', 'mpcl'),
                'fields' => [
                    'cache_images' => [
                        'name' => __('Enable image cache', 'mpcl'),
                        'handler' => [&$this, 'field_checkbox']
                    ],
                    'cache_autoupdate_enabled' => [
                        'name' => __('Enable cache auto-update', 'mpcl'),
                        'handler' => [&$this, 'field_checkbox']
                    ],
                    'cache_autoupdate_interval' => [
                        'name' => __('Update interval (seconds)', 'mpcl'),
                        'handler' => [&$this, 'field_text']
                    ],
                ]
            ]
        ];

        $sectionPrefix = 'mpcl-section-';
        $optionsKey = 'mpcl-options';
        foreach($settings as $sectionKey => $section){
            $sectionKeyPrefixed = $sectionPrefix . $sectionKey;
            $sectionName = $section['name'];
            $descriptionCallback = [&$this, 'description_' . $sectionKey];

            add_settings_section($sectionKeyPrefixed, $sectionName, $descriptionCallback, $optionsKey);

            foreach($section['fields'] as $fieldKey => $field){
                $fieldName = $field['name'];
                $fieldHandler = $field['handler'];

                add_settings_field($fieldKey, $fieldName, $fieldHandler, $optionsKey, $sectionKeyPrefixed, [$optionsKey, $fieldKey]);
            }
        }

        register_setting($optionsKey, $optionsKey, [&$this, 'save_theme_option']);
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