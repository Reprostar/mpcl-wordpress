# WP-CLI commands (`wp mpcl …`) on a fresh install of this checkout.
. /smoke/common.sh

mkdir -p wp-content/plugins/mpcl && cp -r /plugin-src/. wp-content/plugins/mpcl/
$W plugin activate mpcl --quiet
api_mode ok
sync() { $W eval 'do_action("mpcl_sync", true);'; }
status_of() { $W mpcl secrets status --format=json; }
# out <command…>: stdout and stderr on one line, followed by exit=<status>
out() { local o rc; o=$("$@" 2>&1); rc=$?; printf '%s exit=%s' "$o" "$rc" | tr '\n' ' '; }

echo "== secrets"
expect "the command group is registered" "$($W help mpcl)" "secrets"
expect "command help is rendered" "$($W help mpcl status)" "Shows the connection, synchronisation and catalogue state"
expect_not "helper methods are not exposed as commands" "$($W mpcl secrets 2>&1; $W mpcl config 2>&1)" "wp mpcl (secrets tokenState|config values)"
$W option patch update mpcl-options api_key k >/dev/null
$W option patch update mpcl-options api_token t >/dev/null
expect "a token saved by 1.x is reported as plain text" "$(status_of)" '"key_source":"salt","token":"plaintext"'
sync
expect "after a load the token is encrypted with the salt" "$(status_of)" '"key_source":"salt","token":"ok"'
expect "generate-key prints a 64-character hex key" "$($W mpcl secrets generate-key)" "^[0-9a-f]{64}$"
expect_empty "generate-key without --write leaves wp-config.php alone" "$($W config has MPCL_ENCRYPTION_KEY 2>&1 && echo defined)"

sed -i "/That's all, stop editing/d" wp-config.php
expect_empty "wp-config.php has no standard anchor (as in many Docker images)" "$(grep "That's all, stop editing" wp-config.php)"
OUT=$($W mpcl secrets generate-key --write 2>&1)
expect "generate-key --write succeeds" "$OUT" "Success:"
KEY=$($W config get MPCL_ENCRYPTION_KEY)
expect "the constant is placed before wp-settings.php is loaded" "$(grep -n "MPCL_ENCRYPTION_KEY\|wp-settings.php" wp-config.php | cut -d: -f1 | tr '\n' ' ')" "^([0-9]+) ([0-9]+) $"
expect "the key is written into wp-config.php" "$KEY" "^[0-9a-f]{64}$"
expect_not "generate-key --write does not print the key" "$OUT" "$KEY"
expect "the token is encrypted with the new constant" "$(status_of)" '"key_source":"constant","token":"ok"'
api_mode ok; sync
expect "the re-encrypted token authenticates the sync" "$(api_calls)" "^3$"
expect "generate-key --write refuses a second key" "$(out $W mpcl secrets generate-key --write)" "already defined.*exit=1"

OUT=$(out $W mpcl secrets rekey --from=constant --to=salt)
expect "rekey to the salt succeeds and says the constant can go" "$OUT" "Success:.*remove MPCL_ENCRYPTION_KEY"
expect "with the constant still defined the salt token reads through the fallback" "$(status_of)" '"token":"ok-salt-fallback"'
api_mode ok; sync
expect "the fallback token authenticates the sync" "$(api_calls)" "^3$"
$W config delete MPCL_ENCRYPTION_KEY --quiet
expect "after removing the constant the salt is used" "$(status_of)" '"key_source":"salt","token":"ok"'

$W config set MPCL_ENCRYPTION_KEY "manual key" --type=constant --anchor="require_once ABSPATH . 'wp-settings.php';" --quiet
expect "adding the constant by hand keeps the token readable" "$(status_of)" '"key_source":"constant","token":"ok-salt-fallback"'
expect "rekey from the salt to the constant" "$($W mpcl secrets rekey --from=salt --to=constant 2>&1)" "Success:"
expect "the token now uses the constant" "$(status_of)" '"token":"ok"'
expect "rekey refuses the same source twice" "$(out $W mpcl secrets rekey --from=constant --to=constant)" "same key.*exit=1"
$W mpcl secrets rekey --from=constant --to=salt >/dev/null 2>&1
$W config delete MPCL_ENCRYPTION_KEY --quiet
expect "rekey to a missing constant fails" "$(out $W mpcl secrets rekey --from=salt --to=constant)" "not defined.*exit=1"

