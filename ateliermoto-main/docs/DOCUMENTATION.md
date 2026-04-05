# Documentation Technique - Atelier Moto Pro v2.1

> **Mise à jour du 05/04/2026** — document réaligné avec l’état réel du dépôt

---

## 📋 Vue d’ensemble

Atelier Moto Pro est une application de gestion d’atelier moto **multi-atelier** qui couvre :
- prise de **RDV publique** avec calcul des créneaux disponibles
- **planning atelier** par pont et mécanicien
- **réception**, ordre de réparation, rapport technicien et travaux supplémentaires
- **devis**, tarification détaillée, forfaits MO et facturation PDF
- **stock / fournisseurs / commandes**
- **statistiques** et supervision opérationnelle

Le projet reste volontairement en **SPA vanilla JS**, mais il a été largement refactoré pour sortir la logique métier des gros fichiers monolithiques.

---

## 🏗️ Architecture actuelle

```text
Frontend SPA (HTML/CSS/JS vanilla)
        │
        ▼
Caddy / reverse proxy
        │
        ▼
FastAPI (`backend/main.py` + routers métier)
        │
        ▼
PostgreSQL 15
```

### Backend

`backend/main.py` joue désormais principalement le rôle de **composition root** :
- création de l’app `FastAPI`
- middlewares / CORS / logging
- inclusion des routers métier
- quelques routes publiques / legacy maintenues pour compatibilité

### Routers métier principaux

- `routes/auth_api.py` — login, refresh, logout, switch atelier, `/api/auth/me`
- `routes/tenant_admin.py` — administration multi-atelier / rôles
- `routes/clients.py` — clients et véhicules associés
- `routes/rendez_vous.py` — cycle de vie RDV, réception, travail, rapports, OR/facture PDF
- `routes/workshop.py` — ponts, mécaniciens, absences, planning
- `routes/public_booking.py` — booking public, créneaux et lookup véhicule
- `routes/inventory.py` — pièces, fournisseurs, commandes
- `routes/forfaits_mo.py` — forfaits de main d’œuvre
- `routes/moto_base.py` — catégories / modèles moto
- `routes/travaux_supp.py` — travaux supplémentaires et OR complémentaires
- `routes/devis.py` — devis et conversion devis → RDV
- `routes/vehicles.py` — endpoints véhicule dédiés
- `routes/prestations_tarifs.py` — catalogue prestations, grilles tarifaires, synthèse tarifs

---

## 🗂️ Structure du dépôt

```text
ateliermoto-main/
├── backend/
│   ├── main.py
│   ├── auth.py
│   ├── config_api.py
│   ├── facturation_api.py
│   ├── statistiques.py
│   ├── tarifs_api.py
│   ├── models.py
│   ├── services/
│   │   └── pdf_service.py
│   ├── schemas/
│   ├── routes/
│   │   ├── auth_api.py
│   │   ├── clients.py
│   │   ├── devis.py
│   │   ├── forfaits_mo.py
│   │   ├── inventory.py
│   │   ├── moto_base.py
│   │   ├── prestations_tarifs.py
│   │   ├── public_booking.py
│   │   ├── rendez_vous.py
│   │   ├── tenant_admin.py
│   │   ├── travaux_supp.py
│   │   ├── vehicles.py
│   │   └── workshop.py
│   └── tests/
├── frontend/
│   ├── index.html
│   ├── app.js
│   ├── api.js
│   ├── utils.js
│   ├── theme.css
│   └── modules/
│       ├── app-core.js
│       ├── dashboard.js
│       ├── rdv.js
│       ├── planning.js
│       ├── or.js
│       ├── clients.js
│       ├── admin.js
│       ├── mecanicien.js
│       ├── billing.js
│       ├── suivi.js
│       └── workshop.js
├── docs/
├── scripts/
└── docker-compose.yml
```

---

## ⚙️ Stack technique

| Couche | Technologie |
|--------|-------------|
| Backend | `FastAPI` |
| ORM | `SQLAlchemy` |
| Base de données | `PostgreSQL 15` |
| Auth | `JWT` + cookies HttpOnly + `bcrypt` |
| Frontend | `HTML/CSS/JavaScript vanilla` |
| PDF | `ReportLab` |
| Infra | `Docker Compose` + `Caddy` |

---

## 🔐 Authentification et sécurité

### Mécanisme
- access token JWT en **cookie HttpOnly**
- refresh token séparé
- support du header `Authorization: Bearer ...` pour les appels API/tests

### Variables d’environnement utiles

```bash
SECRET_KEY=...
CORS_ORIGINS=http://localhost:3000,https://votre-domaine
COOKIE_SECURE=true|false
COOKIE_SAMESITE=lax|strict|none
API_PLAQUE_IMMATRICULATION_KEY=...
```

