# Atelier Moto Pro

Application SPA de gestion d'atelier moto multi-site.

## 🚀 Démarrage rapide

### Prérequis

- [Docker](https://docs.docker.com/get-docker/) et Docker Compose
- Git

### Installation

```bash
# 1. Cloner le dépôt
git clone git@github.com:forcesuperieur-arch/ateliermoto.git
cd ateliermoto

# 2. Configurer l'environnement
cp backend/.env.example backend/.env
# Éditer backend/.env si nécessaire (les valeurs par défaut fonctionnent en local)

# 3. Lancer l'application
docker compose up -d

# 4. Vérifier que tout tourne
docker compose logs -f backend
```

### Accès

| URL | Description |
|-----|-------------|
| http://localhost:8000 | Application (SPA) |
| http://localhost:8000/api/health | Health check API |
| http://localhost:8000/docs | Swagger API (auto-généré) |

> **Premier lancement** : configurez `ADMIN_USERNAME` et `ADMIN_PASSWORD` dans `backend/.env` pour créer le compte admin initial. Voir `backend/.env.example` pour les options.

## 🏗️ Architecture

```
ateliermoto/
├── backend/                # API FastAPI (Python)
│   ├── main.py             # Routes principales (auth, users, etc.)
│   ├── models.py           # Modèles SQLAlchemy
│   ├── config_api.py       # Routes /api/config/*
│   ├── statistiques.py     # Routes /api/statistiques/*
│   ├── facturation_api.py  # Routes facturation & factures
│   ├── tarifs_api.py       # Routes tarifs & créneaux
│   ├── routes/
│   │   ├── clients.py      # Routes /api/clients/*
│   │   ├── rendez_vous.py  # Routes /api/rendez-vous/*
│   │   └── workshop.py     # Routes ponts, mécaniciens, planning
│   ├── Dockerfile
│   ├── requirements.txt
│   └── .env.example
├── frontend/               # SPA Vanilla JS (pas de build)
│   ├── index.html
│   ├── app.js
│   ├── api.js
│   ├── utils.js            # Helpers (escapeHtml, showToast, formatDate)
│   └── theme.css           # Variables CSS et styles Motoblouz
├── Caddyfile/              # Config reverse proxy (production)
├── scripts/                # Scripts utilitaires
│   ├── db-backup.sh        # Sauvegarde PostgreSQL
│   └── db-restore.sh       # Restauration PostgreSQL
├── docs/                   # Documentation
│   ├── TECHNICAL.md        # Doc technique (92 routes API)
│   └── GUIDE_UTILISATEUR.md # Guide utilisateur complet
└── docker-compose.yml
```

## 🔧 Stack technique

| Composant | Technologie |
|-----------|-------------|
| Backend | FastAPI + SQLAlchemy (Python 3.11) |
| Frontend | HTML5 + CSS + Vanilla JS (SPA, aucun build) |
| Base de données | PostgreSQL 15 |
| Auth | JWT (cookies HttpOnly) + bcrypt |
| PDF | ReportLab |
| Conteneurisation | Docker + Docker Compose |
| Reverse proxy | Caddy (production) |

## ✨ Fonctionnalités

- **Multi-atelier** — Gestion multi-site avec isolation des données
- **Prise de RDV** — Wizard 4 étapes (véhicule → prestations → créneau → confirmation)
- **Planning semaine** — Grille visuelle drag & drop avec filtres mécaniciens
- **Ordres de réparation** — Workflow 5 étapes avec PDF et signature
- **Espace mécanicien** — Interface dédiée avec chrono live et checkup 10 points
- **Suivi live** — Monitoring temps réel des interventions
- **Facturation** — Devis, factures PDF, encaissement multi-modes
- **Statistiques** — Dashboard KPI, CA, productivité, clients fidèles
- **RBAC** — Rôles personnalisables avec 13 permissions granulaires
- **Gestion clients** — Fiches complètes avec historique et véhicules

## 👨‍💻 Développement

### Commandes courantes

```bash
# Redémarrer le backend après modification
docker compose restart backend

# Voir les logs en temps réel
docker compose logs -f backend

# Reconstruire après modification des dépendances
docker compose build backend && docker compose up -d backend

# Accéder au shell du conteneur backend
docker exec -it atelier-backend bash

# Sauvegarde BDD (fichiers stockés dans `../backups/` à la racine du workspace)
./scripts/db-backup.sh

# Restauration BDD
./scripts/db-restore.sh backup_YYYYMMDD_HHMMSS.sql
```

### Structure du code

- **Backend** : Le fichier `main.py` est le point d'entrée (~4700 lignes). Les routes sont réparties dans `routes/`, `config_api.py`, `statistiques.py`, `facturation_api.py`, `tarifs_api.py`.
- **Frontend** : SPA monolithique dans `app.js` (~7000 lignes) + `utils.js` (helpers XSS/toast) + `theme.css` (design system). Pas de framework, pas de build step. Modifié → rafraîchir le navigateur.
- **Base de données** : PostgreSQL dans un Docker volume (`postgres_data`). Les tables sont créées automatiquement au démarrage via SQLAlchemy.

### Variables d'environnement

Voir `backend/.env.example` pour la liste complète. Les principales :

| Variable | Description | Défaut |
|----------|-------------|--------|
| `SECRET_KEY` | Clé JWT (auto-générée si absente) | Auto |
| `DATABASE_URL` | URL PostgreSQL | `postgresql://atelier:atelier@db:5432/atelier_moto` |
| `CORS_ORIGINS` | Domaines autorisés | `http://localhost:3000,http://localhost:8080` |
| `ADMIN_USERNAME` | Compte admin initial (optionnel) | — |
| `ADMIN_PASSWORD` | Mot de passe admin initial (optionnel) | — |

## 📚 Documentation

- **[Documentation technique](docs/TECHNICAL.md)** — Architecture, 92 routes API, modèles, auth, RBAC
- **[Guide utilisateur](docs/GUIDE_UTILISATEUR.md)** — Manuel d'utilisation complet de l'application
