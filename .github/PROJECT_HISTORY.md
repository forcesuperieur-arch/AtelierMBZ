<!-- markdownlint-disable MD022 MD024 MD032 -->

# Historique projet AtelierMBZ

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
- [ ] Refactoring progressif : remplacer les ~10 setTimeout manuels de debounce par `useDebounceFn` dans les pages existantes
- [ ] Lot 6 : `DemandeTravauxSuppController` setStatut() direct au lieu de workflow (#38)
- [ ] Lot 6 : `VOController::sellPurchase` setStatus('vendu') direct au lieu de workflow (#39)
- [ ] Planifier l'exécution de `app:purge-identity-documents` dans le scheduler Symfony ou un cron
- [ ] Lot 7 : Validation format client (plaque, tel, email) dans les formulaires front (#42)
- [ ] Lot 7 : Remplacer `window.open` PDF par fetch + blob download (#43)
- [ ] Lot 7 : Supprimer les catch vides dans admin/providers et companion (#44)

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
- [ ] Lot 1 : Permissions granulaires sur transitions RDV (ROLE_RECEPTIONNAIRE pour certaines transitions)
- [ ] Lot 4 : Emails hardcodés `noreply@atelier-moto.fr` dans DevisController et NotificationDispatcher
- [ ] Lot 4 : `PublicBookingController` atelier_id default 1
- [ ] Lot 4-7 : Voir TODO restants dans la section audit

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
- [ ] Ajouter validation format client (plaque, tel, email) dans les formulaires de création
- [ ] Remplacer les `window.open` PDF par fetch + blob download
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
- [ ] Migrer TVA_RATE vers ConfigAtelier.tauxTva quand l'entité sera disponible
- [ ] Ajouter supervision de la file `failed` du worker Messenger

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
- [ ] `backend/src/Controller/FacturationController.php` : remplacer la numérotation par comptage, les calculs en float et les changements de statut directs par un flux conforme (permissions explicites, scope atelier, calcul monétaire fiable, statut maîtrisé)
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
