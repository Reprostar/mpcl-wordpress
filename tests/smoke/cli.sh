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
$W option patch update mpcl-options api_key k >/dev/null
$W option patch update mpcl-options api_token t >/dev/null
expect "a token saved by 1.x is encrypted with the salt on the first load" "$(status_of)" '"key_source":"salt","token":"ok"'
expect "generate-key prints a 64-character hex key" "$($W mpcl secrets generate-key)" "^[0-9a-f]{64}$"
expect_empty "generate-key without --write leaves wp-config.php alone" "$($W config has MPCL_ENCRYPTION_KEY 2>&1 && echo defined)"

OUT=$($W mpcl secrets generate-key --write 2>&1)
expect "generate-key --write succeeds" "$OUT" "Success:"
KEY=$($W config get MPCL_ENCRYPTION_KEY)
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

$W config set MPCL_ENCRYPTION_KEY "manual key" --type=constant --quiet
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

finish
