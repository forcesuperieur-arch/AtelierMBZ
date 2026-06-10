# User Stories MVP — AtelierMBZ

## Sommaire

1. [Auth & Sécurité](#1-auth--sécurité)
2. [Dashboard](#2-dashboard)
3. [Rendez-vous](#3-rendez-vous)
4. [Planning](#4-planning)
5. [Clients](#5-clients)
6. [Atelier / Workshop](#6-atelier--workshop)
7. [Stock](#7-stock)
8. [Facturation](#8-facturation)
9. [Catalogue & Tarifs](#9-catalogue--tarifs)
10. [Administration](#10-administration)
11. [VO — Véhicules d'Occasion](#11-vo--véhicules-doccasion)
12. [Public](#12-public)
13. [Design System & Non-Régression](#13-design-system--non-régression)

---

## 1. Auth & Sécurité

### US-AUTH-01 — Login avec identifiants locaux
**As a** utilisateur  
**I want** me connecter avec mon email et mot de passe  
**So that** j'accède à mon espace atelier

**Critères d'acceptation**
- [ ] La page `/login` affiche le formulaire de connexion (email, password, bouton)
- [ ] La connexion avec des identifiants valides redirige vers le dashboard
- [ ] La connexion avec des identifiants invalides affiche un message d'erreur
- [ ] Un cookie JWT `access_token` HttpOnly est déposé après connexion réussie

**Test**
1. Aller sur `/login`
2. Remplir `admin@atelier.local` / `Admin123!`
3. Cliquer "Se connecter"
4. Vérifier la redirection vers `/`

---

### US-AUTH-02 — Connexion SSO Google
**As a** utilisateur  
**I want** me connecter avec mon compte Google  
**So that** je n'ai pas à mémoriser un mot de passe spécifique

**Critères d'acceptation**
- [ ] Le bouton "Continuer avec Google" est visible sur `/login`
- [ ] Le flux OAuth redirige vers Google puis revient sur l'application
- [ ] Un nouvel utilisateur Google est créé avec statut `pending_validation`

---

### US-AUTH-03 — Redirection des non-authentifiés
**As a** visiteur non authentifié  
**I want** être redirigé vers la page de login  
**So that** je ne peux pas accéder aux pages protégées

**Critères d'acceptation**
- [ ] Toute page protégée (`/`, `/rdv`, `/planning`, etc.) redirige vers `/login`
- [ ] Les pages publiques (`/public/*`) restent accessibles sans auth

---

### US-AUTH-04 — Permissions par rôle
**As a** administrateur  
**I want** restreindre l'accès aux fonctionnalités selon le rôle  
**So that** un mécanicien ne puisse pas accéder à la facturation

**Critères d'acceptation**
- [ ] Le RBAC dual (legacy + `RoleMetier`) est fonctionnel
- [ ] Un utilisateur sans permission sur un module voit une erreur 403 ou une page vide
- [ ] Les permissions sont vérifiées côté API (pas seulement UI)

---

## 2. Dashboard

### US-DASH-01 — Vue d'ensemble des KPIs
**As a** gestionnaire d'atelier  
**I want** voir les indicateurs clés (CA, marge, taux d'occupation)  
**So that** je prends des décisions éclairées

**Critères d'acceptation**
- [ ] La page `/` affiche les stat cards (CA, marge, RDV du jour, taux occupation)
- [ ] Les KPIs sont calculés sur la période sélectionnée
- [ ] La comparaison avec la période précédente est visible

---

### US-DASH-02 — Filtres de période
**As a** gestionnaire  
**I want** changer la période d'analyse (aujourd'hui, semaine, mois, année)  
**So that** j'adapte la vue à mes besoins

**Critères d'acceptation**
- [ ] Les presets (Aujourd'hui, Semaine, Mois, Année, Personnalisé) sont cliquables
- [ ] La période sélectionnée met à jour les KPIs
- [ ] Les dates personnalisées (from/to) fonctionnent

---

### US-DASH-03 — Alertes et notifications
**As a** réceptionniste  
**I want** voir les alertes (RDV en retard, stock bas, etc.)  
**So that** je réagis rapidement

**Critères d'acceptation**
- [ ] Une alert strip s'affiche en haut du dashboard quand il y a des alertes
- [ ] Les alertes sont catégorisées (danger, warning, info)
- [ ] Le clic sur une alerte redirige vers la page concernée

---

## 3. Rendez-vous

### US-RDV-01 — Liste des rendez-vous
**As a** réceptionniste  
**I want** consulter la liste des RDV  
**So that** je gère les arrivées clients

**Critères d'acceptation**
- [ ] La page `/rdv` affiche une liste paginée des RDV
- [ ] Les filtres (date, statut, client, mécanicien) fonctionnent
- [ ] Le statut de chaque RDV est visible (badge coloré)

---

### US-RDV-02 — Création d'un rendez-vous
**As a** réceptionniste  
**I want** créer un nouveau RDV  
**So that** je planifie une intervention

**Critères d'acceptation**
- [ ] Le bouton "Nouveau RDV" ouvre un formulaire (/rdv/new ou modal)
- [ ] Les champs obligatoires (client, véhicule, date, type) sont requis
- [ ] La création via API retourne 201
- [ ] Le RDV apparaît dans la liste après création

---

### US-RDV-03 — Détail d'un rendez-vous
**As a** mécanicien  
**I want** consulter le détail d'un RDV  
**So that** je connais les travaux à effectuer

**Critères d'acceptation**
- [ ] La page `/rdv/:id` affiche les infos du RDV
- [ ] Les informations client et véhicule sont visibles
- [ ] Les travaux prévus et pièces associées sont listés

---

### US-RDV-04 — Changement de statut
**As a** mécanicien  
**I want** changer le statut d'un RDV  
**So that** je reflète l'avancement réel

**Critères d'acceptation**
- [ ] Les transitions de statut (`en_attente` → `en_cours` → `termine`) sont possibles
- [ ] Un statut finalisé bloque certaines modifications
- [ ] L'historique des statuts est tracé

---

## 4. Planning

### US-PLAN-01 — Vue calendrier
**As a** réceptionniste  
**I want** voir le planning sous forme de calendrier  
**So that** j'optimise l'occupation des ponts

**Critères d'acceptation**
- [ ] La page `/planning` affiche une grille horaire × ponts
- [ ] Les RDV sont représentés par des cartes colorées
- [ ] La navigation semaine précédente / suivante fonctionne

---

### US-PLAN-02 — Assignation pont/mécanicien
**As a** réceptionniste  
**I want** assigner un RDV à un pont et un mécanicien  
**So that** les ressources sont optimisées

**Critères d'acceptation**
- [ ] Le drag & drop d'un RDV sur un pont fonctionne
- [ ] La confirmation d'assignation met à jour le RDV
- [ ] Les conflits (double assignation) sont signalés

---

## 5. Clients

### US-CLI-01 — Liste des clients
**As a** réceptionniste  
**I want** consulter la liste des clients  
**So that** je retrouve rapidement un client

**Critères d'acceptation**
- [ ] La page `/clients` affiche une liste avec recherche
- [ ] La recherche fonctionne sur nom, prénom, téléphone, email
- [ ] La pagination fonctionne

---

### US-CLI-02 — Fiche client
**As a** réceptionniste  
**I want** consulter la fiche détaillée d'un client  
**So that** je vois son historique

**Critères d'acceptation**
- [ ] La page `/clients/:id` affiche les coordonnées du client
- [ ] L'historique des RDV et véhicules est visible
- [ ] Les consentements RGPD sont affichés

---

## 6. Atelier / Workshop

### US-ATEL-01 — Occupation des ponts
**As a** chef d'atelier  
**I want** voir l'occupation des ponts en temps réel  
**So that** je répartis la charge

**Critères d'acceptation**
- [ ] La page `/workshop` affiche les ponts avec leur statut (libre/occupé)
- [ ] Les RDV en cours sont visibles sur chaque pont
- [ ] Le temps restant estimé est affiché

---

### US-ATEL-02 — Ordres de réparation
**As a** mécanicien  
**I want** consulter et signer les ordres de réparation  
**So that** je valide les travaux avec le client

**Critères d'acceptation**
- [ ] La page `/ordres` liste les OR
- [ ] Le détail `/ordres/:id` affiche les travaux, pièces, et prix
- [ ] La signature client est possible (tablette/tactile)
- [ ] Une fois signé, l'OR est figé (snapshot RGPD)

---

### US-ATEL-03 — Demandes de travaux complémentaires
**As a** mécanicien  
**I want** créer une demande de travaux supplémentaires  
**So that** le client valide des travaux additionnels

**Critères d'acceptation**
- [ ] Une demande peut être créée depuis un RDV en cours
- [ ] Le client reçoit un email/SMS avec un lien de validation
- [ ] Le lien public `/public/demande/:token` permet d'accepter/refuser

---

### US-ATEL-04 — Rapport technicien
**As a** mécanicien  
**I want** remplir un rapport d'intervention  
**So that** je documente les travaux réalisés

**Critères d'acceptation**
- [ ] La page `/rapport/:rdvId` affiche le formulaire de rapport
- [ ] Les points de contrôle sont cochables
- [ ] Les photos d'intervention peuvent être uploadées

---

### US-ATEL-05 — Essai routier
**As a** mécanicien  
**I want** enregistrer un essai routier  
**So that** je valide le bon fonctionnement du véhicule

**Critères d'acceptation**
- [ ] Le formulaire d'essai routier est accessible depuis le RDV
- [ ] Les 10 points de contrôle sont vérifiables
- [ ] Les anomalies détectées bloquent la clôture du RDV

---

## 7. Stock

### US-STOCK-01 — Catalogue des pièces
**As a** magasinier  
**I want** consulter le catalogue des pièces détachées  
**So that** je gère les stocks

**Critères d'acceptation**
- [ ] La page `/stock` affiche la liste des pièces
- [ ] Les alertes de stock bas sont visibles (badge rouge)
- [ ] La recherche par référence ou nom fonctionne

---

### US-STOCK-02 — Commandes fournisseurs
**As a** magasinier  
**I want** passer des commandes à mes fournisseurs  
**So that** je réapprovisionne le stock

**Critères d'acceptation**
- [ ] Une commande peut être créée avec un fournisseur
- [ ] Les lignes de commande lient une pièce et une quantité
- [ ] Le statut de la commande évolue (`en_attente` → `commandee` → `recue`)

---

## 8. Facturation

### US-FACT-01 — Devis
**As a** réceptionniste  
**I want** créer et envoyer des devis  
**So that** le client approuve les travaux avant intervention

**Critères d'acceptation**
- [ ] La page `/devis` liste les devis
- [ ] Un devis peut être créé à partir d'un RDV
- [ ] Les lignes de devis (prestations, pièces, MO) sont calculées
- [ ] Le devis peut être envoyé par email au client

---

### US-FACT-02 — Factures
**As a** comptable  
**I want** éditer des factures  
**So that** je suis réglé pour les travaux effectués

**Critères d'acceptation**
- [ ] La page `/facturation` liste les factures
- [ ] Une facture peut être générée à partir d'un RDV terminé
- [ ] Les paiements sont enregistrés et liés à la facture
- [ ] Les factures ont un statut (`emise`, `payee`, `impayee`, `annulee`)

---

### US-FACT-03 — Snapshots RGPD
**As a** DPO  
**I want** que les devis et factures conservent une copie figée des données client  
**So that** je respecte la réglementation RGPD même si le client est modifié

**Critères d'acceptation**
- [ ] Les champs `snapClient*` sont remplis à la création
- [ ] Modifier le client après coup ne change pas le devis/facture existant
- [ ] Les snapshots sont inclus dans les exports PDF

---

## 9. Catalogue & Tarifs

### US-CAT-01 — Modèles moto
**As a** réceptionniste  
**I want** consulter le catalogue des modèles moto  
**So that** j'accélère la saisie véhicule

**Critères d'acceptation**
- [ ] La page `/motos` affiche la liste des modèles
- [ ] Les filtres (catégorie, recherche) fonctionnent
- [ ] Le détail d'un modèle s'affiche dans une modal
- [ ] **SSR-safe** : aucun crash quand `selectedModel` est null

---

### US-CAT-02 — Grille tarifaire
**As a** chef d'atelier  
**I want** gérer les tarifs des prestations  
**So that** mes devis sont cohérents

**Critères d'acceptation**
- [ ] La page `/tarifs` affiche la grille tarifaire
- [ ] Les prix sont affichés par catégorie de moto et cylindrée
- [ ] La modification d'un tarif met à jour les devis futurs (pas les passés)

---

### US-CAT-03 — Prestations
**As a** administrateur  
**I want** gérer le catalogue des prestations  
**So that** je standardise les interventions

**Critères d'acceptation**
- [ ] La page `/admin/prestations` liste les prestations
- [ ] CRUD complet (créer, modifier, supprimer)
- [ ] Les prestations sont liées à la grille tarifaire

---

## 10. Administration

### US-ADMIN-01 — Gestion des utilisateurs
**As a** superadmin  
**I want** créer et gérer les comptes utilisateurs  
**So that** chaque employé a les bons accès

**Critères d'acceptation**
- [ ] La page `/admin/users` liste les utilisateurs
- [ ] Modal de création avec formulaire complet
- [ ] Le profil d'accès (`USelectMenu`) fonctionne avec la syntaxe v3
- [ ] Le footer de modal (`PitModalFooter`) est standardisé

---

### US-ADMIN-02 — Gestion des ponts
**As a** administrateur  
**I want** configurer les ponts de l'atelier  
**So that** le planning reflète la capacité réelle

**Critères d'acceptation**
- [ ] La page `/admin/ponts` liste les ponts
- [ ] CRUD pont avec assignation mécanicien
- [ ] L'ordre d'affichage est configurable

---

### US-ADMIN-03 — Rôles et permissions
**As a** superadmin  
**I want** définir des rôles métier granulaires  
**So that** je contrôle finement les accès

**Critères d'acceptation**
- [ ] La page `/admin/roles-metier` liste les rôles
- [ ] L'héritage de permissions fonctionne (`heriteDe`)
- [ ] Chaque rôle a des entrées `RolePermissionEntry` par module/action

---

### US-ADMIN-04 — Absences
**As a** chef d'atelier  
**I want** déclarer les absences des mécaniciens  
**So that** le planning tient compte des indisponibilités

**Critères d'acceptation**
- [ ] La page `/admin/absences` liste les absences
- [ ] Une absence bloque le planning sur la période
- [ ] Les types d'absence sont paramétrables

---

### US-ADMIN-05 — Configuration atelier
**As a** administrateur  
**I want** configurer les paramètres de mon atelier  
**So that** l'application s'adapte à ma structure

**Critères d'acceptation**
- [ ] La page `/admin/config` affiche les paramètres
- [ ] Les taux horaires, TVA, marges sont configurables
- [ ] Les modules activés/désactivés sont persistés

---

### US-ADMIN-06 — Audit log
**As a** DPO / admin  
**I want** consulter l'historique des actions sensibles  
**So that** je trace les modifications

**Critères d'acceptation**
- [ ] La page `/admin/audit` liste les logs
- [ ] Les actions (création, modification, suppression) sont tracées
- [ ] Le filtre par utilisateur et date fonctionne

---

### US-ADMIN-07 — Notifications providers
**As a** administrateur  
**I want** configurer les providers de notifications  
**So that** mes utilisateurs reçoivent des alertes

**Critères d'acceptation**
- [ ] La page `/admin/notifications/providers` liste les providers
- [ ] La configuration SMTP, SMS, Slack est possible
- [ ] Le test d'envoi fonctionne

---

### US-ADMIN-08 — Templates de documents
**As a** administrateur  
**I want** personnaliser les templates d'emails et documents  
**So that** ma communication est cohérente

**Critères d'acceptation**
- [ ] La page `/admin/templates-documents` liste les templates
- [ ] L'édition avec variables dynamiques fonctionne
- [ ] L'aperçu du rendu est disponible

---

### US-ADMIN-09 — Clauses légales
**As a** administrateur  
**I want** gérer les clauses légales des documents  
**So that** mes devis et factures sont conformes

**Critères d'acceptation**
- [ ] La page `/admin/clauses-legales` liste les clauses
- [ ] Les clauses sont injectées dans les PDF devis/factures

---

## 11. VO — Véhicules d'Occasion

### US-VO-01 — Dashboard VO
**As a** responsable VO  
**I want** voir les indicateurs du parc VO  
**So that** je pilote l'activité occasion

**Critères d'acceptation**
- [ ] La page `/vo` affiche les KPIs (stock, ventes, marge)
- [ ] Les véhicules en stock, réservés, vendus sont comptabilisés

---

### US-VO-02 — Rachats
**As a** responsable VO  
**I want** enregistrer un rachat de moto  
**So that** j'alimente mon stock occasion

**Critères d'acceptation**
- [ ] La page `/vo/rachats` liste les rachats
- [ ] Le formulaire de création inclut les infos vendeur, prix, SIV
- [ ] Le statut évolue (`brouillon` → `en_stock` → `en_vente` → `vendu`)

---

### US-VO-03 — Dépôts-vente
**As a** responsable VO  
**I want** gérer les dépôts-vente  
**So that** je vends des motos pour des clients

**Critères d'acceptation**
- [ ] La page `/vo/depots` liste les dépôts-vente
- [ ] La commission et la durée de mandat sont configurables
- [ ] L'expiration du mandat est signalée

---

### US-VO-04 — Remises en état
**As a** responsable VO  
**I want** planifier des campagnes de remise en état  
**So that** les motos sont vendables

**Critères d'acceptation**
- [ ] La page `/vo/remises-en-etat` liste les campagnes
- [ ] Les lignes de MO et pièces sont chiffrées
- [ ] Le workflow (`a_chiffrer` → `terminee`) guide le processus

---

### US-VO-05 — Livre de police
**As a** responsable VO  
**I want** tenir un livre de police immuable  
**So that** je respecte l'article 321-7 du Code Pénal

**Critères d'acceptation**
- [ ] La page `/vo/livre-police` affiche le registre
- [ ] Les entrées sont en lecture seule (pas de modification)
- [ ] L'API rejette les requêtes PUT/PATCH/DELETE avec 405

---

### US-VO-06 — Facturation VO
**As a** comptable  
**I want** éditer des factures VO  
**So that** les ventes occasion sont comptabilisées

**Critères d'acceptation**
- [ ] La page `/vo/factures` liste les factures VO
- [ ] Le régime TVA marge est appliqué
- [ ] Les snapshots RGPD sont préservés

---

### US-VO-07 — Documents VO
**As a** responsable VO  
**I want** attacher des documents à une transaction  
**So that** le dossier est complet

**Critères d'acceptation**
- [ ] La page `/vo/documents` permet l'upload
- [ ] Les types de documents (CERFA, carte grise, contrôle technique) sont prédéfinis
- [ ] Les documents sont associés à un achat ou dépôt-vente

---

## 12. Public

### US-PUB-01 — Prise de rendez-vous en ligne
**As a** client  
**I want** prendre rendez-vous en ligne  
**So that** je n'ai pas à appeler l'atelier

**Critères d'acceptation**
- [ ] La page `/public/booking` est accessible sans auth
- [ ] Le formulaire demande les infos client et véhicule
- [ ] La sélection d'atelier et de créneau horaire fonctionne
- [ ] Le RDV est créé en base avec statut `en_attente`

---

### US-PUB-02 — Suivi de réparation
**As a** client  
**I want** suivre l'avancement de ma réparation  
**So that** je sais quand récupérer ma moto

**Critères d'acceptation**
- [ ] La page `/public/suivi` demande un numéro de suivi
- [ ] Le statut du RDV et les photos d'intervention sont visibles
- [ ] Aucune donnée sensible n'est exposée (token sécurisé)

---

### US-PUB-03 — Companion client
**As a** client  
**I want** accéder à un espace companion  
**So that** je consulte mes RDV passés et futurs

**Critères d'acceptation**
- [ ] La page `/public/companion` est accessible avec un lien magique
- [ ] L'historique des interventions est lisible
- [ ] Les prochaines échéances (revision, CT) sont affichées

---

### US-PUB-04 — Companion VO
**As a** acheteur potentiel  
**I want** consulter le catalogue VO en ligne  
**So that** je trouve une moto d'occasion

**Critères d'acceptation**
- [ ] La page `/public/vo-companion` liste les motos en vente
- [ ] Les filtres (marque, prix, cylindrée) fonctionnent
- [ ] Le contact avec l'atelier est possible depuis la fiche

---

### US-PUB-05 — Validation demande travaux
**As a** client  
**I want** valider ou refuser des travaux complémentaires  
**So that** je contrôle la facture finale

**Critères d'acceptation**
- [ ] Le lien `/public/demande/:token` est valide 72h
- [ ] Le client voit la description, le prix estimé, et les photos
- [ ] L'acceptation/refus met à jour le statut dans l'atelier

---

### US-PUB-06 — Mentions légales & confidentialité
**As a** visiteur  
**I want** consulter les mentions légales  
**So that** je connais mes droits

**Critères d'acceptation**
- [ ] Les pages `/public/mentions-legales` et `/public/politique-confidentialite` chargent
- [ ] Le contenu est statique et accessible sans auth

---

## 13. Design System & Non-Régression

### US-DS-01 — Modal Design System v2
**As a** utilisateur  
**I want** des modales cohérentes et animées  
**So that** l'expérience est fluide

**Critères d'acceptation**
- [ ] Toutes les modales utilisent `AppModal` + `PitModalFooter`
- [ ] Pas de `UCard` interne dans les modales
- [ ] Les props `icon`, `iconColor`, `title`, `description` sont utilisées
- [ ] L'animation (scale + translateY) fonctionne

---

### US-DS-02 — Dropdown dans les modales
**As a** utilisateur  
**I want** voir les options du select au-dessus de la modale  
**So that** je peux sélectionner une valeur

**Critères d'acceptation**
- [ ] `USelectMenu` ouvert dans une modale affiche toutes les options
- [ ] Le z-index du dropdown (250) est supérieur à l'overlay (240)
- [ ] Le backdrop blur n'affecte pas la lisibilité

---

### US-DS-03 — Accessibilité des modales
**As a** utilisateur  
**I want** interagir avec les modales au clavier  
**So that** je n'ai pas besoin de souris

**Critères d'acceptation**
- [ ] `Escape` ferme la modale
- [ ] Le clic sur le backdrop ferme la modale
- [ ] Le focus reste dans la modale (focus trap)
- [ ] Le scroll du body est bloqué quand la modale est ouverte

---

### US-DS-04 — Syntaxe Nuxt UI v3
**As a** développeur  
**I want** que tous les selects utilisent la syntaxe v3  
**So that** il n'y a pas de warnings ni de régressions

**Critères d'acceptation**
- [ ] Tous les `USelectMenu` utilisent `:items` (pas `:options`)
- [ ] `label-key` et `value-key` sont utilisés (pas `option-attribute` / `value-attribute`)
- [ ] Aucune valeur vide `''` n'est passée dans les items (`null` + `placeholder` à la place)

---

## Checklist de validation finale

| Domaine | Pages P0 testées | Modales testées | API 200 |
|---------|-----------------|-----------------|---------|
| Auth | ☐ | — | ☐ |
| Dashboard | ☐ | — | ☐ |
| RDV | ☐ | ☐ | ☐ |
| Planning | ☐ | — | ☐ |
| Clients | ☐ | ☐ | ☐ |
| Atelier | ☐ | ☐ | ☐ |
| Stock | ☐ | ☐ | ☐ |
| Facturation | ☐ | ☐ | ☐ |
| Catalogue | ☐ | ☐ | ☐ |
| Admin | ☐ | ☐ | ☐ |
| VO | ☐ | ☐ | ☐ |
| Public | ☐ | — | ☐ |
| Design System | ☐ | ☐ | — |
