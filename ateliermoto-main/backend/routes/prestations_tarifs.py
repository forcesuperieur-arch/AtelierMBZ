from typing import List, Optional

from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from sqlalchemy import func
from sqlalchemy.orm import Session

from auth import get_current_user
from models import ConfigAtelier, ForfaitMO, GrilleTarifaire, PieceDetachee, Prestation, User, get_db
from routes.public_booking import get_delais_intervention_handler

router = APIRouter(tags=["prestations-tarifs"])


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

    payload = prestation_data.model_dump()
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
    prestation = db.query(Prestation).filter(Prestation.id == prestation_id).first()
    if not prestation:
        raise HTTPException(status_code=404, detail="Prestation non trouvée")

    if prestation_data.code and prestation_data.code != prestation.code:
        existing = db.query(Prestation).filter(Prestation.code == prestation_data.code).first()
        if existing:
            raise HTTPException(status_code=400, detail="Ce code prestation existe déjà")

    for field, value in prestation_data.model_dump(exclude_unset=True).items():
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
    prestation = db.query(Prestation).filter(Prestation.id == prestation_id).first()
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
    grille = db.query(GrilleTarifaire).filter(GrilleTarifaire.id == grille_id).first()
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


@router.get("/api/tarifs/forfaits-mo")
def get_forfaits_mo_actifs(
    categorie: Optional[str] = None,
    db: Session = Depends(get_db),
):
    """Récupère les forfaits MO actifs (public)."""
    query = db.query(ForfaitMO).filter(ForfaitMO.is_active == 1)
    if categorie:
        query = query.filter(ForfaitMO.categorie == categorie)

    forfaits = query.order_by(ForfaitMO.categorie, ForfaitMO.nom).all()
    return [
        {
            "id": forfait.id,
            "code": forfait.code,
            "nom": forfait.nom,
            "description": forfait.description,
            "categorie": forfait.categorie,
            "temps_base_minutes": forfait.temps_base_minutes,
            "prix_forfait_mo_ttc": forfait.prix_forfait_mo_ttc,
            "prix_affichage": forfait.prix_promo_mo_ttc if forfait.is_promo and forfait.prix_promo_mo_ttc else forfait.prix_forfait_mo_ttc,
            "inclut_pieces": forfait.inclut_pieces,
            "type_vehicule": forfait.type_vehicule,
            "is_promo": forfait.is_promo,
            "prix_promo_mo_ttc": forfait.prix_promo_mo_ttc,
        }
        for forfait in forfaits
    ]


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
    """Synthèse complète des tarifs et forfaits."""
    total_prestations = db.query(Prestation).filter(Prestation.is_active == 1).count()
    prestations_par_categorie = db.query(
        Prestation.categorie,
        func.count(Prestation.id).label("count"),
    ).filter(Prestation.is_active == 1).group_by(Prestation.categorie).all()

    total_forfaits = db.query(ForfaitMO).filter(ForfaitMO.is_active == 1).count()
    forfaits_promo = db.query(ForfaitMO).filter(
        ForfaitMO.is_active == 1,
        ForfaitMO.is_promo == 1,
    ).count()

    prix_moyen_prestation = db.query(func.avg(Prestation.prix_base_ttc)).filter(
        Prestation.is_active == 1
    ).scalar() or 0
    prix_moyen_forfait = db.query(func.avg(ForfaitMO.prix_forfait_mo_ttc)).filter(
        ForfaitMO.is_active == 1
    ).scalar() or 0
    config = db.query(ConfigAtelier).first()

    return {
        "prestations": {
            "total_actives": total_prestations,
            "par_categorie": [{"categorie": categorie, "count": count} for categorie, count in prestations_par_categorie],
            "prix_moyen_ttc": round(float(prix_moyen_prestation), 2),
        },
        "forfaits_mo": {
            "total_actifs": total_forfaits,
            "en_promotion": forfaits_promo,
            "prix_moyen_ttc": round(float(prix_moyen_forfait), 2),
        },
        "taux_horaires": {
            "standard": config.taux_horaire_mo_standard if config else 65.0,
            "complexe": config.taux_horaire_mo_complexe if config else 85.0,
            "expert": config.taux_horaire_mo_expert if config else 95.0,
        },
    }
