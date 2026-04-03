import os
from datetime import date, time, timedelta

import pytest
from fastapi import HTTPException
from fastapi.testclient import TestClient
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker
from sqlalchemy.pool import StaticPool

os.environ.setdefault("SECRET_KEY", "test-secret-key-for-testing-only")
os.environ.setdefault("CORS_ORIGINS", "http://localhost:3000")

from auth import get_current_user
from main import app
from models import (
    Absence,
    Base,
    CategorieMoto,
    Client,
    ConfigAtelier,
    GrilleTarifs,
    HoraireAtelier,
    InterventionType,
    Mecanicien,
    PieceDetachee,
    PieceUtilisee,
    Pont,
    PontEquipement,
    RapportTechnicien,
    RendezVous,
    TempsIntervention,
    Vehicule,
    get_db,
)
from services.pdf_service import generate_facture_pdf as generate_facture_pdf_service
from services.pdf_service import generate_ordre_reparation_pdf


SQLALCHEMY_DATABASE_URL = "sqlite:///:memory:"
engine = create_engine(
    SQLALCHEMY_DATABASE_URL, connect_args={"check_same_thread": False}, poolclass=StaticPool
)
TestingSessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)


def override_get_db():
    db = TestingSessionLocal()
    try:
        yield db
    finally:
        db.close()


class _AdminUser:
    username = "admin-test"
    role = "admin"


def _override_current_user_admin():
    return _AdminUser()


app.dependency_overrides[get_db] = override_get_db
app.dependency_overrides[get_current_user] = _override_current_user_admin
Base.metadata.create_all(bind=engine)
client = TestClient(app)


def _auth_headers():
    return {"Authorization": "Bearer test-token"}


def _ensure_local_overrides():
    app.dependency_overrides[get_db] = override_get_db
    app.dependency_overrides[get_current_user] = _override_current_user_admin


def _next_weekday(start: date) -> date:
    current = start
    while current.weekday() >= 5:
        current += timedelta(days=1)
    return current


def test_tarifs_endpoints_full_flow():
    _ensure_local_overrides()
    db = TestingSessionLocal()
    cat = CategorieMoto(nom="Roadster Test", description="Catégorie test")
    db.add(cat)
    db.flush()
    vehicule = Vehicule(
        plaque="COV123",
        marque="Yamaha",
        modele="MT07",
        cylindree="689",
        categorie_id=cat.id,
    )
    db.add(vehicule)
    db.commit()
    db.refresh(cat)
    db.refresh(vehicule)
    db.close()

    payload = {
        "categorie_moto_id": cat.id,
        "type_intervention": "revision",
        "nom": "Revision complete",
        "description": "Tarif test",
        "temps_minutes": 90,
        "prix_mo_ht": 90.0,
        "prix_mo_ttc": 108.0,
        "pieces_incluses": False,
    }
    r_create = client.post("/api/tarifs", json=payload, headers=_auth_headers())
    assert r_create.status_code == 200
    tarif_id = r_create.json()["id"]

    r_list = client.get("/api/tarifs", params={"categorie_id": cat.id, "type_intervention": "revision"})
    assert r_list.status_code == 200
    assert any(t["id"] == tarif_id for t in r_list.json())

    r_update = client.put(f"/api/tarifs/{tarif_id}", json={**payload, "temps_minutes": 120}, headers=_auth_headers())
    assert r_update.status_code == 200

    r_calc_ok = client.post(
        "/api/tarifs/calculer",
        json={"vehicule_id": vehicule.id, "prestations": [{"type_intervention": "revision"}]},
        headers=_auth_headers(),
    )
    assert r_calc_ok.status_code == 200
    calc_data = r_calc_ok.json()
    assert calc_data["temps_total_minutes"] >= 90
    assert calc_data["nb_prestations"] == 1

    r_calc_404 = client.post(
        "/api/tarifs/calculer",
        json={"vehicule_id": 999999, "prestations": [{"type_intervention": "revision"}]},
        headers=_auth_headers(),
    )
    assert r_calc_404.status_code == 404

    r_delete = client.delete(f"/api/tarifs/{tarif_id}", headers=_auth_headers())
    assert r_delete.status_code == 200

    db = TestingSessionLocal()
    mec = Mecanicien(nom="Mec", prenom="One", specialites='["moteur"]', couleur="#111111", is_active=1)
    pont = Pont(nom="Pont Creneaux", type_pont="moto", capacite_kg=500, is_active=1)
    db.add_all([mec, pont])
    db.flush()
    jour = _next_weekday(date.today())
    db.add(Absence(mecanicien_id=mec.id, date_debut=jour, date_fin=jour, motif="conge"))
    db.add(
        RendezVous(
            client_id=1,
            vehicule_id=1,
            date_rdv=jour,
            heure_rdv=time(10, 0),
            type_intervention="Test",
            statut="confirme",
            temps_estime=60,
            pont_id=pont.id,
        )
    )
    db.commit()
    db.close()

    r_slots = client.get(
        "/api/creneaux/par-duree",
        params={"date_debut": jour.isoformat(), "date_fin": jour.isoformat(), "duree_heures": 1},
    )
    assert r_slots.status_code == 200
    assert isinstance(r_slots.json(), list)


