from typing import List, Optional

from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session

from auth import get_current_user
from models import Atelier, AtelierCategorieMoto, CategorieMoto, ModeleMoto, User, get_db
from schemas.moto_base import (
    CategorieMotoCreate,
    CategorieMotoResponse,
    ModeleMotoCreate,
    ModeleMotoUpdate,
)

router = APIRouter(tags=["moto-base"])


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
    """Crée une nouvelle catégorie de moto (admin)."""
    existing = db.query(CategorieMoto).filter(CategorieMoto.nom == categorie.nom).first()
    if existing:
        raise HTTPException(status_code=400, detail="Cette catégorie existe déjà")

    new_categorie = CategorieMoto(**categorie.dict())
    db.add(new_categorie)
    db.commit()
    db.refresh(new_categorie)
    return {"message": "Catégorie créée", "id": new_categorie.id}


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


@router.post("/api/motos/modeles")
def create_modele_moto(
    modele: ModeleMotoCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Crée un nouveau modèle de moto (admin)."""
    categorie = db.query(CategorieMoto).filter(CategorieMoto.id == modele.categorie_id).first()
    if not categorie:
        raise HTTPException(status_code=404, detail="Catégorie non trouvée")

    existing = db.query(ModeleMoto).filter(
        ModeleMoto.marque == modele.marque,
        ModeleMoto.modele == modele.modele,
        ModeleMoto.categorie_id == modele.categorie_id,
    ).first()
    if existing:
        raise HTTPException(status_code=400, detail="Ce modèle existe déjà dans cette catégorie")

    new_modele = ModeleMoto(**modele.dict())
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
    """Met à jour un modèle de moto (admin)."""
    modele = db.query(ModeleMoto).filter(ModeleMoto.id == modele_id).first()
    if not modele:
        raise HTTPException(status_code=404, detail="Modèle non trouvé")

    if modele_data.categorie_id:
        categorie = db.query(CategorieMoto).filter(CategorieMoto.id == modele_data.categorie_id).first()
        if not categorie:
            raise HTTPException(status_code=404, detail="Catégorie non trouvée")

    for field, value in modele_data.dict(exclude_unset=True).items():
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
    """Supprime un modèle de moto (admin)."""
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
        "modeles_par_marque": [{"marque": marque, "count": count} for marque, count in modeles_par_marque],
        "modeles_par_categorie": [{"categorie": categorie, "count": count} for categorie, count in modeles_par_categorie],
    }
