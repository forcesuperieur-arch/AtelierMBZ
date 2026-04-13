"""Programmation et envoi des rappels email pour les rendez-vous.

Le scheduler tourne en tâche de fond (asyncio) et vérifie
toutes les 60 secondes les rappels à envoyer.
"""

import asyncio
import logging
from datetime import datetime, timedelta

from sqlalchemy.orm import Session

from models import (
    Atelier, Client, HoraireAtelier, Mecanicien, RappelEmail, RendezVous, SessionLocal, Vehicule,
)
from services.email_service import (
    render_confirmation_rdv,
    render_rappel_rdv,
    render_rappel_rdv_enhanced,
    send_email,
)

logger = logging.getLogger("ateliermoto.rappels")


def _build_atelier_adresse(atelier) -> str:
    """Build formatted address from atelier model."""
    if not atelier:
        return ""
    parts = [p for p in [atelier.adresse, atelier.cp, atelier.ville] if p]
    return ", ".join(parts)


def _build_vehicule_info(db: Session, vehicule_id: int | None) -> str:
    """Build vehicle display string."""
    if not vehicule_id:
        return ""
    v = db.query(Vehicule).filter(Vehicule.id == vehicule_id).first()
    if not v:
        return ""
    parts = [p for p in [v.marque, v.modele, v.plaque] if p]
    return " — ".join(parts)


def _get_mecanicien_nom(db: Session, mecanicien_id: int | None) -> str:
    if not mecanicien_id:
        return ""
    mec = db.query(Mecanicien).filter(Mecanicien.id == mecanicien_id).first()
    return f"{mec.prenom or ''} {mec.nom or ''}".strip() if mec else ""


def _get_atelier_info(db: Session, atelier_id: int) -> dict:
    """Return atelier info dict for email templates."""
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


# ── helpers publics ──────────────────────────────────────────────

def programmer_rappels_rdv(db: Session, rdv_id: int) -> list[RappelEmail]:
    """Programme les rappels automatiques (48h, 24h, 4h) pour un RDV confirmé.

    Idempotent : supprime d'abord les rappels "programme" existants
    pour ce RDV avant d'en recréer.
    """
    rdv = db.query(RendezVous).filter(RendezVous.id == rdv_id).first()
    if not rdv or not rdv.client_id:
        return []

    client = db.query(Client).filter(Client.id == rdv.client_id).first()
    if not client or not client.email:
        logger.info("Pas d'email client pour rdv_id=%s, rappels non programmés", rdv_id)
        return []

    atelier_id = rdv.atelier_id or 1

    # Supprimer les rappels programmés existants (pas ceux déjà envoyés)
    db.query(RappelEmail).filter(
        RappelEmail.rdv_id == rdv_id,
        RappelEmail.statut == "programme",
        RappelEmail.type_rappel.in_(["rappel_48h", "rappel_24h", "rappel_4h", "rappel_j3", "rappel_j1"]),
    ).delete(synchronize_session=False)

    rdv_datetime = datetime.combine(rdv.date_rdv, rdv.heure_rdv)
    now = datetime.now()
    rappels = []

    # Rappel 48h avant
    r48h = rdv_datetime - timedelta(hours=48)
    if r48h > now:
        r = RappelEmail(
            atelier_id=atelier_id,
            rdv_id=rdv.id,
            client_id=client.id,
            type_rappel="rappel_48h",
            destinataire=client.email,
            sujet="Rappel : rendez-vous dans 2 jours",
            date_envoi_prevu=r48h,
        )
        db.add(r)
        rappels.append(r)

    # Rappel 24h avant
    r24h = rdv_datetime - timedelta(hours=24)
    if r24h > now:
        r = RappelEmail(
            atelier_id=atelier_id,
            rdv_id=rdv.id,
            client_id=client.id,
            type_rappel="rappel_24h",
            destinataire=client.email,
            sujet="Rappel : rendez-vous demain",
            date_envoi_prevu=r24h,
        )
        db.add(r)
        rappels.append(r)

    # Rappel 4h avant
    r4h = rdv_datetime - timedelta(hours=4)
    if r4h > now:
        r = RappelEmail(
            atelier_id=atelier_id,
            rdv_id=rdv.id,
            client_id=client.id,
            type_rappel="rappel_4h",
            destinataire=client.email,
            sujet="Rappel : rendez-vous dans 4 heures",
            date_envoi_prevu=r4h,
        )
        db.add(r)
        rappels.append(r)

    db.commit()
    logger.info("Programmé %d rappel(s) pour rdv_id=%s", len(rappels), rdv_id)
    return rappels


