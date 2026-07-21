#!/usr/bin/env bash
#
# Feu vert avant mise en ligne — Paddock.
#
# Vérifie automatiquement que le serveur est prêt à ouvrir au public. Reprend, en
# une seule commande, la checklist manuelle de DEPLOIEMENT.md (§1, §3, §5).
#
# LECTURE SEULE par défaut (aucune modification de la base ni des services).
# L'option --with-backup lance en plus une vraie sauvegarde pour prouver la chaîne.
#
# Usage (sur le serveur cible, depuis le dossier du projet) :
#   scripts/preflight-prod.sh                # tous les contrôles, lecture seule
#   scripts/preflight-prod.sh --with-backup  # + test de sauvegarde
#
# Variables optionnelles : COMPOSE_BIN (défaut "docker compose"), ENV_FILE (défaut .env)
#
# Code de sortie : 0 si aucun échec bloquant, 1 sinon (les avertissements ne bloquent pas).

set -uo pipefail  # pas de -e : on veut dérouler TOUS les contrôles, pas s'arrêter au 1er

COMPOSE_BIN="${COMPOSE_BIN:-docker compose}"
ENV_FILE="${ENV_FILE:-.env}"
WITH_BACKUP=0
[ "${1:-}" = "--with-backup" ] && WITH_BACKUP=1

fails=0
warns=0
ok()      { printf '  [ OK ] %s\n' "$1"; }
bad()     { printf '  [FAIL] %s\n' "$1"; fails=$((fails + 1)); }
warn()    { printf '  [WARN] %s\n' "$1"; warns=$((warns + 1)); }
section() { printf '\n=== %s ===\n' "$1"; }

envval() { grep -E "^$1=" "$ENV_FILE" 2>/dev/null | head -1 | cut -d= -f2- | tr -d '"' ; }

# Requête psql non interactive dans le conteneur db (chaîne vide si indisponible).
DBU="$(envval POSTGRES_USER)"; DBU="${DBU:-atelier}"
DBN="$(envval POSTGRES_DB)";   DBN="${DBN:-atelier_moto}"
psql_q() { $COMPOSE_BIN exec -T db psql -U "$DBU" -d "$DBN" -tAc "$1" 2>/dev/null | tr -d '[:space:]'; }

httpcode() { curl -k -s -o /dev/null -w '%{http_code}' --max-time 10 "$1" 2>/dev/null; }

echo "========================================================"
echo " Paddock — contrôle avant mise en ligne (preflight-prod)"
echo "========================================================"

# ---------------------------------------------------------------------------
section "1. Fichier d'environnement (.env)"
if [ ! -f "$ENV_FILE" ]; then
  bad "$ENV_FILE introuvable — copier .env.prod.example en .env et le remplir."
else
  ok "$ENV_FILE présent"

  if grep -q 'CHANGEME' "$ENV_FILE"; then
    bad "des valeurs 'CHANGEME' restent dans $ENV_FILE : $(grep -c CHANGEME "$ENV_FILE") ligne(s) à compléter."
  else
    ok "aucun 'CHANGEME' résiduel"
  fi

  APP_ENV="$(envval APP_ENV)"
  [ "$APP_ENV" = "prod" ] && ok "APP_ENV=prod" || bad "APP_ENV='$APP_ENV' (attendu: prod)"

  PUBLIC_DOMAIN="$(envval PUBLIC_DOMAIN)"
  if [ -z "$PUBLIC_DOMAIN" ]; then
    bad "PUBLIC_DOMAIN vide — le domaine public n'est pas défini (HTTPS impossible)."
  else
    ok "PUBLIC_DOMAIN=$PUBLIC_DOMAIN"
  fi

  PUBLIC_URL="$(envval PUBLIC_URL)"
  case "$PUBLIC_URL" in
    https://*CHANGEME*) bad "PUBLIC_URL contient encore CHANGEME." ;;
    https://*)          ok "PUBLIC_URL en https" ;;
    *)                  bad "PUBLIC_URL='$PUBLIC_URL' (doit commencer par https://)." ;;
  esac

  MAILER_DSN="$(envval MAILER_DSN)"
  case "$MAILER_DSN" in
    *mailhog*)   bad "MAILER_DSN pointe encore sur MailHog (dev) — mettre un SMTP réel." ;;
    *CHANGEME*|"") bad "MAILER_DSN non renseigné." ;;
    *)           ok "MAILER_DSN configuré (SMTP réel)" ;;
  esac
fi

# ---------------------------------------------------------------------------
section "2. Services Docker"
RUNNING="$($COMPOSE_BIN ps --services --status running 2>/dev/null)"
for svc in db php worker nuxt client-nuxt caddy mercure; do
  if grep -qx "$svc" <<<"$RUNNING"; then
    ok "service '$svc' démarré"
  else
    bad "service '$svc' NON démarré."
  fi
done
if grep -qx "mailhog" <<<"$RUNNING"; then
  warn "MailHog tourne — inutile en prod (SMTP réel), le désactiver."
else
  ok "MailHog absent (attendu en prod)"
fi

