# Documentation Technique - Atelier Moto Pro v2.0

## 📋 Table des matières
1. [Vue d'ensemble](#vue-densemble)
2. [Architecture](#architecture)
3. [Stack Technique](#stack-technique)
4. [Modèles de Données](#modèles-de-données)
5. [API Endpoints](#api-endpoints)
6. [Authentification](#authentification)
7. [Frontend](#frontend)
8. [Workflow des Statuts](#workflow-des-statuts)
9. [Agents en Parallèle](#agents-en-parallèle)
10. [Déploiement](#déploiement)
11. [URLs de Production](#urls-de-production)
12. [Évolution](#évolution)

---

## Vue d'ensemble

Atelier Moto Pro est une application complète de gestion d'atelier mécanique moto comprenant :
- Interface client publique pour la prise de rendez-vous
- Dashboard administrateur avec statistiques avancées
- Planning mécanicien en temps réel
- Gestion des ponts (4 ponts configurables)
- Gestion des pièces détachées et stock
- Système de devis et facturation
- Workflow complet de suivi des interventions

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              CLIENT WEB                                      │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌─────────────────┐  │
│  │  Dashboard   │  │   Planning   │  │    Admin     │  │   RDV Public    │  │
│  │   (Auth)     │  │   (Auth)     │  │   (Auth)     │  │   (Public)      │  │
│  └──────────────┘  └──────────────┘  └──────────────┘  └─────────────────┘  │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                           BACKEND API (FastAPI)                              │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌─────────────────┐  │
│  │     Auth     │  │ Rendez-vous  │  │   Planning   │  │   Statistiques  │  │
│  │    (JWT)     │  │   (CRUD)     │  │   (Ponts)    │  │    (Rapports)   │  │
│  └──────────────┘  └──────────────┘  └──────────────┘  └─────────────────┘  │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌─────────────────┐  │
│  │    Stock     │  │    Devis     │  │    Tarifs    │  │      OR         │  │
│  │   (Pièces)   │  │ (Facturation)│  │  (Forfaits)  │  │    (PDF)        │  │
│  └──────────────┘  └──────────────┘  └──────────────┘  └─────────────────┘  │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                         BASE DE DONNÉES (PostgreSQL)                         │
│                                                                              │
│   clients │ vehicules │ rendez_vous │ ponts │ mecaniciens │ pieces │ devis  │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Structure des dossiers
```
atelier-moto/
├── backend/
│   ├── main.py              # Routes API principales
│   ├── models.py            # Modèles SQLAlchemy
│   ├── auth.py              # Authentification JWT
│   ├── seed.py              # Données initiales
│   ├── statistiques.py      # Module statistiques
│   ├── stock_routes.py      # Routes gestion stock
│   ├── requirements.txt     # Dépendances Python
│   └── Dockerfile
├── frontend/
│   ├── index.html           # Dashboard principal
│   ├── dashboard.html       # Dashboard alternatif
│   ├── rendez-vous.html     # Interface publique RDV
│   ├── rendez-vous.js       # Logique prise RDV
│   ├── planning.html        # Planning mécanicien
│   ├── planning.js          # Logique planning
│   ├── clients.html         # Gestion clients
│   ├── admin.html           # Administration
│   ├── statistiques.html    # Rapports et stats
│   └── styles/              # CSS additionnels
├── database/
│   └── init.sql             # Initialisation DB
├── docker-compose.yml
└── docs/
    └── DOCUMENTATION.md     # Ce fichier
```

---

## Stack Technique

| Couche | Technologie | Version |
|--------|-------------|---------|
| Backend | FastAPI | 0.104.1 |
| ORM | SQLAlchemy | 2.0.23 |
| Base de données | PostgreSQL | 15 |
| Auth | JWT + bcrypt | - |
| Frontend | HTML5 + Tailwind CSS | 3.x |
| Icons | Font Awesome | 6.4.0 |
| PDF | ReportLab | - |
| Conteneur | Docker + Docker Compose | - |

---

## Modèles de Données

### Client
```python
{
    id: int (PK)
    nom: str
    prenom: str
    telephone: str
    email: str (optionnel)
    adresse: text (optionnel)
    notes: text (optionnel)
    created_at: datetime
}
```

### Vehicule
```python
{
    id: int (PK)
    plaque: str (unique, indexé)
    marque: str
    modele: str
    annee: int
    cylindree: str
    type_moto: str
}
```

### RendezVous
```python
{
    id: int (PK)
    client_id: int (FK)
    vehicule_id: int (FK)
    pont_id: int (FK, optionnel)
    mecanicien_id: int (FK, optionnel)
    date_rdv: date
    heure_rdv: time
    type_intervention: str
    commentaire: text
    prix_estime: float
    prix_final: float
    temps_estime: int (minutes)
    temps_final: int (minutes)
    kilometrage: int
    etat_vehicule: text (JSON)
    photos_etat: text (URLs)
    statut: str [en_attente, confirme, en_cours, termine, annule, facture, paye]
    created_at: datetime
    updated_at: datetime
}
```

### Pont (Poste de travail)
```python
{
    id: int (PK)
    nom: str
    type_pont: str [moto, scooter, quad]
    capacite_kg: int
    is_active: bool
    ordre_affichage: int
}
```

### Mecanicien
```python
{
    id: int (PK)
    nom: str
    prenom: str
    specialites: text (JSON)
    couleur: str (hex pour calendrier)
    is_active: bool
}
```

### User (Authentification)
```python
{
    id: int (PK)
    username: str (unique)
    email: str (unique)
    hashed_password: str (bcrypt)
    role: str [admin, receptionnaire, mecanicien]
    is_active: bool
    created_at: datetime
}
```

### InterventionType
```python
{
    id: int (PK)
    nom: str
    description: str
    prix_base: float
    temps_estime: int (minutes)
    is_active: bool
}
```

### PieceDetachee (Gestion de stock)
```python
{
    id: int (PK)
    reference: str (unique)
    reference_fournisseur: str
    nom: str
    description: text
    categorie: str
    quantite_stock: int
    quantite_minimale: int
    quantite_maximale: int
    emplacement: str
    prix_achat_ht: float
    prix_vente_ht: float
    tva_taux: float
    fournisseur_id: int (FK)
    is_active: bool
}
```

### Devis
```python
{
    id: int (PK)
    numero_devis: str (unique)
    client_id: int (FK)
    vehicule_id: int (FK)
    date_creation: datetime
    date_validite: date
    statut: str [brouillon, envoye, accepte, refuse, expire, converti]
    total_mo_ht: float
    total_pieces_ht: float
    total_ht: float
    total_ttc: float
    remise_pourcentage: float
    acompte_demande: float
}
```

### ForfaitMO (Forfaits main d'œuvre)
```python
{
    id: int (PK)
    code: str (unique)
    nom: str
    categorie: str
    temps_base_minutes: int
    taux_horaire_applique: str [standard, complexe, expert]
    prix_forfait_mo_ht: float
    prix_forfait_mo_ttc: float
    inclut_pieces: bool
    type_vehicule: str
    cylindree_min: int
    cylindree_max: int
    is_active: bool
    is_promo: bool
}
```

### ConfigAtelier
```python
{
    id: int (PK)
    taux_horaire_mo_standard: float (défaut: 65.0)
    taux_horaire_mo_complexe: float (défaut: 85.0)
    taux_horaire_mo_expert: float (défaut: 95.0)
    marge_pieces_standard: float (défaut: 30.0)
    marge_pieces_consommable: float (défaut: 50.0)
    marge_pieces_pneumatique: float (défaut: 25.0)
    forfait_mo_minimum: float (défaut: 25.0)
    tva_mo_taux: float (défaut: 20.0)
    tva_pieces_taux: float (défaut: 20.0)
    validite_devis_jours: int (défaut: 30)
    accompte_pourcentage: float (défaut: 30.0)
}
```

---

## API Endpoints

### 🔓 Endpoints Publics (sans authentification)

#### POST /api/rendez-vous/public
**Description** : Crée un rendez-vous depuis l'interface publique
**Body** :
```json
{
    "client": { "nom", "prenom", "telephone", "email" },
    "vehicule": { "plaque", "marque", "modele", "annee", "cylindree" },
    "prestations": [1, 2, 3],
    "date_heure": "2026-03-25T09:00:00",
    "montant_estime": 180.0,
    "commentaires": "..."
}
```

#### GET /api/creneaux/disponibles
**Description** : Récupère les créneaux disponibles pour une période
**Query params** : `date_debut`, `date_fin`
**Response** : Liste des créneaux libres

#### GET /api/vehicule/{plaque}
**Description** : Récupère les infos d'un véhicule par sa plaque
**Response** :
```json
{
    "plaque": "AA-123-BB",
    "marque": "YAMAHA",
    "modele": "MT-07",
    "annee": 2020,
    "cylindree": "689cc",
    "type_moto": "Roadster",
    "source": "api|database|mock|manual"
}
```

#### POST /api/vehicule
**Description** : Crée un véhicule manuellement (pour plaques non trouvées)
**Body** : `{ plaque, marque, modele, annee, cylindree, type_moto }`

#### GET /api/interventions
**Description** : Liste les types d'intervention disponibles
**Response** : Array d'InterventionType

#### GET /api/tarifs/forfaits-mo
**Description** : Récupère les forfaits MO actifs (public)

---

### 🔒 Endpoints Protégés (JWT requis)

#### Authentification

##### POST /api/auth/login
**Description** : Connexion utilisateur
**Body** : `username`, `password` (FormData)
**Response** :
```json
{
    "access_token": "eyJhbG...",
    "token_type": "bearer",
    "role": "receptionnaire"
}
```

##### GET /api/auth/me
**Description** : Infos utilisateur connecté
**Response** : `{ username, email, role }`

---

#### Rendez-vous

##### GET /api/rendez-vous
**Description** : Liste tous les RDV
**Query params** : `skip`, `limit`
**Response** : Array de RendezVous

##### POST /api/rendez-vous
**Description** : Crée un nouveau RDV (interface pro)
**Body** :
```json
{
    "client": { "nom", "prenom", "telephone", "email" },
    "vehicule": { "plaque", "marque", "modele", "annee", "cylindree" },
    "date_rdv": "2026-03-25",
    "heure_rdv": "14:00:00",
    "type_intervention": "Révision 10 000km",
    "commentaire": "..."
}
```

##### GET /api/rendez-vous/{id}
**Description** : Détail d'un RDV

##### PUT /api/rendez-vous/{id}
**Description** : Met à jour un RDV (statut, prix, pont, mécanicien)
**Body** : `{ statut, kilometrage, etat_vehicule, prix_final, temps_final, pont_id, mecanicien_id }`

##### DELETE /api/rendez-vous/{id}
**Description** : Supprime un RDV

##### GET /api/rendez-vous/{id}/ordre-reparation
**Description** : Génère un PDF OR avec mentions légales
**Response** : PDF stream

---

#### Clients

##### GET /api/clients
**Description** : Liste les clients avec recherche
**Query params** : `search`, `skip`, `limit`
**Response** : Array avec stats (nb_rdv, dernier_rdv)

##### GET /api/clients/{id}
**Description** : Fiche détaillée client avec historique complet
**Response** :
```json
{
    "id": 1,
    "nom": "...",
    "prenom": "...",
    "historique": [...],
    "vehicules": [...]
}
```

##### PUT /api/clients/{id}
**Description** : Met à jour un client

##### DELETE /api/clients/{id}
**Description** : Supprime un client (si pas de RDV)

---

#### Planning

##### GET /api/planning
**Description** : Planning des RDV pour une date
**Query params** : `date` (YYYY-MM-DD)
**Response** :
```json
{
    "date": "2026-03-25",
    "ponts": [...],
    "mecaniciens": [...],
    "rendez_vous": [...]
}
```

##### GET /api/planning/semaine
**Description** : Planning sur 7 jours
**Query params** : `date_debut`

---

#### Ponts

##### GET /api/ponts
**Description** : Liste des ponts actifs

##### POST /api/ponts
**Description** : Crée un nouveau pont

##### PUT /api/ponts/{id}
**Description** : Met à jour un pont

##### DELETE /api/ponts/{id}
**Description** : Supprime un pont (soft delete)

---

#### Mécaniciens

##### GET /api/mecaniciens
**Description** : Liste des mécaniciens actifs

##### POST /api/mecaniciens
**Description** : Crée un mécanicien

##### PUT /api/mecaniciens/{id}
**Description** : Met à jour un mécanicien

##### DELETE /api/mecaniciens/{id}
**Description** : Supprime un mécanicien (soft delete)

---

#### Statistiques (/api/statistiques)

##### GET /api/statistiques/dashboard
**Description** : Stats complètes pour le dashboard

##### GET /api/statistiques/ca/comparatif
**Description** : CA jour/semaine/mois/année

##### GET /api/statistiques/ponts/occupation
**Description** : Taux d'occupation des ponts

##### GET /api/statistiques/mecaniciens/productivite
**Description** : Productivité par mécanicien

##### GET /api/statistiques/interventions/top
**Description** : Top interventions

##### GET /api/statistiques/clients/fideles
**Description** : Clients les plus fidèles

---

#### Stock / Pièces détachées

##### GET /api/pieces
**Description** : Liste des pièces avec filtres
**Query params** : `search`, `categorie`, `stock_bas`

##### GET /api/pieces/alertes
**Description** : Pièces en alerte stock bas

##### POST /api/pieces
**Description** : Crée une pièce

##### PUT /api/pieces/{id}
**Description** : Met à jour une pièce

##### POST /api/pieces/{id}/ajuster-stock
**Description** : Ajuste le stock d'une pièce

---

#### Fournisseurs

##### GET /api/fournisseurs
**Description** : Liste des fournisseurs

##### POST /api/fournisseurs
**Description** : Crée un fournisseur

##### PUT /api/fournisseurs/{id}
**Description** : Met à jour un fournisseur

---

#### Commandes Fournisseurs

##### GET /api/commandes
**Description** : Liste des commandes

##### POST /api/commandes
**Description** : Crée une commande

##### POST /api/commandes/{id}/receptionner
**Description** : Réceptionne une commande et met à jour les stocks

---

#### Devis

##### GET /api/devis
**Description** : Liste les devis

##### POST /api/devis
**Description** : Crée un devis

##### POST /api/devis/calculer
**Description** : Calcule les totaux sans sauvegarder

##### POST /api/devis/{id}/convertir-rdv
**Description** : Convertit un devis accepté en RDV

---

#### Configuration

##### GET /api/config/atelier
**Description** : Configuration globale de l'atelier

##### PUT /api/config/atelier
**Description** : Met à jour la configuration

##### GET /api/config/taux-mo
**Description** : Taux horaires MO (public)

---

## Authentification

### JWT Token
- **Durée** : 30 minutes
- **Algorithme** : HS256
- **Secret** : Variable d'environnement `SECRET_KEY`

### Rôles
| Rôle | Permissions |
|------|-------------|
| admin | Toutes les opérations |
| receptionnaire | CRUD RDV, clients, véhicules, devis |
| mecanicien | Lecture RDV, mise à jour statut, planning |

### Sécurité
- Mots de passe hashés avec **bcrypt** (cost=12)
- CORS limité aux domaines autorisés
- Pas de stockage de tokens en clair

---

## Frontend

### Charte Graphique Unifiée (v2.0)

#### Couleurs
```css
:root {
    --primary: #ffd700;        /* Jaune - Actions principales */
    --secondary: #1a1a1a;      /* Noir - Header, texte */
    --background: #f8f9fa;     /* Gris clair - Fond */
    --surface: #ffffff;        /* Blanc - Cartes */
    --border: #e9ecef;         /* Gris bordure */
    --text-primary: #1a1a1a;   /* Texte principal */
    --text-secondary: #6c757d; /* Texte secondaire */
    
    /* Statuts */
    --status-attente: #f59e0b;
    --status-confirme: #10b981;
    --status-cours: #3b82f6;
    --status-termine: #6b7280;
    --status-annule: #ef4444;
}
```

### Pages

#### 1. Interface Publique - Prise de RDV (`/rendez-vous.html`)
- **Accès** : Public (sans authentification)
- **Fonctionnalités** :
  - Recherche véhicule par plaque (API + mock 50+ motos)
  - Formulaire création véhicule si plaque inconnue
  - Sélection des prestations
  - Choix du créneau horaire (calendrier interactif)
  - Formulaire client
  - Récapitulatif et confirmation

#### 2. Dashboard (`/index.html`)
- **Accès** : Authentifié
- **Fonctionnalités** :
  - Vue d'ensemble avec statistiques
  - Liste des RDV récents
  - Navigation vers les autres sections

#### 3. Planning (`/planning.html`)
- **Accès** : Authentifié
- **Fonctionnalités** :
  - Vue jour avec grille horaire
  - Vue semaine
  - Assignation ponts et mécaniciens
  - Drag & drop des RDV
  - Ligne temps réel

#### 4. Clients (`/clients.html`)
- **Accès** : Authentifié
- **Fonctionnalités** :
  - Liste des clients avec recherche
  - Fiche détaillée par client
  - Historique des RDV
  - Véhicules associés
  - Statistiques client (CA total, nb RDV)

#### 5. Admin (`/admin.html`)
- **Accès** : Admin
- **Fonctionnalités** :
  - Gestion des ponts
  - Gestion des mécaniciens
  - Configuration atelier
  - Gestion des forfaits MO

---

## Workflow des Statuts

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│  en_attente │────▶│  confirmé   │────▶│  en_cours   │────▶│   terminé   │
└─────────────┘     └─────────────┘     └─────────────┘     └──────┬──────┘
      │                                                              │
      │         (création depuis                                      │
      └──────────l'interface publique)                               │
                                                                     ▼
                                                              ┌─────────────┐
                                                              │   facturé   │
                                                              └──────┬──────┘
                                                                     │
                                                                     ▼
                                                              ┌─────────────┐
                                                              │    payé     │
                                                              └─────────────┘

┌─────────────┐
│   annulé    │◀──── (depuis n'importe quel statut sauf payé)
└─────────────┘
```

### Transitions possibles
- `en_attente` → `confirme`, `annule`
- `confirme` → `en_cours`, `annule`
- `en_cours` → `termine`, `annule`
- `termine` → `facture`
- `facture` → `paye`

---

## Agents en Parallèle

Le système intègre plusieurs agents spécialisés fonctionnant en parallèle :

### 1. Agent OR (Ordre de Réparation)
- **Statut** : ✅ Fonctionnel
- **Fonction** : Génération de PDF OR avec mentions légales
- **Endpoint** : `GET /api/rendez-vous/{id}/ordre-reparation`
- **Features** :
  - En-tête avec numéro OR
  - Informations client et véhicule
  - Détails de l'intervention
  - État du véhicule à l'arrivée
  - Signatures (client + atelier)
  - CGV en annexe

### 2. Agent Statistiques
- **Statut** : ✅ Fonctionnel
- **Fonction** : Rapports et analyses de performance
- **Endpoints** :
  - `/api/statistiques/dashboard` - Vue d'ensemble
  - `/api/statistiques/ca/comparatif` - CA périodique
  - `/api/statistiques/ponts/occupation` - Occupation ponts
  - `/api/statistiques/mecaniciens/productivite` - Performance mécanos
  - `/api/statistiques/interventions/top` - Top interventions
  - `/api/statistiques/clients/fideles` - Clients fidèles

### 3. Agent Tarifs
- **Statut** : 🚧 En cours de développement
- **Fonction** : Gestion dynamique des tarifs et forfaits
- **Features prévues** :
  - Grille tarifaire par cylindrée
  - Forfaits MO configurables
  - Calcul automatique des devis
  - Marges sur pièces paramétrables

---

## Déploiement

### Prérequis
- Docker
- Docker Compose
- Domaine configuré (nicebot.duckdns.org)

### Commandes
```bash
# Build et démarrage
cd /root/.openclaw/workspace/atelier-moto
docker compose up -d --build

# Logs
docker logs -f atelier-backend

# Redémarrage
docker restart atelier-backend
```

### Variables d'environnement
Créer fichier `.env` :
```bash
SECRET_KEY=votre-cle-secrete-32-caracteres-min
DATABASE_URL=postgresql://atelier:atelier@db:5432/atelier_moto
CORS_ORIGINS=https://nicebot.duckdns.org,http://localhost:3000
API_PLAQUE_IMMATRICULATION_KEY=votre_cle_api
```

### Docker Compose
```yaml
version: '3.8'

services:
  db:
    image: postgres:15
    environment:
      POSTGRES_USER: atelier
      POSTGRES_PASSWORD: atelier
      POSTGRES_DB: atelier_moto
    volumes:
      - postgres_data:/var/lib/postgresql/data
    ports:
      - "5432:5432"

  backend:
    build: ./backend
    environment:
      - DATABASE_URL=postgresql://atelier:atelier@db:5432/atelier_moto
      - SECRET_KEY=${SECRET_KEY}
      - CORS_ORIGINS=${CORS_ORIGINS}
    ports:
      - "8000:8000"
    depends_on:
      - db
    volumes:
      - ./frontend:/app/static

volumes:
  postgres_data:
```

---

## URLs de Production

| Service | URL | Description |
|---------|-----|-------------|
| Dashboard | https://nicebot.duckdns.org | Interface administration |
| RDV Public | https://nicebot.duckdns.org/rendez-vous.html | Prise de RDV client |
| Planning | https://nicebot.duckdns.org/planning.html | Planning mécanicien |
| API | https://nicebot.duckdns.org/api | Endpoints API |

### Comptes de test
| Rôle | Login | Mot de passe |
|------|-------|--------------|
| Admin | admin | admin123 |
| Réception | reception | reception123 |
| Mécanicien | mecano | mecano123 |

---

## Système Mock Véhicules

L'application dispose d'une base de véhicules mock étendue (50+ motos) pour la pré-production :

### Marques supportées
- YAMAHA (MT-07, MT-09, TMAX, XSR, etc.)
- HONDA (CBR, CB, Africa Twin, etc.)
- KAWASAKI (Z900, Ninja, Versys, etc.)
- SUZUKI (GSX-S, SV650, V-Strom, etc.)
- BMW (F900R, R1250GS, etc.)
- DUCATI, KTM, TRIUMPH, HARLEY-DAVIDSON
- VESPA, PIAGGIO (scooters)

### Plaques mock disponibles
- `AA-123-BB` → YAMAHA MT-07 2020
- `CC-456-DD` → HONDA CBR650R 2022
- `EE-789-FF` → KAWASAKI Z900 2021
- `GG-012-HH` → SUZUKI GSX-S750 2019
- `II-345-JJ` → BMW F900R 2023
- `AP-304-RM` → YAMAHA TMAX 2022
- Et 45+ autres véhicules...

Si une plaque n'est pas trouvée, un formulaire de création manuelle est proposé.

---

## Évolution

### Roadmap v2.1
- [ ] Notifications email/SMS
- [ ] Application mobile PWA
- [ ] Intégration API SIV réelle
- [ ] Signature électronique OR
- [ ] Photos état des lieux

### Roadmap v2.2
- [ ] Multi-atelier (SaaS)
- [ ] Gestion des garanties constructeur
- [ ] Rappels entretien automatiques
- [ ] Intégration comptabilité

---

## Notes de développement

### Conventions de code
- Python : PEP 8
- JavaScript : ES6+
- HTML : Sémantique, accessibilité
- CSS : Mobile-first, variables CSS

### Tests
```bash
# Lancer les tests
cd backend
pytest tests/

# Tests spécifiques
pytest tests/test_api.py
pytest tests/test_models.py
pytest tests/test_auth.py
```

### Debugging
- Logs backend : `docker logs atelier-backend`
- Console navigateur : F12
- Network tab pour voir les appels API

---

**Dernière mise à jour** : 2026-03-23
**Version** : 2.0
**Auteur** : NiceBot
