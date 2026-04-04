import random
import re
from datetime import datetime
from typing import List, Optional

from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from sqlalchemy import and_, or_
from sqlalchemy.orm import Session

from auth import get_current_user, get_password_hash, is_password_strong
from models import Atelier, Mecanicien, Pont, RendezVous, User, UserAtelierRole, get_db
from routes.auth_api import get_allowed_roles, normalize_role_slug, require_role

router = APIRouter(tags=["tenant-admin"])


class UserCreate(BaseModel):
    username: str
    email: Optional[str] = None
    password: str
    role: str
    atelier_id: Optional[int] = None


class UserUpdate(BaseModel):
    username: Optional[str] = None
    email: Optional[str] = None
    password: Optional[str] = None
    role: Optional[str] = None
    is_active: Optional[bool] = None
    mecanicien_nom: Optional[str] = None
    mecanicien_prenom: Optional[str] = None
    mecanicien_specialites: Optional[str] = None
    mecanicien_couleur: Optional[str] = None


class UserResponse(BaseModel):
    id: int
    username: str
    email: Optional[str]
    role: str
    is_active: int
    atelier_id: Optional[int] = None
    mecanicien: Optional[dict] = None

    class Config:
        from_attributes = True


class UserAtelierRoleCreate(BaseModel):
    email: str
    username: str
    role: str = "receptionnaire"
    atelier_id: int
    password: Optional[str] = None
    mecanicien_nom: Optional[str] = None
    mecanicien_prenom: Optional[str] = None
    mecanicien_specialites: Optional[str] = None
    mecanicien_couleur: Optional[str] = None


class AtelierCreate(BaseModel):
    nom: str
    slug: Optional[str] = None
    email: Optional[str] = None
    telephone: Optional[str] = None
    adresse: Optional[str] = None
    cp: Optional[str] = None
    ville: Optional[str] = None
    siret: Optional[str] = None
    plan: Optional[str] = "starter"


class AtelierUpdate(BaseModel):
    nom: Optional[str] = None
    slug: Optional[str] = None
    email: Optional[str] = None
    telephone: Optional[str] = None
    adresse: Optional[str] = None
    cp: Optional[str] = None
    ville: Optional[str] = None
    siret: Optional[str] = None
    plan: Optional[str] = None
    actif: Optional[bool] = None


def _serialize_user(user: User, db: Session, atelier_id: Optional[int] = None) -> dict:
    item = {
        "id": user.id,
        "username": user.username,
        "email": user.email,
        "role": user.role,
        "is_active": user.is_active,
        "atelier_id": user.atelier_id,
    }
    if user.role == "mecanicien":
        mec = db.query(Mecanicien).filter(
            Mecanicien.user_id == user.id,
            Mecanicien.atelier_id == (atelier_id or user.atelier_id or 1)
        ).first()
        if mec:
            item["mecanicien"] = {
                "id": mec.id,
                "nom": mec.nom,
                "prenom": mec.prenom,
                "specialites": mec.specialites,
                "couleur": mec.couleur,
                "is_active": mec.is_active,
            }
    return item


def _upsert_mecanicien_for_user(
    db: Session,
    user: User,
    nom: Optional[str] = None,
    prenom: Optional[str] = None,
    specialites: Optional[str] = None,
    couleur: Optional[str] = None
) -> Mecanicien:
    mecano = db.query(Mecanicien).filter(
        Mecanicien.user_id == user.id,
        Mecanicien.atelier_id == (user.atelier_id or 1)
    ).first()
    if not mecano:
        couleurs = ["#3b82f6", "#ef4444", "#10b981", "#f59e0b", "#8b5cf6", "#ec4899", "#06b6d4"]
        parts = (user.username or "").split(".")
        default_nom = (parts[0] if parts and parts[0] else user.username or "Mecanicien").capitalize()
        default_prenom = (parts[1] if len(parts) > 1 else "").capitalize()
        mecano = Mecanicien(
            atelier_id=user.atelier_id or 1,
            user_id=user.id,
            nom=(nom or default_nom),
            prenom=(prenom or default_prenom),
            specialites=specialites,
            couleur=(couleur or random.choice(couleurs)),
            is_active=1 if user.is_active else 0
        )
        db.add(mecano)
        db.flush()
        return mecano
    mecano.nom = nom or mecano.nom
    mecano.prenom = prenom if prenom is not None else mecano.prenom
    mecano.specialites = specialites if specialites is not None else mecano.specialites
    mecano.couleur = couleur or mecano.couleur
    mecano.is_active = 1 if user.is_active else 0
    return mecano


