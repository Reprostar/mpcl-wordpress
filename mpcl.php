<?php

/*
Plugin Name: MyPCList for WordPress
Plugin URI: https://github.com/reprostar/mpcl-wordpress
Description: Official MyPCList integration plugin. Show your machine collection on your own blog!
Version: 2.1.0
Requires at least: 6.9
Requires PHP: 7.4
Author: Reprostar
Author URI: https://mypclist.net
Text Domain: mpcl
Domain Path: /languages
License: MIT
Update URI: false
*/

defined('ABSPATH') || exit;

const MPCL_VERSION = '2.1.0';

require_once __DIR__ . '/src/autoload.php';

register_activation_hook(__FILE__, static function (): void {
    \Reprostar\MpclWordpress\Plugin::create(__FILE__)->activate();
});

register_deactivation_hook(__FILE__, static function (): void {
    \Reprostar\MpclWordpress\Plugin::create(__FILE__)->deactivate();
});

add_action('plugins_loaded', static function (): void {
    \Reprostar\MpclWordpress\Plugin::create(__FILE__)->boot();
});
