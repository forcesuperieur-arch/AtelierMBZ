# Configuration de l'application Atelier Moto
# Copier ce fichier en .env et remplir les valeurs

# Sécurité JWT
# Option A: définir explicitement SECRET_KEY (recommandé en prod)
# SECRET_KEY=votre-cle-super-secrete-a-changer-en-production-min-32-caracteres
# Option B: laisser vide -> génération auto persistée dans SECRET_KEY_FILE au 1er démarrage
SECRET_KEY_FILE=.secret_key

# Bootstrap admin initial (optionnel): aucun compte par défaut n'est créé sans ces variables
# ADMIN_USERNAME=admin
# ADMIN_PASSWORD=Admin1234
# ADMIN_EMAIL=admin@atelier-moto.fr

# CORS - Domaines autorisés (séparés par des virgules)
# Exemples: http://localhost:3000,https://atelier-moto.fr,https://admin.atelier-moto.fr
CORS_ORIGINS=http://localhost:3000,http://localhost:8080

# Base de données (recommandé: PostgreSQL en docker-compose)
DATABASE_URL=postgresql://atelier:atelier@db:5432/atelier_moto
# Option local SQLite (dev):
# DATABASE_URL=sqlite:///./atelier_moto.db

# API Plaque Immatriculation (optionnel - pour recherche plaque SIV réelle)
# Site: https://apiplaqueimmatriculation.com/ - 39€/mois sans engagement
# API_PLAQUE_IMMATRICULATION_KEY=votre_cle_api_ici
