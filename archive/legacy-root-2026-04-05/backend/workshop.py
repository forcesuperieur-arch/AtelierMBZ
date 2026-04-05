"""
Endpoints API pour la paramétrabilité complète
- Configuration atelier
- Horaires
- Temps interventions
- Équipements ponts
"""
from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session
from sqlalchemy.exc import IntegrityError
from typing import List, Optional
from pydantic import BaseModel
from datetime import datetime
import re

from models import (
    get_db, Atelier, ConfigAtelier, HoraireAtelier, TempsIntervention,
    PontEquipement, CategorieMoto, InterventionType, Pont, User, RolePermission,
    AtelierCategorieMoto
)
from auth import get_current_user
import json as _json

router = APIRouter(prefix="/api/config", tags=["Configuration"])


def _tenant_id(current_user: User) -> int:
    raw = getattr(current_user, "atelier_id", None)
    try:
        value = int(raw)
        return value if value > 0 else 1
    except (TypeError, ValueError):
        return 1


def _resolve_atelier_id_for_config(db: Session, current_user: User) -> int:
    raw = getattr(current_user, "atelier_id", None)
    try:
        atelier_id = int(raw)
    except (TypeError, ValueError):
        atelier_id = 0
    if atelier_id > 0:
        exists = db.query(Atelier.id).filter(Atelier.id == atelier_id).first()
        if exists:
            return atelier_id
    # Priorite: atelier "default" meme inactif pour eviter un conflit unique slug.
    default_atelier = db.query(Atelier).filter(Atelier.slug == "default").first()
    if default_atelier:
        return default_atelier.id
    first_active = db.query(Atelier).filter(Atelier.actif == True).order_by(Atelier.id.asc()).first()
    if first_active:
        return first_active.id
    first_any = db.query(Atelier).order_by(Atelier.id.asc()).first()
    if first_any:
        return first_any.id
    created = Atelier(nom="Mon Atelier", slug="default", plan="starter", actif=True)
    db.add(created)
    try:
        db.commit()
        db.refresh(created)
        return created.id
    except IntegrityError:
        db.rollback()
        existing = db.query(Atelier).filter(Atelier.slug == "default").first()
        if existing:
            return existing.id
        raise


def _resolve_any_atelier_id(db: Session) -> int:
    default_atelier = db.query(Atelier).filter(Atelier.slug == "default").first()
    if default_atelier:
        return default_atelier.id
    first_active = db.query(Atelier).filter(Atelier.actif == True).order_by(Atelier.id.asc()).first()
    if first_active:
        return first_active.id
    first_any = db.query(Atelier).order_by(Atelier.id.asc()).first()
    if first_any:
        return first_any.id
    created = Atelier(nom="Mon Atelier", slug="default", plan="starter", actif=True)
    db.add(created)
    db.commit()
    db.refresh(created)
    return created.id


def _user_can_select_atelier(current_user: User, db: Session) -> bool:
    if current_user.role == "super_admin":
        return True
    rp = db.query(RolePermission).filter(RolePermission.role == (current_user.role or "")).first()
    if rp:
        try:
            perms = _json.loads(rp.permissions_json or "[]")
        except Exception:
            perms = []
        if "rdv.select_atelier" in perms:
            return True
    if current_user.role in ("admin", "receptionnaire", "service_client"):
        return True
    return False


def _resolve_target_atelier_id(
    db: Session,
    current_user: User,
    atelier_id: Optional[int] = None,
    atelier_slug: Optional[str] = None
) -> int:
    can_select = _user_can_select_atelier(current_user, db)
    if can_select:
        if atelier_id is not None:
            a = db.query(Atelier.id).filter(Atelier.id == atelier_id).first()
            if not a:
                raise HTTPException(status_code=404, detail="Atelier introuvable")
            return int(atelier_id)
        slug = (atelier_slug or "").strip().lower()
        if slug:
            a = db.query(Atelier.id).filter(Atelier.slug == slug, Atelier.actif == True).first()
            if not a:
                raise HTTPException(status_code=404, detail="Atelier introuvable")
            return int(a[0])
    return _resolve_atelier_id_for_config(db, current_user)


