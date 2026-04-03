#!/bin/bash
# Sauvegarde la BDD PostgreSQL dans un fichier SQL
# Usage: ./scripts/db-backup.sh [nom_fichier]

set -e

BACKUP_DIR="$(dirname "$0")/../backups"
mkdir -p "$BACKUP_DIR"

FILENAME="${1:-backup_$(date +%Y%m%d_%H%M%S).sql}"
FILEPATH="$BACKUP_DIR/$FILENAME"

echo "💾 Backup BDD → $FILEPATH"
docker compose exec -T db pg_dump -U atelier -d atelier_moto --clean --if-exists > "$FILEPATH"

SIZE=$(du -h "$FILEPATH" | cut -f1)
echo "✅ Backup terminé ($SIZE)"
echo "   Pour restaurer: ./scripts/db-restore.sh $FILENAME"
