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


__all__ = [
    "FournisseurCreate",
    "FournisseurUpdate",
    "PieceDetacheeCreate",
    "PieceDetacheeUpdate",
]
