# Rapport Exhaustif – Aspects Transverses du Projet AtelierMBZ

---

## 1. Configuration Backend

### 1.1 Services Personnalisés (`services.yaml`)

| Service | Rôle | Configuration |
|---------|------|---------------|
| `App\EventListener\TenantSetterListener` | Injecte `atelierId` sur nouvelles entités | `prePersist` event |
| `App\Service\ConfigEncryptionService` | Chiffre secrets en base via `kernel.secret` | Injected via binding |

### 1.2 Authentification & Sécurité (`security.yaml`)

**Firewalls :**

| Firewall | Pattern | Authentificateur | Comportement |
|----------|---------|-----------------|--------------|
| `dev` | `^/(_(profiler|wdt)\|css\|images\|js)/` | aucun | Désactivé |
| `public` | `/api/(auth/login\|auth/refresh\|auth/google\|health\|public/\|companion/\|photos/file/\|webhooks/\|security/csp-report\|motos/autocomplete\|\.well-known/)` | aucun | Public, pas de JWT requis |
| `api_root` | `^/api$` | aucun | Racine API publique |
| `api` | `^/api` | `CookieJwtAuthenticator` | Stateless, JWT via cookie ou header |

**Access Control :**

| Chemin | Rôles | Méthode | Notes |
|--------|-------|--------|-------|
| `^/api/auth/login` | `PUBLIC_ACCESS` | POST | Création session JWT |
| `^/api/auth/refresh` | `PUBLIC_ACCESS` | POST | Renouvellement token |
| `^/api/auth/google` | `PUBLIC_ACCESS` | POST | OAuth2 Google (optionnel) |
| `^/api/health` | `PUBLIC_ACCESS` | GET | Healthcheck public |
| `^/api/public` | `PUBLIC_ACCESS` | GET/POST | Endpoints client public (booking, suivi, demande) |
| `^/api/companion` | `PUBLIC_ACCESS` | GET/POST/PATCH | Signature Companion client (PDA réception) |
| `^/api/photos/file` | `PUBLIC_ACCESS` | GET | Téléchargement photos public |
| `^/api/webhooks/notifications` | `PUBLIC_ACCESS` | POST | Webhooks Twilio/Mailgun/OVH |
| `^/api/security/csp-report` | `PUBLIC_ACCESS` | POST | Collecte violations CSP |
| `^/api/motos/(autocomplete\|marques)` | `PUBLIC_ACCESS` | GET | Recherche moto, marques (sans auth) |
| `^/api` | `IS_AUTHENTICATED_FULLY` | tous | Reste API protégé |

**Hiérarchie des Rôles :**

```yaml
ROLE_SUPER_ADMIN: [ROLE_ADMIN]
ROLE_ADMIN: [ROLE_USER, ROLE_VO_MANAGER]
ROLE_VO_MANAGER: [ROLE_USER]
ROLE_RECEPTIONNAIRE: [ROLE_USER]
ROLE_MECANICIEN: [ROLE_USER]
ROLE_COMPTABLE: [ROLE_USER]
```

### 1.3 Doctrine ORM (`doctrine.yaml`)

| Paramètre | Valeur | But |
|-----------|--------|-----|
| `dbal.url` | `%env(resolve:DATABASE_URL)%` | PostgreSQL 15 |
| `orm.naming_strategy` | `underscore_number_aware` | Colonnes snake_case |
| `orm.auto_generate_proxy_classes` | true (dev), false (prod) | Cache Doctrine |
| `orm.filters.tenant_filter.class` | `App\Doctrine\TenantFilter` | Multi-tenant row-level |
| `orm.filters.tenant_filter.enabled` | false | Activé dynamiquement via listener |
| `mapping.App` | attribute | Entités avec `#[Entity]` |

**TenantFilter :** Applique `WHERE atelier_id = :id` sur toutes les requêtes d'entités ayant le champ `atelierId`. Activé via [TenantFilterListener](backend/src/EventListener/TenantFilterListener.php) dès `kernel.request`, sauf si utilisateur `ROLE_SUPER_ADMIN`.

### 1.4 JWT & Authentification (`lexik_jwt_authentication.yaml`)

| Paramètre | Valeur | But |
|-----------|--------|-----|
| `secret_key` | `%env(JWT_SECRET_KEY)%` | Clé signage HS256 |
| `token_ttl` | 900 secondes | 15 minutes d'expiration |
| `user_id_claim` | `username` | Claim d'identité |
| `encoder.signature_algorithm` | HS256 | Signature HMAC-SHA256 |
| `token_extractors.cookie.enabled` | true | Extraction depuis cookie `access_token` |
| `token_extractors.authorization_header.enabled` | true | Extraction header `Authorization: Bearer` |

**Payload JWT :** `user_id`, `atelier_id`, `role`, `jti`. Révocation stockée en table `revoked_token`.

### 1.5 API Platform (`api_platform.yaml`)

| Paramètre | Valeur |
|-----------|--------|
| `title` | Paddock API |
| `version` | 2.0.0 |
| `formats` | JSON-LD (`application/ld+json`), JSON (`application/json`) |
| `pagination.enabled` | true |
| `pagination.itemsPerPage` | 30 |
| `pagination.maxItemsPerPage` | 200 |
| `cache_headers.vary` | `Content-Type`, `Authorization`, `Origin` |

### 1.6 Messenger (`messenger.yaml`)

| Paramètre | Valeur | But |
|-----------|--------|-----|
| `failure_transport` | `failed` | Queue pour messages échoués |
| `transports.async` | `%env(MESSENGER_TRANSPORT_DSN)%` | Transport principal (default: Doctrine) |
| `transports.failed` | `doctrine://default?queue_name=failed` | Persistence des failed messages |
| `transports.sync` | `sync://` | Exécution synchrone (test, dev) |