def _ensure_horaire_jour(db: Session, atelier_id: int, jour: int) -> HoraireAtelier:
    horaire = db.query(HoraireAtelier).filter(
        HoraireAtelier.jour_semaine == jour,
        HoraireAtelier.atelier_id == atelier_id
    ).first()
    if horaire:
        return horaire
    horaire = HoraireAtelier(
        atelier_id=atelier_id,
        jour_semaine=jour,
        heure_ouverture="08:00",
        heure_fermeture="18:00",
        pause_debut="12:00",
        pause_fin="14:00",
        is_ouvert=1,
    )
    db.add(horaire)
    try:
        db.commit()
        db.refresh(horaire)
        return horaire
    except IntegrityError:
        db.rollback()
        # Cas concurrent / FK invalide: on re-resout un atelier valide et on retente.
        fallback_atelier_id = _resolve_any_atelier_id(db)
        horaire = db.query(HoraireAtelier).filter(
            HoraireAtelier.jour_semaine == jour,
            HoraireAtelier.atelier_id == fallback_atelier_id
        ).first()
        if horaire:
            return horaire
        retry = HoraireAtelier(
            atelier_id=fallback_atelier_id,
            jour_semaine=jour,
            heure_ouverture="08:00",
            heure_fermeture="18:00",
            pause_debut="12:00",
            pause_fin="14:00",
            is_ouvert=1,
        )
        db.add(retry)
        db.commit()
        db.refresh(retry)
        return retry


def _horaire_to_dict(h: HoraireAtelier) -> dict:
    return {
        "id": h.id,
        "jour_semaine": h.jour_semaine,
        "heure_ouverture": h.heure_ouverture or None,
        "heure_fermeture": h.heure_fermeture or None,
        "pause_debut": h.pause_debut or None,
        "pause_fin": h.pause_fin or None,
        "is_ouvert": 1 if bool(h.is_ouvert) else 0,
    }


def _is_hhmm(value: Optional[str]) -> bool:
    if value is None:
        return True
    if not re.match(r"^\d{2}:\d{2}$", value):
        return False
    hh, mm = value.split(":")
    return 0 <= int(hh) <= 23 and 0 <= int(mm) <= 59

# ===== PYDANTIC MODELS =====

class ConfigAtelierSchema(BaseModel):
    id: int
    taux_horaire_mo_standard: float
    taux_horaire_mo_complexe: float
    taux_horaire_mo_expert: float
    marge_pieces_standard: float
    marge_pieces_consommable: float
    marge_pieces_pneumatique: float
    forfait_mo_minimum: float
    tva_mo_taux: float
    tva_pieces_taux: float
    validite_devis_jours: int
    accompte_pourcentage: float

    class Config:
        from_attributes = True


class ConfigAtelierUpdate(BaseModel):
    taux_horaire_mo_standard: Optional[float] = None
    taux_horaire_mo_complexe: Optional[float] = None
    taux_horaire_mo_expert: Optional[float] = None
    marge_pieces_standard: Optional[float] = None
    marge_pieces_consommable: Optional[float] = None
    marge_pieces_pneumatique: Optional[float] = None
    forfait_mo_minimum: Optional[float] = None
    tva_mo_taux: Optional[float] = None
    tva_pieces_taux: Optional[float] = None
    validite_devis_jours: Optional[int] = None
    accompte_pourcentage: Optional[float] = None


class HoraireAtelierSchema(BaseModel):
    id: int
    jour_semaine: int
    heure_ouverture: Optional[str]
    heure_fermeture: Optional[str]
    pause_debut: Optional[str]
    pause_fin: Optional[str]
    is_ouvert: int

    class Config:
        from_attributes = True


class HoraireAtelierUpdate(BaseModel):
    heure_ouverture: Optional[str] = None
    heure_fermeture: Optional[str] = None
    pause_debut: Optional[str] = None
    pause_fin: Optional[str] = None
    is_ouvert: Optional[int] = None


class TempsInterventionSchema(BaseModel):
    id: int
    categorie_moto_id: int
    intervention_type_id: int
    temps_minutes: int
    coefficient_difficulte: float

    class Config:
        from_attributes = True


class TempsInterventionCreate(BaseModel):
    categorie_moto_id: int
    intervention_type_id: int
    temps_minutes: int
    coefficient_difficulte: float = 1.0


