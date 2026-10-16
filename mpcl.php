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

require_once __DIR__ . '/src/autoload.php';

add_action('plugins_loaded', static function (): void {
    (new \Reprostar\MpclWordpress\Plugin(__FILE__, $GLOBALS['wpdb']))->boot();
});
