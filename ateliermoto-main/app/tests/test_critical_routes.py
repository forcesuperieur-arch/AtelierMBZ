"""
Tests pour les endpoints critiques de l'application.
Ces tests vérifient que les routes essentielles fonctionnent correctement.
"""
import json
import pytest
import sys
import os
from datetime import date, datetime, time, timedelta

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

os.environ["SECRET_KEY"] = "test-secret-key-for-testing-only"
os.environ["CORS_ORIGINS"] = "http://localhost:3000"

from fastapi.testclient import TestClient
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker
from sqlalchemy.pool import StaticPool

from auth import get_current_user
from models import Absence, Atelier, Base, Client, DemandeTravauxSupp, HoraireAtelier, Mecanicien, OrdreReparation, Pont, Prestation, RendezVous, Vehicule, get_db
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


@pytest.fixture(autouse=True)
def _reset_test_overrides():
    app.dependency_overrides[get_db] = override_get_db
    app.dependency_overrides.pop(get_current_user, None)
    yield
    app.dependency_overrides[get_db] = override_get_db
    app.dependency_overrides.pop(get_current_user, None)


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

    def _create_rendez_vous_route(self):
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

    def test_create_rendez_vous_route(self):
        """Test la route de création de RDV"""
        rdv_id = self._create_rendez_vous_route()
        assert isinstance(rdv_id, int)

    def test_get_rendez_vous_route(self, auth_token):
        """Test la route de récupération d'un RDV"""
        # Créer un RDV d'abord
        rdv_id = self._create_rendez_vous_route()

        response = client.get(
            f"/api/rendez-vous/{rdv_id}",
            headers={"Authorization": f"Bearer {auth_token}"},
        )
        assert response.status_code == 200
        data = response.json()
        assert data["id"] == rdv_id
    
    def test_update_rendez_vous_route(self, auth_token):
        """Test la route de mise à jour d'un RDV"""
        rdv_id = self._create_rendez_vous_route()
        
        update_data = {"statut": "confirme", "kilometrage": 5000}
        response = client.put(
            f"/api/rendez-vous/{rdv_id}",
            json=update_data,
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert response.status_code == 200

    def test_update_rendez_vous_detects_conflict_after_pause_split(self, auth_token):
        """Un RDV qui reprend après la pause midi doit bloquer le même pont/technicien."""
        target_day = date.today()
        while target_day.weekday() >= 5:
            target_day += timedelta(days=1)

        db = TestingSessionLocal()
        atelier = db.query(Atelier).filter(Atelier.id == 1).first()
        if not atelier:
            atelier = Atelier(id=1, nom="Atelier Test Interne", slug="default", plan="starter", actif=True)
            db.add(atelier)
            db.flush()
        mecanicien = Mecanicien(atelier_id=atelier.id, nom="Pause", prenom="Split", is_active=1)
        db.add(mecanicien)
        db.flush()
        pont = Pont(atelier_id=atelier.id, nom="Pont Pause", type_pont="moto", capacite_kg=500, is_active=1, mecanicien_id=mecanicien.id)
        client_a = Client(atelier_id=atelier.id, nom="Client", prenom="A", telephone="0611111111")
        client_b = Client(atelier_id=atelier.id, nom="Client", prenom="B", telephone="0622222222")
        db.add_all([pont, client_a, client_b])
        db.flush()

        vehicule_a = Vehicule(atelier_id=atelier.id, plaque="PAUSEA01", marque="Yamaha", modele="MT-09", client_id=client_a.id)
        vehicule_b = Vehicule(atelier_id=atelier.id, plaque="PAUSEB01", marque="Honda", modele="CB650", client_id=client_b.id)
        db.add_all([vehicule_a, vehicule_b])
        db.flush()

        existing_horaire = db.query(HoraireAtelier).filter(HoraireAtelier.atelier_id == atelier.id, HoraireAtelier.jour_semaine == target_day.weekday()).first()
        if existing_horaire:
            existing_horaire.heure_ouverture = "08:00"
            existing_horaire.heure_fermeture = "18:00"
            existing_horaire.pause_debut = "12:00"
            existing_horaire.pause_fin = "14:00"
            existing_horaire.is_ouvert = 1
        else:
            db.add(HoraireAtelier(
                atelier_id=atelier.id,
                jour_semaine=target_day.weekday(),
                heure_ouverture="08:00",
                heure_fermeture="18:00",
                pause_debut="12:00",
                pause_fin="14:00",
                is_ouvert=1,
            ))

        rdv_a = RendezVous(
            atelier_id=atelier.id,
            client_id=client_a.id,
            vehicule_id=vehicule_a.id,
            date_rdv=target_day,
            heure_rdv=time(11, 30),
            type_intervention="Diagnostic long",
            statut="confirme",
            temps_estime=90,
            pont_id=pont.id,
            mecanicien_id=mecanicien.id,
        )
        rdv_b = RendezVous(
            atelier_id=atelier.id,
            client_id=client_b.id,
            vehicule_id=vehicule_b.id,
            date_rdv=target_day,
            heure_rdv=time(14, 30),
            type_intervention="Controle rapide",
            statut="confirme",
            temps_estime=60,
            pont_id=pont.id,
            mecanicien_id=mecanicien.id,
        )
        db.add_all([rdv_a, rdv_b])
        db.commit()
        rdv_b_id = rdv_b.id
        db.close()

        response = client.put(
            f"/api/rendez-vous/{rdv_b_id}",
            json={"commentaire": "verification conflit pause"},
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert response.status_code == 409
        assert "Conflit planning" in response.json()["detail"]

    def test_create_rendez_vous_rejects_absent_mecanicien_assignment(self, auth_token):
        """La creation rapide ne doit pas assigner un technicien absent."""
        target_day = date.today()
        while target_day.weekday() >= 5:
            target_day += timedelta(days=1)

        db = TestingSessionLocal()
        atelier = db.query(Atelier).filter(Atelier.id == 1).first()
        if not atelier:
            atelier = Atelier(id=1, nom="Atelier Test Interne", slug="default", plan="starter", actif=True)
            db.add(atelier)
            db.flush()
        mecanicien = Mecanicien(atelier_id=atelier.id, nom="Absent", prenom="Tech", is_active=1)
        db.add(mecanicien)
        db.flush()
        pont = Pont(atelier_id=atelier.id, nom="Pont Absence", type_pont="moto", capacite_kg=500, is_active=1, mecanicien_id=mecanicien.id)
        db.add(pont)
        db.flush()
        db.add(Absence(
            atelier_id=atelier.id,
            mecanicien_id=mecanicien.id,
            date_debut=target_day,
            date_fin=target_day,
            motif="conge",
            notes="indisponible",
        ))
        db.commit()
        mecanicien_id = mecanicien.id
        pont_id = pont.id
        db.close()

        response = client.post(
            "/api/rendez-vous",
            json={
                "client": {"nom": "Creation", "prenom": "Rapide", "telephone": "0633333333"},
                "vehicule": {"plaque": "ABSENCE01", "marque": "BMW", "modele": "R1250"},
                "date_rdv": target_day.isoformat(),
                "heure_rdv": "09:00:00",
                "type_intervention": "Revision express",
                "mecanicien_id": mecanicien_id,
                "pont_id": pont_id,
            },
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert response.status_code == 409
        assert "Technicien indisponible" in response.json()["detail"]

    def test_public_slots_block_overlap_and_pause_for_long_duration(self):
        """Un créneau long ne doit ni chevaucher un RDV existant, ni traverser une fermeture atelier."""
        target_day = date.today()
        while target_day.weekday() >= 5:
            target_day += timedelta(days=1)
        atelier_slug = f"public-slots-{target_day.strftime('%Y%m%d')}"

        db = TestingSessionLocal()
        atelier = db.query(Atelier).filter(Atelier.slug == atelier_slug).first()
        if not atelier:
            atelier = Atelier(nom="Atelier Public", slug=atelier_slug, plan="starter", actif=True)
            db.add(atelier)
            db.flush()

        mecanicien = Mecanicien(atelier_id=atelier.id, nom="Martin", prenom="Leo", is_active=1)
        db.add(mecanicien)
        db.flush()

        pont = Pont(atelier_id=atelier.id, nom="Pont Public", type_pont="moto", capacite_kg=500, is_active=1, mecanicien_id=mecanicien.id)
        client_db = Client(atelier_id=atelier.id, nom="Client", prenom="Test", telephone="0601010101")
        db.add_all([pont, client_db])
        db.flush()

        vehicule = Vehicule(atelier_id=atelier.id, plaque="PUBLIC01", marque="Yamaha", modele="MT-07", client_id=client_db.id)
        db.add(vehicule)
        db.flush()

        db.add(HoraireAtelier(
            atelier_id=atelier.id,
            jour_semaine=target_day.weekday(),
            heure_ouverture="08:00",
            heure_fermeture="18:00",
            pause_debut="12:00",
            pause_fin="14:00",
            is_ouvert=1,
        ))
        db.add(RendezVous(
            atelier_id=atelier.id,
            client_id=client_db.id,
            vehicule_id=vehicule.id,
            date_rdv=target_day,
            heure_rdv=time(10, 0),
            type_intervention="Revision deja planifiee",
            statut="confirme",
            temps_estime=60,
            mecanicien_id=mecanicien.id,
        ))
        db.commit()
        db.close()

        response = client.get(
            "/api/creneaux/avec-ponts",
            params={"date_str": target_day.isoformat(), "duree_minutes": 120, "atelier_slug": atelier_slug},
        )
        assert response.status_code == 200

        creneaux = {item["heure"]: item for item in response.json()["creneaux"]}
        assert creneaux["09:15"]["disponible"] is False
        assert "11:00" not in creneaux
        assert "16:15" not in creneaux

    def test_public_booking_rejects_conflict_on_same_pont(self):
        """La réservation publique doit refuser un chevauchement sur le même pont."""
        target_day = date.today()
        while target_day.weekday() >= 5:
            target_day += timedelta(days=1)
        atelier_slug = f"public-conflict-{target_day.strftime('%Y%m%d')}"

        db = TestingSessionLocal()
        atelier = db.query(Atelier).filter(Atelier.slug == atelier_slug).first()
        if not atelier:
            atelier = Atelier(nom="Atelier Public", slug=atelier_slug, plan="starter", actif=True)
            db.add(atelier)
            db.flush()

        mecanicien = Mecanicien(atelier_id=atelier.id, nom="Dupont", prenom="Max", is_active=1)
        db.add(mecanicien)
        db.flush()

        pont = Pont(atelier_id=atelier.id, nom="Pont Conflit", type_pont="moto", capacite_kg=500, is_active=1, mecanicien_id=mecanicien.id)
        client_db = Client(atelier_id=atelier.id, nom="Existant", prenom="Client", telephone="0602020202")
        db.add_all([pont, client_db])
        db.flush()

        vehicule = Vehicule(atelier_id=atelier.id, plaque="CONFLIT01", marque="Honda", modele="CB500", client_id=client_db.id)
        prestation = Prestation(
            atelier_id=atelier.id,
            code=f"PRESTA-CONFLIT-{target_day.strftime('%Y%m%d')}",
            nom="Revision publique",
            categorie="entretien",
            prix_base_ht=50,
            prix_base_ttc=60,
            temps_estime_minutes=60,
            is_active=1,
        )
        db.add_all([vehicule, prestation])
        db.flush()

        db.add(RendezVous(
            atelier_id=atelier.id,
            client_id=client_db.id,
            vehicule_id=vehicule.id,
            date_rdv=target_day,
            heure_rdv=time(10, 0),
            type_intervention="Revision existante",
            statut="confirme",
            temps_estime=90,
            pont_id=pont.id,
            mecanicien_id=mecanicien.id,
        ))
        db.commit()
        prestation_id = prestation.id
        pont_id = pont.id
        db.close()

        response = client.post(
            "/api/rendez-vous/public",
            json={
                "client": {"nom": "Nouveau", "prenom": "Client", "telephone": "0603030303", "email": "public@test.local"},
                "vehicule": {"plaque": "NEWPUBLIC", "marque": "Suzuki", "modele": "SV650", "type_moto": "Roadster"},
                "prestations": [prestation_id],
                "date_heure": f"{target_day.isoformat()}T10:30:00",
                "montant_estime": 60,
                "commentaires": "Test conflit public",
                "pont_id": pont_id,
                "atelier_slug": atelier_slug,
            },
        )
        assert response.status_code == 409

    def test_public_slots_block_afternoon_when_existing_rdv_spans_lunch_break(self):
        """Un RDV qui traverse la pause midi doit bloquer la plage de l'apres-midi correspondante."""
        target_day = date.today()
        while target_day.weekday() >= 5:
            target_day += timedelta(days=1)
        atelier_slug = f"public-lunch-overlap-{target_day.strftime('%Y%m%d')}"

        db = TestingSessionLocal()
        atelier = db.query(Atelier).filter(Atelier.slug == atelier_slug).first()
        if not atelier:
            atelier = Atelier(nom="Atelier Public", slug=atelier_slug, plan="starter", actif=True)
            db.add(atelier)
            db.flush()

        mecanicien = Mecanicien(atelier_id=atelier.id, nom="Lopez", prenom="Nina", is_active=1)
        db.add(mecanicien)
        db.flush()

        pont = Pont(atelier_id=atelier.id, nom="Pont Pause", type_pont="moto", capacite_kg=500, is_active=1, mecanicien_id=mecanicien.id)
        client_db = Client(atelier_id=atelier.id, nom="Test", prenom="Pause", telephone="0604040404")
        db.add_all([pont, client_db])
        db.flush()

        vehicule = Vehicule(atelier_id=atelier.id, plaque="PAUSE01", marque="BMW", modele="F900", client_id=client_db.id)
        db.add(vehicule)
        db.flush()

        db.add(HoraireAtelier(
            atelier_id=atelier.id,
            jour_semaine=target_day.weekday(),
            heure_ouverture="08:00",
            heure_fermeture="18:00",
            pause_debut="12:00",
            pause_fin="14:00",
            is_ouvert=1,
        ))
        # 11:00 + 180 min => reprise 14:00 puis fin 16:00 (doit bloquer 14:30)
        db.add(RendezVous(
            atelier_id=atelier.id,
            client_id=client_db.id,
            vehicule_id=vehicule.id,
            date_rdv=target_day,
            heure_rdv=time(11, 0),
            type_intervention="Intervention longue",
            statut="confirme",
            temps_estime=180,
            pont_id=pont.id,
            mecanicien_id=mecanicien.id,
        ))
        db.commit()
        db.close()

        response = client.get(
            "/api/creneaux/avec-ponts",
            params={"date_str": target_day.isoformat(), "duree_minutes": 30, "atelier_slug": atelier_slug},
        )
        assert response.status_code == 200

        creneaux = {item["heure"]: item for item in response.json()["creneaux"]}
        assert creneaux["14:30"]["disponible"] is False


class TestCriticalRoutesClient:
    """Tests critiques pour les routes de clients"""
    
    def test_list_clients_route(self, auth_token):
        """Test la route de liste des clients"""
        client.cookies.set("legacy_client_list", "1")
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

    def test_prestations_and_tarifs_synthese_routes(self, auth_token):
        """Les routes prestations/tarifs restent disponibles après extraction."""
        headers = {"Authorization": f"Bearer {auth_token}"}

        prestations_response = client.get("/api/prestations", headers=headers)
        assert prestations_response.status_code == 200
        assert isinstance(prestations_response.json(), list)

        synthese_response = client.get("/api/tarifs/synthese", headers=headers)
        assert synthese_response.status_code == 200
        payload = synthese_response.json()
        assert "prestations" in payload
        assert "forfaits_mo" in payload
        assert "taux_horaires" in payload


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

    def test_devis_can_be_created_and_converted_to_rdv(self):
        """Le workflow devis -> RDV reste fonctionnel après refactor."""
        headers = auth_headers_for_role("admin", "admin_devis_workflow")

        db = TestingSessionLocal()
        client_db = Client(atelier_id=1, nom="Devis", prenom="Client", telephone="0605050505")
        db.add(client_db)
        db.flush()

        vehicule = Vehicule(atelier_id=1, plaque="DEVIS01", marque="Yamaha", modele="Tracer 9", client_id=client_db.id)
        db.add(vehicule)
        db.commit()
        vehicule_id = vehicule.id
        client_id = client_db.id
        db.close()

        create_response = client.post(
            "/api/devis",
            json={
                "client_id": client_id,
                "vehicule_id": vehicule_id,
                "kilometrage": 24500,
                "notes_client": "Prévoir la grosse révision",
                "lignes": [
                    {
                        "type_ligne": "forfait_mo",
                        "designation": "Révision complète",
                        "quantite": 1,
                        "prix_unitaire_ht": 120,
                        "taux_tva": 20,
                    }
                ],
                "remise_pourcentage": 10,
            },
            headers=headers,
        )
        assert create_response.status_code == 200
        devis_id = create_response.json()["id"]

        update_response = client.put(
            f"/api/devis/{devis_id}",
            json={"statut": "accepte"},
            headers=headers,
        )
        assert update_response.status_code == 200

        target_day = date.today() + timedelta(days=1)
        convert_response = client.post(
            f"/api/devis/{devis_id}/convertir-rdv",
            params={"date_rdv": target_day.isoformat(), "heure_rdv": "10:30:00"},
            headers=headers,
        )
        assert convert_response.status_code == 200
        rdv_id = convert_response.json()["rdv_id"]

        rdv_response = client.get(f"/api/rendez-vous/{rdv_id}", headers=headers)
        assert rdv_response.status_code == 200
        rdv_payload = rdv_response.json()
        assert rdv_payload["type_intervention"] == "Révision complète"
        assert rdv_payload["commentaire"] == "Prévoir la grosse révision"
        assert rdv_payload["kilometrage"] == 24500


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

    def test_terminer_avec_rapport_persists_notes_and_sets_end_date(self):
        headers = auth_headers_for_role("admin", "admin_atomic_finish")

        db = TestingSessionLocal()
        client_db = Client(atelier_id=1, nom="Fin", prenom="Atomic", telephone="0607070707")
        db.add(client_db)
        db.flush()

        vehicule = Vehicule(atelier_id=1, plaque="ATOMIC01", marque="Suzuki", modele="GSX-8S", client_id=client_db.id)
        db.add(vehicule)
        db.flush()

        rdv = RendezVous(
            atelier_id=1,
            client_id=client_db.id,
            vehicule_id=vehicule.id,
            date_rdv=date.today(),
            heure_rdv=time(10, 0),
            type_intervention="Diagnostic final",
            commentaire="RDV atelier pour cloture",
            statut="en_cours",
            temps_estime=30,
            heure_debut_travail=datetime.now() - timedelta(minutes=35),
        )
        db.add(rdv)
        db.commit()
        rdv_id = rdv.id
        db.close()

        response = client.post(
            f"/api/rendez-vous/{rdv_id}/terminer-avec-rapport",
            json={
                "points_controle": {},
                "alertes": "Client a rappeler pour la batterie.",
                "recommandations": "Controle complementaire au prochain passage.",
                "travaux_realises": "Essai routier et verification finale.",
                "statut": "termine",
            },
            headers=headers,
        )

        assert response.status_code == 200
        assert response.json()["statut"] == "termine"
        assert response.json()["rapport"]["alertes"] == "Client a rappeler pour la batterie."
        assert response.json()["rapport"]["date_fin"] is not None

        rapport_response = client.get(f"/api/rendez-vous/{rdv_id}/rapport-technicien", headers=headers)
        assert rapport_response.status_code == 200
        rapport_payload = rapport_response.json()
        assert rapport_payload["alertes"] == "Client a rappeler pour la batterie."
        assert rapport_payload["travaux_realises"] == "Essai routier et verification finale."
        assert rapport_payload["date_fin"] is not None

    def test_service_client_cannot_drive_workflow_without_workflow_permission(self):
        rdv_id, _ = self._create_rdv_as_admin()
        service_headers = auth_headers_for_role("service_client", "src_workflow_blocked")

        response = client.put(
            f"/api/rendez-vous/{rdv_id}",
            json={"statut": "confirme"},
            headers=service_headers,
        )

        assert response.status_code == 403
        assert "workflow.manage" in response.text or "permission" in response.text.lower()

    def test_invalid_status_transition_is_rejected(self):
        rdv_id, headers = self._create_rdv_as_admin()

        response = client.put(
            f"/api/rendez-vous/{rdv_id}",
            json={"statut": "termine"},
            headers=headers,
        )

        assert response.status_code == 400
        assert "transition" in response.text.lower() or "statut" in response.text.lower()

    def test_generic_update_cannot_force_reception_gate(self):
        rdv_id, headers = self._create_rdv_as_admin()

        response = client.put(
            f"/api/rendez-vous/{rdv_id}",
            json={"statut": "reception"},
            headers=headers,
        )

        assert response.status_code == 400
        assert "reception" in response.text.lower() or "dedi" in response.text.lower()

    def test_dedicated_reception_requires_confirmed_status(self):
        rdv_id, headers = self._create_rdv_as_admin()

        response = client.post(
            f"/api/rendez-vous/{rdv_id}/reception",
            headers=headers,
        )

        assert response.status_code == 400
        assert "confirme" in response.text.lower()

    def test_restitution_endpoint_closes_rdv_and_records_history(self):
        headers = auth_headers_for_role("admin", "admin_restitution_flow")

        db = TestingSessionLocal()
        atelier = db.query(Atelier).filter(Atelier.id == 1).first()
        if not atelier:
            atelier = Atelier(id=1, nom="Atelier Test Interne", slug="default", plan="starter", actif=True)
            db.add(atelier)
            db.flush()

        client_db = Client(atelier_id=atelier.id, nom="Restit", prenom="Client", telephone="0609090909")
        db.add(client_db)
        db.flush()

        vehicule = Vehicule(atelier_id=atelier.id, plaque="RESTIT01", marque="Ducati", modele="Monster", client_id=client_db.id)
        db.add(vehicule)
        db.flush()

        mecanicien = Mecanicien(atelier_id=atelier.id, nom="Restit", prenom="Tech", is_active=1)
        db.add(mecanicien)
        db.flush()

        pont = Pont(atelier_id=atelier.id, nom="Pont restitution", type_pont="moto", capacite_kg=500, is_active=1, mecanicien_id=mecanicien.id)
        db.add(pont)
        db.flush()

        rdv = RendezVous(
            atelier_id=atelier.id,
            client_id=client_db.id,
            vehicule_id=vehicule.id,
            date_rdv=date.today(),
            heure_rdv=time(17, 0),
            type_intervention="Restitution finale",
            statut="termine",
            temps_estime=45,
            pont_id=pont.id,
            mecanicien_id=mecanicien.id,
        )
        db.add(rdv)
        db.commit()
        rdv_id = rdv.id
        pont_id = pont.id
        mecanicien_id = mecanicien.id
        db.close()

        response = client.post(
            f"/api/rendez-vous/{rdv_id}/restituer",
            headers=headers,
        )

        assert response.status_code == 200
        assert response.json()["statut"] == "restitue"

        detail = client.get(f"/api/rendez-vous/{rdv_id}", headers=headers)
        assert detail.status_code == 200
        payload = detail.json()
        history = payload.get("workflow_history") or []
        assert payload["statut"] == "restitue"
        assert any(entry.get("to_status") == "restitue" for entry in history)

        locked = client.put(
            f"/api/rendez-vous/{rdv_id}",
            json={"pont_id": pont_id, "mecanicien_id": mecanicien_id + 999},
            headers=headers,
        )

        assert locked.status_code == 409
        assert "finalise" in locked.text.lower() or "verrou" in locked.text.lower()

    def test_finalized_rdv_cannot_reassign_pont_or_mecanicien(self):
        headers = auth_headers_for_role("admin", "admin_assignment_lock")

        db = TestingSessionLocal()
        atelier = db.query(Atelier).filter(Atelier.id == 1).first()
        if not atelier:
            atelier = Atelier(id=1, nom="Atelier Test Interne", slug="default", plan="starter", actif=True)
            db.add(atelier)
            db.flush()

        client_db = Client(atelier_id=atelier.id, nom="Lock", prenom="Assignation", telephone="0608080808")
        db.add(client_db)
        db.flush()

        vehicule = Vehicule(atelier_id=atelier.id, plaque="LOCKAS01", marque="Kawasaki", modele="Z900", client_id=client_db.id)
        db.add(vehicule)
        db.flush()

        mecanicien_a = Mecanicien(atelier_id=atelier.id, nom="Alpha", prenom="Tech", is_active=1)
        mecanicien_b = Mecanicien(atelier_id=atelier.id, nom="Bravo", prenom="Tech", is_active=1)
        db.add_all([mecanicien_a, mecanicien_b])
        db.flush()

        pont_a = Pont(atelier_id=atelier.id, nom="Pont A lock", type_pont="moto", capacite_kg=500, is_active=1, mecanicien_id=mecanicien_a.id)
        pont_b = Pont(atelier_id=atelier.id, nom="Pont B lock", type_pont="moto", capacite_kg=500, is_active=1, mecanicien_id=mecanicien_b.id)
        db.add_all([pont_a, pont_b])
        db.flush()

        rdv = RendezVous(
            atelier_id=atelier.id,
            client_id=client_db.id,
            vehicule_id=vehicule.id,
            date_rdv=date.today(),
            heure_rdv=time(15, 0),
            type_intervention="Controle final",
            statut="termine",
            temps_estime=45,
            pont_id=pont_a.id,
            mecanicien_id=mecanicien_a.id,
        )
        db.add(rdv)
        db.commit()
        pont_a_id = pont_a.id
        pont_b_id = pont_b.id
        mecanicien_a_id = mecanicien_a.id
        mecanicien_b_id = mecanicien_b.id
        rdv_id = rdv.id
        db.close()

        response = client.put(
            f"/api/rendez-vous/{rdv_id}",
            json={"pont_id": pont_b_id, "mecanicien_id": mecanicien_b_id},
            headers=headers,
        )

        assert response.status_code == 409
        assert "verrou" in response.text.lower() or "finalise" in response.text.lower()

        detail = client.get(f"/api/rendez-vous/{rdv_id}", headers=headers)
        assert detail.status_code == 200
        payload = detail.json()
        assert payload["pont_id"] == pont_a_id
        assert payload["mecanicien_id"] == mecanicien_a_id

    def test_annulation_requires_reason_and_records_history(self):
        rdv_id, headers = self._create_rdv_as_admin()

        blocked = client.put(
            f"/api/rendez-vous/{rdv_id}",
            json={"statut": "annule"},
            headers=headers,
        )

        assert blocked.status_code == 400
        assert "motif" in blocked.text.lower() or "commentaire" in blocked.text.lower()

        allowed = client.put(
            f"/api/rendez-vous/{rdv_id}",
            json={"statut": "annule", "commentaire": "[ANNULATION] motif=atelier_indisponible"},
            headers=headers,
        )

        assert allowed.status_code == 200

        detail = client.get(f"/api/rendez-vous/{rdv_id}", headers=headers)
        assert detail.status_code == 200
        payload = detail.json()
        history = payload.get("workflow_history") or []
        assert payload["statut"] == "annule"
        assert any(entry.get("to_status") == "annule" for entry in history)

    def test_workflow_history_is_exposed_on_status_change(self):
        rdv_id, headers = self._create_rdv_as_admin()

        update = client.put(
            f"/api/rendez-vous/{rdv_id}",
            json={"statut": "confirme"},
            headers=headers,
        )

        assert update.status_code == 200

        detail = client.get(f"/api/rendez-vous/{rdv_id}", headers=headers)
        assert detail.status_code == 200
        history = detail.json().get("workflow_history") or []
        assert any(entry.get("to_status") == "confirme" for entry in history)


class TestTravauxSupplementairesWorkflow:
    """Régressions sur le flux OR complémentaire."""

    def test_approving_travaux_supp_updates_rdv_and_attached_ors(self):
        headers = auth_headers_for_role("admin", "travaux_supp_admin")

        db = TestingSessionLocal()
        client_db = Client(atelier_id=1, nom="Client", prenom="Travaux", telephone="0604040404")
        db.add(client_db)
        db.flush()

        vehicule = Vehicule(atelier_id=1, plaque="TSFLOW01", marque="Honda", modele="CB650R", client_id=client_db.id)
        prestation = Prestation(
            atelier_id=1,
            code="TS-KIT-CHAINE",
            nom="Kit chaine",
            categorie="atelier",
            prix_base_ht=150,
            prix_base_ttc=180,
            temps_estime_minutes=90,
            is_active=1,
        )
        db.add_all([vehicule, prestation])
        db.flush()

        rdv = RendezVous(
            atelier_id=1,
            client_id=client_db.id,
            vehicule_id=vehicule.id,
            date_rdv=date.today(),
            heure_rdv=time(9, 0),
            type_intervention="Vidange",
            commentaire="Vidange annuelle",
            prix_estime=120,
            temps_estime=60,
            statut="en_cours",
        )
        db.add(rdv)
        db.flush()

        db.add(OrdreReparation(
            rendez_vous_id=rdv.id,
            numero_or=f"OR-{date.today().year}-{str(rdv.id).zfill(3)}",
            type_or="initial",
            travaux="Vidange annuelle",
            signature_client="signed",
        ))
        db.commit()
        rdv_id = rdv.id
        prestation_id = prestation.id
        db.close()

        create_response = client.post(
            f"/api/rendez-vous/{rdv_id}/travaux-supplementaires",
            json={
                "description": "Usure du kit chaine constatee",
                "prestations_demandees": [{"prestation_id": prestation_id, "nom": "Kit chaine", "code": "TS-KIT-CHAINE"}],
                "urgence": "urgent",
            },
            headers=headers,
        )
        assert create_response.status_code == 200
        demande_id = create_response.json()["id"]

        approve_response = client.put(
            f"/api/travaux-supplementaires/{demande_id}",
            json={
                "statut": "approuve",
                "notes_receptionniste": "Accord client pour changement du kit chaine",
                "prix_estime": 180,
                "temps_estime": 90,
                "signature": "signed-ts",
            },
            headers=headers,
        )
        assert approve_response.status_code == 200

        rdv_response = client.get(f"/api/rendez-vous/{rdv_id}", headers=headers)
        assert rdv_response.status_code == 200
        rdv_payload = rdv_response.json()

        assert "Vidange" in rdv_payload["type_intervention"]
        assert "Kit chaine" in rdv_payload["type_intervention"]
        assert float(rdv_payload["prix_estime"]) == pytest.approx(300.0)
        assert rdv_payload["temps_estime"] == 150
        assert len(rdv_payload["ordres_reparation"]) == 2
        assert any(o["type_or"] == "supplementaire" for o in rdv_payload["ordres_reparation"])

        db = TestingSessionLocal()
        demande = db.query(DemandeTravauxSupp).filter(DemandeTravauxSupp.id == demande_id).first()
        initial_or = db.query(OrdreReparation).filter(
            OrdreReparation.rendez_vous_id == rdv_id,
            OrdreReparation.type_or == "initial",
        ).first()
        supp_or = db.query(OrdreReparation).filter(
            OrdreReparation.rendez_vous_id == rdv_id,
            OrdreReparation.type_or == "supplementaire",
        ).first()

        assert demande is not None
        assert supp_or is not None
        assert supp_or.demande_travaux_supp_id == demande_id
        assert "Kit chaine" in (supp_or.travaux or "")
        assert "Kit chaine" in (initial_or.travaux or "")
        db.close()


class TestOrdreReparationVisualData:
    """Régressions sur la sauvegarde enrichie des OR."""

    def test_save_ordre_reparation_persists_visual_metadata(self):
        headers = auth_headers_for_role("admin", "admin_or_visual")

        db = TestingSessionLocal()
        client_db = Client(
            atelier_id=1,
            nom="Martin",
            prenom="Elise",
            telephone="0605050505",
            email="elise@example.com",
            adresse="8 rue des virages",
        )
        db.add(client_db)
        db.flush()

        vehicule = Vehicule(
            atelier_id=1,
            plaque="ORVIS001",
            marque="Ducati",
            modele="Monster",
            annee=2022,
            client_id=client_db.id,
        )
        db.add(vehicule)
        db.flush()

        rdv = RendezVous(
            atelier_id=1,
            client_id=client_db.id,
            vehicule_id=vehicule.id,
            date_rdv=date.today(),
            heure_rdv=time(10, 30),
            type_intervention="Revision majeure",
            commentaire="Controle complet",
            prix_estime=249.0,
            temps_estime=120,
            statut="confirme",
        )
        db.add(rdv)
        db.commit()
        rdv_id = rdv.id
        db.close()

        payload = {
            "kilometrage": 18234,
            "etat_vehicule": json.dumps({
                "points": ["carrosserie_ok", "freins_ok"],
                "observations": "Rayure visible sur le flanc droit.",
                "priority": "urgent",
                "fuel_level": 3,
                "body_damages": ["flanc_droit", "reservoir"],
                "schema_notes": "Impact leger proche du reservoir",
                "estimate_rows": [
                    {"label": "Diagnostic complet", "qty": 1, "amount": 89.9},
                    {"label": "Remplacement plaquettes", "qty": 1, "amount": 159.1},
                ],
            }),
            "travaux": "Diagnostic complet et controle securite.",
            "signature": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO5nXKkAAAAASUVORK5CYII=",
            "photos": [
                "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO5nXKkAAAAASUVORK5CYII="
            ],
        }

        response = client.post(f"/api/rendez-vous/{rdv_id}/ordre-reparation/save", json=payload, headers=headers)
        assert response.status_code == 200

        detail = client.get(f"/api/rendez-vous/{rdv_id}", headers=headers)
        assert detail.status_code == 200
        data = detail.json()

        meta = json.loads(data["etat_vehicule"])
        assert meta["priority"] == "urgent"
        assert meta["fuel_level"] == 3
        assert meta["body_damages"] == ["flanc_droit", "reservoir"]
        assert len(meta["estimate_rows"]) == 2
        assert isinstance(data["photos_etat"], list)
        assert len(data["photos_etat"]) == 1
        assert data["ordres_reparation"][0]["signature_client"].startswith("data:image/png;base64,")

    def test_signed_ordre_reparation_becomes_locked_against_resave(self):
        headers = auth_headers_for_role("admin", "admin_or_locked")

        db = TestingSessionLocal()
        client_db = Client(
            atelier_id=1,
            nom="Lock",
            prenom="Test",
            telephone="0606060606",
        )
        db.add(client_db)
        db.flush()

        vehicule = Vehicule(
            atelier_id=1,
            plaque="ORLOCK01",
            marque="Yamaha",
            modele="MT-07",
            client_id=client_db.id,
        )
        db.add(vehicule)
        db.flush()

        rdv = RendezVous(
            atelier_id=1,
            client_id=client_db.id,
            vehicule_id=vehicule.id,
            date_rdv=date.today(),
            heure_rdv=time(14, 0),
            type_intervention="Controle",
            commentaire="Reception atelier",
            prix_estime=99.0,
            temps_estime=45,
            statut="confirme",
        )
        db.add(rdv)
        db.commit()
        rdv_id = rdv.id
        db.close()

        first_payload = {
            "kilometrage": 25000,
            "etat_vehicule": json.dumps({"points": ["carrosserie_ok"], "observations": "RAS"}),
            "travaux": "Controle de routine",
            "signature": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO5nXKkAAAAASUVORK5CYII=",
        }
        second_payload = {
            "kilometrage": 25555,
            "etat_vehicule": json.dumps({"points": ["bosses"], "observations": "Modification tardive"}),
            "travaux": "Tentative de modification apres signature",
            "signature": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO5nXKkAAAAASUVORK5CYII=",
        }

        first = client.post(f"/api/rendez-vous/{rdv_id}/ordre-reparation/save", json=first_payload, headers=headers)
        assert first.status_code == 200

        second = client.post(f"/api/rendez-vous/{rdv_id}/ordre-reparation/save", json=second_payload, headers=headers)
        assert second.status_code == 409
        assert "verrou" in second.text.lower() or "signe" in second.text.lower()


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
