"""Shared helper functions for configuration API."""

import json as _json
import re
from pathlib import Path
from typing import Optional

from fastapi import HTTPException
from sqlalchemy.exc import IntegrityError
from sqlalchemy.orm import Session

from models import Atelier, HoraireAtelier, RolePermission, User
from routes.auth_api import user_has_permission

LOGO_DIR = Path(__file__).resolve().parent / "data" / "logos"
LOGO_DIR.mkdir(parents=True, exist_ok=True)
ALLOWED_IMG_TYPES = {"png", "jpeg", "gif", "webp"}
MAX_LOGO_SIZE = 2 * 1024 * 1024


def detect_image_type(data: bytes) -> str | None:
    if data[:8] == b"\x89PNG\r\n\x1a\n":
        return "png"
    if data[:2] == b"\xff\xd8":
        return "jpeg"
    if data[:4] == b"GIF8":
        return "gif"
    if data[:4] == b"RIFF" and data[8:12] == b"WEBP":
        return "webp"
    return None


def resolve_atelier_id_for_config(db: Session, current_user: User) -> int:
    raw = getattr(current_user, "atelier_id", None)
    try:
        atelier_id = int(raw)
    except (TypeError, ValueError):
        atelier_id = 0
    if atelier_id > 0:
        exists = db.query(Atelier.id).filter(Atelier.id == atelier_id).first()
        if exists:
            return atelier_id
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


def resolve_any_atelier_id(db: Session) -> int:
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


def user_can_select_atelier(current_user: User, db: Session) -> bool:
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
    return current_user.role in ("admin", "receptionnaire", "service_client")


def resolve_target_atelier_id(
    db: Session,
    current_user: User,
    atelier_id: Optional[int] = None,
    atelier_slug: Optional[str] = None,
) -> int:
    if user_can_select_atelier(current_user, db):
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
    return resolve_atelier_id_for_config(db, current_user)


def ensure_horaire_jour(db: Session, atelier_id: int, jour: int) -> HoraireAtelier:
    horaire = db.query(HoraireAtelier).filter(
        HoraireAtelier.jour_semaine == jour,
        HoraireAtelier.atelier_id == atelier_id,
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
        fallback_atelier_id = resolve_any_atelier_id(db)
        horaire = db.query(HoraireAtelier).filter(
            HoraireAtelier.jour_semaine == jour,
            HoraireAtelier.atelier_id == fallback_atelier_id,
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


def horaire_to_dict(h: HoraireAtelier) -> dict:
    return {
        "id": h.id,
        "jour_semaine": h.jour_semaine,
        "heure_ouverture": h.heure_ouverture or None,
        "heure_fermeture": h.heure_fermeture or None,
        "pause_debut": h.pause_debut or None,
        "pause_fin": h.pause_fin or None,
        "is_ouvert": 1 if bool(h.is_ouvert) else 0,
    }


def is_hhmm(value: Optional[str]) -> bool:
    if value is None:
        return True
    if not re.match(r"^\d{2}:\d{2}$", value):
        return False
    hh, mm = value.split(":")
    return 0 <= int(hh) <= 23 and 0 <= int(mm) <= 59


def ensure_permission(current_user: User, db: Session, permission: str) -> None:
    if not user_has_permission(current_user, db, permission):
        raise HTTPException(status_code=403, detail=f"Permission {permission} requise")
