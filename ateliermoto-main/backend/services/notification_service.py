"""Service central de notifications email pour les changements de statut.

Chaque transition de workflow déclenche un email au client si son adresse
est renseignée. Les envois sont journalisés dans la table rappels_email.
"""

import logging
from datetime import datetime

from sqlalchemy.orm import Session

from models import (
    Atelier, Client, Facture, HoraireAtelier, Mecanicien, PhotoIntervention,
    RappelEmail, RapportTechnicien, RendezVous, Vehicule,
)
from services.email_service import (
    render_compte_rendu,
    render_deplacement_rdv,
    render_facture_email,
    render_signature_or,
    render_suivi_statut,
    send_email,
)

logger = logging.getLogger("ateliermoto.notifications")


# ── Helpers ──────────────────────────────────────────────────────

def _build_atelier_adresse(atelier) -> str:
    if not atelier:
        return ""
    parts = [p for p in [atelier.adresse, atelier.cp, atelier.ville] if p]
    return ", ".join(parts)


def _vehicule_info(db: Session, vehicule_id: int | None) -> str:
    if not vehicule_id:
        return ""
    v = db.query(Vehicule).filter(Vehicule.id == vehicule_id).first()
    if not v:
        return ""
    parts = [p for p in [v.marque, v.modele, v.plaque] if p]
    return " — ".join(parts)


def _mecanicien_nom(db: Session, mecanicien_id: int | None) -> str:
    if not mecanicien_id:
        return ""
    mec = db.query(Mecanicien).filter(Mecanicien.id == mecanicien_id).first()
    return f"{mec.prenom or ''} {mec.nom or ''}".strip() if mec else ""


def _get_atelier(db: Session, atelier_id: int) -> dict:
    atelier = db.query(Atelier).filter(Atelier.id == atelier_id).first()
    if not atelier:
        return {"nom": "Atelier Moto", "telephone": "", "adresse": "", "email": "", "logo_url": ""}
    return {
        "nom": atelier.nom or "Atelier Moto",
        "telephone": atelier.telephone or "",
        "adresse": _build_atelier_adresse(atelier),
        "email": atelier.email or "",
        "logo_url": atelier.logo_url or "",
    }


def _log_email(db: Session, rdv, client, type_rappel: str, subject: str, ok: bool):
    rappel = RappelEmail(
        atelier_id=rdv.atelier_id or 1,
        rdv_id=rdv.id,
        client_id=client.id,
        type_rappel=type_rappel,
        destinataire=client.email,
        sujet=subject,
        statut="envoye" if ok else "erreur",
        date_envoi_prevu=datetime.now(),
        date_envoi_reel=datetime.now() if ok else None,
        erreur=None if ok else "Échec SMTP",
    )
    db.add(rappel)
    db.commit()


def _get_client_rdv(db: Session, rdv_id: int):
    """Return (rdv, client) or (None, None) if email not possible."""
    rdv = db.query(RendezVous).filter(RendezVous.id == rdv_id).first()
    if not rdv or not rdv.client_id:
        return None, None
    client = db.query(Client).filter(Client.id == rdv.client_id).first()
    if not client or not client.email:
        return rdv, None
    return rdv, client


# ── Messages par statut ──────────────────────────────────────────

_STATUS_MESSAGES = {
    "confirme": {
        "titre": "Votre rendez-vous est confirmé",
        "message": "Votre rendez-vous a été confirmé. Nous vous attendons à la date prévue.",
    },
    "reception": {
        "titre": "Votre moto a été réceptionnée",
        "message": "Votre véhicule a bien été réceptionné à l'atelier. Le diagnostic va commencer.",
    },
    "en_cours": {
        "titre": "Les travaux ont commencé",
        "message": "Notre mécanicien a commencé à travailler sur votre véhicule.",
    },
    "termine": {
        "titre": "Intervention terminée — Votre moto est prête",
        "message": "L'intervention sur votre véhicule est terminée. Vous pouvez venir le récupérer.",
    },
    "facture": {
        "titre": "Votre facture est disponible",
        "message": "La facture de votre intervention a été générée.",
    },
    "paye": {
        "titre": "Paiement reçu — Merci !",
        "message": "Nous avons bien reçu votre paiement. Merci de votre confiance !",
    },
    "annule": {
        "titre": "Votre rendez-vous a été annulé",
        "message": "Votre rendez-vous a été annulé. N'hésitez pas à reprendre contact pour reprogrammer.",
    },
}


# ── Notification principale par statut ───────────────────────────

