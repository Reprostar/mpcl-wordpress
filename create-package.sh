#!/usr/bin/env bash

TMP_DIR=mpcl
SOURCES=(classes img js languages templates views vendor mpcl.php composer.json LICENSE README.md)

rm -rf $TMP_DIR/
mkdir $TMP_DIR/

# Copy sources
for item in ${SOURCES[*]}
do
    cp -rv $item $TMP_DIR/
done

# Remove demos for Smarty sources
rm -rf $TMP_DIR/vendor/smarty/smarty/demo

# Remove all git repo data
for item in `find $TMP_DIR | grep -i "/\.git/"`
do
    rm -rf $item
done

# Copy only compilled CSS files
mkdir $TMP_DIR/css
for item in `find css | grep -i "\.css$"`
do
    cp -rv $item $TMP_DIR/css/
done

# Create a package
zip mpcl-wordpress-1.2.zip `find $TMP_DIR`