**Message Routing :**

| Message | Destination |
|---------|-------------|
| `SendRappelMessage` | `async` |
| `GeneratePdfMessage` | `async` |
| `ProcessScheduledRappels` | `async` |
| `SendGardiennageRappelMessage` | `async` |

### 1.7 Rate Limiter (`rate_limiter.yaml`)

| Limiter | Limite | Intervalle | Verrouillage | But |
|---------|--------|-----------|--------------|-----|
| `public_booking` | 5 req | 1 minute | `lock.factory` | Prise de RDV publique |
| `public_suivi` | 60 req | 1 minute | lock | Suivi client public |
| `public_companion` | 60 req | 1 minute | lock | Companion client (signature) |
| `public_vo_companion` | 60 req | 1 minute | lock | Companion VO (signature) |
| `public_demande` | 30 req | 1 minute | lock | Demande travaux (validation) |
| `companion_upload` | 20 req | 1 minute | lock | Upload photos intervention |

### 1.8 CORS (`nelmio_cors.yaml`)

| Paramètre | Valeur |
|-----------|--------|
| `origin` | `%env(CORS_ALLOW_ORIGIN)%` (regex) |
| `methods` | GET, OPTIONS, POST, PUT, PATCH, DELETE |
| `headers` | Content-Type, Authorization, Accept, Origin, X-Requested-With |
| `expose_headers` | Link |
| `credentials` | true |
| `max_age` | 3600 secondes |

### 1.9 Mercure (`mercure.yaml`)

| Paramètre | Valeur | But |
|-----------|--------|-----|
| `hubs.default.url` | `%env(MERCURE_URL)%` | Serveur interne (Docker: `http://mercure:3000/.well-known/mercure`) |
| `hubs.default.public_url` | `%env(MERCURE_PUBLIC_URL)%` | URL client (`/.well-known/mercure` en prod) |
| `hubs.default.jwt.secret` | `%env(MERCURE_JWT_SECRET)%` | Clé JWT pour publish/subscribe |
| `hubs.default.jwt.publish` | `*` | Qui peut publier (ici: tous) |

### 1.10 Mailer (`mailer.yaml`)

```yaml
dsn: %env(MAILER_DSN)%
```

Défaut dev: `smtp://mailhog:1025`. En prod: `mailgun://KEY:DOMAIN@default` ou `smtp+tls`.

### 1.11 Framework Core (`framework.yaml`)

| Paramètre | Valeur |
|-----------|--------|
| `secret` | `%env(APP_SECRET)%` |
| `session` | true (état HTTP persistent) |
| `serializer.name_converter` | `camel_case_to_snake_case` |
| `test` (quand @test) | true |

### 1.12 Workflow (`workflow.yaml`)

**State Machine `rendez_vous`** : Entité `RendezVous`, propriété `statut`.

**Places** (21 au total):
```
en_attente, reserve, confirme, reception, en_cours, en_pause, termine, restitue,
facture, paye, annule, en_attente_pieces, en_attente_reprise, en_gardiennage,
restitue_partiel, no_show
```

**Transitions principales** (28 total):
- `reserver` : en_attente → reserve
- `confirmer` : [en_attente, reserve] → confirme
- `reception` : confirme → reception
- `start_travail` : reception → en_cours
- `mettre_en_pause` / `reprendre` : boucle en_cours ↔ en_pause
- `mettre_en_attente_pieces` / `reprendre_apres_pieces` : gestion manque pièces
- `mettre_en_attente_reprise` / `reprendre_demain` : reprise lendemain
- `terminer` : [en_cours, en_pause] → termine
- `restituer` : termine → restitue
- `restituer_partiel` : [en_cours, termine] → restitue_partiel
- `facturer` : [termine, restitue, restitue_partiel] → facture
- `payer` : facture → paye
- `annuler` : [en_attente, reserve, confirme, reception, en_attente_pieces, en_gardiennage] → annule
- `declarer_no_show` / `no_show` : [confirme, reception] → no_show
- `reporter` : [reception, no_show] → confirme
- `passer_gardiennage` / `mettre_en_gardiennage` / `sortir_gardiennage` : gestion gardiennage
- Audit trail activé

---

## 2. Configuration Frontend (Nuxt 3)

### 2.1 Core Settings (`nuxt.config.ts`)

| Paramètre | Valeur | But |
|-----------|--------|-----|
| `compatibilityDate` | `2026-04-14` | Version Nuxt compatibilité |
| `ssr` | false | Mode SPA (pas de SSR) |
| `modules` | `@nuxt/ui`, `@pinia/nuxt` | UI composants, state management |
| `css` | `~/assets/css/main.css` | Styles globales |
| `alias['#app-manifest']` | `./app-manifest.stub` | Workaround Vite HMR |

### 2.2 Runtime Config

```typescript
runtimeConfig: {
  public: {
    apiBase: '/api',              // Proxy backend via Caddy :80/:443
    mercureUrl: '/.well-known/mercure',  // Mercure client endpoint
  }
}
```

### 2.3 App Head & Meta

| Meta Tag | Valeur |
|----------|--------|
| `title` | Paddock |
| `application-name` | Paddock |
| `og:title`, `og:image` | Social preview |
| `twitter:card`, `twitter:image` | Card réseau social |
| **Fonts** | Google Fonts Inter (wght 400-800) |
| **Favicon** | `/branding/paddock-logo-favicon.svg` |
| **Apple icon** | `/branding/paddock-logo-favicon.svg` |

