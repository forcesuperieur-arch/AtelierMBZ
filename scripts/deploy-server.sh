#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="${PROJECT_DIR:-/opt/ateliermbz}"
BRANCH="${BRANCH:-main}"
COMPOSE_BIN="${COMPOSE_BIN:-docker compose}"

cd "$PROJECT_DIR"

echo "[AtelierMBZ] Déploiement sur la branche $BRANCH"

echo "[1/7] Synchronisation Git"
git fetch origin "$BRANCH"
git checkout "$BRANCH"
git reset --hard "origin/$BRANCH"

echo "[2/7] Build des images"
$COMPOSE_BIN build php nuxt caddy

echo "[3/7] Redémarrage des services"
$COMPOSE_BIN up -d db php worker nuxt caddy mercure

echo "[4/7] Sauvegarde de la base AVANT migrations (rollback possible)"
COMPOSE_BIN="$COMPOSE_BIN" ./scripts/backup-db.sh "${BACKUP_DIR:-/var/backups/paddock}" || {
  echo "ATTENTION : sauvegarde pré-migration échouée — déploiement interrompu." >&2
  exit 1
}

echo "[5/7] Migrations Doctrine"
$COMPOSE_BIN exec -T php php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "[6/7] Vérifications HTTP"
if curl -kfsS https://localhost/login >/dev/null 2>&1; then
  echo "Front OK en HTTPS"
else
  curl -fsS http://localhost/login >/dev/null
  echo "Front OK en HTTP"
fi

if curl -kfsS https://localhost/api/docs >/dev/null 2>&1; then
  echo "API OK en HTTPS"
else
  curl -fsS http://localhost/api/docs >/dev/null
  echo "API OK en HTTP"
fi

echo "[7/7] Déploiement terminé avec succès"