def test_config_endpoints_full_flow():
    _ensure_local_overrides()
    db = TestingSessionLocal()
    config = ConfigAtelier(
        taux_horaire_mo_standard=70.0,
        taux_horaire_mo_complexe=90.0,
        taux_horaire_mo_expert=110.0,
        marge_pieces_standard=30.0,
        marge_pieces_consommable=40.0,
        marge_pieces_pneumatique=20.0,
        forfait_mo_minimum=25.0,
        tva_mo_taux=20.0,
        tva_pieces_taux=20.0,
        validite_devis_jours=30,
        accompte_pourcentage=20.0,
    )
    cat = CategorieMoto(nom="ConfigCat", description="desc")
    intervention = InterventionType(nom="IntervConfig", description="desc", prix_base=10, temps_estime=30, is_active=1)
    pont = Pont(nom="Pont Config", type_pont="moto", capacite_kg=500, is_active=1)
    db.add_all([config, cat, intervention, pont])
    db.flush()
    for i in range(7):
        db.add(HoraireAtelier(jour_semaine=i, heure_ouverture="08:00", heure_fermeture="18:00", is_ouvert=True))
    db.commit()
    db.refresh(cat)
    db.refresh(intervention)
    db.refresh(pont)
    db.close()

    assert client.get("/api/config/atelier").status_code == 200
    assert client.put("/api/config/atelier", json={"taux_horaire_mo_standard": 75.0}, headers=_auth_headers()).status_code == 200
    assert client.get("/api/config/horaires").status_code == 200
    assert client.get("/api/config/horaires/0").status_code == 200
    assert client.get("/api/config/horaires/9").status_code == 400
    assert client.put("/api/config/horaires/0", json={"heure_ouverture": "09:00"}, headers=_auth_headers()).status_code == 200

    temp_payload = {
        "categorie_moto_id": cat.id,
        "intervention_type_id": intervention.id,
        "temps_minutes": 45,
        "coefficient_difficulte": 1.2,
    }
    r_temp_create = client.post("/api/config/temps-interventions", json=temp_payload, headers=_auth_headers())
    assert r_temp_create.status_code == 200
    temp_id = r_temp_create.json()["id"]
    assert client.get("/api/config/temps-interventions").status_code == 200
    assert client.get(f"/api/config/temps-interventions/{temp_id}").status_code == 200
    assert client.put(
        f"/api/config/temps-interventions/{temp_id}",
        json={**temp_payload, "temps_minutes": 60},
        headers=_auth_headers(),
    ).status_code == 200
    assert client.delete(f"/api/config/temps-interventions/{temp_id}", headers=_auth_headers()).status_code == 200

    r_eq_create = client.post(
        "/api/config/pont-equipements",
        json={"pont_id": pont.id, "nom": "Demonte pneu", "description": "outil", "is_present": 1},
        headers=_auth_headers(),
    )
    assert r_eq_create.status_code == 200
    eq_id = r_eq_create.json()["id"]
    assert client.get("/api/config/pont-equipements", params={"pont_id": pont.id}).status_code == 200
    assert client.get(f"/api/config/pont-equipements/{eq_id}").status_code == 200
    assert client.put(
        f"/api/config/pont-equipements/{eq_id}",
        json={"pont_id": pont.id, "nom": "Demonte pneu X", "description": "outil", "is_present": 1},
        headers=_auth_headers(),
    ).status_code == 200
    assert client.delete(f"/api/config/pont-equipements/{eq_id}", headers=_auth_headers()).status_code == 200
    assert client.get(f"/api/config/pont-equipements/{eq_id}").status_code == 404

    class _ReceptionUser:
        username = "reception"
        role = "receptionnaire"

    app.dependency_overrides[get_current_user] = lambda: _ReceptionUser()
    try:
        r_forbidden = client.put("/api/config/atelier", json={"taux_horaire_mo_standard": 80.0}, headers=_auth_headers())
        assert r_forbidden.status_code == 403
    finally:
        app.dependency_overrides[get_current_user] = _override_current_user_admin


