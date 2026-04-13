# Accès à la Base de Données - Atelier Moto

## 🗄️ Coordonnées de Connexion

```
Host: localhost
Port: 5432
Database: atelier_moto
User: atelier
Password: atelier
```

**Connection String**:
```
postgresql://atelier:atelier@localhost:5432/atelier_moto
```

---

## 🔑 Méthode 1: CLI PostgreSQL (Terminal)

### Le plus rapide pour requêtes simples

```bash
cd /root/AtelierMBZ/ateliermoto-main

# Accès au shell psql
docker compose exec -T db psql -U atelier -d atelier_moto

# Vous êtes maintenant dans PostgreSQL shell
atelier_moto=#
```

### Commandes utiles dans psql

```sql
-- Lister les 10 premiers clients
SELECT * FROM clients LIMIT 10;

-- Compter les prestations
SELECT COUNT(*) FROM prestations;

-- Voir toutes les tables
\dt

-- Quitter
\q
```

### Export/Import depuis CLI

```bash
# Faire un backup
docker compose exec -T db pg_dump -U atelier -d atelier_moto > backup_manual.sql

# Restaurer
docker compose exec -T db psql -U atelier -d atelier_moto < backup_manual.sql
```

---

## 🖥️ Méthode 2: pgAdmin (Interface Web)

### Démarrer pgAdmin

⏳ **pgAdmin est en cours de démarrage...**

```bash
# Si vous voulez le relancer
docker restart pgadmin-atelier
```

### Accès Web

Une fois prêt:
- **URL**: http://127.0.0.1:5050
- **Email**: admin@ateliermoto.app
- **Password**: admin123

### Configurer une connexion serveur

1. Cliquer sur "Add New Server"
2. Onglet "General":
   - Name: `Atelier Moto Local`
3. Onglet "Connection":
   - Host: `db` (depuis Docker) ou `localhost` (depuis host)
   - Port: `5432`
   - Username: `atelier`
   - Password: `atelier`
   - Database: `atelier_moto`
4. Cliquer "Save"

---

## 🧑‍💻 Méthode 3: DBeaver (Desktop App)

### Installation

```bash
# Sur Linux
sudo apt install dbeaver-ce

# Sur macOS
brew install dbeaver-community

# Sur Windows
# Télécharger depuis https://dbeaver.io/download/
```

### Configuration

1. File → New Database Connection
2. Select PostgreSQL → Next
3. Remplir:
   - Host: `localhost`
   - Port: `5432`
   - Database: `atelier_moto`
   - Username: `atelier`
   - Password: `atelier`
4. Test Connection → Finish

---

## 📊 Structure de la Base de Données

### Tables principales

```sql
-- Voir la structure complète
\d prestations
\d grille_tarifaire
\d clients
\d rendez_vous
\d vehicules
\d users
```

### Statistiques

```sql
SELECT 
  'Clients' as entity, COUNT(*) as count FROM clients
UNION ALL
SELECT 'Prestations', COUNT(*) FROM prestations
UNION ALL
SELECT 'Rendez-vous', COUNT(*) FROM rendez_vous
UNION ALL
SELECT 'Grille Tarifaire', COUNT(*) FROM grille_tarifaire
UNION ALL
SELECT 'Véhicules', COUNT(*) FROM vehicules
UNION ALL
SELECT 'Utilisateurs', COUNT(*) FROM users
ORDER BY count DESC;
```

---

## ✅ Tests de Connectivité

### Vérifier que la DB est accessible

```bash
# Depuis la machine host
docker compose exec -T db pg_isready -U atelier -d atelier_moto

# Résultat attendu: "accepting connections"

# Tester une requête
docker compose exec -T db psql -U atelier -d atelier_moto -c "SELECT version();"
```

---

## 🔒 Secrets / Configuration

Les coordonnées sont aussi disponibles dans:

```bash
# Voir les variables d'environnement
cat .env | grep DATABASE

# Voir la connection string dans docker-compose
docker compose config | grep DATABASE_URL
```

---

## 🚨 Troubleshooting

### "Connection refused"

```bash
# Vérifier que la DB est en running
docker compose ps db

# Redémarrer si nécessaire
docker compose restart db
```

### "Password authentication failed"

- Vérifier les credentials: `atelier` / `atelier`
- Vérifier la base: `atelier_moto`
- Vérifier l'host: `localhost` (host) ou `db` (Docker)

### pgAdmin ne démarre pas

```bash
# Vérifier les logs
docker logs pgadmin-atelier

# Redémarrer
docker restart pgadmin-atelier

# Ou relancer
docker rm pgadmin-atelier
docker run -d -p 5050:80 \
  -e PGADMIN_DEFAULT_EMAIL=admin@ateliermoto.app \
  -e PGADMIN_DEFAULT_PASSWORD=admin123 \
  --name pgadmin-atelier \
  --network ateliermoto-main_default \
  dpage/pgadmin4
```

---

## 📋 Résumé Rapide

| Besoin | Commande/Lien |
|--------|--------------|
| **CLI rapide** | `docker compose exec -T db psql -U atelier -d atelier_moto` |
| **Interface Web** | http://127.0.0.1:5050 |
| **Vérifier accès** | `docker compose exec -T db pg_isready -U atelier -d atelier_moto` |
| **Backup** | `docker compose exec -T db pg_dump -U atelier -d atelier_moto > backup.sql` |
| **Restore** | `docker compose exec -T db psql -U atelier -d atelier_moto < backup.sql` |

