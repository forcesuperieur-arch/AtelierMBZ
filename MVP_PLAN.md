# PLAN D'ACTION MVP — Sans facturation, front client séparé

> Date : 2026-06-10
> Branche de départ : `feat/refonte-document-unique-or`
> Objectif : un MVP exploitable en atelier — prise de RDV publique → planning → intervention mécanicien → OR signé/PDF → restitution → suivi dans l'espace client. **Pas de facturation ni paiement.**

---

## Périmètre MVP

### Inclus
- Booking public (port 81) + suivi tokenisé
- Planning / RDV (workflow 15 états)
- Espace mécanicien (checkup, photos, signature, OR)
- Document unique OR : snapshot PDF gelé + hash (déjà sur la branche)
- Espace client séparé (`client-frontend/`) : login, mes RDV, mes motos, historique, profil
- Notifications P0 : confirmation RDV, rappel J-1, travaux terminés
- Clauses légales / RGPD complètes

### Exclus (post-MVP)
- Facturation, paiements, lignes facture (module désactivé, code conservé)
- Stock / pièces / fournisseurs
- Module VO (déjà `false` par défaut)
- Paiement en ligne, signature électronique de devis

---

## Phase 0 — Stabiliser la base (0,5 jour)

Le dépôt a un gros volume de modifications non commitées (entités supprimées : Fournisseur, Prestation, CommandeFournisseur… + controllers + config).

- [ ] Faire l'inventaire `git status` et committer la refonte OR par lots cohérents (entités / controllers / front)
- [ ] Vérifier que les migrations Doctrine correspondent aux entités supprimées (`php bin/console doctrine:schema:validate`)
- [ ] Lancer la suite Playwright (62 tests) et figer un état de référence vert
- [ ] Taguer ce point de départ : `git tag pre-mvp`

**Critère de sortie : branche propre, tests verts, tag posé.**

---

## Phase 1 — Désactiver la facturation proprement (1 jour) ✅ FAIT (2026-06-10)

La facturation est déjà un module désactivable : `facturation: true` dans `DEFAULT_FEATURE_MODULES` (`frontend/stores/atelier.ts:3-21`), stocké dans `Atelier.configJson` (`backend/src/Entity/Atelier.php:74-121`), filtré par `hasSection()` (`frontend/composables/useAuth.ts:68-78`).

- [ ] Passer `facturation: false` (et décider pour `devis` — recommandé : off aussi pour un MVP cohérent) dans le `configJson` de l'atelier + dans les defaults du store
- [ ] Vérifier que la sidebar et les routes `/facturation`, `/devis` disparaissent (gérées par `SidebarLink.vue` + `layouts/default.vue`)
- [ ] Ajouter une garde backend : `FacturationController`, `DevisController`, `RendezVousFacturationCompatController` retournent 403/404 si le module est off (aujourd'hui seul le front masque)
- [ ] **Point critique — Analytics** : `AnalyticsSyncService.php:78-92` lit `Facture` pour le CA. Garder le service mais rendre les métriques CA tolérantes à l'absence de facture (CA = 0 / null, pas d'exception). Idem `ClientStatsController` (CA par client)
- [ ] Masquer les widgets CA du dashboard quand `facturation` est off
- [ ] Vérifier le détail RDV : ne plus afficher le bloc facture (`Facture` a un `ManyToOne` vers `RendezVous`)

**Critère de sortie : app interne complète sans aucune trace de facturation, dashboard fonctionnel, aucun 500.**

### Réalisé (notes)
- Modules `facturation` + `devis` désactivés : defaults front (`stores/atelier.ts`), defaults backend (`ConfigAtelier`), et les 13 ateliers en base
- Garde backend `FeatureModuleGuardSubscriber` : 404 sur ~20 routes facturation/devis (controllers custom + API Platform) quand le module est off
- Analytics déjà tolérantes (agrégats SQL `COALESCE`) — vérifié en live, aucun changement nécessaire
- UI masquée : cartes CA/panier moyen, mix rentabilité, rentabilité, CA par prestation, prévisions CA, « Factures sur période » (synthèse), CA par mécano/segment, métriques CA de l'exploration, transitions `facturer`/`payer` du planning
- Bonus corrigés : pattern firewall `^/api/client` qui avalait les routes staff `/api/clients/*` ; appels `getAtelierId()` inexistant dans Facturation/DevisController ; `PHP_CLI_SERVER_WORKERS=8` (php -S mono-thread saturé par les E2E) ; projet `setup` Playwright qui régénère l'état d'auth admin

