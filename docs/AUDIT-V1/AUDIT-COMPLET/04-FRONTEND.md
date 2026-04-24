Maintenant que j'ai collecté toutes les informations, je vais produire le rapport exhaustif :

---

# Rapport Exhaustif du Frontend Nuxt 3 — AtelierMBZ

## 📋 Table des matières
1. [Configuration Nuxt](#configuration-nuxt)
2. [Pages](#pages)
3. [Composables](#composables)
4. [Stores Pinia](#stores-pinia)
5. [Composants](#composants)
6. [Middlewares](#middlewares)
7. [Layouts](#layouts)
8. [Plugins](#plugins)
9. [Tests Vitest](#tests-vitest)

---

## Configuration Nuxt

### `nuxt.config.ts`

**Modules activés :**
- `@nuxt/ui` — Système de design UI
- `@pinia/nuxt` — Gestion d'état

**SSR :** `false` (SPA)

**Runtime Config public :**
- `apiBase` : `/api`
- `mercureUrl` : `/.well-known/mercure`

**Colormode :** Dark mode par défaut

**Nitro Route Rules :**
- Proxy API : `/api/**` → `http://localhost:8000/api/**`
- CSP Report-Only sur toutes les pages (observer mode)
  - Whitelist : fonts.googleapis.com, Mercure
  - Headers de sécurité : X-Frame-Options, X-Content-Type-Options, Referrer-Policy

---

## Pages

### Vue d'ensemble par URL

| URL | Métier | But | État |
|-----|--------|-----|------|
| `/` | Resp. atelier, Admin | Dashboard KPI : CA, RDV, panier moyen, occupation ponts, charge, statuts | ⚠️ Implémenté |
| `/login` | Public | Authentification Google SSO + fallback local | ⚠️ Implémenté |
| `/profile` | Tous | Profil utilisateur : identité, rôle, permissions, atelier actif | ⚠️ Implémenté |
| `/planning` | Réceptionniste | Planning drag & drop ponts/mécaniciens, création RDV rapide, filtrage | ⚠️ Implémenté |
| `/workshop` | Resp. atelier | Pilotage ponts, mécaniciens, alertes, gardiennage, absences | ⚠️ Implémenté |
| `/mecanicien` | Mécanicien | Espace mobile-first : interventions en cours, chrono, checkup, essai routier, photos, travaux complémentaires | ⚠️ Implémenté |
| `/rdv/new` | Réceptionniste | Prise de RDV : client, véhicule, type intervention, pont, mécanicien, temps estimé | ⚠️ Implémenté |
| `/rdv/[id]` | Tous | Détail RDV (redirection conditionnelle) | ⚠️ Implémenté |
| `/rdv/index` | Réceptionniste | Liste RDV filtrée (redirection vers `/planning` ou `/rdv/new` selon rôle) | ⚠️ Implémenté |
| `/ordres/index` | Tous | Liste des OR : client, véhicule, montant, statut ; filtres par statut | ⚠️ Implémenté |
| `/ordres/[id]` | Tous | Détail OR : création/consultation, lignes, signatures, rectification | ⚠️ Implémenté |
| `/rapport/[rdvId]` | Mécanicien + Réceptionniste | Rapport d'intervention : travaux, km restitution, essai routier, signatures, rectification | ⚠️ Implémenté |
| `/devis/index` | Tous | Liste des devis : client, véhicule, montant, statut | ⚠️ Implémenté |
| `/devis/[id]` | Tous | Détail devis : lignes, totaux, validité, signature client | ⚠️ Implémenté |
| `/clients/index` | Réceptionniste | Liste clients : recherche, stats (avec RDV, véhicules), CA total | ⚠️ Implémenté |
| `/clients/[id]` | Réceptionniste | Détail client : contact, véhicules associés, historique RDV | ⚠️ Implémenté |
| `/motos` | Tous | Catalogue moto : recherche, filtre catégorie, détails techniques | ⚠️ Implémenté |
| `/suivi` | Réceptionniste | Suivi live interventions par mécanicien (temps réel Mercure) | ⚠️ Implémenté |
| `/tarifs` | Admin | Configuration grilles tarifaires : prestations, taux MO, marges pièces | ⚠️ Implémenté |
| `/facturation/index` | Comptable + Admin | Liste factures (EN RÉÉCRITURE — NE PAS TOUCHER) | 🔄 Réécriture |
| `/stock/index` | Gestionnaire stock | Gestion pièces détachées (EN RÉÉCRITURE — NE PAS TOUCHER) | 🔄 Réécriture |
| `/admin/index` | Admin | Hub admin : accès ateliers, users, config, absences, ponts, etc. | ⚠️ Implémenté |
| `/admin/ateliers` | Super-admin | CRUD ateliers (créer, lister, configurer) | ⚠️ Implémenté |
| `/admin/users` | Admin | CRUD utilisateurs : rôles, permissions, SSO | ⚠️ Implémenté |
| `/admin/roles` | Super-admin | Gestion rôles globaux (CRUD) | ⚠️ Implémenté |
| `/admin/roles-metier/index` | Super-admin | Lister rôles métier (CRUD) | ⚠️ Implémenté |
| `/admin/roles-metier/[id]` | Super-admin | Détail rôle métier : permissions granulaires | ⚠️ Implémenté |
| `/admin/prestations` | Admin | CRUD prestations (tarification par prestation) | ⚠️ Implémenté |
| `/admin/ponts` | Admin | CRUD ponts : équipements, affectations mécaniciens | ⚠️ Implémenté |
| `/admin/config` | Admin | Configuration atelier : identité, tarifs, horaires, gardiennage, modules VO | ⚠️ Implémenté |
| `/admin/absences` | Admin | CRUD absences mécaniciens (congé, maladie, formation) | ⚠️ Implémenté |
| `/admin/audit` | Super-admin | Journal d'audit global (AuditLog cross-atelier) | ⚠️ Implémenté |
| `/admin/clauses-legales` | Admin | Gestion clauses légales par prestation | ⚠️ Implémenté |
| `/admin/templates-documents` | Super-admin | Édition templates email/SMS | ⚠️ Implémenté |
| `/admin/demandes-travaux-supp` | Admin | Configuration demandes complémentaires (workflow, escalade) | ⚠️ Implémenté |
| `/admin/notifications/providers` | Admin | Configuration providers (SMTP, Twilio, Mercure) | ⚠️ Implémenté |
| `/admin/cerfa-config` | Super-admin | Paramètres CERFA / SIV (numérotation, données) | ⚠️ Implémenté |
| `/vo/index` | Gestionnaire VO | Dashboard VO : stock prioritaire, alertes, stats | ⚠️ Implémenté |
| `/vo/rachats/new` | Gestionnaire VO | Wizard création rachat : vendeur → véhicule → documents → FRE → confirmation | ⚠️ Implémenté |
| `/vo/rachats/index` | Gestionnaire VO | Lister rachats avec filtres (statut, marge, jours stock) | ⚠️ Implémenté |
| `/vo/rachats/[id]` | Gestionnaire VO | Détail rachat : véhicule, documents obligatoires, Livre Police, verdict vendabilité | ⚠️ Implémenté |
| `/vo/depots/new` | Gestionnaire VO | Wizard création dépôt-vente : déposant → véhicule → mandat → confirmation | ⚠️ Implémenté |
| `/vo/depots/index` | Gestionnaire VO | Lister dépôts avec filtres (statut, mandat, net déposant) | ⚠️ Implémenté |
| `/vo/depots/[id]` | Gestionnaire VO | Détail dépôt-vente : véhicule, mandat, revenus, LP | ⚠️ Implémenté |
| `/vo/remises-en-etat/index` | Gestionnaire VO | File atelier FRE : prestations, pièces, coûts, marge temps réel | ⚠️ Implémenté |
| `/vo/factures` | Comptable VO | Lister factures VO (régime TVA marge ou normal) | ⚠️ Implémenté |
| `/vo/livre-police` | Gestionnaire VO | Registre officiel acquisitions/ventes (immuable) | ⚠️ Implémenté |
| `/vo/documents` | Admin VO | Gestion documents VO (uploads, RETENTION_YEARS) | ⚠️ Implémenté |
| `/public/booking` | Client public | Prise de RDV en ligne : sélection atelier, creénaux, confirmation | ⚠️ Implémenté |
| `/public/suivi/[token]` | Client public | Suivi RDV avec code token (progress steps) | ⚠️ Implémenté |
| `/public/suivi` | Client public | Recherche suivi (saisir code token) | ⚠️ Implémenté |
| `/public/companion/[token]` | Client public | Signature OR + essai routier + restitution (PDA) | ⚠️ Implémenté |
| `/public/vo-companion/[token]` | Vendeur / Déposant VO | Signature documents VO (PDA) : pièce identité, CG, etc. | ⚠️ Implémenté |
| `/public/demande/[token]` | Client public | Validation demande travaux complémentaires (avec signature) | ⚠️ Implémenté |
| `/public/mentions-legales` | Public | Mentions légales | ⚠️ Implémenté |
| `/public/politique-confidentialite` | Public | Politique de confidentialité | ⚠️ Implémenté |

---

### Pages complexes — Détails

#### `/planning.vue`

**Métier cible :** Réceptionniste

**Sections de l'écran :**
1. **KPI Bar** : Charge visible (MO + pièces), conflits, non-assignés, retards
2. **Légende de couleurs** : À valider, réservé, confirmé, en cours, terminé
3. **Horaires affichage** : Range horaire atelier + jours ouverture
4. **Filtre mécaniciens** : Toggle par mécano avec couleur
5. **View toggle** : Grid (drag-drop) vs List (tableau)
6. **PlanningGrid** : Composant affichage visuellement slots par pont × temps
7. **PlanningList** : Alternative tabulaire des RDV
8. **Quick Create Modal** : Création RDV rapide depuis planning

**Stores utilisés :** `useRdvStore`, `useAtelierStore`, `useAuthStore`

**Composables utilisés :** `useApi`, `useAuth`, `useFormat`

**Endpoints API clés :**
- `GET /rendez-vous` (filtre date, statut, mécanicien, pont)
- `PATCH /rendez-vous/{id}` (déplacement, transition)
- `GET /mecaniciens` (liste mécaniciens pour filtres)
- `GET /ponts` (liste ponts)
- `GET /horaires-atelier` (horaires)

---

#### `/mecanicien.vue`

**Métier cible :** Mécanicien (mobile-first, tablette)

**Sections :**
1. **Header** : Avatar, date, KPIs (en cours, à faire, terminés, % journée)
2. **Priority Action** : Prochaine action (basée sur statut RDV)
3. **Active Intervention Card** :
   - Nav tabs : Intervention, Check-up, Essai routier, Travaux, Photos, Notes
   - **Intervention** : Client, véhicule, pont, motif, contexte réception (OR signé, km, carburant, priorité)
   - **Chrono** : Elapsed / total (transition `start_travail`, `mettre_en_pause`, `reprendre`)
   - **Check-up** : Grille d'inspection des points (toggle OK/NOK/N/A), photos associées
   - **Essai routier** : Km début/fin, points de contrôle, anomalies, signature mécanicien
   - **Travaux complémentaires** : Liste demandes en escalade, accept/reject
   - **Photos** : Galerie photos intervention (types : en_cours, apres_travaux, restitution, probleme)
   - **Notes** : Notes texte mécanicien

**Stores utilisés :** `useRdvStore`, `useAuthStore`

**Composables utilisés :** `useApi`, `useFormat`, `useNotifications` (Mercure)

**Composants enfants :**
- `MecanicienNav` — Onglets section
- `MecanicienSection` — Container section repliable
- `MecanicienCheckupGrid` — Grille checkup interactive
- `MecanicienChrono` — Affichage chrono temps

**Endpoints API clés :**
- `GET /rendez-vous/{id}/mecanicien` (contexte mécanicien)
- `GET /essai-routier/{rdvId}` (essai routier)
- `PATCH /essai-routier/{id}` (sauvegarder essai)
- `PATCH /rendez-vous/{id}` (transitions : start_travail, mettre_en_pause, etc.)
- `POST /photos/upload` (upload photos intervention)
- `GET /rapport-intervention/{rdvId}` (rapport)

---

#### `/workshop.vue`

**Métier cible :** Responsable atelier

**Sections :**
1. **KPI Bar** : Occupation ponts %, RDV aujourd'hui, mécaniciens actifs, conflits
2. **Tabs** :
   - **Ponts** : Vue ponts (grid) ou liste. Alert badges (CG manquante, CT expiré, OR non signé, essai manquant, gardiennage). Véhicule timeline par pont.
   - **Mécaniciens** : Charge par mécanicien, historique journée
   - **Alertes** : Listing alertes filtrables (docs manquants, expirations, workflow bloqués)
   - **Gardiennage** : Motos en gardiennage (relances, abandon)
   - **Absences** : Absences mécaniciens vue calendrier

**Stores utilisés :** `useRdvStore`, `useAuthStore`, `useAtelierStore`

**Composables utilisés :** `useApi`, `useNotifications` (Mercure)

**Composants enfants :**
- `WorkshopAlertBadges` — Filtres d'alerte
- `WorkshopQuickActions` — Actions rapides
- `WorkshopVehicleTimeline` — Timeline véhicule par pont
- `WorkshopSkeletonBays` — Skeleton loading

---

#### `/vo/rachats/new.vue`

**Métier cible :** Gestionnaire VO

**Wizard 4 étapes :**
1. **Vendeur** : Recherche client ou création rapide (prénom, nom, tél, email, adresse)
2. **Véhicule** : OCR carte grise ou saisie manuelle (plaque, VIN, marque, modèle, MEC, cylindrée, couleur)
3. **Documents** : Upload documents obligatoires (CG, CT, non-gage, PV rachat, PV d'état)
4. **FRE** : Frais remise en état (lignes, pièces, coûts, marge calculée en temps réel via `VOMarginService`)

**Secteur Companion** : QR code immédiat pour ouverture `/public/vo-companion/{token}` (PDA).

**Stores utilisés :** `useVoStore`, `useAuthStore`

**Composables utilisés :** `useApi`, `useCarteGriseOcr`, `useQrCode`, `usePdfDownload`, `voVehicleForm`

**Endpoints API clés :**
- `POST /vo/rachats` (création brouillon)
- `PATCH /vo/rachats/{id}` (mise à jour étapes)
- `POST /vo/rachats/{id}/companion` (génération token Companion PDA)
- `POST /ocr/carte-grise` (OCR carte grise)

---

#### `/public/companion/[token].vue`

**Métier cible :** Client final (signature OR + essai + restitution)

**Sections :**
1. **RDV Info** : Récapitulatif client, véhicule, statut
2. **Réception PDA** (statut == `reception`) : Signature client sur OR
3. **Intervention terminée** (statut == `termine`) : Signature client rapport d'intervention + essai routier
4. **Status Pills** : Indicateurs photos, OCR CG, signatures

**Composants :**
- Canvas signature bidirectionnelle (pointerdown/pointermove/pointerup)
- Affichage rapport d'intervention texte

**Endpoints API clés :**
- `GET /public/companion/{token}` (validation token)
- `GET /rendez-vous/{id}/public` (données RDV avec token)
- `PATCH /rendez-vous/{id}/signature-or` (signature OR client)
- `PATCH /rendez-vous/{id}/signature-rapport` (signature rapport client)

---

#### `/public/vo-companion/[token].vue`

**Métier cible :** Vendeur / Déposant VO (signature documents VO)

**Sections :**
1. **Stepper** : Étapes étapes wizard (Vendeur/Déposant → Véhicule → Documents → Signature)
2. **Seller** : Transcription pièce identité (type, numéro, date) — **pas d'upload**
3. **Vehicule** : Saisie véhicule (OCR CG ou manual)
4. **Documents** : Liste documents générés (PV rachat, contrat dépôt, CERFA, etc.)
5. **Signature** : Canvas électronique

**Stores utilisés :** `useVoStore`

**Composables utilisés :** `useApi`, `voCompanionDraftSync`, `voVehicleForm`, `usePdfDownload`

---

#### `/vo/remises-en-etat/index.vue`

**Métier cible :** Gestionnaire VO

**Contenu :**
- Liste véhicules VO en FRE
- Chaque véhicule : marque/modèle, plaque, prestations + pièces associées
- Colonnes : désignation, coût HT, quantité, temps, statut (proposée, validée, facturée)
- **VORemiseEnEtatCard** composant enfant : affiche ligne + pièces, marge temps réel via `VOMarginService`

**Stores utilisés :** `useVoStore`

**Composables utilisés :** `useApi`

**Composants enfants :**
- `VORemiseEnEtatCard` — Carte FRE ligne + pièces

---

---

## Composables

| Composable | Fonctions exportées | But |
|------------|-------------------|-----|
| **useApi** | `$fetch<T>`, `get<T>`, `post<T>`, `patch<T>`, `put<T>`, `delete<T>`, `upload` | Client HTTP universel avec auth, refresh token auto, gestion erreurs |
| **useAuth** | `login()`, `getGoogleLoginConfig()`, `startGoogleLogin()`, `exchangeGoogleCode()`, `logout()`, `fetchMe()`, `hasSection()`, `hasPerm()`, `hasStatsAccess()` | Authentification SSO Google + local, récupération user, permissions |
| **useFormat** | `formatDate()`, `formatTime()`, `formatCurrency()`, `formatDuration()` | Formatage dates, heure, devise EUR, durée en H:MM |
| **useValidation** | `isValidPhone()`, `isValidEmail()`, `isValidPlate()`, `validateClientFields()` | Validation regex champs français (tél 10 chiffres, plaque AA-123-AA, email) |
| **useNotifications** | `fetchNotifications()`, `fetchUnreadCount()`, `markAsRead()`, `subscribeToMercure()` | Notifications via EventSource Mercure ou poll (websocket temps réel) |
| **useQrCode** | `useQrCode(text, size)` → `{ dataUrl }`, `generateQrDataUrl()` | Génération QR codes localement (zéro service externe) |
| **usePdfDownload** | `downloadPdf(path, filename)`, `openPdf(path)` | Téléchargement/aperçu PDF via fetch blob (inclut credentials) |
| **useMotoAutocomplete** | `searchMoto()`, `getMotoDetails()` | Autocomplete marque/modèle moto via API |
| **useDebounceFn** | `debounceFn(fn, delay)` | Debounce fonction |
| **useCarteGriseOcr** | `startOcr()`, `pickOcrImageFile()`, `buildOcrComparison()`, `vinErrorMessage()`, `isValidVin()` | OCR carte grise (téléchargement image + traitement backend), validation VIN ISO 3779 |
| **useVoHelpers** | `formatPrice()`, `computeMargin()` | Helpers VO (calculs prix/marge) |
| **voVehicleForm** | `isValidVin()`, `vinErrorMessage()`, `extractVehicleCategoryId()`, `applyVehicleToForm()`, `buildVoVehiclePayload()` | Gestion formulaire véhicule VO (VIN validation, payload API) |
| **voCompanionDraftSync** | `syncDraftField()`, `syncDraftBoolean()`, `adoptDraftEntity()` | Synchronisation brouillon Companion PDA ↔ saisie comptoir (conflit détection) |
| **voRefurbishmentCard** | `selectRefurbishmentCampaignId()`, `buildRefurbishmentLineForms()`, `buildRefurbishmentPieceForms()`, `toRefurbishmentDateTimeLocal()` | Gestion formulaires FRE (campagnes, lignes, pièces) |

### Détails composables clés

#### `useApi`

**Singleton refresh token** : Si 401 → refresh auto (serialisé pour éviter race conditions parallèles).

**Gestion FormData** : Auto-détection `Content-Type`.

**Logging** : Erreurs en console si verbeux.

**Fallback local cert** : Recommandation si HTTPS localhost échoue.

---

#### `useAuth`

**Contexte :** Multi-ateliers, rôles métier, permissions granulaires.

**Refresh stratégie :**
- `silentRefresh()` → POST `/auth/refresh` (include credentials)
- `fetchMe()` → GET `/auth/me` pour rechargement contexte

**Permissions :**
- `hasSection(section)` : Vérif dans `role_permissions.sections_json`
- `hasPerm(module, action)` : Vérif dans `role_permissions.permissions_json`
- `hasStatsAccess()` : Accès dashboard (resp. atelier min)

---

#### `useNotifications`

**Transport :** Priorité Mercure (EventSource) → fallback poll (setInterval).

**API endpoints :**
- `GET /notifications?status=unread&limit=50` (fetch)
- `GET /notifications/unread-count` (count badge)
- `PATCH /notifications/{id}/read` (marquer lue)

---

#### `useCarteGriseOcr`

**Flux :**
1. Utilisateur sélectionne fichier image (ou PDF ignoré)
2. `startOcr(file)` → `POST /ocr/carte-grise` (multipart)
3. Retour JSON : plaque, marque, modèle, VIN, annee, cylindree, etc.
4. Comparaison vs saisie manuelle : `buildOcrComparison()` retourne { tone, message, canUseBase }

**Validation VIN ISO 3779 :**
- 17 caractères exactement
- Pas I, O, Q (ambigu visuellement)
- Alphanumérique

---

---

## Stores Pinia

| Store | State | Getters | Actions | But |
|-------|-------|---------|---------|-----|
| **app** | `sidebarOpen`, `loading`, `currentSection` | — | `toggleSidebar()`, `setSection()`, `setLoading()` | UI globale (sidebar, section active) |
| **auth** | `user: UserData` | `isAuthenticated`, `fullName`, `role`, `atelierId`, `atelierNom` | `setUser()`, `clearUser()` | Contexte user (JWT payload, permissions) |
| **atelier** | `modules`, `branding`, `loaded` | `isModuleEnabled()` | `setModules()`, `setBranding()`, `setConfig()`, `clearModules()` | Modules VO/atelier activés, branding (logo, nom) |
| **rdv** | `rdvs`, `currentRdv`, `filters`, `loading`, `planningDate` | — | `fetchRdvs()`, `getRdvById()`, `updateRdv()`, `createRdv()` | Gestion RDV en mémoire (cache planning) |
| **billing** | `factures`, `currentFacture`, `loading` | — | `fetchFactures()`, `createFacture()`, `addPayment()`, `createAvoir()`, `downloadPdf()` | Gestion factures atelier (archive, téléchargement PDF) |
| **stock** | `pieces`, `loading` | `alertes` (pièces < seuil) | `fetchPieces()`, `createPiece()`, `updatePiece()` | Gestion stock pièces détachées |
| **vo** | `purchases`, `depots`, `refurbishments`, `loading` | — | `fetchPurchases()`, `createPurchase()`, `updatePurchase()`, `fetchDepots()`, `createDepot()`, `updateDepot()`, etc. | Gestion dossiers VO (rachats, dépôts, FRE) |

### Détails stores clés

#### `useAuthStore`

```typescript
interface UserData {
  id: number
  email: string
  username: string
  nom?: string | null
  prenom?: string | null
  role: string  // Legacy
  roles?: string[]  // New (ROLE_*, ROLE_SUPER_ADMIN)
  atelier_id?: number | null
  atelier_nom?: string | null
  auth_provider?: string
  access_status?: string
  is_pending_validation?: boolean
  needs_atelier_assignment?: boolean
  role_permissions?: {
    sections_json: string[]
    permissions_json: string[]
  } | null
  role_metier?: {
    id: number
    code: string
    libelle: string
    base_role?: string
    permissions?: Array<{ module: string; action: string; scope: string }>
  } | null
}
```

---

#### `useAtelierStore`

**Feature modules par défaut :**
```typescript
{
  dashboard: true,
  rdv: true,
  planning: true,
  workshop: true,
  mecanicien: true,
  suivi: true,
  clients: true,
  or: true,
  devis: true,
  facturation: true,
  stock: true,
  absences: true,
  admin: true,
  tarifs: true,
  vo: false,  // Disabled par défaut
}
```

**Branding :** Logo atelier, nom, adresse, SIRET, TVA intracom

---

#### `useRdvStore`

**Normalisation API Platform** : Aplati champs imbriqués (client.nom → client_nom, etc.) pour alignment templates.

**Payload update :**
```typescript
{
  date_rdv,
  heure_rdv,
  type_intervention,
  commentaire,
  temps_estime,
  prix_estime,
  prix_final,
  kilometrage,
  statut,
  client: IRI,
  vehicule: IRI,
  pont: IRI,
  mecanicien: IRI
}
```

---

#### `useVoStore`

**Actions principales :**
- `fetchPurchases(limit=200)` → GET `/vo/rachats?itemsPerPage=200`
- `createPurchase(payload)` → POST `/vo/rachats`
- `updatePurchase(id, patch)` → PATCH `/vo/rachats/{id}`
- `fetchDepots()`, `createDepot()`, `updateDepot()` (dépôts-vente)
- `fetchRefurbishments(depotId)` → GET `/vo/remises-en-etat?depot={id}`
- `createRefurbishment(depotId)` → POST `/vo/depots/{depotId}/remise-en-etat`
- `fetchLivrePolicies()` → GET `/vo/livre-police` (immuable, GET only)

---

---

## Composants

| Composant | Props | Événements | But |
|-----------|-------|-----------|-----|
| **AppPageHeader** | `title`, `subtitle`, `backTo` | — | En-tête page uniformisé (titre, sous-titre, actions slot) |
| **AppBanner** | `variant` (info/warning/danger), `title`, `description`, `dismissible` | `@dismiss` | Banneau d'alerte/notification en haut page |
| **AppBreadcrumbs** | — | — | Fil d'Ariane navigation |
| **AppEmptyState** | `icon`, `title`, `description`, `actionLabel` | `@action` | État vide (pas de données) |
| **AppErrorState** | `title`, `description` | `@retry` | État erreur (échec chargement API) |
| **AppLoadingState** | `title`, `description` | — | État loading (skeleton ou spinner) |
| **AppModal** | `v-model:open`, `size` (lg/md/sm), `title` | — | Modal générique avec slot content |
| **AppNotificationBell** | `unreadCount` | `@click` | Cloche notifications header |
| **AppPageHeader** | `title`, `subtitle` | — | En-tête page (utilisé dans planning, mecanicien, etc.) |
| **AppPdfEmbed** | `src` (URL PDF) | — | Afficheur PDF inline |
| **AppPeriodSelector** | `v-model`, `presets`, `selectedPreset` | `@preset`, `@update:model-value`, `@refresh` | Sélecteur période (dashboard) |
| **AppQuickActions** | — | — | Raccourcis actions rapides |
| **AppSkeletonCard** | `lines` (nombre de lignes) | — | Skeleton loading card |
| **AppSkeletonKpi** | — | — | Skeleton loading KPI |
| **AppSkeletonTable** | `rows`, `cols` | — | Skeleton loading tableau |
| **StatusBadge** | `status` (statut RDV/OR) | — | Badge couleur statut (réservé, confirmé, terminé, etc.) |
| **SidebarLink** | `to`, `icon`, `label`, `section`, `badgeCount` | — | Lien sidebar actif selon route |
| **StatsCard** | `title`, `value`, `icon`, `color` | — | Carte stat KPI |
| **UTable** | `data`, `columns`, `loading` | — | Tableau sortable Nuxt UI |
| **MecanicienNav** | `v-model`, `sections` (tabs) | `@update:model-value` | Onglets mécanicien (Intervention, Essai, etc.) |
| **MecanicienSection** | `title`, `sectionKey`, `icon`, `defaultOpen` | — | Section repliable écran mécanicien |
| **MecanicienCheckupGrid** | `checkup`, `items` (liste points), `photos` | `@toggle` | Grille checkup 3-états (OK/NOK/N/A) |
| **MecanicienChrono** | `elapsed`, `total` (secondes), `status` | — | Affichage chrono interactif |
| **PlanningGrid** | `ponts`, `rdvs`, `horaires`, `mecaniciens`, `canDrag` | `@select-rdv`, `@move-rdv`, `@create-at`, `@dates-changed` | Grille drag-drop planning ponts × temps |
| **PlanningList** | `rdvs`, `mecaniciens`, `canCreate` | `@select-rdv`, `@action-transition`, `@create-request` | Vue list alternative planning |
| **NotificationPopIn** | — | — | Pop-in notifications temps réel (Mercure) |
| **VOCompanionCard** | `companion`, `generatedDocuments` | — | Carte Companion VO (QR + docs) |
| **VODossierMotoCard** | `mode` (purchase/depot), `dossierId`, `vehicule`, `documents`, `missingDocuments`, `legalChecklist`, `saleVerdict` | `@reload-detail` | Vue documents obligatoires VO |
| **VONav** | — | — | Nav VO sections (Rachats, Dépôts, Remises, etc.) |
| **VORemiseEnEtatCard** | `campaign`, `lines`, `pieces`, `margin` | — | Carte ligne FRE avec calcul marge |
| **WorkshopAlertBadges** | `v-model`, `counts` | `@update:model-value` | Filtres badges alertes workshop |
| **WorkshopQuickActions** | — | — | Actions rapides workshop (créer RDV, etc.) |
| **WorkshopSkeletonBays** | — | — | Skeleton ponts |
| **WorkshopVehicleTimeline** | `rdvs` | — | Timeline véhicule par pont |

---

---

## Middlewares

| Middleware | Type | But | Comportement |
|-----------|------|-----|-------------|
| **auth.global** | Global (avec `global` suffix) | Authentification + autorisation | Routes publiques : `/login`, `/public/*`, `/companion/*`. Routes privées : fetch user, vérif rôle, vérif access sections (admin/audit), fallback home si pas accès dashboard, chargement config atelier. Refresh auth J+5min si stale |
| **local-protocol** | Global | Support protocole personnalisé `atelier://` | Peut être interceptor deeplinks |

---

---

## Layouts

| Layout | Utilisation | Contenu |
|--------|-----------|---------|
| **default** | Pages authentifiées (planning, mecanicien, admin, etc.) | Sidebar (logo atelier, menu groupé, profil/logout en bas), Main area (topbar avec breadcrumbs/actions, main content), AppNotificationBell |
| **public** | Pages publiques (login, booking, companion, suivi, mentions légales) | Fond gradient obscur, footer liens légaux, pas de sidebar |

---

---

## Plugins

| Plugin | Type | But |
|--------|------|-----|
| **auth-heartbeat.client** | Client-side | Polling activité (pointerdown, keydown, scroll, touchstart). Refresh session auto toutes les 5min si actif + dans fenêtre visible. |

---

---

## Tests Vitest

### Tests Frontend Existants (19 tests)

| Test file | Suites | Tests | Objectif |
|-----------|--------|-------|----------|
| **useApi.test.ts** | 1 suite `useApi auth handling` | 5+ tests | Gestion 401 auto-refresh, retry après refresh, composant erreurs |
| **useCarteGriseOcr.test.ts** | 1 suite | 2+ tests | Sélection image OCR (filtre PDF), warning si PDF seul |
| **voCompanionDraftSync.test.ts** | 1 suite | 4+ tests | Sync champs brouillon (respect édits manuels, booleans), adoption entités sans conflit |
| **voRefurbishmentCard.test.ts** | 1 suite | 3+ tests | Sélection campagne FRE, construction formulaires lignes/pièces, format datetime-local |
| **voVehicleForm.test.ts** | 1 suite | 2+ tests | Validation VIN ISO 3779 (17 chars, pas I/O/Q), extraction category ID, build payload |
| **voStore.test.ts** | 1 suite `useVoStore` | 3+ tests | Création dépôt draft, PATCH finalization, endpoints FRE |

### Commandes de test

```bash
npm run test  # Vitest watch mode
npm run test:coverage  # Couverture
```

---

---

## Architecture Générale

### Flux d'authentification

1. **Login page** : Form email/pw ou SSO Google
2. `useAuth().login()` / `startGoogleLogin()` → POST `/auth/login` ou redirect Google OAuth
3. Response contient `user` + JWT stocké en cookie (include credentials)
4. `auth.global.ts` middleware : `fetchMe()` peuple `useAuthStore`
5. Polling refresh auth chaque 5 min si stale (plugin `auth-heartbeat.client`)

### Flux autorisation

- Checker `useAuth().hasSection('rdv')` avant d'afficher `/rdv`
- Vérif `hasPerm('facture.delete')` côté UI + côté API

### Flux notifications temps réel

- Connexion Mercure via `useNotifications().subscribeToMercure()`
- EventSource `/.well-known/mercure?topics=...` (souscription sélective par atelier, rôle)
- Fallback polling si WebSocket indisponible

### Flux VO (Rachat, Dépôt, FRE)

```
new.vue (wizard 4 étapes)
  ↓
createPurchase / createDepot (POST /vo/rachats ou /vo/depots)
  ↓
[QR code Companion PDA généré]
  ↓
/public/vo-companion/[token]
  (vendeur signe documents via PDA)
  ↓
rapport détail /vo/rachats/[id]
  (voir LP, verdict vendabilité, docs manquants)
  ↓
FRE via remises-en-etat/index
  (lignes prestations + pièces, marge recalculée)
  ↓
Vente / emission facture VO
```

### Flux Documents

- `useCarteGriseOcr` : OCR CG en local (tesseract.js) + API backend fallback
- `usePdfDownload` : Fetch PDF avec credentials inclus (pas window.open simple)
- `voRefurbishmentCard` : Gestion formulaires FRE dynamiques

---

## Conventions de code

- **Script setup** : Tous les `.vue` utilisent `<script setup lang="ts">`
- **Composition API** : `ref`, `computed`, `watch`, `onMounted`
- **Typage** : TypeScript strict, interfaces explicites
- **Naming** : camelCase fonctions/vars, PascalCase composants
- **Formatage** : Template 2-space indent, script 2-space indent
- **Stores** : `defineStore('app', { state, getters, actions })`
- **API** : Toujours passer par `useApi()`, jamais `fetch()` direct

---

Fin du rapport exhaustif.