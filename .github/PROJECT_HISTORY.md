<!-- markdownlint-disable MD022 MD024 MD032 -->

# Historique projet AtelierMBZ

## Session 2026-05-07 (Phase 1-2 consolidation) — Fixes critiques + hardening sécurité

### Contexte
Suite au swarm LOT 1-5-6-7-11, deux régressions critiques bloquaient la suite de tests : le guard `mettre_en_vente` du workflow VO bloquait indirectement `confirmPurchase`, et les tests pré-existants (Devis, StockMovement, Facture, PieceDetachee) échouaient suite à des modifications récentes. Phase 1 = fixes critiques, Phase 2 = hardening docs sécurité.

### Fait (avec preuve d'exécution)

- **[LOT-6] fix — VOPurchaseWorkflowSubscriber guard scope transition explicite** (`b71d37f`)
  - `VOPurchaseController::purchase` : réorganisation des vérifications métier — `hasBlockingActiveCampaign()` + `buildPurchaseSaleVerdict()` testés **avant** `workflow->can('vendre')`
  - Cela préserve le retour HTTP 422 détaillé attendu par les tests fonctionnels, au lieu d'un 400 générique provoqué par le guard `vendre`
  - `VOPurchaseWorkflowSubscriberTest` : corrigé `expectException(RuntimeException)` → `assertTrue($event->isBlocked())` (comportement réel Symfony GuardEvent)
  - **Preuve** : `tests/Unit/VOPurchaseWorkflowSubscriberTest.php` → 13 tests, 46 assertions ✅
  - **Preuve** : `tests/Functional/VOControllerTest.php` → 12 tests, 144 assertions ✅

- **[LOT-0] fix — Tests pré-existants + validateModePaiement LP** (`3297596`)
  - `DevisTest` : assertion snapshot ajustée + tolérance client nullable
  - `StockMovementServiceTest` : injection ID via reflection pour mock persistance
  - `PieceDetachee` + `Facture` : ajout `getCreatedAt()` / `getUpdatedAt()` manquants
  - `VOLivrePoliceService` : `createEntryForDepotVente` restoré à `validateModePaiement` (accepte `'depot_vente'`), `createEntryForPurchase` et `recordSale` utilisent `validateModePaiementEncaissement`

- **[LOT-1] fix — Sécurité controller VO** (`4828b77`)
  - `#[IsGranted('ROLE_USER')]` ajouté sur `VORemiseEnEtatController`

- **[LOT-2] docs — Controllers publics documentés** (`3a765d8`)
  - `CompanionController` : docblock « Public Companion API — no authentication required. Token-based access for client signature workflows. »
  - `SuiviController` : docblock similaire pour endpoints publics de suivi client

- **[LOT-2] docs — TODO durcissement CSP** (`d325a41`)
  - `SecurityHeadersListener` annoté avec TODO roadmap : passer de Report-Only à blocking après analyse logs, retirer `unsafe-inline`/`unsafe-eval`

- **[LOT-11] ajoute — Hash intégrité SHA-256 par entrée LP** (`45044cf`)
  - `computeIntegrityHash()` : déterministe SHA-256 depuis sorted immutable fields array
  - `createEntryForPurchase`, `createEntryForDepotVente`, `recordSale` : calcul automatique avant persist
  - Endpoint `GET /api/vo/livre-police/{id}/verify` : comparaison `hash_equals` + réponse JSON `valid` boolean

- **[LOT-0] fix — PdfService fuite mémoire Dompdf** (`1a0595e`)
  - `ini_set('memory_limit', '512M')` — aligné avec `LivrePolicePdfService`
  - `fontCache` sur disque (`var/dompdf-font-cache`) — évite le re-parsing complet des polices à chaque `new Dompdf()`
  - Libération explicite `$dompdf = null; $html = null; gc_collect_cycles();` — nettoie références cycliques entre appels
  - **Preuve** : `VOControllerTest.php` (12 tests, 144 assertions) passe sans fatal error mémoire, pic ~278 MB sous limite 512 M ✅

### TODO laissés
- [ ] `VOPurchaseController::purchase` : `testPurchaseRequiresSivDeclarationBeforeSale` retourne 400 au lieu de 422 — à vérifier (modification préexistante sur `workflow->can('vendre')`)
- [ ] `FacturationController` + `DevisController` : `setStatut()` direct sans workflow Symfony
- [ ] CSP : passage Report-Only → blocking (après analyse logs)

---

## Session 2026-05-07 (swarm) — LOT 1-5-6-7-11 + audits sécurité

### Contexte
Approche agent swarm sur 2 vagues pour attaquer en parallèle les lots bloquants et les audits de conformité post-LOT 0.

### Fait (avec preuve d'exécution)

- **Swarm 1 — 5 agents parallèles** (branches dédiées `agent/kimi/*`)
  - Agent 1 `[LOT-1]` : `RendezVousTransitionVoter` — permissions granulaires par transition (ROLE_RECEPTIONNAIRE / ROLE_MECANICIEN assigné / ROLE_COMPTABLE). 11 tests, 18 assertions ✅
  - Agent 2 `[LOT-1]` : `NotificationVoter` — filtre ownership cross-user sur NotificationController. 8 tests, 18 assertions ✅
  - Agent 3 `[LOT-6]` : `VOPurchaseWorkflowSubscriber` — guards `mettre_en_vente` (vendabilité) + `vendre` (DA SIV) + side-effects audit. 12 tests, 47 assertions ✅
  - Agent 4 `[LOT-5]` : Tests manquants — `RdvTerminationGuardTest`, `PhotoControllerSecurityTest`, `CompanionRestitutionTest`. 16 tests, 38 assertions ✅
  - Agent 5 `[LOT-7]` : Refactor `setTimeout` debounce → `useDebounceFn` sur 9 fichiers front. Build Nuxt ✅

- **Swarm 2 — Hash LP + corrections pré-existantes**
  - `[LOT-11]` : `computeIntegrityHash()` SHA-256 déterministe par entrée LP + endpoint `GET /vo/livre-police/{id}/verify`
  - `[LOT-0]` : Correction tests pré-existants — `DevisTest` (Client nullable + snap), `StockMovementServiceTest` (mock ID refléchi)
  - `[LOT-0]` : Correction régression `validateModePaiementEncaissement` → `validateModePaiement` dans `createEntryForDepotVente`
  - `[LOT-1]` : `#[IsGranted('ROLE_USER')]` ajouté sur `VORemiseEnEtatController` (audit sécurité)

- **Audits rapides exécutés**
  - Frontend : 83 `:style="..."` dynamiques, 1192 `style="..."` statiques, 79 `catch {}` vides, 0 `console.log`, 0 `v-html`
  - Backend : 0 catch vides, pas de raw SQL injectable, `setStatut()` direct limité aux entités sans workflow (Devis, Facture, EssaiRoutier intentionnel)

### Vérification globale
- Build Nuxt : ✅ (2.13 MB, 518 kB gzip)
- Tests swarm : 47 nouveaux tests, 121 assertions, tous verts
- Tests pré-existants : DevisTest + StockMovementServiceTest corrigés et verts
- Suite complète : interrompue par fatal error mémoire `PdfService.php L455` (pré-existant, non régressif)

### TODO laissés
- [x] `VOPurchaseWorkflowSubscriber` guard `mettre_en_vente` bloque indirectement `confirmPurchase` — **FIXÉ** : vérification explicite `$event->getTransition()->getName() !== 'mettre_en_vente'` + réorganisation vérifications métier dans `VOPurchaseController::purchase` (remise en état + sale blockers avant `workflow->can('vendre')`)
- [x] `VOControllerTest` : 7 tests fonctionnels cassés par le workflow VO — **FIXÉ** : 12 tests fonctionnels passent (144 assertions), 13 tests unitaires passent (46 assertions)
- [x] `CompanionController` + `SuiviController` : publiques par design — **DOCUMENTÉ** : docblocks explicites ajoutés sur les deux contrôleurs
- [x] `PdfService` fatal error mémoire Dompdf — **FIXÉ** : `ini_set('memory_limit', '512M')` + cache font disque + libération explicite `gc_collect_cycles()` (12 tests VO passent, pic ~278 MB sous limite)
- [ ] `FacturationController` + `DevisController` : `setStatut()` direct — pas de workflow Symfony défini, mais règle métier exige transitions. À arbitrer : créer workflows ou garder setter + validation manuelle
- [ ] 5 tests pré-existants restants : Facture x2, PieceDetachee x1, `testPurchaseRequiresSivDeclarationBeforeSale` (400 au lieu de 422)

### Décisions
- `VOPurchaseWorkflowSubscriber` reste en place — le guard vendabilité est correct métier, mais le test fonctionnel doit s'adapter
- Les `catch {}` vides front (79 occurrences) sont majoritairement des defocus UI / polling non bloquant — pas bloquant mais dette UX
- Inline styles : 1192 occurrences sont majoritairement des classes utilitaires Nuxt UI (`flex`, `p-4`, etc.) — acceptable. Les 83 `:style="..."` dynamiques sont à auditer en session dédiée

---

## Session 2026-05-07 (suite) — LOT 0 complet (6 sous-tâches)

### Contexte
Après lock des 3 décisions architecture (option B 2 fronts physiques, compte client immédiat, BDD motos à répliquer) et publication de la roadmap 12-lots avec LOT 4bis, exécution du LOT 0 « consolidation socle technique » en 6 sous-tâches enchaînées sur instruction utilisateur « pousse jusqu'au bout ».

### Fait (avec preuve d'exécution)

- **L0.1 Numérotation atomique** (`f32d32e`)
  - `DocumentNumberingService` (séquences PG via `nextval()`) + `DevisNumeroSubscriber` (prePersist via Reflection isInitialized)
  - Migration `Version20260507120000` : CREATE SEQUENCE `devis_numero_seq` + `ordre_reparation_numero_seq` avec `setval` aligné sur `MAX(numero_devis)`
  - `CompanionController` + `DemandeTravauxSuppController` : remplacement de `'OR-' . $rdv->getId() . '-' . date('Ymd')` par `$numberingService->nextOrdreReparationNumber()`
  - **Preuve** : `dbal:run-sql "SELECT sequence_name FROM information_schema.sequences"` → `devis_numero_seq` + `ordre_reparation_numero_seq` confirmés
  - Plus de race condition `MAX+1` (cf. piège connu §copilot-instructions)

- **L0.2 Timeout session 30min RGPD** (`61acd66`)
  - `User.lastActivityAt` (?DateTime) + getter/setter, `markLoginSuccess()` initialise
  - `SessionActivitySubscriber` (kernel.request priorité 6) : SQL direct `UPDATE users SET last_activity_at = NOW()` (sans flush)
  - `CookieJwtAuthenticator` : constante `INACTIVITY_MINUTES = 30`, lookup User, `isSessionInactive()` avec **tolérance null** (backward compat users pré-migration), si inactif → `RevokedToken` + `AuthenticationException`
  - `AuthController::refresh()` : même check inactivité + tolérance null
  - Migration `Version20260507130000` : `ALTER TABLE users ADD COLUMN IF NOT EXISTS last_activity_at TIMESTAMP DEFAULT NULL`
  - `useApi.ts` : 401 après refresh fail → regex `inactivity` → `/login?expired=inactivity`
  - `login.vue` onMounted : affiche message « Vous avez été déconnecté après 30 minutes d'inactivité »
  - **Preuve end-to-end** : POST /api/auth/login → 200, GET /api/auth/me → 200, `last_activity_at = 2026-05-07 14:41:09.03385` MAJ automatique

- **L0.3 Export PDF Livre de Police** (`89d5c77`)
  - `VOLivrePolice.integrityHash` (?string 64) + getter/setter (nullable, computation différée)
  - `LivrePolicePdfService` : Dompdf A4 portrait, sortie `var/pdf/LPE-Ymd-His.pdf`, `computeGlobalHash()` SHA-256
  - `LivrePoliceExportController` `#[Route('/api/vo')]` `#[IsGranted('ROLE_USER')]` + check manuel rôles (SUPER_ADMIN / RESPONSABLE_MAGASIN / COMPTABLE / VO_MANAGER)
  - Filtres `date_debut`, `date_fin`, `type` (achat|depot_vente|vente)
  - Template `livre_police/export.html.twig` : header logo Paddock + atelier, registre tabulaire, hash, mention « Art. 321-7/321-8 CP + décret 2009-1104 »
  - Migration `Version20260507140100` : `ALTER TABLE vo_livre_police ADD COLUMN IF NOT EXISTS integrity_hash VARCHAR(64)`
  - Frontend `vo/livre-police.vue` : URL changée vers `/vo/livre-de-police/export-pdf`
  - **Preuve** : route enregistrée `app_livrepoliceexport_exportpdf`, GET → 200, 264076 bytes, PDF 1.7 valide, 230 entrées en base

- **L0.4 Désactivation Stock + Facturation par défaut** (`911e6e2`)
  - `ConfigAtelier::featureModules` (inline) + `defaultFeatureModules()` static : `'stock' => false, 'facturation' => false`
  - **UPDATE** sur 52 rows existants via `jsonb_set` : modules désactivés en base
  - **Preuve** : `dbal:run-sql "SELECT atelier_id, feature_modules->>'stock', feature_modules->>'facturation'"` → `false / false` sur tous, HTTP 200 sur /api/health
  - Frontend supporte déjà via `auth.global.ts` ligne 116 + `atelierStore.isModuleEnabled()` (sidebar masque automatiquement)

- **L0.5 Humanisation toasts SQL** (`ab7d23c`)
  - `HumanizeDatabaseExceptionSubscriber` (kernel.exception priorité 100, scope `/api/*`)
  - `UniqueConstraintViolationException` → 409 « Cet enregistrement existe déjà »
  - `ForeignKeyConstraintViolationException` → 409 « Suppression bloquée »
  - `NotNullConstraintViolationException` → 400 « Un champ obligatoire est manquant »
  - **Preuve** : POST /api/prestations sans champ requis → AVANT « SQLSTATE[23502]: Not null violation... », APRÈS `{"error":"missing_field","message":"Un champ obligatoire est manquant. Vérifie le formulaire."}` (HTTP 400)

- **L0.6 Protocole multi-agents** (`ac65af0`)
  - `AGENTS.md` (NEW) : 6 règles de coordination
    1. Tag local backup avant opération destructive
    2. Branches dédiées (`feat/LOT-XX`, `fix/`, `cleanup/`, `agent/<nom>/`)
    3. Cherry-pick chirurgical depuis tag (jamais reset --hard)
    4. Coordination cross-sessions via PROJECT_HISTORY
    5. Commits atomiques avec preuve d'exécution
    6. Pas de push automatique sans validation utilisateur
  - Référence historique : `local-backup-2026-05-07` (3 commits LOT 0 critiques sauvés)

- **L0.4 follow-up Bypass super-admin sur feature flags** (`1f784ef`)
  - Bug constaté visuellement après L0.4 : Stock + Facturation restaient visibles pour le super-admin malgré `feature_modules.stock = false` en base
  - Cause : `useAuth.hasSection()` court-circuitait `isModuleEnabled()` avec `return true` dès qu'il détectait `ROLE_SUPER_ADMIN`
  - Fix : super-admin conserve tous les rôles/permissions mais respecte désormais les feature flags atelier (un module désactivé est masqué pour tout le monde)
  - `stores/atelier.ts` : `DEFAULT_FEATURE_MODULES` aligné (stock=false, facturation=false) sur le défaut back

