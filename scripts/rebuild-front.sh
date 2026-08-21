#!/usr/bin/env bash
#
# Rebuild + redémarrage d'un front Nuxt en local, avec PREUVE de ce qui est servi.
#
# POURQUOI CE SCRIPT
# ------------------
# Les deux fronts tournent en BUILD DE PRODUCTION, pas en mode dev :
#   docker-compose.yml → command: ["sh","-c","npm install && npx nuxt build && node .output/server/index.mjs"]
# Le build n'a donc lieu qu'AU DÉMARRAGE du conteneur. Tant que le service n'est pas
# redémarré, modifier frontend/**.vue ne change RIEN de ce qui est servi — même si le
# bind-mount ./frontend:/app est parfaitement à jour côté conteneur. C'est la cause
# n°1 du « je n'ai pas la nouvelle version en local ».
# Aucun watcher ne tourne : `docker compose ps` affiche « Up 14 minutes » sur un
# conteneur qui sert un bundle vieux de 14 minutes. Ce n'est PAS un signal de fraîcheur.
#
# Ce script fait les trois choses que `docker compose restart` ne fait pas :
#   1. il dit AVANT de rebuilder si un rebuild est seulement nécessaire ;
#   2. il ATTEND la vraie disponibilité (ligne « Listening on » du nouveau run) ;
#   3. il PROUVE par HTTP, sur l'URL que le navigateur utilise vraiment, que le bundle
#      servi a changé et qu'il contient le code attendu.
#
# USAGE
#   scripts/rebuild-front.sh                 # front staff : rebuild + redémarrage + preuve
#   scripts/rebuild-front.sh --check         # NE REBUILD PAS : dit en < 1 s ce qui est servi
#   scripts/rebuild-front.sh client          # portail client (service client-nuxt)
#   scripts/rebuild-front.sh client --check
#
# VARIABLES D'ENVIRONNEMENT
#   APP_URL           URL réellement testée (défaut : https://localhost/ pour le staff,
#                     http://localhost:81/client/ pour le portail client)
#   MARQUEUR          chaîne à retrouver dans le bundle servi (preuve bout-en-bout)
#   PUBLIC_EDGE_PORT  port de l'edge public Caddy (défaut 81, cf. docker-compose.yml)
#   TIMEOUT           délai max d'attente du redémarrage, en secondes (défaut 300)
#   COMPOSE_BIN       défaut "docker compose"
#
# COÛT RÉEL DE LA BOUCLE : voir la ligne « Boucle complète » en fin d'exécution.
# Le front renvoie 502 pendant toute la fenêtre de rebuild. C'est acceptable pour
# vérifier un écran, pas pour itérer sur du CSS.
#
set -euo pipefail

RACINE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$RACINE"

COMPOSE_BIN="${COMPOSE_BIN:-docker compose}"
TIMEOUT="${TIMEOUT:-300}"

# --- Sortie lisible (couleurs uniquement sur un vrai terminal) ---------------
if [ -t 1 ]; then
  C_OK=$'\033[32m'; C_WARN=$'\033[33m'; C_ERR=$'\033[31m'; C_DIM=$'\033[2m'; C_B=$'\033[1m'; C_0=$'\033[0m'
else
  C_OK=''; C_WARN=''; C_ERR=''; C_DIM=''; C_B=''; C_0=''
fi
titre() { printf '\n%s== %s ==%s\n' "$C_B" "$*" "$C_0"; }
ok()    { printf '  %sOK%s   %s\n' "$C_OK" "$C_0" "$*"; }
info()  { printf '  --   %s\n' "$*"; }
attn()  { printf '  %sATTN%s %s\n' "$C_WARN" "$C_0" "$*"; }
echec() { printf '  %sKO%s   %s\n' "$C_ERR" "$C_0" "$*"; ANOMALIES=$((ANOMALIES + 1)); }
mourir(){ printf '  %sKO%s   %s\n' "$C_ERR" "$C_0" "$*"; exit 1; }
ANOMALIES=0

