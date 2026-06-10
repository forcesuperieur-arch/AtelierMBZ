# Audit Fonctionnel Complet — AtelierMBZ

> **Date** : 26/05/2026
> **Projet** : AtelierMBZ (Paddock) — Symfony 6 + API Platform 4 + Nuxt 3
> **Base de code** : 182 fichiers PHP backend, ~50 pages frontend, 40+ tests
> **Branch active** : `feat/refonte-document-unique-or`

---

## 1. Résumé exécutif

L'application est un **ERP atelier moto** couvrant : prise de RDV, planning, ordres de réparation, facturation, stock, VO (véhicules d'occasion), notifications multi-canal, et analytics.

| Domaine | État global | Commentaire |
|---------|-------------|-------------|
| Auth & RBAC | ✅ Fonctionnel | Cookie JWT + Google OAuth + permissions granulaires |
| Dashboard / Analytics | ✅ Fonctionnel | KPIs temps réel + tables de faits dénormalisées |
| Planning & RDV | ⚠️ Partiel | Drag & drop corrigé (PATCH), confirmation manquante |
| Espace Mécanicien | ✅ ~90% | Pause/reprise + travaux supp ajoutés récemment |
| Ordres de Réparation | ✅ Refonte terminée | Fusion OR + Rapport en document unique |
| Notifications | ⚠️ Partiel | Architecture complète mais listener workflow déconnecté |
| Public / Client | ✅ Fonctionnel | Booking, suivi, companion, validation travaux supp |
| VO | ✅ Fonctionnel | Rachats, dépôts-vente, remises en état, livre de police |
| Stock & Facturation | ✅ Fonctionnel | Modules activables par feature flag |
| Tests | ⚠️ Partiel | 40+ tests backend, 109 E2E passent, 3 échecs connus |

---

## 2. Architecture technique

### 2.1 Backend (Symfony 6 + API Platform 4.1)

```
Caddy (80) → PHP-FPM 8.3 (8000) + PostgreSQL 15 + Mercure (3000)
```

| Couche | Détails |
|--------|---------|
| **API** | API Platform 4.1.28 (metadata-driven), routes custom Symfony côte à côte |
| **Auth** | LexikJWT + cookie HttpOnly `access_token` + refresh token |
| **Workflow** | Symfony Workflow (state machine) — `RendezVous` 15 états, 15 transitions |
| **DB** | PostgreSQL 15, Doctrine ORM, `TenantFilter` sur `atelier_id` |
| **PDF** | DomPDF, templates Twig, stockage `var/pdf/` |
| **Notifications** | `NotificationDispatcher` (email/SMS/push), `MercureNotifier` (SSE temps réel) |
| **Files** | 182 fichiers PHP, 40+ entités, 30+ contrôleurs, 25+ services |

### 2.2 Frontend (Nuxt 3 SPA)

```
Nuxt 3 (SSR=false) → proxy /api/** → PHP:8000
                    → proxy /.well-known/mercure → Mercure:3000
```

| Couche | Détails |
|--------|---------|
| **Framework** | Nuxt 3.16, Vue 3 Composition API, TypeScript 5.7 |
| **UI** | Nuxt UI v3 (Tailwind), thème sombre forcé |
| **State** | Pinia (7 stores), `auth` + `atelier` persistés |
| **API** | `useApi.ts` — fetch natif, auto-refresh 401, PATCH `merge-patch+json` |
| **Auth** | Cookie-based, RBAC dual (`ROLE_X` + `RoleMetier`), module guards |
| **Tests** | Playwright E2E (Chromium), Vitest unitaire |

---

## 3. Modules fonctionnels détaillés

### 3.1 Auth & Sécurité (US-AUTH-01 à 04)

| Fonctionnalité | État | Notes |
|----------------|------|-------|
| Login email/password | ✅ | Cookie JWT HttpOnly, redirection dashboard |
| Google OAuth | ✅ | Flux complet, création user `pending_validation` |
| Redirection non-auth | ✅ | `auth.global.ts` protège toutes les routes |
| RBAC granulaire | ✅ | `hasPerm()` + `hasSection()` + module guards |
| Switch atelier | ✅ | Cookie `active_atelier_id`, super admin uniquement |
| Dev SSO simulation | ✅ | Mode test uniquement |

**Bugs connus :**
- Aucun critique. Le système de permission est robuste.