def test_statistiques_endpoints_full_flow():
    _ensure_local_overrides()
    db = TestingSessionLocal()
    client_db = Client(nom="Stat", prenom="Client", telephone="0601010101", email="stat@test.com")
    vehicule = Vehicule(plaque="STAT01", marque="Honda", modele="CB500", cylindree="500")
    pont = Pont(nom="Pont Stats", type_pont="moto", capacite_kg=500, is_active=1)
    mec = Mecanicien(nom="Stat", prenom="Meca", specialites='["diag"]', couleur="#222222", is_active=1)
    db.add_all([client_db, vehicule, pont, mec])
    db.flush()

    today = date.today()
    db.add_all(
        [
            RendezVous(
                client_id=client_db.id,
                vehicule_id=vehicule.id,
                date_rdv=today,
                heure_rdv=time(9, 0),
                type_intervention="Vidange",
                statut="termine",
                prix_final=100.0,
                temps_estime=60,
                temps_final=70,
                pont_id=pont.id,
                mecanicien_id=mec.id,
            ),
            RendezVous(
                client_id=client_db.id,
                vehicule_id=vehicule.id,
                date_rdv=today - timedelta(days=1),
                heure_rdv=time(11, 0),
                type_intervention="Freins",
                statut="facture",
                prix_final=200.0,
                temps_estime=90,
                temps_final=90,
                pont_id=pont.id,
                mecanicien_id=mec.id,
            ),
            RendezVous(
                client_id=client_db.id,
                vehicule_id=vehicule.id,
                date_rdv=today - timedelta(days=3),
                heure_rdv=time(14, 0),
                type_intervention="Diagnostic",
                statut="en_cours",
                prix_final=0.0,
                temps_estime=30,
                pont_id=pont.id,
                mecanicien_id=mec.id,
            ),
            RendezVous(
                client_id=client_db.id,
                vehicule_id=vehicule.id,
                date_rdv=today - timedelta(days=5),
                heure_rdv=time(16, 0),
                type_intervention="Vidange",
                statut="annule",
                prix_final=0.0,
                temps_estime=45,
                pont_id=pont.id,
                mecanicien_id=mec.id,
            ),
        ]
    )
    db.commit()
    db.refresh(mec)
    db.close()

    assert client.get("/api/statistiques/ca", params={"periode": "mois"}, headers=_auth_headers()).status_code == 200
    assert client.get(
        "/api/statistiques/ca",
        params={"date_debut": (today - timedelta(days=7)).isoformat(), "date_fin": today.isoformat()},
        headers=_auth_headers(),
    ).status_code == 200
    assert client.get("/api/statistiques/ca/comparatif", headers=_auth_headers()).status_code == 200
    assert client.get("/api/statistiques/ponts", headers=_auth_headers()).status_code == 200
    assert client.get("/api/statistiques/ponts/occupation", headers=_auth_headers()).status_code == 200
    assert client.get("/api/statistiques/interventions/top", params={"limit": 10}, headers=_auth_headers()).status_code == 200
    assert client.get("/api/statistiques/clients/fideles", headers=_auth_headers()).status_code == 200
    assert client.get("/api/statistiques/evolution-mensuelle", params={"mois": 3}, headers=_auth_headers()).status_code == 200
    assert client.get("/api/statistiques/dashboard", headers=_auth_headers()).status_code == 200
    assert client.get("/api/statistiques/mecaniciens", headers=_auth_headers()).status_code == 200
    assert client.get("/api/statistiques/mecaniciens/productivite", headers=_auth_headers()).status_code == 200
    assert client.get(f"/api/statistiques/mecaniciens/{mec.id}/detail", headers=_auth_headers()).status_code == 200
    assert client.get("/api/statistiques/mecaniciens/999999/detail", headers=_auth_headers()).status_code == 404
    assert client.get("/api/statistiques/ponts/occupation-detail", headers=_auth_headers()).status_code == 200
    assert client.get("/api/statistiques/atelier", headers=_auth_headers()).status_code == 200
    assert client.get("/api/statistiques/performance/atelier", headers=_auth_headers()).status_code == 200
    assert client.get("/api/statistiques/mecaniciens/comparaison", headers=_auth_headers()).status_code == 200


