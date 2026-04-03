from datetime import date
from typing import Optional

from pydantic import BaseModel


class PontCreate(BaseModel):
    nom: str
    type_pont: Optional[str] = "moto"
    capacite_kg: Optional[int] = 500
    is_active: Optional[int] = 1
    ordre_affichage: Optional[int] = 0
    mecanicien_id: Optional[int] = None
    actif: Optional[int] = None

    def get_is_active(self):
        if self.actif is not None:
            return 1 if self.actif else 0
        return self.is_active


class MecanicienCreate(BaseModel):
    nom: str
    prenom: str
    specialites: Optional[str] = None
    couleur: Optional[str] = "#3b82f6"
    is_active: Optional[int] = 1
    actif: Optional[int] = None

    def get_is_active(self):
        if self.actif is not None:
            return 1 if self.actif else 0
        return self.is_active


class AbsenceCreate(BaseModel):
    mecanicien_id: int
    date_debut: date
    date_fin: date
    motif: str = "conge"
    notes: Optional[str] = None


class AbsenceResponse(BaseModel):
    id: int
    mecanicien_id: int
    mecanicien_nom: str
    mecanicien_prenom: str
    date_debut: date
    date_fin: date
    motif: str
    notes: Optional[str]