---

### 3.2 Dashboard & Analytics (US-DASH-01 à 03)

| Fonctionnalité | État | Notes |
|----------------|------|-------|
| Stat cards (CA, marge, RDV du jour, taux occupation) | ✅ | Données temps réel depuis `AnalyticsDailySnapshot` |
| Filtres période | ✅ | Jour / Semaine / Mois / Année / Personnalisé |
| Alertes en temps réel | ✅ | `AnalyticsAlertRule` + Mercure |
| Tables de faits dénormalisées | ✅ | `AnalyticsRdvFact`, `AnalyticsClientFact` — event sourcing light |
| Prévision / forecasting | ✅ | Intégré dans le dashboard admin |

---

### 3.3 Rendez-vous (US-RDV-01 à 04)

| Fonctionnalité | État | Notes |
|----------------|------|-------|
| Liste paginée | ✅ | Filtres date, statut, client, mécanicien |
| Création | ✅ | Modal depuis `/rdv` ou `/planning` (page `/rdv/[id]` supprimée) |
| Changement de statut | ✅ | Workflow Symfony + API Platform |
| Détail en modal | ✅ | `RdvDetailModal.vue` — pas de page dédiée |
| Numéros de commande | ✅ | Table `RdvCommande` (0..N) |

**Bugs corrigés en session :**
- `typeIntervention` non-nullable non initialisé → corrigé via le constructeur
- `PUT` API Platform 4 écrasait `client_id`, `vehicule_id`, `pont_id`, `mecanicien_id`, `atelier_id` → **corrigé par passage à `PATCH`**
- `atelier_id` disparaissait après drag & drop → corrigé

---

### 3.4 Planning (US-PLAN-01 à 02)

| Fonctionnalité | État | Notes |
|----------------|------|-------|
| Vue grille horaire × ponts | ✅ | `PlanningGrid.vue` |
| Navigation semaine | ✅ | Précédente / suivante |
| Drag & drop | ✅ | Corrigé (handle visuel, `dataTransfer.dropEffect`, PATCH) |
| Confirmation de déplacement | ❌ | **Manquant** — demandé par l'utilisateur, pas encore implémenté |
| Affichage mécano sur pont | ✅ | Visible dans la grille |
| Conflits double assignation | ✅ | Détectés côté backend |
| Checkup détaillé dans modal | ✅ | Photos réception + points checkup |
| Blocage créneaux passés | ✅ | Corrigé |
| Saisie kilométrage réception | ❌ | Retirée (auto depuis véhicule) |

**Bugs corrigés en session :**
- Drag & drop ne fonctionnait pas (permissions + events natifs) → corrigé
- RDV disparaissait après drop (`atelier_id` → NULL) → corrigé par PATCH
- Conflit click/drag invisible → handle de drag visuel ajouté

**Manquants identifiés :**
- Modal de confirmation avant déplacement de RDV

---

### 3.5 Espace Mécanicien (US-ATEL-01 à 05)

| Fonctionnalité | État | Notes |
|----------------|------|-------|
| RDVs du jour (3 sections) | ✅ | Actif / À faire / Terminés |
| Démarrer intervention | ✅ | Transition `start_travail` → `en_cours` + chrono |
| Chrono live avec alerte retard | ✅ | HH:MM:SS, barre progression vs temps estimé |
| **Pause / Reprise** | ✅ | **Implémenté en session** — transitions `pause_travail` / `reprendre_travail` |
| Checkup express (10 points) | ✅ | PATCH direct |
| Essai routier | ✅ | 10 points, km départ/retour, validation ≥5 points |
| Rapport d'intervention | ✅ | Travaux réalisés, alertes, recommandations |
| Signature mécanicien | ✅ | Canvas, base64, OR → `intervention_signee` |
| Signature client restitution | ✅ | Via companion ou réception |
| **Travaux supplémentaires** | ✅ | **Implémenté en session** — création depuis l'espace mécano |
| Notes intervention | ✅ | Champ libre, sauvegarde indépendante |
| KPIs dashboard mécano | ✅ | En cours / À faire / Terminés / % journée |
| Contexte réception PDA | ✅ | État véhicule, points checkup, statut OR |
| **Téléchargement PDF OR** | ✅ | **Corrigé** — `RapportPdfController.php` créé |
| `isSignedByBoth` | ✅ | **Corrigé** — exposé par `flattenRdvForMecanicien()` |
| `essaiRoutierValide` | ✅ | **Corrigé** — utilise `statut === 'valide'` |
| Canvas signature mobile | ⚠️ | Fix proposé (aspect-ratio dynamique) mais à vérifier |

