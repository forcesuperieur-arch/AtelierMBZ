"""Pydantic schemas for configuration API."""

from typing import Optional

from pydantic import BaseModel, ConfigDict


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

    model_config = ConfigDict(from_attributes=True)


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

    model_config = ConfigDict(from_attributes=True)


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

    model_config = ConfigDict(from_attributes=True)


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

    model_config = ConfigDict(from_attributes=True)


class PontEquipementCreate(BaseModel):
    pont_id: int
    nom: str
    description: Optional[str] = None
    is_present: int = 1