usage() { sed -n '3,36p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; }

# --- Arguments ---------------------------------------------------------------
CIBLE="staff"
MODE="rebuild"
for arg in "$@"; do
  case "$arg" in
    staff|front|nuxt)           CIBLE="staff" ;;
    client|portail|client-nuxt) CIBLE="client" ;;
    --check|-c)                 MODE="check" ;;
    -h|--help)                  usage; exit 0 ;;
    *) printf '  %sKO%s   argument inconnu : %s\n\n' "$C_ERR" "$C_0" "$arg"; usage; exit 2 ;;
  esac
done

# --- Profil de la cible ------------------------------------------------------
# Les URL viennent du Caddyfile : le front staff est servi sur https://localhost,
# le portail client UNIQUEMENT sur l'edge public (port 81) sous /client.
# PIÈGE CONNU : https://localhost/client/ répond 200 mais sert le front STAFF
# (le bloc staff se termine par `handle { reverse_proxy nuxt:3000 }`).
if [ "$CIBLE" = "staff" ]; then
  SERVICE="nuxt"
  CONTENEUR="ateliermbz-frontend"
  SRC_DIR="frontend"
  APP_URL="${APP_URL:-https://localhost/}"
  CURL_TLS="-k"                        # certificat Caddy local auto-signé
  MARQUEUR="${MARQUEUR:-sidebar-group-label}"
else
  SERVICE="client-nuxt"
  CONTENEUR="ateliermbz-client-portal"
  SRC_DIR="client-frontend"
  APP_URL="${APP_URL:-http://localhost:${PUBLIC_EDGE_PORT:-81}/client/}"
  CURL_TLS=""
  MARQUEUR="${MARQUEUR:-Ajouter une moto}"
fi
ORIGINE="$(printf '%s' "$APP_URL" | sed -E 's#^(https?://[^/]+).*#\1#')"
SORTIE="$SRC_DIR/.output"

# --- Sondes HTTP -------------------------------------------------------------
coquille()  { curl -s $CURL_TLS --max-time 10 "$APP_URL" 2>/dev/null || true; }
code_http() { curl -s $CURL_TLS -o /dev/null -w '%{http_code}' --max-time 10 "$1" 2>/dev/null || echo 000; }
assets()    { printf '%s' "$1" | grep -oE '(/[a-z-]+)?/_nuxt/[A-Za-z0-9_.-]+\.(js|css)' | sort -u; }
# Empreinte = liste triée des assets référencés par la coquille. Les noms de chunks
# sont des hash de CONTENU calculés par Vite ; le buildId (aléatoire à chaque build)
# n'est présent QUE dans .output/server/chunks/nitro/nitro.mjs, jamais dans un chunk
# client — vérifié. Deux builds de sources identiques produisent donc la même
# empreinte : une empreinte qui bouge signifie un vrai changement de code.
empreinte() { assets "$1" | md5sum | cut -c1-12; }
build_id()  { printf '%s' "$1" | grep -oE 'buildId:"[^"]+"' | head -1 | sed -E 's/buildId:"(.*)"/\1/'; }
prefixe()   { printf '%s' "$1" | grep -oE '(/[a-z-]+)?/_nuxt/' | head -1; }

# --- Préflight ---------------------------------------------------------------
docker inspect "$CONTENEUR" >/dev/null 2>&1 \
  || mourir "conteneur $CONTENEUR absent. Lancer d'abord : $COMPOSE_BIN up -d"
[ -d "$SRC_DIR" ] || mourir "répertoire source $RACINE/$SRC_DIR introuvable"

titre "Cible"
info "service    : $SERVICE  (conteneur $CONTENEUR)"
info "sources    : $RACINE/$SRC_DIR"
info "URL testée : $APP_URL"
info "marqueur   : « $MARQUEUR »"

