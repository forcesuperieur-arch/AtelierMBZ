# SPEC-PAGE-01 — Dashboard (Vue d'ensemble)

> **Route** : `/` (page d'accueil après login)
> **Fichier** : `frontend/pages/index.vue`
> **Métier cible** : Responsable atelier, Réceptionniste, Responsable magasin
> **Rafraîchissement automatique** : toutes les 30 secondes (`setInterval`)

---

## Objectif

Le Dashboard est la **tour de contrôle** de la journée. Il donne en un coup d'œil l'état de charge de l'atelier, les anomalies actives et les RDV du jour. **Il ne gère pas le flux** — c'est le planning qui fait ça — mais il signale ce qui nécessite une attention immédiate et donne des raccourcis vers les actions clés.

---

## Données sources

| Donnée | Endpoint | Condition |
|---|---|---|
| Stats du jour | `GET /statistiques/dashboard` | Toujours |
| RDV du jour | `GET /rendez-vous?dateRdv[after]={today}&dateRdv[before]={today}&itemsPerPage=200` | Toujours |
| État des ponts | `GET /ponts/status` | Toujours |
| Alertes stock | `GET /stock/alertes` | Module `stock` activé |
| CA du jour / semaine | `GET /statistiques/ca` | Module `facturation` activé |
| Demandes travaux supp en attente | `GET /travaux-supplementaires?statut=en_attente` | Module `or` activé |

---

## Structure de la page

### 1. En-tête de page

```
[Page title]  Vue d'ensemble
[Sous-titre]  Vendredi 18 avril 2026

[Bouton droit]  ↻ Actualiser  |  → Ouvrir le planning
```

- Date courante formatée en français (long : `weekday long, day, month long, year`)
- Bouton **"Ouvrir le planning"** : lien `NuxtLink to="/planning"`, style `btn-primary`
- Bouton **"↻ Actualiser"** : déclenche manuellement `loadDashboard()`, style `btn-ghost`

---

### 2. Bannière module désactivé

Affiché uniquement si `route.query.moduleDisabled` est défini (redirigé depuis une route gardée).

```
⚠️ [Nom du module] est désactivé pour cet atelier
   Réactive ce module dans la configuration atelier.
```

Style : fond `rgba(245,158,11,0.06)`, bordure `rgba(245,158,11,0.2)`, texte `#FBBF24`.

---

### 3. Bande d'alertes actives

Affiché uniquement si des anomalies sont détectées sur les RDV du jour.

**Règles de détection** (calculées côté front, pas d'endpoint dédié) :

| Déclencheur | Type | Message |
|---|---|---|
| RDV `en_cours` depuis > `temps_estime + 10 min` | `danger` | `🔧 {vehicule} ({client}) — dépassement +{N}min` |
| RDV `termine` depuis > 15 min (non restitué) | `danger` | `📦 {vehicule} ({client}) — restitution en attente +{N}min` |
| RDV `confirme` ou `reception` dont l'heure est passée depuis > 10 min | `danger` | `{heure} — {vehicule} ({client}) en retard +{N}m` |
| RDV `confirme` arrivant dans < 15 min | `warning` | `{heure} — {vehicule} arrive bientôt` |
| Demandes travaux supp `en_attente` (module `or`) | `warning` | `⚠️ {N} demande(s) travaux supp en attente de validation` |

Chaque alerte = une chip horizontale avec icône + texte. Les `danger` s'affichent avant les `warning`.

---

### 4. Cartes statistiques (ligne de 4)

```
┌────────────────┐  ┌────────────────┐  ┌────────────────┐  ┌────────────────┐
│  RDV AUJOURD'  │  │  OR OUVERTS    │  │  OCCUPATION    │  │  RESTITUTIONS  │
│  HUI           │  │                │  │                │  │                │
│      12        │  │       4        │  │      75%       │  │       2        │
│  🏍️ 12 planif. │  │  🔧 En cours   │  │  ⚡ 3/4 ponts  │  │  📦 À restituer│
│  ████████░░░░  │  │  ██████░░░░░░  │  │  ████████████░ │  │  ████░░░░░░░░  │
└────────────────┘  └────────────────┘  └────────────────┘  └────────────────┘
```

| Carte | Source | Barre max |
|---|---|---|
| RDV aujourd'hui | `stats.rdvs_today` | 10 |
| OR ouverts | `stats.or_ouverts` | 10 |
| Taux occupation | `pontsOccupes / ponts.length * 100` | 100 |
| Restitutions | `stats.restitutions` | 8 |

**Extension : CA du jour** (si module `facturation` activé) — ajouter une 5e carte ou remplacer "Restitutions" selon arbitrage :

```
┌────────────────┐
│  CA DU JOUR    │
│                │
│   1 240,00 €   │
│  📈 Semaine:   │
│  4 870,00 €    │
└────────────────┘
```

Source : `GET /statistiques/ca` → `{ ca_jour: number, ca_semaine: number }`.

---

### 5. Grille état des ponts (temps réel)

Affiché uniquement si au moins 1 pont existe.

```
PONTS — État en temps réel

┌──────────────────────────────┐  ┌──────────────────────────────┐
│ 🟡  Pont A           EN_COURS│  │ 🟢  Pont B           LIBRE   │
│                              │  │                              │
│ Honda CB500F 2019            │  │ Aucune intervention en cours │
│ Dupont Jean · Révision compl.│  │                              │
│ 👤 M. Martin                 │  │                              │
│                              │  │                              │
│ Prochains : 2 RDV aujourd'hui│  │ Prochains : 1 RDV aujourd'hui│
└──────────────────────────────┘  └──────────────────────────────┘
```

Chaque carte pont :
- `🟡` point animé (pulse) si occupé, `🟢` si libre
- `StatusBadge` avec statut synthétique
- Si occupé : `vehicule_info`, `client_nom · type_intervention`, avatar mécanicien (initial + couleur fixe)
- Pied de carte : nombre de RDV restants dans la journée (`pont.next_count`)
- **Clic sur carte pont** : navigue vers `/planning?pont={id}` (filtre le planning sur ce pont)

---

### 6. Tableau RDV du jour

```
RDV du jour                                      12 rendez-vous  [+ Nouveau RDV]

┌──────┬─────────────────┬──────────────────┬────────────────────┬────────┬────────────┬──────────────┬────┐
│Heure │Véhicule         │Client            │Opération           │Pont    │Mécanicien  │Statut        │    │
├──────┼─────────────────┼──────────────────┼────────────────────┼────────┼────────────┼──────────────┼────┤
│08:30 │Honda CB500F 2019│Dupont Jean       │Révision complète   │Pont A  │M. Martin   │[EN_COURS]    │→   │
│09:00 │Yamaha MT-07     │Bernard Alice     │Vidange + filtres   │Pont B  │L. Lefebvre │[CONFIRME]    │→   │
│...   │...              │...               │...                 │...     │...         │...           │→   │
└──────┴─────────────────┴──────────────────┴────────────────────┴────────┴────────────┴──────────────┴────┘
```

Colonnes : `heure_debut` · `vehicule_info` · `client_nom` · `type_intervention` · `pont_nom` · `mecanicien_nom` · `status` (via `StatusBadge`) · actions.

**Colonne actions** :
- Flèche `→` : **ouvre le planning positionné sur ce RDV** (`/planning?rdv={id}&date={date_rdv}`) — **pas** `/rdv/{id}`
- Si statut `en_attente` ou `reserve` : icône ✉️ pour envoyer la confirmation (appel `POST /rendez-vous/{id}/transition/confirmer`)

Tri par `heure_debut` croissant.

Les RDV aux statuts `annule` et `no_show` sont **exclus** du tableau (filtrés côté front).

---

### 7. Demandes travaux supplémentaires en attente

Affiché uniquement si module `or` activé **et** au moins 1 demande `en_attente`.

```
⚠️ Travaux supplémentaires en attente (3)                [Voir toutes →]

┌──────────────────────────────────────────────────────────────────────┐
│ Honda CB500F — Dupont Jean                                urgente    │
│ Remplacement chaîne + pignons · 45 min · 180,00 €                   │
│ [✅ Approuver]  [❌ Refuser]                                          │
└──────────────────────────────────────────────────────────────────────┘
```

Pour chaque demande :
- Véhicule + client (déduit de l'OR lié)
- Description, urgence (`normal` / `urgent` / `critique`), temps estimé, prix estimé
- Boutons **Approuver** / **Refuser** → `PATCH /travaux-supplementaires/{id}` avec `{ statut: 'approuve' | 'refuse' }`
- Lien "Voir toutes →" → `/admin/demandes-travaux-supp`

Limité à **3 demandes** maximum dans le widget (les plus urgentes en premier).

---

### 8. Alertes stock (si module activé)

Affiché uniquement si module `stock` activé **et** au moins 1 alerte.

```
⚠ Alertes stock (2)

  Filtre à huile HF303 (HF303)             Stock: 0
  Plaquettes de frein EBC (EBC-FA367)      Stock: 1
```

Source : `GET /stock/alertes` → tableau de pièces sous seuil.
Chaque ligne : `désignation (référence)` · badge `Stock: {quantite_stock}`.

---

## Comportements clés

### Rafraîchissement automatique

- `setInterval(loadDashboard, 30000)` monté sur `onMounted`, détruit sur `onUnmounted`
- Les 4 endpoints sont appelés en parallèle via `Promise.all`
- Le bouton "↻ Actualiser" déclenche `loadDashboard()` manuellement avec un état `refreshing` (bouton désactivé pendant l'appel)

### Skeleton loading

- Pendant le premier chargement (`loading === true`) : afficher `<div class="loading-shimmer" style="height:200px;">` à la place du contenu dynamique
- Les stat cards et la grille ponts restent à `0` / vides le temps du chargement

### Gestion module désactivé

- Si `route.query.moduleDisabled` est défini, afficher la bannière d'avertissement en haut
- La guard de route ajoute ce paramètre automatiquement quand on tente d'accéder à un module désactivé

---

## Ce que la page ne fait PAS

- Elle ne gère aucun workflow RDV (confirmation, réception, etc.) — tout ça va dans le planning
- Elle ne duplique pas le planning — elle est un résumé qui pointe vers lui
- Elle ne permet pas de créer un OR directement (bouton "+ Nouveau RDV" → `/rdv/new`)
- Elle n'affiche pas l'historique des jours passés — uniquement le jour courant

---

## Points d'amélioration identifiés vs code actuel

| Point | État actuel | Cible |
|---|---|---|
| Lien tableau RDV | `→ /rdv/{id}` | `→ /planning?rdv={id}&date={date}` |
| Demandes travaux supp | Absentes du dashboard | Widget avec approbation inline |
| CA du jour | Absent | Carte stats si module facturation |
| Clic sur carte pont | Aucun | Lien `/planning?pont={id}` |
| Bouton "Ouvrir planning" | Absent | Bouton `btn-primary` en header |
| Alertes travaux supp | Absentes du strip | Chip `warning` si `en_attente > 0` |

---

## Permissions requises

Aucune permission spéciale — la page est accessible à tout utilisateur authentifié.

Les boutons **Approuver / Refuser** travaux supp sont conditionnés à `PERM_travaux_supp.validate` (identique à ce qui est utilisé dans `/ordres/[id]`).

---

## Dépendances composants

- `StatusBadge` — badge statut coloré
- `UCard` — carte conteneur
- `UTable` — tableau de données
- `StatsCard` (si composant extrait) — carte statistique réutilisable
- `useApi()` — HTTP
- `useAtelierStore()` — modules activés
- `useFormat()` — `formatDate`, `formatCurrency`
