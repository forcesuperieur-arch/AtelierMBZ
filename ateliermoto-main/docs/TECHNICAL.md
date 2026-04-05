# Documentation Technique — Atelier Moto Pro

> **Version** : 2.1 Soft Refactor Multi-Tenant  
> **Stack** : FastAPI (Python) · PostgreSQL · Vanilla JS SPA modulaire · Docker  
> **Dernière mise à jour** : 05 avril 2026

---

## Table des matières

1. [Architecture](#architecture)
2. [Déploiement Docker](#déploiement-docker)
3. [Authentification & Autorisations](#authentification--autorisations)
4. [API Routes — Référence complète](#api-routes--référence-complète)
5. [Modèles de données](#modèles-de-données)
6. [Frontend SPA](#frontend-spa)

---

## Architecture

```
┌────────────────────────────────────────────────────┐
│                    Docker Host                      │
│                                                     │
│  ┌──────────────┐  ┌───────────────┐  ┌──────────┐ │
│  │   Caddy       │  │  Backend      │  │ Postgres │ │
│  │  (reverse     │──│  FastAPI      │──│  DB      │ │
│  │   proxy)      │  │  :8000        │  │  :5432   │ │
│  └──────────────┘  └───────────────┘  └──────────┘ │
│                     ▲                                │
│         ./frontend/ │ (volume mount)                 │
│         servi comme │ fichiers statiques              │
└────────────────────────────────────────────────────┘
```

| Composant | Technologie | Rôle |
|-----------|-------------|------|
| Backend | FastAPI + SQLAlchemy | API REST, auth JWT, logique métier |
| Base de données | PostgreSQL 15 | Stockage persistant (Docker volume) |
| Frontend | HTML/CSS/JS vanilla (SPA) | Interface utilisateur |
| Reverse Proxy | Caddy | HTTPS, routing |

### Structure des fichiers

> `backend/main.py` sert désormais surtout de **composition root** (app FastAPI, middlewares, `include_router(...)`, service SPA) et orchestre le démarrage via **FastAPI `lifespan`**. Les tâches de bootstrap/runtime legacy encore nécessaires sont regroupées dans `backend/startup_tasks.py` en attendant leur bascule progressive vers Alembic.

```text
backend/
├── main.py                  # Composition root FastAPI + middlewares + SPA/static
├── models.py                # Modèles SQLAlchemy
├── auth.py                  # Auth de base / helpers JWT
├── config_api.py            # Routes /api/config/*
├── statistiques.py          # Routes /api/statistiques/*
├── facturation_api.py       # Facturation / paiements / PDF
├── tarifs_api.py            # Tarifs historiques / créneaux
├── startup_tasks.py         # Bootstrap runtime temporaire / backfills idempotents
├── services/
│   └── pdf_service.py       # Génération PDF OR / facture
└── routes/
    ├── auth_api.py
    ├── tenant_admin.py
    ├── clients.py
    ├── rendez_vous.py
    ├── workshop.py
    ├── public_booking.py
    ├── inventory.py
    ├── forfaits_mo.py
    ├── moto_base.py
    ├── travaux_supp.py
    ├── devis.py
    ├── vehicles.py
    └── prestations_tarifs.py

frontend/
├── index.html
├── app.js
├── api.js
├── utils.js
├── theme.css
└── modules/
    ├── app-core.js
    ├── dashboard.js
    ├── rdv.js
    ├── planning.js
    ├── or.js
    ├── clients.js
    ├── admin.js
    ├── mecanicien.js
    ├── billing.js
    ├── suivi.js
    └── workshop.js

scripts/
├── db-backup.sh             # Sauvegarde PostgreSQL
└── db-restore.sh            # Restauration PostgreSQL
```

---

## Déploiement Docker

### Conteneurs

| Conteneur | Image | Port | Volume |
|-----------|-------|------|--------|
| `atelier-backend` | Python/FastAPI | 8000 | `./frontend:/app/static` |
| `atelier-db` | PostgreSQL 15 | 5432 | `pgdata` (named volume) |
| `caddy` | Caddy | 80/443 | `./Caddyfile` |

### Commandes utiles

```bash
# Démarrer
docker compose up -d

# Logs backend
docker compose logs -f atelier-backend

# Backup BDD
./scripts/db-backup.sh

# Restore BDD
./scripts/db-restore.sh backups/backup_YYYYMMDD_HHMMSS.sql
```

> ⚠️ La BDD vit dans un **Docker volume** (`pgdata`). Elle n'est PAS dans le dépôt Git. Utilisez `db-backup.sh` avant toute migration.

---

## Authentification & Autorisations

### Mécanisme

- **JWT** dans cookie HttpOnly (`access_token`)
- Refresh token dans cookie séparé (`refresh_token`)
- support du header `Authorization: Bearer ...` pour les appels API/tests
- Durée access token : 30 min / refresh token : 7 jours
- sécurité cookie pilotée par `COOKIE_SECURE` et `COOKIE_SAMESITE`

### Rôles système

| Rôle | Sections accessibles | Description |
|------|---------------------|-------------|
| `super_admin` | Toutes | Accès total, gestion multi-atelier |
| `admin` | Toutes | Administration d'un atelier |
| `receptionnaire` | Dashboard, RDV, Planning, Ponts, OR, Suivi, Clients, Espace Méca | Opérations quotidiennes |
| `service_client` | Dashboard, RDV, Planning, Ponts, OR, Suivi, Clients, Espace Méca | Service client |
| `mecanicien` | Dashboard, Planning, OR, Espace Méca | Technicien atelier |

### Permissions granulaires (RBAC)

| Permission | Description |
|-----------|-------------|
| `billing.view` | Voir les factures |
| `billing.edit` | Créer/modifier la facturation |
| `billing.pay` | Encaisser un paiement |
| `billing.pdf` | Générer PDF facture |
| `travaux_supp.review` | Valider les travaux supplémentaires |
| `rdv.select_atelier` | Choix atelier (multi-site) |
| `rdv.edit` | Modifier les rendez-vous |
| `users.manage` | Gérer les utilisateurs |
| `ateliers.manage` | Gérer les ateliers |
| `roles.manage` | Gérer les rôles & droits |
| `config.manage` | Gérer la configuration |
| `prestations.manage` | Gérer les prestations |
| `equipements.manage` | Gérer les équipements |

---

## API Routes — Référence complète

**Total :** routes métier + administration multi-atelier (nombre évolutif selon le refactor)

### Santé

| Méthode | Route | Description | Auth |
|---------|-------|-------------|------|
| GET | `/api/health` | Check santé de l'API | Public |

### Authentification (5 routes)

| Méthode | Route | Description | Auth |
|---------|-------|-------------|------|
| POST | `/api/auth/login` | Connexion (username + password) | Public |
| GET | `/api/auth/me` | Info utilisateur connecté + permissions | Authentifié |
| POST | `/api/auth/switch-atelier` | Changer d'atelier actif | Authentifié |
| POST | `/api/auth/refresh` | Rafraîchir le token d'accès | Authentifié |
| POST | `/api/auth/logout` | Déconnexion | Authentifié |

### Clients (7 routes)

| Méthode | Route | Description | Auth |
|---------|-------|-------------|------|
| GET | `/api/clients/stats` | Statistiques clients globales | Authentifié |
| GET | `/api/clients` | Liste clients (recherche + pagination) | Authentifié |
| POST | `/api/clients` | Créer un client | Public (booking) |
| GET | `/api/clients/{client_id}` | Détail client avec historique | Authentifié |
| PUT | `/api/clients/{client_id}` | Modifier un client | Authentifié |
| DELETE | `/api/clients/{client_id}` | Supprimer un client (si aucun RDV) | Authentifié |
| POST | `/api/clients/{client_id}/vehicules` | Ajouter un véhicule au client | Authentifié |

### Véhicules

| Méthode | Route | Description | Auth |
|---------|-------|-------------|------|
| GET | `/api/vehicules/{vehicule_id}` | Détail d'un véhicule par ID | Authentifié |
| GET | `/api/vehicule/{plaque}` | Lookup véhicule par plaque | Public |
| POST | `/api/vehicule` | Créer un véhicule manuellement | Public |
| PUT | `/api/vehicules/{vehicule_id}` | Modifier un véhicule | Authentifié |
| DELETE | `/api/vehicules/{vehicule_id}` | Supprimer un véhicule (si aucun RDV) | Authentifié |

### Rendez-vous (14 routes)

| Méthode | Route | Description | Auth |
|---------|-------|-------------|------|
| POST | `/api/rendez-vous` | Créer un RDV | Authentifié |
| GET | `/api/rendez-vous` | Lister les RDV (filtre par `?date=`) | Authentifié |
| GET | `/api/rendez-vous/{rdv_id}` | Détail d'un RDV | Authentifié |
| PUT | `/api/rendez-vous/{rdv_id}` | Modifier un RDV (statut, prix, pont, tech) | `rdv.edit` |
| DELETE | `/api/rendez-vous/{rdv_id}` | Supprimer un RDV | Authentifié |
| POST | `/api/rendez-vous/{rdv_id}/demarrer-travail` | Démarrer le chrono travail | Authentifié |
| POST | `/api/rendez-vous/{rdv_id}/terminer-travail` | Arrêter le chrono + calculer temps réel | Authentifié |
| GET | `/api/rendez-vous/{rdv_id}/temps-travail` | Obtenir les infos temps de travail | Authentifié |
| GET | `/api/rendez-vous/{rdv_id}/ordre-reparation` | Générer l'ordre de réparation (PDF) | Authentifié |
| POST | `/api/rendez-vous/{rdv_id}/ordre-reparation/save` | Sauvegarder l'OR + signature | Authentifié |
| GET | `/api/rendez-vous/{rdv_id}/signature` | Récupérer la signature du RDV | Authentifié |
| GET | `/api/rendez-vous/{rdv_id}/rapport-technicien` | Rapport technicien (checkup) | Authentifié |
| POST | `/api/rendez-vous/{rdv_id}/rapport-technicien` | Créer/modifier le rapport technicien | Authentifié |
| GET | `/api/rendez-vous/{rdv_id}/facture` | Générer la facture (PDF) | Authentifié |

### Atelier — Ponts & Mécaniciens (14 routes)

| Méthode | Route | Description | Auth |
|---------|-------|-------------|------|
| GET | `/api/ponts` | Liste des ponts/élévateurs | Authentifié |
| GET | `/api/ponts/status` | Statut temps réel des ponts + charge | Authentifié |
| POST | `/api/ponts` | Créer un pont | Authentifié |
| PUT | `/api/ponts/{pont_id}` | Modifier un pont | Authentifié |
| DELETE | `/api/ponts/{pont_id}` | Supprimer un pont (soft delete) | Authentifié |
| GET | `/api/mecaniciens` | Liste des mécaniciens | Authentifié |
| POST | `/api/mecaniciens` | Créer un mécanicien | Authentifié |
| PUT | `/api/mecaniciens/{mecanicien_id}` | Modifier un mécanicien | Authentifié |
| DELETE | `/api/mecaniciens/{mecanicien_id}` | Supprimer un mécanicien (soft delete) | Authentifié |
| GET | `/api/absences` | Lister les absences (avec filtres) | Authentifié |
| POST | `/api/absences` | Créer une absence | Authentifié |
| PUT | `/api/absences/{absence_id}` | Modifier une absence | Authentifié |
| DELETE | `/api/absences/{absence_id}` | Supprimer une absence | Authentifié |

### Planning (2 routes)

| Méthode | Route | Description | Auth |
|---------|-------|-------------|------|
| GET | `/api/planning` | Planning du jour (défaut : aujourd'hui) | Authentifié |
| GET | `/api/planning/semaine` | Planning sur 7 jours | Authentifié |

### Configuration (13 routes)

| Méthode | Route | Description | Auth |
|---------|-------|-------------|------|
| GET | `/api/config/atelier` | Config atelier (tarifs, marges, TVA) | Authentifié |
| PUT | `/api/config/atelier` | Modifier la config atelier | Admin |
| GET | `/api/config/horaires` | Horaires d'ouverture (Lun-Dim) | Authentifié |
| GET | `/api/config/horaires/{jour}` | Horaires d'un jour (0=Lun, 6=Dim) | Authentifié |
| PUT | `/api/config/horaires/{jour}` | Modifier les horaires d'un jour | Admin |
| GET | `/api/config/temps-interventions` | Temps d'intervention par prestation | Public |
| GET | `/api/config/temps-interventions/{id}` | Détail temps d'intervention | Public |
| POST | `/api/config/temps-interventions` | Créer un temps d'intervention | Admin |
| PUT | `/api/config/temps-interventions/{id}` | Modifier un temps d'intervention | Admin |
| DELETE | `/api/config/temps-interventions/{id}` | Supprimer un temps d'intervention | Admin |
| GET | `/api/config/pont-equipements` | Équipements des ponts | Public |
| POST | `/api/config/pont-equipements` | Créer un équipement | Admin |
| PUT | `/api/config/pont-equipements/{id}` | Modifier un équipement | Admin |

### Statistiques (11 routes)

| Méthode | Route | Description | Auth |
|---------|-------|-------------|------|
| GET | `/api/statistiques/dashboard` | Stats complètes pour le dashboard | Authentifié |
| GET | `/api/statistiques/ca` | Chiffre d'affaires par période | Authentifié |
| GET | `/api/statistiques/ca/comparatif` | Comparatif CA (jour/semaine/mois/année) | Authentifié |
| GET | `/api/statistiques/ponts` | Stats d'occupation des ponts | Authentifié |
| GET | `/api/statistiques/ponts/occupation` | Détail occupation (plage de dates) | Authentifié |
| GET | `/api/statistiques/interventions/top` | Top interventions (par nombre/CA) | Authentifié |
| GET | `/api/statistiques/clients/fideles` | Clients fidèles/fréquents | Authentifié |
| GET | `/api/statistiques/evolution-mensuelle` | Évolution CA mensuelle | Authentifié |
| GET | `/api/statistiques/mecaniciens` | Stats mécaniciens (alias) | Authentifié |
| GET | `/api/statistiques/mecaniciens/productivite` | Productivité des mécaniciens | Authentifié |
| GET | `/api/statistiques/mecaniciens/{id}/detail` | Détail productivité d'un mécanicien | Authentifié |

### Facturation (9 routes)

| Méthode | Route | Description | Auth |
|---------|-------|-------------|------|
| GET | `/api/rendez-vous/{rdv_id}/preview-facture` | Aperçu calcul avant facturation | `billing.view` |
| POST | `/api/rendez-vous/{rdv_id}/facturer` | Créer la facture d'un RDV terminé | `billing.edit` |
| POST | `/api/factures/{facture_id}/encaisser` | Enregistrer un paiement | `billing.pay` |
| GET | `/api/factures` | Liste des factures (avec filtres) | `billing.view` |
| GET | `/api/factures/stats` | Statistiques facturation | `billing.view` |
| GET | `/api/factures/{facture_id}` | Détail d'une facture | `billing.view` |
| GET | `/api/factures/par-rdv/{rdv_id}` | Facture liée à un RDV | `billing.view` |
| POST | `/api/factures/{facture_id}/annuler` | Annuler une facture | `billing.edit` |
| GET | `/api/factures/{facture_id}/pdf` | Télécharger le PDF facture | `billing.pdf` |

### Devis

| Méthode | Route | Description | Auth |
|---------|-------|-------------|------|
| GET | `/api/devis` | Liste les devis | Authentifié |
| GET | `/api/devis/{devis_id}` | Détail d'un devis | Authentifié |
| POST | `/api/devis` | Créer un devis | Authentifié |
| POST | `/api/devis/calculer` | Calculer un devis sans sauvegarde | Authentifié |
| PUT | `/api/devis/{devis_id}` | Mettre à jour statut / notes | Authentifié |
| POST | `/api/devis/{devis_id}/convertir-rdv` | Convertir un devis en RDV | Authentifié |
| DELETE | `/api/devis/{devis_id}` | Supprimer un devis | Authentifié |

### Prestations & tarification

| Méthode | Route | Description | Auth |
|---------|-------|-------------|------|
| GET | `/api/prestations` | Catalogue des prestations | Authentifié |
| GET | `/api/prestations/{prestation_id}` | Détail d'une prestation | Authentifié |
| POST | `/api/prestations` | Créer une prestation | Authentifié |
| PUT | `/api/prestations/{prestation_id}` | Modifier une prestation | Authentifié |
| DELETE | `/api/prestations/{prestation_id}` | Désactiver une prestation | Authentifié |
| GET | `/api/grilles-tarifaires` | Liste des grilles tarifaires | Authentifié |
| POST | `/api/grilles-tarifaires` | Créer une grille tarifaire | Authentifié |
| POST | `/api/tarifs/calcul-detaille` | Calcul détaillé MO + pièces | Authentifié |
| GET | `/api/tarifs/forfaits-mo` | Forfaits MO actifs | Public / Auth selon usage |
| GET | `/api/tarifs/delais` | Délais d'intervention | Public / Auth selon usage |
| GET | `/api/tarifs/synthese` | Synthèse tarifaire atelier | Authentifié |

### Tarifs historiques & créneaux

| Méthode | Route | Description | Auth |
|---------|-------|-------------|------|
| GET | `/api/tarifs` | Grille tarifaire legacy | Authentifié |
| POST | `/api/tarifs` | Créer un tarif | Authentifié |
| PUT | `/api/tarifs/{tarif_id}` | Modifier un tarif | Authentifié |
| DELETE | `/api/tarifs/{tarif_id}` | Désactiver un tarif | Authentifié |
| POST | `/api/tarifs/calculer` | Calculer un devis (prestations → prix) | Authentifié |
| GET | `/api/creneaux/par-duree` | Créneaux disponibles pour une durée | Authentifié |

### Utilisateurs (5 routes)

| Méthode | Route | Description | Auth |
|---------|-------|-------------|------|
| GET | `/api/users` | Liste des utilisateurs | Admin |
| POST | `/api/users` | Créer un utilisateur | Admin |
| GET | `/api/users/{user_id}` | Détail utilisateur | Admin |
| PUT | `/api/users/{user_id}` | Modifier un utilisateur | Admin |
| DELETE | `/api/users/{user_id}` | Supprimer un utilisateur | Admin |

### Rôles & Permissions (3 routes)

| Méthode | Route | Description | Auth |
|---------|-------|-------------|------|
| GET | `/api/roles/permissions` | Lister tous les rôles et permissions | `roles.manage` |
| POST | `/api/roles/permissions` | Créer/modifier un rôle | `roles.manage` |
| DELETE | `/api/roles/permissions/{role}` | Supprimer un rôle | `roles.manage` |

---

## Modèles de données

### Entités principales

| Modèle | Table | Description |
|--------|-------|-------------|
| `Atelier` | `ateliers` | Atelier/workshop (multi-tenant) |
| `User` | `users` | Utilisateurs avec rôle et atelier |
| `Client` | `clients` | Clients avec coordonnées |
| `Vehicule` | `vehicules` | Véhicules liés à un client |
| `RendezVous` | `rendez_vous` | RDV avec statut, prestations, pont, mécanicien |
| `Mecanicien` | `mecaniciens` | Techniciens avec spécialités |
| `Pont` | `ponts` | Ponts/élévateurs de l'atelier |
| `HoraireAtelier` | `horaires_atelier` | Horaires d'ouverture par jour |
| `ConfigAtelier` | `config_atelier` | Configuration (tarifs, marges, TVA) |
| `Prestation` | `prestations` | Catalogue de prestations |
| `GrilleTarifaire` | `grille_tarifaire` | Grille prix par type de moto |
| `Facture` | `factures` | Factures avec lignes détaillées |
| `LigneFacture` | `lignes_facture` | Lignes de facture |
| `Paiement` | `paiements` | Paiements enregistrés |
| `Absence` | `absences` | Absences mécaniciens |
| `PontEquipement` | `pont_equipements` | Équipements par pont |
| `RolePermission` | `role_permissions` | Matrice rôles-permissions (RBAC) |

### Cycle de vie d'un RDV

```
reserve → confirme → reception → en_cours → termine → facture → paye
           ↓                        ↓
        annule                   annule / non_presente
```

### Multi-tenant

Toutes les entités principales portent un `atelier_id` pour le filtrage par atelier. Le filtrage est automatique via `_atelier_id_or_403()` dans les routes.

---

## Frontend SPA

### Architecture

Le frontend est une **Single Page Application** vanilla (sans framework) :

- `index.html` — Structure HTML avec toutes les sections
- `app.js` — Logique complète (~7000 lignes, 150+ fonctions)
- `api.js` — Helpers HTTP (`apiGet`, `apiPost`, `apiPut`, `apiDelete`)
- `utils.js` — Helpers partagés (`escapeHtml`, `escapeAttr`, `showToast`, `formatDate`)
- `theme.css` — Design system Motoblouz (variables CSS, boutons, badges, couleurs)

### Helpers API (`api.js`)

```javascript
apiGet(url)          // GET avec cookie auth
apiPost(url, data)   // POST JSON
apiPut(url, data)    // PUT JSON
apiDelete(url)       // DELETE
```

### Helpers partagés (`utils.js`)

```javascript
escapeHtml(str)      // Échappe HTML (protection XSS)
escapeAttr(str)      // Alias escapeHtml pour les attributs
showToast(msg, type) // Toast notification (info/success/warning/error)
formatDate(date)     // Formate en date française (dd/mm/yyyy)
```

### Sections de navigation

| Section | ID | Rôles |
|---------|----|-------|
| Dashboard | `dashboard` | Tous |
| Prise de RDV | `rdv` | Reception, Service client, Admin |
| Planning | `planning` | Tous sauf Mécanicien |
| Ponts & Mécaniciens | `ponts` | Reception, Service client, Admin |
| Ordres de Réparation | `or` | Tous sauf Mécanicien |
| Suivi Live | `suivi` | Reception, Service client, Admin |
| Clients | `clients` | Reception, Service client, Admin |
| Espace Mécanicien | `espace-meca` | Tous (prioritaire pour Mécanicien) |
| Administration | `admin` | Admin, Super Admin |

### Onglets Administration

| Onglet | Permission requise |
|--------|--------------------|
| Ateliers | `users.manage` |
| Workshop (Ponts/Techs) | Admin |
| Utilisateurs | `users.manage` |
| Config (tarifs, marges) | `config.manage` |
| Horaires | `config.manage` |
| Prestations | `prestations.manage` |
| Équipements | `equipements.manage` |
| Rôles (Super Admin) | `roles.manage` |

### Rafraîchissement automatique

| Fonctionnalité | Intervalle |
|---------------|------------|
| Dashboard | 30 secondes |
| Suivi Live | 30 secondes |
| Alertes Travaux Supp. | 30 secondes |
| Ligne "maintenant" (planning) | 60 secondes |
| Timer mécanicien | 1 seconde |
