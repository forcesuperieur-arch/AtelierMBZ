# Historique du Projet

## 2026-04-13
- Reorganisation de la base du projet vers une structure `app/` pour le code Python.
- Ajout d'un `Dockerfile` a la racine et adaptation de `docker-compose.yml`.
- Ajout d'un `requirements.txt` a la racine.
- Creation du dossier racine `data/` pour les fichiers locaux.
- Reorganisation frontend complete: fichiers canoniques `frontend/style.css` et `frontend/script.js`, page principale mise a jour, alias de compatibilite conserves (`theme.css`, `app.js`).
- [V.2026.04.13-1] | [2026-04-13] | [Hygiene Code] | [Ajout du script `scripts/check_code_hygiene.sh` pour verifier la limite de 200 lignes/fichier et detecter des secrets/mots de passe en dur; documentation ajoutee dans README, docs/technical.md et docs/user_guide.md.]
- [V.2026.04.13-2] | [2026-04-13] | [Runtime And Refactor Hardening] | [Mise a jour de `.gitignore` pour la structure `app/`, retrait des fichiers runtime/secrets du suivi Git, extraction du calcul des creneaux dans `app/services/slot_service.py`, factorisation de `app/auth.py` et passage sous 200 lignes de `app/auth.py`, `app/tarifs_api.py`, `app/services/pricing_rules.py`, `app/services/relance_service.py` et `app/seed_parametres.py`.]
