from datetime import date, datetime, time, timedelta
import json
import logging

from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session

from auth import get_current_user
from models import Client, DemandeTravauxSupp, HoraireAtelier, Mecanicien, OrdreReparation, Pont, Prestation, RapportTechnicien, RendezVous, RolePermission, User, Vehicule, get_db
from schemas.rendez_vous import (
    OrdreReparationSave,
    RapportTechnicienCreate,
    RapportTechnicienResponse,
    RendezVousCreate,
    RendezVousUpdate,
)
from services.pdf_service import generate_facture_pdf, generate_ordre_reparation_pdf

logger = logging.getLogger("ateliermoto.api")
router = APIRouter(tags=["rendez-vous"])


def _atelier_id_or_403(current_user: User) -> int:
    # Compatibilite temporaire: users legacy sans atelier_id -> atelier par defaut
    return int(getattr(current_user, "atelier_id", None) or 1)


def _user_has_permission(current_user: User, db: Session, permission: str) -> bool:
    if current_user.role == "super_admin":
        return True
    rp = db.query(RolePermission).filter(RolePermission.role == (current_user.role or "")).first()
    if rp:
        try:
            perms = json.loads(rp.permissions_json or "[]")
        except Exception:
            perms = []
        return permission in perms
    if permission == "rdv.edit" and current_user.role in {"admin", "receptionnaire", "service_client"}:
        return True
    return False


def _rdv_start_end(rdv: RendezVous) -> tuple[datetime, datetime]:
    start_dt = datetime.combine(rdv.date_rdv, rdv.heure_rdv or time(9, 0))
    duration_min = int(rdv.temps_estime or 60)
    if duration_min <= 0:
        duration_min = 60
    end_dt = start_dt + timedelta(minutes=duration_min)
    return start_dt, end_dt


def _validate_no_conflict(db: Session, rdv: RendezVous, atelier_id: int) -> None:
    if rdv.statut in {"annule", "non_presente"}:
        return
    if not rdv.date_rdv or not (rdv.pont_id or rdv.mecanicien_id):
        return

    candidates = db.query(RendezVous).filter(
        RendezVous.atelier_id == atelier_id,
        RendezVous.id != rdv.id,
        RendezVous.date_rdv == rdv.date_rdv,
        RendezVous.statut.notin_(["annule", "non_presente", "termine", "facture", "paye"]),
    )
    if rdv.pont_id and rdv.mecanicien_id:
        candidates = candidates.filter(
            (RendezVous.pont_id == rdv.pont_id) | (RendezVous.mecanicien_id == rdv.mecanicien_id)
        )
    elif rdv.pont_id:
        candidates = candidates.filter(RendezVous.pont_id == rdv.pont_id)
    else:
        candidates = candidates.filter(RendezVous.mecanicien_id == rdv.mecanicien_id)

    my_start, my_end = _rdv_start_end(rdv)
    for other in candidates.all():
        other_start, other_end = _rdv_start_end(other)
        overlap = my_start < other_end and my_end > other_start
        if overlap:
            resource = "pont" if (rdv.pont_id and other.pont_id == rdv.pont_id) else "technicien"
            raise HTTPException(
                status_code=409,
                detail=(
                    f"Conflit planning: {resource} deja occupe entre "
                    f"{my_start.strftime('%H:%M')} et {my_end.strftime('%H:%M')}"
                ),
            )


