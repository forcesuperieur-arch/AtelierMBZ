"""Routes publiques de suivi RDV client (sans authentification)."""

import json
from datetime import datetime, timedelta

from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session, joinedload

from models import (
    Atelier, DemandeTravauxSupp, Mecanicien, PhotoIntervention,
    RapportTechnicien, RendezVous, get_db,
)

router = APIRouter(tags=["suivi-client"])


@router.get("/api/suivi/{token}")
def get_suivi_rdv(token: str, db: Session = Depends(get_db)):
    """Retourne les informations publiques d'un RDV via son token de suivi."""
    rdv = (
        db.query(RendezVous)
        .options(
            joinedload(RendezVous.client),
            joinedload(RendezVous.vehicule),
            joinedload(RendezVous.photos_intervention),
        )
        .filter(RendezVous.token_suivi == token)
        .first()
    )
    if not rdv:
        raise HTTPException(status_code=404, detail="Rendez-vous non trouvé")

    atelier = db.query(Atelier).filter(Atelier.id == rdv.atelier_id).first()

    # Mécanicien
    mecanicien_nom = None
    if rdv.mecanicien_id:
        mec = db.query(Mecanicien).filter(Mecanicien.id == rdv.mecanicien_id).first()
        if mec:
            mecanicien_nom = f"{mec.prenom or ''} {mec.nom or ''}".strip() or None

    # Rapport technicien
    rapport = db.query(RapportTechnicien).filter(
        RapportTechnicien.rendez_vous_id == rdv.id
    ).first()
    rapport_data = None
    if rapport:
        rapport_data = {
            "travaux_realises": rapport.travaux_realises,
            "recommandations": rapport.recommandations,
            "statut": rapport.statut,
        }

    # Travaux supplémentaires
    demandes = db.query(DemandeTravauxSupp).filter(
        DemandeTravauxSupp.rendez_vous_id == rdv.id
    ).all()
    travaux_supp = []
    for d in demandes:
        travaux_supp.append({
            "description": d.description,
            "urgence": d.urgence,
            "prix_estime": float(d.prix_estime) if d.prix_estime else None,
            "statut": d.statut,
            "decision_client": d.decision_client,
        })

    photos = []
    for p in (rdv.photos_intervention or []):
        photos.append({
            "id": p.id,
            "filename": p.filename,
            "description": p.description,
            "url": f"/api/photos/{p.id}/image",
            "created_at": p.created_at.isoformat() if p.created_at else None,
        })

    can_cancel = False
    if rdv.statut in ("en_attente", "confirme", "reserve"):
        rdv_datetime = datetime.combine(rdv.date_rdv, rdv.heure_rdv)
        can_cancel = rdv_datetime > datetime.now() + timedelta(hours=48)

    return {
        "id": rdv.id,
        "date_rdv": rdv.date_rdv.isoformat() if rdv.date_rdv else None,
        "heure_rdv": str(rdv.heure_rdv)[:5] if rdv.heure_rdv else None,
        "type_intervention": rdv.type_intervention,
        "statut": rdv.statut,
        "commentaire": rdv.commentaire,
        "prix_estime": float(rdv.prix_estime) if rdv.prix_estime else None,
        "kilometrage": rdv.kilometrage,
        "mecanicien": mecanicien_nom,
        "client": {
            "prenom": rdv.client.prenom if rdv.client else None,
            "nom": rdv.client.nom if rdv.client else None,
        },
        "vehicule": {
            "plaque": rdv.vehicule.plaque if rdv.vehicule else None,
            "marque": rdv.vehicule.marque if rdv.vehicule else None,
            "modele": rdv.vehicule.modele if rdv.vehicule else None,
        },
        "atelier": {
            "nom": atelier.nom if atelier else "Atelier",
            "telephone": atelier.telephone if atelier else None,
            "adresse": atelier.adresse if atelier else None,
            "ville": atelier.ville if atelier else None,
        },
        "rapport": rapport_data,
        "travaux_supplementaires": travaux_supp,
        "photos": photos,
        "can_cancel": can_cancel,
    }


@router.post("/api/suivi/{token}/annuler")
def annuler_rdv_suivi(token: str, db: Session = Depends(get_db)):
    """Permet au client d'annuler son RDV (si > 48h avant la date)."""
    rdv = db.query(RendezVous).filter(RendezVous.token_suivi == token).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="Rendez-vous non trouvé")

    if rdv.statut not in ("en_attente", "confirme", "reserve"):
        raise HTTPException(status_code=400, detail="Ce rendez-vous ne peut plus être annulé")

    rdv_datetime = datetime.combine(rdv.date_rdv, rdv.heure_rdv)
    if rdv_datetime <= datetime.now() + timedelta(hours=48):
        raise HTTPException(
            status_code=400,
            detail="L'annulation n'est possible que 48h avant le rendez-vous",
        )

    rdv.statut = "annule"
    db.commit()
    try:
        from services.notification_service import notifier_changement_statut
        notifier_changement_statut(db, rdv.id, "annule")
    except Exception:
        pass
    return {"message": "Rendez-vous annulé avec succès"}
