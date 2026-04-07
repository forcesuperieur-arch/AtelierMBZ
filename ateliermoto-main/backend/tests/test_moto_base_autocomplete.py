import os
import sys
import zipfile
from pathlib import Path

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
from models import Base, ModeleMoto, MotoTechnicalSpec, User, get_db
from seed import (
    init_base_moto,
    init_moto_technical_specs,
    load_moto_catalog,
    load_moto_technical_specs,
    load_ngk_sparkplug_rows,
    sync_ngk_catalog_to_db,
    sync_ngk_minimal_specs_to_db,
)


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
        init_moto_technical_specs(db)
        yield
    finally:
        db.close()
        app.dependency_overrides.pop(get_current_user, None)


@pytest.fixture
def client():
    with TestClient(app) as test_client:
        yield test_client


def _xlsx_column_name(index: int) -> str:
    name = ""
    current = index + 1
    while current:
        current, remainder = divmod(current - 1, 26)
        name = chr(65 + remainder) + name
    return name


def _write_minimal_ngk_xlsx(target_path: Path, headers=None, rows=None):
    headers = headers or ["Marque", "Modele", "Annee debut", "Annee fin", "Bougie NGK"]
    rows = rows or [["KAWASAKI", "ER-6n", "2006", "2011", "CR9EIA-9"]]

    def _build_row_xml(row_index, values):
        cells = []
        for col_index, value in enumerate(values):
            cell_ref = f"{_xlsx_column_name(col_index)}{row_index}"
            cells.append(
                f"      <c r='{cell_ref}' t='inlineStr'><is><t>{value}</t></is></c>"
            )
        return "    <row r='{row_index}'>\n{cells}\n    </row>".format(
            row_index=row_index,
            cells="\n".join(cells),
        )

    sheet_rows = [_build_row_xml(1, headers)]
    for row_index, values in enumerate(rows, start=2):
        sheet_rows.append(_build_row_xml(row_index, values))

    files = {
        "[Content_Types].xml": """<?xml version='1.0' encoding='UTF-8'?>
<Types xmlns='http://schemas.openxmlformats.org/package/2006/content-types'>
  <Default Extension='rels' ContentType='application/vnd.openxmlformats-package.relationships+xml'/>
  <Default Extension='xml' ContentType='application/xml'/>
  <Override PartName='/xl/workbook.xml' ContentType='application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml'/>
  <Override PartName='/xl/worksheets/sheet1.xml' ContentType='application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml'/>
</Types>""",
        "_rels/.rels": """<?xml version='1.0' encoding='UTF-8'?>
<Relationships xmlns='http://schemas.openxmlformats.org/package/2006/relationships'>
  <Relationship Id='rId1' Type='http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument' Target='xl/workbook.xml'/>
</Relationships>""",
        "xl/workbook.xml": """<?xml version='1.0' encoding='UTF-8'?>
<workbook xmlns='http://schemas.openxmlformats.org/spreadsheetml/2006/main' xmlns:r='http://schemas.openxmlformats.org/officeDocument/2006/relationships'>
  <sheets><sheet name='NGK' sheetId='1' r:id='rId1'/></sheets>
</workbook>""",
        "xl/_rels/workbook.xml.rels": """<?xml version='1.0' encoding='UTF-8'?>
<Relationships xmlns='http://schemas.openxmlformats.org/package/2006/relationships'>
  <Relationship Id='rId1' Type='http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet' Target='worksheets/sheet1.xml'/>
</Relationships>""",
        "xl/worksheets/sheet1.xml": """<?xml version='1.0' encoding='UTF-8'?>
<worksheet xmlns='http://schemas.openxmlformats.org/spreadsheetml/2006/main'>
  <sheetData>
{sheet_rows}
  </sheetData>
</worksheet>""".format(sheet_rows="\n".join(sheet_rows)),
    }
    with zipfile.ZipFile(target_path, "w") as archive:
        for name, content in files.items():
            archive.writestr(name, content)


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

    def test_autocomplete_keeps_public_rdv_suggestions_useful_after_variant_split(self, client):
        response = client.get("/api/motos/autocomplete?query=mt&marque=YAMAHA&limit=10")

        assert response.status_code == 200
        data = response.json()
        assert any(item["marque"] == "YAMAHA" and item["modele"] in {"MT-07", "MT 07"} for item in data["modeles"])

    def test_autocomplete_matches_hyphenated_public_queries(self, client):
        response = client.get("/api/motos/autocomplete?query=mt-07&marque=YAMAHA&limit=10")

        assert response.status_code == 200
        data = response.json()
        assert any(item["marque"] == "YAMAHA" and item["modele"] in {"MT-07", "MT 07", "MT 07 A"} for item in data["modeles"])

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

    def test_super_admin_can_create_same_model_name_for_other_variant(self, client):
        app.dependency_overrides[get_current_user] = lambda: User(
            id=3,
            username="superadmin2",
            email="superadmin2@test.local",
            hashed_password="x",
            role="super_admin",
            is_active=1,
        )

        first = client.post(
            "/api/motos/modeles",
            json={
                "marque": "TEST",
                "modele": "MODELE-VARIANT",
                "categorie_id": 1,
                "cylindree_min": 650,
                "cylindree_max": 650,
                "annee_debut": 2020,
                "annee_fin": 2022,
            },
        )
        second = client.post(
            "/api/motos/modeles",
            json={
                "marque": "TEST",
                "modele": "MODELE-VARIANT",
                "categorie_id": 1,
                "cylindree_min": 900,
                "cylindree_max": 900,
                "annee_debut": 2023,
                "annee_fin": 2025,
            },
        )

        assert first.status_code == 200
        assert second.status_code == 200

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

    def test_external_technical_specs_cover_1990_to_today(self):
        catalog = load_moto_technical_specs()

        assert isinstance(catalog, dict)
        assert isinstance(catalog.get("specs"), list)
        assert len(catalog["specs"]) >= 300
        assert any((item.get("annee_debut") or 9999) <= 1990 for item in catalog["specs"])
        assert any((item.get("annee_fin") is None or item.get("annee_fin") >= 2024) for item in catalog["specs"])

    def test_can_load_ngk_rows_from_minimal_xlsx_export(self, tmp_path):
        xlsx_path = tmp_path / "ngk_sparkplugs.xlsx"
        _write_minimal_ngk_xlsx(xlsx_path)

        rows = load_ngk_sparkplug_rows(xlsx_path)

        assert rows
        assert rows[0]["marque"] == "KAWASAKI"
        assert rows[0]["modele"] == "ER-6n"
        assert rows[0]["annee_debut"] == 2006
        assert rows[0]["annee_fin"] == 2011
        assert rows[0]["ref_bougie_ngk"] == "NGK CR9EIA-9"

    def test_ngk_xlsx_merge_can_enrich_matching_specs(self, tmp_path):
        xlsx_path = tmp_path / "ngk_sparkplugs.xlsx"
        _write_minimal_ngk_xlsx(xlsx_path)

        catalog = load_moto_technical_specs(ngk_xlsx_path=xlsx_path)
        er6n = next(item for item in catalog["specs"] if item.get("marque") == "KAWASAKI" and item.get("modele") == "ER-6n")

        assert er6n["entretien"]["ref_bougie_ngk"] == "NGK CR9EIA-9"
        assert catalog["metadata"]["ngk_rows_loaded"] >= 1

    def test_can_load_ngk_rows_from_vehicle_headers_with_years_and_name(self, tmp_path):
        xlsx_path = tmp_path / "ngk_vehicle.xlsx"
        _write_minimal_ngk_xlsx(
            xlsx_path,
            headers=["Brand", "CC", "Model", "Name", "From", "To", "Sparkplug"],
            rows=[["HONDA", "750", "NC750", "X DCT", "2014", "2020", "LMAR8A-9"]],
        )

        rows = load_ngk_sparkplug_rows(xlsx_path)

        assert rows
        assert rows[0]["marque"] == "HONDA"
        assert rows[0]["modele"] == "NC750"
        assert rows[0]["designation"] == "X DCT"
        assert rows[0]["cylindree"] == 750
        assert rows[0]["annee_debut"] == 2014
        assert rows[0]["annee_fin"] == 2020
        assert rows[0]["ref_bougie_ngk"] == "NGK LMAR8A-9"

    def test_ngk_sync_can_create_missing_model_and_minimal_spec(self, tmp_path):
        xlsx_path = tmp_path / "ngk_missing_model.xlsx"
        _write_minimal_ngk_xlsx(
            xlsx_path,
            headers=["Brand", "CC", "Model", "Name", "From", "To", "Sparkplug"],
            rows=[["TESTBRAND", "300", "Road 300", "Adventure", "2018", "2022", "CR8E"]],
        )

        ngk_rows = load_ngk_sparkplug_rows(xlsx_path)
        db = TestingSessionLocal()
        try:
            catalog_result = sync_ngk_catalog_to_db(db, ngk_rows=ngk_rows)
            specs_result = sync_ngk_minimal_specs_to_db(db, ngk_rows=ngk_rows)

            modele = db.query(ModeleMoto).filter(
                ModeleMoto.marque == "TESTBRAND",
                ModeleMoto.modele == "Road 300",
            ).first()
            assert catalog_result["created"] >= 1
            assert modele is not None
            assert modele.annee_debut == 2018
            assert modele.annee_fin == 2022
            assert modele.cylindree_min == 300

            spec = db.query(MotoTechnicalSpec).filter(MotoTechnicalSpec.modele_moto_id == modele.id).first()
            assert specs_result["created"] >= 1
            assert spec is not None
            assert spec.annee_debut == 2018
            assert spec.annee_fin == 2022
            assert (spec.entretien or {})["ref_bougie_ngk"] == "NGK CR8E"
        finally:
            db.close()

    def test_ngk_sync_keeps_variants_split_by_cylindree_and_years(self, tmp_path):
        xlsx_path = tmp_path / "ngk_variants.xlsx"
        _write_minimal_ngk_xlsx(
            xlsx_path,
            headers=["Brand", "CC", "Model", "Name", "From", "To", "Sparkplug"],
            rows=[
                ["TESTBRAND", "500", "Roadster", "Base", "2010", "2012", "CR7E"],
                ["TESTBRAND", "500", "Roadster", "Base", "2013", "2015", "CR7E"],
                ["TESTBRAND", "650", "Roadster", "Base", "2010", "2015", "CR8E"],
            ],
        )

        ngk_rows = load_ngk_sparkplug_rows(xlsx_path)
        db = TestingSessionLocal()
        try:
            catalog_result = sync_ngk_catalog_to_db(db, ngk_rows=ngk_rows)
            specs_result = sync_ngk_minimal_specs_to_db(db, ngk_rows=ngk_rows)

            modeles = db.query(ModeleMoto).filter(
                ModeleMoto.marque == "TESTBRAND",
                ModeleMoto.modele == "Roadster",
            ).order_by(ModeleMoto.cylindree_min, ModeleMoto.annee_debut).all()

            assert catalog_result["created"] >= 3
            assert len(modeles) == 3
            assert {
                (modele.cylindree_min, modele.annee_debut, modele.annee_fin)
                for modele in modeles
            } == {
                (500, 2010, 2012),
                (500, 2013, 2015),
                (650, 2010, 2015),
            }

            specs = db.query(MotoTechnicalSpec).join(MotoTechnicalSpec.modele).filter(
                ModeleMoto.marque == "TESTBRAND",
                ModeleMoto.modele == "Roadster",
            ).all()
            assert specs_result["created"] >= 3
            assert len(specs) == 3
        finally:
            db.close()

    def test_can_fetch_structured_technical_sheet_for_model_and_year(self, client):
        response = client.get(
            "/api/motos/technical-specs",
            params={"marque": "KAWASAKI", "modele": "ER-5", "annee": 2000},
        )

        assert response.status_code == 200
        data = response.json()
        assert data["general"]["marque_moto"] == "KAWASAKI"
        assert "ER-5" in data["general"]["modele"]
        assert data["general"]["annee"] == 2000
        assert "moteur" in data and isinstance(data["moteur"], dict)
        assert "pneumatique" in data and isinstance(data["pneumatique"], dict)
        assert data["entretien"]["ref_bougie_ngk"].startswith("NGK")
        assert data["entretien"]["ref_filtre_huile_hiflofiltro"].startswith("HF")
        assert data["entretien"]["ref_filtre_air_hiflofiltro"].startswith("HFA")
        assert "diametre_joint_bouchon_vidange_mm" in data["entretien"]
