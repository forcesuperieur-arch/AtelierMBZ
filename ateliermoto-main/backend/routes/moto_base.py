from typing import List, Optional

from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy import case, func
from sqlalchemy.orm import Session

from auth import get_current_user
from models import Atelier, AtelierCategorieMoto, CategorieMoto, ModeleMoto, MotoTechnicalSpec, User, get_db
from seed import sync_moto_catalog_to_db, sync_moto_technical_specs_to_db
from schemas.moto_base import (
    CategorieMotoCreate,
    CategorieMotoResponse,
    ModeleMotoCreate,
    ModeleMotoUpdate,
)

router = APIRouter(tags=["moto-base"])


def _normalize_autocomplete_value(value: Optional[str]) -> str:
    return "".join(ch for ch in str(value or "").lower() if ch.isalnum())


def _normalized_search_expr(column):
    return func.lower(
        func.replace(
            func.replace(
                func.replace(
                    func.replace(column, " ", ""),
                    "-",
                    "",
                ),
                "/",
                "",
            ),
            "_",
            "",
        )
    )


def _require_super_admin(current_user: User) -> None:
    if not current_user or current_user.role != "super_admin":
        raise HTTPException(status_code=403, detail="Acces reserve au super_admin")


def _serialize_technical_spec(spec: MotoTechnicalSpec):
    return {
        "id": spec.id,
        "modele_moto_id": spec.modele_moto_id,
        "marque": spec.modele.marque if spec.modele else None,
        "modele": spec.modele.modele if spec.modele else None,
        "variante": spec.variante,
        "annee_debut": spec.annee_debut,
        "annee_fin": spec.annee_fin,
        "source": spec.source,
        "general": spec.general,
        "moteur": spec.moteur,
        "pneumatique": spec.pneumatique,
        "freinage": spec.freinage,
        "suspension": spec.suspension,
        "systemes_electriques": spec.systemes_electriques,
        "entretien": spec.entretien,
        "notes": spec.notes,
    }


@router.get("/api/motos/categories", response_model=List[CategorieMotoResponse])
def get_categories_moto(
    atelier_slug: Optional[str] = None,
    db: Session = Depends(get_db),
):
    """Liste les catégories de moto (filtrées par atelier si slug fourni)."""
    categories = db.query(CategorieMoto).order_by(CategorieMoto.nom).all()

    active_ids = None
    if atelier_slug:
        atelier = db.query(Atelier).filter(
            Atelier.slug == atelier_slug.strip().lower(),
            Atelier.actif == True,
        ).first()
        if atelier:
            active_rows = db.query(AtelierCategorieMoto).filter(
                AtelierCategorieMoto.atelier_id == atelier.id,
                AtelierCategorieMoto.is_active == True,
            ).all()
            active_ids = {row.categorie_moto_id for row in active_rows}

    result = []
    for cat in categories:
        if active_ids is not None and cat.id not in active_ids:
            continue
        nb_modeles = db.query(ModeleMoto).filter(ModeleMoto.categorie_id == cat.id).count()
        result.append(
            {
                "id": cat.id,
                "nom": cat.nom,
                "description": cat.description,
                "nb_modeles": nb_modeles,
            }
        )
    return result