def test_facturation_endpoints_full_flow_and_errors():
    _ensure_local_overrides()
    db = TestingSessionLocal()
    if db.query(ConfigAtelier).count() == 0:
        db.add(
            ConfigAtelier(
                taux_horaire_mo_standard=70.0,
                taux_horaire_mo_complexe=90.0,
                taux_horaire_mo_expert=110.0,
                marge_pieces_standard=30.0,
                marge_pieces_consommable=40.0,
                marge_pieces_pneumatique=20.0,
                forfait_mo_minimum=25.0,
                tva_mo_taux=20.0,
                tva_pieces_taux=20.0,
                validite_devis_jours=30,
                accompte_pourcentage=20.0,
            )
        )

    c = Client(nom="Fact", prenom="Client", telephone="0602020202")
    v = Vehicule(plaque="FACT01", marque="Suzuki", modele="SV")
    db.add_all([c, v])
    db.flush()
    rdv = RendezVous(
        client_id=c.id,
        vehicule_id=v.id,
        date_rdv=date.today(),
        heure_rdv=time(10, 0),
        type_intervention="Forfait",
        statut="termine",
        temps_estime=60,
        prix_estime=120.0,
    )
    db.add(rdv)
    db.flush()
    p = PieceDetachee(reference="PIECE-FACT-1", nom="Filtre", prix_vente_ht=15.0, tva_taux=20.0, quantite_stock=10)
    db.add(p)
    db.flush()
    db.add(PieceUtilisee(rendez_vous_id=rdv.id, piece_id=p.id, quantite=2, prix_vente_unitaire=15.0))
    db.commit()
    db.refresh(rdv)
    db.close()

    assert client.get(f"/api/rendez-vous/{rdv.id}/preview-facture", headers=_auth_headers()).status_code == 200
    r_facturer = client.post(
        f"/api/rendez-vous/{rdv.id}/facturer",
        json={"remise_pourcentage": 10, "notes": "test"},
        headers=_auth_headers(),
    )
    assert r_facturer.status_code == 200
    facture_id = r_facturer.json()["facture_id"]

    r_duplicate = client.post(
        f"/api/rendez-vous/{rdv.id}/facturer",
        json={"remise_pourcentage": 0},
        headers=_auth_headers(),
    )
    assert r_duplicate.status_code == 400

    assert client.get("/api/factures", headers=_auth_headers()).status_code == 200
    assert client.get("/api/factures/stats", headers=_auth_headers()).status_code == 200
    assert client.get(f"/api/factures/{facture_id}", headers=_auth_headers()).status_code == 200
    assert client.get(f"/api/factures/par-rdv/{rdv.id}", headers=_auth_headers()).status_code == 200
    assert client.get(f"/api/factures/{facture_id}/pdf", headers=_auth_headers()).status_code == 200
    assert client.get(f"/api/rendez-vous/{rdv.id}/facture-pdf", headers=_auth_headers()).status_code == 200

    assert client.post(
        f"/api/factures/{facture_id}/encaisser",
        json={"montant": 10, "mode_paiement": "invalide"},
        headers=_auth_headers(),
    ).status_code == 400
    assert client.post(
        f"/api/factures/{facture_id}/encaisser",
        json={"montant": 0, "mode_paiement": "cb"},
        headers=_auth_headers(),
    ).status_code == 400
    assert client.post(
        f"/api/factures/{facture_id}/encaisser",
        json={"montant": 999999, "mode_paiement": "cb"},
        headers=_auth_headers(),
    ).status_code == 400

    r_partial = client.post(
        f"/api/factures/{facture_id}/encaisser",
        json={"montant": 50, "mode_paiement": "cb"},
        headers=_auth_headers(),
    )
    assert r_partial.status_code == 200
    assert r_partial.json()["statut"] in ("partiellement_payee", "payee")

    restant = max(r_partial.json()["montant_restant"], 0.0)
    if restant > 0:
        r_full = client.post(
            f"/api/factures/{facture_id}/encaisser",
            json={"montant": restant, "mode_paiement": "especes"},
            headers=_auth_headers(),
        )
        assert r_full.status_code == 200
        assert r_full.json()["statut"] == "payee"

    assert client.post(f"/api/factures/{facture_id}/annuler", headers=_auth_headers()).status_code == 400

    db = TestingSessionLocal()
    c2 = Client(nom="Fact2", prenom="Client2", telephone="0603030303")
    v2 = Vehicule(plaque="FACT02", marque="KTM", modele="Duke")
    db.add_all([c2, v2])
    db.flush()
    rdv2 = RendezVous(
        client_id=c2.id,
        vehicule_id=v2.id,
        date_rdv=date.today(),
        heure_rdv=time(12, 0),
        type_intervention="Horaire",
        statut="termine",
        temps_estime=60,
        prix_estime=None,
    )
    db.add(rdv2)
    db.commit()
    db.refresh(rdv2)
    db.close()

    r_facturer2 = client.post(
        f"/api/rendez-vous/{rdv2.id}/facturer",
        json={"remise_pourcentage": 0},
        headers=_auth_headers(),
    )
    assert r_facturer2.status_code == 200
    facture2_id = r_facturer2.json()["facture_id"]
    assert client.post(f"/api/factures/{facture2_id}/annuler", headers=_auth_headers()).status_code == 200

    class _MecanicienUser:
        username = "mec"
        role = "mecanicien"

    app.dependency_overrides[get_current_user] = lambda: _MecanicienUser()
    try:
        r_forbidden = client.post(
            f"/api/rendez-vous/{rdv2.id}/facturer",
            json={"remise_pourcentage": 0},
            headers=_auth_headers(),
        )
        assert r_forbidden.status_code == 403
    finally:
        app.dependency_overrides[get_current_user] = _override_current_user_admin


