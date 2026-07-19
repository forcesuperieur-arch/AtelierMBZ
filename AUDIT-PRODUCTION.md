# Audit « avant grand jour » — Paddock

_Passe de préparation au pilote (2026-07-19). Objectif : lister ce qui est prêt,
ce qui doit être réglé avant d'ouvrir à de vrais clients, et ce qui dépend de tes
décisions métier. Complète [DEPLOIEMENT.md](DEPLOIEMENT.md) (la marche à suivre)._

## Synthèse

Le socle de déploiement est **mûr et bien pensé** : HTTPS automatique, surface
publique strictement verrouillée, tâches RGPD planifiées, déploiement scripté.
Le principal manque avant d'accueillir de vrais clients était **l'absence de
sauvegardes automatisées** — corrigé dans cette passe. Il reste surtout des
**décisions métier** (hébergement, domaine, SIRET/TVA) et une amélioration serveur
à prévoir dans les semaines suivant la mise en ligne.

## ✅ Ce qui est déjà solide

- **Surface publique verrouillée** (`Caddyfile`) : le domaine public n'expose qu'une
  liste blanche stricte (booking/suivi/demande/portail client/mentions). Tout le
  back-office (planning, admin, companion, API staff, photos brutes) est **invisible
  depuis Internet** (réponse 404). Vérifié.
- **HTTPS** automatique (Caddy + Let's Encrypt) dès que le domaine est posé.
- **RGPD planifié** (`backend/src/Schedule.php`) : purge mensuelle des données,
  purge des pièces d'identité, rappels — tournent dans le worker.
- **Déploiement auto sécurisé** : le webhook GitHub vérifie sa signature (HMAC) —
  pas d'exécution ouverte à n'importe qui.
- **Documents signés** (OR, état des lieux) figés par empreinte + archivage —
  intégrité juridique en place.
- **Secrets** : le fichier `.env` versionné ne contient que des valeurs bidon +
  un avertissement explicite ; les vrais secrets vivent hors dépôt.

## 🔧 À régler avant le grand jour

| # | Sévérité | Point | Statut |
|---|---|---|---|
| 1 | HAUTE | **Sauvegardes automatiques absentes** : la doc promettait « pg_dump quotidien » mais rien ne l'exécutait. | ✅ **Corrigé** — `scripts/backup-db.sh` (dump compressé + rétention 30 j + garde-fou anti-dump-vide), testé OK. À planifier en cron hôte (ligne d'exemple dans le script). |
| 2 | HAUTE | **Dumps de base de données versionnés dans git** (`backups/*.sql`, ~22 Mo, avec des e-mails réels) — risque RGPD/fuite. | ⚠️ **Partiellement corrigé** — fichiers désuivis + ignorés. **Reste à décider** : purge de l'historique git (réécriture — destructif, à faire explicitement) si ces données sont réelles, et suppression sécurisée des fichiers sur disque. |
| 3 | MOYENNE | **Migrations jouées sans sauvegarde préalable** au déploiement (pas de filet en cas de migration ratée). | ✅ **Corrigé** — `deploy-server.sh` sauvegarde la base avant les migrations et s'interrompt si la sauvegarde échoue. |
| 4 | MOYENNE | **Serveur PHP de dev en prod** (`php -S`, 8 workers) : suffisant pour un pilote mono-atelier derrière le rate-limit, mais pas un serveur de prod. | 📋 **Connu/accepté** — basculer vers PHP-FPM (image déjà `php:8.3-fpm`) ou FrankenPHP dans les semaines suivant la mise en ligne. |
| 5 | BASSE | **Pas de supervision d'erreurs** (type Sentry) ni d'alerte : uniquement les logs + le healthcheck du worker. | 📋 Acceptable pour un pilote ; à ajouter si le pilote grossit. |

## 📋 Décisions métier bloquantes (côté cmoreau)

Rien de technique — la mise en ligne attend :
- **Hébergement** (repoussé) : je te ressors le comparatif OVH/Hetzner/Infomaniak.
- **Nom de domaine** à communiquer (à poser dans `PUBLIC_DOMAIN`).
- **SIRET / TVA / hébergeur réels** : à remplir dans `update_clauses.sql` (mentions
  légales) et Admin → Ateliers **avant** exposition.
- **Ne pas lancer les seeds de démo** en prod (comptes de test interdits).

## 🧪 Répétition de déploiement — ce qui a été validé en local

- Migrations Doctrine : s'appliquent proprement (dernière = `Version20260715110000`).
- Surface publique : les routes staff/companion/mercure renvoient 404 sur l'edge
  public ; `/api/client/me` → 401 ; le portail `/client` répond. Conforme.
- Sauvegarde : `scripts/backup-db.sh` produit un dump PostgreSQL valide (testé).
- Suite E2E complète au vert (216+ tests).

**Reste à répéter le jour J** (nécessite le serveur cible) : provisionnement TLS
Let's Encrypt sur le vrai domaine, envoi d'un email/SMS de test sur une vraie boîte,
et le premier passage du scheduler (rappels J-1, purge RGPD) au matin.
