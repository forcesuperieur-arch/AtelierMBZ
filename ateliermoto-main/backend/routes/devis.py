from datetime import date, datetime, time, timedelta
from typing import List, Optional

from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from sqlalchemy.orm import Session, joinedload

from auth import get_current_user
from models import ConfigAtelier, Devis, LigneDevis, RendezVous, User, get_db

router = APIRouter(tags=["devis"])


class LigneDevisCreate(BaseModel):
    type_ligne: str  # forfait_mo, piece, main_oeuvre_libre
    forfait_mo_id: Optional[int] = None
    piece_id: Optional[int] = None
    designation: str
    description_detail: Optional[str] = None
    quantite: int = 1
    prix_unitaire_ht: float
    taux_tva: Optional[float] = 20.0


class DevisCreate(BaseModel):
    client_id: int
    vehicule_id: Optional[int] = None
    kilometrage: Optional[int] = None
    notes_client: Optional[str] = None
    notes_internes: Optional[str] = None
    lignes: List[LigneDevisCreate]
    remise_pourcentage: Optional[float] = 0.0


class DevisUpdate(BaseModel):
    statut: Optional[str] = None
    notes_client: Optional[str] = None
    notes_internes: Optional[str] = None


class CalculDevisRequest(BaseModel):
    lignes: List[LigneDevisCreate]
    remise_pourcentage: Optional[float] = 0.0


def generer_numero_devis(db: Session) -> str:
    """Génère un numéro de devis unique."""
    annee = datetime.now().year
    count = db.query(Devis).filter(Devis.numero_devis.like(f"DEV-{annee}-%")).count()
    return f"DEV-{annee}-{count + 1:04d}"


