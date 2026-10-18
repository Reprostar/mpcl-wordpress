# Fresh install of this checkout: activation, sync, rendering, cron, settings, i18n.
. /smoke/common.sh

echo "== activation"
mkdir -p wp-content/plugins/mpcl && cp -r /plugin-src/. wp-content/plugins/mpcl/
$W plugin activate mpcl --quiet
expect "plugin is active" "$($W plugin list --name=mpcl --fields=status --format=csv)" "active"
expect "schema version stored" "$($W option get mpcl_db_version)" "^3$"
expect "machines table is utf8mb4" "$($W db query "SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_NAME='wp_mpcl_machines'" --skip-column-names)" "^utf8mb4_"
expect_empty "no legacy options table" "$($W db query "SHOW TABLES LIKE 'wp_mpcl_options'" --skip-column-names)"
expect "recurring sync event scheduled" "$($W cron event list --hook=mpcl_sync --fields=recurrence --format=csv)" "5 minutes"

echo "== consent api"
expect "the plugin declares Consent API compliance" "$($W eval 'var_dump(apply_filters("wp_consent_api_registered_mpcl/mpcl.php", false));')" "bool\(true\)"
cp -r "$CONSENT_API_DIR" wp-content/plugins/ && $W plugin activate wp-consent-api hello --quiet
CONSENT_TEST='require_once ABSPATH . "wp-admin/includes/class-wp-site-health.php"; $tests = apply_filters("site_status_tests", ["direct" => [], "async" => []]); $result = call_user_func($tests["direct"]["wp-consent-api"]["test"]); echo $result["status"], " | ", wp_strip_all_tags(str_replace("<p>", " ", $result["actions"]));'
CONSENT=$($W --exec='define("WP_ADMIN", true);' eval "$CONSENT_TEST")
expect "Site Health (WP Consent API) flags a plugin without the declaration" "$CONSENT" "^recommended \|.* hello\.php"
expect_not "Site Health (WP Consent API) does not list mpcl" "$CONSENT" "mpcl"
$W plugin deactivate hello --quiet
expect "with only declaring plugins the Consent API check passes" "$($W --exec='define("WP_ADMIN", true);' eval "$CONSENT_TEST")" "^good \| *$"
$W plugin deactivate wp-consent-api --quiet

echo "== sync"
$W option patch update mpcl-options api_key k >/dev/null
$W option patch update mpcl-options api_token t >/dev/null
api_mode ok
$W cron event run --due-now >/dev/null 2>&1
expect "cron imports all pages (120 machines over 50-item pages)" "$(rows)" "^120$"
expect "import used 3 API calls" "$(api_calls)" "^3$"
expect "last_listing_cache written" "$($W eval 'echo time() - get_option("mpcl-options")["last_listing_cache"];')" "^[0-9]$|^[1-5][0-9]$"
expect "plain-text token is encrypted on load" "$($W option pluck mpcl-options api_token)" "^mpcl:v1:"
expect "emoji stored" "$($W eval 'global $wpdb; echo $wpdb->get_var("SELECT name FROM {$wpdb->prefix}mpcl_machines WHERE id = 120");')" "💾"
for mode in null empty error html; do
    api_mode "$mode"
    $W eval 'do_action("mpcl_sync", true);'
    expect "API '$mode' response keeps existing rows" "$(rows)" "^120$"
done
api_mode ok
$W eval 'do_action("mpcl_sync", false);'
expect "recurring check within the interval makes no API call" "$(api_calls)" "^0$"

