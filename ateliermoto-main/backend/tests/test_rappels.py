"""Tests pour le système de rappels email."""

import os
import pytest
from datetime import date, time, datetime, timedelta
from unittest.mock import patch, MagicMock

os.environ.setdefault("SECRET_KEY", "test-secret-key-for-testing-only")
os.environ.setdefault("CORS_ORIGINS", "http://localhost:3000")
os.environ.setdefault("SMTP_HOST", "")  # disable real SMTP in tests

from fastapi.testclient import TestClient
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker
from sqlalchemy.pool import StaticPool

from main import app
from auth import get_current_user
from models import Base, get_db, Atelier, Client, RendezVous, RappelEmail

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
    username = "test-admin"
    email = "admin@test.local"
    role = "super_admin"
    atelier_id = 1
    is_active = 1
    id = 99


def _override_current_user():
    return _TestUser()


@pytest.fixture(autouse=True)
def _reset_db():
    app.dependency_overrides[get_db] = override_get_db
    app.dependency_overrides[get_current_user] = _override_current_user
    Base.metadata.drop_all(bind=engine)
    Base.metadata.create_all(bind=engine)
    # Seed atelier + client + RDV
    db = TestingSessionLocal()
    atelier = Atelier(id=1, nom="Test Atelier", slug="test", telephone="0600000000", email="atelier@test.local")
    db.add(atelier)
    cl = Client(id=1, atelier_id=1, nom="Dupont", prenom="Jean", telephone="0611111111", email="jean@test.local")
    db.add(cl)
    db.flush()
    # RDV dans le futur (dans 5 jours)
    future_date = date.today() + timedelta(days=5)
    rdv = RendezVous(
        id=1, atelier_id=1, client_id=1, vehicule_id=None,
        date_rdv=future_date, heure_rdv=time(10, 0),
        type_intervention="Revision", statut="confirme",
    )
    db.add(rdv)
    db.commit()
    db.close()
    yield


tc = TestClient(app)
HEADERS = {"Authorization": "Bearer test-token"}


# ── email_service unit tests ──


def test_render_rappel_rdv():
    from services.email_service import render_rappel_rdv
    subject, html, text = render_rappel_rdv(
        client_prenom="Jean",
        client_nom="Dupont",
        date_rdv="15/04/2026",
        heure_rdv="10h00",
        type_intervention="Revision",
        atelier_nom="Test Atelier",
        atelier_telephone="0600000000",
    )
    assert "Rappel" in subject
    assert "Jean" in html
    assert "Revision" in html
    assert "Jean" in text
    assert "0600000000" in text


def test_render_confirmation_rdv():
    from services.email_service import render_confirmation_rdv
    subject, html, text = render_confirmation_rdv(
        client_prenom="Jean",
        date_rdv="15/04/2026",
        heure_rdv="10h00",
        type_intervention="Revision",
        atelier_nom="Test Atelier",
    )
    assert "Confirmation" in subject
    assert "confirmé" in html.lower() or "confirm" in html.lower()
    assert "Jean" in text


def test_send_email_disabled_when_no_host():
    """send_email returns False when SMTP_HOST is empty."""
    from services.email_service import send_email
    with patch.dict(os.environ, {"SMTP_HOST": ""}):
        result = send_email("test@test.local", "Test", "<p>test</p>")
        assert result is False


# ── rappel_service unit tests ──


def test_programmer_rappels_rdv():
    from services.rappel_service import programmer_rappels_rdv
    db = TestingSessionLocal()
    rappels = programmer_rappels_rdv(db, 1)
    # RDV in 5 days → should get 48h, 24h and 4h reminders
    assert len(rappels) == 3
    types = {r.type_rappel for r in rappels}
    assert "rappel_48h" in types
    assert "rappel_24h" in types
    assert "rappel_4h" in types
    # Check they're stored
    stored = db.query(RappelEmail).filter(RappelEmail.rdv_id == 1).all()
    assert len(stored) == 3
    db.close()


