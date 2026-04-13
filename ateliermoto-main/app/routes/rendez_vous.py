from datetime import date, datetime, time, timedelta
import json
import logging

from fastapi import APIRouter, Depends, HTTPException, Request, Response
from sqlalchemy.orm import Session

from auth import get_current_user, get_optional_current_user
from models import Absence, Client, DemandeTravauxSupp, HoraireAtelier, Mecanicien, OrdreReparation, Pont, Prestation, RapportTechnicien, RendezVous, RolePermission, User, Vehicule, get_db
from schemas.rendez_vous import (
    OrdreReparationSave,
    RapportTechnicienCreate,
    RapportTechnicienResponse,
    RendezVousCreate,
    RendezVousUpdate,
)
from services.pdf_service import generate_facture_pdf, generate_ordre_reparation_pdf
from services.pricing_rules import PricingConfigError, resolve_prestation_pricing

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

    legacy = {
        "admin": {"rdv.edit", "workflow.manage", "or.manage", "workshop.manage", "clients.edit", "stats.view"},
        "manager": {"rdv.edit", "workflow.manage", "or.manage", "workshop.manage", "clients.edit", "stats.view"},
        "receptionnaire": {"rdv.edit", "workflow.manage", "or.manage", "clients.edit"},
        "service_client": {"rdv.edit", "clients.edit"},
        "mecanicien": set(),
    }
    return permission in legacy.get(current_user.role or "", set())


def _user_can_manage_workflow(current_user: User, db: Session, rdv: RendezVous | None = None) -> bool:
    if (
        _user_has_permission(current_user, db, "workflow.manage")
        or _user_has_permission(current_user, db, "or.manage")
        or _user_has_permission(current_user, db, "workshop.manage")
    ):
        return True
    if current_user.role != "mecanicien":
        return False
    if rdv is None or not rdv.mecanicien_id:
        return False
    mecanicien = db.query(Mecanicien).filter(
        Mecanicien.id == rdv.mecanicien_id,
        Mecanicien.atelier_id == _atelier_id_or_403(current_user)
    ).first()
    return bool(mecanicien and mecanicien.user_id == current_user.id)


_ALLOWED_STATUS_TRANSITIONS = {
    "reserve": {"confirme", "annule", "non_presente", "reception"},
    "en_attente": {"confirme", "annule", "non_presente", "reception"},
    "confirme": {"reception", "annule", "non_presente", "en_cours"},
    "reception": {"en_cours", "annule"},
    "en_cours": {"termine"},
    "termine": {"restitue", "facture", "paye"},
    "restitue": {"facture", "paye"},
    "facture": {"paye"},
    "paye": set(),
    "annule": set(),
    "non_presente": set(),
}

_FINALIZED_RDV_STATUSES = {"termine", "restitue", "facture", "paye"}


def _assert_valid_status_transition(current_status: str | None, new_status: str | None) -> None:
    current_value = (current_status or "reserve").strip().lower()
    next_value = (new_status or "").strip().lower()
    if not next_value or next_value == current_value:
        return
    allowed_targets = _ALLOWED_STATUS_TRANSITIONS.get(current_value)
    if allowed_targets is None:
        return
    if next_value not in allowed_targets:
        raise HTTPException(
            status_code=400,
            detail=f"Transition de statut interdite: {current_value} -> {next_value}",
        )


_WORKFLOW_ONLY_STATUS_MESSAGES = {
    "reception": "Transition de statut reservee : utilisez l'action de reception dediee pour valider l'OR et la signature client.",
    "en_cours": "Transition de statut reservee : utilisez l'action Demarrer apres une reception validee.",
    "termine": "Transition de statut reservee : utilisez l'action Terminer depuis l'atelier pour cloturer l'intervention.",
    "restitue": "Transition de statut reservee : utilisez l'action de restitution pour cloturer le rendez-vous.",
    "facture": "Transition de statut reservee : utilisez le parcours de facturation dedie.",
    "paye": "Transition de statut reservee : utilisez le parcours d'encaissement dedie.",
}


def _parse_or_meta(raw_value) -> dict:
    if not raw_value:
        return {}
    if isinstance(raw_value, dict):
        return dict(raw_value)
    try:
        data = json.loads(raw_value) if isinstance(raw_value, str) else raw_value
        if isinstance(data, dict):
            return data
    except Exception:
        pass
    text = str(raw_value).strip()
    return {"observations": text} if text else {}


def _get_workflow_history(meta: dict) -> list[dict]:
    history = meta.get("workflow_history") if isinstance(meta, dict) else None
    if not isinstance(history, list):
        return []
    return [entry for entry in history if isinstance(entry, dict)]


def _merge_etat_payload(existing_raw, incoming_raw=None) -> dict:
    payload = _parse_or_meta(existing_raw)
    if incoming_raw is None:
        return payload

    incoming = _parse_or_meta(incoming_raw)
    if incoming:
        preserved_history = _get_workflow_history(payload)
        payload.update(incoming)
        if preserved_history and not isinstance(payload.get("workflow_history"), list):
            payload["workflow_history"] = preserved_history
    elif isinstance(incoming_raw, str):
        clean = incoming_raw.strip()
        if clean:
            payload["observations"] = clean
    return payload


