import os

os.environ.setdefault("SECRET_KEY", "test-secret-key-for-testing-only")
os.environ.setdefault("CORS_ORIGINS", "http://localhost:3000")

from datetime import date, time

from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker
from sqlalchemy.pool import StaticPool

from models import Base, Client, OrdreReparation, Vehicule, RendezVous
from services.pdf_service import generate_facture_pdf, generate_ordre_reparation_pdf


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


def test_generate_ordre_reparation_pdf_supports_enriched_reception_payload():
    db = TestingSessionLocal()
    c = Client(nom="Pdf", prenom="OR", telephone="0600000004", email="or@test.local", adresse="3 avenue des tests")
    v = Vehicule(plaque="DD444DD", marque="BMW", modele="R1250R", annee=2023, client=c)
    db.add_all([c, v])
    db.flush()
    rdv = RendezVous(
        client_id=c.id,
        vehicule_id=v.id,
        date_rdv=date.today(),
        heure_rdv=time(14, 0),
        type_intervention="Inspection complete",
        statut="reception",
        prix_estime=149.0,
        kilometrage=24500,
        etat_vehicule='{"points":["carrosserie_ok"],"observations":"Micro-rayure reservoir","priority":"standard","fuel_level":2,"body_damages":["reservoir"],"estimate_rows":[{"label":"Inspection complete","qty":1,"amount":149.0}]}',
        photos_etat='["data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO5nXKkAAAAASUVORK5CYII="]',
    )
    db.add(rdv)
    db.flush()
    db.add(OrdreReparation(
        rendez_vous_id=rdv.id,
        numero_or=f"OR-{date.today().year}-{str(rdv.id).zfill(3)}",
        type_or="initial",
        kilometrage=24500,
        etat_vehicule=rdv.etat_vehicule,
        travaux="Inspection complete",
        signature_client="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO5nXKkAAAAASUVORK5CYII=",
    ))
    db.commit()

    resp = generate_ordre_reparation_pdf(rdv.id, db)
    assert resp.media_type == "application/pdf"
    assert "Content-Disposition" in resp.headers
    db.close()