**Bugs corrigés en session (Phases 3.1 & 5.1 du MECANICIEN_PLAN) :**
- `isSignedByBoth` jamais défini → corrigé backend + frontend
- `essaiRoutierValide` utilisait `isValide` au lieu de `statut` → corrigé
- Endpoint PDF inexistant → `RapportPdfController.php` créé
- `$user->getId()` → `$this->getUser()?->getId()` dans `signMecanicien` et `createEssai`
- `OrdreReparationFreezeListener` bloquait `signedSnapshot`/`signedHash` → champs ajoutés aux `allowedFields`
- `rapportForm.garantie` inexistant → corrigé

**Manquants (hors scope MVP selon MECANICIEN_PLAN) :**
- Vue mobile native / PWA
- Historique interventions précédentes
- Stats performance mécanicien
- Absences affichées dans l'espace mécano (endpoint existe, UI non branchée)
- Notification SMS "moto prête" après signature mécano (prévu NOTIFICATIONS_PLAN)

---

### 3.6 Ordres de Réparation & Rapport (US-ATEL-02, US-ATEL-04)

| Fonctionnalité | État | Notes |
|----------------|------|-------|
| Fusion OR + Rapport | ✅ | **Refonte terminée** — document unique |
| Page `/ordres/[id].vue` | ❌ | **Supprimée** — tout passe par le planning modal |
| Snapshot RGPD | ✅ | `signedSnapshot` + `signedHash` SHA-256 |
| PDF figé | ✅ | Généré à la finalisation, stocké `var/pdf/` |
| Photos intervention | ✅ | Séparées des photos restitution |
| Photos restitution | ✅ | Via page `/restitution` |
| Encart entretien fluides | ✅ | Ajouté au PDF |
| Prochaine révision | ❌ | **Retiré** de l'affichage et du PDF |
| Lien PDF dans planning | ✅ | Masqué si OR non finalisé |
| Panneau détail OR dans popin RDV | ✅ | Photos + checkup + lien PDF |

**Évolutions récentes (commits) :**
- `feat: refonte document unique OR — fusion OR + Rapport`
- `feat(or): finalisation auto au statut 'termine'`
- `feat(pdf): enrichit le PDF OR avec tous les détails`
- `feat: page restitution publique + template PDF OR enrichi`

---

### 3.7 Clients & Véhicules (US-CLI-01 à 02)

| Fonctionnalité | État | Notes |
|----------------|------|-------|
| Liste avec recherche | ✅ | Nom, prénom, téléphone, email |
| Fiche client détaillée | ✅ | Coordonnées + historique RDV + véhicules |
| Consentements RGPD | ✅ | Affichés sur la fiche |
| Catalogue motos | ✅ | `/motos` — filtres, modal détail |
| OCR carte grise | ✅ | Tesseract.js (FR/NL/EN) |

---

### 3.8 Devis & Facturation (US-FACT-01 à 03)

| Fonctionnalité | État | Notes |
|----------------|------|-------|
| Création devis depuis RDV | ✅ | Lignes prestations + pièces + MO |
| Envoi email devis | ✅ | Via `NotificationDispatcher` |
| Factures | ✅ | Génération depuis RDV terminé |
| Paiements | ✅ | Liés à la facture |
| Statuts facture | ✅ | `emise`, `payee`, `impayee`, `annulee` |
| Snapshots RGPD | ✅ | `snapClient*` remplis à la création |

---

### 3.9 Stock (US-STOCK-01 à 02)

| Fonctionnalité | État | Notes |
|----------------|------|-------|
| Catalogue pièces | ✅ | `/stock` — recherche, alertes stock bas |
| Commandes fournisseurs | ✅ | Statut `en_attente` → `commandee` → `recue` |

---

### 3.10 VO — Véhicules d'Occasion (US-VO-01 à 07)

