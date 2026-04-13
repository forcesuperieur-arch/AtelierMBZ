# Guide d'installation preprod

Mise a jour : 13 avril 2026

## Objectif

Ce guide installe une preproduction complete avec tous les conteneurs necessaires :

- PostgreSQL
- service d'initialisation BDD automatique
- backend FastAPI
- reverse proxy Caddy
- MailHog pour capter les emails de test

L'objectif est d'avoir un premier demarrage en une seule commande apres la copie du fichier d'environnement.

Important : par defaut, cette installation cree une base propre a partir des migrations et des seeds metier essentiels. Si vous voulez charger la base actuelle, il faut restaurer un dump SQL apres le premier demarrage.

## Ce qui a ete prepare pour la preprod

La stack Docker a ete simplifiee pour eviter les etapes manuelles :

- le backend est construit avec le frontend inclus dans l'image
- la BDD est migree automatiquement par le service `init`
- les seeds metier et le compte admin initial sont joues automatiquement par `init`
- le backend ne rejoue pas ce bootstrap a chaque redemarrage preprod
- le sync complet des fiches techniques moto n'est pas bloque dans le premier demarrage preprod
- les photos, logos, signatures et secrets sont persistants dans des volumes Docker
- les sauvegardes sont ecrites dans le dossier local `backups/`
- l'application est servie derriere Caddy sur le port 80
- MailHog est expose sur le port 8025 pour verifier les emails sans risque de partir en vrai SMTP

## Prerequis serveur

- Linux 64 bits
- Docker Engine installe
- Docker Compose v2 disponible via `docker compose`
- au moins 4 Go de RAM recommandes
- au moins 10 Go libres sur disque
- ports 80 et 8025 disponibles

Verification rapide :

```bash
docker --version
docker compose version
```

## Installation pas a pas

Depuis le dossier `ateliermoto-main/` :

### 1. Copier l'environnement

```bash
cp .env.example .env
```

### 2. Editer les variables minimales

Ouvrir `.env` et verifier au minimum :

- `POSTGRES_PASSWORD`
- `DATABASE_URL`
- `BASE_URL`
- `CORS_ORIGINS`
- `ADMIN_USERNAME`
- `ADMIN_PASSWORD`

Exemple si la preprod repond sur l'IP du serveur :

```env
BASE_URL=http://203.0.113.10
CORS_ORIGINS=http://203.0.113.10
COOKIE_SECURE=false
```

Exemple si la preprod repond sur un sous-domaine :

```env
BASE_URL=http://preprod.exemple.fr
CORS_ORIGINS=http://preprod.exemple.fr
COOKIE_SECURE=false
```

Important : avec cette configuration fournie, Caddy sert la preprod en HTTP sur le port 80. Pour cette raison, `COOKIE_SECURE` doit rester a `false` tant que HTTPS n'est pas mis en place.

### 3. Demarrer toute la stack

```bash
docker compose up -d --build
```

Au premier lancement, Docker cree automatiquement :

- le conteneur `atelier-db`
- le conteneur one-shot `atelier-init`
- le conteneur `atelier-backend`
- le conteneur `atelier-caddy`
- le conteneur `atelier-mailhog`
- les volumes Docker de persistance

### 4. Verifier que l'initialisation est terminee

```bash
docker compose ps
docker compose logs init
docker compose logs backend --tail=100
```

Le conteneur `init` doit sortir avec succes. C'est normal qu'il s'arrete ensuite : il ne sert qu'a preparer la base.

### 5. Verifier l'application

```bash
curl http://127.0.0.1/api/health
curl -I http://127.0.0.1/
```

Depuis votre navigateur :

- application : `http://IP_OU_DOMAINE/`
- healthcheck : `http://IP_OU_DOMAINE/api/health`
- emails de test : `http://IP_OU_DOMAINE:8025`

## Charger la base actuelle

Oui, la preprod peut charger la base actuelle, mais ce n'est pas automatique dans le `docker compose up -d --build`.

Le comportement actuel est volontaire :

- `init` prepare une base propre et exploitable
- ensuite on restaure explicitement le dump de la base courante si on veut retrouver les donnees reelles

### Procedure recommandee

1. Exporter la base source en dump SQL
2. Copier le dump dans le dossier `backups/` du projet
3. Demarrer la stack
4. Attendre que `init` soit termine avec succes
5. Restaurer le dump

Exemple :

```bash
cd ateliermoto-main
docker compose up -d --build
docker compose ps
./scripts/db-restore.sh NOM_DU_DUMP.sql
docker compose restart backend
```

### Point d'attention

Si vous restaurez la base actuelle, les donnees du dump remplacent la base preparee par `init`.

Autrement dit :

- pour une preprod vide ou de test rapide : `docker compose up -d --build` suffit
- pour une preprod qui doit reprendre les donnees actuelles : il faut faire la restauration SQL ensuite

## Identifiants du premier compte superadmin

Le compte superadmin est créé automatiquement au premier démarrage si ces variables sont renseignées dans `.env` :

- `ADMIN_USERNAME` : nom d'utilisateur (ex: `admin`)
- `ADMIN_PASSWORD` : mot de passe sécurisé (min 8 caractères, 1 majuscule, 1 chiffre)
- `ADMIN_EMAIL` : email de contact

**Important** : Le mot de passe doit respecter la politique de sécurité (8+ caractères, au moins 1 majuscule et 1 chiffre).

