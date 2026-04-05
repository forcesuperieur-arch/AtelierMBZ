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

from main import app
from models import Base, get_db
from seed import init_base_moto


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
    db = TestingSessionLocal()
    try:
        init_base_moto(db)
        yield
    finally:
        db.close()


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
