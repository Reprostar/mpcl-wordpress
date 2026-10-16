#!/usr/bin/env bash

TMP_DIR=mpcl
SOURCES=(src css img js languages licenses templates mpcl.php LICENSE README.md)

rm -rf $TMP_DIR/
mkdir $TMP_DIR/

# Copy sources
for item in ${SOURCES[*]}
do
    cp -rv $item $TMP_DIR/
done

# Remove all git repo data
for item in `find $TMP_DIR | grep -i "/\.git/"`
do
    rm -rf $item
done

# Create a package
zip mpcl-wordpress-1.2.2.zip `find $TMP_DIR`