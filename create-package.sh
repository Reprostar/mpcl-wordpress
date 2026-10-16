#!/usr/bin/env bash
# Builds mpcl-wordpress-<version>.zip with a top-level mpcl/ directory.
set -euo pipefail

cd "$(dirname "$0")"

VERSION=$(sed -n 's/^Version: *//p' mpcl.php)
CONSTANT=$(sed -n "s/^const MPCL_VERSION = '\(.*\)';/\1/p" mpcl.php)
if [ -z "$VERSION" ] || [ "$VERSION" != "$CONSTANT" ]; then
    echo "Version header ($VERSION) and MPCL_VERSION ($CONSTANT) differ" >&2
    exit 1
fi

SOURCES=(src css img js languages licenses templates mpcl.php CHANGELOG.md LICENSE README.md)
BUILD_DIR=$(mktemp -d)
trap 'rm -rf "$BUILD_DIR"' EXIT

mkdir "$BUILD_DIR/mpcl"
cp -R "${SOURCES[@]}" "$BUILD_DIR/mpcl/"
find "$BUILD_DIR/mpcl" -name '.DS_Store' -delete

PACKAGE="$PWD/mpcl-wordpress-$VERSION.zip"
rm -f "$PACKAGE"
(cd "$BUILD_DIR" && zip -qr "$PACKAGE" mpcl)
echo "$PACKAGE"
