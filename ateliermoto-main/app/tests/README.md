# Tests Backend - Atelier Moto

Ce répertoire contient les tests pour le backend de l'application Atelier Moto.

## Structure des tests

```
tests/
├── __init__.py              # Fichier d'initialisation
├── conftest.py              # Configuration pytest (fixtures partagées)
├── test_models.py           # Tests unitaires des modèles
├── test_auth.py             # Tests de l'authentification
├── test_api.py              # Tests des endpoints API
├── test_integration.py      # Tests d'intégration
├── test_utils.py            # Tests des utilitaires
└── test_critical_routes.py  # Tests des routes critiques
```

## Installation

```bash
# Installer les dépendances de test
pip install -r requirements-test.txt

# Ou avec les dépendances principales
pip install -r requirements.txt -r requirements-test.txt
```

## Exécution des tests

### Tous les tests

```bash
pytest
```

### Tests spécifiques

```bash
# Tests unitaires uniquement
pytest -m unit

# Tests API uniquement
pytest -m api

# Tests d'authentification
pytest -m auth

# Exclure les tests lents
pytest -m "not slow"
```

### Par fichier

```bash
pytest tests/test_models.py
pytest tests/test_api.py
pytest tests/test_auth.py
```

### Avec couverture

```bash
pytest --cov=.
pytest --cov=. --cov-report=html
pytest --cov=. --cov-report=xml
```

### Avec verbose

```bash
pytest -v
pytest -vv  # Plus de détails
```

## Marqueurs (markers)

Les tests sont marqués avec les tags suivants :

- `unit` : Tests unitaires (modèles, fonctions utilitaires)
- `api` : Tests d'API (endpoints HTTP)
- `integration` : Tests d'intégration (workflows complets)
- `auth` : Tests d'authentification
- `slow` : Tests lents à exécuter
- `critical` : Tests des routes critiques

## Configuration

La configuration pytest se trouve dans :

- `pytest.ini` : Configuration principale
- `setup.cfg` : Configuration alternative

## Fixtures

Les fixtures partagées sont définies dans `conftest.py` :

- `test_client` : Client HTTP de test
- `auth_token` : Token d'authentification valide
- `db_session` : Session de base de données de test

## Bonnes pratiques

1. **Isolation** : Chaque test doit être indépendant
2. **Nommage** : `test_<fonctionnalité>_<scenario>`
3. **Documentation** : Docstring décrivant ce qui est testé
4. **Assertions** : Une seule assertion logique par test
5. **Données** : Utiliser des fixtures pour les données de test

## Exemple de test

```python
import pytest
from models import Client

class TestClient:
    """Tests pour le modèle Client"""
    
    def test_client_creation(self):
        """Test la création d'un client avec données valides"""
        client = Client(
            nom="Dupont",
            prenom="Jean",
            telephone="0612345678"
        )
        assert client.nom == "Dupont"
        assert client.prenom == "Jean"
    
    def test_client_invalid_phone(self):
        """Test qu'un téléphone invalide est rejeté"""
        with pytest.raises(ValueError):
            Client(telephone="invalid")
```

## Dépannage

### Erreur de base de données

```bash
# Supprimer et recréer les tables de test
rm test.db  # Si SQLite
pytest
```

### Tests qui échouent aléatoirement

- Vérifier l'isolation des tests
- Vérifier les fixtures
- Utiliser `pytest-repeat` pour reproduire

### Couverture insuffisante

```bash
# Générer un rapport de couverture
pytest --cov=. --cov-report=html
# Ouvrir htmlcov/index.html
```

## CI/CD

Pour intégrer les tests dans une pipeline CI/CD :

```yaml
# Exemple GitHub Actions
- name: Run tests
  run: |
    pip install -r requirements-test.txt
    pytest --cov=. --cov-report=xml

- name: Upload coverage
  uses: codecov/codecov-action@v3
  with:
    file: ./coverage.xml
```
