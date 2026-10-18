#!/usr/bin/env bash
# Smoke test of the plugin on a throwaway WordPress in Docker.
#
# Usage: tests/smoke/run.sh <php version> [wordpress version] [scenario]
#   scenario: fresh (default), cli (the wp mpcl commands) or upgrade; upgrade needs LEGACY_PLUGIN=<path to an
#   unpacked 1.x plugin directory> and installs it first, then replaces it with
#   this checkout the way a WordPress update does.
set -euo pipefail

PHP_VERSION=${1:?php version, e.g. 7.4}
WP_VERSION=${2:-6.9}
SCENARIO=${3:-fresh}

ROOT=$(cd "$(dirname "$0")/../.." && pwd)
CACHE="$ROOT/tests/smoke/.cache"
NAME="mpcl-smoke-$$"
mkdir -p "$CACHE"

WP_DIR="$CACHE/wordpress-$WP_VERSION"
if [ ! -f "$WP_DIR/wordpress/wp-includes/version.php" ]; then
    TARBALL="$CACHE/wordpress-$WP_VERSION.tar.gz"
    curl -sSfL -o "$TARBALL" "https://wordpress.org/wordpress-$WP_VERSION.tar.gz"
    echo "$(curl -sSfL "https://wordpress.org/wordpress-$WP_VERSION.tar.gz.sha1")  $TARBALL" | shasum -c --quiet
    mkdir -p "$WP_DIR" && tar -xzf "$TARBALL" -C "$WP_DIR"
fi

CONSENT_API_VERSION=2.1.0
if [ ! -f "$CACHE/wp-consent-api-$CONSENT_API_VERSION/wp-consent-api/wp-consent-api.php" ]; then
    curl -sSfL -o "$CACHE/wp-consent-api-$CONSENT_API_VERSION.zip" "https://downloads.wordpress.org/plugin/wp-consent-api.$CONSENT_API_VERSION.zip"
    mkdir -p "$CACHE/wp-consent-api-$CONSENT_API_VERSION" && unzip -qo "$CACHE/wp-consent-api-$CONSENT_API_VERSION.zip" -d "$CACHE/wp-consent-api-$CONSENT_API_VERSION"
fi

PLUGIN_DIR="$CACHE/plugin-$$/mpcl"
mkdir -p "$PLUGIN_DIR"
cp -R "$ROOT"/{src,css,img,js,languages,licenses,templates,mpcl.php,uninstall.php} "$PLUGIN_DIR/"

LEGACY_MOUNT=()
DB_ARGS=()
if [ "upgrade" = "$SCENARIO" ]; then
    : "${LEGACY_PLUGIN:?LEGACY_PLUGIN must point to an unpacked 1.x plugin directory}"
    LEGACY_MOUNT=(-v "$LEGACY_PLUGIN:/legacy-src:ro")
    DB_ARGS=(--character-set-server=utf8mb3 --collation-server=utf8mb3_general_ci)
fi

cleanup() {
    docker rm -f "$NAME-cli" "$NAME-db" >/dev/null 2>&1 || true
    docker network rm "$NAME" >/dev/null 2>&1 || true
    rm -rf "$CACHE/plugin-$$"
}
trap cleanup EXIT

docker network create "$NAME" >/dev/null
docker run -d --name "$NAME-db" --network "$NAME" \
    -e MARIADB_ROOT_PASSWORD=root -e MARIADB_DATABASE=wp mariadb:11.8 ${DB_ARGS[@]+"${DB_ARGS[@]}"} >/dev/null
for _ in $(seq 1 60); do
    docker exec "$NAME-db" mariadb -uroot -proot -e 'select 1' >/dev/null 2>&1 && break
    sleep 1
done

docker run --rm --name "$NAME-cli" --network "$NAME" -u root \
    -v "$PLUGIN_DIR:/plugin-src:ro" \
    -v "$WP_DIR/wordpress:/wp-src:ro" \
    -v "$ROOT/tests/smoke:/smoke:ro" \
    ${LEGACY_MOUNT[@]+"${LEGACY_MOUNT[@]}"} \
    -e DB_HOST="$NAME-db" \
    -e CONSENT_API_DIR="/smoke/.cache/wp-consent-api-$CONSENT_API_VERSION/wp-consent-api" \
    "wordpress:cli-php$PHP_VERSION" bash "/smoke/$SCENARIO.sh"