@router.get("/api/devis")
def get_devis(
    statut: Optional[str] = None,
    client_id: Optional[int] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Liste les devis."""
    query = db.query(Devis).options(
        joinedload(Devis.client),
        joinedload(Devis.vehicule),
    )

    if statut:
        query = query.filter(Devis.statut == statut)
    if client_id:
        query = query.filter(Devis.client_id == client_id)

    devis_list = query.order_by(Devis.date_creation.desc()).all()
    return [
        {
            "id": d.id,
            "numero_devis": d.numero_devis,
            "client": {
                "id": d.client.id,
                "nom": d.client.nom,
                "prenom": d.client.prenom,
                "telephone": d.client.telephone,
            },
            "vehicule": {
                "id": d.vehicule.id,
                "marque": d.vehicule.marque,
                "modele": d.vehicule.modele,
                "plaque": d.vehicule.plaque,
            } if d.vehicule else None,
            "date_creation": d.date_creation.isoformat() if d.date_creation else None,
            "date_validite": d.date_validite.isoformat() if d.date_validite else None,
            "statut": d.statut,
            "total_ht": d.total_ht,
            "total_ttc": d.total_ttc,
            "nb_lignes": len(d.lignes),
        }
        for d in devis_list
    ]


@router.get("/api/devis/{devis_id}")
def get_devis_detail(
    devis_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Détail d'un devis avec ses lignes."""
    devis = db.query(Devis).options(
        joinedload(Devis.client),
        joinedload(Devis.vehicule),
        joinedload(Devis.lignes).joinedload(LigneDevis.forfait_mo),
        joinedload(Devis.lignes).joinedload(LigneDevis.piece),
    ).filter(Devis.id == devis_id).first()

    if not devis:
        raise HTTPException(status_code=404, detail="Devis non trouvé")

    lignes = []
    for ligne in sorted(devis.lignes, key=lambda item: item.ordre):
        lignes.append(
            {
                "id": ligne.id,
                "type_ligne": ligne.type_ligne,
                "forfait_mo_id": ligne.forfait_mo_id,
                "forfait_mo_code": ligne.forfait_mo.code if ligne.forfait_mo else None,
                "piece_id": ligne.piece_id,
                "piece_reference": ligne.piece.reference if ligne.piece else None,
                "designation": ligne.designation,
                "description_detail": ligne.description_detail,
                "quantite": ligne.quantite,
                "prix_unitaire_ht": ligne.prix_unitaire_ht,
                "taux_tva": ligne.taux_tva,
                "total_ligne_ht": ligne.total_ligne_ht,
                "total_ligne_ttc": ligne.total_ligne_ttc,
            }
        )

    return {
        "id": devis.id,
        "numero_devis": devis.numero_devis,
        "client": {
            "id": devis.client.id,
            "nom": devis.client.nom,
            "prenom": devis.client.prenom,
            "telephone": devis.client.telephone,
            "email": devis.client.email,
            "adresse": devis.client.adresse,
        },
        "vehicule": {
            "id": devis.vehicule.id,
            "marque": devis.vehicule.marque,
            "modele": devis.vehicule.modele,
            "plaque": devis.vehicule.plaque,
            "annee": devis.vehicule.annee,
            "cylindree": devis.vehicule.cylindree,
        } if devis.vehicule else None,
        "date_creation": devis.date_creation.isoformat() if devis.date_creation else None,
        "date_validite": devis.date_validite.isoformat() if devis.date_validite else None,
        "statut": devis.statut,
        "kilometrage": devis.kilometrage,
        "total_mo_ht": devis.total_mo_ht,
        "total_pieces_ht": devis.total_pieces_ht,
        "total_ht": devis.total_ht,
        "total_ttc": devis.total_ttc,
        "remise_pourcentage": devis.remise_pourcentage,
        "remise_montant": devis.remise_montant,
        "acompte_demande": devis.acompte_demande,
        "notes_client": devis.notes_client,
        "notes_internes": devis.notes_internes,
        "rendez_vous_id": devis.rendez_vous_id,
        "lignes": lignes,
    }


@router.post("/api/devis")
def create_devis(
    devis_data: DevisCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Crée un nouveau devis."""
    config = db.query(ConfigAtelier).first()
    validite = config.validite_devis_jours if config else 30
    date_validite = date.today() + timedelta(days=validite)

    devis = Devis(
        numero_devis=generer_numero_devis(db),
        client_id=devis_data.client_id,
        vehicule_id=devis_data.vehicule_id,
        date_validite=date_validite,
        kilometrage=devis_data.kilometrage,
        notes_client=devis_data.notes_client,
        notes_internes=devis_data.notes_internes,
        remise_pourcentage=devis_data.remise_pourcentage,
        atelier_id=getattr(current_user, "atelier_id", None) or 1,
    )
    db.add(devis)
    db.flush()

    total_mo_ht = 0.0
    total_pieces_ht = 0.0

    for index, ligne_data in enumerate(devis_data.lignes):
        total_ligne_ht = ligne_data.quantite * ligne_data.prix_unitaire_ht
        total_ligne_ttc = total_ligne_ht * (1 + ((ligne_data.taux_tva or 0.0) / 100))

        ligne = LigneDevis(
            devis_id=devis.id,
            type_ligne=ligne_data.type_ligne,
            forfait_mo_id=ligne_data.forfait_mo_id,
            piece_id=ligne_data.piece_id,
            designation=ligne_data.designation,
            description_detail=ligne_data.description_detail,
            quantite=ligne_data.quantite,
            prix_unitaire_ht=ligne_data.prix_unitaire_ht,
            taux_tva=ligne_data.taux_tva,
            total_ligne_ht=total_ligne_ht,
            total_ligne_ttc=total_ligne_ttc,
            ordre=index,
        )
        db.add(ligne)

        if ligne_data.type_ligne in {"forfait_mo", "main_oeuvre_libre"}:
            total_mo_ht += total_ligne_ht
        else:
            total_pieces_ht += total_ligne_ht

    total_ht = total_mo_ht + total_pieces_ht
    remise_montant = total_ht * ((devis_data.remise_pourcentage or 0.0) / 100)
    total_ht_remise = total_ht - remise_montant
    tva_taux = config.tva_mo_taux if config else 20.0
    total_ttc = total_ht_remise * (1 + (tva_taux / 100))
    accompte = total_ttc * ((config.accompte_pourcentage / 100) if config else 0.3)

    devis.total_mo_ht = total_mo_ht
    devis.total_pieces_ht = total_pieces_ht
    devis.total_ht = total_ht_remise
    devis.total_ttc = total_ttc
    devis.remise_montant = remise_montant
    devis.acompte_demande = accompte

    db.commit()
    db.refresh(devis)
    return {"message": "Devis créé", "id": devis.id, "numero": devis.numero_devis}


@router.post("/api/devis/calculer")
def calculer_devis(
    calcul_data: CalculDevisRequest,
    db: Session = Depends(get_db),
):
    """Calcule les totaux d'un devis sans le sauvegarder."""
    config = db.query(ConfigAtelier).first()
    total_mo_ht = 0.0
    total_pieces_ht = 0.0

    for ligne in calcul_data.lignes:
        total_ligne_ht = ligne.quantite * ligne.prix_unitaire_ht
        if ligne.type_ligne in {"forfait_mo", "main_oeuvre_libre"}:
            total_mo_ht += total_ligne_ht
        else:
            total_pieces_ht += total_ligne_ht

    total_ht = total_mo_ht + total_pieces_ht
    remise_montant = total_ht * ((calcul_data.remise_pourcentage or 0.0) / 100)
    total_ht_remise = total_ht - remise_montant
    tva_taux = config.tva_mo_taux if config else 20.0
    total_ttc = total_ht_remise * (1 + (tva_taux / 100))

    return {
        "total_mo_ht": round(total_mo_ht, 2),
        "total_pieces_ht": round(total_pieces_ht, 2),
        "total_ht": round(total_ht, 2),
        "remise_pourcentage": calcul_data.remise_pourcentage,
        "remise_montant": round(remise_montant, 2),
        "total_ht_remise": round(total_ht_remise, 2),
        "total_ttc": round(total_ttc, 2),
    }


@router.put("/api/devis/{devis_id}")
def update_devis(
    devis_id: int,
    devis_data: DevisUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Met à jour un devis (statut, notes)."""
    devis = db.query(Devis).filter(Devis.id == devis_id).first()
    if not devis:
        raise HTTPException(status_code=404, detail="Devis non trouvé")

    for field, value in devis_data.model_dump(exclude_unset=True).items():
        setattr(devis, field, value)

    db.commit()
    db.refresh(devis)
    return {"message": "Devis mis à jour"}


@router.post("/api/devis/{devis_id}/convertir-rdv")
def convertir_devis_en_rdv(
    devis_id: int,
    date_rdv: date,
    heure_rdv: time,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Convertit un devis accepté en rendez-vous."""
    devis = db.query(Devis).filter(Devis.id == devis_id).first()
    if not devis:
        raise HTTPException(status_code=404, detail="Devis non trouvé")

    if devis.statut != "accepte":
        raise HTTPException(status_code=400, detail="Le devis doit être accepté avant conversion")

    types_intervention = ", ".join(
        ligne.designation for ligne in devis.lignes if ligne.type_ligne == "forfait_mo"
    )

    rdv = RendezVous(
        client_id=devis.client_id,
        vehicule_id=devis.vehicule_id,
        date_rdv=date_rdv,
        heure_rdv=heure_rdv,
        type_intervention=types_intervention or "Intervention diverses",
        prix_estime=devis.total_ttc,
        statut="confirme",
        commentaire=devis.notes_client,
        kilometrage=devis.kilometrage,
        atelier_id=getattr(current_user, "atelier_id", None) or 1,
    )
    db.add(rdv)
    db.flush()

    devis.rendez_vous_id = rdv.id
    devis.statut = "converti"

    db.commit()
    return {"message": "Devis converti en RDV", "rdv_id": rdv.id}


@router.delete("/api/devis/{devis_id}")
def delete_devis(
    devis_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Supprime un devis."""
    devis = db.query(Devis).filter(Devis.id == devis_id).first()
    if not devis:
        raise HTTPException(status_code=404, detail="Devis non trouvé")

    if devis.statut == "converti":
        raise HTTPException(status_code=400, detail="Impossible de supprimer un devis déjà converti")

    db.delete(devis)
    db.commit()
    return {"message": "Devis supprimé"}