### Points récemment sécurisés
- `secure=False` n’est plus figé en dur
- transitions de statuts RDV contrôlées côté backend
- permissions `rdv.edit` renforcées sur les actions sensibles

---

## 🔄 Workflow métier RDV

Cycle principal :

```text
reserve -> confirme -> reception -> en_cours -> termine -> facture -> paye
                 └-> annule / non_presente
```

### Transitions contrôlées
- `reserve` / `en_attente` → `confirme`, `annule`, `non_presente`, `reception`
- `confirme` → `reception`, `annule`, `non_presente`, `en_cours`
- `reception` → `en_cours`, `annule`
- `en_cours` → `termine`
- `termine` → `facture`, `paye`
- `facture` → `paye`

Les transitions invalides renvoient désormais un message explicite côté API.

---

## 🌐 Endpoints publics clés

| Méthode | Route | Usage |
|---------|-------|-------|
| `GET` | `/api/health` | santé API |
| `GET` | `/api/prestations/public` | prestations publiques par atelier |
| `GET` | `/api/vehicule/{plaque}` | lookup véhicule par plaque |
| `POST` | `/api/vehicule` | création manuelle d’un véhicule |
| `POST` | `/api/rendez-vous/public` | création d’un RDV public |
| `GET` | `/api/creneaux/disponibles` | créneaux disponibles selon durée |
| `GET` | `/api/creneaux/avec-ponts` | créneaux + ponts compatibles |
| `GET` | `/api/config/taux-mo` | taux MO publics |
| `GET` | `/api/interventions` | types d’intervention legacy/public |

---

## 🔒 Domaines protégés principaux

| Domaine | Exemples de routes |
|--------|----------------------|
| Auth | `/api/auth/login`, `/api/auth/me`, `/api/auth/refresh`, `/api/auth/logout` |
| RDV | `/api/rendez-vous`, `/api/rendez-vous/{id}`, `/api/rendez-vous/{id}/rapport-technicien` |
| Planning / workshop | `/api/planning`, `/api/ponts`, `/api/mecaniciens`, `/api/absences` |
| Clients | `/api/clients`, `/api/clients/{id}` |
| Devis | `/api/devis`, `/api/devis/{id}`, `/api/devis/calculer`, `/api/devis/{id}/convertir-rdv` |
| Prestations & tarifs | `/api/prestations`, `/api/grilles-tarifaires`, `/api/tarifs/calcul-detaille`, `/api/tarifs/synthese` |
| Facturation | `/api/factures`, `/api/factures/{id}`, `/api/rendez-vous/{id}/facturer` |
| Stock | `/api/pieces`, `/api/fournisseurs`, `/api/commandes` |
| Multi-tenant / rôles | routes de `tenant_admin.py` |

---

## 🧩 Frontend SPA

Le frontend reste sans framework, mais il est maintenant **modulaire** :

- `app-core.js` : boot, navigation et état global
- `dashboard.js` : KPI / vue d’ensemble
- `rdv.js` et `rdv-actions.js` : création / édition RDV
- `planning.js` et `planning-utils.js` : planning atelier
- `or.js` : ordres de réparation et travaux supp
- `clients.js` : recherche et fiches client
- `admin.js` : configuration, users, atelier, rôles
- `mecanicien.js` : espace méca et timers
- `billing.js` : facturation et encaissement
- `suivi.js` : suivi live
- `workshop.js` / `absences.js` : ressources atelier

---

## 🧪 Tests et validation

Commande de référence backend :

```bash
cd /workspaces/AtelierMBZ/ateliermoto-main
docker compose exec -T backend pytest -q
```

Régressions critiques récemment verrouillées :
- booking public multi-prestations
- conflits de créneaux pont / mécano
- OR complémentaires et travaux supplémentaires
- conversion `devis -> RDV`
- disponibilité des routes `prestations / tarifs / synthèse`

---

## 🚀 Déploiement local

```bash
cd /workspaces/AtelierMBZ/ateliermoto-main
docker compose up -d --build
```

Commandes utiles :

```bash
# logs backend
docker compose logs -f backend

# backup DB
./scripts/db-backup.sh

# restore DB
./scripts/db-restore.sh <fichier.sql>
```

> La base PostgreSQL vit dans un **volume Docker**. Faire un backup avant toute opération lourde ou migration.

---

## 📌 État du chantier

- backend : **fortement démonolithisé**
- frontend : **modularisé sans rupture UX**
- infra : stable en Docker, mais la partie signatures / backup / restore mérite encore un lissage
- dette restante principale : sortir davantage de logique runtime du `startup` et poursuivre le nettoyage frontend
