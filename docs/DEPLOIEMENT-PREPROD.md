<!-- markdownlint-disable MD013 MD024 MD033 -->

# Déploiement préprod AtelierMBZ — Procédure détaillée

Document de référence pour installer **AtelierMBZ v4** sur un serveur de préprod (Linux + Docker Compose) à partir d'une base de données vide, jusqu'au premier login admin opérationnel.

> **Validation** : la procédure ci-dessous a été testée le 23/04/2026 sur la DB `test` du projet (drop complet → install neuve via baseline → admin créé → seed inséré). Voir §11 pour la trace.

---

## 1. Vue d'ensemble

```text
┌──────────────────────────────────────────────────────────────┐
│  1. Pré-requis serveur (Docker, ports, domaine)              │
│  2. Clone + .env (secrets, domaine, mot de passe admin)      │
│  3. Build des images (php, nuxt, caddy)                      │
│  4. Démarrage des services                                   │
│  5. Bootstrap base : baseline + version --add --all          │
│  6. Création de l'atelier + super-admin                      │
│  7. Seed des données de référence (rôles, config, templates) │
│  8. Vérifications HTTP + premier login                       │
│  9. Sauvegardes & maintenance                                │
└──────────────────────────────────────────────────────────────┘
```

**Spécificité importante** : la chaîne historique de migrations Doctrine ne peut pas être jouée sur une base vide (la 1ère migration historique suppose que des tables préexistent). Une **migration baseline** ([backend/migrations/Version20260101000000.php](../backend/migrations/Version20260101000000.php)) crée tout le schéma en une passe sur une base vide, puis on marque les migrations historiques comme déjà appliquées (sans les exécuter). Sur un déploiement existant, la baseline détecte la table `clients` et se met automatiquement en **no-op**.

---

## 2. Pré-requis serveur

| Élément | Version minimum | Vérification |
|---|---|---|
| OS | Ubuntu 22.04 / Debian 12 | `lsb_release -a` |
| Docker Engine | 24+ | `docker --version` |
| Docker Compose plugin | v2 | `docker compose version` |
| Git | 2.x | `git --version` |
| RAM | 4 Go mini (8 Go conseillés) | `free -h` |
| Disque libre | 20 Go mini | `df -h /` |
| Ports ouverts (firewall) | 80, 443 | `ss -tlnp` |

Installation rapide :

```bash
sudo apt update && sudo apt install -y git curl ca-certificates ufw
# Docker (si absent) :
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER && newgrp docker
sudo ufw allow 22/tcp && sudo ufw allow 80/tcp && sudo ufw allow 443/tcp && sudo ufw enable
```

---

## 3. Clone du projet

```bash
sudo mkdir -p /opt/ateliermbz
sudo chown -R "$USER:$USER" /opt/ateliermbz
cd /opt/ateliermbz
git clone <URL_DU_REPO> .
git checkout main   # ou la branche de release préprod
```

---

## 4. Configuration `.env`

Créer un fichier `.env` à la racine du projet **avec des secrets neufs** (ne **jamais** réutiliser les secrets de dev).

### 4.1 Génération des secrets

```bash
APP_SECRET=$(openssl rand -hex 32)
JWT_SECRET=$(openssl rand -hex 64)
MERCURE_SECRET=$(openssl rand -hex 32)
PG_PASSWORD=$(openssl rand -hex 24)
ADMIN_PWD=$(openssl rand -base64 18)
echo "APP_SECRET=$APP_SECRET"
echo "JWT_SECRET_KEY=$JWT_SECRET"
echo "MERCURE_JWT_SECRET=$MERCURE_SECRET"
echo "POSTGRES_PASSWORD=$PG_PASSWORD"
echo "ADMIN_PASSWORD=$ADMIN_PWD"
```

**Conserve ces valeurs dans un coffre-fort avant de fermer le terminal.**

### 4.2 Contenu du `.env` (à compléter)

```dotenv
# --- Environnement applicatif ---
APP_ENV=prod
APP_DOMAIN=preprod.atelier.example.fr
APP_SECRET=<APP_SECRET généré ci-dessus>

# --- Base de données ---
POSTGRES_DB=atelier_moto
POSTGRES_USER=atelier
POSTGRES_PASSWORD=<PG_PASSWORD généré>

# --- JWT ---
JWT_SECRET_KEY=<JWT_SECRET généré>
JWT_PUBLIC_KEY=
JWT_PASSPHRASE=

# --- Mercure (notifications temps réel) ---
MERCURE_URL=http://mercure/.well-known/mercure
MERCURE_PUBLIC_URL=https://preprod.atelier.example.fr/.well-known/mercure
MERCURE_JWT_SECRET=<MERCURE_SECRET généré>

# --- CORS ---
CORS_ALLOW_ORIGIN=https://preprod.atelier.example.fr

# --- Email transactionnel (à adapter au provider choisi) ---
MAILER_DSN=smtp://user:pass@smtp.example.fr:587

# --- Messenger (worker async) ---
MESSENGER_TRANSPORT_DSN=doctrine://default

# --- Premier admin (utilisé une seule fois par app:create-admin) ---
ADMIN_USERNAME=admin
ADMIN_EMAIL=admin@atelier.example.fr
ADMIN_PASSWORD=<ADMIN_PWD généré>
```

