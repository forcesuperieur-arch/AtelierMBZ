"""API endpoints pour la gestion des rappels email.

Endpoints:
- GET  /api/rappels/rdv/{rdv_id}           — historique rappels d'un RDV
- POST /api/rappels/rdv/{rdv_id}/envoyer   — envoyer un rappel manuel
- GET  /api/rappels/stats                   — statistiques des rappels
- GET  /api/rappels/pending                 — rappels en attente
- POST /api/rappels/{rappel_id}/annuler     — annuler un rappel programmé
- GET  /api/email/test                      — envoyer un email de test
"""

import logging
from datetime import datetime

from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session

from auth import get_current_user
from models import Atelier, Client, RappelEmail, RendezVous, User, get_db
from routes.auth_api import user_has_permission
from services.email_service import send_email
from services.rappel_service import envoyer_rappel_manuel, programmer_rappels_rdv

logger = logging.getLogger("ateliermoto.api.rappels")
router = APIRouter(tags=["Rappels Email"])


def _atelier_id(user: User) -> int:
    raw = getattr(user, "atelier_id", None)
    try:
        v = int(raw)
        return v if v > 0 else 1
    except (TypeError, ValueError):
        return 1


def _ensure_perm(user: User, db: Session, perm: str):
    if user.role == "super_admin":
        return
    if not user_has_permission(user, db, perm):
        raise HTTPException(status_code=403, detail=f"Permission {perm} requise")


@router.get("/api/rappels/rdv/{rdv_id}")
def get_rappels_rdv(
    rdv_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Historique des rappels pour un RDV donné."""
    _ensure_perm(current_user, db, "rdv.view")
    atelier_id = _atelier_id(current_user)
    rdv = db.query(RendezVous).filter(
        RendezVous.id == rdv_id, RendezVous.atelier_id == atelier_id
    ).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="RDV non trouvé")

    rappels = (
        db.query(RappelEmail)
        .filter(RappelEmail.rdv_id == rdv_id, RappelEmail.atelier_id == atelier_id)
        .order_by(RappelEmail.date_envoi_prevu.desc())
        .all()
    )

    return [
        {
            "id": r.id,
            "type": r.type_rappel,
            "destinataire": r.destinataire,
            "sujet": r.sujet,
            "statut": r.statut,
            "erreur": r.erreur,
            "date_envoi_prevu": r.date_envoi_prevu.isoformat() if r.date_envoi_prevu else None,
            "date_envoi_reel": r.date_envoi_reel.isoformat() if r.date_envoi_reel else None,
        }
        for r in rappels
    ]


@router.post("/api/rappels/rdv/{rdv_id}/envoyer")
def envoyer_rappel(
    rdv_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Envoie un rappel manuellement pour un RDV."""
    _ensure_perm(current_user, db, "rdv.edit")
    atelier_id = _atelier_id(current_user)
    rdv = db.query(RendezVous).filter(
        RendezVous.id == rdv_id, RendezVous.atelier_id == atelier_id
    ).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="RDV non trouvé")

    if rdv.statut in ("annule", "restitue", "facture", "paye"):
        raise HTTPException(status_code=400, detail=f"Impossible d'envoyer un rappel pour un RDV en statut {rdv.statut}")

    ok = envoyer_rappel_manuel(db, rdv_id)
    if not ok:
        raise HTTPException(status_code=400, detail="Pas d'email client ou erreur d'envoi")

    return {"message": "Rappel envoyé", "rdv_id": rdv_id}