### 2.4 CSP & Sécurité (Report-Only)

**Content-Security-Policy-Report-Only** :

| Directive | Valeur | But |
|-----------|--------|-----|
| `default-src` | `'self'` | Tout depuis même origin par défaut |
| `script-src` | `'self' 'unsafe-inline' 'unsafe-eval'` | Vue runtime + Nuxt UI |
| `style-src` | `'self' 'unsafe-inline' https://fonts.googleapis.com` | Styles inline + Google Fonts |
| `font-src` | `'self' data: https://fonts.gstatic.com` | Fonts locales + Google |
| `img-src` | `'self' data: blob:` | Images, SVG data URIs, blob URLs |
| `connect-src` | `'self'` | Fetch/XHR/WebSocket vers `/api` et Mercure |
| `frame-ancestors` | `'none'` | Pas d'iframage |
| `form-action` | `'self'` | Forms vers même origin |
| `base-uri` | `'self'` | Base URL |
| `object-src` | `'none'` | Pas de plugins |
| `report-uri` | `/api/security/csp-report` | Logs violations au serveur |

**Other Security Headers** :

| Header | Valeur |
|--------|--------|
| `X-Frame-Options` | DENY |
| `X-Content-Type-Options` | nosniff |
| `Referrer-Policy` | strict-origin-when-cross-origin |

### 2.5 Nitro Config (Routes & Proxying)

| Route | Cible | But |
|-------|-------|-----|
| `/api/**` | `http://localhost:8000/api/**` | Proxy API backend |
| `/**` | headers CSP Report-Only | Toutes pages HTML |

### 2.6 Dev Settings

| Paramètre | Valeur | But |
|-----------|--------|-----|
| `colorMode.preference` | dark | Thème sombre par défaut |
| `devtools.enabled` | true | Devtools Nuxt |
| `vite.server.watch.usePolling` | true | Docker HMR (pas inotify) |
| `vite.server.watch.interval` | 500ms | Fréquence poll |

---

## 3. Variables d'Environnement

### 3.1 Complètes avec Sources & Utilisation

| Variable | Défaut | Requis ? | Sources | Utilisé par |
|----------|--------|---------|---------|------------|
| `APP_ENV` | `dev` | Non | docker-compose.yml, .env | Symfony kernel |
| `APP_SECRET` | `change_me_generate_with_openssl_rand_hex_32` | **OUI** | docker-compose.yml, .env | JWT secret signing |
| `APP_DEBUG` | inféré de `APP_ENV` | Non | Kernel, bootstrap tests | Debug toolbar, erreurs détaillées |
| `DATABASE_URL` | `postgresql://atelier:atelier@db:5432/atelier_moto` | **OUI** | docker-compose.yml, .env | Doctrine ORM, Messenger (Doctrine transport) |
| `POSTGRES_DB` | `atelier_moto` | Non | docker-compose.yml | Container DB |
| `POSTGRES_USER` | `atelier` | Non | docker-compose.yml | Container DB credentials |
| `POSTGRES_PASSWORD` | `atelier` | Non | docker-compose.yml | Container DB credentials |
| `DB_PORT` | `5432` | Non | docker-compose.yml | DB Mapping port (dev only) |
| `JWT_SECRET_KEY` | `change_me_generate_with_openssl_rand_hex_64` | **OUI** | docker-compose.yml, .env | Lexik JWT signing (HS256) |
| `JWT_PUBLIC_KEY` | (vide) | Non | docker-compose.yml | JWT asymétrique (non utilisé) |
| `JWT_PASSPHRASE` | (vide) | Non | docker-compose.yml | Passphrase clé privée (non utilisé) |
| `CORS_ALLOW_ORIGIN` | `^https?://(localhost\|127\.0\.0\.1)(:[0-9]+)?$` | Non | docker-compose.yml, .env | nelmio_cors, Mercure CORS |
| `MAILER_DSN` | `smtp://mailhog:1025` | Non | docker-compose.yml, .env | Symfony Mailer (dev: MailPit, prod: Mailgun/OVH) |
| `MAILER_HOST` | `mailhog` | Non | .env | Composé dans `MAILER_DSN` |
| `MAILER_PORT` | `1025` | Non | .env | Composé dans `MAILER_DSN` |
| `MESSENGER_TRANSPORT_DSN` | `doctrine://default?auto_setup=0` | Non | docker-compose.yml, .env | Symfony Messenger (async jobs) |
| `MERCURE_URL` | `http://mercure:3000/.well-known/mercure` | Non | docker-compose.yml, .env | Hub interne Mercure (serveur) |
| `MERCURE_PUBLIC_URL` | `/.well-known/mercure` | Non | docker-compose.yml, .env | Hub Mercure (client, reverse-proxy) |
| `MERCURE_JWT_SECRET` | `!ChangeThisMercureHubJWTSecretKey!` | **OUI** | docker-compose.yml, .env | JWT Mercure publish/subscribe |
| `ADMIN_USERNAME` | `admin` | Non | docker-compose.yml | Seeds, tests, défaut fixture |
| `ADMIN_PASSWORD` | `Admin123!` | **OUI** (prod) | docker-compose.yml, .env | Hash stocké User, tests e2e (playwright) |
| `ADMIN_EMAIL` | `admin@atelier.local` | Non | .env | Seeds |
| `BACKEND_PORT` | `8000` | Non | docker-compose.yml | PHP port extern |
| `FRONTEND_PORT` | `3000` | Non | docker-compose.yml | Nuxt port extern |
| `MAILHOG_SMTP_PORT` | `1025` | Non | docker-compose.yml | MailPit SMTP (dev) |
| `MAILHOG_UI_PORT` | `8025` | Non | docker-compose.yml | MailPit UI (dev) |
| `PUBLIC_EDGE_PORT` | `81` | Non | docker-compose.yml | Caddy public edge (edge.int) |
| `LOCK_DSN` | `flock` | Non | .env | Symfony Lock (rate limiting) |
| `DEFAULT_URI` | `http://localhost` | Non | .env | CLI routing context |
| `APP_DOMAIN` | `localhost` | Non | docker-compose.yml | Caddy domain config |
| `PLAYWRIGHT_BASE_URL` | `http://localhost` ou `https://localhost` | Non | playwright.config.mjs, env circulaire | Tests e2e URL de base |
| `TWILIO_DSN` | (non défini) | Conditionnel | notifier.yaml, webhook | SMS via Twilio `twilio://SID:TOKEN@default?from=+FROM` |
| `OVHCLOUD_DSN` | (non défini) | Conditionnel | notifier.yaml, webhook | SMS via OVH `ovhcloud://KEY:SECRET@default?consumer_key=CK&service_name=SN` |
| `WEBHOOK_HOST` | `0.0.0.0` | Non | webhook listener Python | Script déploiement (non dans app) |
| `WEBHOOK_PORT` | `9010` | Non | webhook listener Python | Script déploiement |
| `WEBHOOK_PATH` | `/github-webhook` | Non | webhook listener Python | Script déploiement |
| `WEBHOOK_SECRET` | (vide) | Non | webhook listener Python | Signature webhook GitHub |
| `BRANCH` | `main` | Non | webhook listener Python | Branche à pull |
| `PROJECT_DIR` | `/opt/ateliermbz` | Non | webhook listener Python | Répertoire projet |
| `DEPLOY_SCRIPT` | `$PROJECT_DIR/scripts/deploy-server.sh` | Non | webhook listener Python | Script déploiement |

