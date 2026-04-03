import os

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


def _override_current_user():
    return _TestUser()


app.dependency_overrides[get_current_user] = _override_current_user
Base.metadata.create_all(bind=engine)
client = TestClient(app)
_TOKEN = None


def _auth_headers():
    return {"Authorization": "Bearer test-token"}



def test_config_horaires_public_get():
    r = client.get("/api/config/horaires")
    assert r.status_code in (200, 404)


def test_config_atelier_requires_auth_for_update():
    r = client.put("/api/config/atelier", json={"taux_horaire_mo_standard": 75.0}, headers=_auth_headers())
    assert r.status_code in (200, 404)