def test_programmer_rappels_idempotent():
    """Re-programming replaces pending reminders."""
    from services.rappel_service import programmer_rappels_rdv
    db = TestingSessionLocal()
    programmer_rappels_rdv(db, 1)
    programmer_rappels_rdv(db, 1)
    stored = db.query(RappelEmail).filter(
        RappelEmail.rdv_id == 1, RappelEmail.statut == "programme"
    ).all()
    assert len(stored) == 3  # not 6
    db.close()


@patch("services.rappel_service.send_email", return_value=True)
def test_envoyer_confirmation(mock_send):
    from services.rappel_service import envoyer_confirmation
    db = TestingSessionLocal()
    ok = envoyer_confirmation(db, 1)
    assert ok is True
    mock_send.assert_called_once()
    # Check logged
    logged = db.query(RappelEmail).filter(
        RappelEmail.rdv_id == 1, RappelEmail.type_rappel == "confirmation"
    ).first()
    assert logged is not None
    assert logged.statut == "envoye"
    db.close()


@patch("services.rappel_service.send_email", return_value=True)
def test_envoyer_rappel_manuel(mock_send):
    from services.rappel_service import envoyer_rappel_manuel
    db = TestingSessionLocal()
    ok = envoyer_rappel_manuel(db, 1)
    assert ok is True
    mock_send.assert_called_once()
    logged = db.query(RappelEmail).filter(
        RappelEmail.rdv_id == 1, RappelEmail.type_rappel == "manuel"
    ).first()
    assert logged is not None
    assert logged.statut == "envoye"
    db.close()


# ── API endpoint tests ──


def test_get_rappels_rdv():
    r = tc.get("/api/rappels/rdv/1", headers=HEADERS)
    assert r.status_code == 200
    assert isinstance(r.json(), list)


def test_get_rappels_rdv_not_found():
    r = tc.get("/api/rappels/rdv/9999", headers=HEADERS)
    assert r.status_code == 404


@patch("services.rappel_service.send_email", return_value=True)
def test_envoyer_rappel_api(mock_send):
    r = tc.post("/api/rappels/rdv/1/envoyer", headers=HEADERS)
    assert r.status_code == 200
    assert "envoyé" in r.json().get("message", "").lower() or "envoye" in r.json().get("message", "").lower()


def test_rappels_stats():
    r = tc.get("/api/rappels/stats", headers=HEADERS)
    assert r.status_code == 200
    data = r.json()
    assert "total" in data
    assert "envoyes" in data


def test_rappels_pending():
    r = tc.get("/api/rappels/pending", headers=HEADERS)
    assert r.status_code == 200
    assert isinstance(r.json(), list)


def test_annuler_rappel():
    # First program some rappels
    from services.rappel_service import programmer_rappels_rdv
    db = TestingSessionLocal()
    rappels = programmer_rappels_rdv(db, 1)
    rappel_id = rappels[0].id
    db.close()

    r = tc.post(f"/api/rappels/{rappel_id}/annuler", headers=HEADERS)
    assert r.status_code == 200


def test_annuler_rappel_not_found():
    r = tc.post("/api/rappels/9999/annuler", headers=HEADERS)
    assert r.status_code == 404


@patch("routes.rappels.send_email", return_value=True)
def test_email_test_endpoint(mock_send):
    r = tc.get("/api/email/test", headers=HEADERS)
    assert r.status_code == 200
    assert "test" in r.json().get("message", "").lower()


# ── New template render tests ──


def test_render_rappel_rdv_enhanced():
    from services.email_service import render_rappel_rdv_enhanced
    subject, html, text = render_rappel_rdv_enhanced(
        client_prenom="Jean",
        date_rdv="15/04/2026",
        heure_rdv="10h00",
        type_intervention="Revision",
        atelier_nom="Test Atelier",
        atelier_telephone="0600000000",
        delai="dans 2 jours",
        vehicule_info="Yamaha — MT-07 — AB-123-CD",
        token_suivi="abc123",
    )
    assert "dans 2 jours" in subject
    assert "Jean" in html
    assert "Yamaha" in html
    assert "suivi" in html.lower()


def test_render_suivi_statut():
    from services.email_service import render_suivi_statut
    subject, html, text = render_suivi_statut(
        client_prenom="Jean",
        statut="reception",
        titre="Votre moto a été réceptionnée",
        message_principal="Tout va bien.",
        atelier_nom="Test Atelier",
        token_suivi="abc123",
        vehicule_info="Honda CB500",
    )
    assert "réceptionnée" in subject
    assert "Honda" in html
    assert "Réceptionné" in html