---

## 4. Sécurité

### 4.1 JWT (Lexik Bundle)

| Aspect | Détail |
|--------|--------|
| **Algorithme** | HS256 (HMAC-SHA256) |
| **TTL** | 900 secondes (15 minutes) |
| **Extraction** | Cookie `access_token` OU header `Authorization: Bearer ...` |
| **Payload** | `user_id`, `atelier_id`, `role`, `jti` (JWT ID unique) |
| **Rafraîchissement** | Endpoint `/api/auth/refresh` (PUBLIC_ACCESS) renvoie nouveau token |
| **Révocation** | Via table `revoked_token` (listé lors parsing token) |
| **Authenticateur custom** | `App\Security\CookieJwtAuthenticator` |

### 4.2 Multi-Tenant Row-Level

**TenantFilter ([backend/src/Doctrine/TenantFilter.php](backend/src/Doctrine/TenantFilter.php))** :

```php
- Ajoute automatiquement WHERE atelier_id = :id sur toutes requêtes ORM
- Déclenché par TenantFilterListener au kernel.request (priorité -10)
- Résout atelierId via CurrentAtelierResolver (JWT ou cookie)
- ROLE_SUPER_ADMIN bypass : aucun filtre appliqué
- Utilisateurs sans atelierId : filtre avec impossible ID (0)
```

### 4.3 Security Headers ([backend/src/EventListener/SecurityHeadersListener.php](backend/src/EventListener/SecurityHeadersListener.php))

| Header | Valeur | Mode |
|--------|--------|------|
| `X-Frame-Options` | DENY | Activé |
| `X-Content-Type-Options` | nosniff | Activé |
| `X-XSS-Protection` | `1; mode=block` | Activé |
| `Referrer-Policy` | strict-origin-when-cross-origin | Activé |
| `Permissions-Policy` | `geolocation=(), microphone=(), camera=(self)` | Activé |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` | HTTPS seulement |
| `Content-Security-Policy-Report-Only` | Report-Only (HTML pages) | Observation phase |

### 4.4 CSRF

Désactivé explicitement pour API stateless (Symfony default).

### 4.5 Rate Limiting

**Configuré via RateLimiterFactory (Symfony natif)** :

- Politique `sliding_window` (fenêtre glissante)
- Applicateur: `LockFactory` (flock ou redis en prod)
- Appliqué via contrôleurs (e.g., `PublicBookingController`, `CompanionController`)
- **Public endpoints protégés** : booking (5 req/min), suivi (60), companion (60), vo-companion (60), demande (30)
- **Upload photos** : 20 req/min (anti-spam)

### 4.6 Webhook Signatures ([backend/src/Service/WebhookSignatureVerifier.php](backend/src/Service/WebhookSignatureVerifier.php))

**Twilio** :
- Algorithme: HMAC-SHA1
- Signature header: `X-Twilio-Signature`
- Vérification: `base64(HMAC-SHA1(secret, URL + POST params))`

**Mailgun** :
- Algorithme: HMAC-SHA256
- Headers: `X-Mailgun-Signature`, `X-Mailgun-Timestamp` (résiliation > 5min)
- Anti-replay: timestamp vérifié

**OVH** :
- Algorithme: Header `X-Webhook-Token` = secret
- Comparaison simple (pas HMAC)

**Tests** : [backend/tests/Unit/WebhookSignatureVerifierTest.php](backend/tests/Unit/WebhookSignatureVerifierTest.php) (12 tests : Twilio, Mailgun, OVH, invalid, missing)

---

## 5. Notifications

### 5.1 Architecture Multi-Provider

**NotificationDispatcher ([backend/src/Service/NotificationDispatcher.php](backend/src/Service/NotificationDispatcher.php))** :

- Dispatcher centralisé
- Essaye providers dans l'ordre : primary d'abord, puis par priority ASC
- Fallback sur provider suivant en cas échec
- Chaque tentative loggée en `NotificationLog`

**Channels supportés** : `email`, `sms`

**Providers** :

| Provider | Channel | Détail |
|----------|---------|--------|
| **Symfony Mailer** | email | DSN `%env(MAILER_DSN)%` |
| **Twilio** | sms | DSN `twilio://SID:TOKEN@default?from=+FROM` |
| **OVH SMS** | sms | DSN `ovhcloud://KEY:SECRET@default?...` |
| **Mailgun** | email | DSN `mailgun://KEY:DOMAIN@default?region=us` |