def _time_in_open_hours(
    db: Session,
    atelier_id: int,
    rdv_date: date,
    rdv_time: time | None,
    duration_min: int | None = None,
) -> bool:
    if not rdv_time:
        return True
    # 0=lundi ... 6=dimanche (Python identique)
    weekday = rdv_date.weekday()
    horaire = db.query(HoraireAtelier).filter(
        HoraireAtelier.atelier_id == atelier_id,
        HoraireAtelier.jour_semaine == weekday
    ).first()
    if not horaire:
        return True
    if not horaire.is_ouvert:
        return False
    if not horaire.heure_ouverture or not horaire.heure_fermeture:
        return True

    def _to_minutes(hhmm: str) -> int:
        h, m = hhmm.split(":")
        return int(h) * 60 + int(m)

    slot = rdv_time.hour * 60 + rdv_time.minute
    open_min = _to_minutes(horaire.heure_ouverture)
    close_min = _to_minutes(horaire.heure_fermeture)
    if slot < open_min or slot >= close_min:
        return False

    if horaire.pause_debut and horaire.pause_fin:
        pause_start = _to_minutes(horaire.pause_debut)
        pause_end = _to_minutes(horaire.pause_fin)
        if pause_start <= slot < pause_end:
            return False
    if duration_min and duration_min > 0:
        end_slot = slot + int(duration_min)
        # On autorise un "split" sur la pause midi, mais jamais après fermeture.
        if end_slot > close_min:
            return False

    return True


# ========== RENDEZ-VOUS ==========

