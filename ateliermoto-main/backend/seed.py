import json
import re
import unicodedata
import zipfile
from pathlib import Path
from xml.etree import ElementTree as ET

from sqlalchemy.orm import Session
from models import InterventionType, Pont, Mecanicien, Fournisseur, PieceDetachee, ConfigAtelier, ForfaitMO, Prestation, CategorieMoto, ModeleMoto, MotoTechnicalSpec


MOTO_CATALOG_PATH = Path(__file__).resolve().parent / "data" / "moto_catalog.json"
MOTO_TECHNICAL_SPECS_PATH = Path(__file__).resolve().parent / "data" / "moto_technical_specs.json"
NGK_SPARKPLUGS_PATH = Path(__file__).resolve().parent / "data" / "ngk_sparkplugs.xlsx"

DEFAULT_MOTO_CATEGORIES = [
    {"nom": "Roadster", "description": "Moto nue, polyvalente et maniable"},
    {"nom": "Sportive", "description": "Moto performante, position sportive"},
    {"nom": "Trail", "description": "Moto tout-terrain et route"},
    {"nom": "Scooter", "description": "Scooter urbain et GT"},
    {"nom": "Cruiser", "description": "Custom, américaine, position relax"},
    {"nom": "Cross/Enduro", "description": "Moto tout-terrain compétition"},
    {"nom": "Touring", "description": "Moto voyage, grand confort"},
    {"nom": "Supermotard", "description": "Moto cross déclinée route"},
]


def _optional_int(value):
    if value in (None, "", "null"):
        return None
    try:
        return int(value)
    except (TypeError, ValueError):
        return None


def load_moto_catalog(catalog_path=None):
    """Charge le catalogue moto externe utilisé pour peupler la BDD."""
    path = Path(catalog_path) if catalog_path else MOTO_CATALOG_PATH
    if not path.exists():
        return {
            "metadata": {},
            "categories": list(DEFAULT_MOTO_CATEGORIES),
            "models": [],
        }

    with path.open("r", encoding="utf-8") as handle:
        payload = json.load(handle)

    categories = payload.get("categories") or list(DEFAULT_MOTO_CATEGORIES)
    models = payload.get("models") or []
    if not isinstance(categories, list) or not isinstance(models, list):
        raise ValueError("Format invalide du catalogue moto")

    return {
        "metadata": payload.get("metadata", {}),
        "categories": categories,
        "models": models,
    }


def sync_moto_catalog_to_db(db: Session, catalog=None):
    """Synchronise le catalogue moto externe dans les tables de référence."""
    catalog = catalog or load_moto_catalog()
    categories = catalog.get("categories") or list(DEFAULT_MOTO_CATEGORIES)

    for cat_data in categories:
        nom = str(cat_data.get("nom") or "").strip()
        if not nom:
            continue
        description = cat_data.get("description")
        existing = db.query(CategorieMoto).filter(CategorieMoto.nom == nom).first()
        if not existing:
            db.add(CategorieMoto(nom=nom, description=description))
        elif description and existing.description != description:
            existing.description = description

    db.commit()

    categories_by_name = {
        cat.nom: cat
        for cat in db.query(CategorieMoto).all()
    }
    existing_modeles = {
        ((item.marque or "").strip().upper(), (item.modele or "").strip().upper()): item
        for item in db.query(ModeleMoto).all()
    }

    created_count = 0
    updated_count = 0

    for raw in catalog.get("models") or []:
        marque = str(raw.get("marque") or "").strip().upper()
        modele = str(raw.get("modele") or "").strip()
        categorie_name = str(raw.get("categorie") or raw.get("categorie_nom") or "").strip()
        categorie = categories_by_name.get(categorie_name)
        if not marque or not modele or not categorie:
            continue

        payload = {
            "marque": marque,
            "modele": modele,
            "categorie_id": categorie.id,
            "cylindree_min": _optional_int(raw.get("cylindree_min")),
            "cylindree_max": _optional_int(raw.get("cylindree_max")),
            "annee_debut": _optional_int(raw.get("annee_debut")),
            "annee_fin": _optional_int(raw.get("annee_fin")),
        }

        key = (marque, modele.upper())
        existing = existing_modeles.get(key)
        if not existing:
            db.add(ModeleMoto(**payload))
            created_count += 1
            continue

        dirty = False
        for field, value in payload.items():
            if getattr(existing, field) != value:
                setattr(existing, field, value)
                dirty = True
        if dirty:
            updated_count += 1

    db.commit()

    total_modeles = db.query(ModeleMoto).count()
    total_marques = len({marque for (marque,) in db.query(ModeleMoto.marque).distinct().all() if marque})
    metadata = catalog.get("metadata") or {}

    return {
        "message": "Catalogue moto synchronisé",
        "created": created_count,
        "updated": updated_count,
        "total_modeles": total_modeles,
        "nb_marques": total_marques,
        "catalog_version": metadata.get("version"),
        "catalog_source": metadata.get("source"),
    }


def _normalize_ascii(value):
    text = unicodedata.normalize("NFKD", str(value or "")).encode("ascii", "ignore").decode("ascii")
    return re.sub(r"\s+", " ", text).strip()


def _normalize_key(value):
    key = re.sub(r"[^a-z0-9]+", "_", _normalize_ascii(value).lower()).strip("_")
    aliases = {
        "brand": "marque",
        "constructeur": "marque",
        "make": "marque",
        "cc": "cylindree",
        "cylindree": "cylindree",
        "cylinder_capacity": "cylindree",
        "model": "modele",
        "name": "designation",
        "designation": "designation",
        "modele_moto": "modele",
        "annee_debut_modele": "annee_debut",
        "annee_debut": "annee_debut",
        "year_from": "annee_debut",
        "from_year": "annee_debut",
        "from": "annee_debut",
        "debut": "annee_debut",
        "annee_fin_modele": "annee_fin",
        "annee_fin": "annee_fin",
        "year_to": "annee_fin",
        "to_year": "annee_fin",
        "to": "annee_fin",
        "fin": "annee_fin",
        "bougie_ngk": "ref_bougie_ngk",
        "ref_bougie_ngk": "ref_bougie_ngk",
        "reference_ngk": "ref_bougie_ngk",
        "spark_plug": "ref_bougie_ngk",
        "sparkplug": "ref_bougie_ngk",
        "type_bougie": "ref_bougie_ngk",
        "bougie": "ref_bougie_ngk",
        "ngk": "ref_bougie_ngk",
    }
    return aliases.get(key, key)


def _normalize_model_key(value):
    return re.sub(r"[^A-Z0-9]+", "", _normalize_ascii(value).upper())


def _xlsx_column_index(cell_ref):
    letters = "".join(ch for ch in str(cell_ref or "") if ch.isalpha()).upper()
    index = 0
    for char in letters:
        index = index * 26 + (ord(char) - 64)
    return max(index - 1, 0)