| Fonctionnalité | État | Notes |
|----------------|------|-------|
| Dashboard VO | ✅ | KPIs stock, ventes, marge |
| Rachats | ✅ | Workflow `brouillon` → `en_stock` → `en_vente` → `vendu` |
| Dépôts-vente | ✅ | Commission, durée mandat, expiration |
| Remises en état | ✅ | Campagnes avec lignes MO + pièces |
| Livre de police | ✅ | Immuable, lecture seule, API rejette modifications |
| Facturation VO | ✅ | Régime TVA marge |
| Documents | ✅ | Upload CERFA, CG, CT |
| Companion VO public | ✅ | `/public/vo-companion` |

---

### 3.11 Public / Client (US-PUB-01 à 06)

| Fonctionnalité | État | Notes |
|----------------|------|-------|
| Prise de RDV en ligne | ✅ | `/public/booking` — feature flag `PUBLIC_BOOKING_ENABLED` |
| Suivi réparation | ✅ | `/public/suivi` — email + téléphone (pas de token) |
| Companion client | ✅ | `/public/companion` — lien magique |
| Validation travaux supp | ✅ | `/public/demande/:token` — accepte/refuse |
| Mentions légales | ✅ | Statique, accessible sans auth |

**Bug E2E connu :**
- `business-flows.spec.mjs:45` — pas de créneaux pour certaines dates (données de test)

---

### 3.12 Administration (US-ADMIN-01 à 09)

| Fonctionnalité | État | Notes |
|----------------|------|-------|
| Gestion utilisateurs | ✅ | `/admin/users` — modal création, `USelectMenu` v3 |
| Gestion ponts | ✅ | CRUD + assignation mécanicien |
| Rôles et permissions | ✅ | Héritage `heriteDe` + `RolePermissionEntry` |
| Absences | ✅ | Entité + CRUD admin — **mais pas exploitée côté planning** |
| Config atelier | ✅ | Taux horaires, TVA, marges, modules activables |
| Audit log | ✅ | `/admin/audit` — actions tracées |
| Providers notifications | ✅ | SMTP, SMS, Slack + test d'envoi |
| Templates documents | ✅ | Style Canva, variables dynamiques, aperçu |
| Clauses légales | ✅ | Injection dans PDF devis/factures |
| Prestations | ✅ | `/admin/prestations` — CRUD complet |

---

### 3.13 Notifications (NOTIFICATIONS_PLAN)

| Composant | État | Problème |
|-----------|------|----------|
| `NotificationDispatcher` | ✅ | Multi-provider, fallback, logging |
| `MercureNotifier` | ✅ | Push temps réel par atelier |
| `NotificationController` | ✅ | API list/acknowledge/markRead |
| `NotificationProviderController` | ✅ | CRUD providers/templates + webhooks |
| UI frontend (cloche + pop-in) | ✅ | Fonctionnel |
| `ProcessNotificationEscalationsCommand` | ✅ | Exécuté chaque minute |
| `RdvWorkflowListener` | ❌ | **Déconnecté** — utilise legacy `SendRappelHandler` |
| `RappelProchaineRevisionCommand` | ❌ | **TODO ligne 46** — jamais schedulé |
| Escalations SMS | ⚠️ | `targetInfo = "ROLE_X"` sans numéro de téléphone |
| `EmailTemplate` vs `NotificationTemplate` | ⚠️ | Doublon — migration en cours |

**Notifications P0 non branchées :**
- RDV confirmé → email + SMS client
- Rappel J-1 → email + SMS client
- Moto prête → SMS client (après `terminer` ou signature mécano)
- Demande travaux supp → push UI + SMS atelier

---

### 3.14 Design System (US-DS-01 à 04)

| Fonctionnalité | État | Notes |
|----------------|------|-------|
| `AppModal` + `PitModalFooter` | ✅ | Standardisé, pas de `UCard` interne |
| Focus trap | ✅ | `focus-trap` + restoration |
| Escape ferme la modal | ✅ | Listener global `keydown` |
| `USelectMenu` syntaxe v3 | ✅ | `:items`, `label-key`, `value-key` |
| Valeurs vides | ✅ | `null` + `placeholder` (pas `''`) |
| `AppTable` (ex-`UTable`) | ✅ | Fix récursion infinie |

---

## 4. Bugs, dettes techniques & incohérences

### 4.1 Bugs critiques (P0)

