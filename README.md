# Paddock

Application de gestion d’atelier moto pour la réception, le suivi des RDV, les ordres de réparation, les notifications et le pilotage atelier.

> Mise à jour projet : **20 avril 2026**

---

## État actuel du projet

Le socle principal est **opérationnel en local** sur la stack Symfony + Nuxt.
Le projet a fortement évolué ces derniers jours sur les axes suivants : sécurité, authentification, rôles métier, workflow RDV/OR, notifications, mode compagnon PDA et OCR documents véhicule.

### En production locale aujourd’hui
- planning atelier et gestion des RDV
- workflow métier renforcé avec motifs obligatoires sur annulation / no-show
- OR signés, gelés, vérifiables par empreinte
- demandes de travaux complémentaires avec validation client
- notifications web + email + SMS côté application
- mode compagnon public avec photos, checkup, signature et OCR
- OCR renforcé pour cartes grises **françaises et belges**, y compris le **VIN**

---

## Stack technique

| Composant | Technologie |
|-----------|------------|
| Backend | Symfony 7.2, PHP 8.3, API Platform 4.1 |
| ORM | Doctrine ORM |
| Frontend | Nuxt 3, Vue 3, Nuxt UI, Pinia |
| Base de données | PostgreSQL 15 |
| Auth | JWT en cookies HttpOnly + Google SSO |
| Async | Symfony Messenger + worker |
| Reverse proxy | Caddy 2 |
| Temps réel / diffusion | Mercure |
| Email dev | MailHog |
| Conteneurisation | Docker Compose |

---

## Changements livrés récemment

### 1. Authentification et rôles
- passage à **RoleMetier** comme source métier principale des permissions
- simplification des rôles et protection du dernier super admin
- ajout du **Google SSO** avec conservation d’un login local de secours pour le dev
- nouveaux comptes Google créés en mode **en attente de validation** tant que l’atelier et le rôle métier ne sont pas attribués
- support du changement d’atelier actif pour le super admin

### 2. Sécurité et conformité RGPD
- sécurisation des accès publics par token
- exposition sécurisée des photos via routes publiques contrôlées
- archivage / anonymisation conformes RGPD au lieu d’une suppression brute
- durcissement des secrets et de la configuration Docker / Caddy
- limitation de débit sur les points d’entrée publics sensibles

### 3. Workflow RDV / OR
- enrichissement du workflow RDV avec états métier avancés
- obligation d’un motif sur annulation et déclaration no-show
- traçabilité métier des changements critiques
- OR signés avec hash, snapshot, gel des modifications et rectification encadrée
- blocages métiers si la chaîne de preuve n’est pas complète

### 4. Notifications
- notifications **web** dans l’interface
- cloche d’historique en top bar avec compteur des non lues
- notifications **email** et **SMS** avec configuration des providers
- scope par **atelier actif** pour les utilisateurs multi-atelier
- environnement dev validé avec MailHog

### 5. Mode compagnon / PDA
- correction du flux de signature client
- correction des URLs photo publiques sécurisées
- meilleure remontée des erreurs côté écran mobile
- amélioration du flux OCR document véhicule
- support OCR pour :
  - plaque
  - marque
  - modèle
  - année
  - cylindrée
  - type
  - **VIN**
- prise en charge des cartes grises **FR + BE**

---

## Modules disponibles

- **Stat** — indicateurs atelier et pilotage
- **Rendez-vous** — création, suivi, transitions, historique
- **Planning** — vue atelier et lien compagnon
- **Clients / véhicules** — fiches et historique
- **Ordres de réparation** — signature, preuve, contrôle d’intégrité
- **Rapports / intervention** — workflow mécanicien et contrôle de fin
- **Notifications** — web, email, SMS
- **Administration** — utilisateurs, rôles métier, configuration
- **Public** — réservation, suivi tokenisé, compagnon réception

---

## Démarrage rapide

### Avec Docker

```bash
cd /root/AtelierMBZ
docker compose up -d
```

Accès locaux :
- application : http://localhost
- API : http://localhost/api
- MailHog : http://127.0.0.1:8025

### Commandes utiles

```bash
# seed démo
docker compose exec php php bin/console app:seed

# tests backend
docker compose exec -T -e APP_ENV=test php php bin/phpunit

# frontend
cd frontend && npm run dev
```

### Windows
Les scripts suivants sont prévus à la racine :
- start.bat
- stop.bat
- reset.bat
- seed-demo.bat

---

## Ce qu’il reste à faire

Les points suivants restent prioritaires pour la fin de projet :

### Priorité haute
- finaliser les derniers écrans et règles métier autour des **pièces / stock / fournisseurs**
- consolider les flux **stockage / gardiennage** et les cas atelier plus rares
- renforcer encore les tests E2E sur les parcours critiques

### Priorité moyenne
- finaliser les réglages de production pour les providers réels :
  - Google OAuth
  - email sortant
  - SMS
- compléter la documentation d’exploitation et de mise en service
- poursuivre le polissage UX sur certains écrans admin et atelier

### Priorité basse / amélioration continue
- couverture de tests plus large
- optimisation des messages d’erreur et de l’observabilité
- durcissement supplémentaire pour l’hébergement SI entreprise

---

## Référence documentaire

La référence technique détaillée reste dans le dossier docs, notamment :
- docs/TECHNICAL.md
- docs/DEPLOIEMENT-SERVEUR-WEBHOOK.md
- SPECIFICATIONS.md

---

## Licence

Projet propriétaire — Atelier Moto Pro
