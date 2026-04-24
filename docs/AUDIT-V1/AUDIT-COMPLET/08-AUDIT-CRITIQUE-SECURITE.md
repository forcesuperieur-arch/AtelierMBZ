# Audit Critique — Sécurité et Transverse

## Périmètre audité

- backend/config/packages/security.yaml
- backend/config/packages/lexik_jwt_authentication.yaml
- backend/src/Controller/AuthController.php
- backend/src/Controller/NotificationProviderController.php
- backend/src/EventListener/SecurityHeadersListener.php
- backend/src/EventListener/TenantFilterListener.php
- backend/src/EventListener/TenantSetterListener.php
- backend/src/Service/ConfigEncryptionService.php
- frontend/composables/useApi.ts
- frontend/nuxt.config.ts
- Caddyfile

---

## Constats confirmés

### [CRITIQUE] Refresh token non révoqué au logout

Preuves code :
- `AuthController::createAuthenticatedResponse()` crée un refresh token cookie valable 7 jours (`/api/auth/refresh`).
- `AuthController::refresh()` parse le refresh token et émet un nouvel access token.
- `AuthController::logout()` ne révoque que le `jti` de l'access token (15 minutes), puis supprime les cookies.

Impact : en cas de vol du cookie refresh, la session peut être relancée jusqu'à expiration même après logout utilisateur.

Fix minimal :
- Révoquer aussi le `jti` refresh au logout,
- Mettre en place rotation refresh token à chaque refresh (nouveau refresh + invalidation de l'ancien).

---

### [CRITIQUE] Webhooks providers : risque de mise à jour cross-tenant

Preuves code :
- `NotificationProviderController::verifyWebhookSignature()` boucle sur toutes les configs actives d'un provider (tous ateliers) et accepte la première signature valide.
- `handleTwilioWebhook/handleOvhWebhook/handleMailgunWebhook` mettent ensuite à jour `NotificationLog` par `providerMessageId` seul.

Impact : un webhook validé avec une config d'un atelier A peut potentiellement affecter un log atelier B si collision/connaissance d'identifiant provider.

Fix minimal :
- Lier chaque envoi à `provider_config_id` et `atelier_id`,
- Lors du webhook, restreindre l'update au scope de la config qui a validé la signature.

---

### [IMPORTANT] Changement d'atelier super-admin non audité

Preuve code :
- `AuthController::switchAtelier()` contrôle les droits mais n'appelle pas `AuditService::log()`.

Impact : absence de trace explicite d'une action transverse sensible (non-répudiation faible).

Fix minimal :
- Logger systématiquement : acteur, atelier source, atelier cible, IP, user-agent, timestamp.

---

### [IMPORTANT] CSP en mode observation uniquement

Preuves code :
- Backend : `Content-Security-Policy-Report-Only` dans `SecurityHeadersListener`.
- Front : `Content-Security-Policy-Report-Only` dans `nuxt.config.ts`.
- Directives encore permissives (`unsafe-inline`, `unsafe-eval`).

Impact : protection XSS partiellement préventive tant que CSP n'est pas en mode bloquant.

Fix minimal :
- Passer progressivement en CSP bloquante par zones,
- Retirer `unsafe-eval` en premier, puis réduire `unsafe-inline` via nonce/hash.

---

### [IMPORTANT] Surface API large sur edge public

Preuve code (`Caddyfile`, port `:81`) :
- Le bloc public expose `handle /api/*` vers le backend complet,
- Les pages front sont whitelistées, mais pas les routes API au niveau edge.

Impact : surface d'attaque plus large côté exposition publique ; la sécurité repose entièrement sur les contrôles applicatifs.

Fix minimal :
- Restreindre explicitement les routes API autorisées sur `:81` (booking/suivi/demande/photos publiques),
- Répondre 404/403 pour le reste au niveau Caddy.

---

### [CONFORT] Logs front verbeux en production

Preuve code (`useApi.ts`) :
- `logApiIssue()` écrit en console,
- Peut inclure body et extrait de réponse (jusqu'à 500 chars).

Impact : fuite potentielle d'informations métier/PII dans la console navigateur.

Fix minimal :
- Conditionner logs à l'environnement dev,
- Masquer systématiquement champs sensibles (`token`, `email`, `phone`, etc.).

---

## Points à vérifier opérationnellement

1. Valeur effective de `CORS_ALLOW_ORIGIN` en préprod/prod (car `allow_credentials=true`).
2. Durcissement rate-limit sur endpoints publics à token.
3. Environnement réellement forcé en `prod` sur toutes instances exposées (pour éviter les routes simulate/dev).
4. Redirection HTTP->HTTPS effective en edge final + cohérence HSTS.

---

## Priorisation recommandée

P0 (immédiat)
1. Révocation/rotation refresh token.
2. Scoping webhook par atelier/config.
3. Audit log sur `switchAtelier`.

P1
4. Restriction API edge public `:81`.
5. Plan de passage CSP report-only vers mode bloquant.

P2
6. Nettoyage logs front en production.