echo "== rendering"
PAGE=$($W post create --post_type=page --post_status=publish --post_title=Catalog --post_content='[mpcl columns=3]' --porcelain)
$W option patch update mpcl-options catalog_page_id "$PAGE" >/dev/null
api_mode ok
LIST=$(render "echo do_shortcode('[mpcl columns=3]');")
expect "list renders entries" "$LIST" 'class="mpcl-entry" style="width: calc\(33\.333333333333% - 10px\);"'
expect "list links to machine view" "$LIST" 'href="\?machine_id=120"'
expect "list honours the default limit of 100" "$(printf '%s' "$LIST" | grep -o 'class="mpcl-entry"' | wc -l | tr -d ' ')" "^100$"
expect "list render makes no API call" "$(api_calls)" "^0$"
expect "[mpcl] defaults to 4 columns" "$(render "echo do_shortcode('[mpcl]');")" "calc\(25% - 10px\)"
expect "[mpcl columns=0] falls back to 1 column" "$(render "echo do_shortcode('[mpcl columns=0]');")" "calc\(100% - 10px\)"
$W option patch update mpcl-options entries_per_page 7 >/dev/null
expect "entries_per_page limits the list" "$(render "echo do_shortcode('[mpcl]');" | grep -o 'class="mpcl-entry"' | wc -l | tr -d ' ')" "^7$"
SINGLE=$(render "set_query_var('machine_id', 120); echo do_shortcode('[mpcl]');")
expect "single view shows the name" "$SINGLE" "<h2>Machine 120 💾</h2>"
expect "Markdown descriptions use the server-rendered HTML" "$SINGLE" "<strong>Markdown 120</strong>.*<li>item</li>"
expect_not "server HTML is filtered through kses" "$SINGLE" "<script"
ODD=$(render "set_query_var('machine_id', 119); echo do_shortcode('[mpcl]');")
expect "BBCode descriptions keep the plugin's rendering" "$ODD" "<strong>Machine 119</strong>"
expect_not "server HTML is ignored for BBCode" "$ODD" "SERVER-RENDERED"
api_mode legacy
$W eval 'do_action("mpcl_sync", true);'
expect "without the new fields (older server) descriptions fall back to BBCode" "$(render "set_query_var('machine_id', 120); echo do_shortcode('[mpcl]');")" "<strong>Machine 120</strong>"
api_mode ok
$W eval 'do_action("mpcl_sync", true);'
expect "single view lists the gallery" "$SINGLE" 'href="https://img.example/120.jpg"'
expect "missing machine message" "$(render "set_query_var('machine_id', 99999); echo do_shortcode('[mpcl]');")" "cannot be found"
BOX=$(render "echo do_shortcode('[mpcl id=120]');")
expect "box links to the catalogue page" "$BOX" "page_id=$PAGE&#038;machine_id=120"
expect_empty "visitors see nothing for a machine that is not synced" "$(render "echo trim(do_shortcode('[mpcl id=99999]'));" | tr -d ' ')"
expect "editors see a notice for a machine that is not synced" "$($W --user=1 eval "echo do_shortcode('[mpcl id=99999]');" | tr -s ' \n' ' ')" "mpcl-box-missing.*machine #99999 is not in the synced collection"

echo "== escaping"
$W eval 'global $wpdb; $wpdb->update($wpdb->prefix . "mpcl_machines", ["name" => "<i>Evil</i>", "description" => "<script>alert(1)</script> [url=javascript:alert(1)]j[/url]", "photos" => wp_json_encode([["orig_uri" => "javascript:alert(1)", "thumbnails" => ["100" => "\"><img src=x onerror=alert(1)>", "300" => "\"><img src=x onerror=alert(2)>"]]])], ["id" => 119]);'
XSS=$(render "set_query_var('machine_id', 119); echo do_shortcode('[mpcl]');")
expect_not "no script tag, event handler attribute or javascript: URL in output" "$XSS" "<script|[[:space:]\"']onerror=|<i>Evil|(href|src)=\"javascript:"
expect "description is shown as text" "$XSS" "&lt;script&gt;"

echo "== cron and cache clearing"
$W --exec='define("WP_ADMIN", true);' eval 'require_once ABSPATH . "wp-admin/includes/template.php"; wp_set_current_user(1); $_POST["reset"] = 1; do_action("admin_init"); update_option("mpcl-options", get_option("mpcl-options"));' >/dev/null 2>&1
expect "clear cache empties the table" "$(rows)" "^0$"
expect "clear cache schedules a forced sync" "$($W cron event list --hook=mpcl_sync --fields=args --format=csv)" "true"
$W cron event run --due-now >/dev/null 2>&1
expect "forced sync restores the machines" "$(rows)" "^120$"
api_mode error
$W option patch update mpcl-options cache_autoupdate_interval 0 >/dev/null
$W eval 'do_action("mpcl_sync", false);'
expect "failed sync keeps rows" "$(rows)" "^120$"
$W option patch update mpcl-options cache_autoupdate_interval 3600 >/dev/null
api_mode ok
$W eval 'do_action("mpcl_sync", false);'
expect "failed attempt delays the next try by the interval" "$(api_calls)" "^0$"
$W plugin deactivate mpcl --quiet
expect "deactivation unschedules the sync" "$($W cron event list --hook=mpcl_sync --format=count)" "^0$"
expect "deactivation keeps the machines" "$(rows)" "^120$"
expect "deactivation keeps the settings" "$($W option get mpcl-options --format=json)" '"api_key":"k"'
$W plugin activate mpcl --quiet

