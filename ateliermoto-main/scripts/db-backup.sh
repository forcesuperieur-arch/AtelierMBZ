#!/bin/bash
# Sauvegarde la BDD PostgreSQL dans un fichier SQL
# Usage: ./scripts/db-backup.sh [nom_fichier]

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
CANDIDATE_DIRS=(
    "$SCRIPT_DIR/../backups"
    "$SCRIPT_DIR/../../backups"
)

BACKUP_DIR=""
for dir in "${CANDIDATE_DIRS[@]}"; do
    if [ -d "$dir" ]; then
        BACKUP_DIR="$dir"
        break
    fi
done

if [ -z "$BACKUP_DIR" ]; then
    BACKUP_DIR="${CANDIDATE_DIRS[1]}"
fi

mkdir -p "$BACKUP_DIR"

FILENAME="${1:-backup_$(date +%Y%m%d_%H%M%S).sql}"
FILEPATH="$BACKUP_DIR/$FILENAME"

echo "💾 Backup BDD → $FILEPATH"
docker compose exec -T db pg_dump -U atelier -d atelier_moto --clean --if-exists > "$FILEPATH"

SIZE=$(du -h "$FILEPATH" | cut -f1)
echo "✅ Backup terminé ($SIZE)"
echo "   Dossier backups: $BACKUP_DIR"
echo "   Pour restaurer: ./scripts/db-restore.sh $FILENAME"
