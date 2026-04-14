"""Configuration atelier, horaires and atelier info endpoints."""

from datetime import datetime
from typing import List, Optional

from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.exc import IntegrityError
from sqlalchemy.orm import Session

from auth import get_current_user
from config_api_helpers import (
    ensure_horaire_jour,
    ensure_permission,
    horaire_to_dict,
    is_hhmm,
    resolve_atelier_id_for_config,
    resolve_target_atelier_id,
)
from config_api_schemas import (
    ConfigAtelierSchema,
    ConfigAtelierUpdate,
    HoraireAtelierSchema,
    HoraireAtelierUpdate,
)
from models import Atelier, ConfigAtelier, HoraireAtelier, User, get_db

router = APIRouter(tags=["Configuration"])


@router.get("/atelier", response_model=ConfigAtelierSchema)
def get_config_atelier(
    atelier_id: Optional[int] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    target_atelier_id = resolve_target_atelier_id(db, current_user, atelier_id=atelier_id)
    config = db.query(ConfigAtelier).filter(ConfigAtelier.id == target_atelier_id).first()
    if not config:
        config = ConfigAtelier(id=target_atelier_id)
        db.add(config)
        db.commit()
        db.refresh(config)
    return config


@router.put("/atelier", response_model=ConfigAtelierSchema)
def update_config_atelier(
    data: ConfigAtelierUpdate,
    atelier_id: Optional[int] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    ensure_permission(current_user, db, "config.manage")
    target_atelier_id = resolve_target_atelier_id(db, current_user, atelier_id=atelier_id)
    config = db.query(ConfigAtelier).filter(ConfigAtelier.id == target_atelier_id).first()
    if not config:
        config = ConfigAtelier(id=target_atelier_id)
        db.add(config)
    for key, value in data.model_dump(exclude_unset=True).items():
        setattr(config, key, value)
    db.commit()
    db.refresh(config)
    return config


@router.get("/horaires", response_model=List[HoraireAtelierSchema])
def get_horaires_atelier(
    atelier_id: Optional[int] = None,
    atelier_slug: Optional[str] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    target = resolve_target_atelier_id(db, current_user, atelier_id=atelier_id, atelier_slug=atelier_slug)
    horaires = db.query(HoraireAtelier).filter(HoraireAtelier.atelier_id == target).order_by(HoraireAtelier.jour_semaine).all()
    if not horaires:
        for jour in range(7):
            ensure_horaire_jour(db, target, jour)
        horaires = db.query(HoraireAtelier).filter(HoraireAtelier.atelier_id == target).order_by(HoraireAtelier.jour_semaine).all()
    return [horaire_to_dict(h) for h in horaires]


@router.get("/horaires/{jour}", response_model=HoraireAtelierSchema)
def get_horaire_jour(
    jour: int,
    atelier_id: Optional[int] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    if jour < 0 or jour > 6:
        raise HTTPException(status_code=400, detail="Jour invalide (0-6)")
    target = resolve_target_atelier_id(db, current_user, atelier_id=atelier_id)
    return horaire_to_dict(ensure_horaire_jour(db, target, jour))


@router.put("/horaires/{jour}", response_model=HoraireAtelierSchema)
def update_horaire_jour(
    jour: int,
    data: HoraireAtelierUpdate,
    atelier_id: Optional[int] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    ensure_permission(current_user, db, "horaires.manage")
    if jour < 0 or jour > 6:
        raise HTTPException(status_code=400, detail="Jour invalide (0-6)")
    target = resolve_target_atelier_id(db, current_user, atelier_id=atelier_id)
    horaire = ensure_horaire_jour(db, target, jour)

    update_data = data.model_dump(exclude_unset=True)
    for key in ["heure_ouverture", "heure_fermeture", "pause_debut", "pause_fin"]:
        if key in update_data and update_data[key] == "":
            update_data[key] = None
        if key in update_data and not is_hhmm(update_data[key]):
            raise HTTPException(status_code=400, detail=f"Format invalide pour {key} (HH:MM)")
    if update_data.get("is_ouvert") is not None:
        try:
            update_data["is_ouvert"] = 1 if int(update_data["is_ouvert"]) else 0
        except (TypeError, ValueError):
            update_data["is_ouvert"] = 0
    for key, value in update_data.items():
        setattr(horaire, key, value)
    horaire.updated_at = datetime.now()

    try:
        db.commit()
        db.refresh(horaire)
    except IntegrityError:
        db.rollback()
        raise HTTPException(status_code=400, detail="Impossible d'enregistrer l'horaire (contrainte base)")
    except Exception as exc:
        db.rollback()
        raise HTTPException(status_code=500, detail=f"Erreur sauvegarde horaire: {exc}")
    return horaire_to_dict(horaire)


@router.get("/atelier-info")
def get_atelier_info(
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    target_atelier_id = resolve_atelier_id_for_config(db, current_user)
    atelier = db.query(Atelier).filter(Atelier.id == target_atelier_id).first()
    if not atelier:
        return {"nom": "Mon Atelier", "logo_url": None}
    return {
        "id": atelier.id,
        "nom": atelier.nom,
        "logo_url": atelier.logo_url,
        "adresse": atelier.adresse,
        "cp": atelier.cp,
        "ville": atelier.ville,
        "telephone": atelier.telephone,
        "email": atelier.email,
        "siret": atelier.siret,
    }