def test_pdf_service_generation_paths():
    _ensure_local_overrides()
    db = TestingSessionLocal()
    c = Client(nom="PdfSrv", prenom="Client", telephone="0604040404", email="pdf@test.com")
    v = Vehicule(plaque="PDF01", marque="BMW", modele="R1250", annee=2020, cylindree="1250", type_moto="trail")
    pont = Pont(nom="Pont PDF", type_pont="moto", capacite_kg=500, is_active=1)
    mec = Mecanicien(nom="PDF", prenom="Mec", specialites='["elec"]', couleur="#333333", is_active=1)
    db.add_all([c, v, pont, mec])
    db.flush()
    rdv = RendezVous(
        client_id=c.id,
        vehicule_id=v.id,
        date_rdv=date.today(),
        heure_rdv=time(15, 0),
        type_intervention="Diagnostic",
        commentaire="Controle complet",
        statut="facture",
        prix_estime=150.0,
        temps_estime=90,
        temps_final=80,
        etat_vehicule="not-a-json",
        pont_id=pont.id,
        mecanicien_id=mec.id,
        kilometrage=12345,
    )
    db.add(rdv)
    db.flush()
    rapport = RapportTechnicien(
        rendez_vous_id=rdv.id,
        points_controle="not-json",
        alertes="Verifier batterie",
        recommandations="Remplacement prochainement",
        travaux_realises="Controle realise",
        pieces_utilisees='[{"nom":"Bougie","reference":"B1","quantite":1,"prix":12.5}]',
        statut="termine",
    )
    db.add(rapport)
    db.commit()
    db.refresh(rdv)

    resp_or = generate_ordre_reparation_pdf(rdv.id, db)
    assert resp_or.media_type == "application/pdf"
    assert "Content-Disposition" in resp_or.headers

    resp_facture = generate_facture_pdf_service(rdv.id, db)
    assert resp_facture.media_type == "application/pdf"
    assert "Content-Disposition" in resp_facture.headers

    with pytest.raises(HTTPException):
        generate_facture_pdf_service(999999, db)

    db.close()