| # | Bug | Fichier(s) | Impact | Statut |
|---|-----|------------|--------|--------|
| 1 | `RdvWorkflowListener` utilise legacy `SendRappelHandler` | `src/EventListener/RdvWorkflowListener.php` | Aucune notification P0 ne part | ❌ Non corrigé |
| 2 | `RappelProchaineRevisionCommand` — TODO ligne 46 | `src/Command/RappelProchaineRevisionCommand.php` | Rappels révision jamais envoyés | ❌ Non corrigé |
| 3 | Escalations SMS avec `targetInfo = "ROLE_X"` | `DemandeTravauxSuppController.php` | SMS escalation invalide | ❌ Non corrigé |
| 4 | Absences non exploitées dans le planning | `SlotService.php` | Mécaniciens absents apparaissent dispo | ❌ Non corrigé |

### 4.2 Bugs majeurs corrigés en session

| # | Bug | Correction |
|---|-----|------------|
| 5 | Drag & drop effaçait `atelier_id` et toutes les relations | Passage de `PUT` à `PATCH` + `new Patch()` dans API Platform |
| 6 | `isSignedByBoth` jamais défini | Ajout dans `flattenRdvForMecanicien()` |
| 7 | `essaiRoutierValide` utilisait `isValide` | Remplacé par `statut === 'valide'` |
| 8 | Endpoint PDF OR inexistant | Création `RapportPdfController.php` |
| 9 | `$user->getId()` null dans `MecanicienController` | `$this->getUser()?->getId()` |
| 10 | `OrdreReparationFreezeListener` bloquait finalisation | `signedSnapshot`/`signedHash`/`signedAt` ajoutés aux `allowedFields` |
| 11 | `rapportForm.garantie` inexistant | Référence corrigée |
| 12 | Mercure SSE 401 + fallback polling | Proxy Nuxt + queue refresh token |

### 4.3 Dettes techniques

| # | Sujet | Sévérité |
|---|-------|----------|
| 13 | `EmailTemplate` legacy coexiste avec `NotificationTemplate` | Moyenne — migration prévue |
| 14 | `standard_put` n'existe pas dans API Platform 4.1.28 | Moyenne — utilise `Patch` à la place |
| 15 | `TenantFilter` actif pour tous les users non-super-admin — RDV avec `atelier_id=null` invisibles | Faible — corrigé par le PATCH |
| 16 | `typeIntervention` non-nullable sans initialisation | Faible — corrigé |
| 17 | `sendFromTemplate` n'a pas de vérification d'existence du template | Faible — renvoie silencieusement si absent |
| 18 | UI mécanicien pas optimisée mobile (canvas 580px fixe) | Faible — fix proposé |
| 19 | Sync temps réel planning ↔ mécanicien (WebSocket) | Hors scope MVP |
| 20 | Historique interventions précédentes depuis vue mécano | Hors scope MVP |

---

## 5. État des tests

### 5.1 Backend (PHPUnit)

| Suite | État | Détails |
|-------|------|---------|
| `RendezVousWorkflowServiceTest` | ✅ | 4 tests — pause/reprise + accumulation temps |
| `MecanicienControllerTest` | ✅ | 5 tests — CRUD mécano, sign, essai, demande complémentaire |
| `OrdreReparationPolicyTest` | ✅ | Finalisation, hash, snapshot |
| `Notification*Test` | ✅ | Dispatcher, DTO, entités, provider API, template catalog |
| `ProcessNotificationEscalationsCommandTest` | ✅ | Escalations |
| `Lots124FunctionalTest` | ✅ | Flux métiers lots 1-4 |
| `AuthBookingAteliersTest` | ✅ | Auth public booking |
| **Total** | **40+ tests** | **Tous passent** |

### 5.2 Frontend E2E (Playwright)

| Suite | Avant Session 4 | Après Session 4 |
|-------|----------------|-----------------|
| auth + non-regression + navigation + mvp-complete | 8 failed | **0 failed (109 passed)** |
| business-flows + notifications + vo + modernization | 8 failed | **3 failed (110 passed)** |

**Échecs restants connus :**
1. `business-flows.spec.mjs:45` — Public booking : pas de créneaux pour date de test
2. `notifications.spec.mjs:81` — Attend 409, reçoit 404 sur acknowledge inexistant
3. `vo-companion-flow.spec.mjs` + `vo-pricing-diff.spec.mjs` — Fixtures VO spécifiques manquantes

### 5.3 Build

