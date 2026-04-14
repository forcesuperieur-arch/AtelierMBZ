"""Temps d'intervention endpoints."""

from datetime import datetime
from typing import List, Optional

from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session

from auth import get_current_user
from config_api_helpers import ensure_permission
from config_api_schemas import TempsInterventionCreate, TempsInterventionSchema
from models import CategorieMoto, InterventionType, TempsIntervention, User, get_db

router = APIRouter(tags=["Configuration"])


@router.get("/temps-interventions", response_model=List[TempsInterventionSchema])
def get_temps_interventions(
    categorie_id: Optional[int] = None,
    intervention_id: Optional[int] = None,
    db: Session = Depends(get_db),
):
    query = db.query(TempsIntervention)
    if categorie_id:
        query = query.filter(TempsIntervention.categorie_moto_id == categorie_id)
    if intervention_id:
        query = query.filter(TempsIntervention.intervention_type_id == intervention_id)
    return query.all()


@router.get("/temps-interventions/{temps_id}", response_model=TempsInterventionSchema)
def get_temps_intervention(temps_id: int, db: Session = Depends(get_db)):
    temps = db.query(TempsIntervention).filter(TempsIntervention.id == temps_id).first()
    if not temps:
        raise HTTPException(status_code=404, detail="Temps d'intervention non trouvé")
    return temps


@router.post("/temps-interventions", response_model=TempsInterventionSchema)
def create_temps_intervention(
    data: TempsInterventionCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    ensure_permission(current_user, db, "prestations.manage")
    categorie = db.query(CategorieMoto).filter(CategorieMoto.id == data.categorie_moto_id).first()
    intervention = db.query(InterventionType).filter(InterventionType.id == data.intervention_type_id).first()
    if not categorie or not intervention:
        raise HTTPException(status_code=400, detail="Catégorie ou intervention invalide")
    temps = TempsIntervention(**data.model_dump())
    db.add(temps)
    db.commit()
    db.refresh(temps)
    return temps


@router.put("/temps-interventions/{temps_id}", response_model=TempsInterventionSchema)
def update_temps_intervention(
    temps_id: int,
    data: TempsInterventionCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    ensure_permission(current_user, db, "prestations.manage")
    temps = db.query(TempsIntervention).filter(TempsIntervention.id == temps_id).first()
    if not temps:
        raise HTTPException(status_code=404, detail="Temps d'intervention non trouvé")
    for key, value in data.model_dump().items():
        setattr(temps, key, value)
    temps.updated_at = datetime.now()
    db.commit()
    db.refresh(temps)
    return temps


@router.delete("/temps-interventions/{temps_id}")
def delete_temps_intervention(
    temps_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    ensure_permission(current_user, db, "prestations.manage")
    temps = db.query(TempsIntervention).filter(TempsIntervention.id == temps_id).first()
    if not temps:
        raise HTTPException(status_code=404, detail="Temps d'intervention non trouvé")
    db.delete(temps)
    db.commit()
    return {"message": "Temps d'intervention supprimé"}
