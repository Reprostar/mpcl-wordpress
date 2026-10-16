<?php

/*
Plugin Name: MyPCList
Plugin URI: http://mypclist.net/wordpress
Description: Official MyPCList integration plugin. Show your machine collection on your own blog!
Version: 1.2.2
Author: pfcode
Author URI: http://mypclist.net
Text Domain: mpcl
Domain Path: /languages
License: MIT
*/

const MPCL_VERSION = '1.2.2';

require_once __DIR__ . '/src/autoload.php';

register_activation_hook(__FILE__, static function (): void {
    (new \Reprostar\MpclWordpress\Plugin(__FILE__, $GLOBALS['wpdb']))->activate();
});

register_deactivation_hook(__FILE__, static function (): void {
    (new \Reprostar\MpclWordpress\Plugin(__FILE__, $GLOBALS['wpdb']))->deactivate();
});

add_action('plugins_loaded', static function (): void {
    (new \Reprostar\MpclWordpress\Plugin(__FILE__, $GLOBALS['wpdb']))->boot();
});
