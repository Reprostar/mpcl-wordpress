<?php

defined('WP_UNINSTALL_PLUGIN') || exit;

require_once __DIR__ . '/src/autoload.php';

global $wpdb;

if ($wpdb instanceof \wpdb) {
    (new \Reprostar\MpclWordpress\Uninstaller($wpdb))->run();
}