# --- Sources modifiées depuis le dernier build -------------------------------
# Référence : .output/nitro.json, écrit en toute fin de build. On compare des mtime
# via `find -newer` : aucun calcul d'heure, donc insensible au décalage de fuseau
# entre le conteneur (UTC) et l'hôte (CEST).
titre "Sources vs dernier build"
REF="$SORTIE/nitro.json"
NB_NEUFS=0
if [ -f "$REF" ]; then
  NEUFS="$(find "$SRC_DIR" \
      \( -name node_modules -o -name '.nuxt*' -o -name .output -o -name .git \
         -o -name dist -o -name test-results -o -name playwright-report \) -prune -o \
      -type f \( -name '*.vue' -o -name '*.ts' -o -name '*.js' -o -name '*.css' -o -name '*.json' \) \
      -newer "$REF" -print 2>/dev/null || true)"
  NB_NEUFS="$(printf '%s' "$NEUFS" | grep -c . || true)"
  if [ "$NB_NEUFS" -gt 0 ]; then
    attn "$NB_NEUFS fichier(s) au mtime POSTÉRIEUR au dernier build (donc pas dans le bundle servi) :"
    printf '%s\n' "$NEUFS" | head -10 | sed 's/^/         /'
    [ "$NB_NEUFS" -gt 10 ] && info "… et $((NB_NEUFS - 10)) autre(s)" || true
    # Un fichier réécrit à l'identique (formateur, outil) a un mtime neuf sans avoir
    # changé de contenu : git tranche entre « vraiment modifié » et « juste retouché ».
    MODIFS_GIT="$(git status --porcelain -- "$SRC_DIR" 2>/dev/null | grep -vE '\.nuxt|\.output|node_modules' || true)"
    if [ -n "$MODIFS_GIT" ]; then
      info "dont, par rapport à git HEAD :"
      printf '%s\n' "$MODIFS_GIT" | head -10 | sed 's/^/         /'
    else
      info "aucun écart de CONTENU avec git HEAD : ces mtime sont probablement des"
      info "réécritures à l'identique — un rebuild ne changera alors rien."
    fi
  else
    ok "aucune source plus récente que le dernier build"
  fi
else
  attn "$REF absent : aucun build n'a encore abouti dans $SRC_DIR"
fi

# --- État servi AVANT (uniquement si l'on va rebuilder) ----------------------
ID_AVANT=""; EMP_AVANT=""
if [ "$MODE" = "rebuild" ]; then
  HTML_AVANT="$(coquille)"
  if [ -n "$HTML_AVANT" ]; then
    ID_AVANT="$(build_id "$HTML_AVANT")"
    EMP_AVANT="$(empreinte "$HTML_AVANT")"
  fi

  titre "Rebuild + redémarrage de $SERVICE"
  info "chaîne exécutée : npm install → npx nuxt build → node .output/server/index.mjs"
  info "le front répond 502 jusqu'à la fin — c'est normal."
  T0="$(date +%s)"
  RC0="$(docker inspect -f '{{.RestartCount}}' "$CONTENEUR" 2>/dev/null || echo 0)"
  # -t 3 : le serveur Nitro s'arrête proprement sur SIGTERM, inutile d'attendre les
  # 10 s par défaut avant le SIGKILL.
  $COMPOSE_BIN restart -t 3 "$SERVICE"

  DERNIERE=""
  while :; do
    ECOULE=$(( $(date +%s) - T0 ))
    if [ "$ECOULE" -gt "$TIMEOUT" ]; then
      echo; mourir "timeout après ${TIMEOUT}s. Diagnostic : docker logs --tail 50 $CONTENEUR"
    fi
    # `restart: unless-stopped` relance en boucle un build qui échoue : le conteneur
    # n'apparaît donc jamais "exited". C'est RestartCount qui trahit le plantage.
    RC="$(docker inspect -f '{{.RestartCount}}' "$CONTENEUR" 2>/dev/null || echo "$RC0")"
    if [ "$RC" != "$RC0" ]; then
      echo; docker logs --since "$T0" "$CONTENEUR" 2>&1 | tail -25 | sed 's/^/         /'
      mourir "le build a échoué : le conteneur a été relancé par la politique restart"
    fi
    LOGS="$(docker logs --since "$T0" "$CONTENEUR" 2>&1 || true)"
    case "$LOGS" in
      *"Listening on"*)
        [ ! -t 1 ] || printf '\r%-78s\r' " "
        ok "prêt en ${ECOULE}s"; break ;;
      *"npm error"*|*"Could not read package.json"*)
        echo; printf '%s\n' "$LOGS" | tail -15 | sed 's/^/         /'
        mourir "échec de npm install (bind-mount vide, ou registre npm injoignable)" ;;
    esac
    PHASE="$(printf '%s\n' "$LOGS" \
      | grep -aoE 'up to date|added [0-9]+ packages|Building Nuxt for production|Building client|Nitro preset[^,]*|Client built[^,]*|Server built[^,]*|Nuxt Nitro server built' \
      | tail -1 | cut -c1-46 || true)"
    if [ -n "$PHASE" ] && [ "$PHASE" != "$DERNIERE" ]; then
      DERNIERE="$PHASE"
      [ -t 1 ] || printf '  ...  %3ds  %s\n' "$ECOULE" "$DERNIERE"
    fi
    [ ! -t 1 ] || printf '\r  %s...  %3ds  %-46s%s' "$C_DIM" "$ECOULE" "${DERNIERE:-démarrage}" "$C_0"
    sleep 2
  done
  DUREE="$ECOULE"
