# Guide Utilisateur — Atelier Moto Pro

> Application de gestion d'atelier moto multi-site  
> **Version** : 2.0

---

## Table des matières

1. [Connexion](#1-connexion)
2. [Dashboard](#2-dashboard)
3. [Prise de RDV](#3-prise-de-rdv)
4. [Planning](#4-planning)
5. [Ponts & Mécaniciens](#5-ponts--mécaniciens)
6. [Ordres de Réparation](#6-ordres-de-réparation)
7. [Suivi Live](#7-suivi-live)
8. [Clients](#8-clients)
9. [Espace Mécanicien](#9-espace-mécanicien)
10. [Facturation](#10-facturation)
11. [Administration](#11-administration)

---

## 1. Connexion

### Se connecter

1. Ouvrir l'application dans le navigateur
2. Entrer votre **nom d'utilisateur** et **mot de passe**
3. Cliquer **Se connecter**

Vous êtes automatiquement redirigé vers votre section par défaut :
- **Mécanicien** → Espace Mécanicien
- **Autres rôles** → Dashboard

### Changer d'atelier

Si vous avez accès à plusieurs ateliers (permission `rdv.select_atelier`) :
- Un sélecteur d'atelier apparaît en haut de certaines pages (Planning, RDV)
- Choisissez l'atelier souhaité dans le menu déroulant

---

## 2. Dashboard

Le dashboard affiche un résumé de la journée en cours :

### Cartes KPI
- **RDV aujourd'hui** — Nombre de rendez-vous du jour
- **Ordres ouverts** — Travaux en cours
- **Taux d'occupation** — % d'utilisation des ponts
- **CA du mois** — Chiffre d'affaires mensuel

### Statut des ponts
Cartes visuelles pour chaque pont de l'atelier :
- 🟢 **Libre** — Pont disponible
- 🟠 **Occupé** — Intervention en cours (avec mécanicien et véhicule)
- 🔴 **Maintenance** — Pont hors service

### RDV du jour
Tableau listant tous les RDV de la journée avec :
- Heure, client, véhicule, prestation
- Statut (badge couleur)
- Actions rapides

> Le dashboard se rafraîchit automatiquement toutes les 30 secondes.

---

## 3. Prise de RDV

### Assistant en 4 étapes

#### Étape 1 — Véhicule
- **Recherche par immatriculation** : tapez la plaque, l'API recherche automatiquement
- **Saisie manuelle** : si le véhicule n'est pas trouvé, remplissez marque, modèle, année, type moto

#### Étape 2 — Prestations
- Sélectionnez une ou plusieurs prestations dans la liste
- Le prix et le temps estimé se calculent automatiquement
- Les tarifs dépendent du type de moto sélectionné

#### Étape 3 — Date et créneau
- Grille hebdomadaire affichant les créneaux disponibles
- Naviguer entre les semaines avec les flèches ◀ ▶
- Les créneaux grisés sont indisponibles (atelier fermé ou plein)
- Cliquez sur un créneau libre pour le sélectionner

#### Étape 4 — Confirmation
- Vérifiez le récapitulatif (véhicule, prestations, date/heure)
- Renseignez les informations client (nom, téléphone, email)
- Cliquez **Confirmer le RDV**

> **Multi-atelier** : Si vous avez la permission, un sélecteur d'atelier apparaît en haut du formulaire. Les prestations et créneaux se rechargent selon l'atelier choisi.

---

## 4. Planning

### Vue hebdomadaire

Le planning affiche une grille de 7 jours avec créneaux de 15 minutes :

- **Blocs colorés** = RDV planifiés (couleur = mécanicien assigné)
- **Ligne rouge verticale** = heure actuelle (mise à jour toutes les 60s)
- **Zones grisées** = atelier fermé / pause déjeuner

### Actions

| Action | Comment |
|--------|---------|
| Voir un RDV | Cliquer sur le bloc coloré |
| Créer un RDV rapide | Cliquer sur une cellule vide |
| Déplacer un RDV | Glisser-déposer le bloc vers un autre créneau |
| Filtrer par mécanicien | Cliquer les puces de filtre en haut |
| Changer de semaine | Flèches ◀ ▶ ou bouton "Aujourd'hui" |

### Conflits

Si un RDV chevauche un autre sur le même pont, un avertissement s'affiche. Le planning vérifie automatiquement les horaires d'ouverture de l'atelier.

---

## 5. Ponts & Mécaniciens

### Onglet Ponts
Cartes pour chaque pont de l'atelier :
- Nom et type du pont
- Statut actuel (libre/occupé/maintenance)
- Mécanicien assigné
- Prochain RDV prévu

### Onglet Mécaniciens
Cartes pour chaque technicien :
- Nom, spécialités, couleur (planning)
- Planning du jour
- Nombre d'interventions en cours

### Onglet Temps d'intervention
Tableau des durées estimées par prestation et type de moto :
- Permet de calibrer les créneaux du planning
- Modifiable depuis l'administration

---

## 6. Ordres de Réparation

### Liste des OR

Triés par statut (en cours en premier), chaque OR affiche :
- Numéro d'OR, client, véhicule
- Mécanicien assigné
- Barre de progression en 5 étapes :

```
Réception → Diagnostic → Intervention → Contrôle qualité → Livraison
```

### Actions sur un OR

| Action | Description |
|--------|-------------|
| **Réception** | Check-in du véhicule (kilométrage, état, signature client) |
| **Assignation** | Attribuer un mécanicien et un pont |
| **Démarrer** | Lancer le chrono de travail |
| **Checkup** | Rapport technicien (10 points de contrôle) |
| **Terminer** | Arrêter le chrono, calculer le temps réel |
| **Facturer** | Générer la facture |

### Travaux supplémentaires

Si le mécanicien identifie des travaux additionnels pendant l'intervention :

1. Le mécanicien fait une **demande de travaux supplémentaires**
2. Une alerte apparaît chez le réceptionniste
3. Le réceptionniste **approuve ou refuse** avec un devis
4. Si approuvé → signature client requise
5. Les travaux sont ajoutés à l'OR

---

## 7. Suivi Live

Vue en temps réel de l'activité de l'atelier :

### Par mécanicien
- Timeline des RDV du jour avec points de statut
- **Timer live** (HH:MM:SS) pour l'intervention en cours
- Barre de progression temps écoulé vs temps estimé

### Alertes
- 🔴 **Retard** — Intervention dépassant le temps estimé
- 🟠 **Imminent** — RDV démarrant dans les 15 prochaines minutes
- ⚪ **Non assigné** — RDV sans mécanicien

> Le suivi se rafraîchit automatiquement toutes les 30 secondes.

---

## 8. Clients

### Recherche
- Recherche par nom, téléphone ou email
- Pagination automatique

### Fiche client
En cliquant sur un client :
- **Informations** — Nom, téléphone, email, adresse
- **Véhicules** — Liste des motos du client (ajouter/modifier/supprimer)
- **Historique** — Tous les RDV passés avec détails et montants
- **CA total** — Chiffre d'affaires cumulé du client

### Actions
- ✏️ Modifier les informations client
- 🏍️ Ajouter un véhicule
- 📅 Planifier un nouveau RDV
- 🗑️ Supprimer (uniquement si aucun RDV associé)

---

## 9. Espace Mécanicien

Interface dédiée aux techniciens, optimisée pour une utilisation sur tablette/mobile.

### Intervention active
Quand un travail est en cours :
- **Grand timer** (HH:MM:SS) — Temps écoulé en temps réel
- Infos véhicule et client
- Prestations à effectuer

### Checkup (Rapport technicien)
10 points de contrôle avec état OK / NOK / Non vérifié :
1. Niveau huile
2. Pression pneus
3. Usure plaquettes
4. État chaîne/courroie
5. Éclairage
6. Niveaux liquides
7. État pneus
8. Freins
9. Suspension
10. État général

Champs additionnels :
- Alertes (texte libre)
- Recommandations
- Travaux effectués

### Actions mécanicien
- ▶️ **Démarrer** — Lance le chrono
- ⏹️ **Terminer** — Arrête le chrono
- 🔧 **Signaler un problème** — Demande de travaux supplémentaires
- 📋 **Voir l'OR** — Consulter l'ordre de réparation

### File d'attente
- **À faire** — RDV planifiés triés par urgence (en retard d'abord)
- **Terminés** — Interventions finies avec liens vers OR et rapport

> Le timer se met à jour chaque seconde. Il change de couleur si le temps estimé est dépassé.

---

## 10. Facturation

### Créer une facture

1. Depuis un RDV terminé, cliquer **Facturer**
2. Le modal affiche le calcul automatique :
   - Main d'œuvre : heures × tarif horaire (ou forfait)
   - Pièces : listées avec quantité × prix unitaire
   - Remise : saisir un % (recalcul automatique)
   - TVA : calculée séparément MO et pièces
   - **Total TTC**
3. Cliquer **Générer la facture**

### Encaisser un paiement

1. Sur une facture existante, cliquer **Encaisser**
2. Choisir le mode de paiement :
   - 💳 Carte bancaire
   - 💶 Espèces
   - 📝 Chèque (référence obligatoire)
   - 🏦 Virement (référence obligatoire)
   - ⏳ Paiement différé
3. Saisir le montant (partiel ou total)
4. Confirmer

### Suivi des factures

- Liste filtrable par statut (en attente, payée, annulée)
- Statistiques : CA total, nombre de factures, taux d'encaissement
- Export PDF pour chaque facture

---

## 11. Administration

> Accessible uniquement aux rôles **Admin** et **Super Admin**

### Ateliers
- Voir la liste des ateliers
- Sélectionner un atelier pour gérer sa configuration
- Créer un nouvel atelier (Super Admin)

### Workshop (Ponts & Techniciens)
- Ajouter/modifier/supprimer des ponts
- Ajouter/modifier/supprimer des mécaniciens
- Gérer les absences (congés, maladie)

### Utilisateurs
- Créer des comptes utilisateur par atelier
- Attribuer un rôle (admin, réceptionniste, service client, mécanicien)
- Modifier ou supprimer des comptes

### Configuration
- **Tarif horaire MO** — Prix de l'heure de main d'œuvre
- **Marge pièces** — % de marge sur les pièces
- **TVA** — Taux de TVA (MO et pièces)

### Horaires
Pour chaque jour de la semaine :
- Ouvert / Fermé
- Heure d'ouverture et de fermeture
- Pause déjeuner (début/fin)

### Prestations
- Catalogue des services proposés
- Prix et durée par type de moto (grille tarifaire)
- Activer/désactiver une prestation

### Types de moto
- Activer/désactiver les types de moto acceptés par l'atelier
- Les types désactivés ne seront plus proposés dans le booking

### Équipements
- Gérer les équipements par pont (élévateur, démonte-pneu, etc.)
- Marquer la présence ou l'absence d'un équipement

### Rôles & Permissions (Super Admin uniquement)
- Créer des rôles personnalisés
- Définir les sections accessibles par rôle
- Attribuer les permissions granulaires (billing, RDV, config, etc.)

---

## Cycle de vie d'un RDV — Résumé

```
┌──────────┐    ┌──────────┐    ┌───────────┐    ┌──────────┐
│ Réservé  │───▶│ Confirmé │───▶│ Réception │───▶│ En cours │
└──────────┘    └──────────┘    └───────────┘    └──────────┘
                     │                                 │
                     ▼                                 ▼
               ┌──────────┐                      ┌──────────┐
               │  Annulé  │                      │ Terminé  │
               └──────────┘                      └──────────┘
                                                       │
                                                       ▼
                                                 ┌──────────┐    ┌──────────┐
                                                 │ Facturé  │───▶│  Payé    │
                                                 └──────────┘    └──────────┘
```

| Étape | Action | Qui |
|-------|--------|-----|
| Réservé → Confirmé | Confirmer le RDV | Réceptionniste |
| Confirmé → Réception | Check-in véhicule + signature | Réceptionniste |
| Réception → En cours | Démarrer le travail | Mécanicien |
| En cours → Terminé | Terminer le travail | Mécanicien |
| Terminé → Facturé | Générer la facture | Réceptionniste |
| Facturé → Payé | Encaisser le paiement | Réceptionniste |

---

## Raccourcis et astuces

| Astuce | Description |
|--------|-------------|
| **Clic rapide planning** | Cliquer une cellule vide pour créer un RDV rapidement |
| **Drag & drop** | Glisser un RDV sur le planning pour le déplacer |
| **Filtres mécaniciens** | Sur le planning, cliquer un nom pour masquer/afficher ses RDV |
| **Recherche client** | Taper au moins 2 caractères pour lancer la recherche |
| **Timer couleur** | Le chrono mécanicien passe en rouge si le temps est dépassé |
| **Alertes travaux** | Un son d'alerte retentit quand un mécanicien demande des travaux supp. |