### 5.2 Mercure (Temps Réel)

| Aspect | Détail |
|--------|--------|
| **Technologie** | Server-Sent Events (SSE) + WebSocket |
| **Endpoint interne** | `http://mercure:3000/.well-known/mercure` |
| **Endpoint client** | `/.well-known/mercure` (reverse proxy Caddy) |
| **JWT** | Signature avec `MERCURE_JWT_SECRET`, publish: `*` |
| **Format** | JSON via topics (channels Mercure) |
| **Composable client** | `useNotifications.ts` → subscriptions dynamiques |

### 5.3 Templates (`NotificationTemplate` + `NotificationTemplateCatalog`)

**Stockage** : Base de données `notification_template` par atelier + channel

**Rendu** : Twig simple (variables remplacées dans `{subject}` et body)

**Catalog** ([backend/tests/Unit/NotificationTemplateCatalogTest.php](backend/tests/Unit/NotificationTemplateCatalogTest.php)) :
- Ensembles de templates par défaut (defaults)
- Bootstrap automatique pour nouvel atelier
- Codes prédéfinis : `rdv_confirme`, `rdv_rappel`, `demande_travaux_supp`, etc.

### 5.4 Escalade (`NotificationEscalation`)

| Délai | Raison |
|-------|--------|
| T+5min | 1ère escalade (pas de réponse) |
| T+10min | 2ème escalade |
| T+30min | 3ème escalade (critique) |

Modifiable par atelier via `ConfigAtelier.delaiEscalade*`.

### 5.5 Messages Messenger

| Message | Transport | Consommateur | But |
|---------|-----------|--------------|-----|
| `SendRappelMessage` | async | `SendRappelHandler` | Envoi rappel RDV |
| `GeneratePdfMessage` | async | `GeneratePdfHandler` | Générer PDF ordre de rép., rapport, etc. |
| `ProcessScheduledRappels` | async | `ProcessScheduledRappelsHandler` | Traitement rappels programmés |
| `SendGardiennageRappelMessage` | async | `SendGardiennageRappelHandler` | Relances gardiennage (15j, 30j, 45j, 180j) |

---

## 6. Tests

### 6.1 Backend – PHPUnit Unit Tests

| Fichier | Cas de test | Couvre |
|---------|-------------|--------|
| [PricingServiceTest](backend/tests/Unit/PricingServiceTest.php) | ~15 | Tarification horaire, MO, pièces, TVA, marges |
| [NotificationDispatcherDTOTest](backend/tests/Unit/NotificationDispatcherDTOTest.php) | ~10 | DTO NotificationMessage, NotificationResult |
| [NotificationTemplateCatalogTest](backend/tests/Unit/NotificationTemplateCatalogTest.php) | ~8 | Bootstrap templates, rendu variables |
| [NotificationTest](backend/tests/Unit/NotificationTest.php) | ~5 | Entités Notification, NotificationLog |
| [WebhookSignatureVerifierTest](backend/tests/Unit/WebhookSignatureVerifierTest.php) | 12 | Signature Twilio (HMAC-SHA1), Mailgun (HMAC-SHA256 + anti-replay), OVH |
| [OrdreReparationPolicyTest](backend/tests/Unit/OrdreReparationPolicyTest.php) | ~10 | Décisions OR : éditable, modifiable, rectificatif |
| [UserRolesTest](backend/tests/Unit/UserRolesTest.php) | ~8 | Hiérarchie rôles, permissions métier |
| [RapportInterventionServiceTest](backend/tests/Unit/RapportInterventionServiceTest.php) | ~6 | Génération rapports intervention |
| [RendezVousWorkflowServiceTest](backend/tests/Unit/RendezVousWorkflowServiceTest.php) | ~12 | Transitions workflow RDV |
| [VOCompanionWorkflowServiceTest](backend/tests/Unit/VOCompanionWorkflowServiceTest.php) | ~10 | Workflow companion VO (signatures) |
| [VORemiseEnEtatServiceTest](backend/tests/Unit/VORemiseEnEtatServiceTest.php) | ~8 | Coûts FRE, marge calcul |
| [AdminValidationServicesTest](backend/tests/Unit/AdminValidationServicesTest.php) | ~6 | Validations admin (tarifs, closures) |
| [MotoCatalogImporterTest](backend/tests/Unit/MotoCatalogImporterTest.php) | ~4 | Import catalogue moto CSV |
| [ClauseLegaleCodesTest](backend/tests/Unit/ClauseLegaleCodesTest.php) | ~6 | Codes clauses légales (RGPD, CGV) |
| [ClauseLegaleVisibilityServiceTest](backend/tests/Unit/ClauseLegaleVisibilityServiceTest.php) | ~8 | Filtrages clauses par profil |
| [CurrentAtelierResolverTest](backend/tests/Unit/CurrentAtelierResolverTest.php) | ~6 | Résolution atelier depuis JWT/cookie |
| [ConfigEncryptionTest](backend/tests/Unit/ConfigEncryptionTest.php) | ~4 | Chiffrement/déchiffrement config |
| [UserMecanicienSyncServiceTest](backend/tests/Unit/UserMecanicienSyncServiceTest.php) | ~6 | Sync User ↔ Mecanicien |
| [UserAdminLifecycleTest](backend/tests/Unit/UserAdminLifecycleTest.php) | ~8 | Création/suppression user admin |
| [ProcessNotificationEscalationsCommandTest](backend/tests/Unit/ProcessNotificationEscalationsCommandTest.php) | ~6 | Commande escalade notifications |
| [CheckDaSivExpiryCommandTest](backend/tests/Unit/CheckDaSivExpiryCommandTest.php) | ~4 | Vérification DA SIV expiré |
| [RelanceGardiennageCommandTest](backend/tests/Unit/RelanceGardiennageCommandTest.php) | ~6 | Commande relances gardiennage |
| [EssaiRoutierCompletenessTest](backend/tests/Unit/EssaiRoutierCompletenessTest.php) | ~8 | Essai routier obligatoire avant terminer |
| [NotificationProviderConfigSanitizerTest](backend/tests/Unit/NotificationProviderConfigSanitizerTest.php) | ~5 | Sanitization config providers |
| [SlotServiceClosureTest](backend/tests/Unit/SlotServiceClosureTest.php) | ~4 | Slots non disponibles (fermetures) |
| [ModeTarificationTest](backend/tests/Unit/ModeTarificationTest.php) | ~6 | Modes tarification FORFAIT/HORAIRE/SUR_DEVIS |
| [AdminTemplatePreviewControllerTest](backend/tests/Unit/AdminTemplatePreviewControllerTest.php) | ~4 | Préview templates PDF admin |