def _read_xlsx_sheet_rows(xlsx_path):
    ns = {"x": "http://schemas.openxmlformats.org/spreadsheetml/2006/main"}
    rows = []
    with zipfile.ZipFile(xlsx_path) as archive:
        shared_strings = []
        if "xl/sharedStrings.xml" in archive.namelist():
            root = ET.fromstring(archive.read("xl/sharedStrings.xml"))
            for si in root.findall("x:si", ns):
                chunks = [node.text or "" for node in si.findall(".//x:t", ns)]
                shared_strings.append("".join(chunks))

        sheet_name = next((name for name in archive.namelist() if name.startswith("xl/worksheets/") and name.endswith(".xml")), None)
        if not sheet_name:
            return []

        sheet_root = ET.fromstring(archive.read(sheet_name))
        for row in sheet_root.findall(".//x:sheetData/x:row", ns):
            values = {}
            for cell in row.findall("x:c", ns):
                idx = _xlsx_column_index(cell.attrib.get("r", "A1"))
                cell_type = cell.attrib.get("t")
                if cell_type == "inlineStr":
                    value = "".join(node.text or "" for node in cell.findall(".//x:t", ns))
                else:
                    value_node = cell.find("x:v", ns)
                    value = value_node.text if value_node is not None else ""
                    if cell_type == "s" and value not in (None, ""):
                        try:
                            value = shared_strings[int(value)]
                        except Exception:
                            value = ""
                values[idx] = (value or "").strip()
            if values:
                max_idx = max(values)
                rows.append([values.get(i, "") for i in range(max_idx + 1)])
    return rows


def _compose_ngk_modele_label(modele, designation=None):
    base = _normalize_ascii(modele or "")
    extra = _normalize_ascii(designation or "")
    if not base:
        return extra
    if not extra:
        return base
    if _normalize_model_key(extra) == _normalize_model_key(base):
        return base
    if base.isdigit() or len(base) <= 3:
        return f"{extra} {base}".strip()
    return base


def _infer_ngk_category_name(marque, modele, designation=None, cylindree=None):
    text = " ".join([_normalize_ascii(marque), _normalize_ascii(modele), _normalize_ascii(designation)]).lower()
    if any(token in text for token in ["scoot", "scooter", "burgman", "xmax", "tmax", "vespa", "forza", "pcx", "nmax", "gts", "primavera", "sprint", "people", "downtown", "citycom", "cruisym", "joymax", "symphony", "maxsym"]):
        return "Scooter"
    if any(token in text for token in ["africa twin", "transalp", "tenere", "tracer", "multistrada", "v-strom", "versys", "gs", "adventure", "tiger", "caponord", "pegaso", "nc750x", "nx", "dr650", "xt660", "xtz"]):
        return "Trail"
    if any(token in text for token in ["cbr", "r1", "r6", "r7", "r125", "zx", "ninja", "gsx-r", "daytona", "panigale", "f3", "f4", "fzr", "rsv", "supersport", "sport"]):
        return "Sportive"
    if any(token in text for token in ["shadow", "dragstar", "virago", "vulcan", "intruder", "boulevard", "softail", "sportster", "fat bob", "fat boy", "cruiser", "diavel", "rocket", "eliminator"]):
        return "Cruiser"
    if any(token in text for token in ["crf", "wr", "yz", "kx", "rmz", "exc", "sx", "fe", "te", "enduro", "cross"]):
        return "Cross/Enduro"
    if any(token in text for token in ["smc", "supermot", "dr-z400sm", "690 sm", "701 sm"]):
        return "Supermotard"
    if any(token in text for token in ["gold wing", "fjr", "k1600", "trophy", "pan european", "deauville", "rt", "gt"]):
        return "Touring"
    return "Roadster"


def load_ngk_sparkplug_rows(xlsx_path=None):
    """Charge une base NGK depuis un export XLSX sans dépendance externe."""
    path = Path(xlsx_path) if xlsx_path else NGK_SPARKPLUGS_PATH
    if not path.exists():
        return []

    rows = _read_xlsx_sheet_rows(path)
    if not rows:
        return []

    headers = [_normalize_key(value) or f"col_{idx}" for idx, value in enumerate(rows[0])]
    parsed = []
    for raw_row in rows[1:]:
        item = {}
        for idx, raw_value in enumerate(raw_row):
            value = _normalize_ascii(raw_value)
            if not value:
                continue
            item[headers[idx]] = value

        marque = _normalize_ascii(item.get("marque") or "").upper()
        modele = _normalize_ascii(item.get("modele") or "")
        designation = _normalize_ascii(item.get("designation") or "")
        ref_bougie = _normalize_ascii(item.get("ref_bougie_ngk") or "")
        if not marque or not modele or not ref_bougie:
            continue

        if ref_bougie and not ref_bougie.upper().startswith("NGK"):
            ref_bougie = f"NGK {ref_bougie}"

        parsed.append({
            "marque": marque,
            "modele": _compose_ngk_modele_label(modele, designation),
            "modele_base": modele,
            "designation": designation or None,
            "cylindree": _optional_int(item.get("cylindree")),
            "annee_debut": _optional_int(item.get("annee_debut")),
            "annee_fin": _optional_int(item.get("annee_fin")),
            "ref_bougie_ngk": ref_bougie,
        })
    return parsed


def merge_ngk_sparkplug_rows_into_specs_data(catalog, ngk_rows):
    """Injecte les refs NGK d'un export XLSX dans les fiches techniques chargées."""
    specs = (catalog or {}).get("specs") or []
    updated = 0

    for spec in specs:
        marque = _normalize_ascii(spec.get("marque") or "").upper()
        modele = _normalize_model_key(spec.get("modele") or "")
        spec_start = _optional_int(spec.get("annee_debut")) or 1900
        spec_end = _optional_int(spec.get("annee_fin")) or 2100
        if not modele:
            continue

        best_row = None
        best_score = -1
        for row in ngk_rows or []:
            row_candidates = [
                _normalize_model_key(row.get("modele") or ""),
                _normalize_model_key(row.get("modele_base") or ""),
                _normalize_model_key(row.get("designation") or ""),
            ]
            row_candidates = [candidate for candidate in row_candidates if candidate]
            if not row_candidates:
                continue
            row_marque = _normalize_ascii(row.get("marque") or "").upper()
            if row_marque and marque and row_marque != marque:
                continue

            score = -1
            for candidate in row_candidates:
                if candidate == modele:
                    score = max(score, 5)
                elif candidate in modele or modele in candidate:
                    score = max(score, 2)
            if score < 0:
                continue

            row_start = _optional_int(row.get("annee_debut")) or 1900
            row_end = _optional_int(row.get("annee_fin")) or 2100
            if row_start > spec_end or row_end < spec_start:
                continue
            score += 2 if row.get("ref_bougie_ngk") else 0
            if score > best_score:
                best_row = row
                best_score = score

        if not best_row:
            continue

        entretien = spec.setdefault("entretien", {})
        ref_bougie = best_row.get("ref_bougie_ngk")
        if ref_bougie and entretien.get("ref_bougie_ngk") != ref_bougie:
            entretien["ref_bougie_ngk"] = ref_bougie
            updated += 1

    if catalog is not None:
        metadata = catalog.setdefault("metadata", {})
        metadata["ngk_rows_loaded"] = len(ngk_rows or [])
        metadata["ngk_refs_merged"] = updated
    return updated


