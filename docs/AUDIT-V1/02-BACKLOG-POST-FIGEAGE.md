# Backlog post-figeage AtelierMBZ

Issues identifiées lors de l'audit complet du 2026-04-21 mais reportées à des LOTs dédiés (raisons : risque migration, demande d'arbitrage métier, ou impact UI à valider).

---

## 🟠 LOT-FIX-1 — Mecanicien.userId : ajouter FK Doctrine

**Fichier** : `backend/src/Entity/Mecanicien.php` L31

**Problème** : `private ?int $userId = null;` est un `int` nu (pas de relation Doctrine). Les usages (`UserMecanicienSyncService`, `MecanicienController` x4, `RendezVousController`) font des `findOneBy(['userId' => ...])`. Si un User est supprimé, `userId` devient orphelin.

**Action** :
1. Backup base avant migration
2. ALTER TABLE mecaniciens : ajouter contrainte `FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL`
3. Vérifier qu'aucun `userId` ne pointe vers un User inexistant (sinon nettoyer ou créer FK SET NULL)
4. Mettre à jour entité Doctrine : `#[ORM\ManyToOne(targetEntity: User::class)]` + `#[ORM\JoinColumn(name: 'user_id', onDelete: 'SET NULL', nullable: true)]`
5. Garder `userId` accessor pour compat code existant OU refactoriser tous les `findOneBy`

**Risque** : prod soft → demande prudence et fenêtre de maintenance.

---

## 🟠 LOT-FIX-2 — RapportTechnicien dead code

**Fichiers** :
- `backend/src/Entity/RapportTechnicien.php`
- `backend/src/Entity/RendezVous.php` L125-127, L268-272 (relation OneToOne + getter/setter)
- Table BDD `rapports_technicien`
- Migration de drop à créer

**Statut** : confirmé jamais utilisé (0 service, 0 controller, 0 test, 0 page front). Remplacé entièrement par `RapportIntervention`.

**Action** :
1. Vérifier qu'aucune ligne n'existe en table prod (`SELECT COUNT(*) FROM rapports_technicien`)
2. Si vide : créer migration DROP TABLE + retirer entité + retirer relation dans RendezVous
3. Si non vide : analyser le contenu, migrer vers `RapportIntervention` si pertinent, puis drop

**Risque** : faible (zéro usage) mais migration non-trivialement réversible.

---

## 🟠 LOT-FIX-3 — Cookie active_atelier_id non-HttpOnly (volontaire, à documenter)

**Fichiers** : `backend/src/Controller/AuthController.php` L236, L787

**Décision** : ne pas mettre HttpOnly (utilisé par `useCookie` Nuxt). Le JWT contrôle l'accès réel.

**Action** : ajouter commentaire dans le code expliquant le choix volontaire pour empêcher un développeur futur de "fixer" et casser le frontend.

---

## 🟠 LOT-FIX-4 — Notifications transitions gardiennage

**Fichier** : `backend/src/EventListener/RdvWorkflowListener.php`

**Problème** : Listener n'écoute pas `passer_gardiennage` / `mettre_en_gardiennage` / `sortir_gardiennage` / `restituer_partiel` / `reporter`. Les notifications gardiennage sont déclenchées par cron J+15/J+30/J+45/J+180, pas immédiatement à l'entrée en gardiennage.

**Arbitrage métier requis** : faut-il prévenir le client immédiatement quand sa moto passe en gardiennage, ou laisser le réceptionniste gérer oralement à la réception comme actuellement ?

**Templates en base déjà disponibles** : `relance_gardiennage_j15`, `j30`, `j45`, `j180`. Pas de template "entrée immédiate" → à créer si arbitrage = oui.

---

## 🟠 LOT-FIX-5 — Content-Security-Policy

**Fichier** : `backend/src/EventListener/SecurityHeadersListener.php`

**Problème** : Pas de header CSP. Risque XSS / injection script.

**Action** : Définir une CSP compatible avec :
- Assets Nuxt (chunks JS/CSS hashés)
- Mercure SSE (`EventSource` connect-src)
- DomPDF (inline styles dans templates)
- Logos atelier (data: URI ou external src)

**Tests requis** : tous les écrans + génération PDF + Mercure connection. Pas trivial.

---

## 🟠 LOT-FIX-6 — Webhook providers SMS non signés

**Fichier** : `backend/src/Service/NotificationDispatcher.php`

**Problème (claim subagent à vérifier)** : Endpoints webhook (Twilio, OVH, Mailgun) n'auraient pas de vérification de signature HMAC. Risque d'injection de faux statuts de livraison.

**Action** :
1. Vérifier dans les controllers webhook concernés (chercher endpoints `/webhook/*`)
2. Si absent : ajouter validation `hmac_sha1(payload, providerSecret)` selon doc provider
3. Stocker `provider_webhook_secret` dans `NotificationProviderConfig`

---

## 🟠 LOT-FIX-7 — `setStatut('en_attente')` redondants

**Fichiers** :
- `backend/src/Controller/DevisController.php` L171 (`convertir()`)
- `backend/src/Controller/PublicBookingController.php` L253

**Problème** : Le workflow `rendez_vous` a `initial_marking: en_attente`. Donc l'appel `setStatut('en_attente')` après `new RendezVous()` est inutile. Pas de bug fonctionnel mais hygiène.

**Action** : retirer ces lignes. Aucun risque.

---

## 🟠 LOT-FIX-8 — Playwright E2E timeouts

**Fichier** : `frontend/tests/e2e/helpers.mjs` + tous les `.spec.mjs`

**Problème** : 34/84 tests E2E timeout sur `page.goto waiting until load`. Nuxt en mode dev n'émet jamais `load` propre (HMR + chunks asynchrones).

**Action** : 2 approches au choix :
1. **Build prod pour tests E2E** : `npm run build` puis serveur statique → vrais tests bout en bout
2. **Patch helpers** : remplacer `waitUntil: 'load'` par `waitUntil: 'domcontentloaded'` ou `'networkidle'` partout

Recommandation : option 1 (plus robuste et détecte aussi les bugs de build SSR).

---

## 🔵 Confort divers (faible priorité)

- **NotificationLog** : table créée mais jamais lue → soit l'utiliser pour analytics envoi, soit la supprimer
- **MercureNotifier** : pas de RBAC sur topics. Ajouter JWT claim côté Mercure pour vérifier `atelier_id` côté client
- **useApi.ts / useNotifications.ts** : type `any` sur quelques catch — typer en `unknown` avec guard
- **planning.vue** : pas d'`AppErrorState` sur refresh failure
- **rdv/new.vue** : try/catch absent autour des appels API search

---

## Comptes audit à supprimer avant prod

6 comptes créés sur atelier id=1, mot de passe `Audit2026!` :
- `audit_super` (id 1978)
- `audit_admin` (1979)
- `audit_recep` (1980)
- `audit_meca` (1981) + Mecanicien id 271
- `audit_vo` (1982)
- `audit_compta` (1983)

Compte `admin@atelier.local` avec mot de passe reset à `Audit2026!` → remettre un mot de passe fort avant prod.
