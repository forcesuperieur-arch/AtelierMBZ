"""Revenue endpoints for statistiques API."""

from datetime import date, timedelta
from typing import Optional

from fastapi import APIRouter, Depends
from sqlalchemy import func
from sqlalchemy.orm import Session

from auth import User, get_current_user
from models import RendezVous, get_db
from statistiques_common import get_date_range, tenant_id

router = APIRouter(tags=["statistiques"])


@router.get("/ca")
def get_chiffre_affaires(
    periode: Optional[str] = None,
    date_debut: Optional[date] = None,
    date_fin: Optional[date] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    atelier_id = tenant_id(current_user)
    if periode:
        date_debut, date_fin = get_date_range(periode)
    elif not date_debut or not date_fin:
        date_debut = date.today()
        date_fin = date.today()

    ca = db.query(func.coalesce(func.sum(RendezVous.prix_final), 0.0)).filter(
        RendezVous.atelier_id == atelier_id,
        RendezVous.date_rdv >= date_debut,
        RendezVous.date_rdv <= date_fin,
        RendezVous.statut.in_(["termine", "facture", "paye"]),
    ).scalar() or 0

    nb_rdv = db.query(RendezVous).filter(
        RendezVous.atelier_id == atelier_id,
        RendezVous.date_rdv >= date_debut,
        RendezVous.date_rdv <= date_fin,
    ).count()
    panier_moyen = ca / nb_rdv if nb_rdv > 0 else 0

    return {
        "periode": periode or "custom",
        "date_debut": date_debut.isoformat(),
        "date_fin": date_fin.isoformat(),
        "chiffre_affaires": round(ca, 2),
        "nombre_rdv": nb_rdv,
        "panier_moyen": round(panier_moyen, 2),
    }


@router.get("/ca/comparatif")
def get_ca_comparatif(db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    atelier_id = tenant_id(current_user)
    today = date.today()

    ca_jour = db.query(func.coalesce(func.sum(RendezVous.prix_final), 0.0)).filter(
        RendezVous.atelier_id == atelier_id,
        RendezVous.date_rdv == today,
        RendezVous.statut.in_(["termine", "facture", "paye"]),
    ).scalar() or 0

    debut_semaine = today - timedelta(days=today.weekday())
    ca_semaine = db.query(func.coalesce(func.sum(RendezVous.prix_final), 0.0)).filter(
        RendezVous.atelier_id == atelier_id,
        RendezVous.date_rdv >= debut_semaine,
        RendezVous.date_rdv <= today,
        RendezVous.statut.in_(["termine", "facture", "paye"]),
    ).scalar() or 0

    debut_mois = today.replace(day=1)
    ca_mois = db.query(func.coalesce(func.sum(RendezVous.prix_final), 0.0)).filter(
        RendezVous.atelier_id == atelier_id,
        RendezVous.date_rdv >= debut_mois,
        RendezVous.date_rdv <= today,
        RendezVous.statut.in_(["termine", "facture", "paye"]),
    ).scalar() or 0

    debut_annee = today.replace(month=1, day=1)
    ca_annee = db.query(func.coalesce(func.sum(RendezVous.prix_final), 0.0)).filter(
        RendezVous.atelier_id == atelier_id,
        RendezVous.date_rdv >= debut_annee,
        RendezVous.date_rdv <= today,
        RendezVous.statut.in_(["termine", "facture", "paye"]),
    ).scalar() or 0

    return {
        "jour": round(ca_jour, 2),
        "semaine": round(ca_semaine, 2),
        "mois": round(ca_mois, 2),
        "annee": round(ca_annee, 2),
        "dates": {
            "jour": today.isoformat(),
            "semaine_debut": debut_semaine.isoformat(),
            "mois_debut": debut_mois.isoformat(),
            "annee_debut": debut_annee.isoformat(),
        },
    }
