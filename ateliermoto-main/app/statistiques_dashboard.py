"""Dashboard endpoint for statistiques API."""

from datetime import date

from fastapi import APIRouter, Depends
from sqlalchemy.orm import Session

from auth import User, get_current_user
from models import RendezVous, get_db
from statistiques_ca import get_ca_comparatif
from statistiques_common import tenant_id
from statistiques_mecaniciens import get_productivite_mecaniciens
from statistiques_ponts_clients import (
    get_clients_fideles,
    get_evolution_mensuelle,
    get_occupation_ponts,
    get_top_interventions,
)

router = APIRouter(tags=["statistiques"])


@router.get("/dashboard")
def get_dashboard_stats(db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    atelier_id = tenant_id(current_user)
    today = date.today()

    ca_data = get_ca_comparatif(db=db, current_user=current_user)
    rdv_total = db.query(RendezVous).filter(RendezVous.atelier_id == atelier_id).count()
    rdv_en_attente = db.query(RendezVous).filter(
        RendezVous.atelier_id == atelier_id,
        RendezVous.statut.in_(["reserve", "en_attente"]),
    ).count()
    rdv_confirme = db.query(RendezVous).filter(RendezVous.atelier_id == atelier_id, RendezVous.statut == "confirme").count()
    rdv_en_cours = db.query(RendezVous).filter(RendezVous.atelier_id == atelier_id, RendezVous.statut == "en_cours").count()
    rdv_termines = db.query(RendezVous).filter(
        RendezVous.atelier_id == atelier_id,
        RendezVous.statut.in_(["termine", "facture", "paye"]),
    ).count()

    rdv_avec_prix = db.query(RendezVous).filter(RendezVous.atelier_id == atelier_id, RendezVous.prix_final.isnot(None)).all()
    panier_moyen = sum(r.prix_final for r in rdv_avec_prix) / len(rdv_avec_prix) if rdv_avec_prix else 0
    taux_conversion = (rdv_confirme / rdv_total * 100) if rdv_total > 0 else 0

    occupation_data = get_occupation_ponts(db=db, current_user=current_user)
    top_interventions_data = get_top_interventions(limit=10, periode="mois", db=db, current_user=current_user)
    clients_fideles_data = get_clients_fideles(limit=10, db=db, current_user=current_user)
    evolution_data = get_evolution_mensuelle(mois=6, db=db, current_user=current_user)
    productivite_data = get_productivite_mecaniciens(periode="mois", db=db, current_user=current_user)

    return {
        "ca": ca_data,
        "rdv_total": rdv_total,
        "rdv_par_statut": {
            "en_attente": rdv_en_attente,
            "confirme": rdv_confirme,
            "en_cours": rdv_en_cours,
            "termine": rdv_termines,
        },
        "panier_moyen": round(panier_moyen, 2),
        "taux_conversion": round(taux_conversion, 1),
        "occupation_ponts": occupation_data,
        "top_interventions": top_interventions_data,
        "clients_fideles": clients_fideles_data,
        "evolution": evolution_data,
        "productivite_mecaniciens": productivite_data,
    }
