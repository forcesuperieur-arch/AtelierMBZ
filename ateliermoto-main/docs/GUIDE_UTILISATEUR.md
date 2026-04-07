# Guide Utilisateur — Atelier Moto Pro

> Application de gestion d’atelier moto multi-atelier  
> **Version** : 2.2

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

1. Ouvrir l’application dans le navigateur
2. Entrer votre **nom d’utilisateur** et votre **mot de passe**
3. Cliquer **Se connecter**

Redirection automatique :
- **Mécanicien** → Espace Méca
- **Autres rôles** → Dashboard

### Changer d’atelier

Si votre rôle possède la permission `rdv.select_atelier` :
- un sélecteur d’atelier apparaît en haut des écrans concernés
- le planning, les RDV et les données se rechargent pour l’atelier choisi

---

## 2. Dashboard

Le dashboard synthétise l’activité du jour :
- **RDV aujourd’hui**
- **Ordres ouverts**
- **Taux d’occupation** des ponts
- **CA du mois**

### Statut des ponts
- 🟢 **Libre**
- 🟠 **Occupé**
- 🔴 **Maintenance**

### Rafraîchissement
Le dashboard se met à jour automatiquement toutes les 30 secondes.

---

## 3. Prise de RDV

### Assistant en 4 étapes

#### Étape 1 — Véhicule
- recherche par **immatriculation**
- saisie manuelle si besoin
- **autocomplete amélioré** sur la marque et le modèle moto

#### Étape 2 — Prestations
- une ou plusieurs prestations peuvent être sélectionnées
- prix et temps estimés sont recalculés automatiquement
- les **durées se cumulent** pour proposer des créneaux réalistes

#### Étape 3 — Date et créneau
- affichage des créneaux disponibles
- navigation par semaine
- créneaux grisés = indisponibles ou atelier fermé

#### Étape 4 — Confirmation
- récapitulatif final
- informations client
- validation du RDV

---

## 4. Planning

### Vue hebdomadaire

Le planning affiche des blocs par RDV avec :
- la couleur du mécanicien
- la ligne rouge de l’heure courante
- les zones atelier fermé / pause déjeuner

### Actions rapides

| Action | Comment |
|--------|---------|
| Voir un RDV | cliquer sur le bloc |
| Créer un RDV rapide | cliquer une cellule vide |
| Déplacer un RDV | glisser-déposer |
| Filtrer par mécanicien | utiliser les puces de filtre |
| Changer de semaine | flèches ou bouton **Aujourd’hui** |

Le système vérifie les conflits de pont, de mécanicien, d’horaires et de durée totale.

---

## 5. Ponts & Mécaniciens

### Ponts
- statut actuel du pont
- mécanicien affecté
- prochain RDV

### Mécaniciens
- spécialités et couleur planning
- charge de la journée
- nombre d’interventions en cours

### Temps d’intervention
Les durées par prestation et type de moto servent au calcul des créneaux.

---

## 6. Ordres de Réparation

### Liste OR

Les OR sont triés par statut et affichent :
- numéro OR
- client et véhicule
- montant estimatif
- barre d’avancement métier

```
Réception → Diagnostic → Intervention → Contrôle QC → Livraison
```

### Nouvelles actions disponibles

| Action | Description |
|--------|-------------|
| **Aperçu master** | vue détaillée de l’OR avec mise en page atelier |
| **Imprimer** | impression directe depuis le navigateur |
| **PDF** | ouverture du PDF sécurisé de l’OR |
| **Réception** | formulaire enrichi de check-in véhicule |
| **Travaux supp** | traitement des demandes complémentaires |
| **RDV suite** | planification de la prochaine intervention |

### Réception enrichie

L’écran de réception permet maintenant de saisir :
- kilométrage
- points de contrôle véhicule
- **priorité**
- **niveau de carburant**
- **dommages carrosserie** via schéma cliquable
- **notes sur le schéma**
- **photos de l’état du véhicule**
- **lignes d’estimation**
- signature client

### Travaux supplémentaires

Si le mécanicien détecte des travaux additionnels :
1. création d’une demande complémentaire
2. apparition dans la file d’attente du réceptionnaire
3. approbation ou refus
4. signature / validation client si nécessaire
5. rattachement automatique au RDV courant
6. création d’un OR complémentaire archivé avec le dossier

---

## 7. Suivi Live

Le suivi live montre :
- la timeline des RDV du jour
- le **timer live** de l’intervention active
- les écarts entre temps estimé et temps réel

### Alertes
- 🔴 retard
- 🟠 démarrage imminent
- ⚪ RDV non assigné

---

## 8. Clients

### Recherche et fiche client
- recherche par nom, téléphone ou email
- fiche avec coordonnées, adresse, véhicules, historique et CA cumulé

### Actions
- modifier les informations
- ajouter un véhicule
- planifier un nouveau RDV
- supprimer si aucun RDV n’est rattaché

---

## 9. Espace Mécanicien

Interface pensée pour l’usage atelier / tablette.

### Fonctionnalités
- grand timer en temps réel
- informations client / véhicule
- checkup 10 points
- alertes et recommandations
- demande de travaux supplémentaires
- consultation de l’OR

Le timer se met à jour chaque seconde et change visuellement en cas de dépassement.

---

## 10. Facturation

### Générer une facture

Depuis un RDV terminé :
1. cliquer **Facturer**
2. contrôler MO, pièces, remise et TVA
3. générer la facture PDF

### Encaisser

Modes gérés :
- carte bancaire
- espèces
- chèque
- virement
- paiement différé

### Suivi
- liste filtrable des factures
- statistiques d’encaissement
- PDF disponible à tout moment

---

## 11. Administration

> Réservé aux rôles **Admin** et **Super Admin**

### Onglets principaux

| Onglet | Usage |
|--------|-------|
| **Ateliers** | gestion multi-atelier (super admin) |
| **Workshop** | ponts, mécaniciens, absences |
| **Config** | paramètres atelier, marges, TVA |
| **Base moto** | catégories, modèles, import catalogue |
| **Horaires** | jours ouvrés, heures, pauses |
| **Prestations** | catalogue et paramétrage tarifaire |
| **Rôles & droits** | sections et permissions RBAC |

### Points à retenir
- la **base moto** est réservée au super admin
- les prestations et horaires se gèrent depuis les onglets dédiés
- les permissions fines sont configurées dans **Rôles & droits**

---

## Cycle de vie d’un RDV — résumé

```text
reserve → confirme → reception → en_cours → termine → facture → paye
           └──────────────→ annule / non_presente
```
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
