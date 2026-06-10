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

## Phase 2 — Fiabiliser l'espace client séparé (2-3 jours)

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

---

## Phase 3 — Boucle métier de bout en bout (2-3 jours)

- [ ] **Booking public** : aligner le formulaire public sur la prise de RDV interne (champs manquants, sélection d'atelier — point resté ouvert en session Kimi), bloquer les créneaux passés (fix `3d8cf83` à vérifier côté public)
- [ ] **Espace mécanicien** — reliquats de `MECANICIEN_PLAN.md` :
  - [ ] `isSignedByBoth` jamais défini → rapport signé invisible
  - [ ] Vérifier que `/api/rapport/{id}/pdf` (`OrdreReparationPdfController`) couvre bien le besoin
  - [ ] Canvas signature responsive (580px fixe → déformé mobile)
  - [ ] Corriger la logique `essaiRoutierValide`
- [ ] **Restitution** : valider le flux complet `pret_restitution → livré → terminé` avec signature client + snapshot PDF figé
- [ ] **Espace client** : « Mes RDV » affiche le statut temps réel + lien vers le PDF de l'OR finalisé (réutiliser le endpoint PDF avec contrôle d'appartenance client)

**Critère de sortie : un RDV créé depuis le booking public est traité par le mécanicien, l'OR signé est consultable par le client dans son espace.**

---

## Phase 4 — Notifications P0 (2 jours)

État : architecture bonne mais déconnectée (`NOTIFICATIONS_PLAN.md`, ~70 %). `RdvWorkflowListener` part sur le legacy `SendRappelHandler` au lieu de `NotificationDispatcher`.

- [ ] Brancher `RdvWorkflowListener` sur `NotificationDispatcher` (création d'entités `Notification` + email)
- [ ] P0 : **confirmation RDV** (à la transition `confirmé`), **rappel J-1** (scheduler), **travaux terminés** (transition `pret_restitution`)
- [ ] Implémenter `RappelProchaineRevisionCommand` (actuellement vide) — ou la sortir explicitement du MVP
- [ ] Email uniquement pour le MVP (MailHog en dev, SMTP réel en prod). SMS = post-MVP
- [ ] Corriger `reset.bat` (`app:seed-parametres` → `app:seed`)

**Critère de sortie : les 3 notifications P0 partent réellement, visibles dans MailHog.**

---

## Phase 5 — Légal & RGPD (0,5 jour)

- [ ] Remplir les placeholders `[SIRET]`, `[TVA]`… dans `update_clauses.sql`
- [ ] Appliquer le script en base et vérifier le rendu sur `/client/cgv`, `/client/mentions-legales`, `/client/politique-confidentialite` (les CGV doivent rester cohérentes avec un MVP sans facturation — vérifier l'article pénalités de retard)
- [ ] Vérifier l'expiration 30 jours des tokens photos publics (RGPD)

---

## Phase 6 — Recette & déploiement (1-2 jours)

- [ ] Adapter la suite Playwright : tests facturation/devis attendus en « module désactivé » (pattern déjà utilisé pour VO, cf. `BUGFIX_LOG.md`)
- [ ] Ajouter un test E2E sur le parcours client complet : login → F5 → toujours connecté → consultation RDV → PDF OR
- [ ] Test d'isolation : un client A ne voit jamais les données du client B (`security-tenant-isolation.spec.mjs` à étendre côté `/api/client/*`)
- [ ] Healthcheck du worker Messenger (alerte si file `failed` non vide)
- [ ] Déploiement : Caddy 80 (interne) / 81 (public), HTTPS, secrets en env

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
