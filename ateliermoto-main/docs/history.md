# Historique du Projet

## 2026-04-13
- Reorganisation de la base du projet vers une structure `app/` pour le code Python.
- Ajout d'un `Dockerfile` a la racine et adaptation de `docker-compose.yml`.
- Ajout d'un `requirements.txt` a la racine.
- Creation du dossier racine `data/` pour les fichiers locaux.
- Reorganisation frontend complete: fichiers canoniques `frontend/style.css` et `frontend/script.js`, page principale mise a jour, alias de compatibilite conserves (`theme.css`, `app.js`).
- [V.2026.04.13-1] | [2026-04-13] | [Hygiene Code] | [Ajout du script `scripts/check_code_hygiene.sh` pour verifier la limite de 200 lignes/fichier et detecter des secrets/mots de passe en dur; documentation ajoutee dans README, docs/technical.md et docs/user_guide.md.]