@router.post("/api/rendez-vous")
def create_rendez_vous(rdv: RendezVousCreate, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    """Crée un nouveau rendez-vous avec client et véhicule"""
    
    # Créer ou récupérer le client
    atelier_id = _atelier_id_or_403(current_user)
    client_db = db.query(Client).filter(Client.telephone == rdv.client.telephone, Client.atelier_id == atelier_id).first()
    if not client_db:
        client_db = Client(**rdv.client.dict(), atelier_id=atelier_id)
        db.add(client_db)
        db.flush()
    
    # Créer ou récupérer le véhicule
    vehicule_db = db.query(Vehicule).filter(
        Vehicule.plaque == rdv.vehicule.plaque.upper().replace(" ", "").replace("-", ""),
        Vehicule.atelier_id == atelier_id
    ).first()
    
    if not vehicule_db:
        vehicule_db = Vehicule(**rdv.vehicule.dict(), client_id=client_db.id, atelier_id=atelier_id)
        db.add(vehicule_db)
        db.flush()
    else:
        # Mettre a jour type_moto si manquant
        if not vehicule_db.type_moto and rdv.vehicule.type_moto:
            vehicule_db.type_moto = rdv.vehicule.type_moto
        if not vehicule_db.client_id:
            vehicule_db.client_id = client_db.id
    
    # Récupérer le prix estimé depuis Prestation
    prestation = db.query(Prestation).filter(Prestation.nom == rdv.type_intervention, Prestation.atelier_id == atelier_id).first()

    # Créer le rendez-vous
    new_rdv = RendezVous(
        client_id=client_db.id,
        vehicule_id=vehicule_db.id,
        date_rdv=rdv.date_rdv,
        heure_rdv=rdv.heure_rdv,
        type_intervention=rdv.type_intervention,
        commentaire=rdv.commentaire,
        prix_estime=prestation.prix_base_ttc if prestation else None,
        temps_estime=prestation.temps_estime_minutes if prestation else None,
        statut="reserve",
        atelier_id=atelier_id
    )
    
    db.add(new_rdv)
    db.flush()
    if not _time_in_open_hours(db, atelier_id, new_rdv.date_rdv, new_rdv.heure_rdv, new_rdv.temps_estime):
        raise HTTPException(status_code=400, detail="Creneau en dehors des horaires d'ouverture")
    _validate_no_conflict(db, new_rdv, atelier_id)
    db.commit()
    db.refresh(new_rdv)
    
    return {
        "id": new_rdv.id,
        "client": {"nom": client_db.nom, "prenom": client_db.prenom, "telephone": client_db.telephone},
        "vehicule": {"plaque": vehicule_db.plaque, "marque": vehicule_db.marque, "modele": vehicule_db.modele},
        "date_rdv": new_rdv.date_rdv,
        "heure_rdv": new_rdv.heure_rdv,
        "type_intervention": new_rdv.type_intervention,
        "prix_estime": new_rdv.prix_estime,
        "temps_estime": new_rdv.temps_estime,
        "statut": new_rdv.statut
    }

@router.get("/api/rendez-vous")
def get_all_rendez_vous(skip: int = 0, limit: int = 100, date: str = None, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    """Récupère tous les rendez-vous (protégé par auth). ?date=YYYY-MM-DD filtre par jour."""
    from typing import Optional as _Opt
    atelier_id = _atelier_id_or_403(current_user)
    q = db.query(RendezVous).filter(RendezVous.atelier_id == atelier_id)
    if date:
        from datetime import date as _date_type
        try:
            filter_date = _date_type.fromisoformat(date)
            q = q.filter(RendezVous.date_rdv == filter_date)
        except (ValueError, TypeError):
            pass
    rdvs = q.order_by(RendezVous.date_rdv.desc()).offset(skip).limit(limit).all()
    
    result = []
    for rdv in rdvs:
        ors = [{"id": o.id, "numero_or": o.numero_or, "type_or": o.type_or, "travaux": o.travaux} for o in rdv.ordres_reparation] if rdv.ordres_reparation else []
        result.append({
            "id": rdv.id,
            "client": {"nom": rdv.client.nom, "prenom": rdv.client.prenom, "telephone": rdv.client.telephone},
            "vehicule": {"plaque": rdv.vehicule.plaque, "marque": rdv.vehicule.marque, "modele": rdv.vehicule.modele},
            "date_rdv": rdv.date_rdv,
            "heure_rdv": rdv.heure_rdv,
            "type_intervention": rdv.type_intervention,
            "prix_estime": rdv.prix_estime,
            "temps_estime": rdv.temps_estime,
            "mecanicien_id": rdv.mecanicien_id,
            "pont_id": rdv.pont_id,
            "notes": rdv.commentaire,
            "statut": rdv.statut,
            "heure_debut_travail": rdv.heure_debut_travail.isoformat() if rdv.heure_debut_travail else None,
            "heure_fin_travail": rdv.heure_fin_travail.isoformat() if rdv.heure_fin_travail else None,
            "temps_effectif_minutes": rdv.temps_effectif_minutes,
            "ordres_reparation": ors
        })
    return result

@router.get("/api/rendez-vous/{rdv_id}")
def get_rendez_vous(rdv_id: int, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    """Récupère un rendez-vous par son ID"""
    atelier_id = _atelier_id_or_403(current_user)
    rdv = db.query(RendezVous).filter(RendezVous.id == rdv_id, RendezVous.atelier_id == atelier_id).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="Rendez-vous non trouvé")
    
    ors = [{"id": o.id, "numero_or": o.numero_or, "type_or": o.type_or, "travaux": o.travaux} for o in rdv.ordres_reparation] if rdv.ordres_reparation else []
    return {
        "id": rdv.id,
        "client": {"id": rdv.client.id, "nom": rdv.client.nom, "prenom": rdv.client.prenom, "telephone": rdv.client.telephone, "email": rdv.client.email},
        "vehicule": {"id": rdv.vehicule.id, "plaque": rdv.vehicule.plaque, "marque": rdv.vehicule.marque, "modele": rdv.vehicule.modele, "annee": rdv.vehicule.annee, "cylindree": rdv.vehicule.cylindree, "type_moto": rdv.vehicule.type_moto},
        "date_rdv": rdv.date_rdv,
        "heure_rdv": rdv.heure_rdv,
        "type_intervention": rdv.type_intervention,
        "commentaire": rdv.commentaire,
        "prix_estime": rdv.prix_estime,
        "prix_final": rdv.prix_final,
        "temps_estime": rdv.temps_estime,
        "kilometrage": rdv.kilometrage,
        "etat_vehicule": rdv.etat_vehicule,
        "mecanicien_id": rdv.mecanicien_id,
        "pont_id": rdv.pont_id,
        "notes": rdv.commentaire,
        "statut": rdv.statut,
        "created_at": rdv.created_at,
        "ordres_reparation": ors
    }

@router.put("/api/rendez-vous/{rdv_id}")
def update_rendez_vous(rdv_id: int, update_data: RendezVousUpdate, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    """Met à jour un rendez-vous (statut, kilométrage, état, prix, pont, mecanicien)"""
    if not _user_has_permission(current_user, db, "rdv.edit"):
        raise HTTPException(status_code=403, detail="Permission rdv.edit requise")
    atelier_id = _atelier_id_or_403(current_user)
    rdv = db.query(RendezVous).filter(RendezVous.id == rdv_id, RendezVous.atelier_id == atelier_id).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="Rendez-vous non trouvé")
    
    previous_status = rdv.statut

    if update_data.statut:
        rdv.statut = update_data.statut
    if update_data.kilometrage is not None:
        rdv.kilometrage = update_data.kilometrage
    if update_data.etat_vehicule:
        rdv.etat_vehicule = update_data.etat_vehicule
    if update_data.prix_final is not None:
        rdv.prix_final = update_data.prix_final
    if update_data.temps_final is not None:
        rdv.temps_final = update_data.temps_final
    if update_data.commentaire is not None:
        rdv.commentaire = update_data.commentaire
    if update_data.pont_id is not None:
        if update_data.pont_id:
            pont = db.query(Pont).filter(Pont.id == update_data.pont_id, Pont.atelier_id == atelier_id).first()
            if not pont:
                raise HTTPException(status_code=400, detail="Pont invalide pour cet atelier")
            rdv.pont_id = pont.id
            if rdv.mecanicien_id is None and pont.mecanicien_id:
                rdv.mecanicien_id = pont.mecanicien_id
        else:
            rdv.pont_id = None
    if update_data.mecanicien_id is not None:
        if update_data.mecanicien_id:
            mec = db.query(Mecanicien).filter(
                Mecanicien.id == update_data.mecanicien_id,
                Mecanicien.atelier_id == atelier_id
            ).first()
            if not mec:
                raise HTTPException(status_code=400, detail="Technicien invalide pour cet atelier")
            rdv.mecanicien_id = mec.id
            # Source de vérité: technicien -> pont. On ne recalcule pas pour les RDV déjà finalisés.
            if rdv.statut not in {"termine", "facture", "paye"}:
                pont_du_meca = db.query(Pont).filter(
                    Pont.atelier_id == atelier_id,
                    Pont.mecanicien_id == mec.id,
                    Pont.is_active == 1
                ).first()
                if not pont_du_meca:
                    raise HTTPException(status_code=400, detail="Ce technicien n'est affecte a aucun pont actif")
                rdv.pont_id = pont_du_meca.id
        else:
            rdv.mecanicien_id = None
            if rdv.statut not in {"termine", "facture", "paye"}:
                rdv.pont_id = None
    if update_data.heure_rdv:
        from datetime import datetime
        try:
            rdv.heure_rdv = datetime.strptime(update_data.heure_rdv, "%H:%M").time()
        except ValueError:
            logger.warning("Invalid heure_rdv format for rdv_id=%s value=%s", rdv_id, update_data.heure_rdv)
            raise HTTPException(status_code=400, detail="Format heure_rdv invalide (HH:MM attendu)")
    if update_data.date_rdv:
        from datetime import datetime
        try:
            rdv.date_rdv = datetime.strptime(update_data.date_rdv, "%Y-%m-%d").date()
        except ValueError:
            logger.warning("Invalid date_rdv format for rdv_id=%s value=%s", rdv_id, update_data.date_rdv)
            raise HTTPException(status_code=400, detail="Format date_rdv invalide (YYYY-MM-DD attendu)")
    
    _validate_no_conflict(db, rdv, atelier_id)
    if not _time_in_open_hours(db, atelier_id, rdv.date_rdv, rdv.heure_rdv, rdv.temps_estime):
        raise HTTPException(status_code=400, detail="Creneau en dehors des horaires d'ouverture")

    db.commit()
    db.refresh(rdv)
    if previous_status != "confirme" and rdv.statut == "confirme":
        logger.info(
            "TODO email confirmation client: rdv_id=%s atelier_id=%s",
            rdv.id,
            atelier_id,
        )
    logger.info("RDV updated id=%s by user=%s", rdv.id, current_user.username)
    return {"message": "Rendez-vous mis à jour", "id": rdv.id}

@router.delete("/api/rendez-vous/{rdv_id}")
def delete_rendez_vous(rdv_id: int, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    """Supprime un rendez-vous"""
    atelier_id = _atelier_id_or_403(current_user)
    rdv = db.query(RendezVous).filter(RendezVous.id == rdv_id, RendezVous.atelier_id == atelier_id).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="Rendez-vous non trouvé")
    
    db.delete(rdv)
    db.commit()
    return {"message": "Rendez-vous supprimé"}

# ========== SUIVI TEMPS DE TRAVAIL (INTERNE ATELIER) ==========

@router.post("/api/rendez-vous/{rdv_id}/demarrer-travail")
def demarrer_travail(rdv_id: int, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    """Démarre le chronomètre de travail sur un RDV"""
    atelier_id = _atelier_id_or_403(current_user)
    rdv = db.query(RendezVous).filter(RendezVous.id == rdv_id, RendezVous.atelier_id == atelier_id).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="Rendez-vous non trouvé")
    
    from datetime import datetime
    rdv.heure_debut_travail = datetime.now()
    rdv.heure_fin_travail = None
    rdv.temps_effectif_minutes = None
    rdv.statut = "en_cours"
    
    db.commit()
    db.refresh(rdv)
    return {
        "message": "Travail démarré",
        "heure_debut": rdv.heure_debut_travail.isoformat(),
        "statut": rdv.statut
    }

@router.post("/api/rendez-vous/{rdv_id}/terminer-travail")
def terminer_travail(rdv_id: int, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    """Termine le chronomètre et calcule le temps effectif"""
    atelier_id = _atelier_id_or_403(current_user)
    rdv = db.query(RendezVous).filter(RendezVous.id == rdv_id, RendezVous.atelier_id == atelier_id).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="Rendez-vous non trouvé")
    
    if not rdv.heure_debut_travail:
        raise HTTPException(status_code=400, detail="Le travail n'a pas été démarré")
    
    from datetime import datetime
    rdv.heure_fin_travail = datetime.now()
    
    # Calcul du temps effectif en minutes
    delta = rdv.heure_fin_travail - rdv.heure_debut_travail
    rdv.temps_effectif_minutes = int(delta.total_seconds() / 60)
    rdv.temps_final = rdv.temps_effectif_minutes  # Pour la facturation
    rdv.statut = "termine"
    
    db.commit()
    db.refresh(rdv)
    return {
        "message": "Travail terminé",
        "heure_debut": rdv.heure_debut_travail.isoformat(),
        "heure_fin": rdv.heure_fin_travail.isoformat(),
        "temps_effectif_minutes": rdv.temps_effectif_minutes,
        "statut": rdv.statut
    }

@router.get("/api/rendez-vous/{rdv_id}/temps-travail")
def get_temps_travail(rdv_id: int, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    """Récupère les infos de temps de travail (interne atelier)"""
    atelier_id = _atelier_id_or_403(current_user)
    rdv = db.query(RendezVous).filter(RendezVous.id == rdv_id, RendezVous.atelier_id == atelier_id).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="Rendez-vous non trouvé")
    
    return {
        "heure_debut_travail": rdv.heure_debut_travail.isoformat() if rdv.heure_debut_travail else None,
        "heure_fin_travail": rdv.heure_fin_travail.isoformat() if rdv.heure_fin_travail else None,
        "temps_effectif_minutes": rdv.temps_effectif_minutes,
        "en_cours": rdv.heure_debut_travail is not None and rdv.heure_fin_travail is None
    }

# ========== ORDRE DE RÉPARATION PDF V3 - DESIGN PRO ==========

@router.get("/api/rendez-vous/{rdv_id}/ordre-reparation")
def generate_ordre_reparation(rdv_id: int, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    """Génère un ordre de réparation au format PDF avec design professionnel"""
    atelier_id = _atelier_id_or_403(current_user)
    rdv = db.query(RendezVous).filter(RendezVous.id == rdv_id, RendezVous.atelier_id == atelier_id).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="Rendez-vous non trouvé")
    return generate_ordre_reparation_pdf(rdv_id, db)


@router.post("/api/rendez-vous/{rdv_id}/ordre-reparation/save")
def save_ordre_reparation(
    rdv_id: int,
    or_data: OrdreReparationSave,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Sauvegarde les données de l'ordre de réparation"""
    atelier_id = _atelier_id_or_403(current_user)
    rdv = db.query(RendezVous).filter(RendezVous.id == rdv_id, RendezVous.atelier_id == atelier_id).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="Rendez-vous non trouvé")
    
    # Mettre à jour les champs
    rdv.kilometrage = or_data.kilometrage
    rdv.etat_vehicule = or_data.etat_vehicule
    if or_data.travaux:
        rdv.commentaire = or_data.travaux
    
    # Historiser l'OR initial en base (idempotent)
    year = rdv.date_rdv.year if rdv.date_rdv else datetime.now().year
    numero_or = f"OR-{year}-{str(rdv_id).zfill(3)}"
    or_initial = db.query(OrdreReparation).filter(
        OrdreReparation.rendez_vous_id == rdv_id,
        OrdreReparation.type_or == "initial"
    ).order_by(OrdreReparation.created_at.desc()).first()
    if not or_initial:
        or_initial = OrdreReparation(
            rendez_vous_id=rdv_id,
            numero_or=numero_or,
            type_or="initial",
        )
        db.add(or_initial)

    or_initial.kilometrage = or_data.kilometrage
    or_initial.etat_vehicule = or_data.etat_vehicule
    or_initial.travaux = or_data.travaux

    # Sauvegarder la signature si fournie (base + fichier legacy)
    if or_data.signature:
        or_initial.signature_client = or_data.signature
        import base64
        from pathlib import Path
        
        # Créer le dossier signatures s'il n'existe pas
        signatures_dir = Path("signatures")
        signatures_dir.mkdir(exist_ok=True)
        
        # Sauvegarder l'image
        signature_path = signatures_dir / f"rdv_{rdv_id}_signature.png"
        image_data = base64.b64decode(or_data.signature.split(',')[1])
        with open(signature_path, 'wb') as f:
            f.write(image_data)
    
    db.commit()
    db.refresh(rdv)
    
    return {
        "message": "Ordre de réparation sauvegardé",
        "id": rdv.id,
        "or_signe": True
    }

@router.get("/api/rendez-vous/{rdv_id}/signature")
def get_signature(rdv_id: int, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    """Récupère la signature d'un RDV s'il en existe une"""
    atelier_id = _atelier_id_or_403(current_user)
    rdv = db.query(RendezVous).filter(RendezVous.id == rdv_id, RendezVous.atelier_id == atelier_id).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="Rendez-vous non trouvé")
    # Source principale: OR historise en base
    or_initial = db.query(OrdreReparation).filter(
        OrdreReparation.rendez_vous_id == rdv_id,
        OrdreReparation.type_or == "initial"
    ).order_by(OrdreReparation.created_at.desc()).first()
    if or_initial and or_initial.signature_client:
        return {"signature": or_initial.signature_client}

    # Fallback legacy: fichier
    from pathlib import Path
    import base64
    signature_path = Path("signatures") / f"rdv_{rdv_id}_signature.png"
    if not signature_path.exists():
        raise HTTPException(status_code=404, detail="Signature non trouvée")
    with open(signature_path, 'rb') as f:
        image_data = base64.b64encode(f.read()).decode('utf-8')
    return {"signature": f"data:image/png;base64,{image_data}"}

# ========== RAPPORTS TECHNICIEN ==========

@router.get("/api/rendez-vous/{rdv_id}/rapport-technicien", response_model=RapportTechnicienResponse)
def get_rapport_technicien(rdv_id: int, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    """Récupère le rapport technicien d'un RDV"""
    atelier_id = _atelier_id_or_403(current_user)
    rdv = db.query(RendezVous).filter(RendezVous.id == rdv_id, RendezVous.atelier_id == atelier_id).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="Rendez-vous non trouvé")
    rapport = db.query(RapportTechnicien).filter(RapportTechnicien.rendez_vous_id == rdv_id).first()
    if not rapport:
        raise HTTPException(status_code=404, detail="Rapport non trouvé")
    
    import json
    return {
        "id": rapport.id,
        "rendez_vous_id": rapport.rendez_vous_id,
        "points_controle": json.loads(rapport.points_controle) if rapport.points_controle else {},
        "alertes": rapport.alertes,
        "recommandations": rapport.recommandations,
        "travaux_realises": rapport.travaux_realises,
        "pieces_utilisees": json.loads(rapport.pieces_utilisees) if rapport.pieces_utilisees else [],
        "statut": rapport.statut,
        "date_debut": rapport.date_debut,
        "date_fin": rapport.date_fin
    }

@router.post("/api/rendez-vous/{rdv_id}/rapport-technicien", response_model=RapportTechnicienResponse)
def create_or_update_rapport_technicien(
    rdv_id: int, 
    rapport_data: RapportTechnicienCreate, 
    db: Session = Depends(get_db), 
    current_user: User = Depends(get_current_user)
):
    """Crée ou met à jour le rapport technicien d'un RDV"""
    import json
    from datetime import datetime
    
    # Vérifier que le RDV existe
    atelier_id = _atelier_id_or_403(current_user)
    rdv = db.query(RendezVous).filter(RendezVous.id == rdv_id, RendezVous.atelier_id == atelier_id).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="Rendez-vous non trouvé")
    
    # Chercher un rapport existant
    rapport = db.query(RapportTechnicien).filter(RapportTechnicien.rendez_vous_id == rdv_id).first()
    
    if not rapport:
        # Créer un nouveau rapport
        rapport = RapportTechnicien(
            rendez_vous_id=rdv_id,
            points_controle=json.dumps(rapport_data.points_controle) if rapport_data.points_controle else "{}",
            alertes=rapport_data.alertes,
            recommandations=rapport_data.recommandations,
            travaux_realises=rapport_data.travaux_realises,
            pieces_utilisees=json.dumps(rapport_data.pieces_utilisees) if rapport_data.pieces_utilisees else "[]",
            statut=rapport_data.statut or "en_cours",
            date_debut=datetime.now()
        )
        db.add(rapport)
    else:
        # Mettre à jour le rapport existant
        if rapport_data.points_controle is not None:
            rapport.points_controle = json.dumps(rapport_data.points_controle)
        if rapport_data.alertes is not None:
            rapport.alertes = rapport_data.alertes
        if rapport_data.recommandations is not None:
            rapport.recommandations = rapport_data.recommandations
        if rapport_data.travaux_realises is not None:
            rapport.travaux_realises = rapport_data.travaux_realises
        if rapport_data.pieces_utilisees is not None:
            rapport.pieces_utilisees = json.dumps(rapport_data.pieces_utilisees)
        if rapport_data.statut:
            rapport.statut = rapport_data.statut
            if rapport_data.statut == "termine":
                rapport.date_fin = datetime.now()
    
    db.commit()
    db.refresh(rapport)
    
    return {
        "id": rapport.id,
        "rendez_vous_id": rapport.rendez_vous_id,
        "points_controle": json.loads(rapport.points_controle) if rapport.points_controle else {},
        "alertes": rapport.alertes,
        "recommandations": rapport.recommandations,
        "travaux_realises": rapport.travaux_realises,
        "pieces_utilisees": json.loads(rapport.pieces_utilisees) if rapport.pieces_utilisees else [],
        "statut": rapport.statut,
        "date_debut": rapport.date_debut,
        "date_fin": rapport.date_fin
    }

# ========== FACTURE PDF ==========

@router.get("/api/rendez-vous/{rdv_id}/facture")
def generate_facture(rdv_id: int, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    """Génère une facture au format PDF"""
    atelier_id = _atelier_id_or_403(current_user)
    rdv = db.query(RendezVous).filter(RendezVous.id == rdv_id, RendezVous.atelier_id == atelier_id).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="Rendez-vous non trouvé")
    return generate_facture_pdf(rdv_id, db)
