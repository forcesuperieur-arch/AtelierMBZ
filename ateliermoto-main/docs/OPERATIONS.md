# Exploitation locale — Atelier Moto Pro

Mise à jour : **2026-04-07**

## Démarrage standard

Depuis `ateliermoto-main/` :

```bash
docker compose up -d --build
docker compose ps
docker compose logs -f backend
```

## Vérification rapide

```bash
curl http://localhost:8000/api/health
curl http://localhost/
```

Ports attendus :
- `80` : proxy HTTP (`caddy`)
- `443` : proxy HTTPS (`caddy`)
- `8000` : API FastAPI
- `5432` : PostgreSQL

---

## Bootstrap BDD depuis Git

La persistance locale se fait dans le volume Docker **`postgres_data`**.

Le **versionnage Git** de la BDD repose sur :
- `backend/alembic/versions/` pour le **schéma**
- `backend/seed_parametres.py` et `backend/seed.py` pour les **données de référence**

### Procédure recommandée sur un poste vierge

```bash
cd ateliermoto-main
docker compose up -d
docker compose exec backend alembic upgrade head
docker compose exec backend python seed_parametres.py
docker compose exec backend python seed.py
```

> Les dumps SQL restent des artefacts d'exploitation et **ne doivent pas servir de versionnement Git principal**.

---

## Sauvegarde BDD

Les dumps sont stockés dans le dossier racine du workspace : `../backups/`.

```bash
cd ateliermoto-main
./scripts/db-backup.sh
./scripts/db-backup.sh backup_manual_YYYYMMDD.sql
```

## Restauration BDD

```bash
cd ateliermoto-main
./scripts/db-restore.sh backup_YYYYMMDD_HHMMSS.sql
```

Le script :
- cherche automatiquement le bon dossier `backups/`
- échoue immédiatement sur erreur SQL (`ON_ERROR_STOP=1`)
- laisse la BDD de production/dev locale hors Git

---

## Rollback conseillé

1. faire un dump de sécurité avant restauration
2. restaurer le dump cible
3. vérifier :

```bash
docker compose ps
curl http://localhost:8000/api/health
docker compose exec -T db psql -U atelier -d atelier_moto -c "SELECT COUNT(*) FROM users;"
```

---

## Bonnes pratiques Git / BDD

### À committer
- migrations Alembic
- modèles SQLAlchemy
- scripts de seed
- fichiers de données de référence dans `backend/data/`

### À ne pas committer
- dumps `backups/*.sql`
- volume `postgres_data`
- fichiers secrets et données locales de travail

---

## Références utiles

- documentation technique : `docs/TECHNICAL.md`
- plan de refactor : `docs/PLAN_REFACTOR_TECHNIQUE.md`
- scripts : `scripts/db-backup.sh`, `scripts/db-restore.sh`
