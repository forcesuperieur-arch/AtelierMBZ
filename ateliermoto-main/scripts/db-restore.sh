#!/bin/bash
# Restaure la BDD depuis un fichier SQL
# Usage: ./scripts/db-restore.sh <nom_fichier>

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
    BACKUP_DIR="${CANDIDATE_DIRS[0]}"
fi

if [ -z "$1" ]; then
    echo "Usage: ./scripts/db-restore.sh <fichier.sql>"
    echo ""
    echo "Backups disponibles:"
    ls -lh "$BACKUP_DIR"/*.sql 2>/dev/null || echo "  Aucun backup trouvé"
    exit 1
fi

FILEPATH="$BACKUP_DIR/$1"
if [ ! -f "$FILEPATH" ]; then
    echo "❌ Fichier non trouvé: $FILEPATH"
    exit 1
fi

echo "⚠️  Restauration de $FILEPATH — les données actuelles seront écrasées"
read -p "Continuer ? (y/N) " confirm
if [ "$confirm" != "y" ] && [ "$confirm" != "Y" ]; then
    echo "Annulé."
    exit 0
fi

echo "🔄 Restauration en cours..."
docker compose exec -T db psql -v ON_ERROR_STOP=1 -U atelier -d atelier_moto < "$FILEPATH"
echo "✅ Restauration terminée"
