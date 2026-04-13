#!/bin/bash
# db-access.sh - Accès rapide à la base de données PostgreSQL

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_ROOT"

echo "╔═══════════════════════════════════════════╗"
echo "║  Atelier Moto - Accès Base de Données     ║"
echo "╚═══════════════════════════════════════════╝"
echo ""
echo "1. 🖥️  Interface Web pgAdmin (http://127.0.0.1:5050)"
echo "   Login: admin@ateliermoto.app / admin123"
echo ""
echo "2. 💻 CLI PostgreSQL (psql shell)"
echo ""
echo "3. 📊 Rapide: Compter les tables"
echo ""
echo "4. 🔄 Vérifier la connexion"
echo ""
echo "0. ❌ Quitter"
echo ""
read -p "Choisir (0-4): " choice

case $choice in
  1)
    echo "🌐 Ouvrant pgAdmin..."
    echo "   URL: http://127.0.0.1:5050"
    if command -v xdg-open &> /dev/null; then
      xdg-open http://127.0.0.1:5050
    elif command -v open &> /dev/null; then
      open http://127.0.0.1:5050
    else
      echo "   Ouvrez manuellement: http://127.0.0.1:5050"
    fi
    ;;
  2)
    echo "🐘 Connexion à PostgreSQL..."
    docker compose exec -T db psql -U atelier -d atelier_moto
    ;;
  3)
    echo "📊 Statistiques des tables:"
    docker compose exec -T db psql -U atelier -d atelier_moto -c "
SELECT 
  'Clients' as entity, COUNT(*) as count FROM clients
UNION ALL
SELECT 'Prestations', COUNT(*) FROM prestations
UNION ALL
SELECT 'Rendez-vous', COUNT(*) FROM rendez_vous
UNION ALL
SELECT 'Grille Tarifaire', COUNT(*) FROM grille_tarifaire
UNION ALL
SELECT 'Véhicules', COUNT(*) FROM vehicules
UNION ALL
SELECT 'Utilisateurs', COUNT(*) FROM users
ORDER BY count DESC;"
    ;;
  4)
    echo "🔗 Vérification de la connexion..."
    docker compose exec -T db pg_isready -U atelier -d atelier_moto
    echo ""
    docker compose exec -T db psql -U atelier -d atelier_moto -c "SELECT version();"
    ;;
  0)
    echo "Au revoir! 👋"
    exit 0
    ;;
  *)
    echo "❌ Option invalide"
    exit 1
    ;;
esac
