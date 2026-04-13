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

## Alignement runtime et refactor backend

Le deplacement de `backend/` vers `app/` impose les ajustements suivants:
- les secrets et fichiers runtime sont ignores dans `.gitignore` via `app/.env`, `app/.secret_key`, `app/signatures/` et `app/data/logos/`
- le calcul des creneaux par duree a ete extrait dans `app/services/slot_service.py` pour alleger `app/tarifs_api.py`
- la resolution du token d'acces et de l'utilisateur courant a ete factorisee dans `app/auth.py` pour supprimer la duplication entre acces obligatoire et acces optionnel

