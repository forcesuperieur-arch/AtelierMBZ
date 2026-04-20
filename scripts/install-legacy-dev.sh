#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

DB_NAME="${POSTGRES_DB:-atelier_moto}"
DB_USER="${POSTGRES_USER:-atelier}"

pick_latest_file() {
  local pattern_dir="$1"
  local pattern_name="$2"
  find "$pattern_dir" -maxdepth 1 -type f -name "$pattern_name" -size +1k -printf '%T@ %p\n' 2>/dev/null \
    | sort -nr \
    | awk 'NR==1 {print $2}'
}

DUMP_FILE="$(pick_latest_file "$ROOT_DIR/backup" 'local_migration_*.sql')"
if [[ -z "$DUMP_FILE" ]]; then
  DUMP_FILE="$(find "$ROOT_DIR/backup" "$ROOT_DIR/backups" -maxdepth 1 -type f -name '*.sql' -size +1k -printf '%T@ %p\n' 2>/dev/null | sort -nr | awk 'NR==1 {print $2}')"
fi

UPLOADS_ARCHIVE="$(pick_latest_file "$ROOT_DIR/backup" 'uploads_*.tar.gz')"

echo "==> Start Docker services"
docker compose up -d --build

echo "==> Reset database schema: $DB_NAME"
docker compose exec -T db psql -U "$DB_USER" -d "$DB_NAME" -v ON_ERROR_STOP=1 <<'SQL'
DROP SCHEMA IF EXISTS public CASCADE;
CREATE SCHEMA public;
GRANT ALL ON SCHEMA public TO atelier;
GRANT ALL ON SCHEMA public TO public;
SQL

if [[ -n "$DUMP_FILE" ]]; then
  echo "==> Restore SQL dump: $DUMP_FILE"
  docker compose exec -T db psql -U "$DB_USER" -d "$DB_NAME" -v ON_ERROR_STOP=1 < "$DUMP_FILE"
else
  echo "==> No SQL dump found, running Doctrine migrations"
  docker compose exec -T php php bin/console doctrine:migrations:migrate --no-interaction
fi

if [[ -n "$UPLOADS_ARCHIVE" ]]; then
  echo "==> Restore uploads archive: $UPLOADS_ARCHIVE"
  TMP_UPLOADS_DIR="$(mktemp -d)"
  tar --touch --no-same-owner --no-same-permissions -xzf "$UPLOADS_ARCHIVE" -C "$TMP_UPLOADS_DIR"
  mkdir -p "$ROOT_DIR/backend/public/uploads"
  if [[ -d "$TMP_UPLOADS_DIR/backend/public/uploads/vo" ]]; then
    rm -rf "$ROOT_DIR/backend/public/uploads/vo"
    cp -a "$TMP_UPLOADS_DIR/backend/public/uploads/vo" "$ROOT_DIR/backend/public/uploads/"
  fi
  PHP_CONTAINER="$(docker compose ps -q php)"
  if [[ -n "$PHP_CONTAINER" && -d "$TMP_UPLOADS_DIR/backend/public/uploads" ]]; then
    docker cp "$TMP_UPLOADS_DIR/backend/public/uploads/." "$PHP_CONTAINER:/app/public/uploads/"
  fi
  rm -rf "$TMP_UPLOADS_DIR"
fi

echo "==> Clear Symfony cache"
docker compose exec -T php php bin/console cache:clear

echo "==> Done"
echo "Application: http://localhost"
echo "API docs   : http://localhost/api/docs"
echo "MailHog    : http://localhost:8025"