def notifier_changement_statut(db: Session, rdv_id: int, nouveau_statut: str) -> bool:
    """Envoie un email au client pour un changement de statut.

    Retourne True si l'email est envoyé, False sinon.
    """
    if nouveau_statut not in _STATUS_MESSAGES:
        return False

    rdv, client = _get_client_rdv(db, rdv_id)
    if not client:
        return False

    info = _get_atelier(db, rdv.atelier_id or 1)
    veh = _vehicule_info(db, rdv.vehicule_id)

    msg = _STATUS_MESSAGES[nouveau_statut]
    details = []
    if nouveau_statut == "en_cours":
        mec = _mecanicien_nom(db, rdv.mecanicien_id)
        if mec:
            details.append(("Mécanicien", mec))
    if nouveau_statut == "termine" and rdv.temps_effectif_minutes:
        h, m = divmod(rdv.temps_effectif_minutes, 60)
        details.append(("Durée", f"{h}h{m:02d}" if h else f"{m} min"))

    date_str = rdv.date_rdv.strftime("%d/%m/%Y") if rdv.date_rdv else ""
    heure_str = str(rdv.heure_rdv)[:5] if rdv.heure_rdv else ""

    subject, html_body, text_body = render_suivi_statut(
        client_prenom=client.prenom or "",
        statut=nouveau_statut,
        titre=msg["titre"],
        message_principal=msg["message"],
        atelier_nom=info["nom"],
        atelier_telephone=info["telephone"],
        atelier_adresse=info["adresse"],
        logo_url=info["logo_url"],
        atelier_email=info["email"],
        token_suivi=getattr(rdv, "token_suivi", "") or "",
        vehicule_info=veh,
        details_extra=details,
        date_rdv=date_str,
        heure_rdv=heure_str,
        type_intervention=rdv.type_intervention or "",
    )

    ok = send_email(client.email, subject, html_body, text_body, atelier_id=rdv.atelier_id)
    _log_email(db, rdv, client, f"statut_{nouveau_statut}", subject, ok)
    logger.info("Email statut=%s rdv=%s ok=%s", nouveau_statut, rdv_id, ok)
    return ok


# ── Notification compte rendu (à la clôture) ────────────────────

def notifier_compte_rendu(db: Session, rdv_id: int) -> bool:
    """Envoie le compte rendu d'intervention quand l'OR est clôturé."""
    rdv, client = _get_client_rdv(db, rdv_id)
    if not client:
        return False

    info = _get_atelier(db, rdv.atelier_id or 1)
    veh = _vehicule_info(db, rdv.vehicule_id)

    rapport = db.query(RapportTechnicien).filter(
        RapportTechnicien.rendez_vous_id == rdv_id
    ).first()

    photos_count = db.query(PhotoIntervention).filter(
        PhotoIntervention.rendez_vous_id == rdv_id
    ).count()

    subject, html_body, text_body = render_compte_rendu(
        client_prenom=client.prenom or "",
        atelier_nom=info["nom"],
        vehicule_info=veh,
        travaux_realises=rapport.travaux_realises if rapport else (rdv.commentaire or ""),
        recommandations=rapport.recommandations if rapport else "",
        kilometrage=str(rdv.kilometrage) if rdv.kilometrage else "",
        photos_count=photos_count,
        atelier_telephone=info["telephone"],
        atelier_adresse=info["adresse"],
        logo_url=info["logo_url"],
        atelier_email=info["email"],
        token_suivi=getattr(rdv, "token_suivi", "") or "",
        date_rdv=rdv.date_rdv.strftime("%d/%m/%Y") if rdv.date_rdv else "",
        heure_rdv=str(rdv.heure_rdv)[:5] if rdv.heure_rdv else "",
        type_intervention=rdv.type_intervention or "",
    )

    ok = send_email(client.email, subject, html_body, text_body, atelier_id=rdv.atelier_id)
    _log_email(db, rdv, client, "compte_rendu", subject, ok)
    return ok


# ── Notification signature OR complémentaire ─────────────────────

def notifier_signature_or(
    db: Session,
    rdv_id: int,
    description_travaux: str,
    urgence: str = "normal",
    prix_estime: str = "",
) -> bool:
    """Envoie un email pour demander la signature d'un OR complémentaire."""
    rdv, client = _get_client_rdv(db, rdv_id)
    if not client:
        return False

    info = _get_atelier(db, rdv.atelier_id or 1)
    veh = _vehicule_info(db, rdv.vehicule_id)

    subject, html_body, text_body = render_signature_or(
        client_prenom=client.prenom or "",
        description_travaux=description_travaux,
        urgence=urgence,
        prix_estime=prix_estime,
        atelier_nom=info["nom"],
        atelier_telephone=info["telephone"],
        atelier_adresse=info["adresse"],
        logo_url=info["logo_url"],
        atelier_email=info["email"],
        token_suivi=getattr(rdv, "token_suivi", "") or "",
        vehicule_info=veh,
        date_rdv=rdv.date_rdv.strftime("%d/%m/%Y") if rdv.date_rdv else "",
        heure_rdv=str(rdv.heure_rdv)[:5] if rdv.heure_rdv else "",
        type_intervention=rdv.type_intervention or "",
    )

    ok = send_email(client.email, subject, html_body, text_body, atelier_id=rdv.atelier_id)
    _log_email(db, rdv, client, "signature_or", subject, ok)
    return ok


