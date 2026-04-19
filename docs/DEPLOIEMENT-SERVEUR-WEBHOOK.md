# Déploiement serveur avec webhook GitHub

Ce guide permet de déployer AtelierMBZ sur un serveur Linux avec Docker Compose et de déclencher automatiquement les mises à jour via un webhook GitHub.

## 1. Vérification réelle effectuée

Validation faite dans l'environnement Docker du projet :

- build complet des images `php` et `nuxt`
- démarrage des services `db`, `php`, `worker`, `nuxt`, `caddy`, `mercure`
- contrôle HTTP concluant :
  - `https://localhost/` → `200`
  - `https://localhost/login` → `200`
  - `https://localhost/api/docs` → `200`

Conclusion : l'application démarre correctement après un build serveur, à condition de fournir les variables d'environnement obligatoires.

---

## 2. Prérequis serveur

Prévoir un serveur Ubuntu/Debian avec :

- Docker installé
- plugin Docker Compose installé
- Git installé
- un domaine pointé vers le serveur
- les ports `80` et `443` ouverts

Exemple :

```bash
sudo apt update
sudo apt install -y git curl ca-certificates
```

---

## 3. Installation du projet

```bash
sudo mkdir -p /opt/ateliermbz
sudo chown -R $USER:$USER /opt/ateliermbz
cd /opt/ateliermbz
git clone <URL_DU_REPO> .
```

### Important

Ne réutilise pas les secrets committés en local. Sur le serveur, définis de nouvelles valeurs fortes.

Crée ou remplace le fichier `.env` à la racine du projet :

```dotenv
APP_ENV=prod
APP_DOMAIN=atelier.example.fr
POSTGRES_DB=atelier_moto
POSTGRES_USER=atelier
POSTGRES_PASSWORD=change-moi
APP_SECRET=genere_une_cle_hex_64
JWT_SECRET_KEY=genere_une_cle_hex_128
JWT_PUBLIC_KEY=
JWT_PASSPHRASE=
MERCURE_JWT_SECRET=change-moi-aussi
ADMIN_USERNAME=admin
ADMIN_PASSWORD=change-moi-vraiment
```

Génération simple de secrets :

```bash
openssl rand -hex 32
openssl rand -hex 64
```

---

## 4. Premier lancement

```bash
cd /opt/ateliermbz
docker compose build php nuxt caddy
docker compose up -d db php worker nuxt caddy mercure
```

Si des migrations sont en attente :

```bash
docker compose exec -T php php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
```

Contrôle rapide :

```bash
curl -I https://atelier.example.fr/login
curl -I https://atelier.example.fr/api/docs
```

Si tu veux injecter des données de démo :

```bash
docker compose exec php php bin/console app:seed
```

---

## 5. Déploiement automatique par webhook GitHub

Deux fichiers d'exemple sont fournis :

- `scripts/deploy-server.sh`
- `scripts/github-webhook-listener.py`

### Rôle des scripts

- `deploy-server.sh` : met à jour la branche, rebuild l'app, relance les conteneurs et exécute les migrations
- `github-webhook-listener.py` : écoute un webhook GitHub, valide la signature HMAC et déclenche le script de déploiement

---

## 6. Préparer le serveur pour le webhook

```bash
cd /opt/ateliermbz
chmod +x scripts/deploy-server.sh
export WEBHOOK_SECRET="une-cle-longue-et-aleatoire"
python3 scripts/github-webhook-listener.py
```

Par défaut, l'écoute se fait sur le port `9010` avec le chemin :

- `http://IP_DU_SERVEUR:9010/github-webhook`

Pour un vrai usage serveur, passe par `systemd`.

---

## 7. Service systemd recommandé

Créer le fichier `/etc/systemd/system/ateliermbz-webhook.service` :

```ini
[Unit]
Description=AtelierMBZ GitHub Webhook Listener
After=network.target docker.service
Requires=docker.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/opt/ateliermbz
Environment=PROJECT_DIR=/opt/ateliermbz
Environment=BRANCH=main
Environment=WEBHOOK_SECRET=remplace_par_un_secret_fort
ExecStart=/usr/bin/python3 /opt/ateliermbz/scripts/github-webhook-listener.py
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Puis :

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now ateliermbz-webhook.service
sudo systemctl status ateliermbz-webhook.service
```

---

## 8. Configurer le webhook dans GitHub

Dans le dépôt GitHub :

- ouvrir `Settings` → `Webhooks` → `Add webhook`
- renseigner :
  - **Payload URL** : `https://atelier.example.fr/github-webhook` si tu le proxifies, sinon l'URL/port exposé
  - **Content type** : `application/json`
  - **Secret** : la même valeur que `WEBHOOK_SECRET`
  - **Events** : `Just the push event`

Le listener ne déploie que les pushes de la branche configurée via `BRANCH`.

---

## 9. Sécurisation minimale à ne pas zapper

1. Utiliser un vrai domaine et HTTPS
2. Changer tous les secrets du fichier `.env`
3. Restreindre le port `9010` par firewall ou reverse proxy
4. Exécuter le listener avec un utilisateur dédié
5. Ne jamais faire tourner le webhook avec un accès root inutile

---

## 10. Commandes utiles en exploitation

```bash
cd /opt/ateliermbz

docker compose ps
docker compose logs -f php
docker compose logs -f nuxt
docker compose logs -f caddy
```

Relancer manuellement un déploiement :

```bash
./scripts/deploy-server.sh
```

---

## 11. Point d'attention connu

Pendant le build Nuxt, un warning peut apparaître sur le chargement de la police Google Inter. Dans les vérifications actuelles, cela n'empêche pas le build ni le démarrage. Si ton serveur sort mal sur Internet, il faudra éventuellement passer cette police en local plus tard.
