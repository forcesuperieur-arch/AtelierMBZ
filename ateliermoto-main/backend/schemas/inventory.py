from typing import Optional

from pydantic import BaseModel


class FournisseurCreate(BaseModel):
    nom: str
    contact: Optional[str] = None
    telephone: Optional[str] = None
    email: Optional[str] = None
    adresse: Optional[str] = None
    siret: Optional[str] = None
    delai_livraison_jours: Optional[int] = 3
    notes: Optional[str] = None


class FournisseurUpdate(BaseModel):
    nom: Optional[str] = None
    contact: Optional[str] = None
    telephone: Optional[str] = None
    email: Optional[str] = None
    adresse: Optional[str] = None
    siret: Optional[str] = None
    delai_livraison_jours: Optional[int] = None
    notes: Optional[str] = None
    is_active: Optional[int] = None


class PieceDetacheeCreate(BaseModel):
    reference: str
    reference_fournisseur: Optional[str] = None
    nom: str
    description: Optional[str] = None
    categorie: Optional[str] = None
    quantite_stock: Optional[int] = 0
    quantite_minimale: Optional[int] = 5
    quantite_maximale: Optional[int] = 50
    emplacement: Optional[str] = None
    prix_achat_ht: Optional[float] = 0.0
    prix_vente_ht: Optional[float] = 0.0
    tva_taux: Optional[float] = 20.0
    fournisseur_id: Optional[int] = None


class PieceDetacheeUpdate(BaseModel):
    reference: Optional[str] = None
    reference_fournisseur: Optional[str] = None
    nom: Optional[str] = None
    description: Optional[str] = None
    categorie: Optional[str] = None
    quantite_stock: Optional[int] = None
    quantite_minimale: Optional[int] = None
    quantite_maximale: Optional[int] = None
    emplacement: Optional[str] = None
    prix_achat_ht: Optional[float] = None
    prix_vente_ht: Optional[float] = None
    tva_taux: Optional[float] = None
    fournisseur_id: Optional[int] = None
    is_active: Optional[int] = None


class LigneCommandeCreate(BaseModel):
    piece_id: int
    quantite_demandee: int
    prix_unitaire_ht: float


class CommandeFournisseurCreate(BaseModel):
    fournisseur_id: int
    lignes: list[LigneCommandeCreate]
    notes: Optional[str] = None


class CommandeFournisseurUpdate(BaseModel):
    statut: Optional[str] = None
    date_prevue_livraison: Optional[str] = None
    notes: Optional[str] = None


class ReceptionLigne(BaseModel):
    ligne_id: int
    quantite_recue: int


class ReceptionCommande(BaseModel):
    lignes: list[ReceptionLigne]


class PieceUtiliseeCreate(BaseModel):
    piece_id: int
    quantite: int
    prix_vente_unitaire: Optional[float] = None


__all__ = [
    "FournisseurCreate",
    "FournisseurUpdate",
    "PieceDetacheeCreate",
    "PieceDetacheeUpdate",
    "LigneCommandeCreate",
    "CommandeFournisseurCreate",
    "CommandeFournisseurUpdate",
    "ReceptionLigne",
    "ReceptionCommande",
    "PieceUtiliseeCreate",
]