def sync_ngk_catalog_to_db(db: Session, ngk_rows=None):
    """Ajoute les motos absentes du catalogue à partir du véhiculier NGK."""
    ngk_rows = ngk_rows or load_ngk_sparkplug_rows()
    if not ngk_rows:
        return {"created": 0, "updated": 0, "rows": 0}

    sync_moto_catalog_to_db(db, {"categories": list(DEFAULT_MOTO_CATEGORIES), "models": []})
    categories_by_name = {cat.nom: cat for cat in db.query(CategorieMoto).all()}
    existing_modeles = {
        ((item.marque or "").strip().upper(), _normalize_model_key(item.modele or "")): item
        for item in db.query(ModeleMoto).all()
    }

    grouped = {}
    for row in ngk_rows:
        marque = _normalize_ascii(row.get("marque") or "").upper()
        modele = _normalize_ascii(row.get("modele") or "")
        key = (marque, _normalize_model_key(modele))
        if not marque or not key[1]:
            continue
        bucket = grouped.setdefault(key, {
            "marque": marque,
            "modele": modele,
            "designation": row.get("designation"),
            "cylindree": row.get("cylindree"),
            "annee_debut": row.get("annee_debut"),
            "annee_fin": row.get("annee_fin"),
        })
        if row.get("cylindree") and not bucket.get("cylindree"):
            bucket["cylindree"] = row.get("cylindree")
        start = _optional_int(row.get("annee_debut"))
        end = _optional_int(row.get("annee_fin"))
        if start is not None:
            current = _optional_int(bucket.get("annee_debut"))
            bucket["annee_debut"] = start if current is None else min(current, start)
        if end is not None:
            current = _optional_int(bucket.get("annee_fin"))
            bucket["annee_fin"] = end if current is None else max(current, end)

    created_count = 0
    updated_count = 0
    for key, row in grouped.items():
        marque, _ = key
        categorie_name = _infer_ngk_category_name(marque, row.get("modele"), row.get("designation"), row.get("cylindree"))
        categorie = categories_by_name.get(categorie_name) or categories_by_name.get("Roadster")
        if not categorie:
            continue

        payload = {
            "marque": marque,
            "modele": row.get("modele"),
            "categorie_id": categorie.id,
            "cylindree_min": _optional_int(row.get("cylindree")),
            "cylindree_max": _optional_int(row.get("cylindree")),
            "annee_debut": _optional_int(row.get("annee_debut")),
            "annee_fin": _optional_int(row.get("annee_fin")),
        }
        existing = existing_modeles.get(key)
        if not existing:
            existing = ModeleMoto(**payload)
            db.add(existing)
            db.flush()
            existing_modeles[key] = existing
            created_count += 1
            continue

        dirty = False
        for field in ["cylindree_min", "cylindree_max", "annee_debut", "annee_fin"]:
            value = payload.get(field)
            current = getattr(existing, field)
            if current is None and value is not None:
                setattr(existing, field, value)
                dirty = True
            elif field == "annee_debut" and value is not None and current is not None and value < current:
                setattr(existing, field, value)
                dirty = True
            elif field == "annee_fin" and value is not None and current is not None and value > current:
                setattr(existing, field, value)
                dirty = True
        if dirty:
            updated_count += 1

    db.commit()
    return {"created": created_count, "updated": updated_count, "rows": len(ngk_rows)}


def sync_ngk_minimal_specs_to_db(db: Session, ngk_rows=None):
    """Crée des fiches techniques minimales pour les motos connues du véhiculier NGK mais absentes des fiches JSON."""
    ngk_rows = ngk_rows or load_ngk_sparkplug_rows()
    if not ngk_rows:
        return {"created": 0, "updated": 0}

    modeles_by_key = {
        ((item.marque or "").strip().upper(), _normalize_model_key(item.modele or "")): item
        for item in db.query(ModeleMoto).all()
    }
    existing_specs = {
        (item.modele_moto_id, item.annee_debut, item.annee_fin, item.variante or ""): item
        for item in db.query(MotoTechnicalSpec).all()
    }

    created_count = 0
    updated_count = 0
    for row in ngk_rows:
        marque = _normalize_ascii(row.get("marque") or "").upper()
        modele = _normalize_ascii(row.get("modele") or "")
        key = (marque, _normalize_model_key(modele))
        modele_ref = modeles_by_key.get(key)
        if not modele_ref:
            continue

        annee_debut = _optional_int(row.get("annee_debut")) or _optional_int(modele_ref.annee_debut) or 1990
        annee_fin = _optional_int(row.get("annee_fin")) or _optional_int(modele_ref.annee_fin)
        variante = _normalize_ascii(row.get("designation") or "") or None
        if variante and _normalize_model_key(variante) == _normalize_model_key(modele_ref.modele):
            variante = None

        payload = {
            "modele_moto_id": modele_ref.id,
            "variante": variante,
            "annee_debut": annee_debut,
            "annee_fin": annee_fin,
            "source": "NGK vehicle application guide",
            "general_json": _json_dump({
                "marque_moto": marque,
                "modele": modele_ref.modele,
                "cylindree": _optional_int(row.get("cylindree")) or _optional_int(modele_ref.cylindree_min),
                "annee": annee_debut,
                "univers": modele_ref.categorie.nom if modele_ref.categorie else None,
            }),
            "moteur_json": _json_dump({
                "cylindree_constructeur_cc": _optional_int(row.get("cylindree")) or _optional_int(modele_ref.cylindree_min),
            }),
            "pneumatique_json": _json_dump({}),
            "freinage_json": _json_dump({}),
            "suspension_json": _json_dump({}),
            "systemes_electriques_json": _json_dump({}),
            "entretien_json": _json_dump({"ref_bougie_ngk": row.get("ref_bougie_ngk")}),
            "notes": "Fiche minimale générée depuis le véhiculier NGK; compléter les autres données atelier si disponibles.",
        }

        key_spec = (modele_ref.id, annee_debut, annee_fin, variante or "")
        existing = existing_specs.get(key_spec)
        if not existing:
            db.add(MotoTechnicalSpec(**payload))
            created_count += 1
            continue

        entretien = existing.entretien or {}
        ref_bougie = row.get("ref_bougie_ngk")
        if ref_bougie and entretien.get("ref_bougie_ngk") != ref_bougie:
            entretien["ref_bougie_ngk"] = ref_bougie
            existing.entretien_json = _json_dump(entretien)
            updated_count += 1

    db.commit()
    return {"created": created_count, "updated": updated_count}


def load_moto_technical_specs(specs_path=None, ngk_xlsx_path=None):
    """Charge le référentiel de fiches techniques moto 1990+ depuis un fichier externe."""
    path = Path(specs_path) if specs_path else MOTO_TECHNICAL_SPECS_PATH
    if not path.exists():
        return {"metadata": {}, "specs": []}

    with path.open("r", encoding="utf-8") as handle:
        payload = json.load(handle)

    specs = payload.get("specs") or []
    if not isinstance(specs, list):
        raise ValueError("Format invalide des fiches techniques moto")

    catalog = {
        "metadata": payload.get("metadata", {}),
        "specs": specs,
    }

    ngk_rows = load_ngk_sparkplug_rows(ngk_xlsx_path)
    if ngk_rows:
        merge_ngk_sparkplug_rows_into_specs_data(catalog, ngk_rows)

    return catalog


def _json_dump(value):
    return json.dumps(value or {}, ensure_ascii=False)


