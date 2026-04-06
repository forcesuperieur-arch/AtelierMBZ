import os
import sys

import pytest
from fastapi.testclient import TestClient
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker
from sqlalchemy.pool import StaticPool

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

os.environ["SECRET_KEY"] = "test-secret-key-for-testing-only"
os.environ["CORS_ORIGINS"] = "http://localhost:3000"

from auth import get_current_user
from main import app
from models import Base, User, get_db
from seed import init_base_moto, load_moto_catalog


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


@pytest.fixture(autouse=True)
def _seed_moto_reference_data():
    app.dependency_overrides[get_db] = override_get_db
    app.dependency_overrides.pop(get_current_user, None)
    db = TestingSessionLocal()
    try:
        init_base_moto(db)
        yield
    finally:
        db.close()
        app.dependency_overrides.pop(get_current_user, None)


@pytest.fixture
def client():
    with TestClient(app) as test_client:
        yield test_client


class TestMotoBaseAutocomplete:
    def test_autocomplete_returns_matching_marques_and_modeles(self, client):
        response = client.get("/api/motos/autocomplete?query=mt")

        assert response.status_code == 200
        data = response.json()
        assert "marques" in data
        assert "modeles" in data
        assert any(marque == "YAMAHA" for marque in data["marques"])
        assert any(item["marque"] == "YAMAHA" and "MT-0" in item["modele"] for item in data["modeles"])

    def test_autocomplete_can_filter_modeles_by_marque(self, client):
        response = client.get("/api/motos/autocomplete?query=nc&marque=HONDA")

        assert response.status_code == 200
        data = response.json()
        assert data["modeles"]
        assert all(item["marque"] == "HONDA" for item in data["modeles"])

    def test_create_modele_is_reserved_to_super_admin(self, client):
        app.dependency_overrides[get_current_user] = lambda: User(
            id=1,
            username="admin",
            email="admin@test.local",
            hashed_password="x",
            role="admin",
            is_active=1,
        )

        response = client.post(
            "/api/motos/modeles",
            json={
                "marque": "TEST",
                "modele": "MODELE-ADMIN",
                "categorie_id": 1,
                "cylindree_min": 500,
                "cylindree_max": 500,
            },
        )

        assert response.status_code == 403

    def test_super_admin_can_create_modele(self, client):
        app.dependency_overrides[get_current_user] = lambda: User(
            id=2,
            username="superadmin",
            email="superadmin@test.local",
            hashed_password="x",
            role="super_admin",
            is_active=1,
        )

        response = client.post(
            "/api/motos/modeles",
            json={
                "marque": "TEST",
                "modele": "MODELE-SUPERADMIN",
                "categorie_id": 1,
                "cylindree_min": 650,
                "cylindree_max": 650,
                "annee_debut": 2024,
            },
        )

        assert response.status_code == 200
        assert response.json()["message"] == "Modèle créé"

    def test_seed_contains_extended_brand_catalog(self, client):
        response = client.get("/api/motos/marques")

        assert response.status_code == 200
        brands = response.json()
        assert len(brands) >= 35
        for expected in [
            "APRILIA",
            "HARLEY-DAVIDSON",
            "ROYAL ENFIELD",
            "CFMOTO",
            "VESPA",
            "MOTO GUZZI",
            "INDIAN",
            "MASH",
        ]:
            assert expected in brands

    def test_external_catalog_is_loaded_from_data_file(self):
        catalog = load_moto_catalog()

        assert isinstance(catalog, dict)
        assert isinstance(catalog.get("categories"), list)
        assert isinstance(catalog.get("models"), list)
        assert len(catalog["models"]) >= 300

        brands = {item["marque"] for item in catalog["models"]}
        for expected in ["YAMAHA", "HONDA", "BMW", "HARLEY-DAVIDSON", "MOTO GUZZI", "MASH"]:
            assert expected in brands
