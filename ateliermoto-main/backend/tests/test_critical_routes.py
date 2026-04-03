"""
Tests pour les endpoints critiques de l'application.
Ces tests vérifient que les routes essentielles fonctionnent correctement.
"""
import pytest
import sys
import os
from datetime import date, time

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

os.environ["SECRET_KEY"] = "test-secret-key-for-testing-only"
os.environ["CORS_ORIGINS"] = "http://localhost:3000"

from fastapi.testclient import TestClient
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker
from sqlalchemy.pool import StaticPool

from models import Base, get_db
from main import app

SQLALCHEMY_DATABASE_URL = "sqlite:///:memory:"
engine = create_engine(
    SQLALCHEMY_DATABASE_URL,
    connect_args={"check_same_thread": False},
    poolclass=StaticPool,
)
TestingSessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)


def override_get_db():
    try:
        db = TestingSessionLocal()
        yield db
    finally:
        db.close()


app.dependency_overrides[get_db] = override_get_db
Base.metadata.create_all(bind=engine)
client = TestClient(app)


def auth_headers_for_role(role: str, username: str):
    """Create or reuse a user and return Authorization headers for that role."""
    db = TestingSessionLocal()
    from auth import get_password_hash
    from models import User

    user = db.query(User).filter(User.username == username).first()
    if not user:
        user = User(
            username=username,
            email=f"{username}@test.local",
            hashed_password=get_password_hash("testpass"),
            role=role,
            atelier_id=1,
            is_active=1,
        )
        db.add(user)
        db.commit()
    else:
        user.role = role
        user.atelier_id = 1
        user.is_active = 1
        db.commit()
    db.close()

    response = client.post(
        "/api/auth/login",
        data={"username": username, "password": "testpass"}
    )
    assert response.status_code == 200
    return {"Authorization": f"Bearer {response.json()['access_token']}"}


@pytest.fixture
def auth_token():
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
    db.close()
    
    response = client.post(
        "/api/auth/login",
        data={"username": "testuser", "password": "testpass"}
    )
    return response.json()["access_token"]


class TestCriticalRoutesAuth:
    """Tests pour les routes critiques d'authentification"""
    
    CRITICAL_AUTH_ROUTES = [
        ("/api/auth/me", "GET"),
        ("/api/rendez-vous", "GET"),
        ("/api/clients", "GET"),
        ("/api/ponts", "GET"),
        ("/api/mecaniciens", "GET"),
        ("/api/planning", "GET"),
        ("/api/planning/semaine", "GET"),
        ("/api/pieces", "GET"),
        ("/api/fournisseurs", "GET"),
        ("/api/commandes", "GET"),
    ]
    
    @pytest.mark.parametrize("route,method", CRITICAL_AUTH_ROUTES)
    def test_auth_routes_require_authentication(self, route, method):
        """Test que les routes protégées nécessitent une authentification"""
        if method == "GET":
            response = client.get(route)
        elif method == "POST":
            response = client.post(route, json={})
        elif method == "PUT":
            response = client.put(route, json={})
        elif method == "DELETE":
            response = client.delete(route)
        
        assert response.status_code in [403, 401], f"Route {route} devrait nécessiter une authentification"
    
    @pytest.mark.parametrize("route,method", CRITICAL_AUTH_ROUTES)
    def test_auth_routes_accept_valid_token(self, route, method, auth_token):
        """Test que les routes protégées acceptent un token valide"""
        headers = {"Authorization": f"Bearer {auth_token}"}
        
        if method == "GET":
            response = client.get(route, headers=headers)
        elif method == "POST":
            response = client.post(route, headers=headers, json={})
        elif method == "PUT":
            response = client.put(route, headers=headers, json={})
        elif method == "DELETE":
            response = client.delete(route, headers=headers)
        
        # La route doit répondre (200, 201, 422 pour validation, etc.)
        # mais pas 403/401 car on est authentifié
        assert response.status_code not in [403, 401], f"Route {route} devrait accepter le token valide"


