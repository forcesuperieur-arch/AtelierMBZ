#!/usr/bin/env bash
#
# Sauvegarde PostgreSQL de Paddock, avec rétention (RGPD : 30 j minimum).
#
# Usage :
#   scripts/backup-db.sh [dossier_destination]
#
# À planifier en cron HÔTE (hors dépôt git, jamais versionné) — exemple quotidien 2h :
#   0 2 * * * cd /opt/ateliermbz && ./scripts/backup-db.sh /var/backups/paddock >> /var/log/paddock-backup.log 2>&1
#
# Variables d'environnement optionnelles :
#   POSTGRES_USER (défaut atelier) · POSTGRES_DB (défaut atelier_moto)
#   BACKUP_RETENTION_DAYS (défaut 30) · COMPOSE_BIN (défaut "docker compose")
#
set -euo pipefail

DEST="${1:-/var/backups/paddock}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-30}"
COMPOSE_BIN="${COMPOSE_BIN:-docker compose}"
DB_USER="${POSTGRES_USER:-atelier}"
DB_NAME="${POSTGRES_DB:-atelier_moto}"

mkdir -p "$DEST"
STAMP="$(date +%Y%m%d_%H%M%S)"
FILE="$DEST/paddock_${STAMP}.sql.gz"

# Dump compressé. -T : pas de TTY (exécution non interactive / cron).
$COMPOSE_BIN exec -T db pg_dump -U "$DB_USER" "$DB_NAME" | gzip > "$FILE"

# Garde-fou : un dump vide (DB injoignable, mauvais identifiants…) ne doit pas
# écraser silencieusement la rotation ni faire croire à une sauvegarde valide.
if [ ! -s "$FILE" ]; then
  echo "ERREUR : dump vide, sauvegarde abandonnée ($FILE)" >&2
  rm -f "$FILE"
  exit 1
fi

# Rétention : supprime les sauvegardes plus vieilles que RETENTION_DAYS.
find "$DEST" -name 'paddock_*.sql.gz' -type f -mtime +"$RETENTION_DAYS" -delete

echo "Sauvegarde OK : $FILE ($(du -h "$FILE" | cut -f1))"
