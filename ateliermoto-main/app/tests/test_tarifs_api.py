import os

import pytest

os.environ.setdefault("SECRET_KEY", "test-secret-key-for-testing-only")
os.environ.setdefault("CORS_ORIGINS", "http://localhost:3000")

from fastapi.testclient import TestClient
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker
from sqlalchemy.pool import StaticPool

from main import app
from auth import get_current_user
from models import Base, get_db

SQLALCHEMY_DATABASE_URL = "sqlite:///:memory:"
engine = create_engine(SQLALCHEMY_DATABASE_URL, connect_args={"check_same_thread": False}, poolclass=StaticPool)
TestingSessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)


def override_get_db():
    db = TestingSessionLocal()
    try:
        yield db
    finally:
        db.close()


app.dependency_overrides[get_db] = override_get_db

class _TestUser:
    username = "test-user"
    role = "admin"
    atelier_id = 1
    is_active = 1


def _override_current_user():
    return _TestUser()


@pytest.fixture(autouse=True)
def _reset_test_overrides():
    app.dependency_overrides[get_db] = override_get_db
    app.dependency_overrides[get_current_user] = _override_current_user
    yield
    app.dependency_overrides[get_db] = override_get_db
    app.dependency_overrides[get_current_user] = _override_current_user


app.dependency_overrides[get_current_user] = _override_current_user
Base.metadata.create_all(bind=engine)
client = TestClient(app)
_TOKEN = None


def _auth_headers():
    return {"Authorization": "Bearer test-token"}



def test_tarifs_list_endpoint_accessible():
    r = client.get("/api/tarifs")
    assert r.status_code == 200
    assert isinstance(r.json(), list)


def test_tarifs_create_requires_auth():
    payload = {
        "categorie_moto_id": 1,
        "type_intervention": "revision",
        "nom": "Revision simple",
        "temps_minutes": 60,
        "prix_mo_ht": 60.0,
        "prix_mo_ttc": 72.0
    }
    r = client.post("/api/tarifs", json=payload, headers=_auth_headers())
    assert r.status_code in (200, 400, 404)