def sync_moto_technical_specs_to_db(db: Session, catalog=None):
    """Synchronise les fiches techniques détaillées dans la BDD."""
    sync_moto_catalog_to_db(db)
    ngk_rows = load_ngk_sparkplug_rows()
    ngk_catalog_result = sync_ngk_catalog_to_db(db, ngk_rows=ngk_rows) if ngk_rows else {"created": 0, "updated": 0, "rows": 0}
    catalog = catalog or load_moto_technical_specs(ngk_xlsx_path=NGK_SPARKPLUGS_PATH)

    modeles_by_key = {
        ((item.marque or "").strip().upper(), (item.modele or "").strip().upper()): item
        for item in db.query(ModeleMoto).all()
    }
    existing_specs = {
        (item.modele_moto_id, item.annee_debut, item.annee_fin, item.variante or ""): item
        for item in db.query(MotoTechnicalSpec).all()
    }

    created_count = 0
    updated_count = 0

    for raw in catalog.get("specs") or []:
        marque = str(raw.get("marque") or "").strip().upper()
        modele = str(raw.get("modele") or "").strip()
        if not marque or not modele:
            continue

        modele_ref = modeles_by_key.get((marque, modele.upper()))
        if not modele_ref:
            continue

        annee_debut = _optional_int(raw.get("annee_debut")) or 1990
        annee_fin = _optional_int(raw.get("annee_fin"))
        variante = (raw.get("variante") or "").strip() or None
        key = (modele_ref.id, annee_debut, annee_fin, variante or "")

        payload = {
            "modele_moto_id": modele_ref.id,
            "variante": variante,
            "annee_debut": annee_debut,
            "annee_fin": annee_fin,
            "source": raw.get("source"),
            "general_json": _json_dump(raw.get("general")),
            "moteur_json": _json_dump(raw.get("moteur")),
            "pneumatique_json": _json_dump(raw.get("pneumatique")),
            "freinage_json": _json_dump(raw.get("freinage")),
            "suspension_json": _json_dump(raw.get("suspension")),
            "systemes_electriques_json": _json_dump(raw.get("systemes_electriques")),
            "entretien_json": _json_dump(raw.get("entretien")),
            "notes": raw.get("notes"),
        }

        existing = existing_specs.get(key)
        if not existing:
            db.add(MotoTechnicalSpec(**payload))
            created_count += 1
            continue

        dirty = False
        for field, value in payload.items():
            if getattr(existing, field) != value:
                setattr(existing, field, value)
                dirty = True
        if dirty:
            updated_count += 1

    db.commit()

    ngk_specs_result = sync_ngk_minimal_specs_to_db(db, ngk_rows=ngk_rows) if ngk_rows else {"created": 0, "updated": 0}
    total_specs = db.query(MotoTechnicalSpec).count()
    metadata = catalog.get("metadata") or {}
    return {
        "message": "Fiches techniques moto synchronisées",
        "created": created_count,
        "updated": updated_count,
        "total_specs": total_specs,
        "coverage_from_year": metadata.get("coverage_from_year"),
        "coverage_to_year": metadata.get("coverage_to_year"),
        "catalog_version": metadata.get("version"),
        "ngk_rows_loaded": len(ngk_rows or []),
        "ngk_catalog_created": ngk_catalog_result.get("created", 0),
        "ngk_catalog_updated": ngk_catalog_result.get("updated", 0),
        "ngk_specs_created": ngk_specs_result.get("created", 0),
        "ngk_specs_updated": ngk_specs_result.get("updated", 0),
    }


def init_moto_technical_specs(db: Session):
    """Initialise/synchronise les fiches techniques moto détaillées."""
    return sync_moto_technical_specs_to_db(db)


