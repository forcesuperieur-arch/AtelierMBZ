from typing import Optional

from pydantic import BaseModel


class ClientUpdate(BaseModel):
    nom: Optional[str] = None
    prenom: Optional[str] = None
    telephone: Optional[str] = None
    email: Optional[str] = None
    adresse: Optional[str] = None
    notes: Optional[str] = None


class ClientCreate(BaseModel):
    nom: str
    prenom: str
    telephone: str
    email: Optional[str] = None
    adresse: Optional[str] = None
    notes: Optional[str] = None


class VehiculeCreate(BaseModel):
    plaque: str
    marque: Optional[str] = None
    modele: Optional[str] = None
    annee: Optional[int] = None
    cylindree: Optional[str] = None
    type_moto: Optional[str] = None


class VehiculeUpdate(BaseModel):
    plaque: Optional[str] = None
    marque: Optional[str] = None
    modele: Optional[str] = None
    annee: Optional[int] = None
    cylindree: Optional[str] = None
    type_moto: Optional[str] = None
