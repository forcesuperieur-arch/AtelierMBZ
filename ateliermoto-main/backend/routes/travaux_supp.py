import json
from datetime import datetime

from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session

from auth import get_current_user
from models import DemandeTravauxSupp, OrdreReparation, RendezVous, User, get_db
from routes.auth_api import user_has_permission
from schemas.travaux_supp import DemandeTravauxSuppCreate, DemandeTravauxSuppUpdate

router = APIRouter(tags=["travaux-supplementaires"])


@router.post("/api/rendez-vous/{rdv_id}/travaux-supplementaires")
def creer_demande_travaux_supp(
    rdv_id: int,
    demande: DemandeTravauxSuppCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    atelier_id = current_user.atelier_id or 1
    rdv = db.query(RendezVous).filter(
        RendezVous.id == rdv_id,
        RendezVous.atelier_id == atelier_id,
    ).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="Rendez-vous non trouve")
    if rdv.statut != "en_cours":
        raise HTTPException(status_code=400, detail="Le RDV doit etre en cours pour signaler des travaux supplementaires")

    nouvelle_demande = DemandeTravauxSupp(
        rendez_vous_id=rdv_id,
        description=demande.description or "",
        prestations_demandees=json.dumps(demande.prestations_demandees) if demande.prestations_demandees else None,
        urgence=demande.urgence or "normal",
    )
    db.add(nouvelle_demande)
    db.commit()
    db.refresh(nouvelle_demande)
    return {"message": "Demande creee", "id": nouvelle_demande.id}


