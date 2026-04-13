from typing import Optional

from pydantic import BaseModel


class DemandeTravauxSuppCreate(BaseModel):
    description: Optional[str] = ""
    prestations_demandees: Optional[list] = None
    urgence: Optional[str] = "normal"


class DemandeTravauxSuppUpdate(BaseModel):
    statut: str
    notes_receptionniste: Optional[str] = None
    prix_estime: Optional[float] = None
    temps_estime: Optional[int] = None
    signature: Optional[str] = None


__all__ = ["DemandeTravauxSuppCreate", "DemandeTravauxSuppUpdate"]
