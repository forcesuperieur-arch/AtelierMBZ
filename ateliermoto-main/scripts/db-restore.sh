#!/bin/bash
# Restaure la BDD depuis un fichier SQL
# Usage: ./scripts/db-restore.sh <nom_fichier>

set -e

BACKUP_DIR="$(dirname "$0")/../backups"

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
docker compose exec -T db psql -U atelier -d atelier_moto < "$FILEPATH"
echo "✅ Restauration terminée"
