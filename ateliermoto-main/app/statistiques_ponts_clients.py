"""Pont, interventions and clients endpoints for statistiques API."""

from datetime import date, timedelta
from typing import Optional

from fastapi import APIRouter, Depends
from sqlalchemy import func
from sqlalchemy.orm import Session

from auth import User, get_current_user
from models import Client, Pont, RendezVous, get_db
from statistiques_common import get_date_range, tenant_id

router = APIRouter(tags=["statistiques"])


@router.get("/ponts")
def get_ponts_stats(db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    return get_occupation_ponts(db=db, current_user=current_user)


@router.get("/ponts/occupation")
def get_occupation_ponts(
    date_debut: Optional[date] = None,
    date_fin: Optional[date] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    atelier_id = tenant_id(current_user)
    if not date_debut:
        date_debut = date.today() - timedelta(days=date.today().weekday())
    if not date_fin:
        date_fin = date.today()

    ponts = db.query(Pont).filter(Pont.is_active == 1, Pont.atelier_id == atelier_id).all()
    heures_ouverture_jour = 10
    nb_jours = (date_fin - date_debut).days + 1
    heures_total = heures_ouverture_jour * nb_jours

    result = []
    for pont in ponts:
        rdvs = db.query(RendezVous).filter(
            RendezVous.atelier_id == atelier_id,
            RendezVous.pont_id == pont.id,
            RendezVous.date_rdv >= date_debut,
            RendezVous.date_rdv <= date_fin,
            RendezVous.statut.in_(["confirme", "reception", "en_cours", "termine", "facture", "paye"]),
        ).all()
        heures_occupees = sum(rdv.temps_final or rdv.temps_estime or 60 for rdv in rdvs) / 60
        taux = (heures_occupees / heures_total * 100) if heures_total > 0 else 0
        result.append(
            {
                "pont_id": pont.id,
                "pont_nom": pont.nom,
                "heures_occupees": round(heures_occupees, 1),
                "heures_total": heures_total,
                "taux_occupation": round(min(taux, 100), 1),
                "nb_rdv": len(rdvs),
            }
        )

    return {
        "date_debut": date_debut.isoformat(),
        "date_fin": date_fin.isoformat(),
        "heures_ouverture_jour": heures_ouverture_jour,
        "ponts": result,
    }


@router.get("/interventions/top")
def get_top_interventions(
    limit: int = 5,
    periode: Optional[str] = "mois",
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    atelier_id = tenant_id(current_user)
    date_debut, date_fin = get_date_range(periode)
    results = db.query(
        RendezVous.type_intervention,
        func.count(RendezVous.id).label("count"),
        func.coalesce(func.sum(RendezVous.prix_final), 0.0).label("ca_total"),
    ).filter(
        RendezVous.atelier_id == atelier_id,
        RendezVous.date_rdv >= date_debut,
        RendezVous.date_rdv <= date_fin,
    ).group_by(
        RendezVous.type_intervention,
    ).order_by(
        func.count(RendezVous.id).desc(),
    ).limit(limit).all()
    return {
        "periode": periode,
        "date_debut": date_debut.isoformat(),
        "date_fin": date_fin.isoformat(),
        "top_interventions": [
            {"type_intervention": r.type_intervention, "count": r.count, "ca_total": round(r.ca_total or 0, 2)}
            for r in results
        ],
    }


@router.get("/clients/fideles")
def get_clients_fideles(
    limit: int = 10,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    atelier_id = tenant_id(current_user)
    subq = db.query(
        RendezVous.client_id,
        func.count(RendezVous.id).label("nb_rdv"),
        func.coalesce(func.sum(RendezVous.prix_final), 0.0).label("ca_total"),
        func.max(RendezVous.date_rdv).label("dernier_rdv"),
    ).filter(
        RendezVous.atelier_id == atelier_id,
        RendezVous.statut.in_(["termine", "facture", "paye"]),
    ).group_by(
        RendezVous.client_id,
    ).subquery()

    results = db.query(Client, subq.c.nb_rdv, subq.c.ca_total, subq.c.dernier_rdv).join(
        subq, Client.id == subq.c.client_id
    ).filter(
        Client.atelier_id == atelier_id
    ).order_by(
        subq.c.ca_total.desc()
    ).limit(limit).all()

    return {
        "clients_fideles": [
            {
                "client_id": r.Client.id,
                "nom": r.Client.nom,
                "prenom": r.Client.prenom,
                "telephone": r.Client.telephone,
                "nb_rdv": r.nb_rdv,
                "ca_total": round(r.ca_total or 0, 2),
                "dernier_rdv": r.dernier_rdv.isoformat() if r.dernier_rdv else None,
            }
            for r in results
        ]
    }


@router.get("/evolution-mensuelle")
def get_evolution_mensuelle(
    mois: int = 12,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    atelier_id = tenant_id(current_user)
    today = date.today()
    result = []
    for i in range(mois - 1, -1, -1):
        month_date = today - timedelta(days=i * 30)
        year = month_date.year
        month = month_date.month
        next_month = date(year + 1, 1, 1) if month == 12 else date(year, month + 1, 1)
        month_start = date(year, month, 1)
        month_end = next_month - timedelta(days=1)
        ca = db.query(func.coalesce(func.sum(RendezVous.prix_final), 0.0)).filter(
            RendezVous.atelier_id == atelier_id,
            RendezVous.date_rdv >= month_start,
            RendezVous.date_rdv <= month_end,
            RendezVous.statut.in_(["termine", "facture", "paye"]),
        ).scalar() or 0
        nb_rdv = db.query(RendezVous).filter(
            RendezVous.atelier_id == atelier_id,
            RendezVous.date_rdv >= month_start,
            RendezVous.date_rdv <= month_end,
        ).count()
        result.append({"mois": f"{year}-{month:02d}", "mois_nom": month_date.strftime("%b %Y"), "ca": round(ca, 2), "nb_rdv": nb_rdv})
    return {"evolution": result}
