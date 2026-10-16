# Shared setup for the smoke scenarios; sourced inside the wordpress:cli container.
set -u

W="wp --allow-root --path=/var/www/html"
FAILURES=0

pass() { echo "  ok    $1"; }
fail() { echo "  FAIL  $1"; echo "        got: $(printf '%s' "$2" | head -c 400)"; FAILURES=$((FAILURES + 1)); }

# expect <description> <actual> <extended regex>
expect() { if printf '%s' "$2" | grep -Eq -- "$3"; then pass "$1"; else fail "$1" "$2"; fi; }
# expect_empty <description> <actual>
expect_empty() { if [ -z "$2" ]; then pass "$1"; else fail "$1" "$2"; fi; }
# expect_not <description> <actual> <extended regex>
expect_not() { if printf '%s' "$2" | grep -Eq -- "$3"; then fail "$1" "$2"; else pass "$1"; fi; }

finish() {
    local log
    log=$(sed 's/^\[[^]]*\] //' /var/www/html/wp-content/debug.log 2>/dev/null | grep -iv 'mpcl: machine import failed\|mpcl: could not save' || true)
    expect_not "debug.log has no PHP errors, warnings or deprecations" "$log" "PHP (Fatal|Warning|Notice|Deprecated)|_load_textdomain_just_in_time"
    if [ 0 -eq "$FAILURES" ]; then echo "PASSED"; exit 0; fi
    echo "FAILED: $FAILURES check(s)"; exit 1
}

php -v | head -1

mkdir -p /var/www/html && cd /var/www/html
cp -r /wp-src/. /var/www/html/
$W config create --dbname=wp --dbuser=root --dbpass=root --dbhost="$DB_HOST" --skip-check --quiet --extra-php <<'PHP'
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('MPCL_API_URL', 'http://127.0.0.1:8099/');
PHP
(php -S 127.0.0.1:8099 /smoke/fake-api.php >/dev/null 2>&1 &)
$W core install --url=http://127.0.0.1:8080 --title=t --admin_user=a --admin_password=a --admin_email=a@example.com --skip-email --quiet

rows() { $W db query "SELECT COUNT(*) FROM wp_mpcl_machines" --skip-column-names; }
api_calls() { [ -f /tmp/fake-api-log ] && wc -l < /tmp/fake-api-log | tr -d ' ' || echo 0; }
api_mode() { echo "$1" > /tmp/fake-api-mode; rm -f /tmp/fake-api-log; }
render() { $W eval "$1" | tr -s ' \n' ' '; }