def _as_float_or_none(value) -> float | None:
    if value in (None, ""):
        return None
    try:
        return float(value)
    except Exception:
        return None


def _get_estimate_rows_total(rows) -> float | None:
    total = 0.0
    has_amount = False
    for row in rows or []:
        if not isinstance(row, dict):
            continue
        qty = _as_float_or_none(row.get("qty", row.get("quantite", 1)))
        amount = _as_float_or_none(row.get("amount", row.get("montant")))
        if amount is None:
            continue
        total += amount * (qty if qty is not None else 1.0)
        has_amount = True
    return round(total, 2) if has_amount else None


def _ensure_booking_price(payload: dict, rdv: RendezVous) -> dict:
    if not isinstance(payload, dict):
        payload = _merge_etat_payload(payload)

    if _as_float_or_none(payload.get("booking_price")) is not None:
        return payload

    estimate_rows = payload.get("estimate_rows") if isinstance(payload.get("estimate_rows"), list) else []
    booking_price = _get_estimate_rows_total(estimate_rows)
    if booking_price is None:
        booking_price = _as_float_or_none(getattr(rdv, "prix_estime", None))

    if booking_price is not None:
        payload["booking_price"] = booking_price
    return payload


def _record_workflow_event(
    rdv: RendezVous,
    current_user: User | None,
    *,
    action: str,
    from_status: str | None = None,
    to_status: str | None = None,
    note: str | None = None,
    extra: dict | None = None,
) -> dict:
    payload = _merge_etat_payload(rdv.etat_vehicule)
    history = _get_workflow_history(payload)
    actor = getattr(current_user, "username", None) or getattr(current_user, "email", None) or "system"
    role = getattr(current_user, "role", None) or "system"
    entry = {
        "action": action,
        "from_status": (from_status or rdv.statut or "").strip().lower() or None,
        "to_status": (to_status or rdv.statut or "").strip().lower() or None,
        "by": actor,
        "role": role,
        "at": datetime.now().isoformat(),
    }
    clean_note = str(note or "").strip()
    if clean_note:
        entry["note"] = clean_note
    if extra:
        for key, value in extra.items():
            if value is not None:
                entry[key] = value

    history.append(entry)
    payload["workflow_history"] = history[-25:]
    if entry.get("to_status"):
        payload["last_status"] = entry["to_status"]
    payload["last_status_change_at"] = entry["at"]
    payload["last_status_change_by"] = actor
    rdv.etat_vehicule = json.dumps(payload)
    return entry


def _get_initial_ordre(db: Session, rdv_id: int) -> OrdreReparation | None:
    return db.query(OrdreReparation).filter(
        OrdreReparation.rendez_vous_id == rdv_id,
        OrdreReparation.type_or == "initial"
    ).order_by(OrdreReparation.created_at.desc()).first()


def _is_ordre_locked(rdv: RendezVous, ordre: OrdreReparation | None) -> bool:
    if ordre and ordre.signature_client:
        return True
    meta = _parse_or_meta((ordre.etat_vehicule if ordre and ordre.etat_vehicule else None) or rdv.etat_vehicule)
    return bool(meta.get("or_locked"))


def _get_pause_window(db: Session, atelier_id: int, rdv_date: date | None) -> tuple[int, int] | None:
    if not rdv_date:
        return None
    horaire = db.query(HoraireAtelier).filter(
        HoraireAtelier.atelier_id == atelier_id,
        HoraireAtelier.jour_semaine == rdv_date.weekday(),
    ).first()
    if not horaire or not horaire.pause_debut or not horaire.pause_fin:
        return None
    try:
        pause_start = int(str(horaire.pause_debut)[:2]) * 60 + int(str(horaire.pause_debut)[3:5])
        pause_end = int(str(horaire.pause_fin)[:2]) * 60 + int(str(horaire.pause_fin)[3:5])
    except Exception:
        return None
    if pause_end <= pause_start:
        return None
    return pause_start, pause_end