def test_render_signature_or():
    from services.email_service import render_signature_or
    subject, html, text = render_signature_or(
        client_prenom="Jean",
        description_travaux="Remplacement chaine",
        urgence="urgent",
        prix_estime="150",
        atelier_nom="Test Atelier",
        vehicule_info="Yamaha MT-07",
    )
    assert "complémentaires" in subject
    assert "Remplacement chaine" in html
    assert "150 €" in html
    assert "Urgent" in html


def test_render_compte_rendu():
    from services.email_service import render_compte_rendu
    subject, html, text = render_compte_rendu(
        client_prenom="Jean",
        atelier_nom="Test Atelier",
        vehicule_info="Honda CB500",
        travaux_realises="Vidange + filtres",
        recommandations="Pneu arrière à changer",
        kilometrage="25000",
        photos_count=3,
        token_suivi="abc123",
    )
    assert "Compte rendu" in subject
    assert "Vidange" in html
    assert "25000" in html
    assert "3 photo" in html


def test_render_facture_email():
    from services.email_service import render_facture_email
    subject, html, text = render_facture_email(
        client_prenom="Jean",
        atelier_nom="Test Atelier",
        numero_facture="F-2026-001",
        montant_ttc="350.00",
        is_paid=False,
    )
    assert "F-2026-001" in subject
    assert "350.00 €" in html

    # Paid version
    subject2, html2, _ = render_facture_email(
        client_prenom="Jean",
        atelier_nom="Test Atelier",
        numero_facture="F-2026-001",
        montant_ttc="350.00",
        is_paid=True,
    )
    assert "Paiement reçu" in subject2


def test_render_relance_client():
    from services.email_service import render_relance_client
    for niveau in (1, 2, 3):
        subject, html, text = render_relance_client(
            client_prenom="Jean",
            atelier_nom="Test Atelier",
            niveau_relance=niveau,
            vehicule_info="Kawasaki Z650",
        )
        assert "Test Atelier" in subject
        assert "Jean" in html
        assert "Kawasaki" in html


# ── notification_service tests ──


@patch("services.notification_service.send_email", return_value=True)
def test_notifier_changement_statut(mock_send):
    from services.notification_service import notifier_changement_statut
    db = TestingSessionLocal()
    ok = notifier_changement_statut(db, 1, "reception")
    assert ok is True
    mock_send.assert_called_once()
    # Check log
    logged = db.query(RappelEmail).filter(
        RappelEmail.type_rappel == "statut_reception"
    ).first()
    assert logged is not None
    assert logged.statut == "envoye"
    db.close()


@patch("services.notification_service.send_email", return_value=True)
def test_notifier_compte_rendu(mock_send):
    from services.notification_service import notifier_compte_rendu
    db = TestingSessionLocal()
    ok = notifier_compte_rendu(db, 1)
    assert ok is True
    mock_send.assert_called_once()
    db.close()


@patch("services.notification_service.send_email", return_value=True)
def test_notifier_signature_or(mock_send):
    from services.notification_service import notifier_signature_or
    db = TestingSessionLocal()
    ok = notifier_signature_or(db, 1, "Changement pneus", urgence="urgent", prix_estime="200")
    assert ok is True
    mock_send.assert_called_once()
    db.close()


# ── New API endpoint tests ──


@patch("services.notification_service.send_email", return_value=True)
def test_email_notification_endpoint(mock_send):
    r = tc.post("/api/email/notification/1", headers=HEADERS)
    assert r.status_code == 200
    assert "notification" in r.json().get("message", "").lower() or "statut" in r.json().get("message", "").lower()


def test_email_client_history():
    r = tc.get("/api/email/client/1", headers=HEADERS)
    assert r.status_code == 200
    assert isinstance(r.json(), list)


@patch("services.relance_service.send_email", return_value=True)
def test_relance_manuelle_endpoint(mock_send):
    r = tc.post("/api/email/relance", headers=HEADERS)
    assert r.status_code == 200
    assert "relance" in r.json().get("message", "").lower()