class TestCriticalRoutesPublic:
    """Tests pour les routes publiques critiques"""
    
    CRITICAL_PUBLIC_ROUTES = [
        ("/", "GET"),
        ("/api/interventions", "GET"),
        ("/api/vehicule/AA123BB", "GET"),
        ("/api/config/taux-mo", "GET"),
    ]
    
    @pytest.mark.parametrize("route,method", CRITICAL_PUBLIC_ROUTES)
    def test_public_routes_accessible(self, route, method):
        """Test que les routes publiques sont accessibles sans authentification"""
        if method == "GET":
            response = client.get(route)
        elif method == "POST":
            response = client.post(route, json={})
        
        # Ces routes ne doivent pas retourner 403
        assert response.status_code != 403, f"Route {route} devrait être publique"


class TestCriticalRoutesRendezVous:
    """Tests critiques pour les routes de rendez-vous"""
    
    def test_create_rendez_vous_route(self):
        """Test la route de création de RDV"""
        rdv_data = {
            "client": {"nom": "Test", "prenom": "Route", "telephone": "0600000000"},
            "vehicule": {"plaque": "ROUTE01", "marque": "YAMAHA", "modele": "MT"},
            "date_rdv": str(date.today()),
            "heure_rdv": "10:00:00",
            "type_intervention": "Révision"
        }
        
        response = client.post("/api/rendez-vous", json=rdv_data)
        assert response.status_code == 200
        data = response.json()
        assert "id" in data
        return data["id"]
    
    def test_get_rendez_vous_route(self):
        """Test la route de récupération d'un RDV"""
        # Créer un RDV d'abord
        rdv_id = self.test_create_rendez_vous_route()
        
        response = client.get(f"/api/rendez-vous/{rdv_id}")
        assert response.status_code == 200
        data = response.json()
        assert data["id"] == rdv_id
    
    def test_update_rendez_vous_route(self, auth_token):
        """Test la route de mise à jour d'un RDV"""
        rdv_id = self.test_create_rendez_vous_route()
        
        update_data = {"statut": "confirme", "kilometrage": 5000}
        response = client.put(
            f"/api/rendez-vous/{rdv_id}",
            json=update_data,
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert response.status_code == 200


class TestCriticalRoutesClient:
    """Tests critiques pour les routes de clients"""
    
    def test_list_clients_route(self, auth_token):
        """Test la route de liste des clients"""
        response = client.get(
            "/api/clients",
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert response.status_code == 200
        assert isinstance(response.json(), list)
    
    def test_search_clients_route(self, auth_token):
        """Test la route de recherche de clients"""
        response = client.get(
            "/api/clients?search=test",
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert response.status_code == 200
        assert isinstance(response.json(), list)


class TestCriticalRoutesPlanning:
    """Tests critiques pour les routes de planning"""
    
    def test_planning_jour_route(self, auth_token):
        """Test la route de planning du jour"""
        response = client.get(
            "/api/planning",
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert response.status_code == 200
        data = response.json()
        assert "date" in data
        assert "ponts" in data
        assert "mecaniciens" in data
        assert "rendez_vous" in data
    
    def test_planning_semaine_route(self, auth_token):
        """Test la route de planning de la semaine"""
        response = client.get(
            "/api/planning/semaine",
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert response.status_code == 200
        data = response.json()
        assert "date_debut" in data
        assert "date_fin" in data
        assert "jours" in data


class TestCriticalRoutesStock:
    """Tests critiques pour les routes de stock"""
    
    def test_pieces_list_route(self, auth_token):
        """Test la route de liste des pièces"""
        response = client.get(
            "/api/pieces",
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert response.status_code == 200
        assert isinstance(response.json(), list)
    
    def test_alertes_stock_route(self, auth_token):
        """Test la route d'alertes de stock"""
        response = client.get(
            "/api/pieces/alertes",
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert response.status_code == 200
        assert isinstance(response.json(), list)
    
    def test_stats_stock_route(self, auth_token):
        """Test la route de statistiques de stock"""
        response = client.get(
            "/api/stats/stock",
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert response.status_code == 200
        data = response.json()
        assert "total_references" in data
        assert "stock_bas" in data
        assert "valeur_stock_ht" in data


class TestCriticalRoutesFournisseurs:
    """Tests critiques pour les routes de fournisseurs"""
    
    def test_fournisseurs_list_route(self, auth_token):
        """Test la route de liste des fournisseurs"""
        response = client.get(
            "/api/fournisseurs",
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert response.status_code == 200
        assert isinstance(response.json(), list)


class TestCriticalRoutesCommandes:
    """Tests critiques pour les routes de commandes"""
    
    def test_commandes_list_route(self, auth_token):
        """Test la route de liste des commandes"""
        response = client.get(
            "/api/commandes",
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert response.status_code == 200
        assert isinstance(response.json(), list)


class TestCriticalRoutesConfig:
    """Tests critiques pour les routes de configuration"""
    
    def test_config_atelier_route(self, auth_token):
        """Test la route de configuration de l'atelier"""
        response = client.get(
            "/api/config/atelier",
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert response.status_code == 200
        data = response.json()
        assert "taux_horaire_mo_standard" in data
    
    def test_taux_mo_public_route(self):
        """Test la route publique des taux MO"""
        response = client.get("/api/config/taux-mo")
        assert response.status_code == 200
        data = response.json()
        assert "standard" in data
        assert "complexe" in data
        assert "expert" in data


class TestCriticalRoutesForfaits:
    """Tests critiques pour les routes de forfaits"""
    
    def test_forfaits_list_route(self, auth_token):
        """Test la route de liste des forfaits"""
        response = client.get(
            "/api/forfaits-mo",
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert response.status_code == 200
        assert isinstance(response.json(), list)


class TestCriticalRoutesDevis:
    """Tests critiques pour les routes de devis"""
    
    def test_devis_list_route(self, auth_token):
        """Test la route de liste des devis"""
        response = client.get(
            "/api/devis",
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert response.status_code == 200
        assert isinstance(response.json(), list)


class TestCriticalRdvSecurityAndTransitions:
    """Tests de sécurité et de machine d'états pour le sprint A."""

    def _create_rdv_as_admin(self):
        headers = auth_headers_for_role("admin", "admin_rdv_sprint_a")
        rdv_data = {
            "client": {"nom": "Sprint", "prenom": "Admin", "telephone": "0612345678"},
            "vehicule": {"plaque": "SPRINTA1", "marque": "HONDA", "modele": "CB500"},
            "date_rdv": str(date.today()),
            "heure_rdv": "11:00:00",
            "type_intervention": "Révision"
        }
        response = client.post("/api/rendez-vous", json=rdv_data, headers=headers)
        assert response.status_code == 200
        return response.json()["id"], headers

    def test_delete_rendez_vous_requires_rdv_edit_permission(self):
        rdv_id, _ = self._create_rdv_as_admin()
        mecanicien_headers = auth_headers_for_role("mecanicien", "meca_delete_blocked")

        response = client.delete(f"/api/rendez-vous/{rdv_id}", headers=mecanicien_headers)

        assert response.status_code == 403

    def test_demarrer_travail_requires_reception_status(self):
        rdv_id, headers = self._create_rdv_as_admin()

        response = client.post(f"/api/rendez-vous/{rdv_id}/demarrer-travail", headers=headers)

        assert response.status_code == 400
        assert "reception" in response.text.lower()

    def test_invalid_status_transition_is_rejected(self):
        rdv_id, headers = self._create_rdv_as_admin()

        response = client.put(
            f"/api/rendez-vous/{rdv_id}",
            json={"statut": "termine"},
            headers=headers,
        )

        assert response.status_code == 400
        assert "transition" in response.text.lower() or "statut" in response.text.lower()


class TestRouteResponseTimes:
    """Tests pour vérifier que les routes répondent dans un temps raisonnable"""
    
    def test_public_routes_response_time(self):
        """Test que les routes publiques répondent rapidement"""
        import time
        
        start = time.time()
        response = client.get("/api/interventions")
        elapsed = time.time() - start
        
        assert response.status_code == 200
        assert elapsed < 2.0, f"La route a pris {elapsed}s, trop lent"
    
    def test_auth_routes_response_time(self, auth_token):
        """Test que les routes protégées répondent rapidement"""
        import time
        
        start = time.time()
        response = client.get(
            "/api/rendez-vous",
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        elapsed = time.time() - start
        
        assert response.status_code == 200
        assert elapsed < 2.0, f"La route a pris {elapsed}s, trop lent"


if __name__ == "__main__":
    pytest.main([__file__, "-v"])
