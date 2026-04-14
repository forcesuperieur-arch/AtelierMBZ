"""Categories and logo endpoints for configuration API."""

import uuid
from pathlib import Path
from typing import Optional

from fastapi import APIRouter, Depends, File, HTTPException, UploadFile
from fastapi.responses import FileResponse
from sqlalchemy.orm import Session

from auth import get_current_user
from config_api_helpers import (
    ALLOWED_IMG_TYPES,
    LOGO_DIR,
    MAX_LOGO_SIZE,
    detect_image_type,
    ensure_permission,
    resolve_target_atelier_id,
)
from models import Atelier, AtelierCategorieMoto, CategorieMoto, User, get_db

router = APIRouter(tags=["Configuration"])


@router.get("/categories-moto")
def get_atelier_categories_moto(
    atelier_id: Optional[int] = None,
    atelier_slug: Optional[str] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    target_atelier_id = resolve_target_atelier_id(db, current_user, atelier_id=atelier_id, atelier_slug=atelier_slug)
    categories = db.query(CategorieMoto).order_by(CategorieMoto.nom).all()
    result = []
    for cat in categories:
        acm = db.query(AtelierCategorieMoto).filter(
            AtelierCategorieMoto.atelier_id == target_atelier_id,
            AtelierCategorieMoto.categorie_moto_id == cat.id,
        ).first()
        result.append(
            {
                "id": cat.id,
                "nom": cat.nom,
                "description": cat.description,
                "is_active": acm.is_active if acm else True,
            }
        )
    return result


@router.put("/categories-moto/{categorie_id}/toggle")
def toggle_atelier_categorie_moto(
    categorie_id: int,
    atelier_id: Optional[int] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    ensure_permission(current_user, db, "motos.manage")
    target_atelier_id = resolve_target_atelier_id(db, current_user, atelier_id=atelier_id)

    cat = db.query(CategorieMoto).filter(CategorieMoto.id == categorie_id).first()
    if not cat:
        raise HTTPException(status_code=404, detail="Catégorie introuvable")

    acm = db.query(AtelierCategorieMoto).filter(
        AtelierCategorieMoto.atelier_id == target_atelier_id,
        AtelierCategorieMoto.categorie_moto_id == categorie_id,
    ).first()

    if not acm:
        acm = AtelierCategorieMoto(
            atelier_id=target_atelier_id,
            categorie_moto_id=categorie_id,
            is_active=False,
        )
        db.add(acm)
    else:
        acm.is_active = not acm.is_active

    db.commit()
    return {"message": "Catégorie mise à jour", "is_active": acm.is_active, "categorie_id": categorie_id}


@router.post("/atelier/logo")
async def upload_atelier_logo(
    file: UploadFile = File(...),
    atelier_id: Optional[int] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    ensure_permission(current_user, db, "config.manage")
    target_atelier_id = resolve_target_atelier_id(db, current_user, atelier_id=atelier_id)

    content = await file.read()
    if len(content) > MAX_LOGO_SIZE:
        raise HTTPException(status_code=400, detail="Fichier trop volumineux (max 2 Mo)")

    img_type = detect_image_type(content)
    if img_type not in ALLOWED_IMG_TYPES:
        raise HTTPException(status_code=400, detail="Format non supporté (PNG, JPEG, GIF, WebP)")

    ext = "jpg" if img_type == "jpeg" else img_type
    filename = f"atelier_{target_atelier_id}_{uuid.uuid4().hex[:8]}.{ext}"
    filepath = LOGO_DIR / filename
    filepath.write_bytes(content)

    logo_url = f"/api/config/atelier/logo/{filename}"
    atelier = db.query(Atelier).filter(Atelier.id == target_atelier_id).first()
    if atelier:
        old_url = atelier.logo_url or ""
        if old_url.startswith("/api/config/atelier/logo/"):
            old_name = old_url.split("/")[-1]
            old_path = LOGO_DIR / old_name
            if old_path.exists():
                old_path.unlink(missing_ok=True)
        atelier.logo_url = logo_url
        db.commit()

    return {"logo_url": logo_url}


@router.get("/atelier/logo/{filename}")
async def serve_atelier_logo(filename: str):
    safe_name = Path(filename).name
    if safe_name != filename or ".." in filename:
        raise HTTPException(status_code=400, detail="Nom de fichier invalide")
    filepath = LOGO_DIR / safe_name
    if not filepath.exists():
        raise HTTPException(status_code=404, detail="Logo introuvable")
    ext = safe_name.rsplit(".", 1)[-1].lower()
    mime = {
        "png": "image/png",
        "jpg": "image/jpeg",
        "jpeg": "image/jpeg",
        "gif": "image/gif",
        "webp": "image/webp",
    }.get(ext, "application/octet-stream")
    return FileResponse(str(filepath), media_type=mime)