class PontEquipementSchema(BaseModel):
    id: int
    pont_id: int
    nom: str
    description: Optional[str]
    is_present: int

    class Config:
        from_attributes = True


class PontEquipementCreate(BaseModel):
    pont_id: int
    nom: str
    description: Optional[str] = None
    is_present: int = 1


# ===== ENDPOINTS - CONFIGURATION ATELIER =====

@router.get("/atelier", response_model=ConfigAtelierSchema)
def get_config_atelier(
    atelier_id: Optional[int] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Récupère la configuration globale de l'atelier (tarifs, marges, TVA)"""
    target_atelier_id = _resolve_target_atelier_id(db, current_user, atelier_id=atelier_id)
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
    current_user: User = Depends(get_current_user)
):
    """Modifie la configuration de l'atelier (admin seulement)"""
    if current_user.role not in ["admin", "manager", "super_admin"]:
        raise HTTPException(status_code=403, detail="Accès refusé")

    target_atelier_id = _resolve_target_atelier_id(db, current_user, atelier_id=atelier_id)
    config = db.query(ConfigAtelier).filter(ConfigAtelier.id == target_atelier_id).first()
    if not config:
        config = ConfigAtelier(id=target_atelier_id)
        db.add(config)

    # Mettre à jour les champs fournis
    update_data = data.dict(exclude_unset=True)
    for key, value in update_data.items():
        setattr(config, key, value)

    db.commit()
    db.refresh(config)
    return config


# ===== ENDPOINTS - HORAIRES ATELIER =====

