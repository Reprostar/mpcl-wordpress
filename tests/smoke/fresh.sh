# Fresh install of this checkout: activation, sync, rendering, cron, settings, i18n.
. /smoke/common.sh

echo "== activation"
mkdir -p wp-content/plugins/mpcl && cp -r /plugin-src/. wp-content/plugins/mpcl/
$W plugin activate mpcl --quiet
expect "plugin is active" "$($W plugin list --name=mpcl --fields=status --format=csv)" "active"
expect "schema version stored" "$($W option get mpcl_db_version)" "^2$"
expect "machines table is utf8mb4" "$($W db query "SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_NAME='wp_mpcl_machines'" --skip-column-names)" "^utf8mb4_"
expect_empty "no legacy options table" "$($W db query "SHOW TABLES LIKE 'wp_mpcl_options'" --skip-column-names)"
expect "recurring sync event scheduled" "$($W cron event list --hook=mpcl_sync --fields=recurrence --format=csv)" "5 minutes"

echo "== sync"
$W option patch update mpcl-options api_key k >/dev/null
$W option patch update mpcl-options api_token t >/dev/null
api_mode ok
$W cron event run --due-now >/dev/null 2>&1
expect "cron imports all pages (120 machines over 50-item pages)" "$(rows)" "^120$"
expect "import used 3 API calls" "$(api_calls)" "^3$"
expect "last_listing_cache written" "$($W eval 'echo time() - get_option("mpcl-options")["last_listing_cache"];')" "^[0-9]$|^[1-5][0-9]$"
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
expect "single view renders BBCode" "$SINGLE" "<strong>Machine 120</strong>"
expect "single view lists the gallery" "$SINGLE" 'href="https://img.example/120.jpg"'
expect "missing machine message" "$(render "set_query_var('machine_id', 99999); echo do_shortcode('[mpcl]');")" "cannot be found"
BOX=$(render "echo do_shortcode('[mpcl id=120]');")
expect "box links to the catalogue page" "$BOX" "page_id=$PAGE&#038;machine_id=120"

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
expect_not "no image cache field" "$SETTINGS" "cache_images"

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

finish