OLD_SALT=$($W eval 'echo wp_salt("auth");')
$W config shuffle-salts --quiet
expect "after shuffling the salts the token is unreadable" "$(status_of)" '"token":"unreadable"'
STORED=$($W option pluck mpcl-options api_token)
$W eval '$s = get_option("mpcl-options"); $s["entries_per_page"] = 5; update_option("mpcl-options", $s);'
$W eval '(new Reprostar\MpclWordpress\Settings\SettingsRepository(new Reprostar\MpclWordpress\Security\Secrets(new Reprostar\MpclWordpress\Security\KeyMaterial())))->save((new Reprostar\MpclWordpress\Settings\SettingsRepository(new Reprostar\MpclWordpress\Security\Secrets(new Reprostar\MpclWordpress\Security\KeyMaterial())))->load());'
expect "saving settings keeps an unreadable token for recovery" "$([ "$STORED" = "$($W option pluck mpcl-options api_token)" ] && echo same || echo changed)" "^same$"
expect "rekey with a wrong old salt fails" "$(printf %s wrong | out $W mpcl secrets rekey --from-stdin --to=salt)" "cannot be decrypted.*exit=1"
expect "rekey with the old salt from STDIN recovers the token" "$(printf %s "$OLD_SALT" | $W mpcl secrets rekey --from-stdin --to=salt 2>&1)" "Success:"
expect "the recovered token reads" "$(status_of)" '"key_source":"salt","token":"ok"'
api_mode ok; sync
expect "the recovered token authenticates the sync" "$(api_calls)" "^3$"

echo "== config"
expect "config set stores a number" "$($W mpcl config set entries_per_page 10 2>&1)" "Success: entries_per_page is now 10"
expect "config get prints it" "$($W mpcl config get entries_per_page)" "^10$"
expect "config set rejects a non-number" "$(out $W mpcl config set entries_per_page abc)" "whole number.*exit=1"
expect "config get leaves it unchanged" "$($W mpcl config get entries_per_page)" "^10$"
$W mpcl config set cache_autoupdate_enabled off >/dev/null
expect "config set parses booleans" "$($W mpcl config get cache_autoupdate_enabled)" "^0$"
$W mpcl config set cache_autoupdate_enabled yes >/dev/null
expect "config set rejects a bad boolean" "$(out $W mpcl config set box_full_width maybe)" "takes 1/0.*exit=1"
expect "config set refuses the token" "$(out $W mpcl config set api_token x)" "wp mpcl connect.*exit=1"
expect "config set refuses read-only keys" "$(out $W mpcl config set last_listing_cache 5)" "not a setting that can be changed.*exit=1"
expect "config get refuses unknown keys" "$(out $W mpcl config get nope)" "Unknown setting.*exit=1"
expect "config set refuses a missing page" "$(out $W mpcl config set catalog_page_id 99999)" "no published, private or draft page.*exit=1"
CFG_PAGE=$($W post create --post_type=page --post_status=publish --post_title=Config --porcelain)
expect "config set accepts an existing page" "$(out $W mpcl config set catalog_page_id "$CFG_PAGE")" "Success.*exit=0"
expect "config set accepts 0 to unset the page" "$(out $W mpcl config set catalog_page_id 0)" "Success.*exit=0"
LIST=$($W mpcl config list --format=json)
expect "config list shows the token state" "$LIST" '"api_token":"set"'
expect_not "config list never shows the token" "$LIST" "mpcl:v1:"
$W cron event delete mpcl_sync --quiet >/dev/null 2>&1 || true
$W mpcl config set api_key k2 >/dev/null
expect "changing the API key schedules a sync" "$($W cron event list --hook=mpcl_sync --fields=args --format=csv)" "true"
$W mpcl config set api_key k >/dev/null
expect "the token survives other settings changes" "$(status_of)" '"token":"ok"'

echo "== test-connection"
api_mode ok
expect "valid credentials report the collection size" "$(out $W mpcl test-connection)" "Success: Connection works. Your collection has 120 machines. exit=0"
api_mode error
expect "an API error exits non-zero" "$(out $W mpcl test-connection)" "Connection failed: API error: API key not authorized exit=1"
api_mode html
expect "a broken response exits non-zero" "$(out $W mpcl test-connection)" "Invalid response format exit=1"
api_mode ok
SAVED_TOKEN=$($W option pluck mpcl-options api_token)
$W option patch update mpcl-options api_token "$($W eval 'echo (new Reprostar\MpclWordpress\Security\Secrets(new Reprostar\MpclWordpress\Security\KeyMaterial(), "another key"))->encrypt("t");')" >/dev/null
expect "an unreadable token is reported" "$(out $W mpcl test-connection)" "cannot be decrypted.*exit=1"
$W option patch update mpcl-options api_token "$SAVED_TOKEN" >/dev/null
$W option patch update mpcl-options api_key "" >/dev/null
expect "missing credentials are reported" "$(out $W mpcl test-connection)" "Not configured.*exit=1"
$W option patch update mpcl-options api_key k >/dev/null