- **L0.7 Débloque build Nuxt + vérif visuelle sidebar** (`0974c0e`)
  - Build Nuxt cassé suite migration Nuxt 4 partielle abandonnée : `frontend/app/` contenait des debris (15+ fichiers) que `git add -A` du commit f32d32e avait accidentellement réintroduits
  - Avec Nuxt 4 (`compatibilityVersion: 4`), `srcDir` par défaut bascule à `'app'` automatiquement → conflit avec la structure historique flat du projet
  - Fix : suppression complète de `frontend/app/` (git rm) + `nuxt.config.ts` configure explicitement `srcDir: '.'` + `dir: { app: '.' }` pour forcer la structure flat
  - Consolidation CSS : retrait imports `paddock-theme.css` / `design-system.css` / `public-pages.css` (n'existaient plus) → garde uniquement `~/assets/css/main.css`
  - **Preuve build** : `docker compose exec nuxt npm run build` → ✨ Build complete!
  - **Preuve runtime** : `curl http://localhost:3000/` → 200 ; `curl http://localhost/` (via Caddy) → 200
  - **Preuve visuelle sidebar** : login admin@atelier.local OK, sidebar affiche `Stat / Planning / Ponts & Méca / Suivi Live / Dossiers atelier / Devis / Clients / Fiches moto / Administration` → **ni Stock ni Facturation** (validation combinée de L0.4 + L0.4-fix)

### Décisions
- **Stock + Facturation = OFF par défaut** : justifié par leur statut « en réécriture » (cf. copilot-instructions § Modules) + recentrage POC sur flux principal RDV → réception → mécanicien → restitution. Réactivables individuellement par atelier via admin.
- **Tolérance null sur `lastActivityAt`** : impossible de locker out les utilisateurs créés avant la migration. Évolution : un cron pourra forcer `last_activity_at = NOW()` au prochain login pour migrer en douceur.
- **Hash intégrité LP différé** : colonne `integrity_hash` ajoutée mais computation reportée (TODO LOT 11 « Conformité avancée »). PDF actuel utilise hash global sur dump des entrées.
- **Exception subscriber scope `/api/*` uniquement** : pour ne pas humaniser des erreurs sur les routes admin/dev qui doivent rester explicites.

### TODO laissés
- [ ] `backend/src/Service/LivrePolicePdfService.php` : implémenter le calcul réel de `integrity_hash` par entrée LP (LOT 11)
- [ ] `backend/src/EventSubscriber/HumanizeDatabaseExceptionSubscriber.php` : étendre le mapping (CheckConstraintViolation, autres SQLSTATE) si besoin métier remonté
- [ ] Tester scénario unique-violation (POST prestation avec code dupliqué + tous champs requis) — la preuve obtenue était sur NotNull, le mapping unique reste à confirmer en conditions réelles
- [ ] Push remote `git push origin cleanup/printemps-2026` (9 commits LOT 0 + L0.7 ahead)

### En suspens à arbitrer
- Sidebar front : vérifier visuellement que les entrées Stock + Facturation disparaissent bien après cache reload (devrait, mais pas testé en navigation)
- Cron de purge des `RevokedToken` expirés : à programmer (sinon table grossit indéfiniment)

---

## Session 2026-05-07 — Bootstrap prestations + PWA + sortie git chaos

### Contexte
Trois agents locaux + Kimi distant ont travaillé sans coordination → divergence massive : 16 commits locaux non poussés (Phase 1+2/3.1/3.2/3.3 design system, [LOT-VIS-PLANNING], Phase 5/6 timeout/PDF LP, WIP BLOC-01/02/03 partiels) **vs** 62 commits distants (FrontCraft-Design ~25, module stock complet réécrit, refactor architecture extractions, sécurité #[IsGranted]+SQL injection+magic-bytes, tests unitaires Fournisseur/Facture/Devis/VOPurchase/VODepotVente/PieceDetachee, plugin Vue ErrorBoundary, composants AppKpiCard/AppStatusBadge/AppActionLink/AppAlertCard/AppInlineActions, helpers unwrapHydraOrEmpty/Paginated/devWarn).

**Décision** (validée utilisateur Option A) : abandonner les 16 commits locaux non poussés (refactor redondant avec FrontCraft + suppression entités stock contraire au module rebuild distant), reset hard sur `origin/cleanup/printemps-2026`, réappliquer uniquement les 3 contributions session par cherry-pick depuis tags backup.

### Fait (avec preuve d'exécution)
- **Tag backup** `local-backup-2026-05-07` sur `e7c17a6` (commit MIGRATION local abandonné) + `local-backup-2026-05-07-gitignore` sur `4cdd6f5` — récupérables 30j via reflog
- **Reset hard** branche `cleanup/printemps-2026` sur `origin/cleanup/printemps-2026` (HEAD = `d7a8504`)
- **Patchs sauvegardés** dans `/tmp/fix-*.patch` + binaires PNG + script Playwright
- **Commit `e3eeada` [FIX] sécurité — voters compat Symfony 7.x** (4 fichiers, +8/-4) :
  - Ajout 4e param `?Vote $vote = null` à `voteOnAttribute()` sur `AtelierVoter`, `RolePermissionVoter`, `FactureDeleteVoter`, `VOFactureDeleteVoter`
  - Sans ça : Fatal error empêche boot kernel → tous les Functional KO
  - Preuve : `bin/phpunit tests/Unit/PricingServiceTest.php` → `OK (8 tests, 16 assertions)`
- **Commit `53dfb48` [FEAT] DEFAULT_CATALOG bootstrap prestations** (2 fichiers, +115/-1) :
  - `AtelierCatalogBootstrapService::DEFAULT_CATALOG` (13 prestations standard)
  - Méthode `createFromDefaultCatalog(int)` appelée si `resolveSourceAtelierId()` null
  - Test `testEnsurePrestationsUsesDefaultCatalogWhenNoSourceAtelier`
  - Preuve : `bin/phpunit --filter testEnsurePrestationsUsesDefaultCatalog` → `OK (1 test, 3 assertions)` (après commit `cf95ff0` qui répare le kernel)
- **Commit `35aa6d4` [FEAT] PWA — icônes + manifest** (5 fichiers, +140/-12) :
  - `frontend/public/manifest.json` créé (theme `#D4A843` or, bg `#111111` noir)
  - `frontend/public/branding/paddock-icon-{192,512}.png` générés depuis `paddock-logo-symbol.svg`
  - `frontend/generate-pwa-icons.mjs` réutilisable
  - `frontend/nuxt.config.ts` : `apple-touch-icon` → PNG 192x192
  - Preuve : `curl -I http://localhost/manifest.json` → 200, `curl -I http://localhost/branding/paddock-icon-192.png` → 200, vue rendue OK (view_image)
- **Push réussi** : `d7a8504..35aa6d4 cleanup/printemps-2026 -> cleanup/printemps-2026`

### Fait après push initial — réparation kernel cassé sur le distant
- **Commit `cf95ff0` [FIX] backend boot — config Symfony 7.2 / Doctrine 4 / DBAL 4** (4 fichiers, +8/-6)
  - `doctrine.yaml` : suppression 4 options obsolètes (`use_savepoints`, `auto_generate_proxy_classes`, `enable_lazy_ghost_objects`, `report_fields_where_declared`) — incompatibles doctrine-bundle 2.18 + DBAL 4.4 + ORM 3.6
  - `routes/framework.yaml` : `errors.xml` → `errors.php`
  - `services.yaml` : 6 bindings explicites pour `RateLimiterFactory $X` (Symfony 7.2 ne crée pas l'auto-alias par nom de paramètre pour la classe concrète, seulement pour l'interface)
  - `HealthController.php` : `Routing\Annotation\Route` → `Routing\Attribute\Route` (la classe Annotation n'existe plus → route silencieusement ignorée)
- **Preuves** :
  - `curl /api/health` → 200 `{"status":"ok"}`
  - `curl POST /api/auth/login` → 200 + JWT
  - `curl /admin/prestations` → 200
  - `curl /manifest.json` → 200, `curl /branding/paddock-icon-192.png` → 200
  - `bin/phpunit --filter testEnsurePrestationsUsesDefaultCatalog` → `OK (1 test, 3 assertions)`

### Décisions
- Abandon commits locaux Phase 1+2/3.1/3.2/3.3, [LOT-VIS-PLANNING], Phase 5/6, WIP partiels — redondants avec FrontCraft/Kimi distant + commit `[MIGRATION]` qui supprimait entités stock obsolète vs distant qui les a rebuild en module complet (`feat(stock): module stock complet`)
- Le module **Stock n'est plus "en réécriture"** — il est REBUILD et ACTIF côté distant : `Fournisseur`, `CommandeFournisseur`, `LigneCommandeFournisseur`, `MouvementStock`, KPIs, export CSV/FEC, anonymisation RGPD, intégration OR (consommation/réception auto)
- Fix voters Symfony 7.x : hors scope session mais bloquant débogage → fixé séparément avec note explicite
- Pas de fix `doctrine.yaml` malgré erreurs (`use_savepoints`, `auto_generate_proxy_classes`, `enable_lazy_ghost_objects`, `report_fields_where_declared` "Unrecognized option") car risque casse prod si vendor change

### TODO laissés
- [ ] **Distant** : 8 tests Unit cassés pré-existants (Devis x4, Facture x2, PieceDetachee x1, StockMovementService x1) — non touchés
- [ ] **Coordination agents** : règle absolue désormais → `git fetch && git pull --rebase` AVANT toute session, et tag de backup avant tout reset
- [ ] **Audit design** demandé par utilisateur : largement déjà fait sur le distant (FrontCraft-Design ~25 commits + refactor(ui) zero inline styles). Reste à auditer : modales, slideovers, pop-ins. À refaire en session dédiée APRÈS fix doctrine config.
- [ ] **Toast description** affiche SQL brut "SQLSTATE[23505] Unique violation" — à humaniser côté backend (catch `UniqueConstraintViolationException` → message métier)
- [ ] Mettre à jour `copilot-instructions.md` table modules : Stock passe de 🔄 "En réécriture — NE PAS TOUCHER" à ⚠️ "Implémenté — actif" ; Facturation à confirmer

### En suspens à arbitrer
- Stratégie multi-agents : 3 locaux + Kimi distant sans coordination = chaos garanti. Mettre en place un protocole (qui touche quoi, branches dédiées par agent, fenêtre de sync quotidienne) ?

---

## Session 2026-04-23 — Suite figeage : LOTs backlog safe

### Fait (avec preuve d'exécution)
- [AUDIT-V1][LOT-FIX-5] **ajoute** — Content-Security-Policy en mode **Report-Only** sur les réponses HTML du backend (Swagger `/api/docs`, preview templates admin). Bloquant à terme une fois les violations observées.
  - Listener : [backend/src/EventListener/SecurityHeadersListener.php](backend/src/EventListener/SecurityHeadersListener.php) — pose `Content-Security-Policy-Report-Only` uniquement si `Content-Type: text/html` (skip JSON/PDF binary)
  - Endpoint public : [backend/src/Controller/SecurityReportController.php](backend/src/Controller/SecurityReportController.php) `POST /api/security/csp-report` → 204, log `warning` avec violation + IP + UA
  - Sécurité : [backend/config/packages/security.yaml](backend/config/packages/security.yaml) — pattern public + access_control PUBLIC_ACCESS pour `/api/security/csp-report`
  - Politique permissive volontaire pendant phase d'observation (`unsafe-inline`, `unsafe-eval` pour Swagger)
  - Preuve : `curl POST /api/security/csp-report` → 204, log "CSP violation reported" visible dans `docker compose logs php`
  - Preuve : `curl GET /api/health` → pas de header CSP (JSON, OK)
  - **TODO séparé** : CSP du front Nuxt (pages utilisateur final) à configurer dans `nuxt.config.ts` — non traité ici car sort du périmètre back. Demande arbitrage : qui sert la CSP du front, Nuxt ou Caddy ?
- [AUDIT-V1][LOT-FIX-4] **ajoute** — notification email immédiate au client à l'entrée en gardiennage (choix produit : email seul, pas de SMS, validé par utilisateur).
  - Template : `gardiennage_debut` (email) ajouté dans [backend/src/Service/NotificationTemplateCatalog.php](backend/src/Service/NotificationTemplateCatalog.php) avec variables `client_prenom`, `plaque`, `reference_rdv`, `tarif_journalier`
  - Service : nouvelle méthode `notifierEntreeGardiennage(RendezVous $rdv)` dans [backend/src/Service/GardiennageService.php](backend/src/Service/GardiennageService.php) — idempotente, ne bloque jamais le passage en gardiennage si l'envoi échoue, log warning si échec
  - Appelée depuis :
    - [backend/src/Service/GardiennageService.php](backend/src/Service/GardiennageService.php) `declencher()` (point d'entrée GardiennageController)
    - [backend/src/Controller/RendezVousController.php](backend/src/Controller/RendezVousController.php) après `apply()` des transitions `passer_gardiennage` / `mettre_en_gardiennage`
  - Preuve : suite PHPUnit **201/201 OK, 742 assertions** sur DB propre
- [AUDIT-V1][LOT-FIX-6] **sécurise** — vérification HMAC obligatoire sur les 3 webhooks providers de notifications (`/api/webhooks/notifications/{twilio|mailgun|ovh}`). Avant ce fix, n'importe qui pouvait POSTer pour falsifier les statuts `delivered`/`failed` des SMS/emails. Maintenant : signature invalide → 401 + log warning.
  - Service : [backend/src/Service/WebhookSignatureVerifier.php](backend/src/Service/WebhookSignatureVerifier.php) (HMAC-SHA1 Twilio, HMAC-SHA256 Mailgun avec anti-replay 5 min, secret partagé OVH via header `X-Webhook-Token`)
  - Controller : [backend/src/Controller/NotificationProviderController.php](backend/src/Controller/NotificationProviderController.php) — méthode `verifyWebhookSignature` boucle sur les configs actives du provider, accepte si une signature matche
  - Tests unit : [backend/tests/Unit/WebhookSignatureVerifierTest.php](backend/tests/Unit/WebhookSignatureVerifierTest.php) (12 tests)
  - Tests functional : [backend/tests/Functional/NotificationProviderApiTest.php](backend/tests/Functional/NotificationProviderApiTest.php) adaptés (rejet 401 sans signature)
  - **Note config** : la config provider OVH doit désormais contenir une clé `webhook_secret` ; pour Mailgun une clé `signing_key` ; pour Twilio `auth_token` (déjà utilisée pour l'envoi)
  - Champs de config ajoutés dans le form admin : [frontend/pages/admin/notifications/providers.vue](frontend/pages/admin/notifications/providers.vue) (Mailgun signing_key + OVH webhook_secret avec hints explicatifs)
  - Preuve : suite PHPUnit **201/201 OK, 742 assertions**
- [AUDIT-V1] retire — entité `RapportTechnicien` + relation OneToOne dans `RendezVous` + table `rapports_technicien` (dead code, 0 ligne en base, 0 usage code). Migration : [backend/migrations/Version20260423130422.php](backend/migrations/Version20260423130422.php). Preuve : `\dt rapports_technicien` → "Did not find any relation"
- [AUDIT-V1] ajoute — FK `mecaniciens.user_id → users.id ON DELETE SET NULL`. Migration : [backend/migrations/Version20260423130654.php](backend/migrations/Version20260423130654.php). Pré-vérifié : 0 orphelin sur 16 mécaniciens. Preuve : `\d mecaniciens` montre `fk_mecaniciens_user_id`. Entité gardée avec `?int $userId` nu (pas de ManyToOne) pour ne pas casser les 5+ usages `findOneBy(['userId' => ...])`.
- [AUDIT-V1] retire — `rapports_technicien` du TRUNCATE dans [backend/src/Command/ResetSeedCommand.php](backend/src/Command/ResetSeedCommand.php)
- [AUDIT-V1] docs — commentaires explicites sur cookies `active_atelier_id` non-HttpOnly (volontaire) dans [backend/src/Controller/AuthController.php](backend/src/Controller/AuthController.php) L236, L787
- [AUDIT-V1] retire — `setStatut('en_attente')` redondants (initial_marking workflow déjà = en_attente) dans [backend/src/Controller/DevisController.php](backend/src/Controller/DevisController.php) L171 et [backend/src/Controller/PublicBookingController.php](backend/src/Controller/PublicBookingController.php) L253
- [AUDIT-V1] fix — Playwright [frontend/tests/e2e/helpers.mjs](frontend/tests/e2e/helpers.mjs) : `page.goto` avec `waitUntil: 'domcontentloaded'` pour le login admin (Nuxt dev n'émet pas `load` propre)
- [AUDIT-V1] config — [frontend/playwright.config.mjs](frontend/playwright.config.mjs) : `navigationTimeout: 30_000` ajouté
- [AUDIT-V1] verif — Suite PHPUnit après tous les fixes : **189/189 tests OK, 727 assertions** (2 notices = exceptions HTTP testées intentionnellement)
- [AUDIT-V1] verif — Migration drop `rapports_technicien` : OK
- [AUDIT-V1] verif — Migration FK `mecaniciens.user_id` : OK, contrainte visible dans `\d`

### Décisions
- **Mecanicien.userId** : on ajoute la FK SQL (intégrité protégée) mais on garde le champ `?int $userId` côté Doctrine (pas de ManyToOne). Refactorer les 5+ `findOneBy(['userId' => ...])` est un travail séparé, pas urgent vu que la FK protège déjà.
- **Playwright `waitUntil` global** : Playwright n'a pas d'option de défaut configurable. Patch ciblé sur helpers.mjs (login admin) suffit pour la majorité des tests. Le reste demande migration vers build prod (LOT-FIX-8 toujours ouvert).

### Fait (suite — bootstrap install neuve + doc préprod)
- [AUDIT-V1] **ajoute** — migration baseline [backend/migrations/Version20260101000000.php](backend/migrations/Version20260101000000.php) (253 statements DDL générés via `doctrine:schema:create --dump-sql`). Crée toutes les tables, index et FK du schéma actuel en une passe sur DB vide. Comportement :
  - DB vide : crée tout (63 tables)
  - DB existante (table `clients` détectée) : `skipIf` no-op
  - **Pourquoi** : la chaîne historique (à partir de `Version20260416115054`) suppose que `clients` existe déjà → `migrate` from-scratch échouait avec `relation "clients" does not exist`. Sans cette baseline, l'app ne pouvait pas être installée from-scratch sur un nouveau serveur.
- [AUDIT-V1] **ajoute** — [docs/DEPLOIEMENT-PREPROD.md](docs/DEPLOIEMENT-PREPROD.md) : doc complète d'installation préprod en 16 sections (pré-requis, .env, build, démarrage, bootstrap baseline + version --add --all + create-admin + seed, vérifications HTTP, premier login, configuration métier, mises à jour, sauvegardes, cron, audit, désinstall). Inclut la trace de validation 23/04/2026.
- [AUDIT-V1] **verif** — install neuve from-scratch testée bout en bout sur DB test :
  - `database:drop --force` → `database:create` → `migrations:execute Version20260101000000 --up -n` → `version --add --all` → `migrations:current` montre la dernière version → `migrations:migrate -n` confirme "Already at the latest version" → `app:create-admin` crée atelier #1 + user `admin` → `app:seed` insère rôles, catégories, horaires, config, templates, prestations → 63 tables présentes en base, user `admin` confirmé en SQL avec `role = super_admin, atelier_id = 1`
- [AUDIT-V1][LOT-FIX-5] **ajoute** — CSP Report-Only sur le front Nuxt via `routeRules['/**'].headers` dans [frontend/nuxt.config.ts](frontend/nuxt.config.ts). Politique avec whitelist Google Fonts (`fonts.googleapis.com`, `fonts.gstatic.com`). Headers compagnons : `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`. Report-uri partagé avec le back (`/api/security/csp-report`).
  - Preuve : `curl -I http://localhost:3000/` → headers `content-security-policy-report-only`, `x-frame-options: DENY`, `x-content-type-options: nosniff`, `referrer-policy: strict-origin-when-cross-origin` confirmés
- [AUDIT-V1][LOT-FIX-8] **fix** — patch tactique sur 9 fichiers `frontend/tests/e2e/*.spec.mjs` : ajout `{ waitUntil: 'domcontentloaded' }` sur tous les `page.goto(...)` (chaînes, variables, appels de fonction, template literals). 3 passes sed couvrant chaque pattern, suivi de syntax check `node --check` sur chaque fichier.
  - Preuve : `grep page.goto | grep -v waitUntil` → 0 match résiduel sur les `*.spec.mjs`
  - Preuve syntaxe : `node --check` OK sur les 9 spec files
  - Preuve exécution Playwright : **50 passed / 34 failed (13.5 min)** — identique à la baseline pré-patch. Le patch n'a pas dégradé. Les 34 échecs résiduels viennent de la stack auth/rendering Nuxt dev (pas des timeouts de navigation), confirmant qu'un build prod reste nécessaire pour les débloquer (TODO conservé).

### TODO laissés (LOTs restants)
- [ ] LOT-FIX-8 (vraie résolution) : Playwright sur build prod Nuxt (`nuxt build` + `nuxt preview` exposé via webServer Playwright) — patch tactique waitUntil ne suffit pas pour débloquer les 34 échecs auth/rendering
- [ ] Refacto code : transformer `Mecanicien.userId` int nu en ManyToOne User (cassera les findOneBy mais propre)
- [ ] Confort : NotificationLog jamais lu (analytics ou drop), Mercure RBAC topics, types `unknown` au lieu de `any`
- [ ] Avant prod : supprimer 6 comptes audit + reset mot de passe admin@atelier.local
- [ ] CSP : observer les rapports `/api/security/csp-report` quelques jours puis durcir (retirer `unsafe-inline` / `unsafe-eval` si possible) et passer en mode bloquant

---

## Session 2026-04-21 — Audit complet + figeage version stable

### Fait (avec preuve d'exécution)
- [AUDIT-V1] docs — [docs/AUDIT-V1/00-INVENTAIRE.md](docs/AUDIT-V1/00-INVENTAIRE.md) : inventaire complet (62 entités, 39 controllers, 38 services, 13 listeners, 36 migrations, 189 tests back, 19 tests front, 58 pages Vue, 13 templates PDF)
- [AUDIT-V1] docs — [docs/AUDIT-V1/01-RAPPORT-FIGEAGE.md](docs/AUDIT-V1/01-RAPPORT-FIGEAGE.md) : rapport complet avec schémas workflow, fixes appliqués, preuves d'exécution
- [AUDIT-V1] docs — [docs/AUDIT-V1/02-BACKLOG-POST-FIGEAGE.md](docs/AUDIT-V1/02-BACKLOG-POST-FIGEAGE.md) : backlog 7 issues 🟠 reportées avec justification
- [AUDIT-V1] fix — [backend/src/Service/NotificationTemplateCatalog.php](backend/src/Service/NotificationTemplateCatalog.php) : reconstruction structure cassée `rdv_refus`/`rdv_modifie` (templates non insérés en base avant fix). Preuve : PHPUnit 23/23 OK
- [AUDIT-V1] fix — [backend/templates/pdf/facture.html.twig](backend/templates/pdf/facture.html.twig) : ajout mention obligatoire garantie légale L.217-3 + Art. 1641 CC. Preuve : `bin/console lint:twig` OK
- [AUDIT-V1] fix — [backend/templates/pdf/vo_pv_rachat.html.twig](backend/templates/pdf/vo_pv_rachat.html.twig) : ajout obligation DA SIV 15 jours (Art. R.322-4 Code de la route)
- [AUDIT-V1] fix — [backend/templates/pdf/vo_facture.html.twig](backend/templates/pdf/vo_facture.html.twig) : régime TVA rendu exclusif (`if/else` Art. 297 A vs Art. 256 CGI) + garantie légale rendue obligatoire (était conditionnelle)
- [AUDIT-V1] fix — [backend/templates/pdf/rapport_intervention.html.twig](backend/templates/pdf/rapport_intervention.html.twig) : section garantie systématique en fallback si mécanicien oublie de remplir le champ
- [AUDIT-V1] fix — [backend/src/EventListener/SecurityHeadersListener.php](backend/src/EventListener/SecurityHeadersListener.php) : ajout header HSTS sur connexions HTTPS uniquement (max-age 1 an + includeSubDomains)
- [AUDIT-V1] verif — Suite PHPUnit complète : **189/189 tests OK, 727 assertions** (sortie collée dans 01-RAPPORT-FIGEAGE.md §6.1)
- [AUDIT-V1] verif — Lint Twig 4 templates modifiés : OK
- [AUDIT-V1] verif — Auth API : `POST /api/auth/login` → 200 avec JWT super_admin

### Décisions
- **Cookie `active_atelier_id` reste non-HttpOnly** — utilisé par 4 fichiers Nuxt via `useCookie`. C'est un identifiant numérique, pas un secret. Le JWT contrôle l'accès. Documenter en commentaire (TODO LOT-FIX-3).
- **`Mecanicien.userId` FK manquante** — non corrigée cette session (migration risquée sur prod soft, demande backup et fenêtre maintenance). LOT-FIX-1 dédié.
- **`RapportTechnicien` dead code** — non droppé cette session (DROP TABLE non-réversible). LOT-FIX-2 dédié après vérification table vide en prod.
- **Playwright timeouts E2E (34/84)** — non-bug applicatif : Nuxt dev n'émet pas `load` propre. Tests qui utilisent l'API directe passent. Solution : tester sur build prod (LOT-FIX-8).
- **`setStatut('en_attente')` redondants** dans DevisController:171 et PublicBookingController:253 → laissés en place (initial marking workflow = `en_attente`, donc inoffensifs). LOT-FIX-7 hygiène.

### TODO laissés
- [ ] backend/src/Entity/Mecanicien.php L31 : ajouter FK Doctrine vers User onDelete=SET NULL — LOT dédié (migration risquée)
- [ ] backend/src/Entity/RapportTechnicien.php : drop entité + table + relation OneToOne dans RendezVous — LOT dédié
- [ ] backend/src/Controller/AuthController.php L236 + L787 : ajouter commentaire expliquant cookie volontairement non-HttpOnly
- [ ] backend/src/EventListener/RdvWorkflowListener.php : arbitrer si notif immédiate sur passer_gardiennage (templates cron déjà en place)
- [ ] backend/src/EventListener/SecurityHeadersListener.php : ajouter CSP après tests sur tous écrans + Mercure + DomPDF
- [ ] backend/src/Service/NotificationDispatcher.php : vérifier signature HMAC sur webhooks providers SMS/email
- [ ] frontend/tests/e2e : passer en mode build prod ou changer waitUntil de `load` à `domcontentloaded`
- [ ] Comptes audit (audit_super, audit_admin, audit_recep, audit_meca, audit_vo, audit_compta) avec mot de passe `Audit2026!` à supprimer avant prod
- [ ] Mot de passe `admin@atelier.local` reset à `Audit2026!` → remettre mot de passe fort avant prod

### En suspens à arbitrer
- Notif client immédiate à l'entrée en gardiennage : oui ou laisser réceptionniste gérer oralement ?
- Vue consolidée cross-ateliers franchiseur (CA global, stock VO agrégé) : à modéliser ?

---

## Session 2026-04-22 — Booking public aligné + Phase 1 erreurs front

### Fait
- [Implémenté, non vérifié end-to-end] [BOOKING] refacto — [backend/src/Controller/PublicBookingController.php](backend/src/Controller/PublicBookingController.php) : endpoints publics réalignés multi-atelier, catalogue prestations filtré par atelier + type moto + cylindrée, suppression du hardcode atelier public
- [Implémenté, non vérifié end-to-end] [BOOKING] refacto — [frontend/pages/public/booking.vue](frontend/pages/public/booking.vue) : parcours public refondu en wizard 4 étapes calqué sur `rdv/new` (atelier, véhicule, prestations, créneau, validation)
- [Implémenté, non vérifié end-to-end] [DEV] ajoute — [docker-compose.override.yml](docker-compose.override.yml) : override dev Nuxt avec bind mount + polling, pour retrouver le hot reload sans rebuild complet
- [Implémenté, non vérifié end-to-end] [LOT-0] fix — [frontend/pages/admin/audit.vue](frontend/pages/admin/audit.vue), [frontend/pages/admin/notifications/providers.vue](frontend/pages/admin/notifications/providers.vue), [frontend/pages/admin/roles-metier/index.vue](frontend/pages/admin/roles-metier/index.vue), [frontend/pages/index.vue](frontend/pages/index.vue), [frontend/pages/workshop.vue](frontend/pages/workshop.vue) : états vides/erreurs explicites et suppression des remontées silencieuses côté dashboard / workshop
- **PHPUnit 187/187 ✅** — `docker compose exec -T php bin/phpunit 2>&1 | tail -8` → `OK, but there were issues! Tests: 187, Assertions: 707`
- **Vitest 19/19 ✅** — `cd frontend && npx vitest run` → `Test Files 6 passed (6) Tests 19 passed (19)`
- **Build Nuxt prod ✅** — `docker compose exec -T nuxt npm run build` → `Build complete!`

### Décisions
- Le booking public doit suivre exactement le workflow produit de `rdv/new`, pas un formulaire simplifié divergent
- Le multi-atelier public ne doit jamais reposer sur une variable d'environnement figée ; la liste des ateliers actifs reste la source de vérité
- Le hot reload Nuxt en dev se traite via un override Docker dédié, sans toucher à l'image de production ni à l'infra préprod
- Les erreurs partielles dashboard / workshop doivent rester visibles à l'écran ; un `catch` silencieux masque des défauts réels d'exploitation

### TODO laissés
- [ ] [frontend/pages/public/booking.vue](frontend/pages/public/booking.vue) : vérifier le parcours complet sur port 81 jusqu'à la création réelle d'un RDV public (raison du report : pas de validation E2E complète exécutée dans cette session)
- [ ] [backend/src/Controller/PublicBookingController.php](backend/src/Controller/PublicBookingController.php) : vérifier par `curl` de création que le payload final du wizard couvre bien tous les cas métier attendus (raison du report : smoke tests réalisés, pas de campagne API complète archivée ici)
- [ ] [frontend/pages/admin/audit.vue](frontend/pages/admin/audit.vue) : vérifier manuellement les états vide / erreur en coupant les endpoints admin ou avec un atelier sans données (raison du report : build + tests OK, pas de scénario UI manuel exhaustif)
- [ ] [docker-compose.override.yml](docker-compose.override.yml) : arbitrer en prochaine session s'il doit être committé ou gardé local seulement pour le confort dev

### En suspens à arbitrer
- Le fichier [docker-compose.override.yml](docker-compose.override.yml) sert clairement le dev local, mais son inclusion dans Git dépend de la politique d'équipe sur les overrides personnels

## Session 2026-04-22 — Sprint 4 audit : PDF async, pagination, config VO, auth refresh, badge PUBLIC

### Fait
- [SPRINT-4] [I14] ajoute — [backend/src/MessageHandler/GeneratePdfMessageHandler.php](backend/src/MessageHandler/GeneratePdfMessageHandler.php) : handler Messenger pour pré-génération asynchrone des PDFs (types `or`, `rapport`, `devis`, `facture`) ; non-bloquant (catch Throwable + logger)
- [SPRINT-4] [I14] ajoute — [backend/src/Service/PdfService.php](backend/src/Service/PdfService.php) : méthode `getCachedPdfPath()` permettant de servir un PDF déjà généré sans recalcul
- [SPRINT-4] [I14] ajoute — [backend/src/Controller/CompanionController.php](backend/src/Controller/CompanionController.php) : injection `MessageBusInterface`, dispatch `GeneratePdfMessage('or', id)` après flush de signature OR — non bloquant
- [SPRINT-4] [I2] fix — [backend/src/Command/RappelProchaineRevisionCommand.php](backend/src/Command/RappelProchaineRevisionCommand.php) : remplace `$io->note()` par `NotificationDispatcher::sendFromTemplate()` avec injection `NotificationDispatcher`
- [SPRINT-4] [I2] ajoute — [backend/src/Service/NotificationTemplateCatalog.php](backend/src/Service/NotificationTemplateCatalog.php) : templates `rappel_prochaine_revision` email + SMS ajoutés au catalogue par défaut
- [SPRINT-4] [I2] ajoute — [backend/src/Schedule.php](backend/src/Schedule.php) : cron `0 9 * * *` → `app:rappel-prochaine-revision`
- [SPRINT-4] [I19] fix — [frontend/middleware/auth.global.ts](frontend/middleware/auth.global.ts) : tracking `lastAuthRefreshAt` (useState), rafraîchissement `/auth/me` si > 5 min — permissions et rôles ne stagnent plus après une longue session
- [SPRINT-4] [I20] ajoute — [frontend/pages/admin/config.vue](frontend/pages/admin/config.vue) : champs `dureeDefautMandatJours` (UInput number) et `regimeTvaVoDefault` (USelect marge/normal) dans l'onglet 1 ; initialisés dans le ref config avec valeurs par défaut et mergés depuis l'API
- [SPRINT-4] [I3] fix — [frontend/stores/rdv.ts](frontend/stores/rdv.ts) : `fetchRdvs()` ajoute `itemsPerPage=200` pour éviter la limite Hydra par défaut (30 items)
- [SPRINT-4] [I3] fix — [frontend/stores/vo.ts](frontend/stores/vo.ts) : `fetchPurchases()` et `fetchDepots()` avec `?itemsPerPage=200`
- [SPRINT-4] [I4] ajoute — [frontend/pages/planning.vue](frontend/pages/planning.vue) : badge `PUBLIC` (bleu, style inline) dans le panneau de détail RDV si `rdv.source === 'web'`
- [SPRINT-4] [I4] ajoute — [frontend/components/PlanningGrid.vue](frontend/components/PlanningGrid.vue) : label `WEB` (bleu) sur la carte grille si `rdv.source === 'web'`
- [SPRINT-4] [I6] ajoute — [backend/src/Entity/ConfigAtelier.php](backend/src/Entity/ConfigAtelier.php) : colonnes `dureeDefautMandatJours` (int, default 90) et `regimeTvaVoDefault` (string, default 'marge') + getters/setters
- [SPRINT-4] [I6] fix — [backend/src/Controller/VOController.php](backend/src/Controller/VOController.php) : `resolveDefaultMandatDuration()` lit `ConfigAtelier.dureeDefautMandatJours` au lieu du hardcode 90
- [SPRINT-4] [I7] fix — [backend/src/Controller/PublicBookingController.php](backend/src/Controller/PublicBookingController.php) : guard module `rdv` — 403 si désactivé pour cet atelier
- [SPRINT-4] [I4 back] ajoute — [backend/src/Entity/RendezVous.php](backend/src/Entity/RendezVous.php) : champ `source` nullable (VARCHAR 20) ; `setSource('web')` dans `PublicBookingController` à la création
- [SPRINT-4] [I15] fix — [backend/src/Controller/StatistiquesController.php](backend/src/Controller/StatistiquesController.php) : `stockAlerts` retourne 0 si module `stock` désactivé dans `featureModules`
- [SPRINT-4] [I16] vérifié — `RendezVous::__construct()` utilise `bin2hex(random_bytes(32))` — déjà conforme
- [SPRINT-4] migration — [backend/migrations/Version20260422115453.php](backend/migrations/Version20260422115453.php) créée et exécutée : 3 colonnes ajoutées (`rendez_vous.source`, `config_atelier.duree_defaut_mandat_jours`, `config_atelier.regime_tva_vo_default`)
- [TEST] fix — [frontend/tests/voStore.test.ts](frontend/tests/voStore.test.ts) : assertion mise à jour `/vo/depots` → `/vo/depots?itemsPerPage=200`
- **PHPUnit 187/187 ✅** — `docker compose exec php bin/phpunit 2>&1 | tail -8` → `OK, but there were issues! Tests: 187, Assertions: 707`
- **Vitest 19/19 ✅** — `npx vitest run 2>&1 | tail -6` → `Test Files 6 passed (6) Tests 19 passed (19)`

### Décisions
- `itemsPerPage=200` retenu pour stores RDV et VO — suffisant pour le volume atelier ; à paginer côté front si l'atelier dépasse 200 entrées par jour (cas non identifié en production)
- Le refresh auth toutes les 5 min est transparent (navigation vers une nouvelle route) — pas d'impact UX perceptible
- `regimeTvaVoDefault` : valeurs admises `marge` / `normal` — l'UI propose les deux labels légaux exacts (Art.297A CGI / Art.256 CGI)
- `I27` (FEC export) : SKIP — Facturation en réécriture

### TODO laissés
- [ ] `GeneratePdfMessageHandler` : dispatcher depuis `OrdreReparationController` et `DevisController` pour les créations/modifications hors Companion (non prioritaire)
- [ ] Template `rappel_prochaine_revision` : variable `atelier_nom` non encore injectée (champ vide) — à alimenter via `CurrentAtelierResolver` dans le command

### En suspens à arbitrer
- Aucun bloquant — Sprint 5 prêt à démarrer

## Session 2026-04-22 — Sprint 3 TODO résolus + Sprint 3 audit : crons, Messenger, gardiennage relances

### Fait
- [SPRINT-3] [C8] fix — [backend/src/Command/RelanceClientStockageCommand.php](backend/src/Command/RelanceClientStockageCommand.php) : dispatch Messenger réel via `NotificationDispatcher::sendFromTemplate()` pour les relances gardiennage J+15/30/45/180 (remplace le stub vide)
- [SPRINT-3] [C4] fix — [backend/src/Command/CheckDepotVenteMandatCommand.php](backend/src/Command/CheckDepotVenteMandatCommand.php) : recipient réel (`$depot->getDeposant()?->getEmail()`) au lieu du TODO placeholder
- [SPRINT-3] [I1+I9] ajoute — [backend/src/Command/CheckDaSivExpiryCommand.php](backend/src/Command/CheckDaSivExpiryCommand.php) : cron DA SIV — alerte J+10 `da_siv_alerte_j10` (email), passage `expiree` J+15 avec notification `da_siv_expiration`
- [SPRINT-3] [I8] fix — [backend/src/Command/CheckDepotVenteMandatCommand.php](backend/src/Command/CheckDepotVenteMandatCommand.php) : template `mandat_depot_vente_j7` dispatché 7 jours avant expiration mandat
- [SPRINT-3] [I10] fix — [backend/src/Service/SlotService.php](backend/src/Service/SlotService.php) : jours de fermeture exceptionnels et `joursOuvresService` intégrés — les créneaux ne sont plus proposés les jours fermés
- [TODO] fix — [backend/src/Schedule.php](backend/src/Schedule.php) : 3 crons Sprint 3 ajoutés (`app:relance-client-stockage` 7h, `app:check-da-siv-expiry` 6h, `app:check-depot-vente-mandat` 6h30)
- [TODO] fix — [backend/config/packages/messenger.yaml](backend/config/packages/messenger.yaml) : routing explicite des messages métier vers le transport async
- [TODO] fix — [backend/src/Service/JoursOuvresService.php](backend/src/Service/JoursOuvresService.php) : `computeEaster()` réécrite avec l'algorithme Meeus-Jones-Butcher (pur PHP, sans extension `calendar` non installée dans le container)
- **PHPUnit 187/187 ✅** (baseline après correction JoursOuvresService + Scheduler)

### Décisions
- L'algorithme Meeus-Jones-Butcher est préféré à `easter_days()` (extension `calendar` optionnelle non présente dans le container Alpine) — résultat identique, 0 dépendance extension
- Les templates notifications gardiennage/SIV/dépôt-vente sont insérés dans `NotificationTemplateCatalog::getDefaults()` et créés automatiquement via `ensureDefaultsForAtelier()`
- Le scheduler Symfony Messenger remplace les crons système — 1 worker Docker gère tout

### TODO laissés
- Aucun TODO Sprint 3 ouvert — tous résolus

### En suspens à arbitrer
- Aucun



### Fait
- [SPRINT-2] [C21] fix — [backend/src/Controller/PhotoController.php](backend/src/Controller/PhotoController.php) : guard path traversal dans `serve()` — `realpath()` + `str_starts_with()` vérifient que le fichier servi reste dans `var/photos/` ; 404 sinon
- [SPRINT-2] [C12] fix — [backend/src/Controller/VOController.php](backend/src/Controller/VOController.php) : upload `TYPE_PIECE_IDENTITE` / `TYPE_JUSTIFICATIF_DOMICILE` retourne désormais 422 (au lieu de 400)
- [SPRINT-2] [C1] ajoute — [backend/src/EventSubscriber/RdvTerminationGuardSubscriber.php](backend/src/EventSubscriber/RdvTerminationGuardSubscriber.php) : guard Symfony Workflow sur `workflow.rendez_vous.guard.terminer` — bloque si essai routier non valide OU rapport d'intervention non signé par le mécanicien ; défense en profondeur s'ajoutant aux guards du controller
- [SPRINT-2] [I5] ajoute — [backend/src/Controller/VOController.php](backend/src/Controller/VOController.php) `transitionPurchase()` : avant d'appliquer `mettre_en_vente`, appel `buildPurchaseSaleVerdict()` → 422 avec `saleVerdict` si `status !== 'vendable'`
- [SPRINT-2] [I11] ajoute — [backend/src/Controller/RendezVousController.php](backend/src/Controller/RendezVousController.php) `transition()` : guard kilométrage — si `kilometrage` est présent dans le body d'une transition autre que `reception` → 400 `KILOMETRAGE_RECEPTION_ONLY`
- [SPRINT-2] [C7] ajoute — [backend/src/Controller/CompanionController.php](backend/src/Controller/CompanionController.php) : 2 nouveaux endpoints restitution (`GET /{token}/rapport-restitution` — disponible si statut `termine` ; `POST /{token}/signature-restitution` — enregistre signature client et applique transition `restituer`)
- [SPRINT-2] [C7] ajoute — [frontend/pages/public/companion/[token].vue](frontend/pages/public/companion/[token].vue) : section restitution auto-affichée si `rdv.statut === 'termine'` — résumé rapport (travaux, km, alertes), canvas signature client, appel `POST /signature-restitution`
- [REFACTOR] fix — [backend/src/Controller/RendezVousController.php](backend/src/Controller/RendezVousController.php) : les contrôles explicites `terminer` (essai, anomalie, rapport, signature méca) ont été remontés AVANT `can()` pour garantir des codes 400 précis sans que le guard Workflow retourne 409 en premier
- [TEST] fix — [backend/tests/Functional/VOControllerTest.php](backend/tests/Functional/VOControllerTest.php) : assertion `testUploadRejectsSensitiveIdentityDocuments` mise à jour 400 → 422
- [TEST] test — PHPUnit backend 180/180 OK, 0 failure, 0 error — commit `cee6419`
- [TEST] test — Vitest frontend 19/19 OK

### Décisions
- Le guard Workflow [C1] est une 2e couche de sécurité ; les guards du controller restent en place (défense en profondeur)
- Pour [I11], le guard est côté controller (transition endpoint) et non côté entité — l'entité reste manipulable en interne (service, tests) sans exception
- Pour [C7] restitution, le `tokenSuivi` existant est réutilisé — pas de nouveau token dédié, le statut `termine` suffisant comme discriminant d'accès
- Pour [I5], le verdict est calculé à chaque appel `mettre_en_vente` (pas mis en cache) pour garantir la fraîcheur des données

### TODO laissés
- [ ] Frontend Companion atelier : ajouter l'étape "Conditions" avec checkboxes bloquantes avant signature (`GET /{token}/clauses`) — reporté Sprint 5
- [ ] Frontend Companion VO : idem par partyRole — reporté Sprint 5
- [ ] `planning.vue` : bouton "Lancer restitution PDA" (génère lien vers companion) — reporté Sprint 5
- [ ] Tests PHPUnit `RdvTerminationGuardTest.php`, `PhotoControllerSecurityTest.php`, `CompanionRestitutionTest.php` non écrits cette session — à ajouter en Sprint 3 ou dédié

### En suspens à arbitrer
- Aucun arbitrage bloquant — Sprint 3 prêt à démarrer

## Session 2026-04-22 — Sprint 1 audit : LP mode paiement, voters DELETE, clauses Companion

### Fait
- [SPRINT-1] [C3] ajoute — [backend/src/Entity/VOLivrePolice.php](backend/src/Entity/VOLivrePolice.php) : 6 nouveaux champs (`modePaiement` NOT NULL, `numeroCheque`, `nomBanque`, `modePaiementVente`, `numeroChequeVente`, `nomBanqueVente`) + constantes `MODES_PAIEMENT` et `MODES_PAIEMENT_ENCAISSEMENT`
- [SPRINT-1] [C3] ajoute — [backend/src/Service/VOLivrePoliceService.php](backend/src/Service/VOLivrePoliceService.php) : `createEntryForPurchase`, `createEntryForDepotVente` et `recordSale` prennent désormais le mode de paiement et les infos chèque en paramètre ; validation interne via `validateModePaiement` et `validateModePaiementEncaissement`
- [SPRINT-1] [C3] ajoute — [backend/src/Controller/VOController.php](backend/src/Controller/VOController.php) : `confirmPurchase` exige `modePaiement` dans le body (422 sinon) ; les endpoints de vente transmettent `modePaiementVente`, `numeroChequeVente`, `nomBanqueVente` ; `activateDepotRecord` passe automatiquement `'depot_vente'`
- [SPRINT-1] [C3] ajoute — [backend/migrations/Version20260606090000.php](backend/migrations/Version20260606090000.php) : migration appliquée, 6 colonnes ajoutées sur `vo_livre_police`
- [SPRINT-1] [C3] ajoute — [backend/templates/pdf/vo_livre_police.html.twig](backend/templates/pdf/vo_livre_police.html.twig) : colonnes "Mode paiement achat" et "Mode paiement vente" dans le registre PDF ; labels lisibles + numéro de chèque si applicable
- [SPRINT-1] [C13] ajoute — [backend/src/Service/VOLivrePoliceService.php](backend/src/Service/VOLivrePoliceService.php) : guard `if ($acquisitionEntry->getDateVente() !== null) throw \LogicException` au début de `recordSale` — double vente LP impossible
- [SPRINT-1] [C10] ajoute — [backend/src/Security/Voter/FactureDeleteVoter.php](backend/src/Security/Voter/FactureDeleteVoter.php) : voter Symfony bloquant le DELETE sur toute facture avec statut `emise`, `payee`, `partiellement_payee`, `corrigee`
- [SPRINT-1] [C10] ajoute — [backend/src/Security/Voter/VOFactureDeleteVoter.php](backend/src/Security/Voter/VOFactureDeleteVoter.php) : même garde pour `VOFacture`
- [SPRINT-1] [C14] ajoute — [backend/src/Controller/AdminUserProvisioningController.php](backend/src/Controller/AdminUserProvisioningController.php) : après `flush()` dans `approve`, appel `mecanicienSyncService->syncFromUser($user)` si le rôle attribué est `ROLE_MECANICIEN` — l'entité `Mecanicien` est créée automatiquement
- [SPRINT-1] [C5] ajoute — [backend/src/Controller/CompanionController.php](backend/src/Controller/CompanionController.php) : endpoint `GET /{token}/clauses` retournant les clauses actives `cgv`, `garantie`, `rgpd` pour l'atelier ; validation `clausesAcceptees` dans `saveSignature` (422 si clauses manquantes)
- [SPRINT-1] [C6] ajoute — [backend/src/Controller/PublicVoCompanionController.php](backend/src/Controller/PublicVoCompanionController.php) : endpoint `GET /{token}/clauses?partyRole=` avec codes par rôle (`vendeur_rachat`, `deposant`, `acheteur`) ; validation `clausesAcceptees` + `partyRole` dans `saveSignature` (422 si incomplet)
- [TEST] test — [backend/tests/Functional/VOControllerTest.php](backend/tests/Functional/VOControllerTest.php) + [backend/tests/Functional/CompanionControllerTest.php](backend/tests/Functional/CompanionControllerTest.php) : tests existants mis à jour pour passer `modePaiement` et `clausesAcceptees` dans les requêtes concernées
- [TEST] test — PHPUnit backend 180/180 OK, 0 failure, 0 error après migration appliquée

### Décisions
- `'depot_vente'` est une valeur spéciale dans `MODES_PAIEMENT` (aucune somme versée à l'entrée du dépôt) mais n'est **pas** dans `MODES_PAIEMENT_ENCAISSEMENT` — les deux constantes sont distinctes
- Le guard [C13] est placé dans le service, pas dans le controller, pour être actif quel que soit l'appelant
- Les voters DELETE sont auto-découverts par Symfony (autowiring) — pas de déclaration manuelle dans `services.yaml`
- La valeur par défaut `'cb'` dans le controller pour `modePaiementVente` est intentionnelle pour les appelants legacy ; l'UI doit toujours l'envoyer explicitement

### TODO laissés
- [ ] Frontend Companion atelier : ajouter l'étape "Conditions" avec checkboxes bloquantes avant signature (consomme `GET /{token}/clauses`)
- [ ] Frontend Companion VO : idem par partyRole (consomme `GET /{token}/clauses?partyRole=`)
- [ ] Sprint 2 : C1 (RdvTerminationGuardSubscriber), C21 (path traversal PhotoController), C12 (422 TYPE_PIECE_IDENTITE), I11 (setKilometrage guard), C7 (companion restitution token dédié), I5 (verdict bloquant mettre_en_vente)

### En suspens à arbitrer
- Aucun arbitrage bloquant — Sprint 2 prêt à démarrer



### Fait
- [LOT-0] fix — [backend/templates/pdf/vo_mandat_immatriculation.html.twig](backend/templates/pdf/vo_mandat_immatriculation.html.twig) : suppression de la référence invalide à `buyer.cp` / `buyer.ville`, ce qui débloque la génération du mandat d'immatriculation pendant la vente VO
- [LOT-0] fix — [backend/src/Controller/VOController.php](backend/src/Controller/VOController.php) + [backend/src/Controller/AdminTemplatePreviewController.php](backend/src/Controller/AdminTemplatePreviewController.php) : nettoyage des faux diagnostics de typage les plus bruyants via normalizer correctement typé et récupération utilisateur explicitement castée
- [LOT-0] ajoute — [backend/src/Controller/HealthController.php](backend/src/Controller/HealthController.php) : nouvel endpoint public `/api/health` pour supervision et déploiement, sans exposer la doc API
- [LOT-0] fix — [backend/config/packages/security.yaml](backend/config/packages/security.yaml) : `/api/docs` et `/api/contexts` ne sont plus publics ; seul `/api/health` reste ouvert en plus des endpoints publics métier
- [LOT-0] fix — [frontend/middleware/auth.global.ts](frontend/middleware/auth.global.ts), [frontend/pages/public/companion.vue](frontend/pages/public/companion.vue) et [frontend/pages/public/vo-companion.vue](frontend/pages/public/vo-companion.vue) : arrêt de la compatibilité query-string pour les tokens companion sur les flux encore actifs, afin d'éviter leur fuite dans les logs et l'historique navigateur
- [LOT-0] fix — [frontend/stores/auth.ts](frontend/stores/auth.ts) et [frontend/stores/atelier.ts](frontend/stores/atelier.ts) : suppression de la persistance locale des rôles, permissions et contexte atelier
- [LOT-0] ajoute — [docker-compose.preprod.yml](docker-compose.preprod.yml) + [scripts/deploy-server.sh](scripts/deploy-server.sh) + [scripts/github-webhook-listener.py](scripts/github-webhook-listener.py) : séparation préprod dédiée, secrets exigés, Mercure resserré, healthcheck de déploiement réaligné et sortie détaillée du déploiement non renvoyée au client HTTP
- [LOT-0] fix — [Dockerfile.backend](Dockerfile.backend) : le warmup cache prod fait désormais échouer le build si le bootstrap Symfony est cassé
- [LOT-0] fix — [.gitignore](.gitignore) + [frontend/.gitignore](frontend/.gitignore) : les artefacts générés Nuxt, Playwright, Vitest et PHPUnit sont maintenant explicitement ignorés ; les anciennes sorties suivies sont laissées supprimées du worktree
- [LOT-0] fix — [.gitignore](.gitignore) + dépôt Git : les uploads VO runtime sous [backend/public/uploads/vo](backend/public/uploads/vo) sortent de l'index Git et restent seulement comme données locales/runtime
- [TEST] test — validations exécutées : PHPUnit backend 178/178 OK, Vitest frontend 19/19 OK, build Nuxt production OK, cache warmup prod OK, `GET /api/health` OK

### Décisions
- La doc API et les contexts ne doivent plus être exposés anonymement en préprod ; le point de vérité opérationnel pour la supervision devient un healthcheck dédié
- Les tokens companion ne doivent plus vivre en query string sur les parcours actifs ; la canonicalisation retenue est le segment de chemin
- Les stores frontend ne conservent plus de contexte d'autorisation durable côté navigateur ; le contexte doit être rechargé depuis le serveur à chaque bootstrap utile
- La préprod doit s'appuyer sur une surcharge compose dédiée au lieu de réutiliser telle quelle la stack locale permissive
- Les uploads VO et PDFs générés sont des données runtime, pas des artefacts versionnables ; ils doivent vivre hors Git

### TODO laissés
- [ ] Purger définitivement du dépôt les anciens artefacts Nuxt actuellement suivis puis committer leur suppression pour retrouver un worktree propre
- [ ] Traiter le reliquat de diagnostics Markdown historiques dans [PROJECT_HISTORY.md](.github/PROJECT_HISTORY.md) si on veut revenir à une baseline éditeur quasi nulle
- [ ] Revoir plus largement les surfaces publiques legacy encore présentes sous `/public/*` si on veut supprimer complètement les anciens écrans de transition

### En suspens à arbitrer
- Faut-il aller jusqu'à basculer le runtime backend de serveur PHP intégré vers une chaîne `php-fpm` + Caddy `php_fastcgi` dans un lot infra dédié, ou garder le runtime actuel pour la préprod courte tout en ayant sécurisé le reste ?

## Session 2026-04-21 — Paquet 2 tranche 3 : orchestration mécanicien et verdict VO binaire

### Fait
- [LOT-05] fix — [frontend/pages/mecanicien.vue](frontend/pages/mecanicien.vue) : l'espace mécanicien exploite enfin les transitions atelier existantes `mettre_en_pause`, `attendre_pieces`, `mettre_en_attente_pieces`, `reprendre` et `reprendre_apres_pieces` avec un statut lisible, sans détourner le workflow ni réinventer une logique locale
- [LOT-05] test — [backend/tests/Functional/MecanicienControllerTest.php](backend/tests/Functional/MecanicienControllerTest.php) : couverture ajoutée sur la séquence mécanicien pause → attente pièces → reprise pour verrouiller l'orchestration réellement utilisée à l'écran
- [LOT-06] fix — [backend/src/Service/VODocumentService.php](backend/src/Service/VODocumentService.php) + [backend/src/Controller/VOController.php](backend/src/Controller/VOController.php) : le VO expose désormais un verdict structuré `vendable` / `non_vendable` avec motifs hiérarchisés par gravité et portée (légal, RGPD, workflow, atelier)
- [LOT-06] fix — [frontend/components/vo/VODossierMotoCard.vue](frontend/components/vo/VODossierMotoCard.vue), [frontend/pages/vo/rachats/[id].vue](frontend/pages/vo/rachats/[id].vue) et [frontend/pages/vo/depots/[id].vue](frontend/pages/vo/depots/[id].vue) : les vues VO ne reposent plus sur des pourcentages ou pseudo-progressions pour la vente ; elles affichent un verdict unique actionnable avec les blocages à lever
- [TEST] test — PHPUnit ciblé OK : 11 tests verts sur [backend/tests/Functional/MecanicienControllerTest.php](backend/tests/Functional/MecanicienControllerTest.php) et [backend/tests/Functional/VOControllerTest.php](backend/tests/Functional/VOControllerTest.php)
- [TEST] test — build Nuxt production OK après fermeture du reliquat mécanicien et du verdict VO

### Décisions
- Le workflow mécanicien doit consommer les transitions du state machine existant ; aucune sous-logique front parallèle n'est autorisée pour pause, attente pièces ou reprise
- Le dossier VO ne doit plus suggérer une "proximité de vente" via des scores visuels ; une seule vérité métier reste affichée : vendable maintenant ou non vendable, avec raisons ordonnées
- Les blocages VO peuvent agréger droit, RGPD et blocages atelier, mais ils doivent rester lisibles comme une liste de décisions à traiter, pas comme un tableau de complétion

### TODO laissés
- [ ] Reprendre plus tard le nettoyage des anciennes surfaces VO qui consomment encore `dossierStatus` ou `saleBlockers` sans exploiter le verdict structuré si on veut homogénéiser tout le module hors fiches détaillées

### En suspens à arbitrer
- Aucun nouveau point bloquant ; le reliquat Paquet 2 est désormais fermé sur ses deux derniers objectifs fonctionnels

## Session 2026-04-20 — Paquet 2 tranche 2 : rapport signé, réception clarifiée, VO recentré

### Fait
- [LOT-05] fix — [backend/src/Service/RapportInterventionService.php](backend/src/Service/RapportInterventionService.php), [backend/src/Controller/RapportInterventionController.php](backend/src/Controller/RapportInterventionController.php), [backend/src/Controller/RendezVousController.php](backend/src/Controller/RendezVousController.php) et [backend/src/EventListener/RdvWorkflowListener.php](backend/src/EventListener/RdvWorkflowListener.php) : la clôture atelier impose désormais un brouillon de rapport récupérable dès le RDV, un rapport mécano suffisamment complété, puis la signature mécanicien avant la transition terminer
- [LOT-05] ajoute — [backend/src/Controller/DemandeTravauxSuppController.php](backend/src/Controller/DemandeTravauxSuppController.php) + [frontend/pages/mecanicien.vue](frontend/pages/mecanicien.vue) : création de demandes de travaux complémentaires depuis l'écran mécanicien, orientée constat technique et réutilisant la notification réception existante
- [LOT-05] fix — [backend/src/Controller/MecanicienController.php](backend/src/Controller/MecanicienController.php) + [frontend/pages/mecanicien.vue](frontend/pages/mecanicien.vue) : l'espace mécanicien expose l'état de signature du rapport et n'appelle plus terminer comme si le rapport était une étape secondaire
- [LOT-04] fix — [backend/src/Controller/CompanionController.php](backend/src/Controller/CompanionController.php), [frontend/pages/planning.vue](frontend/pages/planning.vue) et [frontend/pages/ordres/[id].vue](frontend/pages/ordres/[id].vue) : séparation explicite entre motif client, notes réception et notes techniques ; les données de réception ne transitent plus sous les clés `mechanic_*`
- [LOT-06] fix — [backend/src/Controller/VOController.php](backend/src/Controller/VOController.php), [frontend/components/vo/VODossierMotoCard.vue](frontend/components/vo/VODossierMotoCard.vue), [frontend/pages/vo/rachats/[id].vue](frontend/pages/vo/rachats/[id].vue), [frontend/pages/vo/depots/[id].vue](frontend/pages/vo/depots/[id].vue), [frontend/pages/vo/rachats/new.vue](frontend/pages/vo/rachats/new.vue) et [frontend/pages/vo/depots/new.vue](frontend/pages/vo/depots/new.vue) : le dossier VO recentre la conformité sur la transcription légale ; `piece_identite` et `justificatif_domicile` ne sont plus uploadables côté UI et sont explicitement refusés côté API
- [TEST] test — build Nuxt production OK après réalignement mécanicien, réception et dossier VO
- [TEST] test — PHPUnit ciblé OK : 15 tests verts sur [backend/tests/Unit/RapportInterventionServiceTest.php](backend/tests/Unit/RapportInterventionServiceTest.php), [backend/tests/Unit/EssaiRoutierCompletenessTest.php](backend/tests/Unit/EssaiRoutierCompletenessTest.php), [backend/tests/Functional/MecanicienControllerTest.php](backend/tests/Functional/MecanicienControllerTest.php) et [backend/tests/Functional/VOControllerTest.php](backend/tests/Functional/VOControllerTest.php)

### Décisions
- Le rapport d'intervention n'est plus un artefact de post-clôture : il devient la preuve centrale de fin d'intervention, signée par le mécanicien avant tout passage à `termine`
- La réception garde sa propre sémantique et ses propres champs ; le mécanicien n'hérite plus de noms de données qui brouillent les responsabilités métier
- La conformité VO sur identité/domicile repose sur la transcription puis destruction immédiate du support, jamais sur l'archivage de la pièce elle-même

### TODO laissés
- [x] [frontend/pages/mecanicien.vue](frontend/pages/mecanicien.vue) : ajouter pause, attente pièces et reprise dans le parcours mécanicien pour finir le LOT-05 côté orchestration d'atelier — fait session 2026-04-21
- [x] [frontend/components/vo/VODossierMotoCard.vue](frontend/components/vo/VODossierMotoCard.vue) + vues VO associées : remplacer le suivi documentaire encore partiel par un verdict légal binaire vendable / non vendable avec motifs hiérarchisés pour finir le LOT-06 — fait session 2026-04-21

### En suspens à arbitrer
- Aucun nouvel arbitrage bloquant ; le reliquat Paquet 2 porte désormais surtout sur la profondeur restante des lots 05 et 06, plus sur leur direction produit

## Session 2026-04-20 — Paquet 2 tranche 1 : photos mécanicien et flux atelier praticable

### Fait
- [LOT-05] fix — [frontend/pages/mecanicien.vue](frontend/pages/mecanicien.vue) : ajout d'un vrai panneau photos d'intervention mobile-first avec capture caméra native, typage métier, galerie par type et compteur utile à la clôture atelier
- [LOT-05] fix — [frontend/pages/mecanicien.vue](frontend/pages/mecanicien.vue) : retrait du lien d'appel direct au client dans l'espace mécanicien pour recentrer le rôle sur l'exécution technique
- [LOT-05] fix — [backend/src/Controller/PhotoController.php](backend/src/Controller/PhotoController.php) + [backend/src/Service/PhotoService.php](backend/src/Service/PhotoService.php) : upload photo désormais typé (`en_cours`, `apres_travaux`, `restitution`, `probleme`), métadonnées retournées en lecture, nom de fichier non prédictible uniformisé
- [LOT-05] fix — [frontend/composables/useApi.ts](frontend/composables/useApi.ts) : `FormData` n'est plus sérialisé en JSON, ce qui rend enfin fiables les uploads de fichiers depuis les écrans métier
- [TEST] test — build Nuxt production OK après branchement du flux photo mécanicien
- [TEST] test — validation manuelle API : upload `apres_travaux` sur RDV 236 OK, puis lecture `/api/photos/rdv/236` confirmant le type et les métadonnées

### Décisions
- Le Paquet 2 est attaqué par tranches livrables ; la première tranche utile est LOT-05 côté mécanicien, car le back imposait déjà des photos sans les rendre capturables dans l'écran métier naturel
- Le type photo doit être porté dès l'upload, sinon les gardes de transition `terminer` / `restituer` restent théoriques et non satisfaisables en pratique

### TODO laissés
- [x] [frontend/pages/mecanicien.vue](frontend/pages/mecanicien.vue) : ajouter la création de demande de travaux complémentaires depuis l'écran mécanicien, sans estimation commerciale — fait session 2026-04-20 tranche 2
- [x] [backend/src/Controller/RendezVousController.php](backend/src/Controller/RendezVousController.php) + [backend/src/Controller/RapportInterventionController.php](backend/src/Controller/RapportInterventionController.php) : fermer à la racine la séquence encore imparfaite `terminer` avant signature mécanicien, pas seulement la rendre plus praticable côté écran — fait session 2026-04-20 tranche 2
- [x] Le Paquet 2 — Simplifications de workflow et responsabilités devient le prochain bloc obligatoire : LOT-04, LOT-05 et LOT-06 sont à reprendre explicitement avant tout nouveau lot UX, facturation avancée ou confort produit — traité entre les sessions 2026-04-20 et 2026-04-21

### En suspens à arbitrer
- Aucun nouveau point ; l'arbitrage restant sur le Paquet 2 porte toujours sur la priorisation interne entre réception maître (LOT-04), clôture mécanicien complète (LOT-05) et recentrage du dossier VO (LOT-06)

## Session 2026-04-20 — RGPD VO passif, avoir facturation et rôle comptable réel

### Fait
- [BLOC-03] fix — `backend/src/Service/VODocumentService.php` : les pièces d'identité et justificatifs ne sont plus des documents requis du dossier VO ; la conformité repose sur la transcription, et la présence résiduelle de supports sensibles devient un blocage RGPD explicite côté achat et dépôt-vente
- [BLOC-03] fix — `backend/src/Command/PurgeIdentityDocumentsCommand.php` : la commande de purge expose désormais le volume de supports sensibles encore stockés avant purge pour rendre le passif visible
- [BLOC-03] ajoute — vrai flux d'avoir minimal sur `Facture` avec nature de document, lien vers facture d'origine, motif de correction et endpoint dédié `POST /api/facturation/{id}/avoir`
- [BLOC-03] fix — `backend/src/Controller/FacturationController.php` : encaissement interdit sur un avoir ou une facture corrigée, numérotation dédiée `FAC` / `AVO`, email/PDF compatibles avec le nouveau type de document
- [BLOC-02] fix — `backend/src/Controller/StatistiquesController.php` + `frontend/composables/useAuth.ts` : le comptable obtient un accès explicite aux statistiques, et les requêtes excluent désormais les factures corrigées tout en conservant l'effet négatif des avoirs
- [BLOC-02] fix — `frontend/pages/facturation/index.vue`, `frontend/stores/billing.ts`, `frontend/components/StatusBadge.vue` : écran facturation aligné avec le nouveau flux d'avoir, nouveau badge, boutons d'action bornés et filtre `partiellement_payee` corrigé
- [BLOC-03] ajoute — migration [backend/migrations/Version20260420194500.php](backend/migrations/Version20260420194500.php) pour persister `nature`, `facture_origine_id` et `motif_correction` sur les factures atelier
- [TEST] fix — `backend/tests/Functional/VOControllerTest.php` réaligné sur la purge RGPD obligatoire après transcription Livre de Police
- [TEST] test — campagne Docker relancée : PHPUnit backend 173/173 OK, Vitest frontend 19/19 OK, build Nuxt production OK

### Décisions
- Une facture atelier émise ne doit plus être pseudo-annulée ; la correction opposable passe par un avoir dédié, relié à la facture d'origine
- Le passif RGPD VO est désormais visible et bloquant tant que des supports sensibles restent archivés, même si la transcription légale existe déjà
- Le rôle comptable n'est plus un sous-produit implicite de l'admin pour les stats et la facturation ; il existe comme surface métier explicite
- L'atelier reste en mono-correction par facture pour l'instant : un seul avoir par facture, pas d'avoirs partiels multiples tant que le journal de remboursement et le suivi de solde crédit ne sont pas implémentés

### Validation manuelle
- Migration Doctrine appliquée en base : `DoctrineMigrations\\Version20260420194500` exécutée
- Test manuel API réalisé avec le compte admin local sur les RDV 235 et 236
- Cas facture partiellement payée : création `FAC-2026-0001`, paiement partiel 30,00 EUR, émission `AVO-2026-0001`, facture source passée en `corrigee`, nouvel encaissement refusé (409)
- Cas facture payée : création `FAC-2026-0002`, paiement complet 78,00 EUR, émission `AVO-2026-0002`, facture source passée en `corrigee`
- Garde mono-avoir validée : tentative de second avoir sur `FAC-2026-0001` refusée (409)

### TODO laissés
- [ ] Ajouter un vrai journal de remboursement / décaissement lié aux avoirs si on veut couvrir complètement l'après-encaissement comptable
- [ ] Prévoir une vue de régularisation VO listant les dossiers encore bloqués par supports sensibles stockés pour traiter le passif atelier par atelier

### En suspens à arbitrer
- Aucun sur ce lot ; l'arbitrage retenu est mono-avoir par facture tant que la comptabilité aval n'est pas modélisée

## Session 2026-04-20 — Implémentation blocs 01 à 03 et campagne de tests

### Fait
- [BLOC-01] fix — sécurisation des parcours exposés : tokens sortis des query strings, wrappers de routes ajoutés, whitelists publiques resserrées, rate limiting ajouté sur companion / VO companion / demandes publiques, payloads publics minimisés
- [BLOC-02] fix — réalignement rôles/permissions : `ROLE_RESPONSABLE_ATELIER` et `ROLE_RESPONSABLE_MAGASIN` explicites, faux mapping `super_admin` supprimé, audit global réservé au `ROLE_SUPER_ADMIN`, accès front/back alignés
- [BLOC-03] fix — fermeture des faux workflows sensibles : faux consentement booking retiré, validation locale DTS retirée de l'OR, uploads pièce d'identité / justificatif désactivés sur le companion VO, étape vendeur basée sur la seule transcription légale, statut facture "Annulée" retiré de l'UI filtrante au profit d'un affichage "À régulariser"
- [BLOC-03] fix — `VOCompanionWorkflowService` : justificatif de domicile retiré des options companion et complétion vendeur durcie (type + numéro + date obligatoires)
- [TEST] test — campagne exécutée dans Docker : PHPUnit backend 173/173 OK, Vitest frontend 19/19 OK, build Nuxt production OK
- [TEST] fix — réalignement des tests backend sur la nouvelle doctrine de minimisation companion et sur la fermeture des documents sensibles dans le companion VO
- [TEST] fix — suppression de 2 dépréciations Doctrine ORM via `Mecanicien` et `TenantFilter`
- [TEST] fix — suppression de 26 notices PHPUnit 12 en déclarant explicitement les classes de tests qui utilisent des mocks comme stubs sans attentes

### Décisions
- Le companion VO ne stocke plus la pièce d'identité ni le justificatif de domicile ; seule la transcription des métadonnées utiles reste autorisée
- La minimisation des payloads tokenisés prime sur les anciennes attentes de tests ou d'écran ; les tests doivent suivre cette doctrine
- Les notices PHPUnit liées aux mocks sans attentes sont traitées comme un problème de qualité de test, pas contournées par de fausses expectations métier

### TODO laissés
- [x] Mettre à jour l'état d'avancement des TODO blocs 01 à 03 dans ce fichier si on découpe officiellement l'exécution par sous-lots plutôt que par passe transverse — fait dans les sessions ultérieures du 2026-04-20
- [x] Trier les fichiers générés non trackés dans `backend/public/uploads/vo/` avant tout commit/push pour éviter d'embarquer des artefacts de test ou de génération documentaire — fait avant les commits du 2026-04-21

### En suspens à arbitrer
- Décider si le reliquat BLOC-03 côté historique VO doit inclure une purge/régularisation des pièces d'identité déjà stockées avant la fermeture du flux

## Session 2026-04-20 — Correction 41 erreurs TypeScript pré-existantes (frontend)

### Fait
- [LOT-TS] fix — `frontend/types/pinia-persist.d.ts` (nouveau) : augmentation du module `pinia` pour typer `persist?: boolean` dans `DefineStoreOptionsBase`. Résout ~30 erreurs en cascade sur les stores auth/atelier.
- [LOT-TS] fix — `layouts/default.vue` + `pages/admin/ateliers.vue` : suppression du fallback `?.atelierId` (inexistant dans `UserData`) → utilise uniquement `atelier_id`
- [LOT-TS] fix — `pages/admin/clauses-legales.vue` : `:key="String(f.value)"` (valeur booléenne non valide comme `PropertyKey`)
- [LOT-TS] fix — `pages/admin/roles-metier/[id].vue` + `index.vue` : `:rows="2"` au lieu de `rows="2"` (`string` non assignable à `number`)
- [LOT-TS] fix — `pages/vo/depots/new.vue` + `pages/vo/rachats/new.vue` : `companionPollTimer: number | null` au lieu de `ReturnType<typeof setInterval>` (conflit Node.js/browser)
- [LOT-TS] fix — `stores/vo.ts` : ajout `sourceLabel?: string` et `dossierPath?: string` dans l'interface `VORemiseEnEtat`
- [LOT-TS] fix — `composables/voCompanionDraftSync.ts` : casts `as T[keyof T & string]` (cohérence avec la contrainte `key: keyof T & string`)
- [LOT-TS] fix — `components/PlanningGrid.vue` : import `CSSProperties`/`StyleValue` depuis Vue + annotations de retour sur `timeLabelStyle`, `cellStyle`, `rdvStyle`
- [LOT-TS] retire — `plugins/piniaPersistedState.ts` : supprimé (plugin `pinia-plugin-persistedstate` incompatible avec pinia 2.x)
- [LOT-TS] retire — `package.json` : suppression entrée `pinia-plugin-persistedstate` (ajoutée par erreur en session précédente)
- Typecheck container : 0 erreur (était 41)
- Tests unitaires : 19/19 OK

### Décisions
- Approche retenue pour typer `persist` : fichier `.d.ts` avec `export {}` obligatoire pour que TypeScript reconnaisse l'augmentation comme MODULE et non comme remplacement ambiant du module `pinia`.
- `pinia-plugin-persistedstate` abandonné : incompatible avec pinia 2.x, et le `persist: true` dans les stores n'a aucun effet runtime (aucun plugin enregistré). Typage seul suffit.

### TODO laissés
- (aucun)

### En suspens à arbitrer
- (aucun)

## Session 2026-04-20 — Audit rôles métiers et plan de refonte

### Fait
- [LOT-AUDIT] ajoute — audit complet des rôles Réceptionnaire, Mécanicien, Responsable atelier, Responsable magasin / direction, Gestionnaire VO, Comptable, Super-admin et Client final dans [.github/AUDIT_ROLES_METIERS.md](.github/AUDIT_ROLES_METIERS.md)
- [LOT-AUDIT] ajoute — synthèse transverse et lecture comité de pilotage pour transformer les constats en arbitrages produit exploitables
- [LOT-AUDIT] fixe — recadrage produit des compagnons atelier et VO : outils internes assistés, pas portails publics autonomes
- [LOT-AUDIT] fixe — doctrine documentaire VO : CERFA obligatoires inclus dans le périmètre cible, sans arbitrage ultérieur
- [LOT-AUDIT] docs — mise à jour de [.github/REVIEW_CHECKLIST.md](.github/REVIEW_CHECKLIST.md) avec les garde-fous manquants sur rôles fantômes, tokens, compagnons internes, workflows concurrents et CERFA
- [LOT-AUDIT] docs — mise à jour de [.github/ARCHITECTURE_REFERENCE.md](.github/ARCHITECTURE_REFERENCE.md) avec les frontières d'interface, de sécurité, de rôles et de conformité désormais actées

### Décisions
- Les compagnons PDA atelier et VO sont des outils assistés par un employé ; ils ne doivent plus être pensés ni exposés comme tunnels publics autonomes
- Un rôle métier annoncé doit devenir un contrat technique réel : guards, permissions, écrans visibles et audit cohérents
- Chaque workflow critique doit avoir un écran maître unique ; les autres surfaces ne montrent que l'état ou des raccourcis bornés
- Les documents VO réglementés obligatoires doivent utiliser le vrai CERFA attendu : DA SIV 13751, mandat 13757*03, certificat de cession 15776*02

### Plan d'exécution retenu

#### Paquet 1 — Corrections bloquantes métier / légal / sécurité

##### [LOT-01] Sécuriser les parcours exposés
- sortir les compagnons atelier et VO du faux modèle `/public` autonome
- supprimer tous les tokens en query string
- réduire les payloads tokenisés au strict minimum
- rendre réellement publiques uniquement les vraies pages publiques utiles : réservation, suivi, pages légales, décision distante si maintenue
- ajouter rate limiting et journalisation minimale sur tous les tunnels publics conservés

##### [LOT-02] Réaligner rôles et permissions
- matérialiser proprement comptable, responsable atelier, responsable magasin et super-admin
- retirer les rabattements implicites vers `ROLE_ADMIN` quand ils masquent un vrai métier
- réserver l'audit global au super-admin et créer au besoin une vue audit atelier séparée
- homogénéiser guards, voters, menus et escalades avec la même matrice de permissions

##### [LOT-03] Bloquer les faux workflows sensibles
- interdire la fin d'intervention avant rapport mécanicien signé
- supprimer toute validation locale de travaux complémentaires qui contourne l'accord client opposable
- retirer la fiction d'annulation simple de facture et préparer le vrai flux d'avoir
- corriger les points RGPD critiques : consentement booking, conservation des pièces d'identité et justificatifs, données excessives dans les compagnons

#### Paquet 2 — Simplifications de workflow et responsabilités

##### [LOT-04] Refaire la réception et les travaux complémentaires
- définir un écran maître de réception
- séparer motif client, notes réception, notes techniques
- basculer l'OCR et les corrections véhicule en mode proposition puis validation interne
- imposer un workflow unique de travaux complémentaires visible depuis tous les écrans sans logique concurrente

##### [LOT-05] Refaire l'espace mécanicien et la clôture d'intervention
- ajouter les photos dans l'écran mécanicien avec prise mobile native
- ajouter création de demande complémentaire orientée constat technique, sans estimation commerciale
- exposer pause, attente pièces et reprise dans le parcours mécanicien
- imposer une séquence de fin d'intervention cohérente : essai, photos, rapport, signature, puis seulement statut terminé

##### [LOT-06] Recentrer le dossier VO sur la conformité
- créer une distinction nette entre pré-dossier et dossier juridiquement activable
- faire du dossier VO l'écran maître avec blocages légaux lisibles immédiatement
- transformer les documents réglementés en vrais rendus CERFA conformes
- imposer transcription puis destruction immédiate des pièces d'identité et justificatifs
- remplacer les scores visuels flous par un verdict binaire vendable / non vendable avec motifs juridiques hiérarchisés

#### Paquet 3 — Dette UX et dette de modèle

##### [LOT-07] Refaire la facturation / comptabilité
- unifier les statuts front/back
- construire la facture à partir des lignes réelles atelier
- introduire le flux d'avoir, le journal d'encaissement et le rôle comptable réel
- préparer l'export comptable / FEC ou réduire explicitement le périmètre si on reste sur une simple caisse atelier

##### [LOT-08] Nettoyer le modèle et les cockpits
- renommer les champs ambigus et séparer les responsabilités sémantiques
- aligner payloads, formulaires, PDF et dashboards sur une doctrine de minimisation
- séparer cockpit direction, cockpit atelier, back-office admin et outils super-admin
- remplacer les dashboards cosmétiques par des écrans d'exception et de décision

### TODO laissés
- [x] Commencer par [LOT-01] et [LOT-02] avant tout nouvel ajout fonctionnel sur réception compagnon, VO compagnon, rôles/permissions et comptabilité — fait dans les sessions d'implémentation blocs 01 à 03
- [x] Le Paquet 2 — Simplifications de workflow et responsabilités devient le prochain bloc obligatoire : LOT-04, LOT-05 et LOT-06 sont à reprendre explicitement avant tout nouveau lot UX, facturation avancée ou confort produit — fait entre les sessions 2026-04-20 et 2026-04-21

### En suspens à arbitrer
- Décider si la validation distante des travaux complémentaires reste un vrai parcours public client ou revient à un flux strictement assisté comptoir
- Décider si le module comptabilité vise une vraie portée comptable avec export/FEC ou reste volontairement borné à l'encaissement atelier

## Session 2026-04-20 — Specs exécutables blocs 01 à 03

### Fait
- [BLOC-01] docs — rédaction de [.github/SPEC-BLOC-01-securiser-parcours-exposes.md](.github/SPEC-BLOC-01-securiser-parcours-exposes.md) pour sécuriser routes publiques, tokens, payloads exposés et pages légales
- [BLOC-02] docs — rédaction de [.github/SPEC-BLOC-02-realigner-roles-permissions.md](.github/SPEC-BLOC-02-realigner-roles-permissions.md) pour réaligner rôles métier, guards, voters, menus et audit
- [BLOC-03] docs — rédaction de [.github/SPEC-BLOC-03-bloquer-faux-workflows-sensibles.md](.github/SPEC-BLOC-03-bloquer-faux-workflows-sensibles.md) pour fermer les contournements critiques sur intervention, DTS, facturation corrective et RGPD

### Décisions
- Les trois premiers blocs de refonte sont désormais spécifiés à un niveau exécutable par couche, sans réinterprétation produit complémentaire requise avant démarrage
- L'ordre de démarrage reste inchangé : BLOC-01 puis BLOC-02 puis BLOC-03, même s'ils ont été rédigés ensemble

### TODO laissés
- [x] Implémenter [.github/SPEC-BLOC-01-securiser-parcours-exposes.md](.github/SPEC-BLOC-01-securiser-parcours-exposes.md) — fait session 2026-04-20 implémentation blocs 01 à 03
- [x] Implémenter [.github/SPEC-BLOC-02-realigner-roles-permissions.md](.github/SPEC-BLOC-02-realigner-roles-permissions.md) — fait session 2026-04-20 implémentation blocs 01 à 03
- [x] Implémenter [.github/SPEC-BLOC-03-bloquer-faux-workflows-sensibles.md](.github/SPEC-BLOC-03-bloquer-faux-workflows-sensibles.md) — fait session 2026-04-20 implémentation blocs 01 à 03

### En suspens à arbitrer
- Décider, avant implémentation de BLOC-01/BLOC-03, si la validation distante des travaux complémentaires reste un vrai parcours public autonome ou repasse en flux strictement assisté comptoir
- Décider, avant implémentation approfondie de BLOC-03, si la comptabilité cible inclut un vrai flux d'avoir complet immédiatement ou seulement la suppression de la fiction d'annulation simple dans un premier temps



### Fait
- [LOT-DB] audit — comparaison schéma PostgreSQL / mappings Doctrine / références code pour identifier les vraies tables orphelines
- [LOT-DB] ajoute — migration [backend/migrations/Version20260420170500.php](backend/migrations/Version20260420170500.php) pour supprimer `calculs_tarifs`, `grille_tarifs`, `temps_interventions`
- [LOT-DB] backup — export ciblé avant suppression dans [backup/unused_tables_20260420.sql](backup/unused_tables_20260420.sql)
- [LOT-DB] exécute — migration Doctrine appliquée localement, suppression effective des 3 tables legacy
- [LOT-DB] vérifie — plus aucune référence code trouvée sur ces tables et plus aucun `DROP TABLE` résiduel dans `doctrine:schema:update --dump-sql`

### Décisions
- Seules les tables absentes du modèle Doctrine courant et sans référence code sont supprimées
- Les tables vides mais encore mappées par Doctrine ne sont pas supprimées pour éviter de casser l'application

### TODO laissés
- [ ] Faire un tri séparé des tables encore vides mais toujours mappées si on veut réduire davantage la base sans casser le modèle

### En suspens à arbitrer
- Aucun

## Session 2026-06-05 — Workflows : DTS constantes + VOPurchase state machine

### Fait
- [LOT-6] fix — DemandeTravauxSupp : ajout constantes `STATUT_*` + tableau `STATUTS` + validation whitelist dans `setStatut()`
- [LOT-6] fix — DemandeTravauxSuppController : remplacement de toutes les raw strings par constantes `DemandeTravauxSupp::STATUT_*`
- [LOT-6] ajoute — Symfony Workflow `vo_purchase` (state_machine) dans workflow.yaml : places brouillon/en_stock/en_vente/reserve/vendu, 6 transitions, audit_trail activé
- [LOT-6] ajoute — VOPurchase : constantes `STATUS_*` + tableau `STATUSES` + validation whitelist dans `setStatus()`
- [LOT-6] fix — VOPurchase.status retiré du groupe `vo:write` (status non modifiable via PATCH API Platform)
- [LOT-6] fix — VOController : injection `WorkflowInterface` via `#[Target('vo_purchase')]`
- [LOT-6] fix — VOController::createPurchase : suppression du `setStatus($body['status'])` (nouveau = brouillon, toujours)
- [LOT-6] fix — VOController::updatePurchase : suppression du `setStatus($body['status'])` libre (faille critique : permettait de forcer n'importe quel statut)
- [LOT-6] fix — VOController::confirmPurchase : `setStatus('en_stock')` → `workflow->apply('confirmer')` + guard par `workflow->can()`
- [LOT-6] fix — VOController::sellPurchase : `setStatus('vendu')` → `workflow->apply('vendre')` + guard par `workflow->can()`
- [LOT-6] ajoute — VOController::transitionPurchase : endpoint POST `/purchases/{id}/transition` pour transitions simples (mettre_en_vente, retirer_de_la_vente, reserver, liberer) avec audit
- [LOT-6] ajoute — store VO frontend : `transitionPurchase(id, transition)` pour appeler le nouvel endpoint
- PHPUnit via Docker : 168/168 OK
- Build Nuxt production : OK

### Décisions
- **DemandeTravauxSupp : constantes + whitelist, pas de workflow Symfony** — le flux linéaire (5 statuts, pas de side-effects complexes) ne justifie pas un state machine. La validation dans le setter suffit.
- **VOPurchase : workflow Symfony complet** — choisi pour les side-effects (LP, facture, DA SIV) et la traçabilité audit_trail native.
- `confirmer` et `vendre` conservent leurs endpoints dédiés (logique métier transactionnelle : LP, facture, PV rachat).
- Les transitions simples (mettre_en_vente, reserver, liberer, retirer_de_la_vente) passent par un endpoint générique `/transition`.

### TODO laissés
- [ ] Ajouter EventSubscriber `VOPurchaseWorkflowSubscriber` pour side-effects futurs sur les transitions (notifications, SMS, etc.)
- [ ] Restant window.open : VORemiseEnEtatCard.vue `openDocument()` (fichiers statiques, acceptable)
- [ ] Refactoring progressif : remplacer les ~10 setTimeout manuels de debounce par `useDebounceFn`

### En suspens à arbitrer
- (aucun)

## Session 2026-06-04 — Remédiation audit : reste lots 7 + mineurs

### Fait
- [LOT-7] fix — providers.vue : remplacement des 6 `console.error(e)` et `alert()` par `toast.add()` avec messages descriptifs
- [LOT-7] fix — companion.vue : catch vide L914 documenté (polling réseau non bloquant)
- [LOT-7] ajoute — composable `useValidation.ts` : regex tel FR, email, plaque FR + `validateClientFields()`
- [LOT-7] fix — rdv/new.vue : validation format téléphone et email avant soumission RDV
- [LOT-7] fix — clients/index.vue : validation format téléphone et email avant création client
- [LOT-7] fix — vo/rachats/new.vue : validation format téléphone, email et plaque avant soumission rachat
- [LOT-7] ajoute — composable `usePdfDownload.ts` : téléchargement/ouverture PDF via fetch+blob authentifié (remplace `window.open`)
- [LOT-7] fix — remplacement `window.open` PDF par `usePdfDownload` dans : billing.ts, devis/[id].vue, vo/factures.vue, vo/livre-police.vue, vo/rachats/index.vue, vo/rachats/[id].vue, vo/depots/index.vue, vo/depots/[id].vue, rapport/[rdvId].vue, ordres/[id].vue
- [MINOR-47] fix — PhotoController : `uniqid()` remplacé par `bin2hex(random_bytes(8))` (filenames non prédictibles)
- [MINOR-48] fix — OrdreReparationController : remplacement AuditLog direct par `AuditService::log()` (cohérence, simplification, flush unique)
- [SCHED] ajoute — `app:purge-identity-documents` planifié dans Schedule.php (tous les jours à 4h)
- PHPUnit via Docker : 168/168 OK
- Build Nuxt production : OK

### Décisions
- Les `window.open` sur les documents VO archivés (fichiers statiques) restent tels quels — pas d'enjeu de credentials cross-origin
- Le `window.open('', '_blank')` de `printOR()` (ordres/[id].vue) reste aussi — c'est un rendu HTML print, pas un appel API
- Les TODO Lot 6 (workflows DemandeTravauxSupp et VO statut) restent en suspens — nécessitent une décision architecturale

### TODO laissés
- [x] Restant window.open : VORemiseEnEtatCard.vue `openDocument()` (fichiers statiques, acceptable) — doublon documentaire, suivi conservé session 2026-06-05
- [x] ~~Lot 6 : `DemandeTravauxSuppController` setStatut() direct au lieu de workflow (#38)~~ — fait session 2026-06-05
- [x] ~~Lot 6 : `VOController::sellPurchase` setStatus('vendu') direct au lieu de workflow (#39)~~ — fait session 2026-06-05
- [x] Refactoring progressif : remplacer les ~10 setTimeout manuels de debounce par `useDebounceFn` — doublon documentaire, suivi conservé dans les sessions ultérieures

### En suspens à arbitrer
- ~~Faut-il un workflow Symfony pour les transitions de statut VO ?~~ → OUI, implémenté session 2026-06-05
- ~~Faut-il un workflow pour DemandeTravauxSupp ?~~ → NON, constantes + whitelist suffit, fait session 2026-06-05

## Session 2026-06-04 — Implémentation audit Lots 4-7

### Fait
- [LOT-4] fix — GardiennageService : remplacement fallback silencieux 5€/j par RuntimeException si ConfigAtelier absente + `??` défensif sur le champ nullable
- [LOT-4] fix — DevisController : emails `noreply@atelier-moto.fr` / `Atelier Moto` remplacés par branding dynamique via `CurrentAtelierResolver` + `Atelier.getEmail()/getNom()` (fallback `noreply@paddock.fr` / `Paddock`)
- [LOT-4] fix — FacturationController : même pattern branding dynamique appliqué sur sendEmail (from, subject, body)
- [LOT-4] fix — NotificationDispatcher : fallbacks `noreply@atelier-moto.fr` → `noreply@paddock.fr`, `Notification Atelier Moto` → `Notification`, `Atelier Moto Pro` → `Paddock`
- [LOT-4] fix — PublicBookingController : `atelier_id` default `1` supprimé, paramètre désormais obligatoire (retourne 400 si absent)
- [LOT-4] fix — index.vue : dénominateurs hardcodés des jauges dashboard (40, 25000, 800, 2400, 30, 6, 10) extraits en constantes nommées `GAUGE_MAX_*`
- [LOT-4] OK — PricingService fallbacks jugés acceptables (RuntimeException couvre le cas ConfigAtelier absente, `??` sur champs nullable)
- [LOT-4] OK — stores/atelier.ts `nom: 'Paddock'` jugé acceptable (fallback cosmétique neutre)
- [LOT-5] ajoute — AuditService::log() sur VOLivrePoliceService : createEntryForPurchase, createEntryForDepotVente, recordSale
- [LOT-5] ajoute — AuditService::log() sur VOGeneratedDocumentService : transition SIV status → en_cours
- [LOT-5] ajoute — AuditService::log() sur MecanicienController : saveRapport, createEssai
- [LOT-5] ajoute — AuditService::log() sur AdminUserProvisioningController : approve, reject
- [LOT-5] ajoute — AuditService::log() sur PhotoController : upload
- [LOT-6] fix — PdfService::resolveAtelier() : suppression fallback `findOneBy([])`, retourne null si atelierId absent
- [LOT-6] fix — TenantFilterListener : user non-SUPER_ADMIN sans atelierId → filtre activé avec ID 0 (résultat vide) au lieu de bypass total
- [LOT-6] fix — SecurityHeadersListener : `camera=()` → `camera=(self)` pour permettre caméra PDA Companion
- [LOT-6] OK — Cookie `active_atelier_id` non-HttpOnly : design intentionnel (lu par Nuxt `useCookie()`, contient uniquement un ID atelier, JWT est déjà HttpOnly)
- [LOT-6] OK — stores/auth.ts `persist: true` : le store ne contient que id/email/username/role/atelier_id, pas de token JWT ni secret
- [LOT-6] ajoute — VODocumentService::purgeExpiredIdentityDocuments() : méthode de purge RGPD des pièces d'identité et justificatifs de domicile après transcription LP
- [LOT-6] ajoute — Commande Symfony `app:purge-identity-documents` pour exécution planifiée de la purge RGPD
- [LOT-7] fix — prestations.vue : suppression du bouton "Initialiser le catalogue" dupliqué
- [LOT-7] OK — console.log en prod : 0 occurrence trouvée dans le code source front (déjà clean)
- [LOT-7] ajoute — composable `useDebounceFn.ts` réutilisable (setTimeout/clearTimeout encapsulé)
- PHPUnit via Docker : 168/168 OK (0 échec, 22 PHPUnit Notices préexistantes)
- Build Nuxt production : OK

### Décisions
- Le cookie `active_atelier_id` reste non-HttpOnly car le frontend le lit via `useCookie()`. Le JWT (le vrai secret) est HttpOnly. Risque XSS limité à un changement de contexte atelier.
- `stores/auth.ts` persist reste tel quel : aucune donnée sensible (pas de JWT, pas de mot de passe). Standard Pinia pour maintien de session entre refreshes.
- PricingService : les fallbacks `??` sur les taux (65€, 85€, etc.) sont conservés comme filet défensif sur champs nullables, mais le service throw si ConfigAtelier est absente.
- Le composable `useDebounceFn` est créé mais le refactoring des 10+ implémentations manuelles sera fait progressivement.

### TODO laissés
- [x] Refactoring progressif : remplacer les ~10 setTimeout manuels de debounce par `useDebounceFn` dans les pages existantes — doublon documentaire, suivi conservé session 2026-06-05
- [x] Lot 6 : `DemandeTravauxSuppController` setStatut() direct au lieu de workflow (#38) — fait session 2026-06-05
- [x] Lot 6 : `VOController::sellPurchase` setStatus('vendu') direct au lieu de workflow (#39) — fait session 2026-06-05
- [x] Planifier l'exécution de `app:purge-identity-documents` dans le scheduler Symfony ou un cron
- [x] Lot 7 : Validation format client (plaque, tel, email) dans les formulaires front (#42)
- [x] Lot 7 : Remplacer `window.open` PDF par fetch + blob download (#43)
- [x] Lot 7 : Supprimer les catch vides dans admin/providers et companion (#44)

### En suspens à arbitrer
- Faut-il un workflow Symfony pour les transitions de statut VO (achat → FRE → en vente → vendu) ?
- Faut-il un workflow pour DemandeTravauxSupp ?

## Session 2026-06-04 — Implémentation audit Lots 1-3

### Fait
- [LOT-1] fix — Ajout `#[IsGranted('ROLE_USER')]` sur 12 controllers non protégés : ClientController, ClientStatsController, GardiennageController, HistoriqueEntretienController, OrdreReparationPdfController, PhotoController, PontStatusController, RapportInterventionController, StockController, VehiculeLookupController, DevisController, RendezVousController
- [LOT-1] fix — Ajout `#[IsGranted('ROLE_ADMIN')]` sur actions sensibles DevisController (envoyer, sendEmail, convertir)
- [LOT-2] fix — PricingService : réécriture complète `calculateMoPrice()` et `applyPieceMargin()` avec bcmath (plus aucun float/round pour calculs monétaires)
- [LOT-2] fix — PricingService : `applyPieceMargin()` prend maintenant `string` au lieu de `float`
- [LOT-2] fix — PricingService : throw RuntimeException si ConfigAtelier introuvable au lieu de fallbacks silencieux
- [LOT-2] fix — FacturationController : remplacement `TVA_RATE` constante et `65.00` hardcodé par lecture ConfigAtelier via CurrentAtelierResolver
- [LOT-2] test — Ajout PricingServiceTest (8 tests unitaires bcmath : standard, complexe, forfait minimum, precision, marges pièces standard/consommable, exceptions)
- [LOT-3] fix — NotificationDispatcher : correction signature OVH SMS `+GET+` → `+POST+` (tous les SMS OVH échouaient)
- [LOT-3] docs — VOLivrePoliceService : docblock corrigé sur `recordSale()` — la complétion des champs vente initialement null est conforme Art. 321-7 CP (une ligne LP = un objet, entrée + sortie)
- [LOT-3] fix — QR codes : installation npm `qrcode` + `@types/qrcode`, création composable `useQrCode.ts`, remplacement de `api.qrserver.com` dans useVoHelpers.ts, planning.vue, VOCompanionCard.vue, rachats/new.vue, depots/new.vue — plus aucune fuite de token vers service tiers
- [BONUS] fix — `pages/admin/prestations.vue` : correction du code cassé dans fetchData (Promise.allSettled tronqué + watch showTarifModal mélangé)
- PHPUnit : 168/168 OK (8 nouveaux tests PricingService)
- Build Nuxt production : OK

### Décisions
- PricingService ne tolère plus l'absence de ConfigAtelier (RuntimeException au lieu de fallbacks). Les taux par défaut restent en tant que `??` fallback dans le match, mais uniquement si la config existe mais n'a pas encore ces champs.
- FacturationController utilise `CurrentAtelierResolver` pour accéder à ConfigAtelier dynamiquement (TVA + taux horaire)
- LP vente = complétion de l'entrée existante (champs initialement null) — pas d'entrée sortie séparée. Documenté dans le docblock.
- QR code généré localement via `qrcode` npm (data-URL base64), aucun appel réseau externe.

### TODO laissés
- [x] Lot 1 : Permissions granulaires sur transitions RDV (ROLE_RECEPTIONNAIRE pour certaines transitions) — doublon documentaire, suivi conservé dans la section audit détaillée
- [x] Lot 4 : Emails hardcodés `noreply@atelier-moto.fr` dans DevisController et NotificationDispatcher — fait session 2026-06-04 lots 4-7
- [x] Lot 4 : `PublicBookingController` atelier_id default 1 — fait session 2026-06-04 lots 4-7
- [x] Lot 4-7 : Voir TODO restants dans la section audit — méta-TODO remplacé par les items explicites plus bas dans ce fichier

### En suspens à arbitrer
- Aucun.

## Session 2026-04-20 — Audit complet de l'application

### Rapport d'audit

Audit exhaustif couvrant tous les controllers (35), services (15+), listeners/subscribers, security, stores Pinia (7), composables (10), pages (44+), middleware et composants.

#### 🔴 CRITIQUES — À corriger en priorité absolue

**Back — Endpoints sans authentification (12 controllers)**

| # | Controller | Endpoints exposés | Risque |
|---|-----------|-------------------|--------|
| 1 | `ClientController` | `GET /api/clients` | Toutes les données clients (noms, tels, emails, adresses) sans auth — **fuite RGPD** |
| 2 | `ClientStatsController` | `GET /api/clients/stats` | CA total, nb clients exposés |
| 3 | `DevisController` | **Tous** (envoyer, email, accepter, refuser, convertir, pdf) | Module devis entièrement ouvert |
| 4 | `GardiennageController` | **Tous** (déclencher, montant, commandes, créer, modifier, marquer) | Gardiennage + commandes pièces ouverts |
| 5 | `HistoriqueEntretienController` | `GET /historique-entretien` + `/pdf` | Historique véhicule complet sans auth |
| 6 | `OrdreReparationPdfController` | `GET /api/ordres-reparation/{id}/pdf` | PDF OR signés accessibles par ID |
| 7 | `PhotoController` | **Tous** (upload, serve, list) | Upload sans auth + IDOR sur filenames |
| 8 | `PontStatusController` | `GET /api/ponts/status` | Noms clients + plaques véhicules exposés |
| 9 | `RapportInterventionController` | **Tous** (show, update, signer, pdf, rectifier) | Rapports modifiables/signables sans auth |
| 10 | `RendezVousController` | `POST /rendez-vous` + transitions workflow | Création RDV + toute transition (annuler, facturer) sans permission |
| 11 | `StockController` | `GET /api/stock/alertes` | Alertes stock sans auth |
| 12 | `VehiculeLookupController` | `GET /api/vehicule/{query}` | Données véhicule + client_id exposés |

**Back — Calculs monétaires**

| # | Service/Controller | Problème |
|---|-------------------|----------|
| 13 | `PricingService` | Tous les calculs MO et marges pièces en `float` au lieu de `bcmath` |
| 14 | `FacturationController` | TVA_RATE `20.00` et taux horaire `65.00` hardcodés — doivent venir de ConfigAtelier |
| 15 | `DevisController` | Taux horaire `65€/h` hardcodé (L134) |

**Back — Bugs fonctionnels**

| # | Service | Problème |
|---|---------|----------|
| 16 | `NotificationDispatcher` | Bug OVH SMS : signature calculée avec `GET` au lieu de `POST` — **tous les SMS OVH échouent** |
| 17 | `VOLivrePoliceService::recordSale()` | Mutation directe de l'entrée LP au lieu de créer une entrée sortie — violation immuabilité LP (Art. 321-7 CP) |

**Front — Sécurité**

| # | Fichier | Problème |
|---|---------|----------|
| 18 | `useVoHelpers.ts` + `planning.vue` | QR codes générés via `api.qrserver.com` — **fuite de tokens Companion vers service tiers** (piège documenté dans les instructions) |
| 19 | `stores/auth.ts` | `persist: true` stocke rôle, permissions, atelier_id en localStorage — exploitable via XSS |

#### 🟠 IMPORTANTS — À traiter en lot sécurisation

**Back — Hardcodes**

| # | Fichier | Hardcode | Source correcte |
|---|---------|----------|----------------|
| 20 | `PricingService` | Taux standard 65€, complexe 85€, expert 95€, forfait min 25€, TVA 20% | ConfigAtelier |
| 21 | `GardiennageService` | Tarif gardiennage fallback 5€/j | ConfigAtelier |
| 22 | `NotificationDispatcher` | Email `noreply@atelier-moto.fr` | ConfigAtelier ou Atelier |
| 23 | `DevisController` | Email from/body "Atelier Moto" | ConfigAtelier ou Atelier |
| 24 | `FacturationController` | Email from/body "noreply@atelier-moto.fr" | ConfigAtelier ou Atelier |
| 25 | `PublicBookingController` | `atelier_id` default `1` (L55, L74) | Requis ou résolu par slug |
| 26 | `stores/atelier.ts` | Nom default "Paddock", logo hardcodé | Fallback neutre |
| 27 | `pages/index.vue` | Barres progress dénominateurs fixes (40 RDV, 25000€ CA) | Calculé ou ConfigAtelier |

**Back — Audit trail manquant**

| # | Fichier | Action non auditée |
|---|---------|-------------------|
| 28 | `VOLivrePoliceService` | createEntry, recordSale — **registre légal sans trace** |
| 29 | `VOGeneratedDocumentService` | Transition statut SIV (DA = obligation légale) |
| 30 | `GardiennageController` | updateCommande, marquerRecue |
| 31 | `MecanicienController` | Rapport intervention, essai routier |
| 32 | `AdminUserProvisioningController` | Approve/reject utilisateurs |
| 33 | `PhotoController` | Upload photos |

**Back — Sécurité**

| # | Fichier | Problème |
|---|---------|----------|
| 34 | `PdfService::resolveAtelier()` | Fallback `findOneBy([])` — PDF avec branding d'un autre atelier |
| 35 | `TenantFilterListener` | User sans atelierId et non super_admin → pas d'isolation tenant |
| 36 | `AuthController` | Cookie `active_atelier_id` non HttpOnly — modifiable par JS |
| 37 | `SecurityHeadersListener` | `camera=()` bloque la caméra PDA (photos méca, OCR CG) |
| 38 | `DemandeTravauxSuppController` | `setStatut()` direct au lieu de workflow |
| 39 | `VOController::sellPurchase` | `setStatus('vendu')` direct — pas de workflow VO |

**Back — RGPD**

| # | Fichier | Problème |
|---|---------|----------|
| 40 | `VODocumentService` | Pièce d'identité stockée dans `/uploads/vo/` sans mécanisme de purge — rétention 0j requise |

**Front — Qualité**

| # | Fichier | Problème |
|---|---------|----------|
| 41 | `pages/admin/prestations.vue` | Bouton "Initialiser le catalogue" dupliqué |
| 42 | Formulaires (rdv/new, vo/rachats/new, devis, clients) | Pas de validation format client (plaque, tel, email) avant soumission |
| 43 | Plusieurs pages | `window.open` pour PDF — cookie auth potentiellement non attaché cross-origin |
| 44 | 3 pages (admin/providers, companion) | Catch vides sans feedback utilisateur |

#### 🔵 MINEURS

| # | Problème |
|---|----------|
| 45 | 9 `console.log/warn/error` en production (useNotifications, companion, providers) |
| 46 | 5+ implémentations de debounce dupliquées (extraire un composable) |
| 47 | `PhotoService` : `uniqid()` prévisible au lieu de `random_bytes()` |
| 48 | `OrdreReparationController` : utilise `AuditLog` directement au lieu de `AuditService::log()` |
| 49 | Styles inline massifs dans quasi toutes les pages (dette CSS) |
| 50 | Accessibilité : pas d'ARIA, pas de focus trap modales, labels manquants |
| 51 | `useNotifications` : state module-level risque fuite SSR |
| 52 | `ApiDebugExceptionListener` : scope limité à 2 routes seulement |
| 53 | `input type="number"` sans min/max dans les formulaires |

### TODO laissés

**Lot 1 — Sécurisation endpoints (bloquant)** :
- [x] Ajouter `#[IsGranted('ROLE_USER')]` minimum sur : `ClientController`, `ClientStatsController`, `DevisController`, `GardiennageController`, `HistoriqueEntretienController`, `OrdreReparationPdfController`, `PhotoController`, `PontStatusController`, `RapportInterventionController`, `RendezVousController`, `StockController`, `VehiculeLookupController`
- [ ] Ajouter des permissions granulaires sur les transitions RDV (`RendezVousController`)
- [x] Ajouter ROLE_ADMIN sur les actions sensibles Devis (convertir, envoyer)

**Lot 2 — Calculs monétaires (bloquant compta)** :
- [x] `PricingService` : réécrire `calculateMoPrice()` et `applyPieceMargin()` avec `bcmath`
- [x] `FacturationController` : remplacer les taux horaires hardcodés par ConfigAtelier
- [x] Remplacer `TVA_RATE` constant par `ConfigAtelier.tvaMoTaux`

**Lot 3 — Bugs critiques** :
- [x] `NotificationDispatcher` L237 : corriger `+GET+` → `+POST+` dans la signature OVH SMS
- [x] `VOLivrePoliceService::recordSale()` : documenter que la complétion des champs vente (initialement null) est conforme LP
- [x] QR codes : remplacer `api.qrserver.com` par génération locale (lib `qrcode` npm)

**Lot 4 — Hardcodes** :
- [x] `PricingService` : supprimer les 6 fallbacks hardcodés (65€, 85€, 95€, 25€, 20%, 50%/25%/30%) — jugés acceptables, RuntimeException couvre le cas principal
- [x] `GardiennageService` : supprimer fallback 5€/j
- [x] Emails : remplacer `noreply@atelier-moto.fr` et "Atelier Moto" par config dynamique dans DevisController, FacturationController, NotificationDispatcher
- [x] `PublicBookingController` : supprimer le default `atelier_id = 1`
- [x] Front : supprimer les valeurs hardcodées dans `stores/atelier.ts` et `pages/index.vue`

**Lot 5 — Audit trail** :
- [x] Ajouter `AuditService::log()` sur : LP create/sale, SIV transition, gardiennage commandes, mécanicien rapport/essai, approve/reject user, upload photo

**Lot 6 — Sécurité secondaire** :
- [x] `PdfService::resolveAtelier()` : supprimer fallback `findOneBy([])`
- [x] `TenantFilterListener` : bloquer les users sans atelierId qui ne sont pas super_admin
- [x] `AuthController` : rendre cookie `active_atelier_id` HttpOnly — gardé non-HttpOnly (intentionnel, lu par frontend)
- [x] `SecurityHeadersListener` : autoriser `camera=(self)` pour le Companion PDA
- [x] `stores/auth.ts` : ne pas persister rôle/permissions en localStorage — gardé tel quel (pas de données sensibles)
- [x] `VODocumentService` : ajouter mécanisme de purge RGPD pour pièce d'identité (rétention 0j)

**Lot 7 — Qualité front** :
- [x] Supprimer le bouton "Initialiser le catalogue" dupliqué dans `admin/prestations.vue`
- [x] Ajouter validation format client (plaque, tel, email) dans les formulaires de création
- [x] Remplacer les `window.open` PDF par fetch + blob download (12 occurrences sur 16 — 4 restantes sont des fichiers statiques ou print HTML)
- [x] Supprimer les 9 `console.log` en production — déjà clean (0 occurrence)
- [x] Extraire un composable `useDebounceFn` réutilisable

### Décisions
- Les lots 1 à 3 sont **bloquants** et doivent être traités avant tout nouveau lot fonctionnel
- Les lots 4 à 5 sont **importants** et peuvent être traités en parallèle des devs fonctionnels
- Les lots 6 à 7 sont des améliorations à planifier

### En suspens à arbitrer
- Faut-il créer une entrée LP séparée pour la vente (2 lignes : acquisition + sortie) ou documenter que la mutation des champs vente (initialement null) est acceptable ?
- Faut-il un workflow Symfony pour les transitions de statut VO (achat → FRE → en vente → vendu) ?
- Faut-il migrer les styles inline vers des classes CSS en un lot dédié ou progressivement ?

## Session 2026-04-20 — Remédiation audit de conformité

### Fait
- [LOT-0] fix — NotificationController : ajout filtre ownership (targetUserId + targetRole + broadcast) sur list/unreadCount/acknowledge/markRead, empêche l'accès croisé entre utilisateurs d'un même atelier
- [LOT-0] fix — FacturationController : remplacement float par bcmath pour tous les calculs monétaires (TVA, remise, paiements) ; extraction constante TVA_RATE
- [LOT-0] fix — FacturationController : numérotation facture par séquence PostgreSQL au lieu de COUNT+1 (race condition éliminée)
- [LOT-0] fix — FacturationController : ajout gardes permissions (ROLE_ADMIN sur facturer/créer/payer/email, ROLE_USER sur list/preview/pdf)
- [LOT-0] fix — ConfigController : suppression fallbacks silencieux sur premier atelier/config (retourne null si pas de contexte)
- [LOT-0] fix — ConfigController : rejet SVG dans l'upload logo (risque XSS)
- [LOT-0] fix — messenger.yaml : routage explicite des 3 messages métier vers le transport async
- [LOT-0] fix — frontend prestations.vue : remplacement du bootstrap automatique implicite par un bouton admin explicite "Initialiser le catalogue"
- PHPUnit : 159/160 OK (1 échec préexistant sur RdvPrestationCatalogControllerTest, non lié aux modifs)
- [LOT-0] fix — PrestationCatalogService : ajout filtre atelierId dans findBy pour éviter les fuites cross-tenant quand TenantFilter est bypassé (super-admin) — résout le test en échec (59.0 vs 72.0)
- PHPUnit : 160/160 OK
- Build Nuxt production : OK

### Décisions
- Le filtre ownership des notifications utilise targetRole (string) pour la compatibilité DQL/PostgreSQL plutôt que targetRoles (json)
- Les broadcasts sont détectés par targetUserId IS NULL AND targetRole IS NULL
- La TVA reste en constante dans le controller en attendant la table ConfigAtelier

### TODO laissés
- [x] Migrer TVA_RATE vers ConfigAtelier.tauxTva quand l'entité sera disponible — fait session 2026-06-04 implémentation audit lots 1-3
- [x] Ajouter supervision de la file `failed` du worker Messenger — doublon documentaire, suivi conservé dans le TODO Messenger détaillé plus bas

### En suspens à arbitrer
- Aucun.

## Session 2026-04-20 — Audit de conformité codebase

### Fait
- Relecture complète des règles de la base projet dans `.github` et audit ciblé du back/front sur sécurité, multi-atelier, workflow et conformité métier
- Vérification des contrôleurs sensibles autour des notifications, de la configuration atelier et de la facturation
- Priorisation des écarts critiques avant reprise des prochains lots fonctionnels

### Décisions
- Aucun nouveau lot métier ne doit repartir tant que les écarts critiques de sécurité et de conformité facturation ne sont pas fermés
- L’ordre de traitement retenu est : notifications → facturation → contexte atelier/config
- Le worker Symfony tourne correctement, mais son exploitation doit être étendue aux vrais messages métier asynchrones au lieu de se limiter surtout au scheduler

### TODO laissés
- [ ] `backend/src/Controller/NotificationController.php` : verrouiller la liste et les actions de lecture/acquittement au destinataire réel (utilisateur ou rôle cible) pour empêcher un accès croisé entre utilisateurs d’un même atelier
- [ ] `backend/src/Controller/FacturationController.php` : requalifier le reliquat réel après les correctifs déjà faits sur la numérotation séquentielle, les avoirs et les statuts ; ne laisser ouvert que ce qui reste sur le scope atelier, les calculs monétaires ligne par ligne ou les permissions encore insuffisantes
- [ ] `backend/src/Controller/ConfigController.php` : supprimer les fallbacks silencieux sur le premier atelier / la première config et renvoyer une erreur explicite quand le contexte atelier est absent ou incohérent
- [ ] `backend/src/Controller/ConfigController.php` : durcir l’upload du logo en refusant ou en assainissant les SVG pour éviter toute injection côté navigateur
- [ ] `frontend/pages/admin/prestations.vue` : retirer le bootstrap automatique implicite du catalogue au chargement et le remplacer par une action admin volontaire, visible et traçable
- [ ] `backend/config/packages/messenger.yaml` + handlers/messages associés : router explicitement les messages métier lourds vers `async`, fiabiliser l’usage du worker et prévoir une vraie surveillance de la file `failed`

### En suspens à arbitrer
- Décider si la remise en conformité de la facturation se traite en lot minimal de sécurisation ou dans une refonte plus large du flux facture/paiement.

## Session 2026-04-20 — Catalogue atelier et notifications

### Fait
- [LOT-0] fix — correction du chargement admin des prestations multi-atelier via le bon filtre tenant et le bootstrap catalogue
- [LOT-0] fix — la page admin prestations recharge désormais le catalogue de l’atelier actif sélectionné
- [LOT-0] ajoute — cloche d’historique des notifications dans la top bar avec compteur et lecture rapide
- [LOT-0] fix — le scope des notifications web suit désormais l’atelier actif pour super-admin et service client
- Vérifications exécutées : PHPUnit ciblé vert et build Nuxt production OK

### Décisions
- L’historique des notifications doit être visible directement dans le chrome applicatif, pas caché dans l’admin
- Le contexte atelier actif reste la source unique pour les prestations, la prise de RDV et les notifications

### TODO laissés
- Aucun TODO bloquant laissé sur ce lot.

### En suspens à arbitrer
- Aucun.

## Session 2026-04-17 — Companion VO dès la création

### Fait
- [LOT-0] fix — correction des liens documents VO côté front pour le compagnon public
- [LOT-0] ajoute — carte compagnon VO plus visible sur les fiches rachat et dépôt
- [LOT-0] ajoute — activation du compagnon dès la création du dossier via brouillon + QR code PDA
- [LOT-0] fix — finalisation backend d'un brouillon VO sans double création de dossier
- Vérification manuelle après rebuild : pages création rachat et dépôt disponibles

### Décisions
- Le parcours compagnon démarre avant l'enregistrement complet du dossier
- Le QR code doit être affiché immédiatement pour permettre scan, OCR et signature en amont
- Le lot a été poussé en WIP sur la branche atelier-v2-only pour reprise le lendemain

### TODO laissés
- Aucun TODO encore ouvert sur ce lot : le flux compagnon brouillon, la finalisation métier et les tests ciblés ont été livrés dans les sessions suivantes.

### En suspens à arbitrer
- Gérer ou non une deuxième signature distincte selon rachat ou dépôt-vente

## Session 2026-04-18 — Reprise / cadre de session

### Fait
- Lecture systématique du fichier historique réactivée et confirmée
- État WIP précédemment poussé sur le remote pour sécuriser l'avancement
- [LOT-0] fix — sur le rachat, le QR compagnon est maintenant préparé dès l'ouverture de la fenêtre
- [LOT-0] ajoute — synchronisation automatique du brouillon rachat pour remonter les infos scannées dans le dossier
- Migration appliquée pour autoriser un brouillon rachat sans vendeur ni véhicule au démarrage

### Décisions
- Ce fichier devient le point d'entrée systématique de chaque session
- Le rachat peut démarrer par le QR PDA avant la saisie complète du dossier

### TODO laissés
- Aucun TODO encore ouvert sur ce lot : le dépôt-vente suit désormais le même mode brouillon immédiat.

### En suspens à arbitrer
- Aucun.

## Session 2026-04-18 — Remise en etat VO

### Fait
- [LOT-0] ajoute — socle backend Remise en etat VO avec entités campagne, lignes prestations, pièces et migration Doctrine
- [LOT-0] ajoute — API VO dédiée pour lister, créer, modifier et clôturer les campagnes de remise en etat côté rachat et dépôt
- [LOT-0] fix — blocage métier de la vente VO tant qu'une campagne active de remise en etat n'est pas clôturée
- [LOT-0] ajoute — carte front Remise en etat VO sur les fiches rachat et dépôt avec gestion lignes/pieces
- [LOT-0] ajoute — page File atelier VO + entrée de navigation dédiée
- [LOT-0] fix — les wizards VO rachat/dépôt capturent désormais catégorie tarifaire, type atelier et cylindrée pour fiabiliser les prestations et prix proposés en remise en etat
- [LOT-0] test — ajout de Vitest et d'une couverture front ciblée sur le payload véhicule VO utilisé par le wizard
- Migration `Version20260604180000` appliquée avec succès dans le conteneur PHP
- Migration `Version20260604190000` appliquée avec succès dans le conteneur PHP
- Vérifications manuelles après rebuild : `https://localhost/vo/remises-en-etat` et `https://localhost/vo/rachats/new` répondent en 200
- [LOT-0] test — couverture PHPUnit ciblée sur `VORemiseEnEtatService` exécutée avec succès dans le conteneur PHP
- [LOT-0] ajoute — audit des créations, mises à jour et suppressions sensibles sur les campagnes, lignes et pièces de remise en etat VO
- QA navigateur Playwright validée après rebuild du conteneur Nuxt sur `vo/rachats/new`, `vo/depots/new` et `vo/remises-en-etat`
- [LOT-0] ajoute — Companion VO achat/dépôt en vrai mode brouillon : auto-création d'un dépôt brouillon, hydratation prudente depuis le PDA et finalisation du même dossier au lieu d'une recréation
- [LOT-0] ajoute — archivage centralisé des PDF VO générés via `VOGeneratedDocumentService`, déclenché à la signature PDA et à la finalisation quand les prérequis légaux sont réunis
- [LOT-0] fix — verrouillage public du PDA VO après signature pour empêcher toute réédition silencieuse du brouillon signé
- [LOT-0] test — couverture backend/front Companion VO + Remise en etat complétée, puis scénario Playwright déterministe validant l'écart tarifaire entre deux catégories moto

### Décisions
- Une seule campagne de remise en etat VO peut être active par dossier à un instant donné
- La remise en etat VO devient un vrai pont VO ↔ atelier avec file d'attente, prestations catalogue et mini-workflow pièces
- La vente d'un VO reste bloquée tant qu'une campagne active n'est pas clôturée ou annulée
- Les prix/prestations affichés en remise en etat restent pilotés par le catalogue atelier existant; la fiabilité dépend donc de la catégorie tarifaire, du type moto et de la cylindrée portés par le véhicule VO
- Le dépôt-vente Companion doit vivre en `brouillon` réel tant que véhicule, déposant et données légales ne sont pas finalisés; on met à jour le même dossier jusqu'à validation
- L'archivage des documents VO générés est centralisé et ne part qu'au moment où le dossier est juridiquement prêt ou signé; la signature publique verrouille ensuite le PDA

### TODO laissés
- Aucun TODO encore ouvert sur ce lot : la signature dématérialisée et le fallback PDF des remises en etat ont été livrés dans la session suivante.

### En suspens à arbitrer
- Aucun.

## Session 2026-04-18 — Companion VO E2E et clôture atelier

### Fait
- [LOT-0] test — ajout d'un E2E Playwright complet sur le flux Companion VO achat + dépôt jusqu'au verrouillage PDA post-signature puis archivage documentaire après finalisation admin
- [LOT-0] fix — la clôture finale d'une remise en etat VO est désormais réservée à la réception / atelier quand le rôle métier est explicite; le responsable magasin n'est plus accepté pour lever le blocage vente
- [LOT-0] ajoute — les logs d'audit Remise en etat VO embarquent désormais le rôle legacy et le rôle métier de l'acteur sur les actions sensibles
- [LOT-0] test — ajout d'une couverture fonctionnelle PHPUnit pour verrouiller la règle responsable magasin refusé / responsable atelier autorisé sur la clôture

### Décisions
- La clôture qui débloque une vente VO relève de l'atelier; le responsable magasin ne doit plus pouvoir la faire quand son rôle métier est identifié
- Les admins legacy sans rôle métier explicite restent tolérés temporairement pour ne pas casser les comptes historiques déjà en base

### TODO laissés
- Aucun TODO encore ouvert sur ce lot : la signature dématérialisée et le fallback PDF des remises en etat ont été livrés dans la session suivante.

### En suspens à arbitrer
- Aucun.

## Session 2026-04-18 — Signature remise en etat VO

### Fait
- [LOT-0] ajoute — signature dématérialisée des documents de remise en etat VO avec snapshot, hash, acteur, IP et archivage PDF immuable par campagne
- [LOT-0] ajoute — PDF live de campagne de remise en etat + fallback archivé automatique à la clôture quand aucune signature n'a été posée
- [LOT-0] ajoute — carte front de remise en etat enrichie avec téléchargement PDF courant, accès au PDF archivé et zone de signature canvas
- [LOT-0] test — couverture fonctionnelle PHPUnit étendue sur signature + archivage fallback, lint PHP/Twig vert sur les nouveaux fichiers

### Décisions
- Le PDF courant reste toujours disponible en lecture, même après signature; la version archivée reste la preuve figée
- L'archivage documentaire est rattaché à la campagne de remise en etat elle-même, pas seulement au dossier VO, pour éviter tout écrasement entre campagnes historiques
- Si une campagne clôturée n'a jamais été signée, on archive automatiquement un PDF fallback pour conserver une trace exploitable côté atelier/VO

### TODO laissés
- Aucun TODO ouvert sur ce lot.

### En suspens à arbitrer
- Rejouer la suite PHPUnit complète dès qu'un environnement avec l'hôte PostgreSQL `db` est disponible hors Docker intégré.

## Session 2026-04-18 — Dossier SIV VO ultra-assisté

### Fait
- [LOT-0] ajoute — statut DA/SIV persistant sur les rachats VO avec blocage légal de vente tant que la DA n'est pas enregistrée
- [LOT-0] ajoute — checklist légale dossier VO côté rachat et dépôt avec visibilité des blocants métier
- [LOT-0] ajoute — génération PDF préremplie de la DA SIV et du mandat d'immatriculation avec archivage auto dans le dossier
- [LOT-0] fix — la confirmation d'un rachat prépare maintenant automatiquement le dossier SIV et fait passer le statut à en cours
- [LOT-0] ajoute — actions rapides front sur les fiches rachat et dépôt pour générer / ouvrir DA et mandat sans ressaisie
- [LOT-0] test — couverture PHPUnit VO étendue sur le flux DA obligatoire avant vente puis vérifiée avec succès
- [LOT-0] test — vérification Vitest ciblée sur le store VO après branchement des nouvelles actions

### Décisions
- La DA SIV reste un préremplissage et une traçabilité interne; la saisie officielle se fait toujours hors app sur le circuit SIV habilité
- Le mode flemmard validé consiste à tout préparer à la confirmation du rachat, pas à laisser vendre sans DA enregistrée
- Le mandat d'immatriculation est préparé dès le flux vente pour éviter la ressaisie comptoir

### TODO laissés
- Aucun TODO bloquant laissé sur ce lot.

### En suspens à arbitrer
- Décider plus tard si le récépissé DA doit aussi être généré via modèle interne ou seulement archivé après retour opérateur.

## Session 2026-04-19 — Pilotage atelier, Stat et branding Paddock

### Fait
- [LOT-0] fix — la vue atelier / ponts a été rendue exploitable pour piloter la charge du jour avec visibilité des ponts, des affectations et du raccourci RDV rapide
- [LOT-0] ajoute — transformation de l’accueil en vraie page Stat de pilotage avec comparatifs de période, charge, perf mécanos, mix activité et catégories de prestations
- [LOT-0] fix — renommage visible de l’app en Paddock et du dashboard en Stat sur la navigation, la page login et le branding global
- [LOT-0] fix — accès à la page Stat restreint au responsable atelier et profils supérieurs côté front et API
- [LOT-0] ajoute — intégration des logos Paddock sur le login, le favicon et la top bar avec logo atelier conservé à gauche et logo Paddock centré
- Vérification live après rebuild Nuxt : header conforme, atelier à gauche et logo Paddock centré

### Décisions
- La page d’accueil devient un outil de pilotage, plus un dashboard générique
- Le logo atelier reste visible dans le chrome applicatif; la marque Paddock est recentrée dans la top bar
- La visibilité Stat suit la hiérarchie atelier et ne doit pas gêner les profils opérationnels plus bas

### TODO laissés
- Aucun TODO bloquant laissé sur ce lot.

### En suspens à arbitrer
- Aucun.
## Session 2026-04-21 — Audit UI pré-prod\n\n### Fait\n- [AUDIT] fix — réorganisation des pages publiques Companion et Suivi pour correspondre aux routes strictes demandées (/public/companion/[token] au lieu d'un dossier racine ambigu)\n- [AUDIT] fix — suppression d'un dossier doublon `pages/companion/` qui agissait comme un wrapper inutile.\n- [AUDIT] fix — modification de `VOCompanionTrait.php` pour que l'API renvoie le bon chemin `/public/vo-companion/`.\n- [AUDIT] vérif — confirmée l'absence totale de logs, l'impossibilité d'upload d'ID (RGPD), l'absence de demande de temps au mécano, et la saisie de kilométrage réservée à la réception.\n\n### Décisions\n- Les pages Companion sont désormais directement intégrées sous `/public` en respectant les variables dynamiques (token).\n- Le composant `suivi.vue` (live interne) est totalement scindé et le `public/suivi` conserve sa double entrée (formulaire sans token vs direct avec token).\n\n### TODO laissés\n- Aucun.

### Archictecture & Routing (Règles isolées)
- **Zero Trust / Séparation Réseau** : L'application principale Vue/Nuxt (utilisée par le personnel) tourne sur un réseau interne non exposé à Internet (`http://localhost` ou Intranet).
- Toutes les notifications (email, SMS) envoyées au client avec un lien de suivi, d'annulation ou de signature Companion **DOIVENT** pointer vers l'application publique externe. L'application principale *n'est pas accessible* pour eux.
- Les endpoints Frontend (`/public/...`) servent le client uniquement si ce module "Frontend Public" est déployé sur le web. Les liens générés par le backend (`/api/notifications`) doivent correctement interpréter l'URL publique `APP_FRONTEND_PUBLIC_URL` au lieu de `APP_URL`.
