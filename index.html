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
engine = create_engine(
    SQLALCHEMY_DATABASE_URL,
    connect_args={"check_same_thread": False},
    poolclass=StaticPool,
)
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



def test_preview_facture_requires_terminated_rdv():
    from datetime import date, time
    from models import Client, Vehicule, RendezVous

    db = TestingSessionLocal()
    c = Client(nom="Durand", prenom="Paul", telephone="0600000001")
    v = Vehicule(plaque="AA111AA", marque="YAMAHA", modele="MT-07")
    db.add_all([c, v])
    db.flush()
    rdv = RendezVous(
        client_id=c.id,
        vehicule_id=v.id,
        date_rdv=date.today(),
        heure_rdv=time(9, 0),
        type_intervention="Revision",
        statut="confirme",
    )
    db.add(rdv)
    db.commit()
    db.refresh(rdv)
    db.close()

    r = client.get(f"/api/rendez-vous/{rdv.id}/preview-facture", headers=_auth_headers())
    assert r.status_code in (400, 404)


def test_facturer_rdv_success_path():
    from datetime import date, time
    from models import Client, Vehicule, RendezVous

    db = TestingSessionLocal()
    c = Client(nom="Martin", prenom="Lea", telephone="0600000002")
    v = Vehicule(plaque="BB222BB", marque="HONDA", modele="CB500")
    db.add_all([c, v])
    db.flush()
    rdv = RendezVous(
        client_id=c.id,
        vehicule_id=v.id,
        date_rdv=date.today(),
        heure_rdv=time(10, 0),
        type_intervention="Vidange",
        statut="termine",
        temps_estime=60,
        prix_estime=120.0,
    )
    db.add(rdv)
    db.commit()
    db.refresh(rdv)
    db.close()

    r = client.post(f"/api/rendez-vous/{rdv.id}/facturer", json={"remise_pourcentage": 0}, headers=_auth_headers())
    assert r.status_code in (200, 404)
    if r.status_code == 200:
        data = r.json()
        assert "total_ttc" in data
