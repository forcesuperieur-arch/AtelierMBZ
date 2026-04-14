from typing import List, Optional

from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from sqlalchemy import func
from sqlalchemy.orm import Session

from auth import get_current_user
from models import (
    Atelier,
    CategorieMoto,
    ConfigAtelier,
    GrilleTarifaire,
    PieceDetachee,
    Prestation,
    User,
    get_db,
)
from routes.public_booking import get_delais_intervention_handler
from services.pricing_rules import (
    PricingConfigError,
    mode_to_legacy,
    normalize_mode_tarification,
    normalize_taux_profile,
)

router = APIRouter(tags=["prestations-tarifs"])


def _resolve_target_atelier_id_for_config(
    db: Session,
    current_user: User,
    atelier_id: Optional[int] = None,
) -> int:
    if current_user.role == "super_admin" and atelier_id is not None:
        target = db.query(Atelier.id).filter(Atelier.id == atelier_id).first()
        if not target:
            raise HTTPException(status_code=404, detail="Atelier non trouvé")
        return int(atelier_id)
    return int(getattr(current_user, "atelier_id", None) or 1)


class PrestationCreate(BaseModel):
    code: str
    nom: str
    description: Optional[str] = None
    categorie: str = "entretien"
    sous_categorie: Optional[str] = None
    prix_base_ht: float = 0.0
    prix_base_ttc: float = 0.0
    temps_estime_minutes: int = 30
    delai_intervention_jours: int = 1
    type_tarif: str = "forfait"
    taux_horaire_applique: str = "standard"
    type_vehicule: str = "tous"
    cylindree_min: Optional[int] = None
    cylindree_max: Optional[int] = None
    is_forfait: int = 0
    inclut_pieces: int = 0
    description_pieces_incluses: Optional[str] = None
    cout_pieces_incluses_ht: float = 0.0
    marge_pieces_pourcent: float = 30.0


class PrestationUpdate(BaseModel):
    code: Optional[str] = None
    nom: Optional[str] = None
    description: Optional[str] = None
    categorie: Optional[str] = None
    sous_categorie: Optional[str] = None
    prix_base_ht: Optional[float] = None
    prix_base_ttc: Optional[float] = None
    temps_estime_minutes: Optional[int] = None
    delai_intervention_jours: Optional[int] = None
    type_tarif: Optional[str] = None
    taux_horaire_applique: Optional[str] = None
    type_vehicule: Optional[str] = None
    cylindree_min: Optional[int] = None
    cylindree_max: Optional[int] = None
    is_active: Optional[int] = None
    is_forfait: Optional[int] = None
    is_promo: Optional[int] = None
    prix_promo_ttc: Optional[float] = None
    inclut_pieces: Optional[int] = None
    description_pieces_incluses: Optional[str] = None
    cout_pieces_incluses_ht: Optional[float] = None
    marge_pieces_pourcent: Optional[float] = None


class GrilleTarifaireCreate(BaseModel):
    prestation_id: int
    type_vehicule: str = "tous"
    cylindree_min: Optional[int] = None
    cylindree_max: Optional[int] = None
    prix_ht: float
    prix_ttc: float
    temps_minutes: int
    delai_jours: int = 1


class CalculDetailleRequest(BaseModel):
    prestations: List[dict]  # [{"prestation_id": 1, "quantite": 1}]
    pieces: List[dict]  # [{"piece_id": 1, "quantite": 2, "prix_achat_ht": 50.0}]
    marge_pieces_pourcent: Optional[float] = 30.0
    remise_pourcent: Optional[float] = 0.0


class GrilleEntry(BaseModel):
    categorie_moto_id: int
    prix_ttc: float
    prix_ht: float
    temps_minutes: int
    is_active: int = 1


class GrilleBulkUpdate(BaseModel):
    entries: List[GrilleEntry]