### Dette de tests connue (3 × `test.fixme`, à réécrire)
- `business-flows` « Public booking: can submit » + `lots-124` « booking lock message » → la page booking est devenue un wizard multi-étapes avec sélecteur d'atelier (**phase 3**)
- `notification-providers` modals/templates ×2 → la page providers a été refondue (**phase 4**)

---

## Phase 2 — Fiabiliser l'espace client séparé (2-3 jours) ✅ FAIT (2026-06-10)

Constat actuel (`client-frontend/`) : pages propres (13 pages, un seul login), mais **le token vit dans `useState` → perdu au refresh** (`client-frontend/stores/auth.ts:4`). Le backend pose pourtant déjà un cookie HttpOnly `client_access_token` (`backend/src/Controller/ClientAuthController.php:71-73`) et expose `/api/client/refresh` et `/api/client/me`.

### 2.1 Auth fiable (cœur de la phase)
- [ ] Au boot de l'app (plugin Nuxt), appeler `GET /api/client/me` : si le cookie HttpOnly est valide → réhydrater le store. Plus aucune dépendance au `useState` volatile
- [ ] Sur réponse 401, tenter `POST /api/client/refresh` puis rejouer la requête (wrapper fetch dans le store/composable)
- [ ] Mettre à jour `middleware/auth.global.ts` : attendre la réhydratation avant de rediriger vers `/login`
- [ ] Logout : appeler `/api/client/logout` (purge cookies) + reset store

### 2.2 Finitions UI login (reliquats de la session Kimi)
- [ ] Page login : logo Paddock, titre « Connexion à votre espace client », label « Email », pas de SSO, pas de lien « Connexion atelier »
- [ ] Vérifier que le CSS est bien chargé (régression signalée) et corriger le 404 `manifest.json`

### 2.3 Passage en build de prod
- [ ] `docker-compose.yml:90-106` : remplacer `npx nuxt dev --port 3001` par `npm run build` + `node .output/server/index.mjs` (ou génération statique servie par Caddy)
- [ ] Retester le routing `/client/*` via Caddy port 81 après le changement

**Critère de sortie : un client se connecte, F5/fermeture navigateur ne le déconnecte plus, build prod servi derrière Caddy.**

### Réalisé (notes)
- Backend : cookie HttpOnly `client_refresh_token` (7 jours, path `/api/client`) posé au login, purgé au logout ; le refresh fonctionne cookie-only
- Front : composable `useClientApi` (fetch same-origin + retry silencieux via `/api/client/refresh` sur 401), store réécrit (le cookie est la source de vérité, `hydrated` évite les refetchs), middleware avec réhydratation au boot, pages migrées hors Bearer mémoire
- Build prod : Dockerfile multi-stage + commande compose `nuxt build && node .output/server/index.mjs` (fini `nuxt dev`)
- Caddy 81 : `/manifest.json` et `/sw.js` ajoutés aux assets (fix du 404 manifest)
- Vérifié par scénario navigateur complet : login → F5 → navigation → expiration access token → refresh silencieux → logout → session close (10/10), isolation RDV client A/B (404)
- Le login client était déjà conforme (logo, wording, pas de SSO)

---

## Phase 3 — Boucle métier de bout en bout (2-3 jours) ✅ FAIT (2026-06-12)

