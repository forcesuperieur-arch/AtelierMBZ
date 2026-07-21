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
$COMPOSE_BIN build php nuxt client-nuxt caddy

echo "[3/7] Redémarrage des services"
# client-nuxt = portail client public (proxifié par Caddy sur /client/*) : il DOIT
# démarrer, sinon l'espace client est down après déploiement. mailhog volontairement
# absent (SMTP réel en prod via MAILER_DSN).
$COMPOSE_BIN up -d db php worker nuxt client-nuxt caddy mercure

echo "[4/7] Sauvegarde de la base AVANT migrations (rollback possible)"
COMPOSE_BIN="$COMPOSE_BIN" ./scripts/backup-db.sh "${BACKUP_DIR:-/var/backups/paddock}" || {
  echo "ATTENTION : sauvegarde pré-migration échouée — déploiement interrompu." >&2
  exit 1
}

echo "[5/7] Base de données"
# Base VIDE (premier déploiement) : les migrations sont incrémentales et ne créent
# pas le schéma de base → initialiser depuis la baseline. Sinon : migrations normales.
TABLES="$($COMPOSE_BIN exec -T db psql -U "${POSTGRES_USER:-atelier}" -d "${POSTGRES_DB:-atelier_moto}" -tAc "SELECT count(*) FROM information_schema.tables WHERE table_schema='public';" 2>/dev/null | tr -d '[:space:]')"
if [ "${TABLES:-0}" = "0" ]; then
  echo "  base vide → initialisation depuis la baseline (scripts/init-fresh-db.sh)"
  COMPOSE_BIN="$COMPOSE_BIN" ./scripts/init-fresh-db.sh
else
  echo "  base existante ($TABLES tables) → migrations incrémentales"
  $COMPOSE_BIN exec -T php php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
fi

echo "[6/7] Vérifications HTTP"
if curl -kfsS https://localhost/login >/dev/null 2>&1; then
  echo "Front OK en HTTPS"
else
  curl -fsS http://localhost/login >/dev/null
  echo "Front OK en HTTP"
fi

# /api/docs est masqué en prod (durcissement) : on teste la vie de l'API via un
# endpoint public légitime (clauses légales, servi 200 sans authentification).
if curl -kfsS https://localhost/api/clauses-legales >/dev/null 2>&1; then
  echo "API OK en HTTPS"
else
  curl -fsS http://localhost/api/clauses-legales >/dev/null
  echo "API OK en HTTP"
fi

# Portail client : contrôlé directement sur son conteneur (127.0.0.1:3001), car
# la route /client n'existe que sur l'edge public (domaine), pas sur l'edge local.
if curl -fsS "http://127.0.0.1:${CLIENT_FRONTEND_PORT:-3001}/client/login" >/dev/null 2>&1; then
  echo "Portail client OK"
else
  echo "ATTENTION : portail client injoignable (client-nuxt) — vérifier 'docker compose logs client-nuxt'." >&2
  exit 1
fi

echo "[7/7] Déploiement terminé avec succès"
