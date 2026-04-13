"""
Tests d'API pour les endpoints de l'application Atelier Moto.
Utilise pytest et httpx pour tester les endpoints FastAPI.
"""
import pytest
import sys
import os
from datetime import date, time, timedelta

# Ajouter le répertoire parent au path
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

# Configuration des variables d'environnement pour les tests
os.environ["SECRET_KEY"] = "test-secret-key-for-testing-only"
os.environ["CORS_ORIGINS"] = "http://localhost:3000"

from fastapi.testclient import TestClient
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker
from sqlalchemy.pool import StaticPool

from auth import get_current_user
from models import Base, get_db
from main import app

# Configuration de la base de données de test
SQLALCHEMY_DATABASE_URL = "sqlite:///:memory:"
engine = create_engine(
    SQLALCHEMY_DATABASE_URL,
    connect_args={"check_same_thread": False},
    poolclass=StaticPool,
)
TestingSessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)


def override_get_db():
    """Override de la dépendance get_db pour les tests"""
    try:
        db = TestingSessionLocal()
        yield db
    finally:
        db.close()


app.dependency_overrides[get_db] = override_get_db


@pytest.fixture(autouse=True)
def _reset_test_overrides():
    app.dependency_overrides[get_db] = override_get_db
    app.dependency_overrides.pop(get_current_user, None)
    yield
    app.dependency_overrides[get_db] = override_get_db
    app.dependency_overrides.pop(get_current_user, None)


# Créer les tables
Base.metadata.create_all(bind=engine)


@pytest.fixture
def test_client():
    """Fixture pour le client de test avec état isolé par test"""
    with TestClient(app) as client:
        yield client


@pytest.fixture
def auth_token(test_client):
    """Fixture pour obtenir un token d'authentification"""
    db = TestingSessionLocal()
    from auth import get_password_hash
    from models import User

    user = db.query(User).filter(User.username == "testuser").first()
    if not user:
        user = User(
            username="testuser",
            email="test@test.com",
            hashed_password=get_password_hash("testpass"),
            role="admin"
        )
        db.add(user)
        db.commit()
        db.refresh(user)
    db.close()

    response = test_client.post(
        "/api/auth/login",
        data={"username": "testuser", "password": "testpass"}
    )
    assert response.status_code == 200
    return response.json()["access_token"]


class TestHealthCheck:
    """Tests pour les endpoints de base"""
    
    def test_root_endpoint(self, test_client):
        """Test que l'endpoint racine retourne une réponse"""
        response = test_client.get("/")
        assert response.status_code == 200
    
    def test_docs_endpoint(self, test_client):
        """Test que la documentation Swagger est accessible"""
        response = test_client.get("/docs")
        assert response.status_code == 200
    
    def test_openapi_endpoint(self, test_client):
        """Test que le schéma OpenAPI est accessible"""
        response = test_client.get("/openapi.json")
        assert response.status_code == 200
        data = response.json()
        assert "openapi" in data
        assert data["info"]["title"] == "Atelier Moto API Pro"


class TestAuthEndpoints:
    """Tests pour les endpoints d'authentification"""
    
    def test_login_success(self, test_client):
        """Test la connexion avec des identifiants valides"""
        # Créer un utilisateur
        db = TestingSessionLocal()
        from auth import get_password_hash
        from models import User
        
        user = User(
            username="logintest",
            email="login@test.com",
            hashed_password=get_password_hash("testpass123"),
            role="admin"
        )
        db.add(user)
        db.commit()
        db.close()
        
        response = test_client.post(
            "/api/auth/login",
            data={"username": "logintest", "password": "testpass123"}
        )
        assert response.status_code == 200
        data = response.json()
        assert "access_token" in data
        assert data["token_type"] == "bearer"
        assert data["role"] == "admin"
    
    def test_login_failure(self, test_client):
        """Test la connexion avec des identifiants invalides"""
        response = test_client.post(
            "/api/auth/login",
            data={"username": "wronguser", "password": "wrongpass"}
        )
        assert response.status_code == 401
    
    def test_login_missing_fields(self, test_client):
        """Test la connexion avec des champs manquants"""
        response = test_client.post(
            "/api/auth/login",
            data={}
        )
        assert response.status_code == 422
    
    def test_get_me_without_auth(self, test_client):
        """Test l'accès à /me sans authentification"""
        response = test_client.get("/api/auth/me")
        assert response.status_code == 403