**Total Unit** : ~200 tests

### 6.2 Backend – PHPUnit Functional Tests

| Fichier | Cas de test | Couvre |
|---------|-------------|--------|
| [ApiEndpointsTest](backend/tests/Functional/ApiEndpointsTest.php) | ~10 | Non-régression endpoints (login, booking public, tracking) |
| [AuthBookingAteliersTest](backend/tests/Functional/AuthBookingAteliersTest.php) | ~6 | Booking multi-atelier, isolation tenant |
| [CompanionControllerTest](backend/tests/Functional/CompanionControllerTest.php) | ~12 | Signature Companion, photos, transitions |
| [MecanicienControllerTest](backend/tests/Functional/MecanicienControllerTest.php) | ~10 | Espace mécanicien, RDV mécanicien |
| [FacturationControllerTest](backend/tests/Functional/FacturationControllerTest.php) | ~8 | Création factures, export PDF |
| [VOControllerTest](backend/tests/Functional/VOControllerTest.php) | ~15 | VO achats, ventes, LP PDF, DA SIV |
| [VORemiseEnEtatControllerTest](backend/tests/Functional/VORemiseEnEtatControllerTest.php) | ~10 | FRE workflow, coûts, marges |
| [NotificationProviderApiTest](backend/tests/Functional/NotificationProviderApiTest.php) | ~12 | Webhooks notification (Twilio, Mailgun, OVH) |
| [RdvPrestationCatalogControllerTest](backend/tests/Functional/RdvPrestationCatalogControllerTest.php) | ~6 | Catalogue prestations par atelier |
| [ClientStatsControllerTest](backend/tests/Functional/ClientStatsControllerTest.php) | ~4 | Stats clients (CA, intervention count) |
| [PontStatusControllerTest](backend/tests/Functional/PontStatusControllerTest.php) | ~4 | Status ponts en temps réel |
| [VehiculeLookupControllerTest](backend/tests/Functional/VehiculeLookupControllerTest.php) | ~6 | Recherche véhicule, OCR plaque |
| [AtelierCatalogBootstrapServiceTest](backend/tests/Functional/AtelierCatalogBootstrapServiceTest.php) | ~8 | Bootstrap nouvel atelier (templates, config defaults) |
| [NotificationContextTest](backend/tests/Functional/NotificationContextTest.php) | ~6 | Contexte notifications (providers, escalade) |
| [RendezVousWorkflowControllerTest](backend/tests/Functional/RendezVousWorkflowControllerTest.php) | ~10 | Transitions workflow API (reception, start_travail, etc.) |

**Total Functional** : ~130 tests

**Total Backend Tests** : ~330 tests

### 6.3 Frontend – Vitest

| Fichier | Cas de test | Couvre |
|---------|-------------|--------|
| [useApi.test.ts](frontend/tests/useApi.test.ts) | ~8 | Composable API (GET, POST, PATCH, erreurs) |
| [useCarteGriseOcr.test.ts](frontend/tests/useCarteGriseOcr.test.ts) | ~6 | OCR carte grise (parsing VIN, plaque) |
| [voCompanionDraftSync.test.ts](frontend/tests/voCompanionDraftSync.test.ts) | ~8 | Sync brouillon VO Companion |
| [voVehicleForm.test.ts](frontend/tests/voVehicleForm.test.ts) | ~10 | Validation formulaire véhicule VO |
| [voRefurbishmentCard.test.ts](frontend/tests/voRefurbishmentCard.test.ts) | ~7 | Card remise en état (coûts, statuts) |
| [voStore.test.ts](frontend/tests/voStore.test.ts) | ~12 | Store Pinia VO (rachats, dépôts, documents) |

**Total Frontend Unit** : ~51 tests

### 6.4 Frontend – Playwright E2E