echo "== settings page"
SETTINGS=$($W --exec='define("WP_ADMIN", true);' eval-file /smoke/settings.php)
expect "settings page requires manage_options" "$SETTINGS" "capability: manage_options"
expect "settings are sanitised and merged" "$SETTINGS" 'sanitized: .*"api_key":"a\\"b\\\\c".*"catalog_page_id":12'
expect_not "unknown input keys are dropped" "$SETTINGS" '"evil"'
expect "stored values are escaped in the form" "$SETTINGS" 'value="k&quot;&gt;x"'
expect "a new token is stored encrypted" "$SETTINGS" "stored token: mpcl:v1:"
expect "an empty token field keeps the saved token" "$SETTINGS" "after empty submit: encrypted"
expect "the token field is an empty password input" "$SETTINGS" '<input type="password" id="mpcl-field-api-token" name="mpcl-options\[api_token\]" value=""'
expect_not "the token never appears in the form" "$SETTINGS" "secret-token"
expect "a saved token can be removed" "$SETTINGS" 'name="mpcl-options\[api_token_clear\]"'
ADMIN_SAVE='wp_set_current_user(1); do_action("admin_init"); update_option("mpcl-options", array_merge(get_option("mpcl-options"), json_decode(getenv("MPCL_INPUT"), true)));'
MPCL_INPUT='{"api_key":"k","api_token":"t"}' $W --exec='define("WP_ADMIN", true);' eval "$ADMIN_SAVE"
api_mode ok
$W eval 'do_action("mpcl_sync", true);'
expect "a token saved through the form authenticates the sync" "$(api_calls)" "^3$"
RENDER_ONLY='wp_set_current_user(1); require_once ABSPATH . "wp-admin/includes/template.php"; require_once ABSPATH . "wp-admin/includes/class-wp-screen.php"; require_once ABSPATH . "wp-admin/includes/screen.php"; set_current_screen("settings_page_mpcl-settings"); do_action("admin_menu"); do_action("admin_init"); do_action(get_plugin_page_hookname("mpcl-settings", "options-general.php"));'
SAVED_TOKEN=$($W option pluck mpcl-options api_token)
$W option patch update mpcl-options api_token "$($W eval 'echo (new Reprostar\MpclWordpress\Security\Secrets(new Reprostar\MpclWordpress\Security\KeyMaterial(), "another key"))->encrypt("t");')" >/dev/null
expect "a token encrypted with another key is reported" "$($W --exec='define("WP_ADMIN", true);' eval "$RENDER_ONLY")" "cannot be decrypted"
$W option patch update mpcl-options api_token "$SAVED_TOKEN" >/dev/null
expect "a salt-encrypted token still reads after MPCL_ENCRYPTION_KEY is added" "$($W --exec='define("MPCL_ENCRYPTION_KEY", "another key");' eval 'echo (new Reprostar\MpclWordpress\Settings\SettingsRepository(new Reprostar\MpclWordpress\Security\Secrets(new Reprostar\MpclWordpress\Security\KeyMaterial())))->load()->apiToken;')" "^t$"
MPCL_INPUT='{"api_token_clear":"1"}' $W --exec='define("WP_ADMIN", true);' eval "$ADMIN_SAVE"
expect_empty "removing the token empties it" "$($W option pluck mpcl-options api_token)"
MPCL_INPUT='{"api_token":"t"}' $W --exec='define("WP_ADMIN", true);' eval "$ADMIN_SAVE"
expect_not "no image cache field" "$SETTINGS" "cache_images"
expect "the catalogue page is a dropdown" "$SETTINGS" "<select name='mpcl-options\\[catalog_page_id\\]' id='mpcl-field-catalog-page-id'>"
expect "the catalogue page is selected and marked" "$SETTINGS" "value=\"$PAGE\" selected=\"selected\">Catalog \\(catalogue shortcode\\)"
expect "other pages are listed" "$SETTINGS" 'value="2">Sample Page<'
MPCL_INPUT='{"catalog_page_id":"2"}' $W --exec='define("WP_ADMIN", true);' eval "$ADMIN_SAVE"
expect "choosing another page stores its ID" "$($W option pluck mpcl-options catalog_page_id)" "^2$"
expect "boxes link to the newly chosen page" "$(render "echo do_shortcode('[mpcl id=120]');")" "page_id=2&#038;machine_id=120"
$W option patch update mpcl-options catalog_page_id 99999 >/dev/null
expect "a missing page stays selected" "$($W --exec='define("WP_ADMIN", true);' eval "$RENDER_ONLY")" 'value="99999" selected="selected">Missing page #99999<'
$W option patch update mpcl-options catalog_page_id "$PAGE" >/dev/null