> **Décisions métier (cmoreau, 2026-06-11)** :
> - Booking public : l'email de confirmation propose l'activation de l'espace client (booking sans compte, conversion après)
> - RDV espace client : bouton « Demander l'annulation » → notification atelier, l'atelier valide (boutons Accepter/Refuser dans la modale RDV du planning)
> - Créneaux : réservables le jour même avec un délai minimum de 2h avant le créneau
> - Espace mécanicien : desktop ET mobile traités au même niveau
>
> **Décisions complémentaires (cmoreau, 2026-06-12)** :
> - Fiches véhicules : partagées entre ateliers uniquement si même propriétaire (téléphone identique), sinon nouvelle fiche locale
> - Tokens publics (suivi/restitution) : expirent 30 j après la clôture du RDV (RGPD, aligné photos)

- [x] **Booking public** : email d'accusé via NotificationDispatcher (template `booking_accuse` + invitation espace client pour les nouveaux comptes uniquement — anti-takeover), créneaux passés bloqués + délai 2h, garde module `public_booking` côté API, verrou anti-course (advisory lock), suivi en un clic par token
- [x] **Espace mécanicien** : `is_signed_by_both` exposé (GET /me/rapport/{orId}), canvas signature responsive, `essaiRoutierValide` corrigé, grilles mobiles, saisies protégées contre l'écrasement au refetch
- [x] **Restitution** : flux complet réparé (alias DQL `or` + liste blanche du freeze listener) et couvert par `restitution.spec.mjs` — il était entièrement mort
- [x] **Espace client** : statuts lisibles temps réel, PDF de l'OR finalisé (streaming du document figé, contrôle d'appartenance), demande d'annulation bout en bout

### Réalisé en plus (revues de code des 2026-06-11/12)
- Sécurité réseau port 81 : whitelist API stricte, Mercure retiré du public, rate limit companion, refresh token non utilisable en access, cookies Secure auto
- Réservations : occupation pont corrigée (pause déjeuner), contrôle de chevauchement côté staff (`PONT_OCCUPE`, `force=true` pour outrepasser)
- Analytics asynchrones (worker) — plus de ~25 requêtes d'agrégats par clic de workflow
- `todayLocalISO()` partout (bug date UTC entre minuit et 2h), guard NaN Safari, retour semaine courante du wizard, photos staff cloisonnées par atelier, `statut` non modifiable par PATCH générique, reset.bat/seed-demo.bat corrigés

**Critère de sortie : un RDV créé depuis le booking public est traité par le mécanicien, l'OR signé est consultable par le client dans son espace.** ✅

---

## Phase 4 — Notifications P0 (2 jours)

État : architecture bonne mais déconnectée (`NOTIFICATIONS_PLAN.md`, ~70 %). `RdvWorkflowListener` part sur le legacy `SendRappelHandler` au lieu de `NotificationDispatcher`.

- [x] Brancher `RdvWorkflowListener` sur `NotificationDispatcher` — fait, vérifié E2E le 2026-06-12
- [x] P0 : **confirmation RDV** (transition `confirmer`, testée E2E jusqu'à MailHog via `notifications-p0.spec.mjs`), **rappel J-1** (scheduler 8h, worker consomme `scheduler_default`, commande testée), **travaux terminés** (transition `terminer` → place `termine` = moto prête ; la place `pret_restitution` du plan n'existe pas dans le workflow réel)
- [x] `RappelProchaineRevisionCommand` : en fait déjà implémentée (95 lignes, planifiée à 9h) — le plan était obsolète ; exécution vérifiée
- [x] Email pour le MVP (MailHog dev OK) ; SMS : provider twilio configuré mais non testé = post-MVP
- [x] Healthcheck worker : commande `app:worker-health` + healthcheck Docker (unhealthy si file `failed` non vide) ; 2 messages périmés de mai purgés
- [x] Corriger `reset.bat` (`app:seed-parametres` → `app:seed`) — fait le 2026-06-12, idem `seed-demo.bat`

**Critère de sortie : les 3 notifications P0 partent réellement, visibles dans MailHog.**

---

## Phase 5 — Légal & RGPD (0,5 jour) ✅ FAIT (2026-06-12)