> **NB** : ne pas committer ce fichier. `.env` est dans `.gitignore` racine.

---

## 5. Build des images Docker

```bash
cd /opt/ateliermbz
docker compose -f docker-compose.yml -f docker-compose.preprod.yml build php nuxt caddy
```

Le build télécharge les images de base PostgreSQL/Mercure/Caddy + compile l'image PHP (Composer install) et Nuxt (npm install + build).

---

## 6. Démarrage des services

```bash
docker compose -f docker-compose.yml -f docker-compose.preprod.yml up -d db php worker nuxt caddy mercure
docker compose ps   # tous les services doivent être "Up" / "healthy"
```

Attendre ~10 secondes que PostgreSQL soit prêt :

```bash
docker compose exec db pg_isready -U "$POSTGRES_USER" -d "$POSTGRES_DB"
# attendu : "accepting connections"
```

---

## 7. Bootstrap de la base de données (première fois UNIQUEMENT)

> ⚠️ **À exécuter une seule fois, sur une base totalement vide.** Si la base contient déjà des tables (déploiement existant), passer directement au §10.

### 7.1 Création de la base

```bash
docker compose exec -T php bin/console doctrine:database:create --if-not-exists
```

### 7.2 Application de la baseline (crée tout le schéma)

```bash
docker compose exec -T php bin/console doctrine:migrations:execute "DoctrineMigrations\\Version20260101000000" --up --no-interaction
```

Sortie attendue :

```text
[OK] Successfully migrated version(s):
     DoctrineMigrations\Version20260101000000: [UP]
```

### 7.3 Marquer les migrations historiques comme déjà appliquées

Comme la baseline a créé le schéma final, les migrations historiques (qui font des `ALTER TABLE` incrémentaux) doivent être marquées comme exécutées sans les rejouer (sinon erreurs `duplicate column`).

```bash
yes | docker compose exec -T php bin/console doctrine:migrations:version --add --all
```

Sortie attendue :

```text
DoctrineMigrations\Version20260606090000 added to the version table.
```

Vérification :

```bash
docker compose exec -T php bin/console doctrine:migrations:current
# attendu : "DoctrineMigrations\Version20260606090000" (ou la version la plus récente disponible)
```

### 7.4 Création du super-admin et de l'atelier par défaut

```bash
docker compose exec -T php bin/console app:create-admin
```

Sortie attendue :

```text
[INFO] Default atelier created (id=1).
[OK]   Super admin created: admin / <ADMIN_PASSWORD>
```

> Si l'admin existe déjà : `[WARNING] Admin user already exists.` (idempotent, pas d'action).

### 7.5 Seed des données de référence

Crée les rôles système (`super_admin`, `admin`, `receptionnaire`, `mecanicien`, `comptable`), catégories de motos, horaires d'ouverture par défaut, configuration atelier (taux MO, TVA, marges, garantie 30j, gardiennage 5€/j, etc.), templates email + notifications, prestations forfait standard.

```bash
docker compose exec -T php bin/console app:seed
```

Sortie attendue :

```text
[INFO] Notification templates seeded.
[INFO] Default forfait prestations seeded.
[OK]   Seed data inserted.
```

> **Optionnel — données de démonstration** (mécaniciens, ponts, clients fictifs, pièces) :
> ```bash
> docker compose exec -T php bin/console app:seed --demo
> ```
> ⚠️ À ne **jamais** lancer sur un atelier réel en exploitation. Réservé aux environnements vitrine / formation.

---

## 8. Vérifications HTTP

### 8.1 Endpoints publics

```bash
curl -I https://preprod.atelier.example.fr/                    # 200
curl -I https://preprod.atelier.example.fr/login               # 200
curl    https://preprod.atelier.example.fr/api/health          # {"status":"ok",...}
```

### 8.2 Premier login API

```bash
curl -X POST https://preprod.atelier.example.fr/api/auth/login \
  -H 'Content-Type: application/json' \
  -d "{\"username\":\"admin\",\"password\":\"$ADMIN_PWD\"}"
```

Réponse attendue : JSON avec un `token` JWT et `user.role = "super_admin"`.

### 8.3 Premier login UI

