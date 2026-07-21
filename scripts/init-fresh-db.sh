#!/usr/bin/env bash
#
# Initialisation d'une base NEUVE (premier déploiement sur un serveur vierge).
#
# Pourquoi ce script : le jeu de migrations Doctrine est purement INCRÉMENTAL — il
# suppose que le schéma de base existe déjà (la 1re migration fait `ALTER TABLE`,
# pas `CREATE TABLE`). Sur une base vide, `doctrine:migrations:migrate` échoue donc
# ("relation ... does not exist"). Et `doctrine:schema:create` seul perd les objets
# posés en SQL brut par migration (index partiels) ainsi que les données portées par
# migration (clauses légales).
#
# La solution fiable : appliquer une BASELINE figée (schéma de référence complet +
# contenu légal : backend/schema/baseline.sql), puis marquer toutes les migrations
# comme déjà appliquées, et enfin semer les paramètres de base + l'admin.
#
# Usage :
#   scripts/init-fresh-db.sh          # initialise la base (refuse si non vide)
#   scripts/init-fresh-db.sh --dump   # (dev) régénère baseline.sql depuis la base courante
#
# Après ce script : appliquer update_clauses.sql (placeholders légaux remplis), puis
# les déploiements suivants utilisent doctrine:migrations:migrate normalement.
#
set -euo pipefail

COMPOSE_BIN="${COMPOSE_BIN:-docker compose}"
DB_USER="${POSTGRES_USER:-atelier}"
DB_NAME="${POSTGRES_DB:-atelier_moto}"
BASELINE="backend/schema/baseline.sql"

psql_db() { $COMPOSE_BIN exec -T db psql -U "$DB_USER" -d "$DB_NAME" "$@"; }
console() { $COMPOSE_BIN exec -T php php bin/console "$@"; }

# --- Mode --dump : régénère la baseline depuis la base de référence (usage dev) ---
if [ "${1:-}" = "--dump" ]; then
  echo "[dump] Régénération de $BASELINE depuis $DB_NAME…"
  mkdir -p "$(dirname "$BASELINE")"
  {
    echo "-- Baseline d'installation Paddock — schéma de référence + contenu légal."
    echo "-- Régénéré via scripts/init-fresh-db.sh --dump. NE PAS éditer à la main."
    echo
    $COMPOSE_BIN exec -T db pg_dump -U "$DB_USER" --schema-only --no-owner --no-privileges "$DB_NAME"
    echo
    echo "-- ============ Données : clauses légales (contenu public opposable) ============"
    $COMPOSE_BIN exec -T db pg_dump -U "$DB_USER" --data-only --no-owner -t clause_legale "$DB_NAME"
  } > "$BASELINE"
  echo "[dump] OK — $(grep -c 'CREATE TABLE' "$BASELINE") tables, $(wc -l < "$BASELINE") lignes."
  exit 0
fi

# --- Sécurité : ne jamais écraser une base déjà peuplée ---
[ -f "$BASELINE" ] || { echo "ERREUR : $BASELINE introuvable." >&2; exit 1; }
existing="$(psql_db -tAc "SELECT count(*) FROM information_schema.tables WHERE table_schema='public';" | tr -d '[:space:]')"
if [ "${existing:-0}" != "0" ]; then
  echo "ERREUR : la base '$DB_NAME' contient déjà $existing table(s)." >&2
  echo "  → base non vide : utiliser 'doctrine:migrations:migrate' (déploiement normal), pas ce script." >&2
  exit 1
fi

echo "[1/4] Application de la baseline (schéma + clauses légales)"
psql_db -v ON_ERROR_STOP=1 < "$BASELINE" >/dev/null
echo "      $(psql_db -tAc "SELECT count(*) FROM information_schema.tables WHERE table_schema='public';" | tr -d '[:space:]') tables, $(psql_db -tAc 'SELECT count(*) FROM clause_legale;' | tr -d '[:space:]') clauses légales."

echo "[2/4] Marquage des migrations comme déjà appliquées"
console doctrine:migrations:version --add --all --no-interaction >/dev/null
console doctrine:migrations:up-to-date

echo "[3/4] Paramètres de base (rôles, prestations, config) — SANS données de démo"
console app:seed >/dev/null

echo "[4/4] Compte administrateur initial (ADMIN_USERNAME / ADMIN_PASSWORD / ADMIN_EMAIL)"
console app:create-admin

echo
echo "Base initialisée. Étape suivante : appliquer update_clauses.sql (placeholders légaux"
echo "[SIRET]/[TVA]/[Hébergeur]/… remplis) puis lancer scripts/preflight-prod.sh."
