# Atelier Moto Pro

Application de gestion d'atelier moto **multi-atelier** avec **SPA Vanilla JS**, **API FastAPI** et **PostgreSQL**.

## 🚀 Démarrage rapide

### Prérequis

- [Docker](https://docs.docker.com/get-docker/) et Docker Compose
- Git

### Installation locale

```bash
# 1. Cloner le dépôt
git clone git@github.com:forcesuperieur-arch/ateliermoto.git
cd ateliermoto-main

# 2. Configurer l'environnement
cp backend/.env.example backend/.env

# 3. Démarrer les services
docker compose up -d --build

# 4. Appliquer le schéma versionné et injecter les données de référence
docker compose exec backend alembic upgrade head
docker compose exec backend python seed_parametres.py
# optionnel : catalogue / données complémentaires
docker compose exec backend python seed.py

# 5. Vérifier
docker compose logs -f backend
```

### Accès

| URL | Description |
|-----|-------------|
| http://localhost:8000 | Application (SPA) |
| http://localhost:8000/api/health | Health check API |
| http://localhost:8000/docs | Swagger API |

> **Premier lancement** : configurez `ADMIN_USERNAME` et `ADMIN_PASSWORD` dans `backend/.env` si vous souhaitez créer un compte administrateur initial.

---

## 🏗️ Architecture actuelle

```text
ateliermoto-main/
├── backend/
│   ├── main.py                  # Composition root FastAPI
│   ├── models.py                # Modèles SQLAlchemy
│   ├── auth.py                  # Helpers auth / JWT
│   ├── config_api.py            # Configuration atelier
│   ├── facturation_api.py       # Facturation / encaissement / PDF
│   ├── statistiques.py          # Dashboard et KPI
│   ├── tarifs_api.py            # Tarifs legacy / créneaux
│   ├── alembic/versions/        # Schéma versionné dans Git
│   ├── routes/                  # Routers métier modulaires
│   ├── services/                # PDF, startup, runtime migrations
│   └── tests/                   # Tests backend
├── frontend/
│   ├── index.html               # SPA principale
│   ├── app.js                   # Pont global / compatibilité UI
│   ├── api.js                   # Helpers HTTP + ouverture PDF protégée
│   ├── utils.js                 # Helpers communs
│   ├── theme.css                # Design system
│   └── modules/                 # Modules métier (RDV, planning, OR, admin...)
├── docs/                        # Documentation à jour
├── scripts/                     # Backup / restore PostgreSQL
└── docker-compose.yml
```

### Stack technique

| Composant | Technologie |
|-----------|-------------|
| Backend | FastAPI + SQLAlchemy (Python 3.11) |
| Frontend | HTML5 + CSS + JavaScript vanilla modulaire |
| Base de données | PostgreSQL 15 |
| Auth | JWT + cookies HttpOnly + bcrypt |
| PDF | ReportLab |
| Infra | Docker Compose + Caddy |

---

## ✨ Fonctionnalités en place

- **Prise de RDV** publique et interne avec calcul des créneaux par durée
- **Planning atelier** semaine/jour avec assignation pont / mécanicien
- **Réception enrichie** : kilométrage, état véhicule, priorité, niveau carburant, annotations carrosserie, photos, signature client
- **Ordres de réparation** : aperçu “master”, impression navigateur, PDF sécurisé, OR complémentaires
- **Travaux supplémentaires** : demande, validation, rattachement au RDV courant
- **Base moto** et **autocomplete amélioré** des modèles
- **Facturation / devis / paiements** avec PDF
- **Stock / fournisseurs / commandes** côté backend
- **Dashboard & statistiques** opérationnelles
- **RBAC** par rôles et permissions granulaires
- **Gestion clients / véhicules** avec historique atelier

---

## 🗄️ Base de données et Git

La base **ne doit pas être commitée sous forme de dump vivant**. La stratégie retenue pour Git est :

### ✅ Ce qui est versionné

- `backend/alembic/versions/` → **schéma et migrations**
- `backend/seed.py` → **catalogue et données métier de référence**
- `backend/seed_parametres.py` → **paramètres initiaux atelier**
- `backend/data/` → fichiers de données sources utilisés pour le seed

### ❌ Ce qui ne doit pas être commitée

- le volume Docker PostgreSQL `postgres_data`
- les dumps de travail dans `backups/*.sql`
- les données locales de développement ou de production

### Recréer la base depuis Git

```bash
cd ateliermoto-main
docker compose up -d
docker compose exec backend alembic upgrade head
docker compose exec backend python seed_parametres.py
docker compose exec backend python seed.py
```

---

## 👨‍💻 Développement

### Commandes utiles

```bash
# Redémarrer le backend
docker compose restart backend

# Logs backend
docker compose logs -f backend

# Rebuild si dépendances modifiées
docker compose build backend && docker compose up -d backend

# Shell dans le conteneur backend
docker compose exec backend bash

# Sauvegarde PostgreSQL (dumps stockés dans ../backups/)
./scripts/db-backup.sh

# Restauration d'un dump
./scripts/db-restore.sh backup_YYYYMMDD_HHMMSS.sql
```

### Tests UI Playwright

```bash
# 1. Installer les dépendances UI
npm install

# 2. Installer Chromium pour Playwright
npm run test:ui:install

# 3. Lancer l'appli (backend déjà démarré sur http://127.0.0.1:8000)
# puis exécuter les smoke/E2E tests
PLAYWRIGHT_BASE_URL=http://127.0.0.1:8000 \
E2E_USERNAME=admin \
E2E_PASSWORD=motdepasse \
npm run test:ui
```

> Les tests publics tournent sans authentification ; le scénario `planning -> fiche client` utilise les variables `E2E_USERNAME` et `E2E_PASSWORD`.

### Notes de structure

- **Backend** : `main.py` sert désormais de composition root ; la logique métier vit dans `backend/routes/` et `backend/services/`.
- **Frontend** : la SPA est **modulaire** (`frontend/modules/*`) ; `app.js` sert surtout de couche de compatibilité / dispatch global.
- **Pages legacy** : quelques fichiers HTML isolés restent servis via `backend/routes/frontend_pages.py` pour compatibilité, en parallèle de la SPA principale.

### Variables d'environnement principales

| Variable | Description | Défaut |
|----------|-------------|--------|
| `SECRET_KEY` | Clé JWT | auto / `.env` |
| `DATABASE_URL` | URL PostgreSQL | `postgresql://atelier:atelier@db:5432/atelier_moto` |
| `CORS_ORIGINS` | Origines autorisées | local dev |
| `ADMIN_USERNAME` | Admin initial | optionnel |
| `ADMIN_PASSWORD` | Mot de passe admin initial | optionnel |
| `COOKIE_SECURE` | Sécurisation cookies | selon env |
| `COOKIE_SAMESITE` | Politique SameSite | `lax` par défaut |

---

## 📚 Documentation

- **[`docs/TECHNICAL.md`](docs/TECHNICAL.md)** — référence technique canonique
- **[`docs/GUIDE_UTILISATEUR.md`](docs/GUIDE_UTILISATEUR.md)** — guide métier et parcours utilisateur
- **[`docs/GUIDE_INSTALLATION_PREPROD.md`](docs/GUIDE_INSTALLATION_PREPROD.md)** — installation serveur préprod prête à lancer
- **[`docs/OPERATIONS.md`](docs/OPERATIONS.md)** — exploitation locale, backup/restore, bootstrap BDD
- **[`docs/PLAN_REFACTOR_TECHNIQUE.md`](docs/PLAN_REFACTOR_TECHNIQUE.md)** — état du refactor et dette restante
