"""Routes pour la gestion des photos d'intervention (mécanicien)."""

import os
import uuid
from pathlib import Path

from fastapi import APIRouter, Depends, File, Form, HTTPException, UploadFile
from fastapi.responses import FileResponse
from sqlalchemy.orm import Session

from auth import get_current_user
from models import PhotoIntervention, RendezVous, User, get_db

router = APIRouter(tags=["photos"])

PHOTOS_DIR = Path(__file__).resolve().parent.parent / "data" / "photos"
PHOTOS_DIR.mkdir(parents=True, exist_ok=True)

ALLOWED_EXTENSIONS = {".jpg", ".jpeg", ".png", ".gif", ".webp"}
MAX_FILE_SIZE = 10 * 1024 * 1024  # 10 MB


@router.post("/api/rendez-vous/{rdv_id}/photos")
async def upload_photo(
    rdv_id: int,
    file: UploadFile = File(...),
    description: str = Form(default=""),
    annotation_json: str = Form(default=""),
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Upload une photo pour un RDV."""
    rdv = db.query(RendezVous).filter(RendezVous.id == rdv_id).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="RDV non trouvé")

    # Validate extension
    ext = os.path.splitext(file.filename or "")[1].lower()
    if ext not in ALLOWED_EXTENSIONS:
        raise HTTPException(status_code=400, detail=f"Type de fichier non autorisé. Extensions acceptées: {', '.join(ALLOWED_EXTENSIONS)}")

    # Read and validate size
    content = await file.read()
    if len(content) > MAX_FILE_SIZE:
        raise HTTPException(status_code=400, detail="Fichier trop volumineux (max 10 Mo)")

    # Save file
    unique_name = f"{uuid.uuid4().hex}{ext}"
    file_path = PHOTOS_DIR / unique_name
    file_path.write_bytes(content)

    photo = PhotoIntervention(
        atelier_id=getattr(current_user, "atelier_id", None) or 1,
        rendez_vous_id=rdv_id,
        filename=unique_name,
        original_name=file.filename,
        annotation_json=annotation_json or None,
        description=description or None,
    )
    db.add(photo)
    db.commit()
    db.refresh(photo)

    return {
        "id": photo.id,
        "filename": photo.filename,
        "url": f"/api/photos/{photo.id}/image",
        "description": photo.description,
        "created_at": photo.created_at.isoformat() if photo.created_at else None,
    }


@router.get("/api/rendez-vous/{rdv_id}/photos")
def list_photos(
    rdv_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Liste les photos d'un RDV."""
    photos = (
        db.query(PhotoIntervention)
        .filter(PhotoIntervention.rendez_vous_id == rdv_id)
        .order_by(PhotoIntervention.created_at.desc())
        .all()
    )
    return [
        {
            "id": p.id,
            "filename": p.filename,
            "original_name": p.original_name,
            "description": p.description,
            "annotation_json": p.annotation_json,
            "url": f"/api/photos/{p.id}/image",
            "created_at": p.created_at.isoformat() if p.created_at else None,
        }
        for p in photos
    ]


@router.get("/api/photos/{photo_id}/image")
def get_photo_image(photo_id: int, db: Session = Depends(get_db)):
    """Sert le fichier image d'une photo (public pour suivi client)."""
    photo = db.query(PhotoIntervention).filter(PhotoIntervention.id == photo_id).first()
    if not photo:
        raise HTTPException(status_code=404, detail="Photo non trouvée")

    file_path = PHOTOS_DIR / photo.filename
    if not file_path.exists():
        raise HTTPException(status_code=404, detail="Fichier introuvable")

    return FileResponse(str(file_path))


@router.delete("/api/photos/{photo_id}")
def delete_photo(
    photo_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Supprime une photo."""
    photo = db.query(PhotoIntervention).filter(PhotoIntervention.id == photo_id).first()
    if not photo:
        raise HTTPException(status_code=404, detail="Photo non trouvée")

    # Delete file
    file_path = PHOTOS_DIR / photo.filename
    if file_path.exists():
        file_path.unlink()

    db.delete(photo)
    db.commit()
    return {"message": "Photo supprimée"}


@router.put("/api/photos/{photo_id}/annotation")
def update_annotation(
    photo_id: int,
    annotation_json: str = Form(default=""),
    description: str = Form(default=""),
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Met à jour les annotations d'une photo."""
    photo = db.query(PhotoIntervention).filter(PhotoIntervention.id == photo_id).first()
    if not photo:
        raise HTTPException(status_code=404, detail="Photo non trouvée")

    if annotation_json:
        photo.annotation_json = annotation_json
    if description:
        photo.description = description

    db.commit()
    return {"message": "Annotations mises à jour"}