Ouvrir https://preprod.atelier.example.fr/login dans un navigateur et se connecter avec :

- **utilisateur** : `admin`
- **mot de passe** : valeur de `ADMIN_PASSWORD` du `.env`

Redirection attendue vers le dashboard `/`.

### 8.4 Headers de sécurité

```bash
curl -sI https://preprod.atelier.example.fr/ | grep -iE "strict-transport|csp|x-frame|x-content|referrer"
```

Headers attendus :

- `strict-transport-security: max-age=...`
- `content-security-policy-report-only: ...`
- `x-frame-options: DENY`
- `x-content-type-options: nosniff`
- `referrer-policy: strict-origin-when-cross-origin`

---

## 9. Configuration métier post-installation (à faire dans l'UI admin)

Une fois connecté en `admin`, **avant ouverture aux utilisateurs réels** :

1. **Atelier > Identité** : compléter `nom`, `siret`, `tva_intracom`, `adresse`, `cp`, `ville`, `telephone`, `email`, `logo` — utilisés sur tous les PDF (factures, OR, LP, PV rachat).
2. **Atelier > Configuration** : valider taux horaires MO, marges pièces, taux TVA, durée garantie travaux, tarif gardiennage, jours/dates de fermeture.
3. **Modules** : activer les modules nécessaires (`featureModules` dans `config_atelier`). VO désactivé par défaut.
4. **Utilisateurs** : créer les comptes des collaborateurs (réceptionnaires, mécaniciens, gestionnaire VO, comptable) avec leurs rôles métier.
5. **Notifications** : configurer les providers (SMTP/Twilio/Mailgun/OVH) dans `Admin > Notifications > Providers` et tester l'envoi.
6. **Mentions légales** : vérifier les pages `/public/mentions-legales` et `/public/politique-confidentialite` (à personnaliser via `clauses-legales`).

---

## 10. Mises à jour ultérieures (déploiement existant)

Pour les déploiements suivants (la base contient déjà des données), la procédure est standard :

```bash
cd /opt/ateliermbz
git pull origin main
docker compose -f docker-compose.yml -f docker-compose.preprod.yml build php nuxt
docker compose -f docker-compose.yml -f docker-compose.preprod.yml up -d php nuxt worker

# Migrations (la baseline est ignorée automatiquement via skipIf)
docker compose exec -T php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# Vider le cache prod si nécessaire
docker compose exec -T php bin/console cache:clear --env=prod

# Vérification
curl -I https://preprod.atelier.example.fr/api/health
```

> La migration baseline `Version20260101000000` détecte la présence de la table `clients` et **se met en no-op** automatiquement (`skipIf`). Aucune intervention requise.

---

## 11. Trace de validation (23/04/2026)

Procédure complète testée en local sur la DB `test` :

```text
$ docker compose exec -T -e APP_ENV=test php bin/console doctrine:database:drop --force
Dropped database "atelier_moto_test" for connection named default

$ docker compose exec -T -e APP_ENV=test php bin/console doctrine:database:create
Created database "atelier_moto_test" for connection named default

$ docker compose exec -T -e APP_ENV=test php bin/console doctrine:migrations:execute \
    "DoctrineMigrations\Version20260101000000" --up --no-interaction
[OK] Successfully migrated version(s):
     DoctrineMigrations\Version20260101000000: [UP]

$ yes | docker compose exec -T -e APP_ENV=test php bin/console doctrine:migrations:version --add --all
DoctrineMigrations\Version20260606090000 added to the version table.

$ docker compose exec -T -e APP_ENV=test php bin/console doctrine:migrations:current
DoctrineMigrations\Version20260606090000 - [C3] VOLivrePolice : ...

$ docker compose exec -T -e APP_ENV=test php bin/console doctrine:migrations:migrate -n
[OK] Already at the latest version

$ # 63 tables créées (62 entités + 1 doctrine_migration_versions)
$ docker compose exec -T -e APP_ENV=test php bin/console doctrine:query:sql \
    "SELECT count(*) FROM information_schema.tables WHERE table_schema='public'"
nb = 63

$ docker compose exec -T -e APP_ENV=test -e ADMIN_PASSWORD='ChangeMe2026!' \
    php bin/console app:create-admin
[INFO] Default atelier created (id=1).
[OK]   Super admin created: admin / ChangeMe2026!

$ docker compose exec -T -e APP_ENV=test php bin/console app:seed
[INFO] Notification templates seeded.
[INFO] Default forfait prestations seeded.
[OK]   Seed data inserted.
```

---

## 12. Sauvegardes (à mettre en place dès la mise en production)

### 12.1 Dump quotidien PostgreSQL

