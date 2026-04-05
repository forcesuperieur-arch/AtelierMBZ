import os

os.environ.setdefault("SECRET_KEY", "test-secret-key-for-testing-only")
os.environ.setdefault("CORS_ORIGINS", "http://localhost:3000")

from datetime import date, time

from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker
from sqlalchemy.pool import StaticPool

from models import Base, Client, Vehicule, RendezVous
from services.pdf_service import generate_facture_pdf


SQLALCHEMY_DATABASE_URL = "sqlite:///:memory:"
engine = create_engine(SQLALCHEMY_DATABASE_URL, connect_args={"check_same_thread": False}, poolclass=StaticPool)
TestingSessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)
Base.metadata.create_all(bind=engine)


def test_generate_facture_pdf_returns_streaming_response():
    db = TestingSessionLocal()
    c = Client(nom="Pdf", prenom="Test", telephone="0600000003")
    v = Vehicule(plaque="CC333CC", marque="KTM", modele="690")
    db.add_all([c, v])
    db.flush()
    rdv = RendezVous(
        client_id=c.id,
        vehicule_id=v.id,
        date_rdv=date.today(),
        heure_rdv=time(11, 0),
        type_intervention="Entretien",
        statut="termine",
        prix_estime=99.0,
    )
    db.add(rdv)
    db.commit()
    db.refresh(rdv)

    resp = generate_facture_pdf(rdv.id, db)
    assert resp.media_type == "application/pdf"
    assert "Content-Disposition" in resp.headers
    db.close()
