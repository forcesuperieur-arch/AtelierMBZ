from typing import Optional

from pydantic import BaseModel


class CategorieMotoCreate(BaseModel):
    nom: str
    description: Optional[str] = None


class CategorieMotoResponse(BaseModel):
    id: int
    nom: str
    description: Optional[str]
    nb_modeles: int = 0

    class Config:
        from_attributes = True


class ModeleMotoCreate(BaseModel):
    marque: str
    modele: str
    categorie_id: int
    cylindree_min: Optional[int] = None
    cylindree_max: Optional[int] = None
    annee_debut: Optional[int] = None
    annee_fin: Optional[int] = None


class ModeleMotoResponse(BaseModel):
    id: int
    marque: str
    modele: str
    categorie_id: int
    categorie_nom: str
    cylindree_min: Optional[int]
    cylindree_max: Optional[int]
    cylindree_display: str
    annee_debut: Optional[int]
    annee_fin: Optional[int]
    annees_display: str

    class Config:
        from_attributes = True


class ModeleMotoUpdate(BaseModel):
    marque: Optional[str] = None
    modele: Optional[str] = None
    categorie_id: Optional[int] = None
    cylindree_min: Optional[int] = None
    cylindree_max: Optional[int] = None
    annee_debut: Optional[int] = None
    annee_fin: Optional[int] = None


__all__ = [
    "CategorieMotoCreate",
    "CategorieMotoResponse",
    "ModeleMotoCreate",
    "ModeleMotoResponse",
    "ModeleMotoUpdate",
]
