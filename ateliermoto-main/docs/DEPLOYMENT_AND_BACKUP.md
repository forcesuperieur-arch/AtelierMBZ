# Déploiement et Procédures de Sauvegarde - Atelier Moto

Date du déploiement: **13 avril 2026**  
Version: **2.0.0 (réunifié tarification)**

---

## 📋 Vue d'ensemble

Ce document décrit les procédures de déploiement, sauvegarde et restauration de la base de données pour l'application Atelier Moto.

### Modifications incluses
- ✅ Unification du système tarifaire sur `Prestation/GrilleTarifaire`
- ✅ Moteur de tarification centralisé dans `services/pricing_rules.py`
- ✅ Toggles par type de moto via `is_active` dans `GrilleTarifaire`
- ✅ Nettoyage des routes legacy (`GrilleTarifs`, `ForfaitMO`)

---

## 🚀 Déploiement Docker

### Prérequis
- Docker & Docker Compose installés
- Fichier `.env` configuré (voir template `.env.example`)
- Au minimum 5GB d'espace disque disponible

### Commandes principales

#### 1. Démarrer les services
```bash
cd /root/AtelierMBZ/ateliermoto-main

# Build et démarrage en une commande
docker compose up -d --build

# Vérifier l'état
docker compose ps
```

#### 2. Arrêter les services
```bash
docker compose down
```

#### 3. Vues les logs
```bash
# Backend
docker compose logs -f backend

# Database
docker compose logs -f db

# Tout
docker compose logs -f
```

---

## 💾 Sauvegarde de la Base de Données

### Procédure automatique de backup

Un script de backup existe à `scripts/db-backup.sh`:

```bash
# 1. Backup automatique avec timestamp
./scripts/db-backup.sh

# 2. Backup personnalisé
./scripts/db-backup.sh "mon_backup_personnalise.sql"
```

**Résultat**: Fichier SQL créé dans `backups/`

### Backup avant déploiement (procédure recommandée)

```bash
# 1. Vérifier que Docker est running
docker compose ps

# 2. Faire le backup
./scripts/db-backup.sh "pre_deploy_$(date +%Y%m%d_%H%M%S).sql"

# 3. Vérifier le backup
ls -lh backups/*.sql | tail -1
```

**Exemple de sortie**:
```
✅ Backup terminé (5.4M)
   Fichier: /root/AtelierMBZ/ateliermoto-main/backups/pre_deploy_20260413_201135.sql
```

---

## 🔄 Restauration de la Base de Données

### Procédure de restauration

```bash
# 1. Lister les backups disponibles
./scripts/db-restore.sh

# 2. Restaurer un backup spécifique
./scripts/db-restore.sh pre_deploy_20260413_201135.sql

# 3. Confirmer à la demande (y/N)
```

### ⚠️ Avertissements
- **Les données actuelles seront écrasées**
- **Cette opération est IRRÉVERSIBLE** sans autre backup
- **Certifier d'avoir testé le processus en développement**

### Script de restauration
```bash
#!/bin/bash
# Si vous devez restaurer manuellement:
BACKUP_FILE="backups/pre_deploy_20260413_201135.sql"
docker compose exec -T db psql -v ON_ERROR_STOP=1 -U atelier -d atelier_moto < "$BACKUP_FILE"
```

---

## 📊 Vérification post-déploiement

### Health checks

```bash
# 1. Backend API
curl -s http://localhost:8000/api/health | jq .

# 2. Tarifs configuration
curl -s http://localhost:8000/api/config/taux-mo | jq .

# 3. Prestations
curl -s http://localhost:8000/api/prestations | jq '.[0:2]'

# 4. Database
docker compose exec -T db psql -U atelier -d atelier_moto \
  -c "SELECT COUNT(*) as prestations FROM prestation;"
```

**Expected Health Response**:
```json
{
  "status": "ok",
  "service": "atelier-moto-api",
  "version": "2.0.0"
}
```

### Logs de démarrage
```bash
docker compose logs --tail=50 backend
```

---

## 🔧 Archivage des backups

### Espace disque
```bash
# Voir taille des backups
du -sh backups/

# Nettoyer les anciens backups (> 30 jours)
find backups/ -name "*.sql" -mtime +30 -delete
```

### Structure de sauvegarde recommandée
```
backups/
├── pre_deploy_20260413_201135.sql      (5.4M)
├── pre_deploy_20260412_150820.sql      (5.2M)
├── monthly_backup_2026_03.sql          (5.1M)
└── monthly_backup_2026_02.sql          (4.9M)
```

---

## 📈 Tarification - Changements en 2.0.0

### Système unifié (2.0)
- Single source: `Prestation` + `GrilleTarifaire`
- Moteur centralisé: `services/pricing_rules.py`
- Toggles par moto: `GrilleTarifaire.is_active`

### Routes de tarification active
```
GET  /api/prestations                          # Lister prestations
GET  /api/config/prestations                   # Admin config
POST /api/config/prestations                   # Créer prestation
PUT  /api/config/prestations/{id}              # Modifier prestation
PUT  /api/config/prestations/{id}/grille       # Grille tarifaire
POST /api/tarifs/calculer                      # Calcul prix
GET  /api/tarifs/synthese                      # Synthèse
GET  /api/config/taux-mo                       # Taux horaires
```

### Routes DEPRECATED (à ne plus utiliser)
```
❌ GET /api/forfaits-mo/* (désactivé)
❌ GET/POST/PUT/DELETE /tarifs (supprimé)
```

---

## 🆘 Troubleshooting

### Backend ne démarre pas

```bash
# 1. Vérifier les logs
docker compose logs backend

# 2. Vérifier la DB
docker compose logs db

# 3. Reset et rebuild
docker compose down
docker compose up -d --build
```

### Erreur de connexion à la DB

```bash
# 1. Vérifier que DB est running
docker compose exec db pg_isready -U atelier

# 2. Vérifier les credentials
docker compose exec db psql -U atelier -d atelier_moto -c "SELECT 1;"

# 3. Restaurer depuis backup si nécessaire
./scripts/db-restore.sh <backup_file>
```

### Espace disque plein

```bash
# Nettoyer les anciens backups
rm backups/*.sql.bak
docker system prune -a

# Recréer le backup soigneusement
./scripts/db-backup.sh
```

---

## ✅ Checklist de déploiement

- [ ] Backup de la BDD effectué avant déploiement
- [ ] `docker compose ps` montre tous les services UP
- [ ] Health check répond: `curl http://localhost:8000/api/health`
- [ ] Endpoints tarifaires testés
- [ ] Backup peut être listé: `./scripts/db-restore.sh`
- [ ] Documentation sauvegardée localement
- [ ] Équipe informée des changements tarifaires

---

## 📝 Historique des déploiements

| Date | Version | Changement principal | Backup | Status |
|------|---------|----------------------|--------|--------|
| 2026-04-13 | 2.0.0 | Unification tarifs | pre_deploy_20260413_201135.sql | ✅ OK |

---

## 📞 Support

Pour toute question sur le backup/restore:
- Vérifier les logs: `docker compose logs -f`
- Consulter les scripts: `scripts/db-backup.sh`, `scripts/db-restore.sh`
- Revenir à la version précédente: `./scripts/db-restore.sh <previous_backup>`