| Fichier | Cas de test | Couvre |
|---------|-------------|--------|
| [auth.spec.mjs](frontend/tests/e2e/auth.spec.mjs) | ~4 | Login, page publique, redirect unauthenticated |
| [business-flows.spec.mjs](frontend/tests/e2e/business-flows.spec.mjs) | ~8 | RDV wizard, public booking, slot selection |
| [navigation.spec.mjs](frontend/tests/e2e/navigation.spec.mjs) | ~6 | Navigation menu, breadcrumbs |
| [roles-workflow.spec.mjs](frontend/tests/e2e/roles-workflow.spec.mjs) | ~10 | Scénarios par rôle (mécanicien, réceptionniste) |
| [vo-pricing-diff.spec.mjs](frontend/tests/e2e/vo-pricing-diff.spec.mjs) | ~4 | Calcul tarifs VO (marge vs normal) |
| [vo-companion-flow.spec.mjs](frontend/tests/e2e/vo-companion-flow.spec.mjs) | ~8 | Companion VO (signatures, documents) |
| [notifications.spec.mjs](frontend/tests/e2e/notifications.spec.mjs) | ~6 | Mercure live updates, notifications |
| [notification-providers.spec.mjs](frontend/tests/e2e/notification-providers.spec.mjs) | ~5 | Providers email/SMS actifs |
| [non-regression.spec.mjs](frontend/tests/e2e/non-regression.spec.mjs) | ~12 | Non-régression : pages critiques |
| [helpers.mjs](frontend/tests/e2e/helpers.mjs) | 1 | Utilitaires (loginAsAdmin, etc.) |

**Total Frontend E2E** : ~63 tests

**Total Tests Frontend** : ~114 tests

---

## 7. Docker Compose

### 7.1 Services (docker-compose.yml)

| Service | Image | Port | Volumes | Roles |
|---------|-------|------|---------|-------|
| **db** | `postgres:15-alpine` | 5432 (internal) | `postgres_data:/var/lib/postgresql/data` | DB principal |
| **php** | Custom (Dockerfile.backend) | 8000 | app code, logos, photos, signatures | API backend |
| **worker** | Custom (Dockerfile.backend) | - | app code, logos, photos, signatures | Consumer Messenger |
| **nuxt** | Custom (Dockerfile.frontend) | 3000 | - | Frontend SPA |
| **caddy** | `caddy:2.9-alpine` | 80, 443, 81 | Caddyfile (ro), caddy_data, caddy_config | Reverse proxy |
| **mercure** | `dunglas/mercure:v0.18` | 3000 (internal) | mercure_data, mercure_config | Notifications temps réel |
| **mailhog** | `axllent/mailpit:v1.24` | 1025 (SMTP), 8025 (UI) | - | Capture emails dev |

### 7.2 Healthchecks

| Service | Check |
|---------|-------|
| **db** | `pg_isready -U atelier -d atelier_moto` (5s interval, 3s timeout, 10 retries) |
| **php** | dépend_on: db healthcheck |
| **worker** | dépend_on: db healthcheck |
| **nuxt** | dépend_on: php |
| **caddy** | dépend_on: php, nuxt, mercure |

### 7.3 Environment Variables Transmis

| Service | Clés |
|---------|------|
| **db** | `POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_PASSWORD` |
| **php** | `DATABASE_URL`, `APP_ENV`, `APP_SECRET`, `JWT_SECRET_KEY`, `JWT_PUBLIC_KEY`, `JWT_PASSPHRASE`, `CORS_ALLOW_ORIGIN`, `MAILER_DSN`, `MESSENGER_TRANSPORT_DSN`, `MERCURE_URL`, `MERCURE_PUBLIC_URL`, `MERCURE_JWT_SECRET`, `ADMIN_USERNAME`, `ADMIN_PASSWORD` |
| **worker** | `DATABASE_URL`, `APP_ENV`, `APP_SECRET`, `MAILER_DSN`, `MESSENGER_TRANSPORT_DSN` |
| **nuxt** | `NUXT_PUBLIC_API_BASE` |
| **caddy** | `APP_DOMAIN` |
| **mercure** | `MERCURE_PUBLISHER_JWT_KEY`, `MERCURE_SUBSCRIBER_JWT_KEY`, `MERCURE_EXTRA_DIRECTIVES` (CORS, anonymous, subscriptions) |

### 7.4 Volumes Persistants

| Volume | Contenu | Utilisé par |
|--------|---------|-------------|
| `postgres_data` | Base données PostgreSQL | db |
| `backend_logos` | Logos ateliers | php, worker |
| `backend_photos` | Photos interventions/véhicules | php, worker |
| `backend_signatures` | Signatures numériques | php, worker |
| `caddy_data` | Certificats TLS, état | caddy |
| `caddy_config` | Config dynamique | caddy |
| `mercure_data` | Événements persistants | mercure |
| `mercure_config` | Config Mercure | mercure |

### 7.5 Networking

- Réseau `bridge` par défaut
- Services accessibles par hostname (e.g., `db:5432`, `php:8000`)
- Port 81 : edge public (public-facing)

### 7.6 Docker-Compose Préprod (docker-compose.preprod.yml)

**Différences** :

| Aspect | Dev | Préprod |
|--------|-----|---------|
| **Env obligatoires** | Defaults fournis | `${VARIABLE:?must be set}` |
| **Volumes** | Bind-mounted code | Volumes named persistants |
| **Mailhog** | `profiles: ["dev-only"]` | Désactivé |
| **Secrets** | .env fichier | Env variables d'orchestration |

---

## 8. Caddy Reverse Proxy (Caddyfile)

### 8.1 Ports & Listeners

| Port | Nom | But |
|------|-----|-----|
| `:80`, `:443` | Interne | Back-office complet (admin, planning, mécanicien, VO) |
| `:81` | Public edge | Client-facing uniquement (booking, suivi, demande, mentions) |