else
  DUREE=""
fi

# --- Vérifications bout-en-bout ---------------------------------------------
titre "Ce qui est servi maintenant"
CODE="$(code_http "$APP_URL")"
if [ "$CODE" = "200" ]; then ok "$APP_URL → HTTP $CODE"; else echec "$APP_URL → HTTP $CODE"; fi

HTML_APRES="$(coquille)"
ID_APRES="$(build_id "$HTML_APRES")"
EMP_APRES="$(empreinte "$HTML_APRES")"
info "buildId   : ${ID_APRES:-<absent>}"
info "empreinte : $EMP_APRES"
info "assets référencés par la coquille — ce sont EXACTEMENT les fichiers que"
info "l'onglet Réseau du navigateur doit montrer ; d'autres noms = cache client :"
assets "$HTML_APRES" | sed 's/^/         /'

if [ "$MODE" = "rebuild" ]; then
  if [ -n "$ID_AVANT" ] && [ "$ID_AVANT" = "$ID_APRES" ]; then
    echec "buildId INCHANGÉ : le redémarrage n'a pas produit de nouveau build"
  else
    ok "buildId renouvelé → un nouveau build a bien été produit ET est servi"
  fi
  if [ -n "$EMP_AVANT" ] && [ "$EMP_AVANT" = "$EMP_APRES" ]; then
    info "empreinte identique ($EMP_APRES) → le contenu du bundle n'a pas changé :"
    info "le serveur servait DÉJÀ la bonne version. Si l'écran n'a pas bougé, la"
    info "cause est côté navigateur (voir plus bas)."
  elif [ -n "$EMP_AVANT" ]; then
    ok "empreinte modifiée ($EMP_AVANT → $EMP_APRES) : le bundle servi a réellement changé"
  fi
fi

# Preuve bout-en-bout : le marqueur est-il dans un chunk réellement téléchargeable ?
FICHIERS="$(docker exec -e MARQ="$MARQUEUR" "$CONTENEUR" \
  sh -c 'cd /app/.output/public/_nuxt 2>/dev/null && grep -rlF -- "$MARQ" . 2>/dev/null || true' \
  | sed 's#^\./##' | tr -d '\r' || true)"
if [ -z "$FICHIERS" ]; then
  echec "marqueur « $MARQUEUR » absent du bundle construit (/app/.output/public/_nuxt)"
else
  PREM="$(printf '%s\n' "$FICHIERS" | head -1)"
  PREF="$(prefixe "$HTML_APRES")"; PREF="${PREF:-/_nuxt/}"
  N="$(curl -s $CURL_TLS --max-time 15 "${ORIGINE}${PREF}${PREM}" 2>/dev/null | grep -cF -- "$MARQUEUR" || true)"
  if [ "${N:-0}" -gt 0 ]; then
    ok "marqueur « $MARQUEUR » téléchargé par HTTP depuis ${PREF}${PREM}"
  else
    echec "marqueur sur disque mais introuvable via ${ORIGINE}${PREF}${PREM}"
  fi
fi