echo "== sync"
$W db query "DELETE FROM wp_mpcl_machines"
$W mpcl config set cache_autoupdate_enabled off >/dev/null
api_mode ok
expect "sync imports the collection regardless of the auto-update setting" "$(out $W mpcl sync)" "Success: Synchronised 120 machines in [0-9.,]+ s\. exit=0"
expect "sync stores the machines" "$(rows)" "^120$"
expect "sync records the success" "$($W eval 'echo time() - get_option("mpcl-options")["last_listing_cache"];')" "^[0-9]$|^[1-5][0-9]$"
$W mpcl config set cache_autoupdate_enabled on >/dev/null
api_mode error
expect "a failing API exits non-zero with the reason" "$(out $W mpcl sync)" "Synchronisation failed: API error: API key not authorized exit=1"
expect "a failing API keeps the machines" "$(rows)" "^120$"
api_mode ok
expect "sync --reset empties first and imports again" "$(out $W mpcl sync --reset)" "emptied.*Success: Synchronised 120 machines.*exit=0"
expect "sync --reset leaves the full collection" "$(rows)" "^120$"
$W option patch update mpcl-options api_key "" >/dev/null
expect "sync without credentials is reported" "$(out $W mpcl sync)" "Not configured.*exit=1"
$W option patch update mpcl-options api_key k >/dev/null

echo "== connect"
$W option update mpcl-options '{}' --format=json >/dev/null
$W db query "DELETE FROM wp_mpcl_machines"
api_mode ok
expect "connect without a token on STDIN fails" "$(printf '' | out $W mpcl connect --key=k)" "No API token.*exit=1"
expect "connect with a wrong token fails and saves nothing" "$(printf %s wrong | out $W mpcl connect --key=k)" "Connection failed: API error: API key not authorized\. Nothing was saved.*exit=1"
expect_empty "nothing was saved" "$($W mpcl config get api_key)"
OUT=$(printf %s t | out $W mpcl connect --key=k --sync)
expect "connect --sync saves and synchronises" "$OUT" "saved.*Synchronised 120 machines.*exit=0"
expect_not "connect never prints the token" "$OUT" "mpcl:v1:"
expect "the machines are imported" "$(rows)" "^120$"
expect "the token is stored encrypted" "$($W option pluck mpcl-options api_token)" "^mpcl:v1:"
expect "the stored token works" "$(out $W mpcl test-connection)" "exit=0"
$W cron event delete mpcl_sync --quiet >/dev/null 2>&1 || true
expect "connect without --sync schedules a synchronisation" "$(printf %s t | out $W mpcl connect --key=k)" "scheduled.*exit=0"
expect "the forced sync event exists" "$($W cron event list --hook=mpcl_sync --fields=args --format=csv)" "true"
expect "connect --skip-check saves rejected credentials" "$(printf %s wrong | out $W mpcl connect --key=k --skip-check)" "saved.*exit=0"
printf %s t | $W mpcl connect --key=k >/dev/null 2>&1

echo "== status"
ST=$($W mpcl status --format=json)
expect "status reports the version and schema" "$ST" '"plugin_version":"[0-9.]+","schema_version":3,"schema_current":true'
expect "status reports the credentials" "$ST" '"api_key":"set","api_token":"set"'
expect "status counts the machines" "$ST" '"machines":120'
expect "status shows the last sync" "$ST" '"last_sync":"20[0-9-]+T[0-9:]+Z \(.* ago\)"'
expect "status shows the next scheduled check" "$ST" '"next_check":"20[0-9-]+T[0-9:]+Z \(.* (ago|from now)\)"'
expect "status reports a missing catalogue page as not set" "$ST" '"catalog_page_id":0,"catalog_page":"not set"'
expect_not "status never shows the token" "$ST" "mpcl:v1:"
CAT=$($W post create --post_type=page --post_status=publish --post_title=Katalog --porcelain)
$W mpcl config set catalog_page_id "$CAT" >/dev/null
expect "status shows the catalogue page" "$($W mpcl status --format=json)" "\"catalog_page_id\":$CAT,\"catalog_page\":\"Katalog\",\"catalog_url\":\"http"
$W post delete "$CAT" --force >/dev/null
expect "status reports a deleted catalogue page as missing" "$($W mpcl status --format=json)" '"catalog_page":"missing"'
$W mpcl config set catalog_page_id 0 >/dev/null 2>&1 || $W option patch update mpcl-options catalog_page_id 0 >/dev/null
api_mode error; $W mpcl sync >/dev/null 2>&1; api_mode ok
expect "status flags a failed last attempt" "$($W mpcl status --format=json)" '"last_attempt_failed":true'
expect "status prints a table by default" "$($W mpcl status)" "machines[[:space:]]+120"
$W option update mpcl-options '{}' --format=json >/dev/null
expect "status exits 0 when nothing is configured" "$(out $W mpcl status --format=json)" '"api_key":"empty","api_token":"empty".*exit=0'
printf %s t | $W mpcl connect --key=k --sync >/dev/null 2>&1