- [x] Placeholders `[SIRET]`/`[TVA]` : résolus dynamiquement au rendu depuis la fiche atelier (décision cmoreau — une seule source de vérité). À remplir dans **Admin → Ateliers** (valeurs factices posées en dev). Seul le bloc hébergeur reste en jetons : à compléter au déploiement (cf. `DEPLOIEMENT.md`)
- [x] Script `update_clauses.sql` appliqué, rendu vérifié sur les 3 pages du portail. L'article 4 BIS (pénalités de retard) est conservé : il encadre les factures que l'atelier émet par ailleurs, hors logiciel — valable même module facturation désactivé
- [x] Expiration 30 jours des tokens photos vérifiée — et étendue aux endpoints suivi/restitution (30 j après clôture du RDV)

---

## Phase 6 — Recette & déploiement (1-2 jours)

- [x] Adapter la suite Playwright : fait en phase 1 ; suite complète verte (171 passed) le 2026-06-12
- [x] Parcours client complet E2E : couvert par `client-portal.spec.mjs` (login → F5 → RDV → annulation) et `restitution.spec.mjs` (signature → document figé)
- [x] Test d'isolation `/api/client/*` : un client ne voit jamais les RDV/PDF d'un autre (404 systématique)
- [x] Healthcheck du worker Messenger : `app:worker-health` + healthcheck Docker
- [x] Front staff en build de production (2026-06-12) : fini `nuxt dev` — build Nuxt au démarrage du conteneur + Nitro, même pattern que le portail client. Suite E2E revalidée derrière (179 passed / 0 failed)
- [x] Déploiement « clé en main » préparé (2026-06-12) : `.env.prod.example` à remplir + `PUBLIC_DOMAIN` dans le `.env` suffit pour le HTTPS (Caddyfile paramétré, plus d'édition manuelle)
- [ ] Déploiement : voir **`DEPLOIEMENT.md`** (checklist secrets, HTTPS, vérifs post-déploiement). **Bloqué sur : serveur cible + nom de domaine + SIRET/TVA/hébergeur pour les clauses légales**

---

## Récapitulatif

| Phase | Contenu | Durée |
|---|---|---|
| 0 | Stabiliser la branche, tests verts, tag | 0,5 j |
| 1 | Facturation off (module + gardes backend + analytics) | 1 j |
| 2 | Auth client fiable + build prod du front client | 2-3 j |
| 3 | Boucle booking → mécanicien → OR → client | 2-3 j |
| 4 | Notifications P0 (email) | 2 j |
| 5 | Clauses légales / RGPD | 0,5 j |
| 6 | Recette E2E + déploiement | 1-2 j |
| **Total** | | **~10-12 jours** |

### Ordre de bataille
Phase 0 → 1 sont rapides et dérisquent tout. La **phase 2 (auth client)** est le chemin critique : tant qu'un client est déconnecté au refresh, l'espace client séparé n'est pas montrable. Les phases 3 et 4 peuvent se paralléliser (front/back).

---

## Chantier post-MVP — Migration du front staff vers le design system (diagnostic 2026-06-12)

**Constat** : 2 200 styles inline dans les pages staff (planning 214, dashboard 175, mécanicien 174, rdv/new 155, booking 152, admin/config 127…). Cause racine des bugs d'affichage : tailles en px figées, duplications divergentes, aucune adaptabilité. Un filet de sécurité CSS global est en place (images/textes/débordements/modales), mais le remède durable est la migration.

**Méthode (1 lot = 1 page, suite E2E entre chaque)** :
1. `index.vue` (dashboard) — page pilote, pose les classes utilitaires manquantes
2. `planning.vue` — la plus utilisée et la plus chargée
3. `mecanicien.vue` + `workshop.vue` (PDA/tablette : le responsive y est critique)
4. `rdv/new.vue` + `rdv/index.vue`
5. `public/booking.vue` + `public/companion.vue` (vitrine publique)
6. `admin/*` au fil de l'eau
Règles : remplacer chaque `style="…"` par les classes du design system (`.panel`, `.btn*`, `.form-*`, variables CSS), extraire les blocs répétés en composants (`StatusBadge` partout, `SignaturePad` unique), zéro changement de design — uniquement de la robustesse.
