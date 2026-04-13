from typing import Optional

from pydantic import BaseModel


class ForfaitMOCreate(BaseModel):
    code: str
    nom: str
    description: Optional[str] = None
    categorie: Optional[str] = None
    temps_base_minutes: int
    taux_horaire_applique: Optional[str] = "standard"
    prix_forfait_mo_ht: float
    prix_forfait_mo_ttc: float
    inclut_pieces: Optional[int] = 0
    description_pieces_incluses: Optional[str] = None
    prix_pieces_incluses_ht: Optional[float] = 0.0
    type_vehicule: Optional[str] = "tous"
    cylindree_min: Optional[int] = None
    cylindree_max: Optional[int] = None
    is_active: Optional[int] = 1
    is_promo: Optional[int] = 0
    prix_promo_mo_ttc: Optional[float] = None


class ForfaitMOUpdate(BaseModel):
    code: Optional[str] = None
    nom: Optional[str] = None
    description: Optional[str] = None
    categorie: Optional[str] = None
    temps_base_minutes: Optional[int] = None
    taux_horaire_applique: Optional[str] = None
    prix_forfait_mo_ht: Optional[float] = None
    prix_forfait_mo_ttc: Optional[float] = None
    inclut_pieces: Optional[int] = None
    description_pieces_incluses: Optional[str] = None
    prix_pieces_incluses_ht: Optional[float] = None
    type_vehicule: Optional[str] = None
    cylindree_min: Optional[int] = None
    cylindree_max: Optional[int] = None
    is_active: Optional[int] = None
    is_promo: Optional[int] = None
    prix_promo_mo_ttc: Optional[float] = None


__all__ = ["ForfaitMOCreate", "ForfaitMOUpdate"]