```bash
sudo crontab -e
# Ajouter :
0 3 * * * cd /opt/ateliermbz && docker compose exec -T db pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB" | gzip > /var/backups/ateliermbz-$(date +\%Y\%m\%d).sql.gz && find /var/backups -name "ateliermbz-*.sql.gz" -mtime +30 -delete
```

### 12.2 Volumes uploads à sauvegarder

```text
backend_logos       — logos des ateliers
backend_photos      — photos d'intervention
backend_signatures  — signatures électroniques (RGPD : 5 ans)
backend_vo_uploads  — documents VO (CG, non-gage, contrats — RGPD : 5 ans)
```

```bash
docker run --rm -v ateliermbz_backend_vo_uploads:/data -v /var/backups:/backup alpine \
  tar czf /backup/vo-uploads-$(date +%Y%m%d).tar.gz -C /data .
```

### 12.3 Restauration

```bash
gunzip -c /var/backups/ateliermbz-20260423.sql.gz | \
  docker compose exec -T db psql -U "$POSTGRES_USER" -d "$POSTGRES_DB"
```

---

## 13. Maintenance courante

### 13.1 Logs

```bash
docker compose logs -f php           # backend Symfony
docker compose logs -f nuxt          # frontend Nuxt
docker compose logs -f caddy         # reverse proxy
docker compose logs -f worker        # messenger async (notifications)
docker compose logs -f mercure       # SSE temps réel
```

### 13.2 Commandes planifiées (cron Symfony)

À ajouter au crontab du serveur :

```bash
# Notifications escaladées (SMS J+5/10/30 min)
*/5 * * * * cd /opt/ateliermbz && docker compose exec -T php bin/console app:notifications:process-escalations

# Relances clients gardiennage (J+15, J+30)
30 8 * * * cd /opt/ateliermbz && docker compose exec -T php bin/console app:relance-stockage

# Vérification expiration DA SIV (J+10, J-5)
0 7 * * * cd /opt/ateliermbz && docker compose exec -T php bin/console app:vo:check-da-siv-expiry

# Vérification mandat dépôt-vente (J-7)
0 7 * * * cd /opt/ateliermbz && docker compose exec -T php bin/console app:vo:check-depot-mandat

# Détection no-show (toutes les heures)
0 * * * * cd /opt/ateliermbz && docker compose exec -T php bin/console app:check-no-show

# Purge RGPD (pièces identité 0j, justificatifs domicile 0j)
0 4 * * * cd /opt/ateliermbz && docker compose exec -T php bin/console app:purge-identity-documents

# Rappels révision client (J-30)
0 9 * * * cd /opt/ateliermbz && docker compose exec -T php bin/console app:rappel-prochaine-revision
```

### 13.3 Audit trail

Toutes les actions sensibles (création/suppression utilisateur, transition workflow, DA SIV, écriture LP, accès super-admin cross-atelier) sont loggées dans la table `audit_log`. Consultable depuis l'UI : `Admin > Audit`.

---

## 14. Points d'attention

| Point | Action |
|---|---|
| Mot de passe admin par défaut | Changer dès le premier login (UI : `Profil > Mot de passe`) |
| `.env` jamais commité | Vérifier `git status` avant tout `git push` |
| `docker-compose.preprod.yml` requis | Sans lui, le worker et les variables strictes manquent — l'app ne démarre pas |
| Volumes Docker | Ne **jamais** lancer `docker compose down -v` en préprod (perte des uploads RGPD) |
| Backup avant déploiement | Toujours `pg_dump` avant un `git pull` qui inclut une nouvelle migration |
| Healthcheck | `curl /api/health` doit retourner 200 — c'est le seul endpoint API public en préprod |
| `/api/docs` (Swagger) | Doit être désactivé ou protégé en préprod (vérifier `API_PLATFORM_ENABLE_SWAGGER` si présent) |

---

## 15. Désinstallation complète (préprod uniquement, jamais en prod)

```bash
cd /opt/ateliermbz
docker compose down -v       # ⚠️ DÉTRUIT TOUTES LES DONNÉES
sudo rm -rf /opt/ateliermbz
```

---

## 16. Liens utiles

- Architecture & règles métier : [.github/copilot-instructions.md](../.github/copilot-instructions.md)
- Historique projet : [.github/PROJECT_HISTORY.md](../.github/PROJECT_HISTORY.md)
- Webhook auto-deploy : [docs/DEPLOIEMENT-SERVEUR-WEBHOOK.md](DEPLOIEMENT-SERVEUR-WEBHOOK.md)
- Migration baseline : [backend/migrations/Version20260101000000.php](../backend/migrations/Version20260101000000.php)
- Commande admin : [backend/src/Command/CreateAdminCommand.php](../backend/src/Command/CreateAdminCommand.php)
- Commande seed : [backend/src/Command/SeedCommand.php](../backend/src/Command/SeedCommand.php)
