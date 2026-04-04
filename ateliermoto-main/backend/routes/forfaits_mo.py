from typing import Optional

from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session

from auth import get_current_user
from models import ConfigAtelier, ForfaitMO, User, get_db
from schemas.forfaits_mo import ForfaitMOCreate, ForfaitMOUpdate

router = APIRouter(tags=["forfaits-mo"])


@router.get("/api/forfaits-mo")
def get_forfaits_mo(
    categorie: Optional[str] = None,
    type_vehicule: Optional[str] = None,
    actif_only: bool = True,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Liste tous les forfaits MO."""
    query = db.query(ForfaitMO)

    if actif_only:
        query = query.filter(ForfaitMO.is_active == 1)
    if categorie:
        query = query.filter(ForfaitMO.categorie == categorie)
    if type_vehicule and type_vehicule != "tous":
        query = query.filter(
            (ForfaitMO.type_vehicule == type_vehicule) | (ForfaitMO.type_vehicule == "tous")
        )

    forfaits = query.order_by(ForfaitMO.categorie, ForfaitMO.nom).all()
    return [
        {
            "id": forfait.id,
            "code": forfait.code,
            "nom": forfait.nom,
            "description": forfait.description,
            "categorie": forfait.categorie,
            "temps_base_minutes": forfait.temps_base_minutes,
            "temps_formate": f"{forfait.temps_base_minutes // 60}h{forfait.temps_base_minutes % 60:02d}",
            "taux_horaire_applique": forfait.taux_horaire_applique,
            "prix_forfait_mo_ht": forfait.prix_forfait_mo_ht,
            "prix_forfait_mo_ttc": forfait.prix_forfait_mo_ttc,
            "prix_affichage": forfait.prix_promo_mo_ttc if forfait.is_promo and forfait.prix_promo_mo_ttc else forfait.prix_forfait_mo_ttc,
            "inclut_pieces": forfait.inclut_pieces,
            "description_pieces_incluses": forfait.description_pieces_incluses,
            "prix_pieces_incluses_ht": forfait.prix_pieces_incluses_ht,
            "type_vehicule": forfait.type_vehicule,
            "cylindree_min": forfait.cylindree_min,
            "cylindree_max": forfait.cylindree_max,
            "is_active": forfait.is_active,
            "is_promo": forfait.is_promo,
            "prix_promo_mo_ttc": forfait.prix_promo_mo_ttc,
        }
        for forfait in forfaits
    ]


@router.get("/api/forfaits-mo/{forfait_id}")
def get_forfait_mo(
    forfait_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Détail d'un forfait MO."""
    forfait = db.query(ForfaitMO).filter(ForfaitMO.id == forfait_id).first()
    if not forfait:
        raise HTTPException(status_code=404, detail="Forfait non trouvé")

    return {
        "id": forfait.id,
        "code": forfait.code,
        "nom": forfait.nom,
        "description": forfait.description,
        "categorie": forfait.categorie,
        "temps_base_minutes": forfait.temps_base_minutes,
        "taux_horaire_applique": forfait.taux_horaire_applique,
        "prix_forfait_mo_ht": forfait.prix_forfait_mo_ht,
        "prix_forfait_mo_ttc": forfait.prix_forfait_mo_ttc,
        "inclut_pieces": forfait.inclut_pieces,
        "description_pieces_incluses": forfait.description_pieces_incluses,
        "prix_pieces_incluses_ht": forfait.prix_pieces_incluses_ht,
        "type_vehicule": forfait.type_vehicule,
        "cylindree_min": forfait.cylindree_min,
        "cylindree_max": forfait.cylindree_max,
        "is_active": forfait.is_active,
        "is_promo": forfait.is_promo,
        "prix_promo_mo_ttc": forfait.prix_promo_mo_ttc,
        "created_at": forfait.created_at.isoformat() if forfait.created_at else None,
    }


@router.post("/api/forfaits-mo")
def create_forfait_mo(
    forfait_data: ForfaitMOCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Crée un nouveau forfait MO."""
    existing = db.query(ForfaitMO).filter(ForfaitMO.code == forfait_data.code).first()
    if existing:
        raise HTTPException(status_code=400, detail="Ce code forfait existe déjà")

    forfait = ForfaitMO(**forfait_data.dict())
    db.add(forfait)
    db.commit()
    db.refresh(forfait)
    return {"message": "Forfait créé", "id": forfait.id}


@router.put("/api/forfaits-mo/{forfait_id}")
def update_forfait_mo(
    forfait_id: int,
    forfait_data: ForfaitMOUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Met à jour un forfait MO."""
    forfait = db.query(ForfaitMO).filter(ForfaitMO.id == forfait_id).first()
    if not forfait:
        raise HTTPException(status_code=404, detail="Forfait non trouvé")

    if forfait_data.code and forfait_data.code != forfait.code:
        existing = db.query(ForfaitMO).filter(ForfaitMO.code == forfait_data.code).first()
        if existing:
            raise HTTPException(status_code=400, detail="Ce code forfait existe déjà")

    for field, value in forfait_data.dict(exclude_unset=True).items():
        setattr(forfait, field, value)

    db.commit()
    db.refresh(forfait)
    return {"message": "Forfait mis à jour", "forfait": get_forfait_mo(forfait_id, db, current_user)}


@router.delete("/api/forfaits-mo/{forfait_id}")
def delete_forfait_mo(
    forfait_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Désactive un forfait MO."""
    forfait = db.query(ForfaitMO).filter(ForfaitMO.id == forfait_id).first()
    if not forfait:
        raise HTTPException(status_code=404, detail="Forfait non trouvé")

    forfait.is_active = 0
    db.commit()
    return {"message": "Forfait désactivé"}


@router.get("/api/forfaits-mo/categories/list")
def get_categories_forfaits(
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Liste les catégories de forfaits disponibles."""
    categories = db.query(ForfaitMO.categorie).distinct().filter(
        ForfaitMO.is_active == 1,
        ForfaitMO.categorie.isnot(None),
    ).all()
    return [categorie for (categorie,) in categories if categorie]


@router.post("/api/forfaits-mo/calcul-prix")
def calculer_prix_forfait(
    temps_minutes: int,
    taux_horaire: str = "standard",
    db: Session = Depends(get_db),
):
    """Calcule le prix d'un forfait selon le temps et le taux horaire."""
    config = db.query(ConfigAtelier).first()
    if not config:
        taux = {"standard": 65.0, "complexe": 85.0, "expert": 95.0}
    else:
        taux = {
            "standard": config.taux_horaire_mo_standard,
            "complexe": config.taux_horaire_mo_complexe,
            "expert": config.taux_horaire_mo_expert,
        }

    taux_applique = taux.get(taux_horaire, taux["standard"])
    prix_ht = (temps_minutes / 60) * taux_applique
    if config and prix_ht < config.forfait_mo_minimum:
        prix_ht = config.forfait_mo_minimum
    elif not config and prix_ht < 25.0:
        prix_ht = 25.0

    tva_taux = config.tva_mo_taux if config else 20.0
    prix_ttc = prix_ht * (1 + tva_taux / 100)
    return {
        "temps_minutes": temps_minutes,
        "taux_horaire": taux_applique,
        "prix_ht": round(prix_ht, 2),
        "tva_taux": tva_taux,
        "prix_ttc": round(prix_ttc, 2),
    }
