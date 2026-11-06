#!/bin/sh
if [ -z "$1" ]; then
    echo "Usage: $0 /path/to/opnsense-plugins-clone"
    exit 1
fi

TARGET_DIR="$1/sysutils/os-git-backup"
echo "Staging modern MVC Git Backup files into ports tree track: $TARGET_DIR"

mkdir -p "$TARGET_DIR"
# copy the directory itself, not its contents: a plugin needs <plugin>/src/opnsense/...
cp -R src "$TARGET_DIR/"
echo "Assets successfully integrated. Please remember to update version numbers in Makefile and complete pkg-plist before executing a build."
