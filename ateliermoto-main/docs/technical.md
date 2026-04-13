# Documentation Technique

## Controle hygiene code

Un script de verification est disponible: `scripts/check_code_hygiene.sh`.

Objectif:
- detecter les fichiers source depassant la limite projet de 200 lignes
- detecter des motifs simples de secrets/mots de passe en dur dans `app/`, `frontend/` et `scripts/` (hors tests)

Execution:

```bash
./scripts/check_code_hygiene.sh
```

Comportement:
- code retour `0` si aucune violation
- code retour `1` si au moins une violation est detectee
- limite configurable via `MAX_FILE_LINES` (par defaut `200`)

