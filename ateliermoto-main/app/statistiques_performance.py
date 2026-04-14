"""Performance endpoints for statistiques API."""

from datetime import date, timedelta
from typing import Optional

from fastapi import APIRouter, Depends
from sqlalchemy.orm import Session

from auth import User, get_current_user
from models import Pont, RendezVous, get_db
from statistiques_common import get_date_range, tenant_id
from statistiques_ponts_clients import get_occupation_ponts

router = APIRouter(tags=["statistiques"])


@router.get("/ponts/occupation-detail")
def get_occupation_ponts_detail(
    date_debut: Optional[date] = None,
    date_fin: Optional[date] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    if not date_debut:
        date_debut = date.today() - timedelta(days=date.today().weekday())
    if not date_fin:
        date_fin = date.today()

    heures_disponibles = 0
    current = date_debut
    while current <= date_fin:
        if current.weekday() < 5:
            heures_disponibles += 10
        current += timedelta(days=1)

    atelier_id = tenant_id(current_user)
    ponts = db.query(Pont).filter(Pont.is_active == 1, Pont.atelier_id == atelier_id).all()

    result = []
    for pont in ponts:
        rdvs = db.query(RendezVous).filter(
            RendezVous.atelier_id == atelier_id,
            RendezVous.pont_id == pont.id,
            RendezVous.date_rdv >= date_debut,
            RendezVous.date_rdv <= date_fin,
        ).all()
        heures_occupees = sum(r.temps_final or r.temps_estime or 60 for r in rdvs) / 60
        taux_occupation = (heures_occupees / heures_disponibles * 100) if heures_disponibles > 0 else 0
        mecano_ids = set()
        mecaniciens_utilises = []
        for rdv in rdvs:
            if rdv.mecanicien_id and rdv.mecanicien_id not in mecano_ids:
                mecano_ids.add(rdv.mecanicien_id)
                mecaniciens_utilises.append(
                    {"id": rdv.mecanicien.id, "nom": f"{rdv.mecanicien.prenom} {rdv.mecanicien.nom}", "couleur": rdv.mecanicien.couleur}
                )

        result.append(
            {
                "pont_id": pont.id,
                "pont_nom": pont.nom,
                "type_pont": pont.type_pont,
                "heures_occupees": round(heures_occupees, 1),
                "heures_disponibles": heures_disponibles,
                "taux_occupation": round(min(taux_occupation, 100), 1),
                "nb_rdv_assignes": len(rdvs),
                "nb_rdv_completes": len([r for r in rdvs if r.statut in ["termine", "facture", "paye"]]),
                "mecaniciens_utilises": mecaniciens_utilises,
            }
        )

    return {
        "date_debut": date_debut.isoformat(),
        "date_fin": date_fin.isoformat(),
        "heures_disponibles_total": heures_disponibles,
        "ponts": result,
    }


@router.get("/atelier")
def get_atelier_stats(
    periode: Optional[str] = "mois",
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    return get_performance_atelier(periode=periode, db=db, current_user=current_user)


@router.get("/performance/atelier")
def get_performance_atelier(
    periode: Optional[str] = "mois",
    date_debut: Optional[date] = None,
    date_fin: Optional[date] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    if periode and not date_debut:
        date_debut, date_fin = get_date_range(periode)
    elif not date_debut:
        date_debut = date.today().replace(day=1)
        date_fin = date.today()

    atelier_id = tenant_id(current_user)
    rdvs = db.query(RendezVous).filter(
        RendezVous.atelier_id == atelier_id,
        RendezVous.date_rdv >= date_debut,
        RendezVous.date_rdv <= date_fin,
    ).all()

    total_rdv = len(rdvs)
    rdv_completes = len([r for r in rdvs if r.statut in ["termine", "facture", "paye"]])
    rdv_en_cours = len([r for r in rdvs if r.statut == "en_cours"])
    rdv_annules = len([r for r in rdvs if r.statut == "annule"])
    temps_estime_total = sum(r.temps_estime or 0 for r in rdvs)
    temps_reel_total = sum(r.temps_final or r.temps_estime or 0 for r in rdvs)
    ecart_global = temps_reel_total - temps_estime_total
    ratio_productivite = (temps_estime_total / temps_reel_total * 100) if temps_reel_total > 0 else 0
    ca_total = sum(r.prix_final or 0 for r in rdvs if r.statut in ["termine", "facture", "paye"])
    ca_moyen = ca_total / rdv_completes if rdv_completes > 0 else 0
    taux_completion = (rdv_completes / total_rdv * 100) if total_rdv > 0 else 0

    ponts_data = get_occupation_ponts(date_debut, date_fin, db, current_user)
    taux_occupation_moyen = sum(p["taux_occupation"] for p in ponts_data["ponts"]) / len(ponts_data["ponts"]) if ponts_data["ponts"] else 0
    ponts_sorted = sorted(ponts_data["ponts"], key=lambda x: x["taux_occupation"], reverse=True)
    pont_plus_utilise = ponts_sorted[0] if ponts_sorted else None
    pont_moins_utilise = ponts_sorted[-1] if ponts_sorted else None

    return {
        "periode": periode or "custom",
        "date_debut": date_debut.isoformat(),
        "date_fin": date_fin.isoformat(),
        "rdv": {
            "total": total_rdv,
            "completes": rdv_completes,
            "en_cours": rdv_en_cours,
            "annules": rdv_annules,
            "taux_completion": round(taux_completion, 1),
        },
        "temps": {
            "estime_total": temps_estime_total,
            "reel_total": temps_reel_total,
            "ecart": ecart_global,
            "ratio_productivite": round(ratio_productivite, 1),
        },
        "ca": {"total": round(ca_total, 2), "moyen_par_rdv": round(ca_moyen, 2)},
        "ponts": {
            "taux_occupation_moyen": round(taux_occupation_moyen, 1),
            "pont_plus_utilise": pont_plus_utilise,
            "pont_moins_utilise": pont_moins_utilise,
        },
    }