def init_intervention_types(db: Session):
    """Initialise les types d'intervention avec prix et temps"""
    
    interventions = [
        {
            "nom": "Révision 10 000km",
            "description": "Vidange, filtre à huile, contrôle général",
            "prix_base": 180.0,
            "temps_estime": 90
        },
        {
            "nom": "Révision 20 000km",
            "description": "Vidange, filtres, bougies, contrôle général",
            "prix_base": 280.0,
            "temps_estime": 120
        },
        {
            "nom": "Vidange simple",
            "description": "Remplacement huile et filtre",
            "prix_base": 95.0,
            "temps_estime": 45
        },
        {
            "nom": "Changement pneus (x2)",
            "description": "Démontage, montage, équilibrage",
            "prix_base": 45.0,
            "temps_estime": 60
        },
        {
            "nom": "Révision freins avant",
            "description": "Plaquettes, disques, purge",
            "prix_base": 150.0,
            "temps_estime": 75
        },
        {
            "nom": "Révision freins arrière",
            "description": "Plaquettes, disques/tambours, purge",
            "prix_base": 140.0,
            "temps_estime": 75
        },
        {
            "nom": "Remplacement chaîne/courroie",
            "description": "Chaîne, couronne, pignon, réglage",
            "prix_base": 220.0,
            "temps_estime": 90
        },
        {
            "nom": "Remplacement batterie",
            "description": "Batterie + réinitialisation",
            "prix_base": 120.0,
            "temps_estime": 30
        },
        {
            "nom": "Diagnostic électronique",
            "description": "Lecture codes défaut, analyse",
            "prix_base": 65.0,
            "temps_estime": 30
        },
        {
            "nom": "Montage accessoire",
            "description": "Pose d'accessoires divers",
            "prix_base": 60.0,
            "temps_estime": 45
        }
    ]
    
    for interv in interventions:
        existing = db.query(InterventionType).filter(InterventionType.nom == interv["nom"]).first()
        if not existing:
            new_interv = InterventionType(**interv)
            db.add(new_interv)
    
    db.commit()
    
    # Initialiser les ponts
    ponts = [
        {"nom": "Pont 1", "type_pont": "moto", "capacite_kg": 500, "ordre_affichage": 1},
        {"nom": "Pont 2", "type_pont": "moto", "capacite_kg": 500, "ordre_affichage": 2},
        {"nom": "Pont 3", "type_pont": "moto", "capacite_kg": 500, "ordre_affichage": 3},
        {"nom": "Pont Scooter", "type_pont": "scooter", "capacite_kg": 300, "ordre_affichage": 4},
    ]
    
    for pont in ponts:
        existing = db.query(Pont).filter(Pont.nom == pont["nom"]).first()
        if not existing:
            new_pont = Pont(**pont)
            db.add(new_pont)
    
    db.commit()
    
    # Initialiser les mécaniciens
    mecaniciens = [
        {"nom": "Martin", "prenom": "Jean", "specialites": "[\"moteur\", \"revision\"]", "couleur": "#3b82f6"},
        {"nom": "Dubois", "prenom": "Pierre", "specialites": "[\"electricite\", \"diagnostic\"]", "couleur": "#10b981"},
        {"nom": "Bernard", "prenom": "Lucas", "specialites": "[\"carrosserie\", \"peinture\"]", "couleur": "#f59e0b"},
        {"nom": "Petit", "prenom": "Marc", "specialites": "[\"moteur\", \"transmission\"]", "couleur": "#8b5cf6"},
    ]
    
    for mecano in mecaniciens:
        existing = db.query(Mecanicien).filter(
            Mecanicien.nom == mecano["nom"],
            Mecanicien.prenom == mecano["prenom"]
        ).first()
        if not existing:
            new_mecano = Mecanicien(**mecano)
            db.add(new_mecano)
    
    db.commit()
    
    # Initialiser les fournisseurs
    fournisseurs = [
        {
            "nom": "Moto Pieces France",
            "contact": "Jean Dupont",
            "telephone": "01 23 45 67 89",
            "email": "contact@motopieces.fr",
            "adresse": "15 Rue des Pièces, 75011 Paris",
            "siret": "123 456 789 00012",
            "delai_livraison_jours": 2,
            "notes": "Fournisseur principal, bon rapport qualité/prix"
        },
        {
            "nom": "Bike Parts Pro",
            "contact": "Marie Leroy",
            "telephone": "04 56 78 90 12",
            "email": "commandes@bikepartspro.fr",
            "adresse": "42 Avenue de la Mécanique, 69003 Lyon",
            "siret": "987 654 321 00021",
            "delai_livraison_jours": 3,
            "notes": "Spécialisé pièces haute performance"
        },
        {
            "nom": "Scoot Distribution",
            "contact": "Pierre Martin",
            "telephone": "03 20 45 67 89",
            "email": "contact@scootdistribution.fr",
            "adresse": "8 Zone Industrielle Nord, 59000 Lille",
            "siret": "456 789 123 00034",
            "delai_livraison_jours": 1,
            "notes": "Spécialisé scooters et mobylettes"
        },
        {
            "nom": "Huiles et Lubrifiants 2000",
            "contact": "Sophie Bernard",
            "telephone": "05 61 23 45 67",
            "email": "pro@huiles2000.fr",
            "adresse": "25 Rue de l'Industrie, 31000 Toulouse",
            "siret": "789 123 456 00045",
            "delai_livraison_jours": 2,
            "notes": "Toutes marques d'huiles et graisses"
        }
    ]
    
    for fournisseur_data in fournisseurs:
        existing = db.query(Fournisseur).filter(Fournisseur.nom == fournisseur_data["nom"]).first()
        if not existing:
            new_fournisseur = Fournisseur(**fournisseur_data)
            db.add(new_fournisseur)
    
    db.commit()
    
    # Initialiser les pièces détachées
    pieces = [
        # Huiles et filtres
        {
            "reference": "HUI-10W40-1L",
            "reference_fournisseur": "MOTUL-10W40",
            "nom": "Huile moteur 10W40 1L",
            "description": "Huile semi-synthétique pour moteur 4 temps",
            "categorie": "huiles",
            "quantite_stock": 24,
            "quantite_minimale": 12,
            "quantite_maximale": 50,
            "emplacement": "Étagère A1",
            "prix_achat_ht": 8.50,
            "prix_vente_ht": 14.90,
            "tva_taux": 20.0,
            "fournisseur_nom": "Huiles et Lubrifiants 2000"
        },
        {
            "reference": "HUI-10W40-4L",
            "reference_fournisseur": "MOTUL-10W40-4L",
            "nom": "Huile moteur 10W40 4L",
            "description": "Huile semi-synthétique 4L, économique",
            "categorie": "huiles",
            "quantite_stock": 8,
            "quantite_minimale": 4,
            "quantite_maximale": 15,
            "emplacement": "Étagère A1",
            "prix_achat_ht": 28.00,
            "prix_vente_ht": 45.90,
            "tva_taux": 20.0,
            "fournisseur_nom": "Huiles et Lubrifiants 2000"
        },
        {
            "reference": "FIL-HUI-STD",
            "reference_fournisseur": "HF-204",
            "nom": "Filtre à huile standard",
            "description": "Filtre à huile universel 4 temps",
            "categorie": "filtres",
            "quantite_stock": 15,
            "quantite_minimale": 10,
            "quantite_maximale": 40,
            "emplacement": "Étagère A2",
            "prix_achat_ht": 4.50,
            "prix_vente_ht": 9.90,
            "tva_taux": 20.0,
            "fournisseur_nom": "Moto Pieces France"
        },
        {
            "reference": "FIL-AIR-STD",
            "reference_fournisseur": "HA-101",
            "nom": "Filtre à air standard",
            "description": "Filtre à air adaptable multi-marques",
            "categorie": "filtres",
            "quantite_stock": 6,
            "quantite_minimale": 8,
            "quantite_maximale": 25,
            "emplacement": "Étagère A2",
            "prix_achat_ht": 8.00,
            "prix_vente_ht": 16.50,
            "tva_taux": 20.0,
            "fournisseur_nom": "Moto Pieces France"
        },
        # Freinage
        {
            "reference": "PLA-FRE-AV",
            "reference_fournisseur": "BP-AV-STD",
            "nom": "Plaquettes frein avant",
            "description": "Plaquettes frein avant organiques",
            "categorie": "freinage",
            "quantite_stock": 12,
            "quantite_minimale": 6,
            "quantite_maximale": 20,
            "emplacement": "Étagère B1",
            "prix_achat_ht": 12.00,
            "prix_vente_ht": 29.90,
            "tva_taux": 20.0,
            "fournisseur_nom": "Moto Pieces France"
        },
        {
            "reference": "PLA-FRE-AR",
            "reference_fournisseur": "BP-AR-STD",
            "nom": "Plaquettes frein arrière",
            "description": "Plaquettes frein arrière organiques",
            "categorie": "freinage",
            "quantite_stock": 4,
            "quantite_minimale": 5,
            "quantite_maximale": 20,
            "emplacement": "Étagère B1",
            "prix_achat_ht": 11.00,
            "prix_vente_ht": 26.90,
            "tva_taux": 20.0,
            "fournisseur_nom": "Moto Pieces France"
        },
        {
            "reference": "DIS-FRE-AV-260",
            "reference_fournisseur": "DISC-260-BREM",
            "nom": "Disque de frein avant 260mm",
            "description": "Disque de frein avant flottant 260mm",
            "categorie": "freinage",
            "quantite_stock": 3,
            "quantite_minimale": 2,
            "quantite_maximale": 10,
            "emplacement": "Étagère B2",
            "prix_achat_ht": 45.00,
            "prix_vente_ht": 89.90,
            "tva_taux": 20.0,
            "fournisseur_nom": "Bike Parts Pro"
        },
        {
            "reference": "LIQ-FRE-500",
            "reference_fournisseur": "DOT4-500",
            "nom": "Liquide de frein DOT4 500ml",
            "description": "Liquide de frein synthétique DOT4",
            "categorie": "freinage",
            "quantite_stock": 18,
            "quantite_minimale": 6,
            "quantite_maximale": 24,
            "emplacement": "Étagère B3",
            "prix_achat_ht": 3.50,
            "prix_vente_ht": 7.90,
            "tva_taux": 20.0,
            "fournisseur_nom": "Huiles et Lubrifiants 2000"
        },
        # Transmission
        {
            "reference": "CHAI-520-120",
            "reference_fournisseur": "CHAIN-520-120L",
            "nom": "Chaîne 520 x 120 maillons",
            "description": "Chaîne de transmission 520 oring",
            "categorie": "transmission",
            "quantite_stock": 5,
            "quantite_minimale": 3,
            "quantite_maximale": 12,
            "emplacement": "Étagère C1",
            "prix_achat_ht": 35.00,
            "prix_vente_ht": 69.90,
            "tva_taux": 20.0,
            "fournisseur_nom": "Bike Parts Pro"
        },
        {
            "reference": "KIT-CHAINE-YAM-MT07",
            "reference_fournisseur": "AFAM-MT07-KIT",
            "nom": "Kit chaîne Yamaha MT-07",
            "description": "Chaîne + couronne + pignon pour MT-07",
            "categorie": "transmission",
            "quantite_stock": 2,
            "quantite_minimale": 2,
            "quantite_maximale": 8,
            "emplacement": "Étagère C1",
            "prix_achat_ht": 85.00,
            "prix_vente_ht": 159.90,
            "tva_taux": 20.0,
            "fournisseur_nom": "Bike Parts Pro"
        },
        # Électricité
        {
            "reference": "BAT-12V-9AH",
            "reference_fournisseur": "YUASA-YTX9",
            "nom": "Batterie 12V 9Ah",
            "description": "Batterie sans entretien YTX9-BS",
            "categorie": "electricite",
            "quantite_stock": 7,
            "quantite_minimale": 4,
            "quantite_maximale": 15,
            "emplacement": "Étagère D1",
            "prix_achat_ht": 32.00,
            "prix_vente_ht": 59.90,
            "tva_taux": 20.0,
            "fournisseur_nom": "Moto Pieces France"
        },
        {
            "reference": "BOU-IRI-CR9E",
            "reference_fournisseur": "NGK-CR9E",
            "nom": "Bougie NGK CR9E",
            "description": "Bougie d'allumage NGK standard",
            "categorie": "electricite",
            "quantite_stock": 20,
            "quantite_minimale": 12,
            "quantite_maximale": 40,
            "emplacement": "Étagère D2",
            "prix_achat_ht": 6.00,
            "prix_vente_ht": 12.90,
            "tva_taux": 20.0,
            "fournisseur_nom": "Moto Pieces France"
        },
        # Pneumatiques
        {
            "reference": "PNE-120-70-17",
            "reference_fournisseur": "MIC-ROAD-120",
            "nom": "Pneu avant 120/70-17",
            "description": "Pneu route sport 120/70 ZR17",
            "categorie": "pneumatiques",
            "quantite_stock": 4,
            "quantite_minimale": 4,
            "quantite_maximale": 12,
            "emplacement": "Rack Pneus",
            "prix_achat_ht": 65.00,
            "prix_vente_ht": 119.90,
            "tva_taux": 20.0,
            "fournisseur_nom": "Bike Parts Pro"
        },
        {
            "reference": "PNE-180-55-17",
            "reference_fournisseur": "MIC-ROAD-180",
            "nom": "Pneu arrière 180/55-17",
            "description": "Pneu route sport 180/55 ZR17",
            "categorie": "pneumatiques",
            "quantite_stock": 2,
            "quantite_minimale": 4,
            "quantite_maximale": 12,
            "emplacement": "Rack Pneus",
            "prix_achat_ht": 85.00,
            "prix_vente_ht": 149.90,
            "tva_taux": 20.0,
            "fournisseur_nom": "Bike Parts Pro"
        },
        # Consommables
        {
            "reference": "GRA-CHA-400",
            "reference_fournisseur": "MOTUL-C4-400",
            "nom": "Graisse chaîne 400ml",
            "description": "Graisse blanche pour chaîne",
            "categorie": "consommables",
            "quantite_stock": 14,
            "quantite_minimale": 6,
            "quantite_maximale": 20,
            "emplacement": "Étagère E1",
            "prix_achat_ht": 9.00,
            "prix_vente_ht": 16.90,
            "tva_taux": 20.0,
            "fournisseur_nom": "Huiles et Lubrifiants 2000"
        },
        {
            "reference": "NET-CHA-1L",
            "reference_fournisseur": "MOTUL-C1-1L",
            "nom": "Nettoyant chaîne 1L",
            "description": "Dégraissant chaîne et freins",
            "categorie": "consommables",
            "quantite_stock": 9,
            "quantite_minimale": 4,
            "quantite_maximale": 15,
            "emplacement": "Étagère E1",
            "prix_achat_ht": 11.00,
            "prix_vente_ht": 19.90,
            "tva_taux": 20.0,
            "fournisseur_nom": "Huiles et Lubrifiants 2000"
        }
    ]
    
    # Créer les pièces
    for piece_data in pieces:
        existing = db.query(PieceDetachee).filter(PieceDetachee.reference == piece_data["reference"]).first()
        if not existing:
            # Récupérer le fournisseur
            fournisseur_nom = piece_data.pop("fournisseur_nom", None)
            fournisseur_id = None
            if fournisseur_nom:
                fournisseur = db.query(Fournisseur).filter(Fournisseur.nom == fournisseur_nom).first()
                if fournisseur:
                    fournisseur_id = fournisseur.id
            
            piece_data["fournisseur_id"] = fournisseur_id
            new_piece = PieceDetachee(**piece_data)
            db.add(new_piece)
    
    db.commit()
    
    # Initialiser la configuration atelier
    config = db.query(ConfigAtelier).first()
    if not config:
        config = ConfigAtelier(
            taux_horaire_mo_standard=65.0,
            taux_horaire_mo_complexe=85.0,
            taux_horaire_mo_expert=95.0,
            marge_pieces_standard=30.0,
            marge_pieces_consommable=50.0,
            marge_pieces_pneumatique=25.0,
            forfait_mo_minimum=25.0,
            tva_mo_taux=20.0,
            tva_pieces_taux=20.0,
            validite_devis_jours=30,
            accompte_pourcentage=30.0
        )
        db.add(config)
        db.commit()
    
    # Initialiser les forfaits MO
    forfaits = [
        {
            "code": "VID-STD",
            "nom": "Vidange standard",
            "description": "Vidange moteur + filtre à huile",
            "categorie": "vidange",
            "temps_base_minutes": 45,
            "taux_horaire_applique": "standard",
            "prix_forfait_mo_ht": 48.75,
            "prix_forfait_mo_ttc": 58.50,
            "inclut_pieces": 0,
            "type_vehicule": "tous",
            "cylindree_min": None,
            "cylindree_max": None
        },
        {
            "code": "REV-125",
            "nom": "Révision 125cc",
            "description": "Vidange, filtres, contrôles, réglages",
            "categorie": "revision",
            "temps_base_minutes": 90,
            "taux_horaire_applique": "standard",
            "prix_forfait_mo_ht": 97.50,
            "prix_forfait_mo_ttc": 117.00,
            "inclut_pieces": 0,
            "type_vehicule": "moto",
            "cylindree_min": 50,
            "cylindree_max": 125
        },
        {
            "code": "REV-500",
            "nom": "Révision 300-500cc",
            "description": "Vidange, filtres, bougies, contrôles complets",
            "categorie": "revision",
            "temps_base_minutes": 120,
            "taux_horaire_applique": "standard",
            "prix_forfait_mo_ht": 130.00,
            "prix_forfait_mo_ttc": 156.00,
            "inclut_pieces": 0,
            "type_vehicule": "moto",
            "cylindree_min": 300,
            "cylindree_max": 500
        },
        {
            "code": "REV-1000",
            "nom": "Révision +500cc",
            "description": "Révision complète moteur gros cube",
            "categorie": "revision",
            "temps_base_minutes": 150,
            "taux_horaire_applique": "complexe",
            "prix_forfait_mo_ht": 212.50,
            "prix_forfait_mo_ttc": 255.00,
            "inclut_pieces": 0,
            "type_vehicule": "moto",
            "cylindree_min": 500,
            "cylindree_max": None
        },
        {
            "code": "FRE-AV",
            "nom": "Révision freinage avant",
            "description": "Plaquettes, disque, purge circuit",
            "categorie": "freinage",
            "temps_base_minutes": 75,
            "taux_horaire_applique": "standard",
            "prix_forfait_mo_ht": 81.25,
            "prix_forfait_mo_ttc": 97.50,
            "inclut_pieces": 0,
            "type_vehicule": "tous",
            "cylindree_min": None,
            "cylindree_max": None
        },
        {
            "code": "FRE-AR",
            "nom": "Révision freinage arrière",
            "description": "Plaquettes/tambours, purge circuit",
            "categorie": "freinage",
            "temps_base_minutes": 60,
            "taux_horaire_applique": "standard",
            "prix_forfait_mo_ht": 65.00,
            "prix_forfait_mo_ttc": 78.00,
            "inclut_pieces": 0,
            "type_vehicule": "tous",
            "cylindree_min": None,
            "cylindree_max": None
        },
        {
            "code": "PNE-MONT",
            "nom": "Montage pneus (la paire)",
            "description": "Démontage, montage, équilibrage",
            "categorie": "pneumatique",
            "temps_base_minutes": 60,
            "taux_horaire_applique": "standard",
            "prix_forfait_mo_ht": 65.00,
            "prix_forfait_mo_ttc": 78.00,
            "inclut_pieces": 0,
            "type_vehicule": "tous",
            "cylindree_min": None,
            "cylindree_max": None
        },
        {
            "code": "KIT-CHAIN",
            "nom": "Remplacement kit chaîne",
            "description": "Chaîne, couronne, pignon, réglage",
            "categorie": "transmission",
            "temps_base_minutes": 90,
            "taux_horaire_applique": "standard",
            "prix_forfait_mo_ht": 97.50,
            "prix_forfait_mo_ttc": 117.00,
            "inclut_pieces": 0,
            "type_vehicule": "moto",
            "cylindree_min": None,
            "cylindree_max": None
        },
        {
            "code": "DIAG-ELEC",
            "nom": "Diagnostic électronique",
            "description": "Lecture codes défaut, analyse complète",
            "categorie": "diagnostic",
            "temps_base_minutes": 45,
            "taux_horaire_applique": "complexe",
            "prix_forfait_mo_ht": 63.75,
            "prix_forfait_mo_ttc": 76.50,
            "inclut_pieces": 0,
            "type_vehicule": "tous",
            "cylindree_min": None,
            "cylindree_max": None
        },
        {
            "code": "CARBU-NET",
            "nom": "Nettoyage carburateur",
            "description": "Démontage, nettoyage, réglage",
            "categorie": "moteur",
            "temps_base_minutes": 90,
            "taux_horaire_applique": "complexe",
            "prix_forfait_mo_ht": 127.50,
            "prix_forfait_mo_ttc": 153.00,
            "inclut_pieces": 0,
            "type_vehicule": "tous",
            "cylindree_min": None,
            "cylindree_max": None
        },
        {
            "code": "VALV-REG",
            "nom": "Réglage soupapes",
            "description": "Contrôle et réglage jeu aux soupapes",
            "categorie": "moteur",
            "temps_base_minutes": 120,
            "taux_horaire_applique": "expert",
            "prix_forfait_mo_ht": 190.00,
            "prix_forfait_mo_ttc": 228.00,
            "inclut_pieces": 0,
            "type_vehicule": "moto",
            "cylindree_min": None,
            "cylindree_max": None
        },
        {
            "code": "VID-SCOOT",
            "nom": "Vidange scooter",
            "description": "Vidange moteur + filtre",
            "categorie": "vidange",
            "temps_base_minutes": 30,
            "taux_horaire_applique": "standard",
            "prix_forfait_mo_ht": 32.50,
            "prix_forfait_mo_ttc": 39.00,
            "inclut_pieces": 0,
            "type_vehicule": "scooter",
            "cylindree_min": None,
            "cylindree_max": None
        }
    ]
    
    for forfait_data in forfaits:
        existing = db.query(ForfaitMO).filter(ForfaitMO.code == forfait_data["code"]).first()
        if not existing:
            new_forfait = ForfaitMO(**forfait_data)
            db.add(new_forfait)
    
    db.commit()


