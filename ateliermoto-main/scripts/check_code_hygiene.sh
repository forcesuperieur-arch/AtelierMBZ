#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MAX_LINES="${MAX_FILE_LINES:-200}"

# Only check source-like files and skip generated/vendor/runtime artifacts.
LINE_PRUNE=(
  -path "$ROOT_DIR/.git" -o
  -path "$ROOT_DIR/node_modules" -o
  -path "$ROOT_DIR/backups" -o
  -path "$ROOT_DIR/playwright-report" -o
  -path "$ROOT_DIR/test-results" -o
  -path "$ROOT_DIR/archive" -o
  -path "$ROOT_DIR/.venv" -o
  -path "$ROOT_DIR/__pycache__"
)

SOURCE_NAME_FILTER=(
  -name '*.py' -o -name '*.js' -o -name '*.mjs' -o -name '*.html' -o -name '*.css'
)

mapfile -d '' source_files < <(
  find "$ROOT_DIR" \( "${LINE_PRUNE[@]}" \) -prune -o -type f \( "${SOURCE_NAME_FILTER[@]}" \) -print0
)

line_failures=()
for file in "${source_files[@]}"; do
  count="$(wc -l < "$file")"
  if [[ "$count" -gt "$MAX_LINES" ]]; then
    rel="${file#"$ROOT_DIR"/}"
    line_failures+=("$count:$rel")
  fi
done

secret_failures=()
# Basic hardcoded-secret detector for non-test source files.
# Flags assignments like PASSWORD="...", SECRET_KEY='...'.
while IFS= read -r -d '' file; do
  rel="${file#"$ROOT_DIR"/}"
  if grep -nEi '(^|[[:space:]])(password|secret(_key)?|api[_-]?key|token)[[:space:]]*[:=][[:space:]]*["\x27][^"\x27]+["\x27]' "$file" >/tmp/hygiene_matches.txt; then
    while IFS= read -r hit; do
      secret_failures+=("$rel:$hit")
    done < /tmp/hygiene_matches.txt
  fi
done < <(
  find "$ROOT_DIR/app" "$ROOT_DIR/frontend" "$ROOT_DIR/scripts" -type f \( -name '*.py' -o -name '*.js' -o -name '*.mjs' \) \
    ! -path '*/tests/*' ! -name '*.test.*' ! -name '*.spec.*' -print0
)

rm -f /tmp/hygiene_matches.txt

exit_code=0

echo "== Controle hygiene code =="
echo "Regle taille max: ${MAX_LINES} lignes"

if [[ ${#line_failures[@]} -gt 0 ]]; then
  echo
  echo "[ERREUR] Fichiers > ${MAX_LINES} lignes :"
  printf '%s\n' "${line_failures[@]}" | sort -nr
  exit_code=1
else
  echo "[OK] Aucun fichier source au-dessus de ${MAX_LINES} lignes."
fi

if [[ ${#secret_failures[@]} -gt 0 ]]; then
  echo
  echo "[ERREUR] Potentiels secrets/mots de passe en dur detectes :"
  printf '%s\n' "${secret_failures[@]}"
  exit_code=1
else
  echo "[OK] Aucun secret evident detecte en dur dans app/frontend/scripts."
fi

if [[ "$exit_code" -ne 0 ]]; then
  echo
  echo "Echec: corriger les points ci-dessus puis relancer scripts/check_code_hygiene.sh"
else
  echo
  echo "Succes: hygiene code conforme a la regle projet."
fi

exit "$exit_code"