@router.get("/api/rappels/stats")
def get_rappels_stats(
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Statistiques des rappels pour l'atelier courant."""
    _ensure_perm(current_user, db, "rdv.view")
    atelier_id = _atelier_id(current_user)

    total = db.query(RappelEmail).filter(RappelEmail.atelier_id == atelier_id).count()
    envoyes = db.query(RappelEmail).filter(
        RappelEmail.atelier_id == atelier_id, RappelEmail.statut == "envoye"
    ).count()
    erreurs = db.query(RappelEmail).filter(
        RappelEmail.atelier_id == atelier_id, RappelEmail.statut == "erreur"
    ).count()
    programmes = db.query(RappelEmail).filter(
        RappelEmail.atelier_id == atelier_id, RappelEmail.statut == "programme"
    ).count()

    return {
        "total": total,
        "envoyes": envoyes,
        "erreurs": erreurs,
        "programmes": programmes,
    }


@router.get("/api/rappels/pending")
def get_rappels_pending(
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Rappels en attente d'envoi pour l'atelier courant."""
    _ensure_perm(current_user, db, "rdv.view")
    atelier_id = _atelier_id(current_user)

    rappels = (
        db.query(RappelEmail)
        .filter(
            RappelEmail.atelier_id == atelier_id,
            RappelEmail.statut == "programme",
        )
        .order_by(RappelEmail.date_envoi_prevu.asc())
        .limit(50)
        .all()
    )

    results = []
    for r in rappels:
        client = db.query(Client).filter(Client.id == r.client_id).first()
        results.append({
            "id": r.id,
            "rdv_id": r.rdv_id,
            "type": r.type_rappel,
            "destinataire": r.destinataire,
            "client_nom": f"{client.prenom} {client.nom}" if client else "",
            "date_envoi_prevu": r.date_envoi_prevu.isoformat() if r.date_envoi_prevu else None,
        })

    return results


@router.post("/api/rappels/{rappel_id}/annuler")
def annuler_rappel(
    rappel_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Annule un rappel programmé."""
    _ensure_perm(current_user, db, "rdv.edit")
    atelier_id = _atelier_id(current_user)

    rappel = db.query(RappelEmail).filter(
        RappelEmail.id == rappel_id, RappelEmail.atelier_id == atelier_id
    ).first()
    if not rappel:
        raise HTTPException(status_code=404, detail="Rappel non trouvé")
    if rappel.statut != "programme":
        raise HTTPException(status_code=400, detail="Seuls les rappels programmés peuvent être annulés")

    db.delete(rappel)
    db.commit()
    return {"message": "Rappel annulé"}


@router.get("/api/email/test")
def send_test_email(
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Envoie un email de test à l'utilisateur connecté."""
    _ensure_perm(current_user, db, "config.manage")

    if not current_user.email:
        raise HTTPException(status_code=400, detail="Pas d'email configuré pour votre compte")

    atelier = db.query(Atelier).filter(Atelier.id == _atelier_id(current_user)).first()
    atelier_nom = atelier.nom if atelier else "Atelier Moto"

    html = f"""<div style="font-family:Arial,sans-serif;padding:20px">
    <h2 style="color:#FB923C">{atelier_nom} — Test email</h2>
    <p>Si vous recevez cet email, la configuration SMTP est correcte.</p>
    <p style="color:#888">Envoyé le {datetime.now().strftime('%d/%m/%Y à %Hh%M')}</p>
    </div>"""

    ok = send_email(
        current_user.email,
        f"[TEST] Configuration email — {atelier_nom}",
        html,
        f"Test email {atelier_nom} — configuration OK",
    )

    if not ok:
        raise HTTPException(status_code=500, detail="Échec envoi email — vérifiez la configuration SMTP")

    return {"message": f"Email de test envoyé à {current_user.email}"}


# ── Envoi manuel de facture par email ────────────────────────────

@router.post("/api/email/facture/{facture_id}")
def envoyer_facture_email(
    facture_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Envoie (ou renvoie) la facture par email au client avec PDF en PJ."""
    _ensure_perm(current_user, db, "billing.view")
    atelier_id = _atelier_id(current_user)

    from models import Facture
    facture = db.query(Facture).filter(
        Facture.id == facture_id, Facture.atelier_id == atelier_id
    ).first()
    if not facture:
        raise HTTPException(status_code=404, detail="Facture non trouvée")

    rdv = db.query(RendezVous).filter(RendezVous.id == facture.rendez_vous_id).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="RDV associé non trouvé")

    client = db.query(Client).filter(Client.id == facture.client_id).first()
    if not client or not client.email:
        raise HTTPException(status_code=400, detail="Pas d'email client")

    from services.notification_service import notifier_facture
    from services.pdf_service import generate_facture_pdf_bytes
    pdf_bytes = generate_facture_pdf_bytes(db, facture.id, atelier_id)
    is_paid = facture.statut == "payee"
    ok = notifier_facture(db, rdv.id, facture.id, is_paid=is_paid, pdf_bytes=pdf_bytes)

    if not ok:
        raise HTTPException(status_code=500, detail="Erreur d'envoi email")

    return {"message": f"Facture envoyée à {client.email}"}


# ── Envoi manuel notification statut ─────────────────────────────

@router.post("/api/email/notification/{rdv_id}")
def envoyer_notification_statut(
    rdv_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Envoie manuellement un email de notification du statut actuel d'un RDV."""
    _ensure_perm(current_user, db, "rdv.edit")
    atelier_id = _atelier_id(current_user)

    rdv = db.query(RendezVous).filter(
        RendezVous.id == rdv_id, RendezVous.atelier_id == atelier_id
    ).first()
    if not rdv:
        raise HTTPException(status_code=404, detail="RDV non trouvé")

    from services.notification_service import notifier_changement_statut
    ok = notifier_changement_statut(db, rdv_id, rdv.statut)
    if not ok:
        raise HTTPException(status_code=400, detail="Pas d'email client ou erreur d'envoi")

    return {"message": f"Notification statut '{rdv.statut}' envoyée", "rdv_id": rdv_id}


# ── Relance manuelle ─────────────────────────────────────────────

@router.post("/api/email/relance")
def lancer_relances_manuelles(
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Déclenche manuellement le traitement des relances clients inactifs."""
    _ensure_perm(current_user, db, "config.manage")
    atelier_id = _atelier_id(current_user)

    from services.relance_service import traiter_relances_atelier
    count = traiter_relances_atelier(db, atelier_id)
    return {"message": f"{count} relance(s) envoyée(s)", "count": count}


# ── Historique emails d'un client ────────────────────────────────

@router.get("/api/email/client/{client_id}")
def get_emails_client(
    client_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    """Historique de tous les emails envoyés à un client."""
    _ensure_perm(current_user, db, "rdv.view")
    atelier_id = _atelier_id(current_user)

    rappels = (
        db.query(RappelEmail)
        .filter(RappelEmail.client_id == client_id, RappelEmail.atelier_id == atelier_id)
        .order_by(RappelEmail.created_at.desc())
        .limit(100)
        .all()
    )

    return [
        {
            "id": r.id,
            "rdv_id": r.rdv_id,
            "type": r.type_rappel,
            "destinataire": r.destinataire,
            "sujet": r.sujet,
            "statut": r.statut,
            "erreur": r.erreur,
            "date_envoi_prevu": r.date_envoi_prevu.isoformat() if r.date_envoi_prevu else None,
            "date_envoi_reel": r.date_envoi_reel.isoformat() if r.date_envoi_reel else None,
            "created_at": r.created_at.isoformat() if r.created_at else None,
        }
        for r in rappels
    ]