def _deactivate_mecanicien_for_user(db: Session, user: User):
    mecanos = db.query(Mecanicien).filter(Mecanicien.user_id == user.id).all()
    today = datetime.now().date()
    for mecano in mecanos:
        mecano.is_active = 0
        db.query(RendezVous).filter(
            RendezVous.mecanicien_id == mecano.id,
            or_(
                RendezVous.date_rdv > today,
                and_(
                    RendezVous.date_rdv == today,
                    RendezVous.statut.notin_(["termine", "facture", "paye", "annule"])
                )
            )
        ).update({"mecanicien_id": None, "pont_id": None}, synchronize_session=False)
        db.query(Pont).filter(Pont.mecanicien_id == mecano.id).update({"mecanicien_id": None}, synchronize_session=False)


def _delete_mecanicien_for_user(db: Session, user: User):
    mecanos = db.query(Mecanicien).filter(Mecanicien.user_id == user.id).all()
    today = datetime.now().date()
    for mecano in mecanos:
        db.query(RendezVous).filter(
            RendezVous.mecanicien_id == mecano.id,
            or_(
                RendezVous.date_rdv > today,
                and_(
                    RendezVous.date_rdv == today,
                    RendezVous.statut.notin_(["termine", "facture", "paye", "annule"])
                )
            )
        ).update({"mecanicien_id": None, "pont_id": None}, synchronize_session=False)
        db.query(Pont).filter(Pont.mecanicien_id == mecano.id).update({"mecanicien_id": None}, synchronize_session=False)
        mecano.is_active = 0


