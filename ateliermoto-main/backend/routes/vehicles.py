from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session

from auth import get_current_user
from models import User, Vehicule, get_db
from routes.public_booking import get_vehicule_by_plaque_handler

router = APIRouter(tags=["vehicules"])


@router.get("/api/vehicules/{vehicule_id}")
def get_vehicule_by_id(
    vehicule_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Récupère un véhicule par son ID."""
    vehicule = db.query(Vehicule).filter(Vehicule.id == vehicule_id).first()
    if not vehicule:
        raise HTTPException(status_code=404, detail="Véhicule non trouvé")
    return {
        "id": vehicule.id,
        "plaque": vehicule.plaque,
        "marque": vehicule.marque,
        "modele": vehicule.modele,
        "annee": vehicule.annee,
        "cylindree": vehicule.cylindree,
        "type_moto": vehicule.type_moto,
    }


@router.get("/api/vehicule/{plaque}")
async def get_vehicule_by_plaque(plaque: str, db: Session = Depends(get_db)):
    """Récupère les informations d'un véhicule par sa plaque."""
    return await get_vehicule_by_plaque_handler(plaque, db)


@router.post("/api/vehicule")
def create_vehicule_manuel(vehicule_data: dict, db: Session = Depends(get_db)):
    """Crée un véhicule manuellement pour les plaques non trouvées."""
    plaque = str(vehicule_data.get("plaque", "")).upper().replace(" ", "").replace("-", "")

    existing = db.query(Vehicule).filter(Vehicule.plaque == plaque).first()
    if existing:
        return {
            "id": existing.id,
            "plaque": existing.plaque,
            "marque": existing.marque,
            "modele": existing.modele,
            "annee": existing.annee,
            "cylindree": existing.cylindree,
            "type_moto": existing.type_moto,
            "source": "database",
            "message": "Véhicule déjà existant",
        }

    new_vehicule = Vehicule(
        plaque=plaque,
        marque=vehicule_data.get("marque", "Non spécifié"),
        modele=vehicule_data.get("modele", "Non spécifié"),
        annee=vehicule_data.get("annee"),
        cylindree=vehicule_data.get("cylindree"),
        type_moto=vehicule_data.get("type_moto", "Non spécifié"),
    )
    db.add(new_vehicule)
    db.commit()
    db.refresh(new_vehicule)

    return {
        "id": new_vehicule.id,
        "plaque": new_vehicule.plaque,
        "marque": new_vehicule.marque,
        "modele": new_vehicule.modele,
        "annee": new_vehicule.annee,
        "cylindree": new_vehicule.cylindree,
        "type_moto": new_vehicule.type_moto,
        "source": "manual",
        "message": "Véhicule créé avec succès",
    }
