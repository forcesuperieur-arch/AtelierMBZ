"""Mecanicien endpoints for statistiques API."""

from datetime import date
from typing import Optional

from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session

from auth import User, get_current_user
from models import Mecanicien, RendezVous, get_db
from statistiques_common import get_date_range, tenant_id

router = APIRouter(tags=["statistiques"])


@router.get("/mecaniciens")
def get_mecaniciens_stats(
    periode: Optional[str] = "mois",
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    return get_productivite_mecaniciens(periode=periode, db=db, current_user=current_user)


@router.get("/mecaniciens/productivite")
def get_productivite_mecaniciens(
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
    mecaniciens = db.query(Mecanicien).filter(Mecanicien.is_active == 1, Mecanicien.atelier_id == atelier_id).all()

    result = []
    for mecano in mecaniciens:
        rdvs = db.query(RendezVous).filter(
            RendezVous.atelier_id == atelier_id,
            RendezVous.mecanicien_id == mecano.id,
            RendezVous.date_rdv >= date_debut,
            RendezVous.date_rdv <= date_fin,
        ).all()

        rdv_total = len(rdvs)
        rdv_completes = len([r for r in rdvs if r.statut in ["termine", "facture", "paye"]])
        rdv_en_cours = len([r for r in rdvs if r.statut == "en_cours"])
        temps_estime_total = sum(r.temps_estime or 0 for r in rdvs)
        temps_reel_total = sum(r.temps_final or r.temps_estime or 0 for r in rdvs)
        ecart_temps = temps_reel_total - temps_estime_total
        ratio_productivite = (temps_estime_total / temps_reel_total * 100) if temps_reel_total > 0 else 0
        ca_genere = sum(r.prix_final or 0 for r in rdvs if r.statut in ["termine", "facture", "paye"])
        taux_completion = (rdv_completes / rdv_total * 100) if rdv_total > 0 else 0
        temps_moyen = (temps_reel_total / rdv_completes) if rdv_completes > 0 else 0

        result.append(
            {
                "mecanicien_id": mecano.id,
                "nom": mecano.nom,
                "prenom": mecano.prenom,
                "specialites": mecano.specialites,
                "couleur": mecano.couleur,
                "rdv_total": rdv_total,
                "rdv_completes": rdv_completes,
                "rdv_en_cours": rdv_en_cours,
                "temps_estime_total": temps_estime_total,
                "temps_reel_total": temps_reel_total,
                "ecart_temps": ecart_temps,
                "ratio_productivite": round(ratio_productivite, 1),
                "ca_genere": round(ca_genere, 2),
                "taux_completion": round(taux_completion, 1),
                "temps_moyen_par_rdv": round(temps_moyen, 1),
            }
        )

    result.sort(key=lambda x: x["ca_genere"], reverse=True)
    return {
        "periode": periode or "custom",
        "date_debut": date_debut.isoformat(),
        "date_fin": date_fin.isoformat(),
        "mecaniciens": result,
    }


@router.get("/mecaniciens/{mecanicien_id}/detail")
def get_detail_mecanicien(
    mecanicien_id: int,
    periode: Optional[str] = "mois",
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    atelier_id = tenant_id(current_user)
    mecano = db.query(Mecanicien).filter(Mecanicien.id == mecanicien_id, Mecanicien.atelier_id == atelier_id).first()
    if not mecano:
        raise HTTPException(status_code=404, detail="Mécanicien non trouvé")

    date_debut, date_fin = get_date_range(periode)
    rdvs = db.query(RendezVous).filter(
        RendezVous.atelier_id == atelier_id,
        RendezVous.mecanicien_id == mecanicien_id,
        RendezVous.date_rdv >= date_debut,
        RendezVous.date_rdv <= date_fin,
    ).all()

    stats_par_statut = {}
    for rdv in rdvs:
        stats_par_statut[rdv.statut] = stats_par_statut.get(rdv.statut, 0) + 1

    historique = []
    for rdv in sorted(rdvs, key=lambda x: x.date_rdv, reverse=True)[:10]:
        historique.append(
            {
                "rdv_id": rdv.id,
                "date": rdv.date_rdv.isoformat(),
                "type_intervention": rdv.type_intervention,
                "statut": rdv.statut,
                "temps_estime": rdv.temps_estime,
                "temps_final": rdv.temps_final,
                "prix_final": rdv.prix_final,
                "client": f"{rdv.client.prenom} {rdv.client.nom}" if rdv.client else None,
                "vehicule": f"{rdv.vehicule.marque} {rdv.vehicule.modele}" if rdv.vehicule else None,
            }
        )

    return {
        "mecanicien": {
            "id": mecano.id,
            "nom": mecano.nom,
            "prenom": mecano.prenom,
            "specialites": mecano.specialites,
            "couleur": mecano.couleur,
        },
        "periode": periode,
        "date_debut": date_debut.isoformat(),
        "date_fin": date_fin.isoformat(),
        "stats_par_statut": stats_par_statut,
        "historique_rdv": historique,
    }


@router.get("/mecaniciens/comparaison")
def get_comparaison_mecaniciens(
    periode: Optional[str] = "mois",
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    date_debut, date_fin = get_date_range(periode) if periode else (date.today().replace(day=1), date.today())
    atelier_id = tenant_id(current_user)
    mecaniciens = db.query(Mecanicien).filter(Mecanicien.is_active == 1, Mecanicien.atelier_id == atelier_id).all()

    data = []
    for mecano in mecaniciens:
        rdvs = db.query(RendezVous).filter(
            RendezVous.atelier_id == atelier_id,
            RendezVous.mecanicien_id == mecano.id,
            RendezVous.date_rdv >= date_debut,
            RendezVous.date_rdv <= date_fin,
        ).all()
        rdv_completes = [r for r in rdvs if r.statut in ["termine", "facture", "paye"]]
        data.append(
            {
                "mecanicien_id": mecano.id,
                "nom": f"{mecano.prenom} {mecano.nom}",
                "couleur": mecano.couleur,
                "rdv_total": len(rdvs),
                "rdv_completes": len(rdv_completes),
                "ca_genere": sum(r.prix_final or 0 for r in rdv_completes),
                "temps_moyen": (
                    sum(r.temps_final or r.temps_estime or 0 for r in rdv_completes) / len(rdv_completes)
                    if rdv_completes
                    else 0
                ),
            }
        )

    if data:
        avg_ca = sum(d["ca_genere"] for d in data) / len(data)
        avg_rdv = sum(d["rdv_completes"] for d in data) / len(data)
        avg_temps = sum(d["temps_moyen"] for d in data) / len(data)
    else:
        avg_ca = avg_rdv = avg_temps = 0

    return {
        "periode": periode,
        "date_debut": date_debut.isoformat(),
        "date_fin": date_fin.isoformat(),
        "moyennes": {"ca": round(avg_ca, 2), "rdv_completes": round(avg_rdv, 1), "temps_moyen": round(avg_temps, 1)},
        "mecaniciens": data,
    }
