"""Pont equipment endpoints."""

from datetime import datetime
from typing import List, Optional

from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session

from auth import get_current_user
from config_api_helpers import ensure_permission, resolve_target_atelier_id
from config_api_schemas import PontEquipementCreate, PontEquipementSchema
from models import Pont, PontEquipement, User, get_db

router = APIRouter(tags=["Configuration"])


@router.get("/pont-equipements", response_model=List[PontEquipementSchema])
def get_pont_equipements(
    pont_id: Optional[int] = None,
    atelier_id: Optional[int] = None,
    db: Session = Depends(get_db),
):
    query = db.query(PontEquipement).join(Pont, PontEquipement.pont_id == Pont.id)
    if pont_id:
        query = query.filter(PontEquipement.pont_id == pont_id)
    if atelier_id:
        query = query.filter(Pont.atelier_id == atelier_id)
    return query.all()


@router.get("/pont-equipements/{eq_id}", response_model=PontEquipementSchema)
def get_pont_equipement(eq_id: int, atelier_id: Optional[int] = None, db: Session = Depends(get_db)):
    query = db.query(PontEquipement).join(Pont, PontEquipement.pont_id == Pont.id).filter(PontEquipement.id == eq_id)
    if atelier_id:
        query = query.filter(Pont.atelier_id == atelier_id)
    eq = query.first()
    if not eq:
        raise HTTPException(status_code=404, detail="Équipement non trouvé")
    return eq


@router.post("/pont-equipements", response_model=PontEquipementSchema)
def create_pont_equipement(
    data: PontEquipementCreate,
    atelier_id: Optional[int] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    ensure_permission(current_user, db, "workshop.manage")
    target_atelier_id = resolve_target_atelier_id(db, current_user, atelier_id=atelier_id)
    pont = db.query(Pont).filter(Pont.id == data.pont_id, Pont.atelier_id == target_atelier_id).first()
    if not pont:
        raise HTTPException(status_code=404, detail="Pont non trouvé")
    eq = PontEquipement(**data.model_dump())
    db.add(eq)
    db.commit()
    db.refresh(eq)
    return eq


@router.put("/pont-equipements/{eq_id}", response_model=PontEquipementSchema)
def update_pont_equipement(
    eq_id: int,
    data: PontEquipementCreate,
    atelier_id: Optional[int] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    ensure_permission(current_user, db, "workshop.manage")
    target_atelier_id = resolve_target_atelier_id(db, current_user, atelier_id=atelier_id)
    eq = db.query(PontEquipement).join(Pont, PontEquipement.pont_id == Pont.id).filter(
        PontEquipement.id == eq_id,
        Pont.atelier_id == target_atelier_id,
    ).first()
    if not eq:
        raise HTTPException(status_code=404, detail="Équipement non trouvé")
    pont = db.query(Pont).filter(Pont.id == data.pont_id, Pont.atelier_id == target_atelier_id).first()
    if not pont:
        raise HTTPException(status_code=404, detail="Pont non trouvé")

    for key, value in data.model_dump().items():
        setattr(eq, key, value)
    eq.updated_at = datetime.now()
    db.commit()
    db.refresh(eq)
    return eq


@router.delete("/pont-equipements/{eq_id}")
def delete_pont_equipement(
    eq_id: int,
    atelier_id: Optional[int] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    ensure_permission(current_user, db, "workshop.manage")
    target_atelier_id = resolve_target_atelier_id(db, current_user, atelier_id=atelier_id)
    eq = db.query(PontEquipement).join(Pont, PontEquipement.pont_id == Pont.id).filter(
        PontEquipement.id == eq_id,
        Pont.atelier_id == target_atelier_id,
    ).first()
    if not eq:
        raise HTTPException(status_code=404, detail="Équipement non trouvé")
    db.delete(eq)
    db.commit()
    return {"message": "Équipement supprimé"}