def envoyer_confirmation(db: Session, rdv_id: int) -> bool:
    """Envoie un email de confirmation immédiat pour un RDV."""
    rdv = db.query(RendezVous).filter(RendezVous.id == rdv_id).first()
    if not rdv or not rdv.client_id:
        return False

    client = db.query(Client).filter(Client.id == rdv.client_id).first()
    if not client or not client.email:
        return False

    atelier = db.query(Atelier).filter(Atelier.id == (rdv.atelier_id or 1)).first()
    atelier_nom = atelier.nom if atelier else "Atelier Moto"
    atelier_tel = (atelier.telephone if atelier else "") or ""

    date_str = rdv.date_rdv.strftime("%d/%m/%Y") if rdv.date_rdv else ""
    heure_str = rdv.heure_rdv.strftime("%Hh%M") if rdv.heure_rdv else ""
    heure_fin_str = _compute_heure_fin(db, rdv.atelier_id or 1, rdv)

    subject, html_body, text_body = render_confirmation_rdv(
        client_prenom=client.prenom or "",
        date_rdv=date_str,
        heure_rdv=heure_str,
        type_intervention=rdv.type_intervention or "",
        atelier_nom=atelier_nom,
        atelier_telephone=atelier_tel,
        token_suivi=getattr(rdv, "token_suivi", "") or "",
        logo_url=(atelier.logo_url if atelier else "") or "",
        atelier_adresse=_build_atelier_adresse(atelier),
        atelier_email=(atelier.email if atelier else "") or "",
        vehicule_info=_build_vehicule_info(db, rdv.vehicule_id),
        heure_fin=heure_fin_str,
    )

    ok = send_email(client.email, subject, html_body, text_body)

    # Journaliser
    rappel = RappelEmail(
        atelier_id=rdv.atelier_id or 1,
        rdv_id=rdv.id,
        client_id=client.id,
        type_rappel="confirmation",
        destinataire=client.email,
        sujet=subject,
        statut="envoye" if ok else "erreur",
        date_envoi_prevu=datetime.now(),
        date_envoi_reel=datetime.now() if ok else None,
    )
    db.add(rappel)
    db.commit()
    return ok


def envoyer_rappel_manuel(db: Session, rdv_id: int) -> bool:
    """Envoie un rappel immédiat (déclenché manuellement)."""
    rdv = db.query(RendezVous).filter(RendezVous.id == rdv_id).first()
    if not rdv or not rdv.client_id:
        return False

    client = db.query(Client).filter(Client.id == rdv.client_id).first()
    if not client or not client.email:
        return False

    atelier = db.query(Atelier).filter(Atelier.id == (rdv.atelier_id or 1)).first()
    atelier_nom = atelier.nom if atelier else "Atelier Moto"
    atelier_tel = (atelier.telephone if atelier else "") or ""
    atelier_adr = _build_atelier_adresse(atelier)

    date_str = rdv.date_rdv.strftime("%d/%m/%Y") if rdv.date_rdv else ""
    heure_str = rdv.heure_rdv.strftime("%Hh%M") if rdv.heure_rdv else ""

    subject, html_body, text_body = render_rappel_rdv(
        client_prenom=client.prenom or "",
        client_nom=client.nom or "",
        date_rdv=date_str,
        heure_rdv=heure_str,
        type_intervention=rdv.type_intervention or "",
        atelier_nom=atelier_nom,
        atelier_telephone=atelier_tel,
        atelier_adresse=atelier_adr,
        logo_url=(atelier.logo_url if atelier else "") or "",
        atelier_email=(atelier.email if atelier else "") or "",
        vehicule_info=_build_vehicule_info(db, rdv.vehicule_id),
        token_suivi=getattr(rdv, "token_suivi", "") or "",
    )

    ok = send_email(client.email, subject, html_body, text_body)

    rappel = RappelEmail(
        atelier_id=rdv.atelier_id or 1,
        rdv_id=rdv.id,
        client_id=client.id,
        type_rappel="manuel",
        destinataire=client.email,
        sujet=subject,
        statut="envoye" if ok else "erreur",
        date_envoi_prevu=datetime.now(),
        date_envoi_reel=datetime.now() if ok else None,
    )
    db.add(rappel)
    db.commit()
    return ok