# ── Notification facture (avec PDF en PJ) ────────────────────────

def notifier_facture(
    db: Session,
    rdv_id: int,
    facture_id: int,
    is_paid: bool = False,
    pdf_bytes: bytes | None = None,
) -> bool:
    """Envoie la facture par email avec PDF en pièce jointe."""
    rdv, client = _get_client_rdv(db, rdv_id)
    if not client:
        return False

    facture = db.query(Facture).filter(Facture.id == facture_id).first()
    if not facture:
        return False

    info = _get_atelier(db, rdv.atelier_id or 1)
    veh = _vehicule_info(db, rdv.vehicule_id)

    montant = f"{float(facture.total_ttc):.2f}" if facture.total_ttc else "0.00"
    numero = facture.numero_facture or ""

    subject, html_body, text_body = render_facture_email(
        client_prenom=client.prenom or "",
        atelier_nom=info["nom"],
        numero_facture=numero,
        montant_ttc=montant,
        vehicule_info=veh,
        atelier_telephone=info["telephone"],
        atelier_adresse=info["adresse"],
        logo_url=info["logo_url"],
        atelier_email=info["email"],
        is_paid=is_paid,
        token_suivi=getattr(rdv, "token_suivi", "") or "",
        date_rdv=rdv.date_rdv.strftime("%d/%m/%Y") if rdv.date_rdv else "",
        type_intervention=rdv.type_intervention or "",
    )

    attachments = []
    if pdf_bytes:
        attachments.append({
            "filename": f"facture_{numero}.pdf",
            "content": pdf_bytes,
            "mime_type": "application/pdf",
        })

    ok = send_email(
        client.email, subject, html_body, text_body,
        atelier_id=rdv.atelier_id,
        attachments=attachments,
    )
    type_r = "facture_payee" if is_paid else "facture"
    _log_email(db, rdv, client, type_r, subject, ok)
    return ok


# ── Calcul heure de fin avec pause midi ──────────────────────────

def _compute_heure_fin(db: Session, atelier_id: int, rdv) -> str:
    """Compute effective end time string (HHhMM) accounting for lunch break."""
    if not rdv.heure_rdv or not rdv.temps_estime:
        return ""
    start_min = rdv.heure_rdv.hour * 60 + rdv.heure_rdv.minute
    duration = int(rdv.temps_estime or 60)
    end_min = start_min + duration

    horaire = db.query(HoraireAtelier).filter(
        HoraireAtelier.atelier_id == atelier_id,
        HoraireAtelier.jour_semaine == rdv.date_rdv.weekday(),
    ).first() if rdv.date_rdv else None

    if horaire and horaire.pause_debut and horaire.pause_fin:
        try:
            pause_s = int(str(horaire.pause_debut)[:2]) * 60 + int(str(horaire.pause_debut)[3:5])
            pause_e = int(str(horaire.pause_fin)[:2]) * 60 + int(str(horaire.pause_fin)[3:5])
            if pause_e > pause_s and start_min < pause_s and end_min > pause_s:
                end_min += (pause_e - pause_s)
        except Exception:
            pass

    h, m = divmod(end_min, 60)
    return f"{h:02d}h{m:02d}"


# ── Notification déplacement de RDV ──────────────────────────────

def notifier_deplacement_rdv(
    db: Session,
    rdv_id: int,
    ancienne_date: str,
    ancienne_heure: str,
) -> bool:
    """Envoie un email au client quand son RDV est déplacé (nouvelle date/heure)."""
    rdv, client = _get_client_rdv(db, rdv_id)
    if not client:
        return False

    info = _get_atelier(db, rdv.atelier_id or 1)
    veh = _vehicule_info(db, rdv.vehicule_id)

    nouvelle_date = rdv.date_rdv.strftime("%d/%m/%Y") if rdv.date_rdv else ""
    nouvelle_heure = rdv.heure_rdv.strftime("%Hh%M") if rdv.heure_rdv else ""
    heure_fin = _compute_heure_fin(db, rdv.atelier_id or 1, rdv)

    subject, html_body, text_body = render_deplacement_rdv(
        client_prenom=client.prenom or "",
        ancienne_date=ancienne_date,
        ancienne_heure=ancienne_heure,
        nouvelle_date=nouvelle_date,
        nouvelle_heure=nouvelle_heure,
        type_intervention=rdv.type_intervention or "",
        atelier_nom=info["nom"],
        atelier_telephone=info["telephone"],
        atelier_adresse=info["adresse"],
        logo_url=info["logo_url"],
        atelier_email=info["email"],
        vehicule_info=veh,
        token_suivi=getattr(rdv, "token_suivi", "") or "",
        heure_fin=heure_fin,
    )

    ok = send_email(client.email, subject, html_body, text_body, atelier_id=rdv.atelier_id)
    _log_email(db, rdv, client, "deplacement_rdv", subject, ok)
    logger.info("Email deplacement rdv=%s ok=%s", rdv_id, ok)
    return ok
