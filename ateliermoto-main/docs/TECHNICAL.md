# Documentation Technique — Atelier Moto Pro

> **Version** : 2.2  
> **Stack** : FastAPI · PostgreSQL · SPA Vanilla JS modulaire · Docker  
> **Dernière mise à jour** : 07 avril 2026

---

## Table des matières

1. [Architecture](#architecture)
2. [Déploiement Docker](#déploiement-docker)
3. [Authentification & RBAC](#authentification--rbac)
4. [API métier — vue d’ensemble](#api-métier--vue-densemble)
5. [Modèles de données](#modèles-de-données)
6. [Frontend SPA](#frontend-spa)
7. [Base de données & versionnement Git](#base-de-données--versionnement-git)

---

## Architecture

```text
Navigateur / SPA
        │
        ▼
Caddy / proxy
        │
        ▼
FastAPI (`backend/main.py` + routers métier)
        │
        ▼
PostgreSQL 15 (`postgres_data`)
```

| Composant | Technologie | Rôle |
|-----------|-------------|------|
| Backend | FastAPI + SQLAlchemy | API REST, auth, logique métier |
| Base de données | PostgreSQL 15 | stockage persistant |
| Frontend | HTML/CSS/JS vanilla | interface SPA + pages legacy compatibles |
| Reverse proxy | Caddy | exposition HTTP/HTTPS |

### Structure active du dépôt

```text
backend/
├── main.py                  # Composition root FastAPI
├── models.py                # Modèles SQLAlchemy
├── auth.py                  # Helpers JWT / auth
├── config_api.py            # Config atelier / horaires / paramètres
├── facturation_api.py       # Facturation / paiements / PDF
├── statistiques.py          # KPI et dashboard
├── tarifs_api.py            # Tarifs legacy / calculs / créneaux
├── alembic/versions/        # Schéma versionné dans Git
├── routes/
│   ├── auth_api.py
│   ├── tenant_admin.py
│   ├── clients.py
│   ├── vehicles.py
│   ├── rendez_vous.py
│   ├── workshop.py
│   ├── public_booking.py
│   ├── travaux_supp.py
│   ├── devis.py
│   ├── inventory.py
│   ├── forfaits_mo.py
│   ├── moto_base.py
│   ├── prestations_tarifs.py
│   └── frontend_pages.py
├── services/
│   ├── pdf_service.py
│   ├── startup_service.py
│   └── runtime_migrations.py
└── tests/

frontend/
├── index.html
├── app.js
├── api.js
├── utils.js
├── theme.css
└── modules/
    ├── absences.js
    ├── admin.js
    ├── app-core.js
    ├── billing.js
    ├── clients.js
    ├── dashboard.js
    ├── debug-tools.js
    ├── mecanicien.js
    ├── or.js
    ├── planning-utils.js
    ├── planning.js
    ├── rdv-actions.js
    ├── rdv.js
    ├── suivi.js
    └── workshop.js
```

---

## Déploiement Docker

### Services

| Service | Image / build | Ports | Persistance |
|---------|---------------|-------|-------------|
| `backend` | build `./backend` | `8000` | code monté en volume |
| `db` | `postgres:15-alpine` | `5432` | volume `postgres_data` |
| `caddy` | `caddy:latest` | `80/443` | `caddy_data`, `caddy_config` |

### Commandes utiles

```bash
# Démarrer
docker compose up -d --build

# Logs backend
docker compose logs -f backend

# Shell backend
docker compose exec backend bash

# Sauvegarde / restauration
./scripts/db-backup.sh
./scripts/db-restore.sh backup_YYYYMMDD_HHMMSS.sql
```

---

## Authentification & RBAC

### Mécanisme

- JWT en **cookie HttpOnly** (`access_token`)
- refresh token séparé
- support du header `Authorization: Bearer ...` pour les tests et appels API
- paramètres sécurité pilotés par `COOKIE_SECURE` et `COOKIE_SAMESITE`

### Rôles système

| Rôle | Usage |
|------|-------|
| `super_admin` | administration multi-atelier et catalogue global |
| `admin` | administration atelier courant |
| `receptionnaire` | exploitation quotidienne RDV / planning / OR |
| `service_client` | opérations sans facturation complète |
| `mecanicien` | exécution atelier et espace méca |

### Permissions granulaires actives

| Permission | Description |
|-----------|-------------|
| `billing.view` | voir les factures |
| `billing.edit` | créer / modifier la facturation |
| `billing.pay` | encaisser un paiement |
| `billing.pdf` | générer le PDF facture |
| `travaux_supp.review` | approuver/refuser les travaux supplémentaires |
| `rdv.select_atelier` | choisir l’atelier actif |
| `rdv.edit` | modifier un rendez-vous |
| `users.manage` | gérer les utilisateurs |
| `ateliers.manage` | gérer les ateliers |
| `roles.manage` | gérer les rôles & droits |
| `config.manage` | gérer la configuration atelier |
| `prestations.manage` | gérer les prestations |

---

## API métier — vue d’ensemble

> Le nombre exact de routes évolue avec le refactor. La liste ci-dessous couvre les **familles actives** et les endpoints clés à connaître.

### Santé & auth

| Méthode | Route | Usage |
|---------|-------|-------|
| `GET` | `/api/health` | healthcheck |
| `POST` | `/api/auth/login` | connexion |
| `GET` | `/api/auth/me` | utilisateur courant + permissions |
| `POST` | `/api/auth/refresh` | refresh token |
| `POST` | `/api/auth/logout` | déconnexion |
| `POST` | `/api/auth/switch-atelier` | changement d’atelier actif |

### Booking public & créneaux

| Méthode | Route | Usage |
|---------|-------|-------|
| `GET` | `/api/prestations/public` | prestations affichées au booking public |
| `POST` | `/api/rendez-vous/public` | création RDV public |
| `GET` | `/api/creneaux/disponibles` | créneaux libres par durée |
| `GET` | `/api/creneaux/avec-ponts` | créneaux détaillés avec ponts |
| `GET` | `/api/vehicule/{plaque}` | lookup véhicule par immatriculation |

### Clients & véhicules

| Famille | Principales routes |
|---------|--------------------|
| Clients | `/api/clients`, `/api/clients/{id}`, `/api/clients/stats` |
| Véhicules | `/api/vehicules/{id}`, `/api/vehicule/{plaque}` |

### Rendez-vous, réception et OR

| Méthode | Route | Usage |
|---------|-------|-------|
| `GET/POST/PUT/DELETE` | `/api/rendez-vous...` | cycle de vie RDV |
| `POST` | `/api/rendez-vous/{rdv_id}/demarrer-travail` | démarrage chrono |
| `POST` | `/api/rendez-vous/{rdv_id}/terminer-travail` | fin chrono |
| `GET` | `/api/rendez-vous/{rdv_id}/ordre-reparation` | PDF OR du RDV |
| `GET` | `/api/ordres-reparation/{or_id}/pdf` | PDF direct d’un OR stocké |
| `POST` | `/api/rendez-vous/{rdv_id}/ordre-reparation/save` | sauvegarde réception enrichie |
| `GET/POST` | `/api/rendez-vous/{rdv_id}/rapport-technicien` | checkup technicien |

### Données enrichies de réception

Le payload d’OR/réception peut maintenant enregistrer :
- `kilometrage`
- `etat_vehicule` structuré (JSON)
- `priorite`
- `niveau_carburant`
- `dommages_carrosserie`
- `notes_schema`
- `lignes_estimation`
- `photos`
- `signature_client`

### Travaux supplémentaires

| Méthode | Route | Usage |
|---------|-------|-------|
| `POST` | `/api/rendez-vous/{rdv_id}/travaux-supplementaires` | créer une demande |
| `GET` | `/api/travaux-supplementaires/en-attente` | file d’attente à valider |
| `PUT` | `/api/travaux-supplementaires/{demande_id}` | approuver / refuser |
| `GET` | `/api/rendez-vous/{rdv_id}/ordres-reparation-archives` | historique OR liés |

### Planning, workshop, absences

| Famille | Principales routes |
|---------|--------------------|
| Planning | `/api/planning`, `/api/planning/semaine` |
| Ponts | `/api/ponts`, `/api/ponts/status` |
| Mécaniciens | `/api/mecaniciens...` |
| Absences | `/api/absences...` |

### Devis, facturation et paiements

| Famille | Principales routes |
|---------|--------------------|
| Devis | `/api/devis`, `/api/devis/calculer`, `/api/devis/{id}/convertir-rdv` |
| Facturation | `/api/rendez-vous/{rdv_id}/facturer`, `/api/factures`, `/api/factures/{id}/pdf` |
| Paiements | `/api/factures/{id}/encaisser` |

### Stock / fournisseurs / commandes

| Famille | Principales routes |
|---------|--------------------|
| Pièces | `/api/pieces`, `/api/pieces/alertes`, `/api/pieces/{id}/ajuster-stock` |
| Fournisseurs | `/api/fournisseurs...` |
| Commandes | `/api/commandes...`, `/api/commandes/{id}/receptionner` |
| Pièces sur RDV | `/api/rendez-vous/{rdv_id}/pieces...` |
| Stats stock | `/api/stats/stock` |

### Base moto & catalogue

| Méthode | Route | Usage |
|---------|-------|-------|
| `GET` | `/api/motos/autocomplete` | autocomplete marques / modèles |
| `GET` | `/api/motos/categories` | catégories moto |
| `GET` | `/api/motos/modeles` | liste / filtre des modèles |
| `GET` | `/api/motos/technical-specs` | specs techniques |
| `POST` | `/api/motos/catalog/import` | import catalogue (super admin) |

### Configuration & administration

| Famille | Principales routes |
|---------|--------------------|
| Config atelier | `/api/config/atelier`, `/api/config/horaires...` |
| Temps d’intervention | `/api/config/temps-interventions...` |
| Prestations & grilles | `/api/prestations...`, `/api/grilles-tarifaires...`, `/api/tarifs/synthese` |
| Utilisateurs | `/api/users...` |
| Rôles | `/api/roles/permissions...` |
| Équipements ponts | `/api/config/pont-equipements...` *(API encore disponible, UI principale masquée)* |

### Statistiques

Principales routes :
- `/api/statistiques/dashboard`
- `/api/statistiques/ca`
- `/api/statistiques/ponts`
- `/api/statistiques/mecaniciens/productivite`
- `/api/statistiques/clients/fideles`

---

## Modèles de données

### Entités clés

| Modèle | Rôle |
|--------|------|
| `Atelier` | portée multi-atelier |
| `User` / `UserAtelierRole` | auth et rôles par atelier |
| `Client` / `Vehicule` | relation client ↔ motos |
| `RendezVous` | cœur du workflow atelier |
| `OrdreReparation` | OR initiaux et complémentaires |
| `RapportTechnicien` | checkup, alertes, recommandations |
| `Facture` / `Paiement` | facturation et encaissement |
| `Prestation` / `GrilleTarifaire` / `ForfaitMO` | tarification |
| `PieceDetachee` / `Fournisseur` / `CommandeFournisseur` | stock |
| `CategorieMoto` / `ModeleMoto` / `MotoTechnicalSpec` | référentiel moto |
| `RolePermission` | RBAC |

### Cycle de vie RDV

```text
reserve → confirme → reception → en_cours → termine → facture → paye
           └──────────────→ annule / non_presente
```

### Multi-tenant

Les entités métier principales portent un `atelier_id`. Les routes filtrent le périmètre courant via les helpers d’atelier côté backend.

---

## Frontend SPA

### Principe

Le frontend reste volontairement **sans framework**, mais il n’est plus monolithique :
- `index.html` héberge les sections de la SPA
- `app.js` sert de **pont global** et de compatibilité avec les handlers existants
- la logique métier est déplacée dans `frontend/modules/*`

### Modules majeurs

| Module | Rôle |
|--------|------|
| `app-core.js` | bootstrap global, navigation, RBAC, actions transverses |
| `dashboard.js` | dashboard et KPI |
| `rdv.js` + `rdv-actions.js` | création, édition, actions RDV |
| `planning.js` + `planning-utils.js` | planning visuel et helpers temps |
| `or.js` | réception, OR, impression, PDF, travaux supp |
| `billing.js` | facturation / paiements |
| `clients.js` | fiches clients / véhicules |
| `admin.js` | onglets admin et configuration |
| `mecanicien.js` | espace technicien |
| `suivi.js` | suivi live atelier |
| `workshop.js` + `absences.js` | ponts, mécanos, absences |
| `debug-tools.js` | outils de debug non métier |

### Compatibilité legacy

Des pages standalone (`planning.html`, `factures.html`, `clients.html`, `login.html`, etc.) restent servies via `frontend_pages.py` pour compatibilité ou redirection, tandis que la SPA `index.html` reste l’interface principale.

---

## Base de données & versionnement Git

### Persistance locale

- PostgreSQL est stocké dans le volume Docker **`postgres_data`**.
- Les dumps opérationnels vivent dans `../backups/`.

### Source de vérité Git

- **schéma** : `backend/alembic/versions/`
- **bootstrap métier** : `backend/seed.py`, `backend/seed_parametres.py`
- **données de référence** : `backend/data/`

### Runtime migrations

`backend/services/runtime_migrations.py` conserve encore des backfills/idempotences de compatibilité historique. Toute évolution de schéma durable doit désormais être reflétée dans **Alembic** pour rester traçable dans Git.
