"""
Tests d'intégration pour les workflows complets.
"""
import pytest
import sys
import os
from datetime import date, time, timedelta

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


class TestWorkflowRendezVous:
    """Tests pour le workflow complet d'un rendez-vous"""
    
    def test_workflow_complet_rdv(self, auth_token):
        """Test le workflow complet: création → mise à jour → récupération"""
        
        # 1. Créer un RDV
        rdv_data = {
            "client": {
                "nom": "Durand",
                "prenom": "Marie",
                "telephone": "0698765432",
                "email": "marie@example.com"
            },
            "vehicule": {
                "plaque": "XY-789-ZW",
                "marque": "KAWASAKI",
                "modele": "Z900",
                "annee": 2021
            },
            "date_rdv": str(date.today()),
            "heure_rdv": "09:30:00",
            "type_intervention": "Révision",
            "commentaire": "Première révision"
        }
        
        create_response = client.post("/api/rendez-vous", json=rdv_data)
        assert create_response.status_code == 200
        rdv_id = create_response.json()["id"]
        
        # 2. Récupérer le RDV créé
        get_response = client.get(f"/api/rendez-vous/{rdv_id}")
        assert get_response.status_code == 200
        rdv_data_retrieved = get_response.json()
        assert rdv_data_retrieved["client"]["nom"] == "Durand"
        assert rdv_data_retrieved["vehicule"]["marque"] == "KAWASAKI"
        
        # 3. Mettre à jour le statut
        update_response = client.put(
            f"/api/rendez-vous/{rdv_id}",
            json={"statut": "confirme", "kilometrage": 5000},
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert update_response.status_code == 200
        
        # 4. Vérifier la mise à jour
        get_response2 = client.get(f"/api/rendez-vous/{rdv_id}")
        assert get_response2.json()["statut"] == "confirme"
        assert get_response2.json()["kilometrage"] == 5000
    
    def test_workflow_changement_statuts(self, auth_token):
        """Test le workflow de changement de statuts"""
        
        # Créer un RDV
        rdv_data = {
            "client": {"nom": "Test", "prenom": "Statut", "telephone": "0600000000"},
            "vehicule": {"plaque": "STATUT01", "marque": "YAMAHA", "modele": "MT"},
            "date_rdv": str(date.today()),
            "heure_rdv": "10:00:00",
            "type_intervention": "Vidange"
        }
        
        create_response = client.post("/api/rendez-vous", json=rdv_data)
        rdv_id = create_response.json()["id"]
        
        # Vérifier statut initial
        get_response = client.get(f"/api/rendez-vous/{rdv_id}")
        assert get_response.json()["statut"] == "en_attente"
        
        # Changer vers confirmé
        client.put(
            f"/api/rendez-vous/{rdv_id}",
            json={"statut": "confirme"},
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        
        get_response = client.get(f"/api/rendez-vous/{rdv_id}")
        assert get_response.json()["statut"] == "confirme"
        
        # Changer vers en_cours
        client.put(
            f"/api/rendez-vous/{rdv_id}",
            json={"statut": "en_cours"},
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        
        get_response = client.get(f"/api/rendez-vous/{rdv_id}")
        assert get_response.json()["statut"] == "en_cours"
        
        # Changer vers termine
        client.put(
            f"/api/rendez-vous/{rdv_id}",
            json={"statut": "termine", "prix_final": 200.0, "temps_final": 90},
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        
        get_response = client.get(f"/api/rendez-vous/{rdv_id}")
        assert get_response.json()["statut"] == "termine"


class TestWorkflowClient:
    """Tests pour le workflow de gestion des clients"""
    
    def test_workflow_client_avec_rdv(self, auth_token):
        """Test le workflow client avec plusieurs RDV"""
        
        # Créer plusieurs RDV pour le même client (par téléphone)
        client_data = {
            "client": {"nom": "Fidele", "prenom": "Client", "telephone": "0611111111"},
            "vehicule": {"plaque": "FIDEL001", "marque": "HONDA", "modele": "CBR"},
            "date_rdv": str(date.today()),
            "heure_rdv": "09:00:00",
            "type_intervention": "Révision"
        }
        
        # Premier RDV
        response1 = client.post("/api/rendez-vous", json=client_data)
        assert response1.status_code == 200
        
        # Deuxième RDV (même téléphone)
        client_data["vehicule"]["plaque"] = "FIDEL002"
        client_data["heure_rdv"] = "10:00:00"
        response2 = client.post("/api/rendez-vous", json=client_data)
        assert response2.status_code == 200
        
        # Vérifier que le client est listé
        clients_response = client.get(
            "/api/clients",
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert clients_response.status_code == 200
        clients = clients_response.json()
        
        # Trouver notre client
        fidele_client = next((c for c in clients if c["telephone"] == "0611111111"), None)
        assert fidele_client is not None
        assert fidele_client["nb_rdv"] >= 2


class TestWorkflowPlanning:
    """Tests pour le workflow de planning"""
    
    def test_workflow_planning_avec_ponts_mecaniciens(self, auth_token):
        """Test le workflow de planning avec ponts et mécaniciens"""
        
        # 1. Créer un pont
        pont_data = {
            "nom": "Pont Planning Test",
            "type_pont": "moto",
            "capacite_kg": 500
        }
        pont_response = client.post(
            "/api/ponts",
            json=pont_data,
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert pont_response.status_code == 200
        pont_id = pont_response.json()["id"]
        
        # 2. Créer un mécanicien
        mecano_data = {
            "nom": "Test",
            "prenom": "Mecano",
            "specialites": "moteur",
            "couleur": "#00ff00"
        }
        mecano_response = client.post(
            "/api/mecaniciens",
            json=mecano_data,
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert mecano_response.status_code == 200
        mecano_id = mecano_response.json()["id"]
        
        # 3. Créer un RDV
        rdv_data = {
            "client": {"nom": "Planning", "prenom": "Test", "telephone": "0622222222"},
            "vehicule": {"plaque": "PLAN001", "marque": "YAMAHA", "modele": "R1"},
            "date_rdv": str(date.today()),
            "heure_rdv": "14:00:00",
            "type_intervention": "Révision"
        }
        rdv_response = client.post("/api/rendez-vous", json=rdv_data)
        assert rdv_response.status_code == 200
        
        # 4. Vérifier le planning
        planning_response = client.get(
            "/api/planning",
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert planning_response.status_code == 200
        planning = planning_response.json()
        
        assert "ponts" in planning
        assert "mecaniciens" in planning
        assert "rendez_vous" in planning
        
        # Vérifier que notre pont et mécanicien sont dans le planning
        pont_ids = [p["id"] for p in planning["ponts"]]
        mecano_ids = [m["id"] for m in planning["mecaniciens"]]
        
        assert pont_id in pont_ids
        assert mecano_id in mecano_ids


class TestWorkflowPieces:
    """Tests pour le workflow de gestion des pièces"""
    
    def test_workflow_piece_complete(self, auth_token):
        """Test le workflow complet d'une pièce"""
        
        # 1. Créer un fournisseur
        fournisseur_data = {
            "nom": "Fournisseur Test",
            "contact": "Jean Fournisseur",
            "telephone": "0123456789",
            "email": "fournisseur@test.com",
            "delai_livraison_jours": 2
        }
        fournisseur_response = client.post(
            "/api/fournisseurs",
            json=fournisseur_data,
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert fournisseur_response.status_code == 200
        fournisseur_id = fournisseur_response.json()["id"]
        
        # 2. Créer une pièce
        piece_data = {
            "reference": "TEST-PIECE-001",
            "nom": "Pièce de test",
            "description": "Description de test",
            "categorie": "moteur",
            "quantite_stock": 5,
            "quantite_minimale": 10,
            "prix_achat_ht": 20.0,
            "prix_vente_ht": 35.0,
            "fournisseur_id": fournisseur_id
        }
        piece_response = client.post(
            "/api/pieces",
            json=piece_data,
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert piece_response.status_code == 200
        piece_id = piece_response.json()["id"]
        
        # 3. Vérifier les alertes stock
        alertes_response = client.get(
            "/api/pieces/alertes",
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert alertes_response.status_code == 200
        alertes = alertes_response.json()
        
        # Notre pièce doit être en alerte (stock 5 < minimum 10)
        piece_alerte = next((a for a in alertes if a["id"] == piece_id), None)
        assert piece_alerte is not None
        assert piece_alerte["quantite_manquante"] == 5
        
        # 4. Ajuster le stock
        ajustement_response = client.post(
            f"/api/pieces/{piece_id}/ajuster-stock?quantite=10&raison=Test",
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert ajustement_response.status_code == 200
        assert ajustement_response.json()["nouveau_stock"] == 15
        
        # 5. Vérifier que l'alerte est résolue
        alertes_response2 = client.get(
            "/api/pieces/alertes",
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        alertes2 = alertes_response2.json()
        piece_alerte2 = next((a for a in alertes2 if a["id"] == piece_id), None)
        assert piece_alerte2 is None  # Plus en alerte


if __name__ == "__main__":
    pytest.main([__file__, "-v"])
