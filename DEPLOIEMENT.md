# Déploiement production — Paddock

Checklist phase 6 du MVP. À dérouler dans l'ordre sur le serveur cible.

## 1. Secrets à changer AVANT toute exposition (valeurs de dev en clair)

> Modèle prêt à remplir : copier **`.env.prod.example`** en `.env` sur le
> serveur et compléter chaque `CHANGEME`.

| Variable | Où | Valeur dev à remplacer |
|---|---|---|
| `POSTGRES_PASSWORD` | env docker-compose | `atelier` |
| `ADMIN_PASSWORD` | env docker-compose | `Admin123!` |
| `MERCURE_JWT_SECRET` | env docker-compose | `ChangeMercureJWTSecretKey2026` |
| `APP_SECRET` | backend/.env.local | valeur du repo |
| Clés JWT (`config/jwt/*.pem`) | volume backend | régénérer : `php bin/console lexik:jwt:generate-keypair --overwrite` |
| `MAILER_DSN` | backend/.env.local | `smtp://mailhog:1025` → SMTP réel |
| `PUBLIC_URL` | env php | URL publique réelle (liens emails) |
| `CLIENT_COOKIE_SECURE` | env php | poser `1` si le proxy TLS ne transmet pas X-Forwarded-Proto |
| `PUBLIC_BOOKING_RATE_LIMIT` | env php | retirer l'override dev (retombe à 5/min) |

Les comptes de test (`admin@atelier.local`, `jean.moreau@email.fr`) ne doivent pas exister en prod — ne pas lancer les seeds de démo.

## 2. HTTPS sur l'edge public

Le Caddyfile est paramétré : poser simplement le domaine dans `.env` :

```
PUBLIC_DOMAIN=moncommerce.example.com
```

(En dev, la variable absente retombe sur le port 81 local.)
Caddy provisionne Let's Encrypt automatiquement (ports 80/443 ouverts requis).
Le port 80 interne (back-office) ne doit JAMAIS être exposé sur Internet :
pare-feu ou réseau privé uniquement.

## 3. Base de données

- `php bin/console doctrine:migrations:migrate --no-interaction`
- `php bin/console app:seed` (paramètres de base uniquement, pas la démo)
- Appliquer `update_clauses.sql` APRÈS avoir remplacé les placeholders
  `[SIRET]`, `[TVA]`, `[Hébergeur]`, `[capital]`, `[ville]`, `[adresse]`
- Sauvegardes : pg_dump quotidien + rétention 30 j minimum (RGPD)

## 4. Frontends en build de production

- `client-frontend` : déjà en build prod (docker-compose)
- `frontend` (staff) : déjà en build prod depuis le 2026-06-12 (build Nuxt au
  démarrage du conteneur + Nitro, même pattern que client-nuxt)

### Limite connue — serveur PHP

Le backend est servi par `php -S` (8 workers, `PHP_CLI_SERVER_WORKERS`).
Suffisant pour un MVP mono-atelier derrière le rate-limit, mais ce n'est pas
un serveur de production : prévoir la bascule vers PHP-FPM (l'image est déjà
basée `php:8.3-fpm-alpine`) ou FrankenPHP dans les semaines suivant la mise
en ligne.

## 5. Vérifications post-déploiement

```bash
# Surface publique : tout doit répondre 404 sauf les routes client/public
curl -s -o /dev/null -w '%{http_code}' https://DOMAINE/api/companion/x/status   # 404
curl -s -o /dev/null -w '%{http_code}' https://DOMAINE/.well-known/mercure      # 404
curl -s -o /dev/null -w '%{http_code}' https://DOMAINE/api/client/me            # 401
# Cookies : Secure + HttpOnly sur client_access_token (DevTools après login)
# Worker : docker compose ps → état healthy (file failed vide)
# Emails : réserver un créneau test → accusé reçu sur une vraie boîte
```

## 6. Surveillance

- `docker compose ps` : le service worker porte un healthcheck (unhealthy si
  des messages sont en file d'échec) — `messenger:failed:show` pour le détail
- Scheduler embarqué dans le worker : rappels J-1 à 8h, purge RGPD mensuelle,
  rappel révision à 9h — vérifier les logs du worker le premier matin
