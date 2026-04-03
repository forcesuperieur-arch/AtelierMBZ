# Configuration API France Véhicule

## Inscription
1. Aller sur https://france-vehicule.fr/
2. Créer un compte professionnel
3. Souscrire à un forfait (Starter recommandé pour démarrer)

## Clé API
Une fois inscrit, récupérer la clé API dans le tableau de bord et l'ajouter dans le fichier `.env` :

```
FRANCE_VEHICULE_API_KEY=votre_cle_api_ici
```

## Forfaits disponibles
- **Starter** : 50 requêtes/mois gratuites, puis 0.15€/requête
- **Pro** : 500 requêtes/mois pour 29€/mois
- **Entreprise** : Sur devis

## Endpoint API
```
GET https://api.france-vehicule.fr/v1/plaque/{plaque}
Headers: X-API-Key: {votre_cle}
```

## Réponse
```json
{
  "plaque": "AA-123-AA",
  "vin": "VF1...",
  "marque": "RENAULT",
  "modele": "CLIO",
  "version": "CLIO IV 1.5 DCI 90",
  "puissance": 90,
  "energie": "Diesel",
  "date_mise_circulation": "2015-03-15",
  "date_mise_circulation_fr": "15/03/2015"
}
```
