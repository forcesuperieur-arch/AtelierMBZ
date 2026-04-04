import json
from decimal import Decimal
from datetime import datetime

from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session

from auth import get_current_user
from models import DemandeTravauxSupp, OrdreReparation, RendezVous, User, get_db
from routes.auth_api import user_has_permission
from schemas.travaux_supp import DemandeTravauxSuppCreate, DemandeTravauxSuppUpdate

router = APIRouter(tags=["travaux-supplementaires"])


def _as_decimal(value) -> Decimal:
    if value in (None, ""):
        return Decimal("0")
    if isinstance(value, Decimal):
        return value
    try:
        return Decimal(str(value))
    except Exception:
        return Decimal("0")


def _parse_prestations_demandees(payload: str | None) -> tuple[list[dict], list[str]]:
    if not payload:
        return [], []
    try:
        prestations = json.loads(payload)
    except Exception:
        return [], []
    if not isinstance(prestations, list):
        return [], []

    noms: list[str] = []
    seen: set[str] = set()
    for prestation in prestations:
        if not isinstance(prestation, dict):
            continue
        nom = str(prestation.get("nom") or "").strip()
        if nom and nom.casefold() not in seen:
            seen.add(nom.casefold())
            noms.append(nom)
    return prestations, noms


def _append_unique_labels(base_value: str | None, new_labels: list[str]) -> str:
    parts = [part.strip() for part in str(base_value or "").split(",") if part and part.strip()]
    seen = {part.casefold() for part in parts}
    for label in new_labels:
        clean_label = str(label or "").strip()
        if clean_label and clean_label.casefold() not in seen:
            parts.append(clean_label)
            seen.add(clean_label.casefold())
    return ", ".join(parts)


def _append_note(base_value: str | None, note: str | None) -> str | None:
    current = str(base_value or "").strip()
    addition = str(note or "").strip()
    if not addition:
        return current or None
    if addition.casefold() in current.casefold():
        return current or addition
    return f"{current}\n{addition}".strip() if current else addition


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

    previous_status = demande.statut
    demande.statut = update.statut
    if update.notes_receptionniste is not None:
        demande.notes_receptionniste = update.notes_receptionniste
    if update.prix_estime is not None:
        demande.prix_estime = update.prix_estime
    if update.temps_estime is not None:
        demande.temps_estime = update.temps_estime
    demande.approved_by = current_user.id

    if update.statut == "approuve":
        now = datetime.now()
        demande.approved_at = now
        demande.decision_client = "accepte"
        demande.decision_client_at = now

        rdv = db.query(RendezVous).filter(RendezVous.id == demande.rendez_vous_id).first()
        _, noms_prestations = _parse_prestations_demandees(demande.prestations_demandees)

        travaux_parts = []
        if noms_prestations:
            travaux_parts.append(", ".join(noms_prestations))
        if demande.description:
            travaux_parts.append(str(demande.description).strip())
        if demande.notes_receptionniste:
            travaux_parts.append(f"Note reception: {str(demande.notes_receptionniste).strip()}")
        travaux_desc = ". ".join(part for part in travaux_parts if part).strip() or "Travaux complementaires approuves"

        year = rdv.date_rdv.year if rdv and rdv.date_rdv else datetime.now().year
        or_supp = db.query(OrdreReparation).filter(
            OrdreReparation.rendez_vous_id == demande.rendez_vous_id,
            OrdreReparation.type_or == "supplementaire",
            OrdreReparation.demande_travaux_supp_id == demande.id,
        ).order_by(OrdreReparation.created_at.desc()).first()

        if not or_supp:
            count_or = db.query(OrdreReparation).filter(
                OrdreReparation.rendez_vous_id == demande.rendez_vous_id,
                OrdreReparation.type_or == "supplementaire",
            ).count()
            or_supp = OrdreReparation(
                rendez_vous_id=demande.rendez_vous_id,
                numero_or="OR-" + str(year) + "-" + str(demande.rendez_vous_id).zfill(3) + "-S" + str(count_or + 1),
                type_or="supplementaire",
                travaux=travaux_desc,
                demande_travaux_supp_id=demande.id,
                signature_client=update.signature,
            )
            db.add(or_supp)
        else:
            or_supp.travaux = travaux_desc
            if update.signature:
                or_supp.signature_client = update.signature

        if rdv:
            if noms_prestations:
                rdv.type_intervention = _append_unique_labels(rdv.type_intervention, noms_prestations)

            if previous_status != "approuve":
                if demande.prix_estime is not None:
                    rdv.prix_estime = _as_decimal(rdv.prix_estime) + _as_decimal(demande.prix_estime)
                if demande.temps_estime is not None:
                    rdv.temps_estime = int(rdv.temps_estime or 0) + int(demande.temps_estime or 0)

            note_or = f"OR complementaire {or_supp.numero_or}: {travaux_desc}"
            rdv.commentaire = _append_note(rdv.commentaire, note_or)

            or_initial = db.query(OrdreReparation).filter(
                OrdreReparation.rendez_vous_id == demande.rendez_vous_id,
                OrdreReparation.type_or == "initial",
            ).order_by(OrdreReparation.created_at.desc()).first()
            if not or_initial:
                or_initial = OrdreReparation(
                    rendez_vous_id=demande.rendez_vous_id,
                    numero_or=f"OR-{year}-{str(demande.rendez_vous_id).zfill(3)}",
                    type_or="initial",
                )
                db.add(or_initial)

            or_initial.kilometrage = or_initial.kilometrage or (rdv.kilometrage if rdv else None)
            or_initial.etat_vehicule = or_initial.etat_vehicule or (rdv.etat_vehicule if rdv else None)
            or_initial.travaux = _append_note(or_initial.travaux or (rdv.commentaire if rdv else None), note_or)

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
