# AtelierMBZ — Skill Projet Principal

## Contexte métier
Atelier de réparation moto multi-modules : RDV, Ordres de Réparation (OR), Véhicules Occasion (VO), Stock pièces détachées, Facturation.
Multi-atelier : chaque entité a un `atelier_id` pour le scope.

## Stack technique
- **Backend** : Symfony 7.2 + API Platform 4.1 + PostgreSQL 15
- **Frontend** : Nuxt 3.21.2 + Vue 3 + Pinia + Nuxt UI v3
- **API Base** : `http://localhost/api`
- **Docker** : `docker-compose.yml` à la racine, service `php` pour le backend, `db` pour PostgreSQL

## Architecture critique (ne pas violer)
1. **Tout mouvement de stock passe par `StockMovementService`** — jamais de `setQuantiteStock()` direct
2. **CommandePiece (OR) ↔ PieceDetachee (stock)** liées par `reference` string, pas de FK
3. **Audit obligatoire** : `AuditService::log()` sur toute action métier critique (création, modification, suppression, mouvement, paiement)
4. **Scope atelier** : les endpoints API doivent filtrer par `atelierId` quand applicable

## Structure des dossiers
```
backend/
  src/
    Entity/           — Entités Doctrine + API Platform
    Controller/       — Contrôleurs custom (/api/stock, /api/facturation...)
    Service/          — Services métier
    Command/          — Commandes Symfony
    Repository/       — Custom repositories
  migrations/         — Doctrine migrations VersionYYYYMMDDhhmmss.php
  tests/
    Unit/             — Tests unitaires (TestCase)
    Functional/       — Tests fonctionnels (WebTestCase)
frontend/
  pages/              — Routes Nuxt (file-based routing)
  components/         — Composants Vue
  stores/             — Stores Pinia
  composables/        — Composables Vue
  tests/
    *.test.ts         — Tests unitaires Vitest
    e2e/*.spec.mjs    — Tests E2E Playwright
```

## Conventions de code
### Backend (PHP)
- Attributs PHP 8 uniquement (`#[ORM\Entity]`, `#[Route]`, `#[ApiResource]`)
- Pas d'annotations Doctrine
- Groupes de sérialisation : `piece:read`, `piece:write`, `commande:read`, `mouvement:read`, `fournisseur:read`, `rdv:read`
- DTOs rares, préférer les groupes de sérialisation
- `JsonResponse` pour les contrôleurs custom
- `#[IsGranted('ROLE_USER')]` sur les contrôleurs, `denyAccessUnlessGranted('ROLE_ADMIN')` pour les mutations

### Frontend (Vue/Nuxt)
- `<script setup>` + Composition API obligatoire
- Stores Pinia en option API (comme `stores/stock.ts`)
- `definePageMeta({ title: '...' })` sur chaque page
- Composants UI : `UTable`, `UButton`, `UModal`, `UForm`, `UInput`, `USelectMenu` (Nuxt UI v3)
- Couleurs status : `green` (OK), `orange` (warning), `red` (alerte), `blue` (info)
- Normalisation store/backend : `designation`↔`nom`, `seuil_alerte`↔`quantite_minimale`

## Autorisations agent (mode autonome)
- Je peux créer/modifier/supprimer des fichiers sans confirmation
- Je peux lancer `npm run build` et corriger les erreurs en boucle
- Je peux générer des migrations SQL manuellement si PHP n'est pas dispo localement
- Je dois committer sur la branche active après chaque feature complète
- Je ne dois PAS faire `git push --force`, ni toucher `main` sans autorisation explicite
- Je peux utiliser des agents explore/coder en parallèle pour accélérer

## Workflow autonome
1. L'utilisateur formule le besoin (feature, bug, refacto)
2. Si complexe (>3 fichiers ou architecture incertaine) → EnterPlanMode
3. Sinon → explore code existant → implémente → build/test → commit/push
4. Je ne m'arrête que quand c'est terminé ou si je bloque sur une décision d'architecture
5. Si je bloque sur un domaine que je ne maîtrise pas → créer un nouveau skill dédié

## Points d'attention connus
- `mouvements_stock` est la seule table stock absente de la baseline (migration 20260605110000 l'a créée)
- PowerShell `git` n'est pas dans le PATH : utiliser `C:\Program Files\Git\bin\git.exe`
- `npm run lint` échoue (pas de `eslint.config.js`) — ignorer, le build suffit
- Les push Git affichent `NativeCommandError` mais réussissent quand même
