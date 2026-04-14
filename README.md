# Atelier Moto Pro v2

Application de gestion d'atelier moto — Backend Symfony 7.2 + Frontend Nuxt 3.

## Stack technique

| Composant | Technologie |
|-----------|------------|
| Backend | Symfony 7.2, PHP 8.3, API Platform 4.1 |
| ORM | Doctrine ORM 3.6 |
| Auth | LexikJWT (HttpOnly cookies) |
| Frontend | Nuxt 3.16, Nuxt UI v3, Pinia |
| Base de données | PostgreSQL 15 |
| Async | Symfony Messenger |
| PDF | DomPDF + Twig templates |
| Reverse proxy | Caddy 2 |
| Email (dev) | MailHog |
| Conteneurisation | Docker Compose (6 services) |

## Architecture

```
atelier-v2/
├── backend/                # Symfony 7.2
│   ├── src/
│   │   ├── Entity/         # 42 entités Doctrine
│   │   ├── Controller/     # 8 contrôleurs API
│   │   ├── Service/        # 4 services métier
│   │   ├── Security/       # JWT auth + voters + tenant filter
│   │   ├── Command/        # CLI (create-admin, seed)
│   │   ├── Message/        # Messages async (email, PDF)
│   │   └── MessageHandler/ # Handlers Messenger
│   ├── config/             # Symfony config (security, workflow, doctrine)
│   └── templates/pdf/      # Templates Twig pour PDF
├── frontend/               # Nuxt 3 SPA
│   ├── pages/              # 20+ pages (auto-routing)
│   ├── components/         # Composants réutilisables
│   ├── composables/        # useApi, useAuth
│   ├── stores/             # Pinia stores (auth, rdv, billing, stock)
│   ├── layouts/            # default (sidebar) + public
│   └── middleware/          # Auth middleware global
├── docker-compose.yml      # 6 services
├── Dockerfile.backend      # PHP 8.3 FPM Alpine
├── Dockerfile.frontend     # Node 20 Alpine
└── Caddyfile               # Reverse proxy config
```

## Démarrage rapide

### Prérequis
- Docker + Docker Compose v2
- (ou) PHP 8.3 + Composer + Node 20 + PostgreSQL 15

### Avec Docker

```bash
cd atelier-v2

# Démarrer tous les services
docker compose up -d

# Créer le schéma + admin
docker compose exec php php bin/console doctrine:schema:create
docker compose exec php php bin/console app:create-admin
docker compose exec php php bin/console app:seed

# Accès
# App:    http://localhost
# API:    http://localhost/api
# Mail:   http://localhost:8025
```

### Sans Docker (développement)

```bash
# Backend
cd backend
composer install
php bin/console doctrine:schema:create
php bin/console app:create-admin
php bin/console app:seed
symfony server:start

# Frontend (autre terminal)
cd frontend
npm install
npm run dev
```

### Windows

```batch
REM Double-cliquer sur :
start.bat        REM Démarrer les services
stop.bat         REM Arrêter les services
reset.bat        REM Tout réinitialiser
seed-demo.bat    REM Injecter les données de démo
```

## Modules fonctionnels

- **Dashboard** — KPIs, RDV du jour, alertes stock
- **Rendez-vous** — CRUD, workflow 10 états, transitions, historique
- **Planning** — Vue hebdo par pont, drag & drop ready
- **Clients** — Fiche client, véhicules, historique RDV
- **Atelier** — Vue ponts en temps réel (libre/occupé)
- **Ordres de réparation** — OR liés aux RDV, rapports technicien
- **Devis** — Création, lignes MO/pièces, export PDF
- **Facturation** — Factures auto-numérotées, paiements, export PDF
- **Stock** — Pièces détachées, alertes seuil, fournisseurs
- **Catalogue motos** — Catégories, modèles, specs techniques
- **Espace mécanicien** — Interventions du jour, démarrer/terminer
- **Administration** — Utilisateurs, rôles, config atelier, horaires, absences, audit
- **Réservation publique** — Booking client sans auth, suivi par token

## Workflow RDV

```
en_attente → réserver → reserve → confirmer → confirme
→ reception → en_cours → termine → restitue → facture → paye
                                            ↗
en_attente/reserve/confirme → annuler → annule
```

## Sécurité

- JWT HS256 (access 15min + refresh 7j) via HttpOnly cookies
- Tenant isolation par `atelier_id` (Doctrine SQL Filter)
- Rôles hiérarchiques : SUPER_ADMIN > ADMIN > USER
- Sous-rôles : RECEPTIONNAIRE, MECANICIEN, COMPTABLE
- Permissions granulaires via `role_permissions`
- AtelierVoter : vérifie appartenance à l'atelier
- Audit trail automatique

## API

L'API est documentée automatiquement via API Platform :
- Swagger UI : `http://localhost/api`
- JSON-LD : `http://localhost/api/docs.jsonld`

## Variables d'environnement

| Variable | Default | Description |
|----------|---------|-------------|
| `POSTGRES_DB` | `atelier_moto` | Nom de la base |
| `POSTGRES_USER` | `Standards techniques imposés par l’IT :
- backend Symfony
- frontend Nuxt
- architecture séparée frontend / backend
- worker pour les traitements longs, lourds ou asynchrones lorsque nécessaire
- conteneurisation Docker
- environnement de test prévu pour Windows 11 avec Docker Desktop et WSL 2
- génération obligatoire des scripts Windows suivants à la racine du projet :
start.bat, stop.bat, reset.bat
- si pertinent, génération complémentaire de seed-demo.bat pour charger des
données de démonstration
- les scripts .bat doivent permettre à un utilisateur non développeur de
lancer, arrêter et réinitialiser l’application sans taper de commande
complexe
- le lancement de l’application doit être documenté dans un README très court
orienté utilisateur métier
- configuration uniquement par variables d’environnement
- code structuré, maintenable et découpé par responsabilité
- API proprement exposée pour le frontend
- gestion claire des erreurs
- aucun secret ou token en dur dans le code
- dépendances limitées au nécessaire
- solution testable localement de manière simple
- solution conçue pour pouvoir être hébergeable ensuite dans un SI
d’entreprise
- privilégier la simplicité, la robustesse et la lisibilité plutôt qu’une
architecture complexe
` | Utilisateur PostgreSQL |
| `POSTGRES_PASSWORD` | `atelier` | Mot de passe PostgreSQL |
| `APP_ENV` | `dev` | Environnement Symfony |
| `APP_SECRET` | `change_me_in_production` | Secret Symfony |
| `JWT_SECRET_KEY` | — | Clé secrète JWT |
| `ADMIN_PASSWORD` | `Admin123!` | Mot de passe admin initial |
| `CORS_ALLOW_ORIGIN` | `http://localhost:3000` | CORS |

## Licence

Propriétaire — Atelier Moto Pro