echo "== machines"
expect "machines list counts the collection" "$($W mpcl machines list --format=count)" "^120$"
expect "machines list sorts and limits" "$($W mpcl machines list --orderby=id --order=asc --limit=2 --fields=id,name --format=csv)" "^id,name"
expect "machines list returns the first ids in ascending order" "$($W mpcl machines list --orderby=id --order=asc --limit=2 --fields=id --format=csv | tail -n +2 | tr '\n' ' ')" "^1 2 $"
expect "machines list --offset skips" "$($W mpcl machines list --order=desc --limit=1 --offset=1 --fields=id --format=csv | tail -1)" "^119$"
expect "machines list rejects unknown fields" "$(out $W mpcl machines list --fields=id,nope)" "Unknown fields: nope.*exit=1"
expect "machines list rejects a bad limit" "$(out $W mpcl machines list --limit=x)" "whole number.*exit=1"
GET=$($W mpcl machines get 120 --format=json)
expect "machines get shows the machine" "$GET" '"id":120,"name":"Machine 120 '
expect "machines get lists photo URLs" "$GET" '"photo_urls":"https:\\/\\/img.example\\/120.jpg"'
expect "machines get --field prints one value" "$($W mpcl machines get 120 --field=description_format)" "^markdown$"
CAT=$($W post create --post_type=page --post_status=publish --post_title=Machines --porcelain)
$W mpcl config set catalog_page_id "$CAT" >/dev/null
expect "machines get links to the catalogue page" "$($W mpcl machines get 120 --field=catalog_url)" "page_id=$CAT&machine_id=120$"
$W mpcl config set catalog_page_id 0 >/dev/null
expect_empty "machines get without a catalogue page has no URL" "$($W mpcl machines get 120 --field=catalog_url)"
expect "machines get for an unknown id exits non-zero" "$(out $W mpcl machines get 999999)" "not in the synchronised collection.*exit=1"

echo "== schedule"
expect "schedule disable turns auto-update off" "$(out $W mpcl schedule disable)" "Success.*off.*exit=0"
expect "the setting is off" "$($W mpcl config get cache_autoupdate_enabled)" "^0$"
$W option update mpcl_sync_attempted_at 0 >/dev/null
$W option patch update mpcl-options last_listing_cache 0 >/dev/null
api_mode ok
$W eval 'do_action("mpcl_sync", false);'
expect "a scheduled check does not sync while disabled" "$(api_calls)" "^0$"
expect "schedule enable --interval=1h" "$(out $W mpcl schedule enable --interval=1h)" "every 3600 seconds.*exit=0"
expect "the interval is stored" "$($W mpcl config get cache_autoupdate_interval)" "^3600$"
expect "the setting is on" "$($W mpcl config get cache_autoupdate_enabled)" "^1$"
api_mode ok
$W eval 'do_action("mpcl_sync", false);'
expect "a scheduled check syncs once enabled and due" "$(api_calls)" "^3$"
expect "schedule enable rejects less than five minutes" "$(out $W mpcl schedule enable --interval=60)" "at least 5 minutes.*exit=1"
expect "schedule enable rejects a bad interval" "$(out $W mpcl schedule enable --interval=soon)" "takes seconds.*exit=1"
expect "schedule enable accepts minutes" "$(out $W mpcl schedule enable --interval=30m)" "every 1800 seconds"
expect "schedule status reports the state" "$($W mpcl schedule status --format=json)" '"auto_update":true,"auto_update_interval":1800,"next_check":"20'
$W mpcl schedule enable --interval=1h >/dev/null