@router.get("/api/users", response_model=List[UserResponse])
@require_role("admin")
def get_users(
    atelier_id: Optional[int] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    target_atelier_id = atelier_id or current_user.atelier_id or 1
    if current_user.role != "super_admin":
        target_atelier_id = current_user.atelier_id or 1
    users = db.query(User).filter(User.atelier_id == target_atelier_id).all()
    return [_serialize_user(user, db, target_atelier_id) for user in users]


@router.post("/api/users", response_model=UserResponse)
@require_role("admin")
def create_user(user_data: UserCreate, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    user_data.role = normalize_role_slug(user_data.role)
    allowed_roles = get_allowed_roles(db)
    if user_data.role not in allowed_roles:
        raise HTTPException(status_code=400, detail="Role invalide")
    if user_data.role == "super_admin" and current_user.role != "super_admin":
        raise HTTPException(status_code=403, detail="Seul un super_admin peut creer un super_admin")
    if current_user.role != "super_admin" and user_data.atelier_id and user_data.atelier_id != (current_user.atelier_id or 1):
        raise HTTPException(status_code=403, detail="Acces atelier refuse")
    if not is_password_strong(user_data.password):
        raise HTTPException(
            status_code=400,
            detail="Mot de passe trop faible: minimum 8 caracteres, 1 majuscule, 1 chiffre"
        )

    existing = db.query(User).filter(User.username == user_data.username).first()
    if existing:
        raise HTTPException(status_code=400, detail="Ce nom d'utilisateur existe déjà")

    new_user = User(
        username=user_data.username,
        email=user_data.email,
        hashed_password=get_password_hash(user_data.password),
        role=user_data.role,
        atelier_id=user_data.atelier_id or current_user.atelier_id or 1,
        is_active=1
    )
    db.add(new_user)
    db.commit()
    db.refresh(new_user)
    db.add(UserAtelierRole(user_id=new_user.id, atelier_id=new_user.atelier_id or 1, role=new_user.role))
    db.commit()

    if user_data.role == "mecanicien":
        _upsert_mecanicien_for_user(db, new_user)
        db.commit()

    return _serialize_user(new_user, db)


@router.get("/api/users/{user_id}", response_model=UserResponse)
@require_role("admin")
def get_user(user_id: int, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    query = db.query(User).filter(User.id == user_id)
    if current_user.role != "super_admin":
        query = query.filter(User.atelier_id == (current_user.atelier_id or 1))
    user = query.first()
    if not user:
        raise HTTPException(status_code=404, detail="Utilisateur non trouvé")
    return _serialize_user(user, db)


@router.put("/api/users/{user_id}", response_model=UserResponse)
@require_role("admin")
def update_user(user_id: int, user_data: UserUpdate, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    query = db.query(User).filter(User.id == user_id)
    if current_user.role != "super_admin":
        query = query.filter(User.atelier_id == (current_user.atelier_id or 1))
    user = query.first()
    if not user:
        raise HTTPException(status_code=404, detail="Utilisateur non trouvé")

    if user_data.username and user_data.username != user.username:
        existing = db.query(User).filter(User.username == user_data.username).first()
        if existing:
            raise HTTPException(status_code=400, detail="Ce nom d'utilisateur existe déjà")
        user.username = user_data.username

    if user_data.email is not None:
        user.email = user_data.email
    if user_data.role is not None:
        user_data.role = normalize_role_slug(user_data.role)
        allowed_roles = get_allowed_roles(db)
        if user_data.role not in allowed_roles:
            raise HTTPException(status_code=400, detail="Role invalide")
        user.role = user_data.role
        rel = db.query(UserAtelierRole).filter(
            UserAtelierRole.user_id == user.id,
            UserAtelierRole.atelier_id == (user.atelier_id or 1)
        ).first()
        if rel:
            rel.role = user_data.role
    if user_data.password:
        if not is_password_strong(user_data.password):
            raise HTTPException(
                status_code=400,
                detail="Mot de passe trop faible: minimum 8 caracteres, 1 majuscule, 1 chiffre"
            )
        user.hashed_password = get_password_hash(user_data.password)
    if user_data.is_active is not None:
        user.is_active = 1 if user_data.is_active else 0

    if user.role == "mecanicien":
        _upsert_mecanicien_for_user(
            db,
            user,
            nom=(user_data.mecanicien_nom or "").strip() or None,
            prenom=(user_data.mecanicien_prenom or "").strip() or None,
            specialites=(user_data.mecanicien_specialites or "").strip() or None,
            couleur=(user_data.mecanicien_couleur or "").strip() or None
        )
    else:
        _deactivate_mecanicien_for_user(db, user)

    db.commit()
    db.refresh(user)

    return _serialize_user(user, db)


@router.delete("/api/users/{user_id}")
@require_role("admin")
def delete_user(user_id: int, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    query = db.query(User).filter(User.id == user_id)
    if current_user.role != "super_admin":
        query = query.filter(User.atelier_id == (current_user.atelier_id or 1))
    user = query.first()
    if not user:
        raise HTTPException(status_code=404, detail="Utilisateur non trouvé")

    if user.id == current_user.id:
        raise HTTPException(status_code=400, detail="Vous ne pouvez pas supprimer votre propre compte")

    _delete_mecanicien_for_user(db, user)
    db.delete(user)
    db.commit()

    return {"message": "Utilisateur supprimé avec succès"}


@router.post("/api/users/invite")
@require_role("admin")
def invite_user_to_atelier(
    payload: UserAtelierRoleCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    payload.role = normalize_role_slug(payload.role)
    payload.email = (payload.email or "").strip().lower()
    if not payload.email:
        raise HTTPException(status_code=400, detail="Email obligatoire")
    allowed_roles = get_allowed_roles(db)
    if payload.role not in allowed_roles:
        raise HTTPException(status_code=400, detail="Role invalide")
    if payload.role == "super_admin" and current_user.role != "super_admin":
        raise HTTPException(status_code=403, detail="Seul un super_admin peut attribuer ce role")
    if current_user.role != "super_admin" and payload.atelier_id != (current_user.atelier_id or 1):
        raise HTTPException(status_code=403, detail="Acces atelier refuse")

    user = db.query(User).filter(User.email == payload.email).first()
    if not user:
        raw_password = payload.password or "TempPass1"
        if not is_password_strong(raw_password):
            raise HTTPException(status_code=400, detail="Mot de passe invitation invalide")
        existing_username = db.query(User).filter(User.username == payload.username).first()
        if existing_username:
            raise HTTPException(status_code=400, detail="Ce nom d'utilisateur existe deja")
        user = User(
            username=payload.username,
            email=payload.email,
            hashed_password=get_password_hash(raw_password),
            role=payload.role,
            atelier_id=payload.atelier_id,
            is_active=1
        )
        db.add(user)
        db.commit()
        db.refresh(user)
    elif not user.atelier_id:
        user.atelier_id = payload.atelier_id
        db.commit()
    rel = db.query(UserAtelierRole).filter(
        UserAtelierRole.user_id == user.id,
        UserAtelierRole.atelier_id == payload.atelier_id
    ).first()
    if rel:
        rel.role = payload.role
    else:
        db.add(UserAtelierRole(user_id=user.id, atelier_id=payload.atelier_id, role=payload.role))
    user.role = payload.role
    if payload.role == "mecanicien":
        if not (payload.mecanicien_nom or "").strip():
            raise HTTPException(status_code=400, detail="Nom mecanicien obligatoire")
        _upsert_mecanicien_for_user(
            db,
            user,
            nom=(payload.mecanicien_nom or "").strip() or None,
            prenom=(payload.mecanicien_prenom or "").strip() or None,
            specialites=(payload.mecanicien_specialites or "").strip() or None,
            couleur=(payload.mecanicien_couleur or "").strip() or None
        )
    else:
        _deactivate_mecanicien_for_user(db, user)
    db.commit()
    return {"message": "Utilisateur assigne a l'atelier", "user_id": user.id, "atelier_id": payload.atelier_id, "role": payload.role}


@router.get("/api/ateliers/{atelier_id}/users", response_model=List[UserResponse])
@require_role("admin")
def get_atelier_users(
    atelier_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    if current_user.role != "super_admin" and atelier_id != (current_user.atelier_id or 1):
        raise HTTPException(status_code=403, detail="Acces atelier refuse")
    users = db.query(User).filter(User.atelier_id == atelier_id).all()
    return [_serialize_user(user, db, atelier_id) for user in users]


@router.get("/api/ateliers")
def list_ateliers(db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    if current_user.role == "super_admin":
        ateliers = db.query(Atelier).order_by(Atelier.nom).all()
    else:
        ateliers = db.query(Atelier).filter(Atelier.id == (current_user.atelier_id or 1)).all()
    return [
        {
            "id": atelier.id,
            "nom": atelier.nom,
            "slug": atelier.slug,
            "email": atelier.email,
            "telephone": atelier.telephone,
            "ville": atelier.ville,
            "plan": atelier.plan,
            "actif": atelier.actif,
        } for atelier in ateliers
    ]


@router.get("/api/ateliers/public")
def list_public_ateliers(db: Session = Depends(get_db)):
    ateliers = db.query(Atelier).filter(Atelier.actif == True).order_by(Atelier.nom).all()
    return [
        {
            "id": atelier.id,
            "nom": atelier.nom,
            "slug": atelier.slug,
            "ville": atelier.ville,
            "telephone": atelier.telephone,
        } for atelier in ateliers
    ]


@router.post("/api/ateliers")
def create_atelier(data: AtelierCreate, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    if current_user.role != "super_admin":
        raise HTTPException(status_code=403, detail="Acces reserve super_admin")
    base_slug = (data.slug or data.nom or "").strip().lower()
    base_slug = re.sub(r"[^a-z0-9]+", "-", base_slug).strip("-") or "atelier"
    slug = base_slug
    idx = 2
    while db.query(Atelier).filter(Atelier.slug == slug).first():
        slug = f"{base_slug}-{idx}"
        idx += 1
    existing = db.query(Atelier).filter(Atelier.slug == slug).first()
    if existing:
        raise HTTPException(status_code=400, detail="Slug atelier deja utilise")
    payload = data.dict()
    payload["slug"] = slug
    atelier = Atelier(**payload)
    db.add(atelier)
    db.commit()
    db.refresh(atelier)
    rel = db.query(UserAtelierRole).filter(
        UserAtelierRole.user_id == current_user.id,
        UserAtelierRole.atelier_id == atelier.id
    ).first()
    if not rel:
        db.add(UserAtelierRole(user_id=current_user.id, atelier_id=atelier.id, role="super_admin"))
        db.commit()
    return {"id": atelier.id, "nom": atelier.nom, "slug": atelier.slug}


@router.put("/api/ateliers/{atelier_id}")
def update_atelier(
    atelier_id: int,
    data: AtelierUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    if current_user.role not in ("super_admin", "admin"):
        raise HTTPException(status_code=403, detail="Acces refuse")
    if current_user.role != "super_admin" and (current_user.atelier_id or 1) != atelier_id:
        raise HTTPException(status_code=403, detail="Acces atelier refuse")
    atelier = db.query(Atelier).filter(Atelier.id == atelier_id).first()
    if not atelier:
        raise HTTPException(status_code=404, detail="Atelier non trouve")
    if data.slug:
        new_slug = re.sub(r"[^a-z0-9]+", "-", data.slug.strip().lower()).strip("-")
        if not new_slug:
            raise HTTPException(status_code=400, detail="Slug invalide")
        exists = db.query(Atelier).filter(Atelier.slug == new_slug, Atelier.id != atelier_id).first()
        if exists:
            raise HTTPException(status_code=400, detail="Slug atelier deja utilise")
        atelier.slug = new_slug
    for key, value in data.dict(exclude_unset=True).items():
        if key == "slug":
            continue
        setattr(atelier, key, value)
    db.commit()
    db.refresh(atelier)
    return {"id": atelier.id, "nom": atelier.nom, "slug": atelier.slug, "actif": atelier.actif}
