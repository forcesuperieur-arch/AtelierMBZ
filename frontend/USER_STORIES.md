# Paddock — User Stories & Cartographie Fonctionnelle

> Document produit par exploration autonome de l'application (Session 4 — Deep Dive).
> Date : 2026-05-15
> Basé sur : navigation réelle, lecture du code source, exécution E2E, et audit des 53 pages Vue.

---

## 1. Vue d'ensemble du produit

**Paddock** est un ERP web dédié aux ateliers moto / scooter / quad. Il couvre le cycle complet : prise de rendez-vous → réception → ordre de réparation → facturation → restitution, avec des modules optionnels pour le VO (véhicules d'occasion), le stock pièces détachées, et la gestion multi-atelier.

**Stack technique :**
- Backend : Symfony 7.2 + API Platform + PostgreSQL 15 + Mercure (SSE)
- Frontend : Nuxt 3.21 + Nuxt UI v3 + Tailwind CSS + Pinia
- Auth : JWT cookies HttpOnly + Google SSO

---

## 2. Personas & Rôles

| Persona | Rôle technique | Accès principal |
|---------|---------------|-----------------|
| **Super Admin** | `ROLE_SUPER_ADMIN` | Tous les modules + configuration système |
| **Gérant d'atelier** | `ROLE_ADMIN` ou profil métier `admin` | Dashboard, RDV, OR, facturation, clients, admin limité |
| **Mécanicien** | `ROLE_MECANICIEN` ou profil `mecanicien` | Espace mécanicien, ponts & méca, RDV qui lui sont assignés |
| **VO Manager** | `ROLE_VO_MANAGER` | Module VO (rachats, dépôts-vente, remises en état) |
| **Client final** | Aucun compte | Booking public, suivi de RDV par token, companion VO |
| **Comptable / SI** | Rôle lecture | Exports CSV, audit, templates documents |

---

## 3. Modules fonctionnels — User Stories détaillées

### 3.1 🏠 Dashboard / Statistiques (`/`)

> **Objectif** : Donner au gérant une vue synthétique de la santé de l'atelier en temps réel.

#### US-DASH-01 — Vue KPI période
**En tant que** gérant  
**Je veux** voir les KPIs sur une période sélectionnable (aujourd'hui, 7j, 30j, 90j, ou date custom)  
**Afin de** comparer avec la période précédente et détecter les tendances.

**KPIs affichés :**
- RDV sur période (nombre + % vs période précédente)
- CA sur période (€ + %)
- Panier moyen (€)
- Occupation capacité (% + ratio ponts occupés)
- Charge planifiée (heures + %)
- Dossiers clôturés (nombre + %)
- Alertes pilotage (retards, attentes, restitutions)
- Impayés / stock (relances comptables)

**Détails techniques :**
- Période analysée affichée avec comparatif automatique
- Graphique d'évolution quotidienne sur la période
- Mix rentabilité atelier (Main d'œuvre vs Pièces vs Factures)
- Performance mécanos (interventions, minutes moyennes, CA)
- Catégories de prestations (passages, heures, potentiel)
- Répartition des statuts
- Occupation ressources atelier (par pont : statut, RDV restants)
- Synthèse pilotage (OR ouverts, RDV en cours, restitutions, activité/jour, charge/pont)

---

### 3.2 📅 Prise de Rendez-vous (`/rdv`, `/rdv/new`, `/rdv/[id]`)

> **Objectif** : Gérer le cycle de vie complet d'un RDV depuis la réservation jusqu'à la restitution.

#### US-RDV-01 — Liste des RDV
**En tant que** gérant ou réceptionniste  
**Je veux** voir la liste des RDV du jour avec filtres  
**Afin de** prioriser et suivre l'activité.

**Filtres :** Date, Statut (En attente, Réservé, Confirmé, Réception, En cours, Terminé, Restitué, Facturé, Payé, Annulé), Recherche (client, plaque, moto, intervention).

**KPIs en-tête :** Total jour, À traiter, En cours, Terminés.

#### US-RDV-02 — Création wizard (4 étapes)
**En tant que** réceptionniste  
**Je veux** créer un RDV via un parcours guidé  
**Afin de** ne rien oublier et proposer les bons créneaux.

**Étape 1 — Véhicule :**
- Recherche client interne (nom, prénom, téléphone, email)
- Identification moto par plaque d'immatriculation ou VIN
- Autocomplétion marque/modèle via API
- Saisie manuelle si non trouvé

**Étape 2 — Service :**
- Sélection du type d'intervention (Entretien, Réparation, Diagnostic, etc.)
- Choix des prestations dans le catalogue
- Estimation automatique durée/prix

**Étape 3 — Créneau :**
- Calendrier interactif avec disponibilités réelles
- Suggestion d'alternatives si créneau indisponible
- Affectation automatique pont/mécanicien selon charge

**Étape 4 — Validation :**
- Récapitulatif complet
- Envoi email/SMS de confirmation au client

#### US-RDV-03 — Fiche RDV détaillée
**En tant que** gérant ou mécanicien  
**Je veux** consulter et modifier un RDV  
**Afin de** suivre son avancement.

**Informations affichées :**
- Date, heure, type, pont, mécanicien, durée prévue
- Client & véhicule (avec lien vers fiche client)
- Notes internes éditables

**Actions rapides :**
- 📌 Réserver
- ✅ Confirmer
- 👤 Assigner mécanicien / pont
- ❌ Annuler

**Workflow visuel :**
- Timeline des statuts : En attente → Réservé → Confirmé → Réceptionné → En cours → En attente pièces → En attente reprise → Terminé → Restitution partielle → En gardiennage → Restitué

#### US-RDV-04 — Transitions de statut
**En tant que** système ou utilisateur  
**Je veux** que chaque transition de statut déclenche des actions (notifications, recalcul planning, génération documents)  
**Afin de** garantir la traçabilité.

---

### 3.3 🗓 Planning (`/planning`)

> **Objectif** : Visualiser et gérer l'occupation des ponts et des mécaniciens sur une vue hebdomadaire.

#### US-PLAN-01 — Vue grille hebdomadaire
**En tant que** gérant  
**Je veux** voir les RDV positionnés sur une grille horaire  
**Afin de** détecter les conflits et optimiser la charge.

**Affichage :**
- Jours de la semaine (Lun-Sam) avec nombre de RDV
- Créneaux horaires de 08:00 à 18:00 (pas de 15 min)
- Pause déjeuner visualisée (12:00-13:30)
- Légende des statuts avec couleurs
- Navigation semaine précédente / suivante / Aujourd'hui

**KPIs :** Charge visible (ponts occupés), Conflits, Sans affectation, Retards.

#### US-PLAN-02 — Création rapide depuis le planning
**En tant que** gérant  
**Je veux** cliquer sur un créneau vide pour créer un RDV pré-rempli  
**Afin de** gagner du temps.

**URL :** `/planning?create=1&date=YYYY-MM-DD&time=HH:mm&pontId=N`

#### US-PLAN-03 — Filtrage par mécanicien
**En tant que** gérant  
**Je veux** filtrer la vue par mécanicien  
**Afin de** gérer les plannings individuels.

---

### 3.4 🔧 Ponts & Méca / Atelier (`/workshop`)

> **Objectif** : Piloter les postes de travail (ponts) et la charge des mécaniciens en temps réel.

#### US-WORK-01 — Vue des ponts
**En tant que** gérant  
**Je veux** voir l'état de chaque pont  
**Afin de** réaffecter les ressources si besoin.

**Par pont :**
- Nom, type (Moto / Quad), capacité (kg)
- Statut (Planifié / Disponible / Inactif)
- Mécanicien rattaché (combobox avec affectation rapide)
- Bouton Désactiver/Activer
- Prochain passage (heure, client, intervention, véhicule)
- RDV du jour (liste horaire)
- Charge totale (minutes)

#### US-WORK-02 — Onglets
- 🔧 Ponts (vue ci-dessus)
- 👤 Mécaniciens (performance individuelle)
- ⏱ Temps par type (analyse temps réel vs estimé)
- 📅 Absences (congés, indisponibilités)

---

### 3.5 👥 Clients (`/clients`, `/clients/[id]`)

> **Objectif** : Gérer la base client et le carnet d'entretien des véhicules.

#### US-CLI-01 — Liste clients
**En tant que** réceptionniste  
**Je veux** rechercher et consulter les clients  
**Afin de** retrouver rapidement un dossier.

**KPIs :** Total clients, Avec RDV (% actifs), Total véhicules, CA total.

**Tableau :** Nom, Prénom, Téléphone, Email, Véhicules (nombre), Lien fiche.

#### US-CLI-02 — Fiche client détaillée
**En tant que** gérant  
**Je veux** voir l'historique complet d'un client  
**Afin de** personnaliser l'accueil et anticiper les besoins.

**Sections :**
- Coordonnées (modifiable)
- 🏍 Carnet Moto (liste des véhicules avec nombre de passages)
- 📅 Historique complet (tableau RDV : date, type, véhicule, mécanicien, statut)
- Statistiques (visites, motos, CA total, client depuis)
- 🔒 RGPD (exporter données, anonymiser)

#### US-CLI-03 — Création client rapide
**En tant que** réceptionniste  
**Je veux** créer un client minimal (nom, prénom, téléphone) en 2 clics  
**Afin de** ne pas bloquer la prise de RDV.

---

### 3.6 📋 Dossiers Atelier / Ordres de Réparation (`/ordres`, `/ordres/[id]`)

> **Objectif** : Gérer les OR (Ordres de Réparation) avec les travaux, les pièces, les photos et la signature client.

#### US-OR-01 — Liste des OR
**En tant que** gérant  
**Je veux** filtrer les OR par statut  
**Afin de** suivre la production.

**Filtres :** Tous, Réservé, Confirmé, Réception, En cours, Terminé, Restitué, Facturé.
**Action :** Export CSV.

#### US-OR-02 — Fiche OR
**En tant que** mécanicien ou gérant  
**Je veux** consulter et éditer un OR  
**Afin de** documenter l'intervention.

**Contenu (attendu, module workshop lié) :**
- Photos réception (état des lieux)
- Points de contrôle (checklist)
- Niveau carburant
- Observations client
- Travaux réalisés
- Pièces utilisées
- Signature client (canvas)
- Rapport d'intervention PDF

---

### 3.7 💰 Tarifs publics (`/tarifs`)

> **Objectif** : Afficher la grille tarifaire au client / mécanicien.

#### US-TAR-01 — Grille tarifaire
**En tant que** client ou mécanicien  
**Je veux** voir les prix des prestations  
**Afin de** connaître le coût estimé.

**Affichage :** Prestation, Catégorie, Temps estimé, Prix HT, Prix TTC.  
**Filtres :** Par catégorie, recherche texte.

---

### 3.8 📅 Espace Mécanicien (`/mecanicien`)

> **Objectif** : Donner au mécanicien une vue actionnable de sa journée.

#### US-MECA-01 — Vue personnelle
**En tant que** mécanicien  
**Je veux** voir mes interventions du jour  
**Afin de** organiser mon travail.

**KPIs :** En cours, À faire, Terminés, Journée (%).

#### US-MECA-02 — Intervention active
**En tant que** mécanicien  
**Je veux** gérer l'intervention en cours  
**Afin de** suivre le temps et valider les étapes.

**Actions :**
- 📞 Appeler le client (tel:)
- 📋 Ouvrir le dossier atelier
- 🏍 Valider essai routier
- 💾 Checkup / persistance rapport
- ✅ Terminer l'intervention

**Contexte réception :**
- OR signé ou à vérifier
- Km réception
- Priorité
- Carburant
- Points de contrôle

**Live chrono :**
- Compteur temps réel
- Barre de progression vs temps estimé
- Alertes si dépassement

---

### 3.9 🏍 VO — Véhicules d'Occasion (`/vo/*`)

> **Module optionnel** (désactivé par défaut). Gère le cycle rachat → remise en état → vente.

#### US-VO-01 — Dashboard VO
**En tant que** VO Manager  
**Je veux** voir le stock VO en un coup d'œil  
**Afin de** prioriser les véhicules à remettre en état.

**KPIs :** En stock, Vendus ce mois, Dépôts actifs, Alertes dossiers.

**Vue :** Stock prioritaire (rachats + dépôts) avec statut, prix, source.

#### US-VO-02 — Rachats (`/vo/rachats/*`)
**En tant que** VO Manager  
**Je veux** créer et suivre un dossier de rachat  
**Afin de** documenter l'acquisition.

**Workflow :**
1. Création rapide client + véhicule
2. OCR carte grise (Tesseract.js) — pré-remplissage auto
3. Comparaison OCR vs base dossier (alertes divergences)
4. Photos véhicule
5. Expertise / devis remise en état
6. Validation rachat → passage en stock

**Statuts :** Brouillon → En stock → En vente → Réservé → Vendu

#### US-VO-03 — Dépôts-vente (`/vo/depots/*`)
**En tant que** VO Manager  
**Je veux** gérer un contrat de dépôt-vente  
**Afin de** suivre la mandat et la vente.

**Workflow :**
- Création client → véhicule → contrat
- Mandat avec durée et prix de vente
- Prolongation de mandat
- Restitution ou vente

**Statuts :** Actif → Vendu → Restitué → Expiré

#### US-VO-04 — Remises en état (`/vo/remises-en-etat/*`)
**En tant que** VO Manager  
**Je veux** planifier et suivre les travaux sur un véhicule VO  
**Afin de** calculer la rentabilité.

**Contenu :**
- Campagnes de rénovation
- Lignes de travaux (quantité, statut, coût réel)
- Pièces nécessaires (commande, réception)
- Comparaison devis vs réel

#### US-VO-05 — Documents VO (`/vo/documents`, `/vo/factures`, `/vo/livre-police`)
- Génération CERFA (cession achat/vente)
- Carte grise, contrôle technique
- Factures VO
- Livre de police (traçabilité légale)

#### US-VO-06 — Companion public (`/public/vo-companion`)
**En tant que** client vendeur  
**Je veux** compléter mon dossier de vente en ligne  
**Afin de** gagner du temps à l'atelier.

**Parcours :**
- Token sécurisé
- Saisie pièce d'identité
- Vérification véhicule (plaque, VIN)
- Upload documents
- Signature électronique

---

### 3.10 📦 Stock — Pièces détachées (`/stock`)

> **Module optionnel** (désactivé par défaut). Gestion de stock simplifiée.

#### US-STOCK-01 — Vue stock
**En tant que** gérant  
**Je veux** voir les pièces en stock avec alertes  
**Afin de** commander à temps.

**Alertes :** Pièces sous seuil (quantité stock / seuil alerte).

**Tableau :** Référence, Désignation, Quantité stock, Seuil alerte, Prix, Fournisseur.

#### US-STOCK-02 — CRUD pièce
**En tant que** gérant  
**Je veux** ajouter/modifier une pièce  
**Afin de** maintenir le catalogue à jour.

**Champs :** Référence, Désignation, Quantité stock, Seuil alerte, Prix, Fournisseur.

---

### 3.11 ⚙ Administration (`/admin/*`)

> **Objectif** : Configurer l'atelier, gérer les utilisateurs, et piloter les paramètres métier.

#### US-ADM-01 — Utilisateurs (`/admin/users`)
**En tant que** Super Admin  
**Je veux** gérer les comptes utilisateurs  
**Afin de** contrôler les accès.

**Tableau :** Nom, Prénom, Login, Email, Auth (Local/Google), Statut, Rôle métier, Rôle système, Confirmation.

**Actions :** Modifier, Désactiver, Archiver RGPD.

#### US-ADM-02 — Configuration atelier (`/admin/config`)
**En tant que** Super Admin  
**Je veux** configurer l'identité et les règles de l'atelier  
**Afin de** personnaliser l'application.

**Assistant 6 étapes :**
1. **Atelier** : Nom, Téléphone, Email, SIRET, TVA intracom, CP, Ville, Adresse
2. **Logo** : Upload logo
3. **Horaires** : Ouverture/fermeture, jours ouvrés
4. **Types moto** : Catégories (scooter, moto, quad, etc.)
5. **Tarifs** : Taux horaire standard, TVA MO, TVA pièces, Acompte (%), Garantie (jours), Gardiennage/jour
6. **Modules** : Activation/désactivation des modules (dashboard, rdv, planning, workshop, clients, OR, devis, facturation, stock, mecanicien, absences, admin, tarifs, vo)

#### US-ADM-03 — Prestations (`/admin/prestations`)
**En tant que** Super Admin  
**Je veux** gérer le catalogue de prestations  
**Afin de** proposer les bons forfaits.

**Tableau :** Prestation, Catégorie, Durée, Prix HT, Type, Tarifs moto (configuré ou non).

**Actions :** Modifier, Tarifs moto (grille par catégorie de moto), Archiver.

**Filtrage :** Par catégorie (diagnostic, electricite, entretien, freinage, pneumatique, saisonnier, transmission, vo_test).

#### US-ADM-04 — Ponts & Mécanos (`/admin/ponts`)
**En tant que** Super Admin  
**Je veux** configurer les postes de travail  
**Afin de** adapter l'atelier à la flotte.

#### US-ADM-05 — Absences (`/admin/absences`)
**En tant que** Super Admin  
**Je veux** gérer les congés des mécaniciens  
**Afin de** bloquer les créneaux.

#### US-ADM-06 — Ateliers multi-sites (`/admin/ateliers`)
**En tant que** Super Admin  
**Je veux** créer et basculer entre plusieurs ateliers  
**Afin de** gérer un réseau.

#### US-ADM-07 — Profils d'accès (`/admin/roles`)
**En tant que** Super Admin  
**Je veux** créer des rôles simples (Admin, Mécanicien, Réceptionniste)  
**Afin de** standardiser les permissions.

#### US-ADM-08 — Rôles métier avancés (`/admin/roles-metier`)
**En tant que** Super Admin  
**Je veux** définir une matrice détaillée par module et action  
**Afin de** avoir un contrôle granulaire.

#### US-ADM-09 — Audit (`/admin/audit`)
**En tant que** Super Admin  
**Je veux** consulter le journal des actions  
**Afin de** tracer les modifications.

#### US-ADM-10 — Notifications (`/admin/notifications`)
**En tant que** Super Admin  
**Je veux** configurer les providers SMS/Email et les templates  
**Afin de** personnaliser les communications.

#### US-ADM-11 — Demandes complémentaires (`/admin/demandes-travaux-supp`)
**En tant que** Super Admin  
**Je veux** arbitrer les travaux supplémentaires proposés par les mécaniciens  
**Afin de** valider les dépassements de devis.

#### US-ADM-12 — Clauses légales (`/admin/clauses-legales`)
**En tant que** Super Admin  
**Je veux** gérer les CGV, mandats, mentions RGPD, gardiennage  
**Afin de** être conforme juridiquement.

#### US-ADM-13 — Templates documents (`/admin/templates-documents`)
**En tant que** Super Admin  
**Je veux** prévisualiser les templates PDF (OR, factures, VO)  
**Afin de** vérifier le rendu avant impression.

---

### 3.12 🔔 Notifications en temps réel

> **Objectif** : Informer les utilisateurs des événements critiques sans rechargement.

#### US-NOTIF-01 — Cloche de notifications
**En tant que** utilisateur connecté  
**Je veux** voir les notifications non lues et l'historique  
**Afin de** ne rien manquer.

**Transport :** Mercure SSE (avec fallback polling si indisponible).  
**Types :** Nouveau RDV, RDV annulé, OR en attente, demande travaux supp, alerte stock, etc.

---

### 3.13 🌐 Parcours publics (sans auth)

#### US-PUB-01 — Booking public (`/public/booking`)
**En tant que** client final  
**Je veux** réserver un RDV en ligne  
**Afin de** ne pas appeler l'atelier.

**Parcours (4 étapes) :**
1. Coordonnées (prénom, nom, téléphone, email)
2. Moto (marque, modèle, plaque, type d'intervention)
3. Prestations (sélection forfaits avec prix/durée)
4. Créneau (date + heure, avec alternatives si indisponible)

**Récapitulatif estimatif :** Intervention, durée, total.
**Confirmation :** Email avec code de suivi.

#### US-PUB-02 — Suivi de RDV (`/public/suivi?token=xxx`)
**En tant que** client final  
**Je veux** suivre l'avancement de mon RDV avec un code  
**Afin de** savoir quand récupérer ma moto.

#### US-PUB-03 — Companion VO (`/public/vo-companion?token=xxx`)
**En tant que** vendeur VO  
**Je veux** compléter mon dossier en ligne  
**Afin d'**accélérer la transaction.

---

## 4. Parcours critiques (Critical Paths)

### CP-01 — Prise de RDV complète (interne)
```
Dashboard → + Nouveau RDV → Recherche client → Identification moto
→ Sélection prestations → Choix créneau → Confirmation
→ Notification client (email/SMS)
```

### CP-02 — Journée type mécanicien
```
Espace Méca → Intervention en cours → Checkup → Essai routier
→ Terminer → OR signé → Restitution
```

### CP-03 — Rachat VO
```
VO → Nouveau rachat → Client + Véhicule → OCR carte grise
→ Photos → Expertise → Validation → Stock VO
```

### CP-04 — Booking public → Suivi
```
Public Booking → Formulaire → Créneau → Confirmation
→ Suivi par token → Restitution
```

---

## 5. Features identifiées — Gaps & Opportunités

### 🔴 Critique — Améliorations immédiates

| # | Feature / Gap | Impact | Proposition |
|---|--------------|--------|-------------|
| 1 | **Module VO & Stock désactivés par défaut** | Tests E2E VO échouent, fonctionnalités inaccessibles | Activer les modules dans les fixtures de test ou créer un seed dédié |
| 2 | **Pas de page Devis accessible** | Module `devis` désactivé, mais UI existe | Activer le module et vérifier l'intégration OR → Devis → Facture |
| 3 | **Facturation inaccessible** | Module `facturation` désactivé | Même problème — activer et tester le flow |
| 4 | **Planning public booking sans créneaux pour dates futures** | Test E2E échoue (date figée 2026-05-15) | Rendre la date du test dynamique ou seed des créneaux |

### 🟡 Haute — Améliorations produit

| # | Feature / Gap | Impact | Proposition |
|---|--------------|--------|-------------|
| 5 | **Dashboard CA à 0€ malgré 3 RDV** | KPIs financiers non alimentés | Vérifier le lien RDV → OR → Facture → CA |
| 6 | **Prestations sans tarifs moto configurés** | Prix public OK mais pas de grille par type moto | Wizard de config des tarifs par catégorie |
| 7 | **OCR carte grise Tesseract.js non lazy-loaded** | Chunk lourd au démarrage | Découper le worker en chunk séparé |
| 8 | **Pas de vue calendrier mensuel** | Uniquement semaine | Ajouter un toggle semaine/mois/jour |
| 9 | **Notifications SSE échouent sur :3000** | MIME type error quand pas via Caddy | Configurer le proxy Mercure dans nuxt.config |
| 10 | **Pas de recherche fuzzy dans le planning** | Difficile de trouver un RDV sur la grille | Ajouter un champ de recherche en overlay |

### 🟢 Moyenne — Optimisations UX

| # | Feature / Gap | Impact | Proposition |
|---|--------------|--------|-------------|
| 11 | **Fiche client : pas de carte ou géocodage** | Adresse brute | Intégrer Leaflet/OpenStreetMap pour localisation |
| 12 | **Pas de rappel automatique client** | Oublis de RDV | Cron + SMS/email de rappel J-1 |
| 13 | **Pas de statut "En attente pièces" géré** | Statut existe mais pas de logique métier | Lier au module stock et alertes fournisseur |
| 14 | **Pas d'intégration fournisseur pièces** | Saisie manuelle | Connecteurs Iggy / Parts Europe / etc. |
| 15 | **Mécanicien : pas de scan QR code sur OR** | Accès manuel | Générer un QR par OR pour scan rapide |
| 16 | **Pas de mode hors-ligne** | Atelier sans réseau = bloqué | Service Worker + IndexedDB pour cache local |

---

## 6. Matrice de priorité (MoSCoW)

### Must have (fonctionnel aujourd'hui)
- [x] Authentification (local + Google SSO)
- [x] Prise de RDV wizard (interne + public)
- [x] Planning hebdomadaire
- [x] Gestion des ponts et affectations
- [x] Fiches clients et véhicules
- [x] Espace mécanicien (interventions, chrono, essai)
- [x] Catalogue prestations avec tarifs
- [x] Notifications temps réel (Mercure)
- [x] Administration utilisateurs et rôles
- [x] Configuration atelier multi-étapes
- [x] Suivi public par token
- [x] RGPD (export, anonymisation)

### Should have (existe mais partiel/bloqué)
- [~] Module VO (désactivé, code complet)
- [~] Module Stock (désactivé, code complet)
- [~] Module Facturation (désactivé, code existe)
- [~] Module Devis (désactivé, code existe)
- [~] OR avec signature canvas (module workshop)
- [~] OCR carte grise (fonctionnel mais pas optimisé)

### Could have (améliorations identifiées)
- [ ] Calendrier mensuel/journalier
- [ ] Rappels automatiques SMS/email
- [ ] Géolocalisation clients
- [ ] Mode hors-ligne
- [ ] Intégration fournisseurs pièces
- [ ] QR code par OR
- [ ] App mobile PWA

### Won't have (hors scope actuel)
- Comptabilité complète (lien vers logiciel tiers)
- Marketing automation
- Place de marché pièces

---

## 7. Architecture des données (vue haute)

```
Client ──┬── Véhicule ──┬── RDV ──┬── OR (Ordre Réparation)
         │              │         │       ├── Photos
         │              │         │       ├── Travaux réalisés
         │              │         │       ├── Pièces utilisées
         │              │         │       └── Signature client
         │              │         └── Facture
         │              └── Historique entretien
         └── RGPD consentements

Atelier ──┬── Ponts ──┬── Mécaniciens
          │           └── Planning (slots)
          ├── Prestations (catalogue)
          ├── Tarifs (par type moto)
          └── Configuration (modules, branding, horaires)

VO ──┬── Rachats ──┬── Véhicule
     │             ├── Photos
     │             ├── OCR / Documents
     │             └── Remise en état
     ├── Dépôts-vente ──┬── Contrat mandat
     │                  └── Prolongation / Restitution
     └── Livre de police

Stock ── Pièces détachées ── Alertes seuil
```

---

## 8. Points de vigilance techniques (issus de l'audit)

1. **Module-level state pollution** : Le compteur `openModalCount` dans `AppModal.vue` est déclaré au niveau module mais pas exporté — risque si plusieurs instances.
2. **Playwright cookie isolation** : Les tests auth échouent en séquence car les cookies persistent. Nécessite `storageState` par test ou `test.use({ storageState: undefined })`.
3. **VO disabled by default** : `DEFAULT_FEATURE_MODULES.vo = false` — tous les tests VO échouent sans seed préalable.
4. **Mercure SSE MIME type** : Accès direct `:3000` sans Caddy = erreur MIME type sur les events SSE.
5. **Tesseract worker singleton** : `ocrWorkerPromise` est un module-level singleton mais pas nettoyé en cas d'erreur fatale.

---

*Document généré par exploration autonome. Dernière mise à jour : 2026-05-15.*
