#!/bin/sh
if [ -z "$1" ]; then
    echo "Usage: $0 /path/to/opnsense-plugins-clone"
    exit 1
fi

# the plugin directory is named after the source package, the os- prefix is added by Mk/plugins.mk
TARGET_DIR="$1/sysutils/git-backup"
echo "Staging modern MVC Git Backup files into ports tree track: $TARGET_DIR"

mkdir -p "$TARGET_DIR"
# copy the directory itself, not its contents: a plugin needs <plugin>/src/opnsense/...
cp -R src "$TARGET_DIR/"
cp -p Makefile pkg-descr "$TARGET_DIR/"
echo "Assets successfully integrated. Review PLUGIN_VERSION in the Makefile, then run 'make package' inside $TARGET_DIR."
