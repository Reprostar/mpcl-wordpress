# Upgrade from a 1.x install (LEGACY_PLUGIN) to this checkout on a utf8mb3
# database, replacing the files without reactivation as a WordPress update does.
. /smoke/common.sh

echo "== 1.x install"
mkdir -p wp-content/plugins/mpcl && cp -r /legacy-src/. wp-content/plugins/mpcl/
$W plugin activate mpcl --quiet
PAGE=$($W post create --post_type=page --post_status=publish --post_title=Catalog --post_content='[mpcl columns=3]' --porcelain)
$W option update mpcl-options "{\"api_key\":\"k\",\"api_token\":\"t\",\"catalog_page_id\":\"$PAGE\",\"box_full_width\":\"1\",\"entries_per_page\":\"\",\"cache_images\":\"0\",\"cache_autoupdate_enabled\":\"1\",\"cache_autoupdate_interval\":\"999999\",\"last_listing_cache\":\"0\",\"third_party\":\"keep me\"}" --format=json >/dev/null
$W db query 'ALTER TABLE wp_mpcl_machines ADD UNIQUE(`id`)'
$W eval 'global $wpdb; foreach ([15 => "Amiga 500", 16 => "Atari ST"] as $id => $name) { $wpdb->replace($wpdb->prefix . "mpcl_machines", ["id" => $id, "uid" => 1, "created" => 1, "modified" => 1, "slug" => "s$id", "is_visible" => 1, "description" => "[b]Bold[/b] Zażółć", "physical_state" => 4, "name" => $name, "manufacturer" => "Commodore", "type_name" => "Computer", "photos" => json_encode([["orig_uri" => "https://example.com/o.jpg", "thumbnails" => ["100" => "https://example.com/100.jpg", "300" => "https://example.com/300.jpg"]]]), "is_extension" => 0, "is_standalone" => 0, "synchronized" => 1]); }'
OPTION_BEFORE=$($W option get mpcl-options --format=json)
ROWS_BEFORE=$($W db query "SELECT id, name, description FROM wp_mpcl_machines ORDER BY id" --skip-column-names)
expect "1.x created a utf8mb3 table" "$($W db query "SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_NAME='wp_mpcl_machines'" --skip-column-names)" "^utf8mb3_"

echo "== replace files"
rm -rf wp-content/plugins/mpcl && mkdir -p wp-content/plugins/mpcl && cp -r /plugin-src/. wp-content/plugins/mpcl/
$W eval 'echo "";'
expect "plugin is still active" "$($W plugin list --name=mpcl --fields=status --format=csv)" "active"
expect "settings are unchanged" "$($W option get mpcl-options --format=json)" "^$(printf '%s' "$OPTION_BEFORE" | sed 's/[][\.*^$|?+(){}]/\\&/g')$"
expect "machine rows are unchanged" "$($W db query "SELECT id, name, description FROM wp_mpcl_machines ORDER BY id" --skip-column-names)" "^$(printf '%s' "$ROWS_BEFORE" | sed 's/[][\.*^$|?+(){}]/\\&/g')$"
expect "schema migrated to version 2" "$($W option get mpcl_db_version)" "^2$"
expect "table converted to utf8mb4" "$($W db query "SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_NAME='wp_mpcl_machines'" --skip-column-names)" "^utf8mb4_"
expect "only the primary key indexes id" "$($W db query "SHOW INDEX FROM wp_mpcl_machines" --skip-column-names | awk '{print $3}' | sort -u | tr '\n' ' ')" "^PRIMARY $"
expect_empty "legacy options table dropped" "$($W db query "SHOW TABLES LIKE 'wp_mpcl_options'" --skip-column-names)"
expect "sync event scheduled without reactivation" "$($W cron event list --hook=mpcl_sync --format=count)" "^1$"

echo "== after upgrade"
expect "migrated rows render" "$(render "set_query_var('machine_id', 15); echo do_shortcode('[mpcl]');")" "<h2>Amiga 500</h2>.*<strong>Bold</strong> Zażółć"
expect "box keeps full width and catalogue link" "$(render "echo do_shortcode('[mpcl id=16]');")" "mpcl-box full-width.*page_id=$PAGE&#038;machine_id=16"
api_mode ok
$W cron event run --due-now >/dev/null 2>&1
expect "first cron run syncs with the preserved credentials" "$(rows)" "^120$"
expect "emoji stored after conversion" "$($W eval 'global $wpdb; echo $wpdb->get_var("SELECT name FROM {$wpdb->prefix}mpcl_machines WHERE id = 120");')" "💾"
expect "unknown option keys survive a save" "$($W option get mpcl-options --format=json)" '"third_party":"keep me"'

finish
