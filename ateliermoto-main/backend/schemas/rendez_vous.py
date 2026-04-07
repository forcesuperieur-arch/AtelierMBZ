from datetime import date, datetime, time
from typing import List, Optional

from pydantic import BaseModel


class ClientCreate(BaseModel):
    nom: str
    prenom: str
    telephone: str
    email: Optional[str] = None


class VehiculeCreate(BaseModel):
    plaque: str
    marque: Optional[str] = None
    modele: Optional[str] = None
    annee: Optional[int] = None
    cylindree: Optional[str] = None
    type_moto: Optional[str] = None
    categorie_id: Optional[int] = None
    modele_id: Optional[int] = None


class RendezVousCreate(BaseModel):
    client: ClientCreate
    vehicule: VehiculeCreate
    date_rdv: date
    heure_rdv: time
    type_intervention: str
    commentaire: Optional[str] = None


class RendezVousUpdate(BaseModel):
    statut: Optional[str] = None
    kilometrage: Optional[int] = None
    etat_vehicule: Optional[str] = None
    prix_final: Optional[float] = None
    temps_final: Optional[int] = None
    commentaire: Optional[str] = None
    pont_id: Optional[int] = None
    mecanicien_id: Optional[int] = None
    heure_rdv: Optional[str] = None
    date_rdv: Optional[str] = None


class OrdreReparationSave(BaseModel):
    kilometrage: int
    etat_vehicule: str
    travaux: Optional[str] = None
    signature: Optional[str] = None
    priorite: Optional[str] = None
    niveau_carburant: Optional[int] = None
    dommages_carrosserie: Optional[List[str]] = None
    notes_schema: Optional[str] = None
    lignes_estimation: Optional[List[dict]] = None
    photos: Optional[List[str]] = None


class RapportTechnicienCreate(BaseModel):
    points_controle: Optional[dict] = None
    alertes: Optional[str] = None
    recommandations: Optional[str] = None
    travaux_realises: Optional[str] = None
    pieces_utilisees: Optional[List[dict]] = None
    statut: Optional[str] = "en_cours"


class RapportTechnicienResponse(BaseModel):
    id: int
    rendez_vous_id: int
    points_controle: Optional[dict]
    alertes: Optional[str]
    recommandations: Optional[str]
    travaux_realises: Optional[str]
    pieces_utilisees: Optional[List[dict]]
    statut: str
    date_debut: Optional[datetime]
    date_fin: Optional[datetime]
