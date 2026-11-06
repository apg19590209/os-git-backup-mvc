#!/bin/sh
#
# gitbackup_runner.sh
#
# Configd/cron entry point for the community os-git-backup plugin.
#
# Snapshots the live configuration into the plugin-owned working tree
# /conf/backup/git (never into /conf itself) and records one local commit.
#
# Deliberately out of scope here:
#   * no upstream remote configuration, credential staging or push - remote
#     delivery remains on the explicit Save/Test path in
#     OPNsense\Backup\Git::backup();
#   * no write outside /conf/backup/git;
#   * nothing is committed when the snapshot is unchanged.
#
# usage: gitbackup_runner.sh [config-file] [--status]
#
set -u

GIT=/usr/local/bin/git
XMLLINT=/usr/local/bin/xmllint
LOGGER=/usr/bin/logger
CONFIG=/conf/config.xml
TARGETDIR=/conf/backup/git
LOCKDIR=/var/run/gitbackup_runner.lock

umask 022

log() {
    "$LOGGER" -t gitbackup -p daemon.notice "gitbackup_runner: $*" 2>/dev/null || true
    printf 'gitbackup_runner: %s\n' "$*"
}

fail() {
    log "ERROR: $*"
    exit 1
}

# print the string value of an xpath, empty when the node is absent
cfg() {
    "$XMLLINT" --xpath "string($1)" "$SRC" 2>/dev/null || true
}

STATUS_ONLY=0
SRC=$CONFIG
for arg in "$@"; do
    case "$arg" in
        --status) STATUS_ONLY=1 ;;
        -*) fail "unknown option: $arg" ;;
        *) SRC=$arg ;;
    esac
done

[ -x "$GIT" ] || fail "git is not available at $GIT"
[ -r "$SRC" ] || fail "configuration file is not readable: $SRC"

if [ "$STATUS_ONLY" = "1" ]; then
    if [ -d "$TARGETDIR/.git" ]; then
        "$GIT" -C "$TARGETDIR" --no-pager log -1 --pretty='%H %ad %an <%ae> %s' --date=iso 2>/dev/null || true
        "$GIT" -C "$TARGETDIR" status --short 2>/dev/null || true
    else
        printf 'no local repository at %s\n' "$TARGETDIR"
    fi
    exit 0
fi

# the provider switch is authoritative: a disabled provider records nothing
if [ "$(cfg '//system/backup/git/enabled')" != "1" ]; then
    exit 0
fi

# single-run guard, no external locking dependency
if ! mkdir "$LOCKDIR" 2>/dev/null; then
    log "another run is still in progress, skipping"
    exit 0
fi
trap 'rmdir "$LOCKDIR" 2>/dev/null || true' EXIT HUP INT TERM

[ -d "$TARGETDIR" ] || mkdir -p "$TARGETDIR" || fail "cannot create $TARGETDIR"

if [ ! -d "$TARGETDIR/.git" ]; then
    "$GIT" -C "$TARGETDIR" init -q || fail "git init failed in $TARGETDIR"
    log "initialised local repository $TARGETDIR"
fi

cp "$SRC" "$TARGETDIR/config.xml" || fail "cannot snapshot $SRC into $TARGETDIR"
"$GIT" -C "$TARGETDIR" add -- config.xml || fail "git add failed in $TARGETDIR"

if "$GIT" -C "$TARGETDIR" diff --cached --quiet; then
    log "configuration unchanged, nothing to record"
    exit 0
fi

# commit identity follows the OPNsense user who made the change, then the
# configured git user when it carries an address, then a local fallback.
# Global git identity is never required or modified.
REV_USER=$(cfg '//revision/username')
SETTINGS_USER=$(cfg '//system/backup/git/user')
HOST=$(cfg '//system/hostname')
DOMAIN=$(cfg '//system/domain')
[ -n "$HOST" ] || HOST=localhost
[ -n "$DOMAIN" ] || DOMAIN=localdomain

# only characters that are safe inside the xpath predicate below; the revision
# username is stored as user@address, so the bare account name is used
SAFE_USER=$(printf '%s' "${REV_USER%%@*}" | tr -cd 'A-Za-z0-9._-')

AUTHOR_NAME=
AUTHOR_EMAIL=
if [ -n "$SAFE_USER" ]; then
    AUTHOR_NAME=$(cfg "//system/user[name=\"$SAFE_USER\"]/descr")
    AUTHOR_EMAIL=$(cfg "//system/user[name=\"$SAFE_USER\"]/email")
fi
[ -n "$AUTHOR_NAME" ] || AUTHOR_NAME=$SAFE_USER
[ -n "$AUTHOR_NAME" ] || AUTHOR_NAME=git-backup

# an explicit git_user setting carrying an address wins over the revision metadata
case "$SETTINGS_USER" in
    *@*) AUTHOR_EMAIL=$SETTINGS_USER ;;
esac
[ -n "$AUTHOR_EMAIL" ] || AUTHOR_EMAIL="git-backup@$HOST.$DOMAIN"

DESCRIPTION=$(cfg '//revision/description')
MESSAGE="Configuration snapshot @ $(date '+%Y-%m-%d %H:%M:%S')"
[ -n "$DESCRIPTION" ] && MESSAGE="$DESCRIPTION @ $(date '+%Y-%m-%d %H:%M:%S')"

"$GIT" -C "$TARGETDIR" \
    -c user.name="$AUTHOR_NAME" -c user.email="$AUTHOR_EMAIL" \
    commit --author="$AUTHOR_NAME <$AUTHOR_EMAIL>" -q -m "$MESSAGE" \
    || fail "git commit failed in $TARGETDIR"

log "recorded $("$GIT" -C "$TARGETDIR" rev-parse --short HEAD)"
exit 0
