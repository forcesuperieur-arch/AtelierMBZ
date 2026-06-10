# Sécurité — AtelierMBZ

> Document mis à jour après audit complet (Sessions 1–5).

---

## 🔐 Authentification

- **JWT via cookies HttpOnly** — `access_token` (15 min) + `refresh_token` (7 jours)
- **SameSite=Lax** sur les cookies (à passer en `Strict` pour renforcer le CSRF)
- **Secure=true** en production uniquement
- Firewall Symfony avec `CookieJwtAuthenticator` stateless sur `/api/*`
- Routes publiques explicitement listées dans `security.yaml`

## 🏢 Isolation Multi-Tenant (critique)

Toutes les données sont isolées par `atelier_id`. Trois mécanismes s'assurent que
les users ne voient que les données de leur atelier :

1. **Doctrine SQL Filter** (`TenantFilter`) — filtre automatiquement **toutes** les
   requêtes SQL pour ajouter `WHERE atelier_id = X` sur les entités concernées.
2. **TenantSetterListener** — injecte automatiquement `atelier_id` sur les nouvelles
   entités créées via Doctrine.
3. **Super admin bypass** — `ROLE_SUPER_ADMIN` n'est pas soumis au filtre tenant
   (accès cross-atelier intentionnel).

> ⚠️ Les entités API Platform n'ont **pas** de `security` explicite sur chaque
> opération. L'isolation repose intégralement sur le `TenantFilter`. Ne pas
> supprimer ce filtre sans remplacement.

## 📁 Uploads & Fichiers

### Photos d'intervention
- Stockées dans `/var/photos/` (hors du document root)
- Servies via `PhotoController` avec auth `ROLE_USER`
- Types MIME whitelistés : JPEG, PNG, WebP
- Max 10MB
- Hash SHA256 pour l'intégrité

### Documents VO (rachat / dépôt-vente)
- **Avant** : stockés dans `/public/uploads/vo/` → accessible publiquement
- **Après correction** : stockés dans `/var/uploads/vo/` (hors document root)
- Servis via controllers authentifiés (`VOController`, `VORemiseEnEtatController`)
- Fallback legacy sur `/public/uploads/vo/` pour les anciens fichiers
- Types MIME whitelistés : PDF, JPEG, PNG, WebP
- Max 10MB (ajouté après audit)

### Documents publics (companion / suivi)
- `PublicPhotoController` — token-based (30 jours d'expiration RGPD)
- Vérification que la photo appartient bien au RDV du token
- Path traversal protégé via `realpath()` + `str_starts_with()`

## 🌐 CORS

- Restreint à `localhost` et `127.0.0.1` en développement
- `allow_credentials: true` — les cookies JWT sont transmis
- En production, doit être configuré avec le domaine exact de l'app

## 🛡️ Headers de sécurité

> **Amélioration recommandée** : ajouter `nelmio/security-bundle` pour configurer :
> - `X-Content-Type-Options: nosniff`
> - `X-Frame-Options: DENY`
> - `Content-Security-Policy`

## 🔒 Endpoints publics

Les endpoints suivants sont accessibles sans authentification :
- `/api/auth/login`, `/api/auth/refresh`, `/api/auth/google`
- `/api/public/*` (booking, companion, suivi)
- `/api/companion/*`
- `/api/photos/file/*` (⚠️ vérifier le comportement — firewall public mais controller requiert `ROLE_USER`)
- `/api/webhooks/notifications/*`
- `/api/docs`
- `/api/motos/(autocomplete|marques)`

## 🚨 Points de vigilance

| Risque | Statut | Notes |
|--------|--------|-------|
| Tenant isolation | ✅ Corrigé / Fonctionnel | `TenantFilter` + `TenantSetterListener` |
| Documents VO exposés | ✅ Corrigé | Déplacés hors `/public/` |
| Upload taille VO | ✅ Corrigé | Limite 10MB ajoutée |
| Headers de sécurité | ⚠️ À améliorer | Ajouter `nelmio/security-bundle` |
| SameSite cookie | ⚠️ À améliorer | Passer à `Strict` si possible |
| Rate limiting | ⚠️ Partiel | `public_booking` (5/min) et `public_suivi` (60/min) actifs. Companion VO : config prête mais non activée (besoin de clé par token, pas par IP) |
| N+1 queries | ⚠️ À auditer | Vérifier les relations API Platform en production |

## 🧪 Tests de sécurité E2E

Les tests suivants valident les contrôles d'accès :
- `security-tenant-isolation.spec.mjs` — accès non autorisé = 404/401
- `non-regression.spec.mjs` — redirection login, auth admin

---

*Dernière mise à jour : 2026-05-16*