### 8.2 Main Listener (`:80`, `:443`)

| Route | Cible | Compression | Détail |
|-------|-------|-------------|--------|
| `/.well-known/mercure` | `mercure:3000` | zstd, gzip | Mercure hub proxy |
| `/api/*` | `php:8000` | zstd, gzip | Backend API |
| `/uploads/*` | `php:8000` | zstd, gzip | Téléchargements (photos, logos) |
| `/` | `nuxt:3000` | zstd, gzip | Frontend SPA |

### 8.3 Public Edge Listener (`:81`)

**Intention** : Exposer UNIQUEMENT les pages client publiques (booking, suivi, demande, mentions, politique).

| Route | Cible | Compression | Matcher |
|-------|-------|-------------|---------|
| `/api/*` | `php:8000` | zstd, gzip | Public API endpoints (booking, suivi, demande, photos) |
| `/uploads/*` | `php:8000` | zstd, gzip | Téléchargements publics |
| `/.well-known/mercure` | `mercure:3000` | zstd, gzip | Mercure (temps réel suivi) |
| `/public/booking*` | `nuxt:3000` | zstd, gzip | Prise RDV publique |
| `/public/suivi`, `/public/suivi/*` | `nuxt:3000` | zstd, gzip | Suivi RDV client |
| `/public/demande`, `/public/demande/*` | `nuxt:3000` | zstd, gzip | Validation demande travaux |
| `/public/mentions-legales*` | `nuxt:3000` | zstd, gzip | Mentions légales |
| `/public/politique-confidentialite*` | `nuxt:3000` | zstd, gzip | Politique confidentialité |
| `/_nuxt/*`, `/__nuxt/*`, `/favicon.ico`, `/branding/*`, `/robots.txt` | `nuxt:3000` | zstd, gzip | Assets Nuxt |
| `/` | redir `302` → `/public/booking` | - | Root redirect |
| **Catch-all** | 404 response | - | Tout le reste rejeté |

---

## 9. CI/CD (.github/workflows/)

**Status** : **Aucun workflow détecté** — pas de répertoire `.github/workflows/`.

Implication : Déploiement manuel ou via webhook listener Python (`scripts/github-webhook-listener.py`).

**Webhook listener config** :

| Variable | Défaut |
|----------|--------|
| `WEBHOOK_HOST` | `0.0.0.0` |
| `WEBHOOK_PORT` | `9010` |
| `WEBHOOK_PATH` | `/github-webhook` |
| `WEBHOOK_SECRET` | (vide) |
| `BRANCH` | `main` |
| `PROJECT_DIR` | `/opt/ateliermbz` |
| `DEPLOY_SCRIPT` | `$PROJECT_DIR/scripts/deploy-server.sh` |

---

## 10. Résumé des Aspects Transverses

### 10.1 Authentification & Autorisation

```
JWT (HS256, 15min TTL) → CookieJwtAuthenticator
                     ↓
                TenantFilterListener
                     ↓
    TenantFilter (WHERE atelier_id = :id)
                     ↓
    RolePermissionVoter (PERM_*)
                     ↓
    Access-control rules (patterns + roles)
```

### 10.2 Multi-Tenant Isolation

- **Row-level** : TenantFilter Doctrine
- **SUPER_ADMIN bypass** : explicite et audité
- **Default value** : CurrentAtelierResolver (JWT ou cookie)
- **Fallback** : impossible ID (0) si non résolvable

### 10.3 Notifications

```
NotificationDispatcher
    ↓
Provider Selection (priority order, fallback)
    ↓
Channel Routing (email, sms)
    ↓
Twilio / OVH / Mailgun / Symfony Mailer
    ↓
NotificationLog (audit)
```

**Temps réel** : Mercure (SSE/WebSocket) sur `/api/notifications` + `useNotifications.ts`

### 10.4 Data Security

- **Secrets** : `APP_SECRET`, `JWT_SECRET_KEY`, `MERCURE_JWT_SECRET` (requis)
- **Chiffrement** : `ConfigEncryptionService` (base `kernel.secret`)
- **Snapshots RGPD** : Colonnes `snap_*` sur OR, factures, LP
- **Rate limiting** : 6 limiters (sliding window, cache storage)

### 10.5 Deployment Topology

```
                         PROD/PREPROD
                         (PORT 80, 443)
                              ↓
                          Caddy:2.9
                          /        \
                    php:8000       nuxt:3000
                        ↓              
                 PostgreSQL:15      
                        ↓
                      Worker
                   (Messenger
                    Consumer)
                        
                    REAL-TIME
                        ↓
                    Mercure:0.18
```

### 10.6 Test Coverage

| Type | Framework | Count | Focus |
|------|-----------|-------|-------|
| **Unit** | PHPUnit | ~200 | Services, DTO, policies |
| **Functional** | PHPUnit | ~130 | Controllers, workflows, webhooks |
| **E2E** | Playwright | ~63 | Business flows, auth, roles |
| **Frontend Unit** | Vitest | ~51 | Composables, stores |

---

## Conclusion

AtelierMBZ implémente une **architecture multi-tenant row-level cohérente** avec JWT stateless, rate limiting, webhook signature verification, et notifications multi-canaux (email, SMS, Mercure temps réel). La sécurité repose sur TenantFilter Doctrine, CSP Report-Only, headers de sécurité, et audit trail. Les tests couvrent le flux métier principal (RDV → réception → intervention → restitution) ainsi que le module VO (rachat → FRE → revente). Deployment via Docker Compose avec Caddy comme reverse proxy, PostgreSQL 15, et Mercure pour les mises à jour en temps réel.