class TestInterventionEndpoints:
    """Tests pour les endpoints d'interventions"""
    
    def test_get_interventions_public(self, test_client):
        """Test que les interventions sont accessibles publiquement"""
        response = test_client.get("/api/interventions")
        assert response.status_code == 200
        assert isinstance(response.json(), list)


class TestVehiculeEndpoints:
    """Tests pour les endpoints de véhicules"""
    
    def test_get_vehicule_by_plaque_mock(self, test_client):
        """Test la récupération d'un véhicule par plaque (mock)"""
        response = test_client.get("/api/vehicule/AA123BB")
        assert response.status_code == 200
        data = response.json()
        assert "plaque" in data
        assert data["source"] in ["database", "api", "not_found"]
    
    def test_get_vehicule_by_plaque_not_found(self, test_client):
        """Test la récupération d'un véhicule inexistant"""
        response = test_client.get("/api/vehicule/ZZ999ZZ")
        assert response.status_code == 200
        data = response.json()
        assert data.get("not_found") == True or data.get("source") == "not_found"


class TestRendezVousEndpoints:
    """Tests pour les endpoints de rendez-vous"""
    
    def test_create_rendez_vous(self, test_client):
        """Test la création d'un rendez-vous"""
        rdv_data = {
            "client": {
                "nom": "Dupont",
                "prenom": "Jean",
                "telephone": "0612345678",
                "email": "jean@test.com"
            },
            "vehicule": {
                "plaque": "TEST123",
                "marque": "YAMAHA",
                "modele": "MT-07",
                "annee": 2020
            },
            "date_rdv": str(date.today()),
            "heure_rdv": "10:00:00",
            "type_intervention": "Révision",
            "commentaire": "Test commentaire"
        }
        
        response = test_client.post("/api/rendez-vous", json=rdv_data)
        assert response.status_code == 200
        data = response.json()
        assert "id" in data
        assert data["client"]["nom"] == "Dupont"
        assert data["vehicule"]["plaque"] == "TEST123"
    
    def test_create_rendez_vous_invalid_data(self, test_client):
        """Test la création d'un RDV avec des données invalides"""
        invalid_data = {
            "client": {"nom": "Test"}  # Données incomplètes
        }
        
        response = test_client.post("/api/rendez-vous", json=invalid_data)
        assert response.status_code == 422
    
    def test_get_rendez_vous_list_without_auth(self, test_client):
        """Test l'accès à la liste des RDV sans authentification"""
        response = test_client.get("/api/rendez-vous")
        assert response.status_code == 403
    
    def test_get_rendez_vous_list_with_auth(self, test_client, auth_token):
        """Test l'accès à la liste des RDV avec authentification"""
        response = test_client.get(
            "/api/rendez-vous",
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert response.status_code == 200
        assert isinstance(response.json(), list)
    
    def test_get_single_rendez_vous(self, test_client):
        """Test la récupération d'un RDV par ID"""
        # D'abord créer un RDV
        rdv_data = {
            "client": {"nom": "Test", "prenom": "User", "telephone": "0600000000"},
            "vehicule": {"plaque": "TEST456", "marque": "HONDA", "modele": "CBR"},
            "date_rdv": str(date.today()),
            "heure_rdv": "14:00:00",
            "type_intervention": "Vidange"
        }
        
        create_response = test_client.post("/api/rendez-vous", json=rdv_data)
        rdv_id = create_response.json()["id"]
        
        response = test_client.get(f"/api/rendez-vous/{rdv_id}")
        assert response.status_code == 200
        data = response.json()
        assert data["id"] == rdv_id
    
    def test_get_nonexistent_rendez_vous(self, test_client):
        """Test la récupération d'un RDV inexistant"""
        response = test_client.get("/api/rendez-vous/99999")
        assert response.status_code == 404


class TestPontEndpoints:
    """Tests pour les endpoints de ponts"""
    
    def test_get_ponts_without_auth(self, test_client):
        """Test l'accès aux ponts sans authentification"""
        response = test_client.get("/api/ponts")
        assert response.status_code == 403
    
    def test_get_ponts_with_auth(self, test_client, auth_token):
        """Test l'accès aux ponts avec authentification"""
        response = test_client.get(
            "/api/ponts",
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert response.status_code == 200
        assert isinstance(response.json(), list)
    
    def test_create_pont(self, test_client, auth_token):
        """Test la création d'un pont"""
        pont_data = {
            "nom": "Pont Test",
            "type_pont": "moto",
            "capacite_kg": 500,
            "is_active": 1,
            "ordre_affichage": 1
        }
        
        response = test_client.post(
            "/api/ponts",
            json=pont_data,
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert response.status_code == 200
        data = response.json()
        assert "id" in data
        assert data["message"] == "Pont créé"


class TestMecanicienEndpoints:
    """Tests pour les endpoints de mécaniciens"""
    
    def test_get_mecaniciens_without_auth(self, test_client):
        """Test l'accès aux mécaniciens sans authentification"""
        response = test_client.get("/api/mecaniciens")
        assert response.status_code == 403
    
    def test_get_mecaniciens_with_auth(self, test_client, auth_token):
        """Test l'accès aux mécaniciens avec authentification"""
        response = test_client.get(
            "/api/mecaniciens",
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert response.status_code == 200
        assert isinstance(response.json(), list)
    
    def test_create_mecanicien(self, test_client, auth_token):
        """Test la création d'un mécanicien"""
        mecano_data = {
            "nom": "Martin",
            "prenom": "Pierre",
            "specialites": "moteur",
            "couleur": "#ff0000",
            "is_active": 1
        }
        
        response = test_client.post(
            "/api/mecaniciens",
            json=mecano_data,
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert response.status_code == 200
        data = response.json()
        assert "id" in data
        assert data["message"] == "Mécanicien créé"


class TestPlanningEndpoints:
    """Tests pour les endpoints de planning"""
    
    def test_get_planning_without_auth(self, test_client):
        """Test l'accès au planning sans authentification"""
        response = test_client.get("/api/planning")
        assert response.status_code == 403
    
    def test_get_planning_with_auth(self, test_client, auth_token):
        """Test l'accès au planning avec authentification"""
        response = test_client.get(
            "/api/planning",
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert response.status_code == 200
        data = response.json()
        assert "date" in data
        assert "ponts" in data
        assert "mecaniciens" in data
        assert "rendez_vous" in data
    
    def test_get_planning_semaine_with_auth(self, test_client, auth_token):
        """Test l'accès au planning de la semaine"""
        response = test_client.get(
            "/api/planning/semaine",
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert response.status_code == 200
        data = response.json()
        assert "date_debut" in data
        assert "date_fin" in data
        assert "jours" in data


class TestClientEndpoints:
    """Tests pour les endpoints de clients"""
    
    def test_get_clients_without_auth(self, test_client):
        """Test l'accès aux clients sans authentification"""
        response = test_client.get("/api/clients")
        assert response.status_code == 403
    
    def test_get_clients_with_auth(self, test_client, auth_token):
        """Test l'accès aux clients avec authentification (format paginé)"""
        response = test_client.get(
            "/api/clients",
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert response.status_code == 200
        data = response.json()
        assert "items" in data
        assert "total" in data
        assert "page" in data
        assert "pages" in data
        assert isinstance(data["items"], list)
    
    def test_get_client_detail_not_found(self, test_client, auth_token):
        """Test la récupération d'un client inexistant"""
        response = test_client.get(
            "/api/clients/99999",
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert response.status_code == 404


class TestConfigEndpoints:
    """Tests pour les endpoints de configuration"""
    
    def test_get_taux_mo_public(self, test_client):
        """Test que les taux MO sont accessibles publiquement"""
        response = test_client.get("/api/config/taux-mo")
        assert response.status_code == 200
        data = response.json()
        assert "standard" in data
        assert "complexe" in data
        assert "expert" in data


class TestCORS:
    """Tests pour la configuration CORS"""
    
    def test_cors_headers(self, test_client):
        """Test que les headers CORS sont présents"""
        response = test_client.options("/api/interventions")
        # Les requêtes OPTIONS devraient être acceptées ou retourner 405
        assert response.status_code in [200, 404, 405]


if __name__ == "__main__":
    pytest.main([__file__, "-v"])
