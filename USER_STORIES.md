# Cadrage Technique — AtelierMBZ

> **Date** : 11/05/2026  
> **Réunion** : Cadrage métier (9h)  
> **État** : ✅ Cadrage validé — Toutes les décisions sont figées. Prêt pour développement.

---

## 1. Décisions métier validées

| # | Sujet | Décision | Priorité |
|---|-------|----------|----------|
| 1.1 | **Migration prod** | Structure vide + config initiale uniquement (horaires, ponts, prestations, rôles). **Pas de données historiques.** | 🔴 Haute |
| 1.2 | **Prestations grisées** | Les prestations `is_active = false` s'affichent en grisé dans l'**admin (page Prestations)**. Côté **catalogue public**, elles apparaissent grisées **sans prix** (non cliquables). | 🟡 Moyenne |
| 1.3 | **Absences mécano** | Workflow hybride : (a) notif admin + créneaux alternatifs proposés au client, (b) **validation manuelle obligatoire** du chef d'atelier avant confirmation du report. | 🟡 Moyenne |
| 1.4 | **Pont & Mécano** | 1 pont = 1 mécano **obligatoire** pour être actif. 1 mécano = **1 pont max**. Pont créable **sans mécano** (inactif). Désassignation requise avant réaffectation. Sans mécano → pas de créneau dispo. | 🔴 Haute |
| 1.5 | **Fiche RDV** | Suppression de la page `/rdv/[id]`. Toute la gestion RDV se fait en **pop-in / modal** (depuis le planning ou la liste RDV). Accès direct à `/rdv/[id]` → **404**. | 🟡 Moyenne |
| 1.6 | **Suivi public** | Remplacement du token par connexion **Email + Téléphone** (vérification ponctuelle, pas de session). Prise de RDV public **désactivée par atelier** (feature flag configurable dans l'admin). | 🟡 Moyenne |
| 1.7 | **Numéro de commande** | **Liste** de numéros de commande via table relationnelle **`RdvCommande`** (0 à N numéros par RDV). Saisissable lors de la prise/édition de RDV. | 🟢 Normale |
| 1.8 | **Livraison Générix** | Hors périmètre MVP. Contournement manuel en place. | 🔵 Future |

---

## 2. Questions ouvertes (à clarifier)

### Q-1 — Désassignation mécano avec RDV futurs ✅ RÉSOLU
> *« Si rdv pris mais je désassigne le user sur le pont que se passe-t'il ou je supprime »*

**Décision validée** : **Option B — Autoriser + workflow de report auto.**
- La désassignation est possible même avec des RDV futurs.
- Les clients des RDV concernés reçoivent automatiquement un email/SMS avec 3 créneaux alternatifs.
- Le chef d'atelier doit **valider manuellement** le report avant confirmation.
- **Transparent pour le client** : il ne sait pas qu'un mécano a été désassigné, il reçoit juste une proposition de report.
- Le pont devient temporairement "sans mécano" → plus de créneaux proposés sur ce pont jusqu'à réassignation.

### Q-2 — "Gestion / Suivi" ✅ RÉSOLU
> *« Gestion / Suivi »* (mentionné sans détail dans le bilan)

**Décision validée** : **Périmètre complet — "Les deux + tableau de bord manager".**
- **Workflow RDV** : Kanban/timeline des statuts (en attente → confirmé → en cours → terminé → restitué).
- **Suivi pièces / commandes** : Statut des commandes en cours, stock bas, réception pièces (hors Générix pour l'instant).
- **Tableau de bord manager** : KPI (taux d'occupation, CA, temps moyen d'intervention, taux de conversion devis).

### Q-3 — "Liste de rappel" ✅ RÉSOLU
> *« RDV sur devis - appel client - liste de rappel à créer dans l'app »*

**Décision validée** : **Module unique "Rappels" tout-en-un.**
- **Rappels d'entretien** : ❌ **Hors MVP** (reporté à plus tard).
- **Rappels de confirmation** : clients avec un RDV dans les 24-48h à appeler.
- **Relances devis** : clients ayant un devis non accepté depuis X jours.
- **Affichage** : une seule liste quotidienne regroupant tous les appels à faire, triable par type et priorité.
- **Canal de notification** (absences / reports) : **Email par défaut, SMS optionnel** selon configuration atelier.

### Q-4 — Numéro de commande — visibilité ✅ RÉSOLU
> *« Ajout du saisie de numéro de commande dans la prise de rdv et dans l'Edition du rdv »*

**Décision validée** : **Uniquement interne (atelier uniquement).**
- Le champ `numero_commande` est saisissable lors de la création/édition d'un RDV.
- Il n'est **pas visible** par le client dans le suivi public.
- Affichage dans le planning (sur la fiche RDV) et dans la liste des RDV.

### Q-5 — Migration config initiale — méthode ✅ RÉSOLU
> *« Migration de toute la base pour prod + migration config initial »*

**Décision validée** : **Script de seed Symfony (fixtures) + config par défaut.**
- Un script de seed (`php bin/console doctrine:fixtures:load` ou commande custom) crée la structure minimale :
  - 1 atelier par défaut
  - Horaires standards (Lun-Ven 08h-18h, Sam 09h-13h)
  - Rôles et permissions de base
  - 1 compte admin
- L'admin peut ensuite personnaliser via l'interface (pas de wizard first-install pour l'instant).
- **Pas de migration de données historiques** : la base de prod démarre vide côté clients/rdv/factures.

---

## 3. Plan technique par lots

### Lot 1 — Corrections rapides (accès + affichage)
- [ ] Fix security `HoraireAtelier` : passer de `ROLE_ADMIN` à `ROLE_USER` (ou public pour les horaires ouverts).
- [ ] Fix security `Prestation` : rendre la collection accessible au public (filtrer `is_active = true` pour le public, tout afficher pour l'admin).
- [ ] Griser les prestations inactives dans le catalogue public.
- [ ] Supprimer la page `/rdv/[id]` et transformer en modal (depuis `/rdv` et `/planning`).

### Lot 2 — Pont & Mécano (règles métier)
- [ ] Backend : contrainte "1 mécano = 1 pont max" + "1 pont = 1 mécano obligatoire".
- [ ] Backend : endpoint de désassignation mécano d'un pont.
- [ ] Backend : `SlotService` ne retourne pas de créneau sur un pont sans mécano.
- [ ] Frontend : afficher le mécano assigné sur chaque pont dans la grille planning.
- [ ] Gestion du cas "désassignation avec RDV futurs" (voir Q-1).

### Lot 3 — Absences mécanicien
- [ ] CRUD Absences (entité `Absence` + page admin).
- [ ] Détection automatique des conflits RDV lors de la création d'absence.
- [ ] Envoi email/SMS au client avec 3 créneaux alternatifs proposés.
- [ ] Interface chef d'atelier pour valider/refuser les reports proposés.

### Lot 4 — Frontend public & Suivi
- [ ] Endpoint `/public/suivi` : authentification par `email + telephone` au lieu de token.
- [ ] Masquer / désactiver la prise de RDV public (feature flag `PUBLIC_BOOKING_ENABLED` par atelier).
- [ ] Création entité `RdvCommande` (relation Many-to-One avec `RendezVous`).
- [ ] CRUD numéros de commande dans la prise de RDV et l'édition.

### Lot 5 — Modules futurs (hors MVP immédiat)
- [ ] Module "Liste de rappels" complet (confirmation + relances devis + entretien).
- [ ] Intégration API Générix (livraison pièces).
- [ ] Dashboard manager : **Taux d'occupation + CA + Temps moyen d'intervention**.

---

## 4. Accès à revoir (audit rapide)

| Entité / Route | Accès actuel | Problème | Correction proposée |
|----------------|--------------|----------|---------------------|
| `HoraireAtelier` | `ROLE_ADMIN` | Planning cassé pour non-admin | `ROLE_USER` + tenant filter |
| `Prestation` (collection) | Probablement `ROLE_USER` | Catalogue public inaccessible | Public read (filtrer `is_active`) |
| `Pont` | `ROLE_USER` | OK pour l'atelier | Vérifier si public nécessaire pour slots |
| `Mecanicien` | `ROLE_USER` | OK | Vérifier si public nécessaire pour slots |
| `RendezVous` | Aucun `security` explicite | Seul le TenantFilter protège | Ajouter `ROLE_USER` sur les ops sensibles |
| `Absence` (à créer) | — | À définir | **`ROLE_CHEF_ATELIER`** en write, `ROLE_USER` en read |

---

## 5. État des clarifications

| Question | État | Décision |
|----------|------|----------|
| Q-1 — Désassignation mécano avec RDV futurs | ✅ Résolu | Option B : Autoriser + workflow de report auto (transparent client) |
| Q-2 — Périmètre "Gestion / Suivi" | ✅ Résolu | Workflow RDV + suivi pièces + dashboard manager (KPI) |
| Q-3 — "Liste de rappel" | ✅ Résolu | Module unique "Rappels" tout-en-un |
| Q-4 — Visibilité numéro de commande | ✅ Résolu | Uniquement interne (atelier) |
| Q-5 — Méthode migration config | ✅ Résolu | Script de seed Symfony + config par défaut |

**Toutes les décisions sont validées. Le cadrage est figé.**

---

## 6. Prochaines étapes

Choisis le lot sur lequel je dois me lancer :

1. **Lot 1** — Corrections rapides (accès + prestations grisées + suppression `/rdv/[id]`) → ~2-3h
2. **Lot 2** — Pont & Mécano (contraintes + désassignation + slots) → ~4-6h
3. **Lot 4** — Frontend public & suivi (email+tel + numéro commande) → ~3-4h
4. **Tous les lots 1+2+4** — Je commence par le Lot 1 et enchaîne → ~1 journée
5. **Lecture seule** — Tu veux d'abord relire et modifier le fichier toi-même
