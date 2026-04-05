# Exploitation locale — Atelier Moto Pro

Mise à jour : **2026-04-05**

## Démarrage

Depuis `ateliermoto-main/` :

```bash
docker compose up -d
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

## Rollback conseillé

1. faire un dump de sécurité avant restauration
2. restaurer le dump cible
3. vérifier :

```bash
docker compose ps
curl http://localhost:8000/api/health
docker compose exec -T db psql -U atelier -d atelier_moto -c "SELECT COUNT(*) FROM users;"
```

## Référence utile

- plan technique : `docs/PLAN_REFACTOR_TECHNIQUE.md`
- scripts : `scripts/db-backup.sh`, `scripts/db-restore.sh`
