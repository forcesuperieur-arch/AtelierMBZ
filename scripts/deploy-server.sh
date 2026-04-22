#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="${PROJECT_DIR:-/opt/ateliermbz}"
BRANCH="${BRANCH:-main}"
COMPOSE_BIN="${COMPOSE_BIN:-docker compose}"
COMPOSE_FILES=("-f" "docker-compose.yml" "-f" "docker-compose.preprod.yml")

cd "$PROJECT_DIR"

echo "[AtelierMBZ] Déploiement sur la branche $BRANCH"

echo "[1/6] Synchronisation Git"
git fetch origin "$BRANCH"
git checkout "$BRANCH"
git merge --ff-only "origin/$BRANCH"

echo "[2/6] Build des images"
$COMPOSE_BIN "${COMPOSE_FILES[@]}" build php nuxt caddy

echo "[3/6] Redémarrage des services"
$COMPOSE_BIN "${COMPOSE_FILES[@]}" up -d db php worker nuxt caddy mercure

echo "[4/6] Migrations Doctrine"
$COMPOSE_BIN "${COMPOSE_FILES[@]}" exec -T php php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "[5/6] Vérifications HTTP"
if curl -kfsS https://localhost/login >/dev/null 2>&1; then
  echo "Front OK en HTTPS"
else
  curl -fsS http://localhost/login >/dev/null
  echo "Front OK en HTTP"
fi

if curl -kfsS https://localhost/api/health >/dev/null 2>&1; then
  echo "API health OK en HTTPS"
else
  curl -fsS http://localhost/api/health >/dev/null
  echo "API health OK en HTTP"
fi

echo "[6/6] Déploiement terminé avec succès"