Si l'utilisateur admin existe déjà en base, le bootstrap ne le régénère pas lors des redémarrages.

Exemple de `.env` sécurisé :
```
ADMIN_USERNAME=superadmin
ADMIN_PASSWORD=SecurePass123
ADMIN_EMAIL=admin@atelier-moto.local
```

## Montages et persistance

### Montages utilises

- `./backups:/app/backups` : sauvegardes accessibles depuis l'hote
- volume `postgres_data` : donnees PostgreSQL
- volume `backend_logos` : logos atelier
- volume `backend_photos` : photos d'intervention
- volume `backend_signatures` : signatures clients
- volume `backend_secrets` : secret JWT persistant
- volume `caddy_data` et `caddy_config` : etat Caddy

### Pourquoi c'est important

Sans ces volumes :

- les photos disparaissent au redemarrage
- les signatures disparaissent au redemarrage
- la cle JWT est regeneree et invalide les sessions
- les logos atelier sont perdus
- la base est recreee a vide si le volume PostgreSQL est supprime

## Commandes utiles d'exploitation

### Etat des services

```bash
docker compose ps
docker compose logs -f backend
docker compose logs -f caddy
```

### Redemarrage

```bash
docker compose restart backend caddy
```

### Rebuild complet apres changement de code

```bash
docker compose up -d --build
```

### Synchronisation complete optionnelle des fiches techniques moto

Cette operation est volontairement sortie du bootstrap initial pour eviter qu'une premiere installation reste bloquee trop longtemps sur le referentiel technique detaille.

```bash
docker compose exec backend python seed.py
```

### Sauvegarde BDD

```bash
./scripts/db-backup.sh
```

### Restauration BDD

```bash
./scripts/db-restore.sh NOM_DU_DUMP.sql
```

## Verification de bon fonctionnement

Checklist minimale :

- `docker compose ps` montre `db`, `backend`, `caddy`, `mailhog` en cours d'execution
- `init` est termine avec succes
- `curl http://127.0.0.1/api/health` renvoie `status=ok`
- la page d'accueil charge via Caddy
- l'ecran de login s'affiche
- MailHog est accessible sur le port 8025

## Erreurs de montage et de demarrage possibles

### 1. `.env` absent

Symptome typique :

```text
env file .env not found
```

Cause : le fichier d'environnement n'a pas ete copie.

Correction :

```bash
cp .env.example .env
```

### 2. Mot de passe BDD incoherent entre `POSTGRES_PASSWORD` et `DATABASE_URL`

Symptome typique :

```text
password authentication failed for user
```

Cause : le mot de passe defini pour PostgreSQL n'est pas le meme que celui dans `DATABASE_URL`.

Correction : garder la meme valeur dans les deux variables.

### 3. Port 80 deja occupe

Symptome typique :

```text
Bind for 0.0.0.0:80 failed: port is already allocated
```

Cause : un autre serveur web tourne deja sur la machine.

Correction : arreter le service qui occupe le port 80 ou modifier l'exposition reseau de Caddy.

### 4. Port 8025 deja occupe

Symptome typique :

```text
Bind for 0.0.0.0:8025 failed: port is already allocated
```

Cause : un autre service ecoute deja sur 8025.

Correction : liberer le port ou retirer temporairement l'exposition de MailHog.

### 5. Erreur de montage sur `Caddyfile`

Symptome typique :

```text
not a directory
```

ou

```text
cannot mount because source path does not exist
```

Cause : le chemin `./Caddyfile/Caddyfile` n'existe pas ou n'est pas un fichier.

Correction : verifier que le depot est complet et que le fichier `Caddyfile/Caddyfile` est bien present.

### 6. Erreur de montage ou permission sur `./backups`

Symptome typique :

```text
permission denied
```

ou

```text
read-only file system
```

Cause : le dossier local n'est pas accessible en ecriture par Docker.

Correction : verifier les droits du dossier projet et que le filesystem n'est pas monte en lecture seule.

### 7. Ancien volume PostgreSQL incompatible avec la nouvelle stack

Symptome typique :

```text
relation ... does not exist
```

ou

```text
duplicate column
```

Cause : un ancien volume contient un etat de base incoherent avec les migrations actuelles.

Correction : si c'est une installation neuve uniquement, supprimer les volumes et relancer :

```bash
docker compose down -v
docker compose up -d --build
```

Attention : cette commande supprime la base et les volumes. Ne jamais l'utiliser sur une preprod deja exploitee sans sauvegarde.

### 8. Boucle de login ou cookies non pris en compte

Cause la plus frequente : `BASE_URL`, `CORS_ORIGINS` ou `COOKIE_SECURE` mal renseignes.

Correction :

- `BASE_URL` doit correspondre exactement a l'URL d'acces navigateur
- `CORS_ORIGINS` doit contenir cette meme origine
- `COOKIE_SECURE=false` tant que la preprod est en HTTP

### 9. Le backend reste en `starting`

Cause la plus frequente : le service `init` a echoue, donc le backend n'a jamais recu une base prete.

Correction :

```bash
docker compose logs init
```

Corriger l'erreur remontee puis relancer :

```bash
docker compose up -d --build
```

## Procedure de reinstallation propre

Pour reconstruire la preprod sur un serveur vierge :

```bash
git clone ...
cd ateliermoto-main
cp .env.example .env
docker compose up -d --build
docker compose ps
curl http://127.0.0.1/api/health
```

Si ces commandes passent, la preprod est exploitable.