def _process_pending_rappels() -> int:
    """Traite les rappels dont la date d'envoi prévue est passée.

    Retourne le nombre de rappels traités.
    """
    db = SessionLocal()
    try:
        now = datetime.now()
        pending = (
            db.query(RappelEmail)
            .filter(
                RappelEmail.statut == "programme",
                RappelEmail.date_envoi_prevu <= now,
            )
            .limit(50)
            .all()
        )

        _DELAI_LABELS = {
            "rappel_48h": "dans 2 jours",
            "rappel_24h": "demain",
            "rappel_4h": "dans 4 heures",
            "rappel_j3": "dans 3 jours",
            "rappel_j1": "demain",
        }

        count = 0
        for rappel in pending:
            rdv = db.query(RendezVous).filter(RendezVous.id == rappel.rdv_id).first()
            if not rdv or rdv.statut in ("annule", "restitue", "facture", "paye"):
                rappel.statut = "erreur"
                rappel.erreur = f"RDV statut={rdv.statut if rdv else 'supprimé'}"
                db.commit()
                continue

            client = db.query(Client).filter(Client.id == rappel.client_id).first()
            if not client or not client.email:
                rappel.statut = "erreur"
                rappel.erreur = "Pas d'email client"
                db.commit()
                continue

            info = _get_atelier_info(db, rappel.atelier_id)

            date_str = rdv.date_rdv.strftime("%d/%m/%Y") if rdv.date_rdv else ""
            heure_str = rdv.heure_rdv.strftime("%Hh%M") if rdv.heure_rdv else ""
            vehicule_info = _build_vehicule_info(db, rdv.vehicule_id)
            mecanicien_nom = _get_mecanicien_nom(db, rdv.mecanicien_id)
            delai = _DELAI_LABELS.get(rappel.type_rappel, "")
            heure_fin_str = _compute_heure_fin(db, rappel.atelier_id, rdv)

            subject, html_body, text_body = render_rappel_rdv_enhanced(
                client_prenom=client.prenom or "",
                date_rdv=date_str,
                heure_rdv=heure_str,
                type_intervention=rdv.type_intervention or "",
                atelier_nom=info["nom"],
                atelier_telephone=info["telephone"],
                atelier_adresse=info["adresse"],
                logo_url=info["logo_url"],
                atelier_email=info["email"],
                token_suivi=getattr(rdv, "token_suivi", "") or "",
                delai=delai,
                vehicule_info=vehicule_info,
                mecanicien_nom=mecanicien_nom,
                heure_fin=heure_fin_str,
            )

            ok = send_email(client.email, subject, html_body, text_body, atelier_id=rappel.atelier_id)
            rappel.statut = "envoye" if ok else "erreur"
            rappel.date_envoi_reel = datetime.now() if ok else None
            if not ok:
                rappel.erreur = "Échec SMTP"
            db.commit()
            count += 1

        return count
    except Exception:
        logger.exception("Erreur dans le traitement des rappels")
        return 0
    finally:
        db.close()


# ── Scheduler asyncio (tâche de fond) ───────────────────────────

_scheduler_task: asyncio.Task | None = None


async def _rappel_loop():
    """Boucle toutes les 60s pour envoyer les rappels programmés."""
    logger.info("Scheduler rappels email démarré")
    while True:
        try:
            count = _process_pending_rappels()
            if count:
                logger.info("Rappels traités: %d", count)
        except Exception:
            logger.exception("Erreur scheduler rappels")
        await asyncio.sleep(60)


def start_rappel_scheduler():
    """Démarre le scheduler (appeler depuis le lifespan FastAPI)."""
    global _scheduler_task
    if _scheduler_task is None or _scheduler_task.done():
        loop = asyncio.get_event_loop()
        _scheduler_task = loop.create_task(_rappel_loop())
        logger.info("Tâche scheduler rappels créée")


def stop_rappel_scheduler():
    """Arrête le scheduler proprement."""
    global _scheduler_task
    if _scheduler_task and not _scheduler_task.done():
        _scheduler_task.cancel()
        _scheduler_task = None
        logger.info("Scheduler rappels arrêté")
