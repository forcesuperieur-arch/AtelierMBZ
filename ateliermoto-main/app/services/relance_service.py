"""Service de relance des clients inactifs.

Envoie automatiquement des emails de relance aux clients qui n'ont pas
eu de RDV depuis 6, 9 ou 12 mois. Maximum 3 relances par client.
Le scheduler tourne en tâche de fond (asyncio) et vérifie une fois par jour.
"""

import asyncio
import logging
from datetime import datetime, timedelta

from sqlalchemy import func
from sqlalchemy.orm import Session

from models import (
    Atelier, Client, RappelEmail, RendezVous, SessionLocal, Vehicule,
)
from services.email_service import render_relance_client, send_email

logger = logging.getLogger("ateliermoto.relance")

# Seuils de relance (en jours) et niveaux
RELANCE_SEUILS = [
    (180, 1),   # 6 mois -> relance niveau 1
    (270, 2),   # 9 mois -> relance niveau 2
    (365, 3),   # 12 mois -> relance niveau 3
]
MAX_RELANCES = 3


def _build_atelier_adresse(atelier) -> str:
    if not atelier:
        return ""
    parts = [p for p in [atelier.adresse, atelier.cp, atelier.ville] if p]
    return ", ".join(parts)


def _vehicule_info_str(vehicule) -> str:
    if not vehicule:
        return ""
    parts = [p for p in [vehicule.marque, vehicule.modele, vehicule.plaque] if p]
    return " — ".join(parts)


def _count_relances_sent(db: Session, client_id: int) -> int:
    """Compte le nombre de relances déjà envoyées à ce client."""
    return db.query(RappelEmail).filter(
        RappelEmail.client_id == client_id,
        RappelEmail.type_rappel.like("relance_%"),
        RappelEmail.statut == "envoye",
    ).count()


def _derniere_activite(db: Session, client_id: int, atelier_id: int) -> datetime | None:
    """Retourne la date du dernier RDV terminé/payé d'un client."""
    result = db.query(func.max(RendezVous.updated_at)).filter(
        RendezVous.client_id == client_id,
        RendezVous.atelier_id == atelier_id,
        RendezVous.statut.in_(["paye", "restitue", "facture", "termine"]),
    ).scalar()
    return result


def traiter_relances_atelier(db: Session, atelier_id: int) -> int:
    """Envoie les relances pour un atelier donné. Retourne le nb d'emails envoyés."""
    atelier = db.query(Atelier).filter(Atelier.id == atelier_id, Atelier.actif == True).first()  # noqa: E712
    if not atelier:
        return 0

    now = datetime.now()
    count = 0

    # Clients avec au moins 1 RDV passé payé/terminé
    clients = (
        db.query(Client)
        .filter(Client.atelier_id == atelier_id, Client.email.isnot(None), Client.email != "")
        .all()
    )

    for client in clients:
        derniere = _derniere_activite(db, client.id, atelier_id)
        if not derniere:
            continue

        jours_inactif = (now - derniere).days
        relances_envoyees = _count_relances_sent(db, client.id)

        if relances_envoyees >= MAX_RELANCES:
            continue

        # Déterminer le niveau de relance approprié
        niveau_cible = None
        for seuil_jours, niveau in RELANCE_SEUILS:
            if jours_inactif >= seuil_jours and relances_envoyees < niveau:
                niveau_cible = niveau

        if niveau_cible is None:
            continue

        # Vérifier qu'on n'a pas déjà envoyé ce niveau
        deja_niveau = db.query(RappelEmail).filter(
            RappelEmail.client_id == client.id,
            RappelEmail.type_rappel == f"relance_{niveau_cible}",
            RappelEmail.statut == "envoye",
        ).count()
        if deja_niveau > 0:
            continue

        # Trouver le dernier véhicule du client
        vehicule = db.query(Vehicule).filter(Vehicule.client_id == client.id).first()
        veh_str = _vehicule_info_str(vehicule)

        subject, html_body, text_body = render_relance_client(
            client_prenom=client.prenom or client.nom or "Client",
            atelier_nom=atelier.nom or "Atelier Moto",
            niveau_relance=niveau_cible,
            vehicule_info=veh_str,
            atelier_telephone=atelier.telephone or "",
            atelier_adresse=_build_atelier_adresse(atelier),
            logo_url=atelier.logo_url or "",
            atelier_email=atelier.email or "",
        )

        ok = send_email(client.email, subject, html_body, text_body, atelier_id=atelier_id)

        # Journaliser (rdv_id = 0 car pas lié à un RDV spécifique)
        last_rdv = (
            db.query(RendezVous)
            .filter(RendezVous.client_id == client.id, RendezVous.atelier_id == atelier_id)
            .order_by(RendezVous.date_rdv.desc())
            .first()
        )
        rappel = RappelEmail(
            atelier_id=atelier_id,
            rdv_id=last_rdv.id if last_rdv else 0,
            client_id=client.id,
            type_rappel=f"relance_{niveau_cible}",
            destinataire=client.email,
            sujet=subject,
            statut="envoye" if ok else "erreur",
            date_envoi_prevu=now,
            date_envoi_reel=now if ok else None,
            erreur=None if ok else "Échec SMTP",
        )
        db.add(rappel)
        db.commit()

        if ok:
            count += 1
            logger.info("Relance niveau %d envoyée à %s (client_id=%s)", niveau_cible, client.email, client.id)

    return count


def _process_all_relances() -> int:
    """Traite les relances pour tous les ateliers actifs."""
    db = SessionLocal()
    try:
        ateliers = db.query(Atelier).filter(Atelier.actif == 1).all()
        total = 0
        for atelier in ateliers:
            total += traiter_relances_atelier(db, atelier.id)
        return total
    except Exception:
        logger.exception("Erreur dans le traitement des relances")
        return 0
    finally:
        db.close()


# ── Scheduler asyncio (tâche de fond) ───────────────────────────

_relance_task: asyncio.Task | None = None


async def _relance_loop():
    """Boucle une fois par jour (à 10h) pour les relances clients inactifs."""
    logger.info("Scheduler relances clients démarré")
    while True:
        try:
            now = datetime.now()
            # Calculer le temps jusqu'à demain 10h
            demain_10h = now.replace(hour=10, minute=0, second=0, microsecond=0)
            if now.hour >= 10:
                demain_10h += timedelta(days=1)
            wait_seconds = (demain_10h - now).total_seconds()
            await asyncio.sleep(wait_seconds)

            count = _process_all_relances()
            if count:
                logger.info("Relances envoyées: %d", count)
        except asyncio.CancelledError:
            break
        except Exception:
            logger.exception("Erreur scheduler relances")
            await asyncio.sleep(3600)  # Retry dans 1h


def start_relance_scheduler():
    """Démarre le scheduler de relances."""
    global _relance_task
    if _relance_task is None or _relance_task.done():
        loop = asyncio.get_event_loop()
        _relance_task = loop.create_task(_relance_loop())
        logger.info("Tâche scheduler relances créée")


def stop_relance_scheduler():
    """Arrête le scheduler proprement."""
    global _relance_task
    if _relance_task and not _relance_task.done():
        _relance_task.cancel()
        _relance_task = None
        logger.info("Scheduler relances arrêté")
