# Architecture du Projet

## Structure principale
- `app/`: code Python (API FastAPI, routes, schemas, services, migrations, tests backend).
- `frontend/`: interface web (HTML/CSS/JS) avec points d'entree canoniques `index.html`, `style.css`, `script.js`.
- `docs/`: documentation vivante du projet.
- `data/`: fichiers locaux non applicatifs.
- `Dockerfile`: image backend.
- `docker-compose.yml`: orchestration locale (db, init, backend, caddy, mailhog).
- `requirements.txt`: dependances Python backend.
- `.env`: configuration sensible locale.
- `.gitignore`: exclusions Git.

## Demarrage
- `docker compose up -d --build backend`

## Notes
- Le service backend execute `uvicorn main:app` avec le code charge depuis `app/` dans l'image.
- Compatibilite frontend conservee: `theme.css` et `app.js` restent disponibles comme alias.
