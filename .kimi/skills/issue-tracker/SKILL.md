# Issue Tracker — Registre des Problèmes

> Ce fichier est vivant. Je le mets à jour après chaque session.

## 🔴 CRITIQUE — À traiter en priorité

| # | Problème | Fichier(s) | Agent responsable | Statut |
|---|---|---|---|---|
| 1 | **Injection SQL** via `sprintf` dans `nextDocumentNumber` | `FacturationController.php:203,207` | GuardSec | ✅ Résolu — validation regex du prefix ajoutée |
| 2 | **Mots de passe hardcodés** en seed | `SeedCommand.php:191`, `ResetSeedCommand.php:165` | GuardSec | ✅ Résolu — remplacés par `bin2hex(random_bytes(16))` |
| 3 | **Endpoints sans `#[IsGranted]`** | 13 controllers identifiés | GuardSec | ✅ Résolu — `#[IsGranted` ajouté sur tous les controllers internes (Mecanicien, RdvPrestationCatalog, AdminAtelier, AdminUserProvisioning, etc.) |
| 4 | **34 entités sans tests** | `backend/src/Entity/*` (liste complète dans `agent-testpilot`) | TestPilot | 🔴 Ouvert — 5 entités couvertes (Facture, Devis, VOPurchase, VODepotVente, PieceDetachee) |
| 5 | **51 entités sans validation Symfony** | `backend/src/Entity/*` | GuardSec | 🔴 Ouvert |

## 🟠 HAUTE — Dettes structurantes

| # | Problème | Fichier(s) | Agent responsable | Statut |
|---|---|---|---|---|
| 6 | **God classes backend** (>500 lignes) | `AuthController` (793), `VOPurchaseController` (739), `FacturationController` (651), `AdminTemplatePreviewController` (606), `CompanionController` (543), `VODepotController` (547), `VORemiseEnEtatController` (530) | ArchiTech | 🟠 En cours — `RendezVousController` allégé de 184 lignes (618→434), `StatistiquesController` allégé de 100 lignes |
| 7 | **God classes frontend** (>50 KB) | `planning.vue` (96 KB), `ordres/[id].vue` (75 KB), `workshop.vue` (63 KB), `mecanicien.vue` (58 KB), `rdv/new.vue` (55 KB), `public/companion/[token].vue` (53 KB) | FrontCraft | 🟠 Ouvert |
| 8 | **`status` vs `statut`** incohérent | 18 entités | ArchiTech | 🟠 Ouvert |
| 9 | **Duplication RGPD snapshot** | `Facture`, `Devis`, `OrdreReparation` | ArchiTech | 🟠 Ouvert |
| 10 | **Duplication VO** (Purchase/Depot) | `VOPurchaseController`, `VODepotController`, `VOCompanionTrait` | ArchiTech | 🟠 Ouvert |
| 11 | **normalize* dupliqué frontend** | `planning.vue`, `clients/[id].vue`, `admin/ponts.vue` | FrontCraft | 🟠 Ouvert |
| 12 | **snake_case massif dans Vue** | ~15 fichiers majeurs | FrontCraft | 🟠 Ouvert |
| 13 | **Méthodes `@deprecated` encore utilisées** | `MecanicienController`, `ResetSeedCommand` | ArchiTech | 🟠 Ouvert |

## 🟡 MOYENNE — Améliorations

| # | Problème | Fichier(s) | Agent responsable | Statut |
|---|---|---|---|---|
| 14 | **Vulnérabilité postcss** (<8.5.10) | `frontend/package-lock.json` | GuardSec | ✅ Résolu — `npm audit fix` appliqué, 0 vulnérabilités |
| 15 | **Uploads sans magic bytes** | `ConfigController`, `CompanionController`, `VODocumentService` | GuardSec | 🟡 Ouvert |
| 16 | **Pas d'unwrapHydra centralisé** | 15+ duplications | FrontCraft | ✅ Résolu — `utils/hydra.ts` créé |
| 17 | **Pas de `useAsyncAction` composable** | 40+ pages avec try/catch/toast/loading | FrontCraft | ✅ Résolu — `composables/useAsyncAction.ts` créé |
| 18 | **Tests E2E incomplets** | Manquent : facturation, OR, CERFA, gardiennage | TestPilot | 🟡 Ouvert |
| 19 | **Console.warn en prod** | `useNotifications.ts` (5 occurences) | FrontCraft | 🟡 Ouvert |
| 20 | **Rôles legacy** (`role` string + `RoleMetier`) | `User`, `RoleMetier` | ArchiTech | 🟡 Ouvert |

## 🟢 FAIBLE — Refontes futures

| # | Problème | Fichier(s) | Agent responsable | Statut |
|---|---|---|---|---|
| 21 | **Génération PDF dispersée** | 10+ méthodes dans 4 controllers | ArchiTech | 🟢 Ouvert |
| 22 | **Store VO monolithique** (512 lignes) | `stores/vo.ts` | FrontCraft | 🟢 Ouvert |
| 23 | **InterventionType deprecated** | Entière entité | ArchiTech | 🟢 Ouvert |
| 24 | **ConfigController mixte** | Config + bootstrap + logo + seed | ArchiTech | 🟢 Ouvert |

## Historique des résolutions
| Date | Problème | Agent | Commit |
|---|---|---|---|
| 2026-05-01 | Module Stock complet (backend + frontend + tests) | Multi-agent | `5201969` |
| 2026-05-03 | Migration mouvements_stock + seed + tests | Multi-agent | `b9dcb3c` |
| 2026-05-03 | Création skill system (7 skills) | DocuMind | `b9dcb3c` |
| 2026-05-05 | Extract `StatisticsService` from `StatistiquesController` | ArchiTech | multiple |
| 2026-05-05 | Extract `RendezVousViewService` + `AuditFormatter` from `RendezVousController` | ArchiTech | `73d0af7` |
| 2026-05-05 | 5 entités testées (Facture, Devis, VOPurchase, VODepotVente, PieceDetachee) | TestPilot | multiple |
| 2026-05-05 | Centralize `unwrapHydra` + `useAsyncAction` | FrontCraft | `7712bb1` |