def _compute_rdv_segments(
    db: Session,
    atelier_id: int,
    rdv_date: date,
    rdv_time: time | None,
    duration_min: int | None,
) -> list[tuple[datetime, datetime]]:
    base_time = rdv_time or time(9, 0)
    start_dt = datetime.combine(rdv_date, base_time)
    duration = int(duration_min or 60)
    if duration <= 0:
        duration = 60

    pause_window = _get_pause_window(db, atelier_id, rdv_date)
    start_minutes = (base_time.hour * 60) + base_time.minute
    total_end = start_minutes + duration
    if pause_window and start_minutes < pause_window[0] and total_end > pause_window[0]:
        first_end_dt = datetime.combine(rdv_date, time(pause_window[0] // 60, pause_window[0] % 60))
        remaining = max(0, total_end - pause_window[0])
        second_start_dt = datetime.combine(rdv_date, time(pause_window[1] // 60, pause_window[1] % 60))
        second_end_dt = second_start_dt + timedelta(minutes=remaining)
        segments = [(start_dt, first_end_dt)]
        if remaining > 0:
            segments.append((second_start_dt, second_end_dt))
        return segments

    return [(start_dt, start_dt + timedelta(minutes=duration))]


def _rdv_start_end(db: Session, rdv: RendezVous, atelier_id: int) -> tuple[datetime, datetime]:
    segments = _compute_rdv_segments(db, atelier_id, rdv.date_rdv, rdv.heure_rdv, rdv.temps_estime)
    return segments[0][0], segments[-1][1]


def _validate_rdv_resources(db: Session, rdv: RendezVous, atelier_id: int) -> None:
    if rdv.pont_id:
        pont = db.query(Pont).filter(Pont.id == rdv.pont_id, Pont.atelier_id == atelier_id).first()
        if not pont:
            raise HTTPException(status_code=400, detail="Pont invalide pour cet atelier")
        if pont.is_active != 1:
            raise HTTPException(status_code=400, detail="Pont inactif : selection impossible")
        if rdv.mecanicien_id and pont.mecanicien_id and pont.mecanicien_id != rdv.mecanicien_id:
            raise HTTPException(status_code=400, detail="Le pont selectionne est deja affecte a un autre technicien")

    if rdv.mecanicien_id:
        mecanicien = db.query(Mecanicien).filter(
            Mecanicien.id == rdv.mecanicien_id,
            Mecanicien.atelier_id == atelier_id,
        ).first()
        if not mecanicien:
            raise HTTPException(status_code=400, detail="Technicien invalide pour cet atelier")
        if mecanicien.is_active != 1:
            raise HTTPException(status_code=400, detail="Technicien inactif : selection impossible")
        if rdv.date_rdv:
            absence = db.query(Absence).filter(
                Absence.atelier_id == atelier_id,
                Absence.mecanicien_id == rdv.mecanicien_id,
                Absence.date_debut <= rdv.date_rdv,
                Absence.date_fin >= rdv.date_rdv,
            ).first()
            if absence:
                raise HTTPException(status_code=409, detail="Technicien indisponible sur ce creneau (absence planifiee)")


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

    my_segments = _compute_rdv_segments(db, atelier_id, rdv.date_rdv, rdv.heure_rdv, rdv.temps_estime)
    my_start, my_end = _rdv_start_end(db, rdv, atelier_id)
    for other in candidates.all():
        other_segments = _compute_rdv_segments(db, atelier_id, other.date_rdv, other.heure_rdv, other.temps_estime)
        overlap = any(
            seg_start < other_end and seg_end > other_start
            for seg_start, seg_end in my_segments
            for other_start, other_end in other_segments
        )
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

    pause_start = None
    pause_end = None
    if horaire.pause_debut and horaire.pause_fin:
        pause_start = _to_minutes(horaire.pause_debut)
        pause_end = _to_minutes(horaire.pause_fin)
        if pause_start <= slot < pause_end:
            return False
    if duration_min and duration_min > 0:
        end_slot = slot + int(duration_min)
        if pause_start is not None and pause_end is not None and slot < pause_start and end_slot > pause_start:
            end_slot += max(0, pause_end - pause_start)
        # On autorise un "split" sur la pause midi, mais jamais après fermeture.
        if end_slot > close_min:
            return False

    return True


def _serialize_rapport_technicien(rapport: RapportTechnicien) -> dict:
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
        "date_fin": rapport.date_fin,
    }


def _save_rapport_technicien_record(
    rdv_id: int,
    rapport_data: RapportTechnicienCreate,
    db: Session,
) -> RapportTechnicien:
    rapport = db.query(RapportTechnicien).filter(RapportTechnicien.rendez_vous_id == rdv_id).first()
    now = datetime.now()
    requested_status = str(rapport_data.statut or "").strip() or (rapport.statut if rapport else "en_cours")

    if not rapport:
        rapport = RapportTechnicien(
            rendez_vous_id=rdv_id,
            points_controle=json.dumps(rapport_data.points_controle) if rapport_data.points_controle is not None else "{}",
            alertes=rapport_data.alertes,
            recommandations=rapport_data.recommandations,
            travaux_realises=rapport_data.travaux_realises,
            pieces_utilisees=json.dumps(rapport_data.pieces_utilisees) if rapport_data.pieces_utilisees is not None else "[]",
            statut=requested_status or "en_cours",
            date_debut=now,
            date_fin=now if requested_status == "termine" else None,
        )
        db.add(rapport)
        return rapport

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
        rapport.statut = requested_status

    if rapport.statut == "termine":
        rapport.date_fin = rapport.date_fin or now
    elif rapport_data.statut and rapport.statut != "termine":
        rapport.date_fin = None

    return rapport


def _terminer_rdv_intervention(rdv: RendezVous, current_user: User | None) -> None:
    if rdv.statut != "en_cours":
        raise HTTPException(status_code=400, detail="Le rendez-vous doit être en cours avant cloture")
    if not rdv.heure_debut_travail:
        raise HTTPException(status_code=400, detail="Le travail n'a pas été démarré")

    rdv.heure_fin_travail = datetime.now()
    delta = rdv.heure_fin_travail - rdv.heure_debut_travail
    rdv.temps_effectif_minutes = max(0, int(delta.total_seconds() / 60))
    rdv.temps_final = rdv.temps_effectif_minutes
    rdv.statut = "termine"
    _record_workflow_event(
        rdv,
        current_user,
        action="terminer_travail",
        from_status="en_cours",
        to_status="termine",
        note="Intervention atelier cloturee",
        extra={"temps_effectif_minutes": rdv.temps_effectif_minutes},
    )


def _restituer_rdv(rdv: RendezVous, current_user: User | None) -> None:
    if rdv.statut != "termine":
        raise HTTPException(status_code=400, detail="Le rendez-vous doit etre termine avant restitution")

    rdv.statut = "restitue"
    _record_workflow_event(
        rdv,
        current_user,
        action="restitution",
        from_status="termine",
        to_status="restitue",
        note="Vehicule restitue et dossier atelier cloture",
    )


# ========== RENDEZ-VOUS ==========

@router.post("/api/rendez-vous")
def create_rendez_vous(
    rdv: RendezVousCreate,
    request: Request,
    response: Response,
    db: Session = Depends(get_db),
    current_user: User | None = Depends(get_optional_current_user),
):
    """Crée un nouveau rendez-vous avec client et véhicule.

    Compatibilité maintenue pour la prise de RDV publique historique tout en utilisant
    l'atelier courant lorsqu'un utilisateur authentifié est présent.
    """

    atelier_id = _atelier_id_or_403(current_user) if current_user else 1
    client_db = db.query(Client).filter(Client.telephone == rdv.client.telephone, Client.atelier_id == atelier_id).first()
    if not client_db:
        client_db = Client(**rdv.client.model_dump(), atelier_id=atelier_id)
        db.add(client_db)
        db.flush()
    
    # Créer ou récupérer le véhicule
    vehicule_db = db.query(Vehicule).filter(
        Vehicule.plaque == rdv.vehicule.plaque.upper().replace(" ", "").replace("-", ""),
        Vehicule.atelier_id == atelier_id
    ).first()
    
    if not vehicule_db:
        vehicule_db = Vehicule(**rdv.vehicule.model_dump(), client_id=client_db.id, atelier_id=atelier_id)
        db.add(vehicule_db)
        db.flush()
    else:
        # Mettre à jour les informations véhicule à partir de la base moto si besoin
        if not vehicule_db.marque and rdv.vehicule.marque:
            vehicule_db.marque = rdv.vehicule.marque
        if not vehicule_db.modele and rdv.vehicule.modele:
            vehicule_db.modele = rdv.vehicule.modele
        if not vehicule_db.annee and rdv.vehicule.annee:
            vehicule_db.annee = rdv.vehicule.annee
        if not vehicule_db.cylindree and rdv.vehicule.cylindree:
            vehicule_db.cylindree = rdv.vehicule.cylindree
        if not vehicule_db.type_moto and rdv.vehicule.type_moto:
            vehicule_db.type_moto = rdv.vehicule.type_moto
        if not vehicule_db.categorie_id and rdv.vehicule.categorie_id:
            vehicule_db.categorie_id = rdv.vehicule.categorie_id
        if not vehicule_db.modele_id and rdv.vehicule.modele_id:
            vehicule_db.modele_id = rdv.vehicule.modele_id
        if not vehicule_db.client_id:
            vehicule_db.client_id = client_db.id
    
    # Source de vérité tarifaire: configuration des prestations
    prestation = db.query(Prestation).filter(
        Prestation.nom == rdv.type_intervention,
        Prestation.atelier_id == atelier_id,
        Prestation.is_active == 1,
    ).first()
    if not prestation:
        raise HTTPException(
            status_code=400,
            detail="Prestation introuvable: configurez cette intervention dans le catalogue avant de creer le RDV",
        )
    try:
        pricing = resolve_prestation_pricing(
            db,
            atelier_id=atelier_id,
            prestation=prestation,
            vehicule=vehicule_db,
            strict=True,
        )
    except PricingConfigError as exc:
        raise HTTPException(status_code=400, detail=str(exc))

    # Créer le rendez-vous
    new_rdv = RendezVous(
        client_id=client_db.id,
        vehicule_id=vehicule_db.id,
        date_rdv=rdv.date_rdv,
        heure_rdv=rdv.heure_rdv,
        type_intervention=rdv.type_intervention,
        commentaire=rdv.commentaire,
        prix_estime=pricing.prix_ttc,
        temps_estime=pricing.temps_minutes,
        statut="en_attente",
        atelier_id=atelier_id
    )

    if rdv.pont_id is not None:
        new_rdv.pont_id = rdv.pont_id
    if rdv.mecanicien_id is not None:
        new_rdv.mecanicien_id = rdv.mecanicien_id
    if new_rdv.mecanicien_id and not new_rdv.pont_id:
        pont_du_meca = db.query(Pont).filter(
            Pont.atelier_id == atelier_id,
            Pont.mecanicien_id == new_rdv.mecanicien_id,
            Pont.is_active == 1,
        ).first()
        if pont_du_meca:
            new_rdv.pont_id = pont_du_meca.id
    elif new_rdv.pont_id and not new_rdv.mecanicien_id:
        pont = db.query(Pont).filter(Pont.id == new_rdv.pont_id, Pont.atelier_id == atelier_id).first()
        if pont and pont.mecanicien_id:
            new_rdv.mecanicien_id = pont.mecanicien_id
    
    db.add(new_rdv)
    db.flush()
    if not _time_in_open_hours(db, atelier_id, new_rdv.date_rdv, new_rdv.heure_rdv, new_rdv.temps_estime):
        raise HTTPException(status_code=400, detail="Creneau en dehors des horaires d'ouverture")
    _validate_rdv_resources(db, new_rdv, atelier_id)
    _validate_no_conflict(db, new_rdv, atelier_id)
    db.commit()
    db.refresh(new_rdv)

    if "authorization" not in request.headers:
        response.set_cookie("legacy_client_list", "1", max_age=3600, samesite="lax")
    
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
        try:
            photos = json.loads(rdv.photos_etat) if rdv.photos_etat else []
        except Exception:
            photos = []
        etat_meta = _parse_or_meta(rdv.etat_vehicule)
        workflow_history = _get_workflow_history(etat_meta)
        ors = [{
            "id": o.id,
            "numero_or": o.numero_or,
            "type_or": o.type_or,
            "travaux": o.travaux,
            "kilometrage": o.kilometrage,
            "etat_vehicule": o.etat_vehicule,
            "signature_client": o.signature_client,
            "created_at": o.created_at.isoformat() if o.created_at else None,
        } for o in rdv.ordres_reparation] if rdv.ordres_reparation else []
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
            "etat_vehicule": rdv.etat_vehicule,
            "workflow_history": workflow_history,
            "photos_etat": photos,
            "heure_debut_travail": rdv.heure_debut_travail.isoformat() if rdv.heure_debut_travail else None,
            "heure_fin_travail": rdv.heure_fin_travail.isoformat() if rdv.heure_fin_travail else None,
            "temps_effectif_minutes": rdv.temps_effectif_minutes,
            "ordres_reparation": ors
        })
    return result

@router.get("/api/rendez-vous/{rdv_id}")
def get_rendez_vous(
    rdv_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Récupère un rendez-vous par son ID (authentification requise)."""
    atelier_id = _atelier_id_or_403(current_user)
    rdv = db.query(RendezVous).filter(RendezVous.id == rdv_id, RendezVous.atelier_id == atelier_id).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="Rendez-vous non trouvé")
    
    try:
        photos = json.loads(rdv.photos_etat) if rdv.photos_etat else []
    except Exception:
        photos = []
    workflow_history = _get_workflow_history(_parse_or_meta(rdv.etat_vehicule))
    ors = [{
        "id": o.id,
        "numero_or": o.numero_or,
        "type_or": o.type_or,
        "travaux": o.travaux,
        "kilometrage": o.kilometrage,
        "etat_vehicule": o.etat_vehicule,
        "signature_client": o.signature_client,
        "created_at": o.created_at.isoformat() if o.created_at else None,
    } for o in rdv.ordres_reparation] if rdv.ordres_reparation else []
    return {
        "id": rdv.id,
        "client": {"id": rdv.client.id, "nom": rdv.client.nom, "prenom": rdv.client.prenom, "telephone": rdv.client.telephone, "email": rdv.client.email, "adresse": rdv.client.adresse},
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
        "workflow_history": workflow_history,
        "photos_etat": photos,
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
    can_edit_rdv = _user_has_permission(current_user, db, "rdv.edit")
    can_manage_workflow = (
        _user_has_permission(current_user, db, "workflow.manage")
        or _user_has_permission(current_user, db, "or.manage")
        or _user_has_permission(current_user, db, "workshop.manage")
    )
    wants_workflow_change = any([
        update_data.statut is not None,
        update_data.pont_id is not None,
        update_data.mecanicien_id is not None,
    ])
    wants_rdv_edit = any([
        update_data.kilometrage is not None,
        update_data.etat_vehicule is not None,
        update_data.prix_final is not None,
        update_data.temps_final is not None,
        update_data.commentaire is not None,
        update_data.heure_rdv is not None,
        update_data.date_rdv is not None,
    ])
    if wants_workflow_change and not can_manage_workflow:
        raise HTTPException(status_code=403, detail="Permission workflow.manage requise")
    if wants_rdv_edit and not can_edit_rdv:
        raise HTTPException(status_code=403, detail="Permission rdv.edit requise")
    if not wants_workflow_change and not wants_rdv_edit and not can_edit_rdv:
        raise HTTPException(status_code=403, detail="Permission rdv.edit requise")
    rdv = db.query(RendezVous).filter(RendezVous.id == rdv_id).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="Rendez-vous non trouvé")
    if current_user.role != "super_admin" and rdv.atelier_id != _atelier_id_or_403(current_user):
        raise HTTPException(status_code=404, detail="Rendez-vous non trouvé")
    atelier_id = rdv.atelier_id
    
    previous_status = rdv.statut
    previous_date_rdv = rdv.date_rdv
    previous_heure_rdv = rdv.heure_rdv
    requested_status = (update_data.statut or "").strip().lower() if update_data.statut else None
    status_reason = str(update_data.commentaire or "").strip()
    or_initial = _get_initial_ordre(db, rdv_id)
    or_locked = _is_ordre_locked(rdv, or_initial)
    requested_pont_change = update_data.pont_id is not None and update_data.pont_id != rdv.pont_id
    requested_mecanicien_change = update_data.mecanicien_id is not None and update_data.mecanicien_id != rdv.mecanicien_id

    if rdv.statut in _FINALIZED_RDV_STATUSES and (requested_pont_change or requested_mecanicien_change):
        raise HTTPException(
            status_code=409,
            detail="Le pont et le technicien sont verrouilles sur un rendez-vous finalise.",
        )

    if requested_status in _WORKFLOW_ONLY_STATUS_MESSAGES:
        raise HTTPException(status_code=400, detail=_WORKFLOW_ONLY_STATUS_MESSAGES[requested_status])
    if requested_status in {"annule", "non_presente"} and not status_reason:
        raise HTTPException(
            status_code=400,
            detail="Un motif/commentaire est obligatoire pour annuler ou marquer un rendez-vous non presente.",
        )

    locked_reception_edit = any([
        update_data.kilometrage is not None,
        update_data.etat_vehicule is not None,
        update_data.commentaire is not None and requested_status not in {"annule", "non_presente"},
    ])
    if or_locked and locked_reception_edit:
        raise HTTPException(
            status_code=409,
            detail="Ordre de reparation verrouille apres signature client : les informations de reception ne sont plus modifiables.",
        )

    if requested_status:
        _assert_valid_status_transition(previous_status, requested_status)
        rdv.statut = requested_status
    if update_data.kilometrage is not None:
        rdv.kilometrage = update_data.kilometrage
    if update_data.etat_vehicule is not None:
        rdv.etat_vehicule = json.dumps(_merge_etat_payload(rdv.etat_vehicule, update_data.etat_vehicule))
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
            if rdv.statut not in _FINALIZED_RDV_STATUSES:
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
            if rdv.statut not in _FINALIZED_RDV_STATUSES:
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
    
    _validate_rdv_resources(db, rdv, atelier_id)
    _validate_no_conflict(db, rdv, atelier_id)
    if not _time_in_open_hours(db, atelier_id, rdv.date_rdv, rdv.heure_rdv, rdv.temps_estime):
        raise HTTPException(status_code=400, detail="Creneau en dehors des horaires d'ouverture")

    if previous_status != rdv.statut:
        _record_workflow_event(
            rdv,
            current_user,
            action="status_change",
            from_status=previous_status,
            to_status=rdv.statut,
            note=status_reason or None,
        )

    db.commit()
    db.refresh(rdv)
    if previous_status != "confirme" and rdv.statut == "confirme":
        try:
            from services.rappel_service import envoyer_confirmation, programmer_rappels_rdv
            envoyer_confirmation(db, rdv.id)
            programmer_rappels_rdv(db, rdv.id)
        except Exception:
            logger.exception("Erreur envoi confirmation/rappels rdv_id=%s", rdv.id)
    if previous_status != rdv.statut and rdv.statut in ("annule",):
        try:
            from services.notification_service import notifier_changement_statut
            notifier_changement_statut(db, rdv.id, rdv.statut)
        except Exception:
            logger.exception("Erreur envoi notification statut=%s rdv_id=%s", rdv.statut, rdv.id)
    # Notify client when appointment is rescheduled (date or time changed)
    date_changed = previous_date_rdv != rdv.date_rdv
    heure_changed = previous_heure_rdv != rdv.heure_rdv
    if (date_changed or heure_changed) and rdv.statut not in ("annule", "non_presente"):
        try:
            from services.notification_service import notifier_deplacement_rdv
            ancienne_date = previous_date_rdv.strftime("%d/%m/%Y") if previous_date_rdv else ""
            ancienne_heure = previous_heure_rdv.strftime("%Hh%M") if previous_heure_rdv else ""
            notifier_deplacement_rdv(db, rdv.id, ancienne_date, ancienne_heure)
        except Exception:
            logger.exception("Erreur envoi notification deplacement rdv_id=%s", rdv.id)
    logger.info("RDV updated id=%s by user=%s", rdv.id, current_user.username)
    return {"message": "Rendez-vous mis à jour", "id": rdv.id}

@router.delete("/api/rendez-vous/{rdv_id}")
def delete_rendez_vous(rdv_id: int, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    """Supprime un rendez-vous"""
    if not (_user_has_permission(current_user, db, "workflow.manage") or _user_has_permission(current_user, db, "rdv.edit")):
        raise HTTPException(status_code=403, detail="Permission workflow.manage ou rdv.edit requise")
    rdv = db.query(RendezVous).filter(RendezVous.id == rdv_id).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="Rendez-vous non trouvé")
    if current_user.role != "super_admin" and rdv.atelier_id != _atelier_id_or_403(current_user):
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
    if not _user_can_manage_workflow(current_user, db, rdv):
        raise HTTPException(status_code=403, detail="Action non autorisée sur ce rendez-vous")
    if rdv.statut != "reception":
        raise HTTPException(status_code=400, detail="Reception obligatoire avant de demarrer le travail")

    or_initial = db.query(OrdreReparation).filter(
        OrdreReparation.rendez_vous_id == rdv_id,
        OrdreReparation.type_or == "initial"
    ).order_by(OrdreReparation.created_at.desc()).first()
    if not or_initial or not or_initial.signature_client:
        raise HTTPException(status_code=400, detail="Ordre de reparation signe obligatoire avant demarrage")

    from datetime import datetime
    rdv.heure_debut_travail = datetime.now()
    rdv.heure_fin_travail = None
    rdv.temps_effectif_minutes = None
    rdv.statut = "en_cours"
    _record_workflow_event(
        rdv,
        current_user,
        action="demarrer_travail",
        from_status="reception",
        to_status="en_cours",
        note="Demarrage de l'intervention atelier",
    )

    db.commit()
    db.refresh(rdv)
    try:
        from services.notification_service import notifier_changement_statut
        notifier_changement_statut(db, rdv.id, "en_cours")
    except Exception:
        logger.exception("Erreur envoi notification en_cours rdv_id=%s", rdv_id)
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
    if not _user_can_manage_workflow(current_user, db, rdv):
        raise HTTPException(status_code=403, detail="Action non autorisée sur ce rendez-vous")

    _terminer_rdv_intervention(rdv, current_user)

    db.commit()
    db.refresh(rdv)
    try:
        from services.notification_service import notifier_changement_statut, notifier_compte_rendu
        notifier_changement_statut(db, rdv.id, "termine")
        notifier_compte_rendu(db, rdv.id)
    except Exception:
        logger.exception("Erreur envoi notifications termine rdv_id=%s", rdv_id)
    return {
        "message": "Travail terminé",
        "heure_debut": rdv.heure_debut_travail.isoformat(),
        "heure_fin": rdv.heure_fin_travail.isoformat(),
        "temps_effectif_minutes": rdv.temps_effectif_minutes,
        "statut": rdv.statut
    }


@router.post("/api/rendez-vous/{rdv_id}/restituer")
def restituer_rendez_vous(rdv_id: int, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    """Cloture un RDV une fois le vehicule restitue au client."""
    if not (
        _user_has_permission(current_user, db, "or.manage")
        or _user_has_permission(current_user, db, "workflow.manage")
    ):
        raise HTTPException(status_code=403, detail="Permission or.manage ou workflow.manage requise")

    atelier_id = _atelier_id_or_403(current_user)
    rdv = db.query(RendezVous).filter(RendezVous.id == rdv_id, RendezVous.atelier_id == atelier_id).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="Rendez-vous non trouvé")

    _restituer_rdv(rdv, current_user)

    db.commit()
    db.refresh(rdv)
    return {
        "message": "Vehicule restitue, rendez-vous cloture",
        "id": rdv.id,
        "statut": rdv.statut,
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
    """Génère un ordre de réparation PDF pour un rendez-vous."""
    atelier_id = _atelier_id_or_403(current_user)
    rdv = db.query(RendezVous).filter(RendezVous.id == rdv_id, RendezVous.atelier_id == atelier_id).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="Rendez-vous non trouvé")
    return generate_ordre_reparation_pdf(rdv_id, db)


@router.get("/api/ordres-reparation/{or_id}/pdf")
def generate_stored_or_pdf(or_id: int, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    """Ouvre directement le PDF d'un OR stocké en base."""
    atelier_id = _atelier_id_or_403(current_user)
    ordre = db.query(OrdreReparation).join(RendezVous, RendezVous.id == OrdreReparation.rendez_vous_id).filter(
        OrdreReparation.id == or_id,
        RendezVous.atelier_id == atelier_id,
    ).first()
    if not ordre:
        raise HTTPException(status_code=404, detail="Ordre de réparation non trouvé")
    return generate_ordre_reparation_pdf(ordre.rendez_vous_id, db, or_id=ordre.id)


@router.post("/api/rendez-vous/{rdv_id}/ordre-reparation/save")
def save_ordre_reparation(
    rdv_id: int,
    or_data: OrdreReparationSave,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Sauvegarde les données de l'ordre de réparation"""
    if not (
        _user_has_permission(current_user, db, "or.manage")
        or _user_has_permission(current_user, db, "workflow.manage")
    ):
        raise HTTPException(status_code=403, detail="Permission or.manage ou workflow.manage requise")
    atelier_id = _atelier_id_or_403(current_user)
    rdv = db.query(RendezVous).filter(RendezVous.id == rdv_id, RendezVous.atelier_id == atelier_id).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="Rendez-vous non trouvé")
    
    or_initial = _get_initial_ordre(db, rdv_id)
    if _is_ordre_locked(rdv, or_initial):
        raise HTTPException(status_code=409, detail="Ordre de reparation verrouille apres signature client")

    # Mettre à jour les champs enrichis de reception sans perdre l'historique workflow
    etat_payload = _merge_etat_payload(rdv.etat_vehicule, or_data.etat_vehicule)

    if or_data.priorite is not None:
        etat_payload["priority"] = or_data.priorite
    if or_data.niveau_carburant is not None:
        etat_payload["fuel_level"] = or_data.niveau_carburant
    if or_data.dommages_carrosserie is not None:
        etat_payload["body_damages"] = or_data.dommages_carrosserie
    if or_data.notes_schema is not None:
        etat_payload["schema_notes"] = or_data.notes_schema
    if or_data.lignes_estimation is not None:
        etat_payload["estimate_rows"] = or_data.lignes_estimation
    etat_payload = _ensure_booking_price(etat_payload, rdv)
    try:
        existing_photos = json.loads(rdv.photos_etat) if rdv.photos_etat else []
        if not isinstance(existing_photos, list):
            existing_photos = []
    except Exception:
        existing_photos = []
    photos_payload = or_data.photos if or_data.photos is not None else existing_photos
    if photos_payload:
        etat_payload["photo_count"] = len(photos_payload)
        etat_payload["photos"] = photos_payload
    if or_data.signature:
        signed_at = etat_payload.get("or_locked_at") or datetime.now().isoformat()
        signed_by = getattr(current_user, "username", None) or "system"
        etat_payload["or_locked"] = True
        etat_payload["or_locked_at"] = signed_at
        etat_payload["or_locked_by"] = signed_by
        history = _get_workflow_history(etat_payload)
        history.append({
            "action": "or_signed",
            "from_status": (rdv.statut or "").strip().lower() or None,
            "to_status": (rdv.statut or "").strip().lower() or None,
            "by": signed_by,
            "role": getattr(current_user, "role", None) or "system",
            "at": signed_at,
            "note": "OR signe et verrouille",
        })
        etat_payload["workflow_history"] = history[-25:]

    etat_json = json.dumps(etat_payload)
    photos_json = json.dumps(photos_payload)

    rdv.kilometrage = or_data.kilometrage
    rdv.etat_vehicule = etat_json
    rdv.photos_etat = photos_json
    if or_data.travaux:
        rdv.commentaire = or_data.travaux
    
    # Historiser l'OR initial en base (idempotent)
    year = rdv.date_rdv.year if rdv.date_rdv else datetime.now().year
    numero_or = f"OR-{year}-{str(rdv_id).zfill(3)}"
    if not or_initial:
        or_initial = OrdreReparation(
            rendez_vous_id=rdv_id,
            numero_or=numero_or,
            type_or="initial",
        )
        db.add(or_initial)

    or_initial.kilometrage = or_data.kilometrage
    or_initial.etat_vehicule = etat_json
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
        "or_signe": True,
        "photos_count": len(or_data.photos or [])
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

    return _serialize_rapport_technicien(rapport)

@router.post("/api/rendez-vous/{rdv_id}/rapport-technicien", response_model=RapportTechnicienResponse)
def create_or_update_rapport_technicien(
    rdv_id: int,
    rapport_data: RapportTechnicienCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Crée ou met à jour le rapport technicien d'un RDV"""
    atelier_id = _atelier_id_or_403(current_user)
    rdv = db.query(RendezVous).filter(RendezVous.id == rdv_id, RendezVous.atelier_id == atelier_id).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="Rendez-vous non trouvé")
    if not _user_can_manage_workflow(current_user, db, rdv):
        raise HTTPException(status_code=403, detail="Action non autorisée sur ce rendez-vous")

    rapport = _save_rapport_technicien_record(rdv_id, rapport_data, db)
    db.commit()
    db.refresh(rapport)

    return _serialize_rapport_technicien(rapport)


@router.post("/api/rendez-vous/{rdv_id}/terminer-avec-rapport")
def terminer_avec_rapport_technicien(
    rdv_id: int,
    rapport_data: RapportTechnicienCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Sauvegarde le rapport technicien et termine l'intervention dans une seule transaction."""
    atelier_id = _atelier_id_or_403(current_user)
    rdv = db.query(RendezVous).filter(RendezVous.id == rdv_id, RendezVous.atelier_id == atelier_id).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="Rendez-vous non trouvé")
    if not _user_can_manage_workflow(current_user, db, rdv):
        raise HTTPException(status_code=403, detail="Action non autorisée sur ce rendez-vous")

    forced_payload = RapportTechnicienCreate(
        points_controle=rapport_data.points_controle,
        alertes=rapport_data.alertes,
        recommandations=rapport_data.recommandations,
        travaux_realises=rapport_data.travaux_realises,
        pieces_utilisees=rapport_data.pieces_utilisees,
        statut="termine",
    )
    rapport = _save_rapport_technicien_record(rdv_id, forced_payload, db)
    _terminer_rdv_intervention(rdv, current_user)

    db.commit()
    db.refresh(rdv)
    db.refresh(rapport)

    return {
        "message": "Intervention terminee et rapport sauvegarde",
        "heure_debut": rdv.heure_debut_travail.isoformat() if rdv.heure_debut_travail else None,
        "heure_fin": rdv.heure_fin_travail.isoformat() if rdv.heure_fin_travail else None,
        "temps_effectif_minutes": rdv.temps_effectif_minutes,
        "statut": rdv.statut,
        "rapport": _serialize_rapport_technicien(rapport),
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