def _validate_and_normalize_prestation_payload(payload: dict, *, partial: bool = False) -> dict:
    if partial and "type_tarif" not in payload:
        if "taux_horaire_applique" in payload:
            payload["taux_horaire_applique"] = normalize_taux_profile(payload.get("taux_horaire_applique"))
        return payload

    mode = normalize_mode_tarification(payload.get("type_tarif"))
    payload["type_tarif"] = mode_to_legacy(mode)

    if mode == "taux_horaire":
        payload["taux_horaire_applique"] = normalize_taux_profile(payload.get("taux_horaire_applique"))
    elif mode == "sur_devis":
        payload["prix_base_ht"] = 0.0
        payload["prix_base_ttc"] = 0.0

    return payload


@router.get("/api/interventions")
def get_interventions(
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Backward-compat: retourne les prestations actives du tenant au format InterventionType."""
    atelier_id = int(getattr(current_user, "atelier_id", None) or 1)
    prestations = db.query(Prestation).filter(
        Prestation.is_active == 1,
        Prestation.atelier_id == atelier_id,
    ).order_by(Prestation.categorie, Prestation.nom).all()
    return [
        {
            "id": prestation.id,
            "nom": prestation.nom,
            "description": prestation.description,
            "prix_base": prestation.prix_base_ttc or 0,
            "temps_estime": prestation.temps_estime_minutes or 30,
        }
        for prestation in prestations
    ]


@router.get("/api/config/prestations")
def get_config_prestations(
    atelier_id: Optional[int] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Liste toutes les prestations avec grille complète (admin)."""
    target_atelier_id = _resolve_target_atelier_id_for_config(db, current_user, atelier_id)
    prestations = db.query(Prestation).filter(
        Prestation.atelier_id == target_atelier_id,
    ).order_by(Prestation.categorie, Prestation.nom).all()

    grilles = db.query(GrilleTarifaire, CategorieMoto.nom).outerjoin(
        CategorieMoto, GrilleTarifaire.categorie_moto_id == CategorieMoto.id,
    ).filter(
        GrilleTarifaire.categorie_moto_id.isnot(None),
        GrilleTarifaire.atelier_id == target_atelier_id,
    ).all()

    grilles_par_presta = {}
    for grille, cat_nom in grilles:
        grilles_par_presta.setdefault(grille.prestation_id, {})[cat_nom] = {
            "id": grille.id,
            "categorie_moto_id": grille.categorie_moto_id,
            "prix_ht": grille.prix_ht,
            "prix_ttc": grille.prix_ttc,
            "temps_minutes": grille.temps_minutes,
            "is_active": int(grille.is_active or 0),
        }

    return [
        {
            "id": prestation.id,
            "code": prestation.code,
            "nom": prestation.nom,
            "description": prestation.description,
            "categorie": prestation.categorie,
            "sous_categorie": prestation.sous_categorie,
            "prix_base_ht": prestation.prix_base_ht,
            "prix_base_ttc": prestation.prix_base_ttc,
            "temps_estime_minutes": prestation.temps_estime_minutes,
            "type_tarif": prestation.type_tarif,
            "mode_tarification": normalize_mode_tarification(prestation.type_tarif),
            "taux_horaire_applique": prestation.taux_horaire_applique,
            "is_active": prestation.is_active,
            "is_forfait": prestation.is_forfait,
            "is_promo": prestation.is_promo,
            "prix_promo_ttc": prestation.prix_promo_ttc,
            "grille": grilles_par_presta.get(prestation.id, {}),
        }
        for prestation in prestations
    ]


@router.post("/api/config/prestations")
def create_config_prestation(
    data: PrestationCreate,
    atelier_id: Optional[int] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Crée une nouvelle prestation (admin)."""
    target_atelier_id = _resolve_target_atelier_id_for_config(db, current_user, atelier_id)
    existing = db.query(Prestation).filter(Prestation.code == data.code).first()
    if existing:
        raise HTTPException(status_code=400, detail="Ce code prestation existe déjà")

    try:
        payload = _validate_and_normalize_prestation_payload(data.model_dump())
    except PricingConfigError as exc:
        raise HTTPException(status_code=400, detail=str(exc))
    new_presta = Prestation(**payload, atelier_id=target_atelier_id)
    db.add(new_presta)
    db.commit()
    db.refresh(new_presta)
    return {"id": new_presta.id, "message": "Prestation créée"}


@router.put("/api/config/prestations/{prestation_id}")
def update_config_prestation(
    prestation_id: int,
    data: PrestationUpdate,
    atelier_id: Optional[int] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Modifie une prestation (admin)."""
    target_atelier_id = _resolve_target_atelier_id_for_config(db, current_user, atelier_id)
    prestation = db.query(Prestation).filter(
        Prestation.id == prestation_id,
        Prestation.atelier_id == target_atelier_id,
    ).first()
    if not prestation:
        raise HTTPException(status_code=404, detail="Prestation non trouvée")

    try:
        updates = _validate_and_normalize_prestation_payload(data.model_dump(exclude_unset=True), partial=True)
    except PricingConfigError as exc:
        raise HTTPException(status_code=400, detail=str(exc))
    for field, value in updates.items():
        setattr(prestation, field, value)
    db.commit()
    return {"message": "Prestation modifiée"}


@router.delete("/api/config/prestations/{prestation_id}")
def delete_config_prestation(
    prestation_id: int,
    atelier_id: Optional[int] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Désactive une prestation (admin)."""
    target_atelier_id = _resolve_target_atelier_id_for_config(db, current_user, atelier_id)
    prestation = db.query(Prestation).filter(
        Prestation.id == prestation_id,
        Prestation.atelier_id == target_atelier_id,
    ).first()
    if not prestation:
        raise HTTPException(status_code=404, detail="Prestation non trouvée")

    prestation.is_active = 0
    db.commit()
    return {"message": "Prestation désactivée"}


@router.put("/api/config/prestations/{prestation_id}/grille")
def update_grille_prestation(
    prestation_id: int,
    data: GrilleBulkUpdate,
    atelier_id: Optional[int] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Sauve la grille prix par type moto pour une prestation (bulk upsert)."""
    target_atelier_id = _resolve_target_atelier_id_for_config(db, current_user, atelier_id)
    prestation = db.query(Prestation).filter(
        Prestation.id == prestation_id,
        Prestation.atelier_id == target_atelier_id,
    ).first()
    if not prestation:
        raise HTTPException(status_code=404, detail="Prestation non trouvée")

    requested_category_ids = {int(entry.categorie_moto_id) for entry in data.entries}
    valid_category_ids = {
        int(row[0])
        for row in db.query(CategorieMoto.id).filter(CategorieMoto.id.in_(requested_category_ids)).all()
    }
    invalid_category_ids = sorted(requested_category_ids - valid_category_ids)
    if invalid_category_ids:
        missing = ", ".join(str(cat_id) for cat_id in invalid_category_ids)
        raise HTTPException(status_code=400, detail=f"La catégorie n'existe pas: {missing}")

    for entry in data.entries:
        existing = db.query(GrilleTarifaire).filter(
            GrilleTarifaire.prestation_id == prestation_id,
            GrilleTarifaire.categorie_moto_id == entry.categorie_moto_id,
            GrilleTarifaire.atelier_id == target_atelier_id,
        ).first()

        if existing:
            existing.prix_ttc = entry.prix_ttc
            existing.prix_ht = entry.prix_ht
            existing.temps_minutes = entry.temps_minutes
            existing.is_active = 1 if int(entry.is_active or 0) == 1 else 0
        else:
            db.add(
                GrilleTarifaire(
                    atelier_id=target_atelier_id,
                    prestation_id=prestation_id,
                    categorie_moto_id=entry.categorie_moto_id,
                    prix_ht=entry.prix_ht,
                    prix_ttc=entry.prix_ttc,
                    temps_minutes=entry.temps_minutes,
                    delai_jours=prestation.delai_intervention_jours or 1,
                    is_active=1 if int(entry.is_active or 0) == 1 else 0,
                )
            )

    db.commit()
    return {"message": "Grille tarifaire mise à jour"}


@router.get("/api/config/taux-mo")
def get_taux_mo(db: Session = Depends(get_db)):
    """Récupère les taux horaires MO (public pour calculs frontend)."""
    config = db.query(ConfigAtelier).first()
    if not config:
        return {
            "standard": 65.0,
            "complexe": 85.0,
            "expert": 95.0,
            "minimum": 25.0,
        }

    return {
        "standard": config.taux_horaire_mo_standard,
        "complexe": config.taux_horaire_mo_complexe,
        "expert": config.taux_horaire_mo_expert,
        "minimum": config.forfait_mo_minimum,
    }


@router.get("/api/prestations")
def get_prestations(
    categorie: Optional[str] = None,
    type_vehicule: Optional[str] = None,
    actif_only: bool = True,
    search: Optional[str] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Liste toutes les prestations avec filtres."""
    query = db.query(Prestation)

    if actif_only:
        query = query.filter(Prestation.is_active == 1)
    if categorie:
        query = query.filter(Prestation.categorie == categorie)
    if type_vehicule and type_vehicule != "tous":
        query = query.filter(
            (Prestation.type_vehicule == type_vehicule) | (Prestation.type_vehicule == "tous")
        )
    if search:
        search_filter = f"%{search}%"
        query = query.filter(
            (Prestation.nom.ilike(search_filter))
            | (Prestation.code.ilike(search_filter))
            | (Prestation.description.ilike(search_filter))
        )

    prestations = query.order_by(Prestation.categorie, Prestation.nom).all()
    return [
        {
            "id": prestation.id,
            "code": prestation.code,
            "nom": prestation.nom,
            "description": prestation.description,
            "categorie": prestation.categorie,
            "sous_categorie": prestation.sous_categorie,
            "prix_base_ht": prestation.prix_base_ht,
            "prix_base_ttc": prestation.prix_base_ttc,
            "prix_affichage": prestation.prix_promo_ttc if prestation.is_promo and prestation.prix_promo_ttc else prestation.prix_base_ttc,
            "temps_estime_minutes": prestation.temps_estime_minutes,
            "temps_formate": f"{prestation.temps_estime_minutes // 60}h{prestation.temps_estime_minutes % 60:02d}",
            "delai_intervention_jours": prestation.delai_intervention_jours,
            "type_tarif": prestation.type_tarif,
            "mode_tarification": normalize_mode_tarification(prestation.type_tarif),
            "taux_horaire_applique": prestation.taux_horaire_applique,
            "type_vehicule": prestation.type_vehicule,
            "cylindree_min": prestation.cylindree_min,
            "cylindree_max": prestation.cylindree_max,
            "is_active": prestation.is_active,
            "is_forfait": prestation.is_forfait,
            "is_promo": prestation.is_promo,
            "prix_promo_ttc": prestation.prix_promo_ttc,
            "inclut_pieces": prestation.inclut_pieces,
            "description_pieces_incluses": prestation.description_pieces_incluses,
            "cout_pieces_incluses_ht": prestation.cout_pieces_incluses_ht,
            "marge_pieces_pourcent": prestation.marge_pieces_pourcent,
        }
        for prestation in prestations
    ]


@router.get("/api/prestations/{prestation_id}")
def get_prestation(
    prestation_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Détail d'une prestation."""
    prestation = db.query(Prestation).filter(Prestation.id == prestation_id).first()
    if not prestation:
        raise HTTPException(status_code=404, detail="Prestation non trouvée")

    grilles = db.query(GrilleTarifaire).filter(
        GrilleTarifaire.prestation_id == prestation_id,
        GrilleTarifaire.is_active == 1,
    ).all()

    return {
        "id": prestation.id,
        "code": prestation.code,
        "nom": prestation.nom,
        "description": prestation.description,
        "categorie": prestation.categorie,
        "sous_categorie": prestation.sous_categorie,
        "prix_base_ht": prestation.prix_base_ht,
        "prix_base_ttc": prestation.prix_base_ttc,
        "temps_estime_minutes": prestation.temps_estime_minutes,
        "delai_intervention_jours": prestation.delai_intervention_jours,
        "type_tarif": prestation.type_tarif,
        "mode_tarification": normalize_mode_tarification(prestation.type_tarif),
        "taux_horaire_applique": prestation.taux_horaire_applique,
        "type_vehicule": prestation.type_vehicule,
        "cylindree_min": prestation.cylindree_min,
        "cylindree_max": prestation.cylindree_max,
        "is_active": prestation.is_active,
        "is_forfait": prestation.is_forfait,
        "is_promo": prestation.is_promo,
        "prix_promo_ttc": prestation.prix_promo_ttc,
        "inclut_pieces": prestation.inclut_pieces,
        "description_pieces_incluses": prestation.description_pieces_incluses,
        "cout_pieces_incluses_ht": prestation.cout_pieces_incluses_ht,
        "marge_pieces_pourcent": prestation.marge_pieces_pourcent,
        "created_at": prestation.created_at.isoformat() if prestation.created_at else None,
        "grilles_tarifaires": [
            {
                "id": grille.id,
                "type_vehicule": grille.type_vehicule,
                "cylindree_min": grille.cylindree_min,
                "cylindree_max": grille.cylindree_max,
                "prix_ht": grille.prix_ht,
                "prix_ttc": grille.prix_ttc,
                "temps_minutes": grille.temps_minutes,
                "delai_jours": grille.delai_jours,
            }
            for grille in grilles
        ],
    }


@router.post("/api/prestations")
def create_prestation(
    prestation_data: PrestationCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Crée une nouvelle prestation."""
    existing = db.query(Prestation).filter(Prestation.code == prestation_data.code).first()
    if existing:
        raise HTTPException(status_code=400, detail="Ce code prestation existe déjà")

    try:
        payload = _validate_and_normalize_prestation_payload(prestation_data.model_dump())
    except PricingConfigError as exc:
        raise HTTPException(status_code=400, detail=str(exc))
    if hasattr(Prestation, "atelier_id"):
        payload.setdefault("atelier_id", int(getattr(current_user, "atelier_id", None) or 1))

    prestation = Prestation(**payload)
    db.add(prestation)
    db.commit()
    db.refresh(prestation)
    return {"message": "Prestation créée", "id": prestation.id}


@router.put("/api/prestations/{prestation_id}")
def update_prestation(
    prestation_id: int,
    prestation_data: PrestationUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Met à jour une prestation."""
    query = db.query(Prestation).filter(Prestation.id == prestation_id)
    if current_user.role != "super_admin":
        query = query.filter(Prestation.atelier_id == int(getattr(current_user, "atelier_id", None) or 1))
    prestation = query.first()
    if not prestation:
        raise HTTPException(status_code=404, detail="Prestation non trouvée")

    if prestation_data.code and prestation_data.code != prestation.code:
        existing = db.query(Prestation).filter(Prestation.code == prestation_data.code).first()
        if existing:
            raise HTTPException(status_code=400, detail="Ce code prestation existe déjà")

    try:
        updates = _validate_and_normalize_prestation_payload(prestation_data.model_dump(exclude_unset=True), partial=True)
    except PricingConfigError as exc:
        raise HTTPException(status_code=400, detail=str(exc))
    for field, value in updates.items():
        setattr(prestation, field, value)

    db.commit()
    db.refresh(prestation)
    return {"message": "Prestation mise à jour", "prestation": get_prestation(prestation_id, db, current_user)}


@router.delete("/api/prestations/{prestation_id}")
def delete_prestation(
    prestation_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Désactive une prestation."""
    query = db.query(Prestation).filter(Prestation.id == prestation_id)
    if current_user.role != "super_admin":
        query = query.filter(Prestation.atelier_id == int(getattr(current_user, "atelier_id", None) or 1))
    prestation = query.first()
    if not prestation:
        raise HTTPException(status_code=404, detail="Prestation non trouvée")

    prestation.is_active = 0
    db.commit()
    return {"message": "Prestation désactivée"}


@router.get("/api/prestations/categories/list")
def get_categories_prestations(
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Liste les catégories de prestations disponibles."""
    categories = db.query(Prestation.categorie).distinct().filter(
        Prestation.is_active == 1,
        Prestation.categorie.isnot(None),
    ).all()
    return [category for (category,) in categories if category]


@router.get("/api/grilles-tarifaires")
def get_grilles_tarifaires(
    prestation_id: Optional[int] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Liste les grilles tarifaires."""
    query = db.query(GrilleTarifaire).filter(GrilleTarifaire.is_active == 1)
    if prestation_id:
        query = query.filter(GrilleTarifaire.prestation_id == prestation_id)

    grilles = query.all()
    return [
        {
            "id": grille.id,
            "prestation_id": grille.prestation_id,
            "prestation_nom": grille.prestation.nom if grille.prestation else None,
            "type_vehicule": grille.type_vehicule,
            "cylindree_min": grille.cylindree_min,
            "cylindree_max": grille.cylindree_max,
            "prix_ht": grille.prix_ht,
            "prix_ttc": grille.prix_ttc,
            "temps_minutes": grille.temps_minutes,
            "delai_jours": grille.delai_jours,
        }
        for grille in grilles
    ]


@router.post("/api/grilles-tarifaires")
def create_grille_tarifaire(
    grille_data: GrilleTarifaireCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Crée une nouvelle grille tarifaire."""
    prestation = db.query(Prestation).filter(Prestation.id == grille_data.prestation_id).first()
    if not prestation:
        raise HTTPException(status_code=404, detail="Prestation non trouvée")

    grille = GrilleTarifaire(**grille_data.model_dump())
    db.add(grille)
    db.commit()
    db.refresh(grille)
    return {"message": "Grille tarifaire créée", "id": grille.id}


@router.delete("/api/grilles-tarifaires/{grille_id}")
def delete_grille_tarifaire(
    grille_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Désactive une grille tarifaire."""
    query = db.query(GrilleTarifaire).filter(GrilleTarifaire.id == grille_id)
    if current_user.role != "super_admin":
        query = query.filter(GrilleTarifaire.atelier_id == int(getattr(current_user, "atelier_id", None) or 1))
    grille = query.first()
    if not grille:
        raise HTTPException(status_code=404, detail="Grille tarifaire non trouvée")

    grille.is_active = 0
    db.commit()
    return {"message": "Grille tarifaire désactivée"}


@router.post("/api/tarifs/calcul-detaille")
def calculer_tarif_detaille(
    calcul_data: CalculDetailleRequest,
    db: Session = Depends(get_db),
):
    """Calcule détaillé: MO + pièces + marge."""
    config = db.query(ConfigAtelier).first()
    tva_mo = config.tva_mo_taux if config else 20.0

    total_mo_ht = 0.0
    details_mo = []
    for prestation_item in calcul_data.prestations:
        prestation = db.query(Prestation).filter(Prestation.id == prestation_item["prestation_id"]).first()
        if not prestation:
            continue

        quantite = prestation_item.get("quantite", 1)
        ligne_ht = float(prestation.prix_base_ht or 0) * quantite
        ligne_ttc = float(prestation.prix_base_ttc or 0) * quantite
        total_mo_ht += ligne_ht
        details_mo.append(
            {
                "prestation_id": prestation.id,
                "nom": prestation.nom,
                "quantite": quantite,
                "prix_unitaire_ht": prestation.prix_base_ht,
                "prix_unitaire_ttc": prestation.prix_base_ttc,
                "total_ligne_ht": ligne_ht,
                "total_ligne_ttc": ligne_ttc,
            }
        )

    total_pieces_ht = 0.0
    total_pieces_achat = 0.0
    details_pieces = []
    marge_pourcent = calcul_data.marge_pieces_pourcent or 0.0
    for piece_item in calcul_data.pieces:
        piece = db.query(PieceDetachee).filter(PieceDetachee.id == piece_item["piece_id"]).first()
        if not piece:
            continue

        quantite = piece_item.get("quantite", 1)
        prix_achat = float(piece_item.get("prix_achat_ht", piece.prix_achat_ht or 0))
        prix_vente_unitaire = prix_achat * (1 + marge_pourcent / 100)
        ligne_ht = prix_vente_unitaire * quantite

        total_pieces_achat += prix_achat * quantite
        total_pieces_ht += ligne_ht
        details_pieces.append(
            {
                "piece_id": piece.id,
                "reference": piece.reference,
                "nom": piece.nom,
                "quantite": quantite,
                "prix_achat_unitaire": prix_achat,
                "marge_pourcent": marge_pourcent,
                "prix_vente_unitaire_ht": round(prix_vente_unitaire, 2),
                "total_ligne_ht": round(ligne_ht, 2),
            }
        )

    total_ht = total_mo_ht + total_pieces_ht
    marge_pieces = total_pieces_ht - total_pieces_achat
    remise_montant = total_ht * ((calcul_data.remise_pourcent or 0.0) / 100)
    total_ht_remise = total_ht - remise_montant
    total_ttc = total_ht_remise * (1 + (tva_mo / 100))

    return {
        "main_oeuvre": {"total_ht": round(total_mo_ht, 2), "details": details_mo},
        "pieces": {
            "total_achat_ht": round(total_pieces_achat, 2),
            "total_vente_ht": round(total_pieces_ht, 2),
            "marge_ht": round(marge_pieces, 2),
            "marge_pourcent": marge_pourcent,
            "details": details_pieces,
        },
        "totaux": {
            "total_ht": round(total_ht, 2),
            "remise_pourcent": calcul_data.remise_pourcent,
            "remise_montant": round(remise_montant, 2),
            "total_ht_remise": round(total_ht_remise, 2),
            "total_ttc": round(total_ttc, 2),
        },
    }


# Route forfaits-mo DEPRECATED: tout doit passer par Prestation/GrilleTarifaire


@router.get("/api/tarifs/delais")
def get_delais_intervention(
    prestation_ids: Optional[str] = None,
    db: Session = Depends(get_db),
):
    """Récupère les délais d'intervention pour des prestations."""
    return get_delais_intervention_handler(prestation_ids, db)


@router.get("/api/tarifs/synthese")
def get_synthese_tarifs(
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Synthèse complète des tarifs (prestations uniquement, forfaits_mo deprecé)."""
    total_prestations = db.query(Prestation).filter(Prestation.is_active == 1).count()
    prestations_par_categorie = db.query(
        Prestation.categorie,
        func.count(Prestation.id).label("count"),
    ).filter(Prestation.is_active == 1).group_by(Prestation.categorie).all()

    prix_moyen_prestation = db.query(func.avg(Prestation.prix_base_ttc)).filter(
        Prestation.is_active == 1
    ).scalar() or 0
    config = db.query(ConfigAtelier).first()

    return {
        "prestations": {
            "total_actives": total_prestations,
            "par_categorie": [{"categorie": categorie, "count": count} for categorie, count in prestations_par_categorie],
            "prix_moyen_ttc": round(float(prix_moyen_prestation), 2),
        },
        "taux_horaires": {
            "standard": config.taux_horaire_mo_standard if config else 65.0,
            "complexe": config.taux_horaire_mo_complexe if config else 85.0,
            "expert": config.taux_horaire_mo_expert if config else 95.0,
        },
    }