echo "== connection test"
CT_ENQUEUE='do_action("admin_enqueue_scripts", get_plugin_page_hookname("mpcl-settings", "options-general.php")); echo wp_script_is("mpcl-admin-settings") ? "settings:yes" : "settings:no"; do_action("admin_enqueue_scripts", "index.php"); echo " ";'
expect "the test script is loaded on the settings page" "$($W --exec='define("WP_ADMIN", true);' eval "require_once ABSPATH . 'wp-admin/includes/plugin.php'; require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php'; require_once ABSPATH . 'wp-admin/includes/screen.php'; wp_set_current_user(1); set_current_screen('settings_page_mpcl-settings'); $CT_ENQUEUE")" "settings:yes"
expect "the settings page shows the button" "$SETTINGS" 'id="mpcl-field-connection-test"'
CT_CALL='wp_set_current_user((int) getenv("MPCL_USER")); $_POST = json_decode(getenv("MPCL_POST"), true); $_POST["nonce"] = "bad" === getenv("MPCL_NONCE") ? "bad" : wp_create_nonce("mpcl_test_connection"); $_REQUEST = $_POST; do_action("wp_ajax_mpcl_test_connection");'
ct() { MPCL_USER=${3:-1} MPCL_NONCE=${2:-good} MPCL_POST="$1" $W --exec='define("WP_ADMIN", true);' eval "$CT_CALL" 2>/dev/null; }
api_mode ok
expect "valid credentials report the collection size" "$(ct '{"api_key":"k","api_token":"t"}')" '"success":true.*has 120 machines'
expect "an empty token field uses the saved token" "$(ct '{"api_key":"k","api_token":""}')" '"success":true'
expect "the token is not echoed back" "$(ct '{"api_key":"k","api_token":"t"}' | grep -c '"t"')" "^0$"
expect "wrong credentials report the API error" "$(ct '{"api_key":"wrong","api_token":"t"}')" '"success":false.*API key not authorized'
expect "missing credentials are reported" "$(ct '{"api_key":"","api_token":""}')" '"success":false.*Enter the API key'
expect "a bad nonce is rejected" "$(ct '{"api_key":"k","api_token":"t"}' bad)" '"success":false.*not allowed'
SUBSCRIBER=$($W user create viewer viewer@example.com --role=subscriber --porcelain)
expect "users without manage_options are rejected" "$(ct '{"api_key":"k","api_token":"t"}' good "$SUBSCRIBER")" '"success":false.*not allowed'
api_mode html
expect "a broken API response is reported" "$(ct '{"api_key":"k","api_token":"t"}')" '"success":false.*Invalid response format'
api_mode ok

echo "== translations"
mkdir -p wp-content/languages && cp wp-content/plugins/mpcl/languages/mpcl-pl_PL.mo wp-content/languages/pl_PL.mo
$W option update WPLANG pl_PL >/dev/null
expect "Polish strings are used" "$(render "set_query_var('machine_id', 120); echo do_shortcode('[mpcl]');")" "Powrót do listy"
$W option update WPLANG '' >/dev/null

echo "== front end over HTTP"
(php -S 127.0.0.1:8080 -t /var/www/html >/dev/null 2>&1 &)
sleep 1
fetch() { php -r 'echo @file_get_contents($argv[1]);' "$1"; }
CATALOG=$(fetch "http://127.0.0.1:8080/?page_id=$PAGE")
expect "catalogue page loads versioned assets" "$CATALOG" "mpcl/css/front.css\?ver=[0-9]"
expect "catalogue page renders the list" "$CATALOG" 'class="mpcl-entry"'
expect_not "pages without the shortcode load no plugin assets" "$(fetch "http://127.0.0.1:8080/?page_id=2")" "plugins/mpcl/"
expect_not "no Smarty directories in the WordPress root" "$(ls -d /var/www/html/templates_c /var/www/html/cache 2>/dev/null)" "."

echo "== uninstall"
$W plugin deactivate mpcl --quiet
expect "deactivation keeps the data" "$(rows)" "^120$"
$W plugin uninstall mpcl --quiet
expect_empty "uninstall drops the plugin tables" "$($W db query "SHOW TABLES LIKE '%mpcl%'" --skip-column-names)"
expect_empty "uninstall deletes the plugin options" "$($W db query "SELECT option_name FROM wp_options WHERE option_name IN ('mpcl-options', 'mpcl_db_version', 'mpcl_sync_attempted_at')" --skip-column-names)"
expect "uninstall clears the sync event" "$($W cron event list --hook=mpcl_sync --format=count)" "^0$"
expect_empty "uninstall removes the plugin files" "$(ls -d wp-content/plugins/mpcl 2>/dev/null)"

finish