# Homogénéité : `nuxt build` purge .output/public/_nuxt, tous les fichiers doivent
# porter le même horodatage. Plusieurs dates = mélange de builds.
# Lecture faite sur l'HÔTE : .output est sur le bind-mount, et le busybox du
# conteneur node:20-alpine ne connaît pas `ls --time-style`.
if [ -d "$SORTIE/public/_nuxt" ]; then
  DATES="$(find "$SORTIE/public/_nuxt" -type f -printf '%TY-%Tm-%Td %TH:%TM\n' 2>/dev/null | sort -u || true)"
  NB_DATES="$(printf '%s' "$DATES" | grep -c . || true)"
  NB_FIC="$(find "$SORTIE/public/_nuxt" -type f 2>/dev/null | grep -c . || true)"
  if [ "${NB_DATES:-0}" -eq 1 ]; then
    ok "bundle homogène : $NB_FIC fichiers, tous datés du $DATES — aucun résidu d'ancien build"
  elif [ "${NB_DATES:-0}" -eq 0 ]; then
    attn "$SORTIE/public/_nuxt illisible depuis l'hôte"
  else
    attn "$NB_DATES horodatages différents dans _nuxt : $(printf '%s' "$DATES" | tr '\n' '|')"
  fi
fi

# --- Santé de l'API (le front peut être neuf et l'appli inutilisable) --------
if [ "$CIBLE" = "staff" ]; then
  API="$(code_http "${ORIGINE}/api/clauses-legales")"
  if [ "$API" = "404" ] || [ "$API" = "000" ]; then
    attn "API backend → HTTP $API. Le bind ./backend:/app du service php est probablement"
    attn "rompu (indirection Docker Desktop périmée) : /app ne contient pas le code Symfony,"
    attn "donc le login échoue quel que soit l'état du front."
    attn "Vérifier : docker exec ateliermbz-php-1 ls /app"
    attn "Remède   : $COMPOSE_BIN up -d --force-recreate php worker"
  else
    ok "API backend → HTTP $API"
  fi
fi

# --- Ce qu'il reste à faire côté navigateur ---------------------------------
titre "Côté navigateur"
info "Ouvrir EXACTEMENT : $APP_URL"
if [ "$CIBLE" = "staff" ]; then
  info "  PAS http://127.0.0.1:3000 · PAS http://localhost:81 · PAS https://localhost/client/"
  info "  (https://localhost/client/ répond 200 mais sert le front STAFF — piège du Caddyfile)"
else
  info "  PAS https://localhost/client/ : cette URL sert le front STAFF, pas le portail."
fi
if [ "$CIBLE" = "staff" ]; then
  # frontend/nuxt.config.ts : experimental.appManifest:false + alias '#app-manifest'
  # vers un stub vide. Le hook `app:manifest:update` de Nuxt (celui qui recharge
  # l'appli quand un nouveau build est détecté) n'est donc JAMAIS déclenché.
  info "Aucune détection de nouveau build : nuxt.config.ts pose experimental.appManifest:false"
  info "et route '#app-manifest' vers un stub vide. Un onglet ouvert avant le rebuild garde"
  info "l'ancienne appli indéfiniment, sans le moindre signal."
else
  info "Le portail est en SPA (ssr:false) : un onglet déjà ouvert ne recharge pas"
  info "l'application de lui-même après un rebuild."
fi
info "1. Fermer l'onglet, ou recharger de force (Ctrl+Shift+R)"
info "2. Appli installée en PWA : fermer complètement la fenêtre standalone"
info "3. DevTools > Application > Service Workers > Unregister, puis Clear site data"
if [ "$CIBLE" = "staff" ]; then
  info "4. L'UI restaure son état depuis localStorage : paddock-theme,"
  info "   paddock:stat-onglet, paddock:planning-filters, paddock:explorateur-vues."
  info "   La refonte peut rouvrir sur l'ancien onglet/filtre et sembler inchangée."
  info "5. Marqueur visible à l'écran : les libellés de groupe de la barre latérale."
  info "   « Cockpit réseau » n'apparaît QUE pour un compte ROLE_SERVICE_CLIENT."
fi

[ -n "$DUREE" ] && printf '\n%sBoucle complète : %ss.%s\n' "$C_B" "$DUREE" "$C_0" || true
if [ "$ANOMALIES" -gt 0 ]; then
  printf '%s%s anomalie(s) ci-dessus.%s\n' "$C_ERR" "$ANOMALIES" "$C_0"
  exit 1
fi
exit 0
