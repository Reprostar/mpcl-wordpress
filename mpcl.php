<?php

/*
Plugin Name: MyPCList
Plugin URI: http://mypclist.net/wordpress
Description: Official MyPCList integration plugin. Show your machine collection on your own blog!
Version: 1.1
Author: pfcode
Author URI: http://mypclist.net
Text Domain: mpcl
Domain Path: /languages
License: MIT
*/

require_once __DIR__  . "/vendor/autoload.php";

$mpcl = new \Reprostar\MpclWordpress\MpclPlugin(plugin_dir_path(__FILE__), plugin_dir_url(__FILE__));