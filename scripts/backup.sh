#!/bin/bash
# Backup the synctify MariaDB database. Fails loudly: any error exits non-zero
# so a failure shows up as a failure instead of a bogus "Backup complete".
set -euo pipefail

BACKUP_DIR=~/backups/synctify
MAX_BACKUPS=14
CONTAINER=synctify-backend-mysql_db-1
DB=laravel
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
TARGET="$BACKUP_DIR/synctify_${DB}_$TIMESTAMP.sql.gz"

log() { echo "$(date '+%Y-%m-%d %H:%M:%S') $*"; }
fail() { log "ERROR: $*"; [ -n "${HC_PING_URL:-}" ] && curl -fsS -m 10 -o /dev/null "$HC_PING_URL/fail" || true; exit 1; }

mkdir -p "$BACKUP_DIR" || fail "cannot create $BACKUP_DIR"
[ -w "$BACKUP_DIR" ] || fail "$BACKUP_DIR is not writable by $(whoami)"

docker inspect -f '{{.State.Running}}' "$CONTAINER" 2>/dev/null | grep -q true \
  || fail "$CONTAINER is not running"

# --single-transaction keeps InnoDB consistent without locking the app out
docker exec "$CONTAINER" mariadb-dump -uroot -proot \
  --single-transaction --quick --routines --events "$DB" 2>/dev/null | gzip > "$TARGET" \
  || { rm -f "$TARGET"; fail "mariadb-dump failed"; }

# Verify the artifact: non-empty, valid gzip, and structurally a real dump.
# Size alone is a bad check here - this DB is legitimately small - so assert
# the dump actually contains schema.
[ -s "$TARGET" ] || { rm -f "$TARGET"; fail "backup file is empty"; }
gzip -t "$TARGET" || { rm -f "$TARGET"; fail "backup is not a valid gzip archive"; }
TABLES=$(zcat "$TARGET" | grep -c 'CREATE TABLE' || true)
[ "$TABLES" -ge 1 ] || { rm -f "$TARGET"; fail "dump contains no CREATE TABLE - not a usable backup"; }

# Prune only after a verified-good backup, so failures cannot delete good copies
ls -t "$BACKUP_DIR"/synctify_${DB}_*.sql.gz 2>/dev/null | tail -n +$((MAX_BACKUPS + 1)) | xargs -r rm -f

log "Backup complete: $(basename "$TARGET") ($(du -h "$TARGET" | cut -f1), $TABLES tables)"
[ -n "${HC_PING_URL:-}" ] && curl -fsS -m 10 -o /dev/null "$HC_PING_URL" || true
exit 0