# ---------------------------------------------------------------------------
section "3. Base de données & migrations"
if [ "$(psql_q 'SELECT 1;')" = "1" ]; then
  ok "base '$DBN' accessible"

  if $COMPOSE_BIN exec -T php php bin/console doctrine:migrations:up-to-date >/dev/null 2>&1; then
    ok "migrations à jour"
  else
    bad "migrations non appliquées — lancer doctrine:migrations:migrate."
  fi

  # Données de DÉMO interdites en prod : créées uniquement par `app:seed --demo`
  # (mécaniciens meca…@atelier.local, clients fictifs). L'admin de base
  # (username 'admin') est LÉGITIME — juste à passer sur un email réel, contrôlé plus bas.
  n="$(psql_q "SELECT count(*) FROM users WHERE email LIKE 'meca%@atelier.local';")"
  [ "${n:-0}" = "0" ] && ok "mécaniciens de démo absents" || bad "mécaniciens de démo présents (meca…@atelier.local) — seeds --demo lancés par erreur."
  n="$(psql_q "SELECT count(*) FROM clients WHERE email IN ('jean.moreau@email.fr','sophie.petit@email.fr','pierre.robert@email.fr','marie.durand@email.fr','antoine.leroy@email.fr');")"
  [ "${n:-0}" = "0" ] && ok "clients de démo absents" || bad "clients de démo présents — seeds --demo lancés par erreur."

  # Admin de base : email par défaut à personnaliser (hygiène, non bloquant).
  n="$(psql_q "SELECT count(*) FROM users WHERE username='admin' AND email='admin@atelier.local';")"
  [ "${n:-0}" = "0" ] && ok "admin sur un email personnalisé" || warn "l'admin utilise l'email par défaut admin@atelier.local — définir ADMIN_EMAIL sur une adresse réelle."

  # Mentions légales : les placeholders doivent avoir été remplacés (update_clauses.sql)
  n="$(psql_q "SELECT count(*) FROM clause_legale WHERE texte LIKE '%[SIRET]%' OR texte LIKE '%[TVA]%' OR texte LIKE '%[Hébergeur]%' OR texte LIKE '%[capital]%' OR texte LIKE '%[ville]%' OR texte LIKE '%[adresse]%';")"
  [ "${n:-1}" = "0" ] && ok "clauses légales renseignées (aucun placeholder)" || bad "placeholders légaux non remplis ([SIRET]/[TVA]/…) — appliquer update_clauses.sql complété."
else
  bad "base '$DBN' injoignable — impossible de vérifier migrations, comptes, clauses."
fi

# ---------------------------------------------------------------------------
section "4. Worker (rappels / RGPD / notifications)"
if $COMPOSE_BIN exec -T worker php bin/console app:worker-health >/dev/null 2>&1; then
  ok "worker en bonne santé (file d'échec vide)"
else
  warn "worker signalé unhealthy — 'messenger:failed:show' pour le détail."
fi

# ---------------------------------------------------------------------------
section "5. Surface publique (via le domaine)"
# PREFLIGHT_BASE_URL permet de viser un edge local/staging (ex. http://localhost:81)
# quand le domaine public n'est pas encore résolu (répétition de déploiement).
base="${PREFLIGHT_BASE_URL:-}"
[ -z "$base" ] && [ -n "${PUBLIC_DOMAIN:-}" ] && base="https://$PUBLIC_DOMAIN"
if [ -n "$base" ]; then
  chk() { # url attendu libellé
    local code; code="$(httpcode "$1")"
    if [ "$code" = "$2" ]; then ok "$3 → $code"; else bad "$3 → $code (attendu $2)"; fi
  }
  chk "$base/api/companion/x/status"  "404" "back-office masqué (companion)"
  chk "$base/.well-known/mercure"     "404" "Mercure masqué"
  chk "$base/api/client/me"           "401" "API client protégée"
  code="$(httpcode "$base/client/")"
  case "$code" in
    200|308|302) ok "portail client servi → $code" ;;
    *)           bad "portail client → $code (attendu 200/308)" ;;
  esac
else
  warn "PUBLIC_DOMAIN vide (et pas de PREFLIGHT_BASE_URL) — contrôles de surface publique sautés."
fi

# ---------------------------------------------------------------------------
section "6. Sauvegarde"
if [ -x scripts/backup-db.sh ]; then
  ok "scripts/backup-db.sh présent"
  if [ "$WITH_BACKUP" = "1" ]; then
    if COMPOSE_BIN="$COMPOSE_BIN" ./scripts/backup-db.sh "${BACKUP_DIR:-/var/backups/paddock}"; then
      ok "sauvegarde de test réussie"
    else
      bad "la sauvegarde de test a échoué."
    fi
  else
    warn "sauvegarde non testée (relancer avec --with-backup), et vérifier le cron hôte (voir en-tête de backup-db.sh)."
  fi
else
  bad "scripts/backup-db.sh absent ou non exécutable."
fi

# ---------------------------------------------------------------------------
echo
echo "========================================================"
if [ "$fails" -eq 0 ]; then
  echo " RÉSULTAT : PRÊT ✅  ($warns avertissement(s))"
  echo "========================================================"
  exit 0
else
  echo " RÉSULTAT : $fails BLOCAGE(S) ❌  — $warns avertissement(s)"
  echo " Corriger les [FAIL] ci-dessus avant d'ouvrir au public."
  echo "========================================================"
  exit 1
fi