@router.get("/api/travaux-supplementaires/en-attente")
def get_demandes_en_attente(
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    if not user_has_permission(current_user, db, "travaux_supp.review"):
        raise HTTPException(status_code=403, detail="Acces refuse")

    atelier_id = current_user.atelier_id or 1
    demandes = db.query(DemandeTravauxSupp).filter(DemandeTravauxSupp.statut == "en_attente").all()
    result = []
    for demande in demandes:
        rdv = db.query(RendezVous).filter(
            RendezVous.id == demande.rendez_vous_id,
            RendezVous.atelier_id == atelier_id,
        ).first()
        if not rdv:
            continue

        client = rdv.client if rdv else None
        vehicule = rdv.vehicule if rdv else None
        result.append(
            {
                "id": demande.id,
                "rendez_vous_id": demande.rendez_vous_id,
                "description": demande.description,
                "prestations_demandees": json.loads(demande.prestations_demandees) if demande.prestations_demandees else [],
                "temps_estime": demande.temps_estime,
                "prix_estime": demande.prix_estime,
                "urgence": demande.urgence,
                "statut": demande.statut,
                "created_at": demande.created_at.isoformat() if demande.created_at else None,
                "client": {"nom": client.nom, "prenom": client.prenom, "telephone": client.telephone} if client else None,
                "vehicule": {"plaque": vehicule.plaque, "marque": vehicule.marque, "modele": vehicule.modele} if vehicule else None,
                "or_numero": "OR-" + str(demande.rendez_vous_id).zfill(6),
            }
        )
    return result


@router.put("/api/travaux-supplementaires/{demande_id}")
def traiter_demande_travaux_supp(
    demande_id: int,
    update: DemandeTravauxSuppUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    demande = db.query(DemandeTravauxSupp).filter(DemandeTravauxSupp.id == demande_id).first()
    if not demande:
        raise HTTPException(status_code=404, detail="Demande non trouvee")

    demande.statut = update.statut
    if update.notes_receptionniste:
        demande.notes_receptionniste = update.notes_receptionniste
    if update.prix_estime is not None:
        demande.prix_estime = update.prix_estime
    if update.temps_estime is not None:
        demande.temps_estime = update.temps_estime
    demande.approved_by = current_user.id

    if update.statut == "approuve":
        demande.approved_at = datetime.now()
        rdv = db.query(RendezVous).filter(RendezVous.id == demande.rendez_vous_id).first()
        count_or = db.query(OrdreReparation).filter(
            OrdreReparation.rendez_vous_id == demande.rendez_vous_id,
            OrdreReparation.type_or == "supplementaire",
        ).count()
        year = datetime.now().year
        travaux_desc = demande.description or ""
        if demande.prestations_demandees:
            try:
                prestations = json.loads(demande.prestations_demandees)
                noms = [presta.get("nom", "") for presta in prestations if presta.get("nom")]
                if noms:
                    travaux_desc = ", ".join(noms) + (". " + travaux_desc if travaux_desc else "")
            except Exception:
                pass

        or_supp = OrdreReparation(
            rendez_vous_id=demande.rendez_vous_id,
            numero_or="OR-" + str(year) + "-" + str(demande.rendez_vous_id).zfill(3) + "-S" + str(count_or + 1),
            type_or="supplementaire",
            travaux=travaux_desc,
            demande_travaux_supp_id=demande.id,
            signature_client=update.signature,
        )
        db.add(or_supp)
        if rdv and demande.prix_estime:
            rdv.prix_estime = (rdv.prix_estime or 0) + demande.prix_estime
        if rdv and demande.temps_estime:
            rdv.temps_estime = (rdv.temps_estime or 0) + demande.temps_estime

    db.commit()
    return {"message": "Demande " + update.statut, "id": demande_id}


@router.get("/api/rendez-vous/{rdv_id}/travaux-supplementaires")
def get_travaux_supp_rdv(
    rdv_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    demandes = db.query(DemandeTravauxSupp).filter(
        DemandeTravauxSupp.rendez_vous_id == rdv_id
    ).order_by(DemandeTravauxSupp.created_at.desc()).all()
    return [
        {
            "id": demande.id,
            "description": demande.description,
            "temps_estime": demande.temps_estime,
            "prix_estime": demande.prix_estime,
            "urgence": demande.urgence,
            "statut": demande.statut,
            "notes_receptionniste": demande.notes_receptionniste,
            "created_at": demande.created_at.isoformat() if demande.created_at else None,
            "approved_at": demande.approved_at.isoformat() if demande.approved_at else None,
        }
        for demande in demandes
    ]


@router.get("/api/rendez-vous/{rdv_id}/ordres-reparation-archives")
def get_ordres_reparation_archives(
    rdv_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    ordres = db.query(OrdreReparation).filter(
        OrdreReparation.rendez_vous_id == rdv_id
    ).order_by(OrdreReparation.created_at).all()
    return [
        {
            "id": ordre.id,
            "numero_or": ordre.numero_or,
            "type_or": ordre.type_or,
            "kilometrage": ordre.kilometrage,
            "etat_vehicule": ordre.etat_vehicule,
            "travaux": ordre.travaux,
            "created_at": ordre.created_at.isoformat() if ordre.created_at else None,
        }
        for ordre in ordres
    ]


@router.post("/api/rendez-vous/{rdv_id}/reception")
def reception_vehicule(
    rdv_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Passe le RDV en statut `reception` et crée l'OR initial."""
    if not user_has_permission(current_user, db, "rdv.edit"):
        raise HTTPException(status_code=403, detail="Permission rdv.edit requise")

    atelier_id = current_user.atelier_id or 1
    rdv = db.query(RendezVous).filter(
        RendezVous.id == rdv_id,
        RendezVous.atelier_id == atelier_id,
    ).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="Rendez-vous non trouve")
    if rdv.statut not in {"reserve", "confirme", "reception"}:
        raise HTTPException(status_code=400, detail="Statut incompatible avec la reception du vehicule")

    year = rdv.date_rdv.year if rdv.date_rdv else datetime.now().year
    numero_or = f"OR-{year}-{str(rdv_id).zfill(3)}"
    or_initial = db.query(OrdreReparation).filter(
        OrdreReparation.rendez_vous_id == rdv_id,
        OrdreReparation.type_or == "initial",
    ).order_by(OrdreReparation.created_at.desc()).first()
    if not or_initial:
        or_initial = OrdreReparation(
            rendez_vous_id=rdv_id,
            numero_or=numero_or,
            type_or="initial",
        )
        db.add(or_initial)

    or_initial.kilometrage = rdv.kilometrage
    or_initial.etat_vehicule = rdv.etat_vehicule
    or_initial.travaux = rdv.commentaire
    if not or_initial.signature_client:
        raise HTTPException(status_code=400, detail="Signature client obligatoire avant validation de la reception")

    rdv.statut = "reception"
    db.commit()
    return {"message": "Reception validee et OR cree", "id": rdv_id, "statut": "reception"}