| Check | État |
|-------|------|
| `npx nuxt typecheck` | ✅ 0 erreur |
| `npx nuxt build` | ✅ Complet (2.16 MB) |
| `docker compose` | ✅ Caddy + PHP + Nuxt + PostgreSQL + Mercure |

---

## 6. Workflow des RDV (états et transitions)

```
en_attente ──reserver──► reserve ──confirmer──► confirme ──reception──► reception
                                                            │
                                                            ├──declarer_no_show──► no_show
                                                            │                       │
                                                            │                       └──reporter──► confirme
                                                            │
                                                            └──start_travail──► en_cours
                                                                                │
                                                                                ├──pause_travail──► en_pause
                                                                                │                   │
                                                                                │                   └──reprendre_travail──► en_cours
                                                                                │
                                                                                └──terminer──► termine ──restituer──► restitue
                                                                                                                    │
                                                                                                                    ├──facturer──► facture ──payer──► paye
                                                                                                                    │
                                                                                                                    └──[restitue_partiel]──► facturer

[Annulation possible depuis : en_attente, reserve, confirme, reception, en_attente_pieces, en_gardiennage]
```

---

## 7. Recommandations prioritaires

### 🔴 P0 — Avant mise en production

1. **Brancher `RdvWorkflowListener` sur `NotificationDispatcher`** (NOTIFICATIONS_PLAN Tâche 1.2)
   - Impact : aucun SMS/email client ne part actuellement
   - Effort : ~2h

2. **Implémenter `RappelProchaineRevisionCommand`** (NOTIFICATIONS_PLAN Tâche 1.3)
   - Impact : rappels révision jamais envoyés
   - Effort : ~1h

3. **Corriger les escalations SMS** (NOTIFICATIONS_PLAN Tâche 1.4)
   - Impact : escalation avec `ROLE_X` comme numéro de téléphone
   - Effort : ~2h

4. **Ajouter confirmation modal avant déplacement RDV** (demande utilisateur)
   - Impact : UX — évite les déplacements accidentels
   - Effort : ~1h

### 🟠 P1 — Semaine 1 post-MVP

5. **Afficher les absences dans l'espace mécanicien** (MECANICIEN_PLAN Phase 2)
   - Endpoint existe, UI non branchée
   - Effort : ~2h

6. **Bloquer les affectations si mécanicien absent** (MECANICIEN_PLAN Tâche 2.2)
   - Impact : planning suggère des créneaux sur mécano absent
   - Effort : ~3h

7. **Notification SMS "moto prête" après signature mécano** (MECANICIEN_PLAN Phase 4)
   - Impact : client pas notifié de la restitution
   - Effort : ~1h

8. **Corriger canvas signature mobile** (MECANICIEN_PLAN Tâche 1.4)
   - Effort : ~30min

### 🟡 P2 — Améliorations

9. **Unifier `EmailTemplate` et `NotificationTemplate`** (NOTIFICATIONS_PLAN Phase 2)
10. **Ajouter gestion d'erreur dans `MercureNotifier`** (try/catch)
11. **Vue mobile optimisée pour l'espace mécanicien**
12. **Historique interventions précédentes depuis vue mécano**

---

## 8. Fichiers modifiés récemment (à surveiller)

```
backend/src/Controller/MecanicienController.php          # Phase 3.1 + 5.1
backend/src/Controller/RapportPdfController.php           # Nouveau
backend/src/EventListener/OrdreReparationFreezeListener.php # Fix finalisation
backend/src/Entity/RendezVous.php                         # new Patch()
backend/config/packages/workflow.yaml                     # pause_travail / reprendre_travail
backend/src/Service/RendezVousWorkflowService.php         # Pause/reprise
backend/tests/Unit/RendezVousWorkflowServiceTest.php      # Tests pause/reprise
backend/tests/Functional/MecanicienControllerTest.php     # Tests demande complémentaire
frontend/pages/mecanicien.vue                             # Espace mécanicien complet
frontend/pages/planning.vue                               # Drag & drop + PATCH
frontend/stores/rdv.ts                                    # updateRdv → PATCH
frontend/components/PlanningGrid.vue                      # Handle drag + visual
```

---

*Document généré le 26/05/2026 à partir de l'analyse du codebase, des logs de conversation, et des documents de référence (USER_STORIES, MECANICIEN_PLAN, NOTIFICATIONS_PLAN, BUGFIX_LOG, SESSION_4_SUMMARY).*