@router.get("/horaires", response_model=List[HoraireAtelierSchema])
def get_horaires_atelier(
    atelier_id: Optional[int] = None,
    atelier_slug: Optional[str] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Récupère les horaires d'ouverture/fermeture par jour (Lun-Dim)"""
    atelier_id = _resolve_target_atelier_id(db, current_user, atelier_id=atelier_id, atelier_slug=atelier_slug)
    horaires = db.query(HoraireAtelier).filter(HoraireAtelier.atelier_id == atelier_id).order_by(HoraireAtelier.jour_semaine).all()
    if not horaires:
        for jour in range(7):
            _ensure_horaire_jour(db, atelier_id, jour)
        horaires = db.query(HoraireAtelier).filter(HoraireAtelier.atelier_id == atelier_id).order_by(HoraireAtelier.jour_semaine).all()
    return [_horaire_to_dict(h) for h in horaires]


@router.get("/horaires/{jour}", response_model=HoraireAtelierSchema)
def get_horaire_jour(
    jour: int,
    atelier_id: Optional[int] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Récupère l'horaire d'un jour (0=Lun, 6=Dim)"""
    if jour < 0 or jour > 6:
        raise HTTPException(status_code=400, detail="Jour invalide (0-6)")

    atelier_id = _resolve_target_atelier_id(db, current_user, atelier_id=atelier_id)
    horaire = _ensure_horaire_jour(db, atelier_id, jour)
    return _horaire_to_dict(horaire)


@router.put("/horaires/{jour}", response_model=HoraireAtelierSchema)
def update_horaire_jour(
    jour: int,
    data: HoraireAtelierUpdate,
    atelier_id: Optional[int] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Modifie l'horaire d'un jour"""
    if current_user.role not in ["admin", "manager", "super_admin"]:
        raise HTTPException(status_code=403, detail="Accès refusé")

    if jour < 0 or jour > 6:
        raise HTTPException(status_code=400, detail="Jour invalide (0-6)")

    try:
        atelier_id = _resolve_target_atelier_id(db, current_user, atelier_id=atelier_id)
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Impossible de résoudre l'atelier: {e}")

    horaire = _ensure_horaire_jour(db, atelier_id, jour)

    update_data = data.dict(exclude_unset=True)
    for k in ["heure_ouverture", "heure_fermeture", "pause_debut", "pause_fin"]:
        if k in update_data and update_data[k] == "":
            update_data[k] = None
    for k in ["heure_ouverture", "heure_fermeture", "pause_debut", "pause_fin"]:
        if k in update_data and not _is_hhmm(update_data[k]):
            raise HTTPException(status_code=400, detail=f"Format invalide pour {k} (HH:MM)")
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
    except Exception as e:
        db.rollback()
        raise HTTPException(status_code=500, detail=f"Erreur sauvegarde horaire: {e}")
    return _horaire_to_dict(horaire)


# ===== ENDPOINTS - TEMPS INTERVENTIONS =====

@router.get("/temps-interventions", response_model=List[TempsInterventionSchema])
def get_temps_interventions(
    categorie_id: Optional[int] = None,
    intervention_id: Optional[int] = None,
    db: Session = Depends(get_db)
):
    """Liste les temps d'intervention (optionnellement filtrés)"""
    query = db.query(TempsIntervention)

    if categorie_id:
        query = query.filter(TempsIntervention.categorie_moto_id == categorie_id)
    if intervention_id:
        query = query.filter(TempsIntervention.intervention_type_id == intervention_id)

    return query.all()


@router.get("/temps-interventions/{temps_id}", response_model=TempsInterventionSchema)
def get_temps_intervention(temps_id: int, db: Session = Depends(get_db)):
    """Récupère un temps d'intervention"""
    temps = db.query(TempsIntervention).filter(TempsIntervention.id == temps_id).first()
    if not temps:
        raise HTTPException(status_code=404, detail="Temps d'intervention non trouvé")
    return temps


@router.post("/temps-interventions", response_model=TempsInterventionSchema)
def create_temps_intervention(
    data: TempsInterventionCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Crée un nouveau temps d'intervention"""
    if current_user.role not in ["admin", "manager"]:
        raise HTTPException(status_code=403, detail="Accès refusé")

    # Vérifier que la catégorie et intervention existent
    categorie = db.query(CategorieMoto).filter(CategorieMoto.id == data.categorie_moto_id).first()
    intervention = db.query(InterventionType).filter(InterventionType.id == data.intervention_type_id).first()

    if not categorie or not intervention:
        raise HTTPException(status_code=400, detail="Catégorie ou intervention invalide")

    temps = TempsIntervention(**data.dict())
    db.add(temps)
    db.commit()
    db.refresh(temps)
    return temps


@router.put("/temps-interventions/{temps_id}", response_model=TempsInterventionSchema)
def update_temps_intervention(
    temps_id: int,
    data: TempsInterventionCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Modifie un temps d'intervention"""
    if current_user.role not in ["admin", "manager"]:
        raise HTTPException(status_code=403, detail="Accès refusé")

    temps = db.query(TempsIntervention).filter(TempsIntervention.id == temps_id).first()
    if not temps:
        raise HTTPException(status_code=404, detail="Temps d'intervention non trouvé")

    for key, value in data.dict().items():
        setattr(temps, key, value)
    temps.updated_at = datetime.now()

    db.commit()
    db.refresh(temps)
    return temps


@router.delete("/temps-interventions/{temps_id}")
def delete_temps_intervention(
    temps_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Supprime un temps d'intervention"""
    if current_user.role not in ["admin", "manager"]:
        raise HTTPException(status_code=403, detail="Accès refusé")

    temps = db.query(TempsIntervention).filter(TempsIntervention.id == temps_id).first()
    if not temps:
        raise HTTPException(status_code=404, detail="Temps d'intervention non trouvé")

    db.delete(temps)
    db.commit()
    return {"message": "Temps d'intervention supprimé"}


# ===== ENDPOINTS - ÉQUIPEMENTS PONTS =====

@router.get("/pont-equipements", response_model=List[PontEquipementSchema])
def get_pont_equipements(
    pont_id: Optional[int] = None,
    atelier_id: Optional[int] = None,
    db: Session = Depends(get_db)
):
    """Liste les équipements (optionnellement par pont)"""
    query = db.query(PontEquipement).join(Pont, PontEquipement.pont_id == Pont.id)
    if pont_id:
        query = query.filter(PontEquipement.pont_id == pont_id)
    if atelier_id:
        query = query.filter(Pont.atelier_id == atelier_id)
    return query.all()


@router.get("/pont-equipements/{eq_id}", response_model=PontEquipementSchema)
def get_pont_equipement(eq_id: int, atelier_id: Optional[int] = None, db: Session = Depends(get_db)):
    """Récupère un équipement"""
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
    current_user: User = Depends(get_current_user)
):
    """Crée un nouvel équipement pour un pont"""
    if current_user.role not in ["admin", "manager", "super_admin"]:
        raise HTTPException(status_code=403, detail="Accès refusé")

    # Vérifier que le pont existe
    target_atelier_id = _resolve_target_atelier_id(db, current_user, atelier_id=atelier_id)
    pont = db.query(Pont).filter(Pont.id == data.pont_id, Pont.atelier_id == target_atelier_id).first()
    if not pont:
        raise HTTPException(status_code=404, detail="Pont non trouvé")

    eq = PontEquipement(**data.dict())
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
    current_user: User = Depends(get_current_user)
):
    """Modifie un équipement"""
    if current_user.role not in ["admin", "manager", "super_admin"]:
        raise HTTPException(status_code=403, detail="Accès refusé")
    target_atelier_id = _resolve_target_atelier_id(db, current_user, atelier_id=atelier_id)
    eq = db.query(PontEquipement).join(Pont, PontEquipement.pont_id == Pont.id).filter(
        PontEquipement.id == eq_id,
        Pont.atelier_id == target_atelier_id
    ).first()
    if not eq:
        raise HTTPException(status_code=404, detail="Équipement non trouvé")
    pont = db.query(Pont).filter(Pont.id == data.pont_id, Pont.atelier_id == target_atelier_id).first()
    if not pont:
        raise HTTPException(status_code=404, detail="Pont non trouvé")

    for key, value in data.dict().items():
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
    current_user: User = Depends(get_current_user)
):
    """Supprime un équipement"""
    if current_user.role not in ["admin", "manager", "super_admin"]:
        raise HTTPException(status_code=403, detail="Accès refusé")
    target_atelier_id = _resolve_target_atelier_id(db, current_user, atelier_id=atelier_id)
    eq = db.query(PontEquipement).join(Pont, PontEquipement.pont_id == Pont.id).filter(
        PontEquipement.id == eq_id,
        Pont.atelier_id == target_atelier_id
    ).first()
    if not eq:
        raise HTTPException(status_code=404, detail="Équipement non trouvé")

    db.delete(eq)
    db.commit()
    return {"message": "Équipement supprimé"}


# ========== TYPES MOTO PAR ATELIER ==========

@router.get("/categories-moto")
def get_atelier_categories_moto(
    atelier_id: Optional[int] = None,
    atelier_slug: Optional[str] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Liste les catégories moto avec leur statut actif/inactif pour l'atelier"""
    target_atelier_id = _resolve_target_atelier_id(db, current_user, atelier_id=atelier_id, atelier_slug=atelier_slug)

    categories = db.query(CategorieMoto).order_by(CategorieMoto.nom).all()
    result = []
    for cat in categories:
        acm = db.query(AtelierCategorieMoto).filter(
            AtelierCategorieMoto.atelier_id == target_atelier_id,
            AtelierCategorieMoto.categorie_moto_id == cat.id
        ).first()
        result.append({
            "id": cat.id,
            "nom": cat.nom,
            "description": cat.description,
            "is_active": acm.is_active if acm else True
        })
    return result


@router.put("/categories-moto/{categorie_id}/toggle")
def toggle_atelier_categorie_moto(
    categorie_id: int,
    atelier_id: Optional[int] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Active/désactive une catégorie moto pour l'atelier"""
    if current_user.role not in ("super_admin", "admin"):
        raise HTTPException(status_code=403, detail="Accès refusé")

    target_atelier_id = _resolve_target_atelier_id(db, current_user, atelier_id=atelier_id)

    cat = db.query(CategorieMoto).filter(CategorieMoto.id == categorie_id).first()
    if not cat:
        raise HTTPException(status_code=404, detail="Catégorie introuvable")

    acm = db.query(AtelierCategorieMoto).filter(
        AtelierCategorieMoto.atelier_id == target_atelier_id,
        AtelierCategorieMoto.categorie_moto_id == categorie_id
    ).first()

    if not acm:
        acm = AtelierCategorieMoto(
            atelier_id=target_atelier_id,
            categorie_moto_id=categorie_id,
            is_active=False
        )
        db.add(acm)
    else:
        acm.is_active = not acm.is_active

    db.commit()
    return {"message": "Catégorie mise à jour", "is_active": acm.is_active, "categorie_id": categorie_id}