@router.post("/api/motos/categories")
def create_categorie_moto(
    categorie: CategorieMotoCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Crée une nouvelle catégorie de moto (super_admin)."""
    _require_super_admin(current_user)
    existing = db.query(CategorieMoto).filter(CategorieMoto.nom == categorie.nom).first()
    if existing:
        raise HTTPException(status_code=400, detail="Cette catégorie existe déjà")

    new_categorie = CategorieMoto(**categorie.model_dump())
    db.add(new_categorie)
    db.commit()
    db.refresh(new_categorie)
    return {"message": "Catégorie créée", "id": new_categorie.id}


@router.post("/api/motos/catalog/import")
def import_catalogue_moto(
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Réimporte le catalogue moto externe dans la BDD (super_admin)."""
    _require_super_admin(current_user)
    catalog_result = sync_moto_catalog_to_db(db)
    specs_result = sync_moto_technical_specs_to_db(db)
    return {
        "catalog": catalog_result,
        "technical_specs": specs_result,
    }


@router.get("/api/motos/autocomplete")
def autocomplete_moto_base(
    query: Optional[str] = None,
    marque: Optional[str] = None,
    limit: int = 8,
    db: Session = Depends(get_db),
):
    """Suggestions rapides marque/modèle pour la saisie véhicule."""
    safe_limit = max(1, min(int(limit or 8), 20))
    query_value = (query or "").strip()
    marque_value = (marque or "").strip()

    normalized_query = _normalize_autocomplete_value(query_value)
    normalized_marque_value = _normalize_autocomplete_value(marque_value)
    normalized_marque = _normalized_search_expr(ModeleMoto.marque)
    normalized_modele = _normalized_search_expr(ModeleMoto.modele)

    marques_query = db.query(ModeleMoto.marque).distinct()
    if marque_value:
        marques_query = marques_query.filter(
            (ModeleMoto.marque.ilike(f"%{marque_value}%"))
            | (normalized_marque.ilike(f"%{normalized_marque_value}%"))
        )
    if query_value:
        search_filter = f"%{query_value}%"
        normalized_filter = f"%{normalized_query}%"
        marques_query = marques_query.filter(
            (ModeleMoto.marque.ilike(search_filter))
            | (ModeleMoto.modele.ilike(search_filter))
            | (normalized_marque.ilike(normalized_filter))
            | (normalized_modele.ilike(normalized_filter))
        )

    modeles_query = db.query(ModeleMoto).join(CategorieMoto)
    if marque_value:
        modeles_query = modeles_query.filter(
            (ModeleMoto.marque.ilike(f"%{marque_value}%"))
            | (normalized_marque.ilike(f"%{normalized_marque_value}%"))
        )
    if query_value:
        search_filter = f"%{query_value}%"
        normalized_filter = f"%{normalized_query}%"
        modeles_query = modeles_query.filter(
            (ModeleMoto.marque.ilike(search_filter))
            | (ModeleMoto.modele.ilike(search_filter))
            | (normalized_marque.ilike(normalized_filter))
            | (normalized_modele.ilike(normalized_filter))
        )

    marques = [
        marque_name
        for (marque_name,) in marques_query.order_by(ModeleMoto.marque).limit(safe_limit).all()
        if marque_name
    ]

    if query_value:
        model_rank = case(
            (ModeleMoto.modele.ilike(f"{query_value}-%"), 0),
            (ModeleMoto.modele.ilike(f"{query_value} %"), 1),
            (ModeleMoto.modele.ilike(f"{query_value}%"), 2),
            (normalized_modele.ilike(f"{normalized_query}%"), 3),
            else_=4,
        )
        raw_modeles = modeles_query.order_by(
            model_rank,
            ModeleMoto.marque,
            ModeleMoto.modele,
            ModeleMoto.annee_fin.desc().nulls_last(),
            ModeleMoto.annee_debut.desc().nulls_last(),
        ).limit(max(safe_limit * 15, 30)).all()
    else:
        raw_modeles = modeles_query.order_by(
            ModeleMoto.marque,
            ModeleMoto.modele,
            ModeleMoto.annee_fin.desc().nulls_last(),
            ModeleMoto.annee_debut.desc().nulls_last(),
        ).limit(max(safe_limit * 15, 30)).all()

    modeles = []
    seen_keys = set()
    for modele in raw_modeles:
        key = (
            (modele.marque or "").strip().upper(),
            _normalize_autocomplete_value(modele.modele),
        )
        if key in seen_keys:
            continue
        seen_keys.add(key)
        modeles.append(modele)
        if len(modeles) >= safe_limit:
            break

    return {
        "marques": marques,
        "modeles": [
            {
                "id": modele.id,
                "marque": modele.marque,
                "modele": modele.modele,
                "categorie_id": modele.categorie_id,
                "categorie_nom": modele.categorie.nom if modele.categorie else None,
                "cylindree_min": modele.cylindree_min,
                "cylindree_max": modele.cylindree_max,
                "cylindree_display": modele.cylindree_display,
                "annee_debut": modele.annee_debut,
                "annee_fin": modele.annee_fin,
                "annees_display": modele.annees_display,
                "type_moto": modele.categorie.nom if modele.categorie else None,
            }
            for modele in modeles
        ],
    }


@router.get("/api/motos/modeles")
def get_modeles_moto(
    categorie: Optional[int] = None,
    marque: Optional[str] = None,
    search: Optional[str] = None,
    db: Session = Depends(get_db),
):
    """Liste les modèles de moto avec filtres."""
    query = db.query(ModeleMoto).join(CategorieMoto)

    if categorie:
        query = query.filter(ModeleMoto.categorie_id == categorie)
    if marque:
        query = query.filter(ModeleMoto.marque.ilike(f"%{marque}%"))
    if search:
        search_filter = f"%{search}%"
        query = query.filter(
            (ModeleMoto.marque.ilike(search_filter))
            | (ModeleMoto.modele.ilike(search_filter))
        )

    modeles = query.order_by(ModeleMoto.marque, ModeleMoto.modele).all()
    return [
        {
            "id": modele.id,
            "marque": modele.marque,
            "modele": modele.modele,
            "categorie_id": modele.categorie_id,
            "categorie_nom": modele.categorie.nom if modele.categorie else None,
            "cylindree_min": modele.cylindree_min,
            "cylindree_max": modele.cylindree_max,
            "cylindree_display": modele.cylindree_display,
            "annee_debut": modele.annee_debut,
            "annee_fin": modele.annee_fin,
            "annees_display": modele.annees_display,
        }
        for modele in modeles
    ]


@router.get("/api/motos/modeles/{modele_id}")
def get_modele_moto_detail(modele_id: int, db: Session = Depends(get_db)):
    """Détail d'un modèle de moto."""
    modele = db.query(ModeleMoto).filter(ModeleMoto.id == modele_id).first()
    if not modele:
        raise HTTPException(status_code=404, detail="Modèle non trouvé")

    return {
        "id": modele.id,
        "marque": modele.marque,
        "modele": modele.modele,
        "categorie_id": modele.categorie_id,
        "categorie_nom": modele.categorie.nom if modele.categorie else None,
        "cylindree_min": modele.cylindree_min,
        "cylindree_max": modele.cylindree_max,
        "cylindree_display": modele.cylindree_display,
        "annee_debut": modele.annee_debut,
        "annee_fin": modele.annee_fin,
        "annees_display": modele.annees_display,
        "created_at": modele.created_at.isoformat() if modele.created_at else None,
    }


@router.get("/api/motos/technical-specs")
def get_moto_technical_specs(
    marque: str,
    modele: str,
    annee: Optional[int] = None,
    db: Session = Depends(get_db),
):
    """Retourne la fiche technique détaillée d'une moto pour un millésime donné."""
    marque_value = (marque or "").strip()
    modele_value = (modele or "").strip()
    if not marque_value or not modele_value:
        raise HTTPException(status_code=400, detail="Les parametres marque et modele sont obligatoires")

    query = db.query(MotoTechnicalSpec).join(ModeleMoto).filter(
        ModeleMoto.marque.ilike(f"%{marque_value}%"),
        ModeleMoto.modele.ilike(f"%{modele_value}%"),
    )
    if annee:
        query = query.filter(
            MotoTechnicalSpec.annee_debut <= annee,
            ((MotoTechnicalSpec.annee_fin == None) | (MotoTechnicalSpec.annee_fin >= annee)),
        )

    spec = query.order_by(MotoTechnicalSpec.annee_debut.desc()).first()
    if not spec:
        raise HTTPException(status_code=404, detail="Fiche technique non trouvée")

    data = _serialize_technical_spec(spec)
    if annee:
        data.setdefault("general", {})["annee"] = annee
    return data


@router.get("/api/motos/modeles/{modele_id}/technical-specs")
def get_modele_moto_technical_specs(modele_id: int, annee: Optional[int] = None, db: Session = Depends(get_db)):
    """Retourne la fiche technique rattachée à un modèle moto de la base."""
    query = db.query(MotoTechnicalSpec).filter(MotoTechnicalSpec.modele_moto_id == modele_id)
    if annee:
        query = query.filter(
            MotoTechnicalSpec.annee_debut <= annee,
            ((MotoTechnicalSpec.annee_fin == None) | (MotoTechnicalSpec.annee_fin >= annee)),
        )
    spec = query.order_by(MotoTechnicalSpec.annee_debut.desc()).first()
    if not spec:
        raise HTTPException(status_code=404, detail="Fiche technique non trouvée")

    data = _serialize_technical_spec(spec)
    if annee:
        data.setdefault("general", {})["annee"] = annee
    return data


@router.post("/api/motos/modeles")
def create_modele_moto(
    modele: ModeleMotoCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Crée un nouveau modèle de moto (super_admin)."""
    _require_super_admin(current_user)
    categorie = db.query(CategorieMoto).filter(CategorieMoto.id == modele.categorie_id).first()
    if not categorie:
        raise HTTPException(status_code=404, detail="Catégorie non trouvée")

    existing = db.query(ModeleMoto).filter(
        ModeleMoto.marque == modele.marque,
        ModeleMoto.modele == modele.modele,
        ModeleMoto.categorie_id == modele.categorie_id,
        ModeleMoto.cylindree_min == modele.cylindree_min,
        ModeleMoto.cylindree_max == modele.cylindree_max,
        ModeleMoto.annee_debut == modele.annee_debut,
        ModeleMoto.annee_fin == modele.annee_fin,
    ).first()
    if existing:
        raise HTTPException(status_code=400, detail="Ce modèle existe déjà avec cette cylindrée et cette plage d'années")

    new_modele = ModeleMoto(**modele.model_dump())
    db.add(new_modele)
    db.commit()
    db.refresh(new_modele)
    return {"message": "Modèle créé", "id": new_modele.id}


@router.put("/api/motos/modeles/{modele_id}")
def update_modele_moto(
    modele_id: int,
    modele_data: ModeleMotoUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Met à jour un modèle de moto (super_admin)."""
    _require_super_admin(current_user)
    modele = db.query(ModeleMoto).filter(ModeleMoto.id == modele_id).first()
    if not modele:
        raise HTTPException(status_code=404, detail="Modèle non trouvé")

    if modele_data.categorie_id:
        categorie = db.query(CategorieMoto).filter(CategorieMoto.id == modele_data.categorie_id).first()
        if not categorie:
            raise HTTPException(status_code=404, detail="Catégorie non trouvée")

    for field, value in modele_data.model_dump(exclude_unset=True).items():
        setattr(modele, field, value)

    db.commit()
    db.refresh(modele)
    return {"message": "Modèle mis à jour", "id": modele.id}


@router.delete("/api/motos/modeles/{modele_id}")
def delete_modele_moto(
    modele_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Supprime un modèle de moto (super_admin)."""
    _require_super_admin(current_user)
    modele = db.query(ModeleMoto).filter(ModeleMoto.id == modele_id).first()
    if not modele:
        raise HTTPException(status_code=404, detail="Modèle non trouvé")

    db.delete(modele)
    db.commit()
    return {"message": "Modèle supprimé"}


@router.get("/api/motos/marques")
def get_marques_moto(db: Session = Depends(get_db)):
    """Liste toutes les marques distinctes."""
    marques = db.query(ModeleMoto.marque).distinct().order_by(ModeleMoto.marque).all()
    return [marque for (marque,) in marques if marque]


@router.get("/api/motos/stats")
def get_stats_moto(db: Session = Depends(get_db)):
    """Statistiques sur la base moto."""
    from sqlalchemy import func

    total_modeles = db.query(ModeleMoto).count()
    total_categories = db.query(CategorieMoto).count()
    total_fiches_techniques = db.query(MotoTechnicalSpec).count()
    modeles_par_marque = db.query(
        ModeleMoto.marque,
        func.count(ModeleMoto.id).label("count"),
    ).group_by(ModeleMoto.marque).order_by(func.count(ModeleMoto.id).desc()).all()
    modeles_par_categorie = db.query(
        CategorieMoto.nom,
        func.count(ModeleMoto.id).label("count"),
    ).join(ModeleMoto).group_by(CategorieMoto.nom).order_by(func.count(ModeleMoto.id).desc()).all()

    return {
        "total_modeles": total_modeles,
        "total_categories": total_categories,
        "total_fiches_techniques": total_fiches_techniques,
        "modeles_par_marque": [{"marque": marque, "count": count} for marque, count in modeles_par_marque],
        "modeles_par_categorie": [{"categorie": categorie, "count": count} for categorie, count in modeles_par_categorie],
    }