def init_prestations(db: Session):
    """Initialise les prestations du module tarifs"""
    
    prestations = [
        {
            "code": "VID-MOTO-STD",
            "nom": "Vidange moto standard",
            "description": "Vidange moteur et remplacement filtre à huile",
            "categorie": "entretien",
            "sous_categorie": "moteur",
            "prix_base_ht": 48.75,
            "prix_base_ttc": 58.50,
            "temps_estime_minutes": 45,
            "delai_intervention_jours": 1,
            "type_tarif": "forfait",
            "taux_horaire_applique": "standard",
            "type_vehicule": "moto",
            "cylindree_min": 50,
            "cylindree_max": 1000,
            "is_forfait": 0,
            "inclut_pieces": 0,
            "marge_pieces_pourcent": 30.0
        },
        {
            "code": "VID-SCOOT-STD",
            "nom": "Vidange scooter standard",
            "description": "Vidange moteur scooter",
            "categorie": "entretien",
            "sous_categorie": "moteur",
            "prix_base_ht": 32.50,
            "prix_base_ttc": 39.00,
            "temps_estime_minutes": 30,
            "delai_intervention_jours": 1,
            "type_tarif": "forfait",
            "taux_horaire_applique": "standard",
            "type_vehicule": "scooter",
            "cylindree_min": 50,
            "cylindree_max": 650,
            "is_forfait": 0,
            "inclut_pieces": 0,
            "marge_pieces_pourcent": 30.0
        },
        {
            "code": "REV-125-COMP",
            "nom": "Révision complète 125cc",
            "description": "Vidange, filtres, bougies, contrôle et réglages",
            "categorie": "entretien",
            "sous_categorie": "moteur",
            "prix_base_ht": 97.50,
            "prix_base_ttc": 117.00,
            "temps_estime_minutes": 90,
            "delai_intervention_jours": 2,
            "type_tarif": "forfait",
            "taux_horaire_applique": "standard",
            "type_vehicule": "moto",
            "cylindree_min": 50,
            "cylindree_max": 125,
            "is_forfait": 1,
            "inclut_pieces": 1,
            "description_pieces_incluses": "Filtre à huile, filtre à air, bougie",
            "cout_pieces_incluses_ht": 35.0,
            "marge_pieces_pourcent": 30.0
        },
        {
            "code": "REV-500-COMP",
            "nom": "Révision complète 300-500cc",
            "description": "Vidange, filtres, bougies, contrôles complets",
            "categorie": "entretien",
            "sous_categorie": "moteur",
            "prix_base_ht": 130.00,
            "prix_base_ttc": 156.00,
            "temps_estime_minutes": 120,
            "delai_intervention_jours": 2,
            "type_tarif": "forfait",
            "taux_horaire_applique": "standard",
            "type_vehicule": "moto",
            "cylindree_min": 300,
            "cylindree_max": 500,
            "is_forfait": 0,
            "inclut_pieces": 0,
            "marge_pieces_pourcent": 30.0
        },
        {
            "code": "REV-1000-COMP",
            "nom": "Révision complète +500cc",
            "description": "Révision complète moteur gros cube",
            "categorie": "entretien",
            "sous_categorie": "moteur",
            "prix_base_ht": 212.50,
            "prix_base_ttc": 255.00,
            "temps_estime_minutes": 150,
            "delai_intervention_jours": 3,
            "type_tarif": "forfait",
            "taux_horaire_applique": "complexe",
            "type_vehicule": "moto",
            "cylindree_min": 500,
            "cylindree_max": None,
            "is_forfait": 0,
            "inclut_pieces": 0,
            "marge_pieces_pourcent": 30.0
        },
        {
            "code": "FRE-AV-COMP",
            "nom": "Révision freinage avant",
            "description": "Remplacement plaquettes, disque, purge circuit",
            "categorie": "reparation",
            "sous_categorie": "freinage",
            "prix_base_ht": 81.25,
            "prix_base_ttc": 97.50,
            "temps_estime_minutes": 75,
            "delai_intervention_jours": 1,
            "type_tarif": "forfait",
            "taux_horaire_applique": "standard",
            "type_vehicule": "tous",
            "cylindree_min": None,
            "cylindree_max": None,
            "is_forfait": 0,
            "inclut_pieces": 0,
            "marge_pieces_pourcent": 30.0
        },
        {
            "code": "FRE-AR-COMP",
            "nom": "Révision freinage arrière",
            "description": "Remplacement plaquettes/tambours, purge circuit",
            "categorie": "reparation",
            "sous_categorie": "freinage",
            "prix_base_ht": 65.00,
            "prix_base_ttc": 78.00,
            "temps_estime_minutes": 60,
            "delai_intervention_jours": 1,
            "type_tarif": "forfait",
            "taux_horaire_applique": "standard",
            "type_vehicule": "tous",
            "cylindree_min": None,
            "cylindree_max": None,
            "is_forfait": 0,
            "inclut_pieces": 0,
            "marge_pieces_pourcent": 30.0
        },
        {
            "code": "PNE-MONT-PAIR",
            "nom": "Montage pneus (la paire)",
            "description": "Démontage, montage et équilibrage des pneus",
            "categorie": "reparation",
            "sous_categorie": "pneumatique",
            "prix_base_ht": 65.00,
            "prix_base_ttc": 78.00,
            "temps_estime_minutes": 60,
            "delai_intervention_jours": 1,
            "type_tarif": "forfait",
            "taux_horaire_applique": "standard",
            "type_vehicule": "tous",
            "cylindree_min": None,
            "cylindree_max": None,
            "is_forfait": 0,
            "inclut_pieces": 0,
            "marge_pieces_pourcent": 25.0
        },
        {
            "code": "KIT-CHAIN-COMP",
            "nom": "Remplacement kit chaîne complet",
            "description": "Chaîne, couronne, pignon et réglage",
            "categorie": "reparation",
            "sous_categorie": "transmission",
            "prix_base_ht": 97.50,
            "prix_base_ttc": 117.00,
            "temps_estime_minutes": 90,
            "delai_intervention_jours": 2,
            "type_tarif": "forfait",
            "taux_horaire_applique": "standard",
            "type_vehicule": "moto",
            "cylindree_min": None,
            "cylindree_max": None,
            "is_forfait": 0,
            "inclut_pieces": 0,
            "marge_pieces_pourcent": 30.0
        },
        {
            "code": "DIAG-ELEC-FULL",
            "nom": "Diagnostic électronique complet",
            "description": "Lecture codes défaut, analyse et rapport",
            "categorie": "diagnostic",
            "sous_categorie": "electricite",
            "prix_base_ht": 63.75,
            "prix_base_ttc": 76.50,
            "temps_estime_minutes": 45,
            "delai_intervention_jours": 1,
            "type_tarif": "forfait",
            "taux_horaire_applique": "complexe",
            "type_vehicule": "tous",
            "cylindree_min": None,
            "cylindree_max": None,
            "is_forfait": 0,
            "inclut_pieces": 0,
            "marge_pieces_pourcent": 30.0
        },
        {
            "code": "CARBU-NET-COMP",
            "nom": "Nettoyage carburateur complet",
            "description": "Démontage, nettoyage ultrasons, réglage",
            "categorie": "reparation",
            "sous_categorie": "moteur",
            "prix_base_ht": 127.50,
            "prix_base_ttc": 153.00,
            "temps_estime_minutes": 90,
            "delai_intervention_jours": 2,
            "type_tarif": "forfait",
            "taux_horaire_applique": "complexe",
            "type_vehicule": "tous",
            "cylindree_min": None,
            "cylindree_max": None,
            "is_forfait": 0,
            "inclut_pieces": 0,
            "marge_pieces_pourcent": 30.0
        },
        {
            "code": "VALV-REG-JEU",
            "nom": "Réglage jeu aux soupapes",
            "description": "Contrôle et réglage du jeu aux soupapes",
            "categorie": "reparation",
            "sous_categorie": "moteur",
            "prix_base_ht": 190.00,
            "prix_base_ttc": 228.00,
            "temps_estime_minutes": 120,
            "delai_intervention_jours": 3,
            "type_tarif": "forfait",
            "taux_horaire_applique": "expert",
            "type_vehicule": "moto",
            "cylindree_min": None,
            "cylindree_max": None,
            "is_forfait": 0,
            "inclut_pieces": 0,
            "marge_pieces_pourcent": 30.0
        },
        {
            "code": "SUSP-VERIF",
            "nom": "Vérification suspension",
            "description": "Contrôle fourche et amortisseur, réglages",
            "categorie": "entretien",
            "sous_categorie": "suspension",
            "prix_base_ht": 48.75,
            "prix_base_ttc": 58.50,
            "temps_estime_minutes": 45,
            "delai_intervention_jours": 1,
            "type_tarif": "forfait",
            "taux_horaire_applique": "standard",
            "type_vehicule": "tous",
            "cylindree_min": None,
            "cylindree_max": None,
            "is_forfait": 0,
            "inclut_pieces": 0,
            "marge_pieces_pourcent": 30.0
        },
        {
            "code": "BAT-REM",
            "nom": "Remplacement batterie",
            "description": "Remplacement batterie et réinitialisation",
            "categorie": "reparation",
            "sous_categorie": "electricite",
            "prix_base_ht": 32.50,
            "prix_base_ttc": 39.00,
            "temps_estime_minutes": 30,
            "delai_intervention_jours": 1,
            "type_tarif": "forfait",
            "taux_horaire_applique": "standard",
            "type_vehicule": "tous",
            "cylindree_min": None,
            "cylindree_max": None,
            "is_forfait": 0,
            "inclut_pieces": 0,
            "marge_pieces_pourcent": 30.0
        }
    ]
    
    for prestation_data in prestations:
        existing = db.query(Prestation).filter(Prestation.code == prestation_data["code"]).first()
        if not existing:
            new_prestation = Prestation(**prestation_data)
            db.add(new_prestation)

    db.commit()


def init_base_moto(db: Session):
    """Initialise/synchronise la base moto depuis le catalogue externe stocké en BDD."""
    return sync_moto_catalog_to_db(db)
