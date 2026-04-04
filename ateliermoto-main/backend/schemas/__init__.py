from .clients import ClientCreate, ClientUpdate, VehiculeCreate, VehiculeUpdate
from .rendez_vous import (
    ClientCreate as RdvClientCreate,
    OrdreReparationSave,
    RapportTechnicienCreate,
    RapportTechnicienResponse,
    RendezVousCreate,
    RendezVousUpdate,
    VehiculeCreate as RdvVehiculeCreate,
)
from .forfaits_mo import ForfaitMOCreate, ForfaitMOUpdate
from .inventory import (
    CommandeFournisseurCreate,
    CommandeFournisseurUpdate,
    FournisseurCreate,
    FournisseurUpdate,
    PieceDetacheeCreate,
    PieceDetacheeUpdate,
    PieceUtiliseeCreate,
    ReceptionCommande,
)
from .workshop import AbsenceCreate, AbsenceResponse, MecanicienCreate, PontCreate

__all__ = [
    "ClientCreate",
    "ClientUpdate",
    "VehiculeCreate",
    "VehiculeUpdate",
    "PontCreate",
    "MecanicienCreate",
    "AbsenceCreate",
    "FournisseurCreate",
    "FournisseurUpdate",
    "PieceDetacheeCreate",
    "PieceDetacheeUpdate",
    "CommandeFournisseurCreate",
    "CommandeFournisseurUpdate",
    "ReceptionCommande",
    "PieceUtiliseeCreate",
    "ForfaitMOCreate",
    "ForfaitMOUpdate",
    "AbsenceResponse",
    "RendezVousCreate",
    "RendezVousUpdate",
    "OrdreReparationSave",
    "RapportTechnicienCreate",
    "RapportTechnicienResponse",
    "RdvClientCreate",
    "RdvVehiculeCreate",
]
