# tarifs_api.py - Endpoints pour la gestion des tarifs et temps
# NOTE: Les vieilles routes GET/POST/PUT/DELETE sur GrilleTarifs sont DEPRECATED.
# Tout devrait passer par /api/config/prestations (routes/prestations_tarifs.py).
# Cette file gère seulement le calcul de tarifs et creneaux.
from datetime import datetime
from typing import List

from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from sqlalchemy.orm import Session

from auth import get_current_user
from models import Prestation, Vehicule, get_db
from services.slot_service import list_available_duration_slots
from services.pricing_rules import PricingConfigError, resolve_prestation_pricing

router = APIRouter(prefix="/api", tags=["tarifs"])


def _tenant_id(current_user) -> int:
    return int(getattr(current_user, "atelier_id", None) or 1)


class CalculDevisRequest(BaseModel):
    vehicule_id: int
    prestations: List[dict]


def _resolve_prestation_from_payload(db: Session, atelier_id: int, payload: dict) -> Prestation | None:
    prestation_id = payload.get("prestation_id")
    if prestation_id is not None:
        return db.query(Prestation).filter(
            Prestation.id == prestation_id,
            Prestation.atelier_id == atelier_id,
            Prestation.is_active == 1,
        ).first()

    if payload.get("code"):
        return db.query(Prestation).filter(
            Prestation.code == str(payload.get("code")),
            Prestation.atelier_id == atelier_id,
            Prestation.is_active == 1,
        ).first()

    target_name = payload.get("nom") or payload.get("type_intervention")
    if target_name:
        return db.query(Prestation).filter(
            Prestation.nom == str(target_name),
            Prestation.atelier_id == atelier_id,
            Prestation.is_active == 1,
        ).first()

    return None


@router.post("/tarifs/calculer")
def calculer_devis(
    request: CalculDevisRequest,
    db: Session = Depends(get_db),
    current_user = Depends(get_current_user)
):
    """Calcule le temps et le prix total pour un ensemble de prestations.
    
    Utilise le moteur centralisé de tarification (resolve_prestation_pricing)
    qui respecte les toggles par catégorie de véhicule et bloque les prestations
    désactivées.
    """
    
    # Récupérer le véhicule et sa catégorie
    atelier_id = _tenant_id(current_user)
    vehicule = db.query(Vehicule).filter(Vehicule.id == request.vehicule_id, Vehicule.atelier_id == atelier_id).first()
    if not vehicule:
        raise HTTPException(status_code=404, detail="Véhicule non trouvé")
    
    # Calculer pour chaque prestation
    prestations_detaillees = []
    temps_total = 0
    prix_mo_total_ht = 0
    prix_mo_total_ttc = 0
    has_sur_devis = False
    missing = []
    
    for prestation in request.prestations:
        prestation_cfg = _resolve_prestation_from_payload(db, atelier_id, prestation)
        if not prestation_cfg:
            missing.append(
                prestation.get("type_intervention")
                or prestation.get("nom")
                or prestation.get("code")
                or str(prestation.get("prestation_id"))
            )
            continue
        try:
            pricing = resolve_prestation_pricing(
                db,
                atelier_id=atelier_id,
                prestation=prestation_cfg,
                vehicule=vehicule,
                strict=True,
            )
        except PricingConfigError as exc:
            raise HTTPException(status_code=400, detail=str(exc))

        prestations_detaillees.append({
            "type_intervention": prestation_cfg.code,
            "nom": prestation_cfg.nom,
            "description": prestation_cfg.description,
            "mode_tarification": pricing.mode_tarification,
            "temps_minutes": pricing.temps_minutes,
            "temps_formate": f"{pricing.temps_minutes // 60}h{pricing.temps_minutes % 60:02d}",
            "prix_mo_ht": pricing.prix_ht,
            "prix_mo_ttc": pricing.prix_ttc,
        })
        temps_total += pricing.temps_minutes
        if pricing.mode_tarification == "sur_devis":
            has_sur_devis = True
        else:
            prix_mo_total_ht += float(pricing.prix_ht or 0.0)
            prix_mo_total_ttc += float(pricing.prix_ttc or 0.0)

    if missing:
        raise HTTPException(
            status_code=400,
            detail=f"Prestations non configurees: {', '.join([str(m) for m in missing if m])}",
        )
    
    return {
        "vehicule_id": request.vehicule_id,
        "vehicule": {
            "marque": vehicule.marque,
            "modele": vehicule.modele,
            "cylindree": vehicule.cylindree
        },
        "prestations": prestations_detaillees,
        "temps_total_minutes": temps_total,
        "temps_total_heures": round(temps_total / 60, 2),
        "temps_total_formate": f"{temps_total // 60}h{temps_total % 60:02d}",
        "prix_mo_total_ht": round(prix_mo_total_ht, 2),
        "prix_mo_total_ttc": None if has_sur_devis else round(prix_mo_total_ttc, 2),
        "total_ht": round(prix_mo_total_ht, 2),
        "total_ttc": None if has_sur_devis else round(prix_mo_total_ttc, 2),
        "contient_sur_devis": has_sur_devis,
        "nb_prestations": len(prestations_detaillees)
    }


@router.get("/creneaux/par-duree")
def get_creneaux_par_duree(
    date_debut: str,
    date_fin: str,
    duree_heures: int,
    db: Session = Depends(get_db),
    current_user = Depends(get_current_user)
):
    """Récupère les créneaux disponibles pour une plage de dates et une durée spécifique"""
    return list_available_duration_slots(
        db,
        atelier_id=_tenant_id(current_user),
        date_debut=date_debut,
        date_fin=date_fin,
        duree_heures=duree_heures,
    )
