<?php
// Renders the settings page through the real admin hooks; run with WP_ADMIN defined.
require_once ABSPATH . 'wp-admin/includes/template.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';

wp_set_current_user(1);
set_current_screen('settings_page_mpcl-settings');
do_action('admin_menu');
do_action('admin_init');

$token = static function (): string {
    $option = get_option('mpcl-options');

    return is_array($option) ? (string) $option['api_token'] : '';
};

echo 'capability: ' . $GLOBALS['submenu']['options-general.php'][0][1] . "\n";
echo 'sanitized: ' . wp_json_encode(sanitize_option('mpcl-options', [
    'api_key' => ' a"b\\c ',
    'catalog_page_id' => '12abc',
    'box_full_width' => '1',
    'evil' => 'x',
])) . "\n";

update_option('mpcl-options', array_merge((array) get_option('mpcl-options'), ['api_key' => 'k"><b>x', 'api_token' => 'secret-token']));
echo 'stored token: ' . $token() . "\n";
update_option('mpcl-options', array_merge((array) get_option('mpcl-options'), ['api_token' => '']));
echo 'after empty submit: ' . (0 === strpos($token(), 'mpcl:v1:') ? 'encrypted' : 'lost') . "\n";

ob_start();
do_action(get_plugin_page_hookname('mpcl-settings', 'options-general.php'));
echo ob_get_clean();