echo "== catalog"
$W mpcl config set catalog_page_id 0 >/dev/null
NEW=$($W mpcl catalog create-page --title=Kolekcja --columns=3 --porcelain)
expect "create-page --porcelain prints the ID" "$NEW" "^[0-9]+$"
expect "the new page is the catalogue page" "$($W mpcl config get catalog_page_id)" "^$NEW$"
expect "the page holds the shortcode in a shortcode block" "$($W post get "$NEW" --field=post_content)" "wp:shortcode"
expect "the shortcode has the columns" "$($W post get "$NEW" --field=post_content)" "\[mpcl-catalog columns=3\]"
expect "the page is published" "$($W post get "$NEW" --field=post_status)" "^publish$"
expect "create-page refuses to replace a set page" "$(out $W mpcl catalog create-page)" "already set to page $NEW.*--force.*exit=1"
expect "create-page rejects bad columns" "$(out $W mpcl catalog create-page --force --columns=0)" "columns takes.*exit=1"
NEWER=$($W mpcl catalog create-page --force --status=draft --slug=maszyny --porcelain)
expect "--force creates another page" "$($W mpcl config get catalog_page_id)" "^$NEWER$"
expect "the old page is left alone" "$($W post get "$NEW" --field=post_status)" "^publish$"
expect "--slug and --status are applied" "$($W post get "$NEWER" --field=post_name),$($W post get "$NEWER" --field=post_status)" "^maszyny,draft$"
$W mpcl config set catalog_page_id "$NEW" >/dev/null
(php -S 127.0.0.1:8080 -t /var/www/html >/dev/null 2>&1 &)
sleep 1
PAGE_HTML=$(php -r 'echo @file_get_contents($argv[1]);' "http://127.0.0.1:8080/?page_id=$NEW")
expect "the created page renders the catalogue" "$PAGE_HTML" 'class="mpcl-entry" style="width: calc\(33\.333333333333% - 10px\);"'
expect "the created page links to the single view" "$PAGE_HTML" 'machine_id=120'
expect "the single view renders on the created page" "$(php -r 'echo @file_get_contents($argv[1]);' "http://127.0.0.1:8080/?page_id=$NEW&machine_id=120")" "<h2>Machine 120"
PLAIN=$($W post create --post_type=page --post_status=publish --post_title=Plain --porcelain)
expect "set-page warns about a page without the shortcode" "$(out $W mpcl catalog set-page "$PLAIN")" "Warning: The page has no catalogue shortcode.*Success.*exit=0"
expect "set-page rejects a missing page" "$(out $W mpcl catalog set-page 99999)" "no published, private or draft page.*exit=1"
expect "set-page without a warning for a catalogue page" "$(out $W mpcl catalog set-page "$NEW")" "^Success: Page $NEW is now the catalogue page\. exit=0"

echo "== disconnect"
$W mpcl config set entries_per_page 7 >/dev/null
$W eval 'wp_schedule_single_event(time() + 600, "mpcl_sync", [true]);'
expect "a forced sync is pending before disconnecting" "$($W cron event list --hook=mpcl_sync --fields=args --format=csv)" "true"
expect "disconnect asks for confirmation" "$(printf 'n\n' | out $W mpcl disconnect)" "Remove the API credentials\?.*exit=0"
expect "declining keeps the credentials" "$($W mpcl config get api_token)" "^set$"
expect "disconnect --yes removes the credentials" "$(out $W mpcl disconnect --yes)" "Removed the API key and token.*Success: Disconnected.*exit=0"
expect "the token is empty" "$($W mpcl config get api_token)" "^empty$"
expect_empty "the key is empty" "$($W mpcl config get api_key)"
expect "the machines stay" "$(rows)" "^120$"
expect "other settings stay" "$($W mpcl config get entries_per_page)" "^7$"
expect_not "the pending forced sync is removed" "$($W cron event list --hook=mpcl_sync --fields=args --format=csv)" "true"
expect "sync then fails as not configured" "$(out $W mpcl sync)" "Not configured.*exit=1"
printf %s t | $W mpcl connect --key=k --sync >/dev/null 2>&1
expect "disconnect --purge also removes the machines" "$(out $W mpcl disconnect --purge --yes)" "Removed 120 synchronised machines.*exit=0"
expect "the table is empty" "$(rows)" "^0$"
expect "the sync times are reset" "$($W mpcl config get last_listing_cache),$($W option get mpcl_sync_attempted_at 2>/dev/null || echo none)" "^0,none$"
expect "--purge keeps the other settings" "$($W mpcl config get entries_per_page)" "^7$"

finish
