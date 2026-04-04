# Statistiques API - Atelier Moto
from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session
from sqlalchemy import func, extract, and_, or_
from datetime import datetime, date, timedelta
from typing import Optional, List
from pydantic import BaseModel

from models import get_db, RendezVous, Client, Vehicule, Pont, Mecanicien
from auth import get_current_user, User

router = APIRouter(prefix="/api/statistiques", tags=["statistiques"])


def _tenant_id(current_user: User) -> int:
    return int(getattr(current_user, "atelier_id", None) or 1)

# ========== MODÈLES PYDANTIC ==========

class CAStats(BaseModel):
    jour: float
    semaine: float
    mois: float
    annee: float

class PontOccupation(BaseModel):
    pont_id: int
    pont_nom: str
    heures_occupees: float
    heures_total: float
    taux_occupation: float

class TopIntervention(BaseModel):
    type_intervention: str
    count: int
    ca_total: float

class ClientFidele(BaseModel):
    client_id: int
    nom: str
    prenom: str
    telephone: str
    nb_rdv: int
    ca_total: float
    dernier_rdv: Optional[date]

class StatsGlobales(BaseModel):
    ca: dict
    rdv: dict
    ponts: List[PontOccupation]
    top_interventions: List[TopIntervention]
    clients_fideles: List[ClientFidele]
    evolution_mensuelle: List[dict]

# ========== FONCTIONS UTILITAIRES ==========

def get_date_range(periode: str) -> tuple:
    """Retourne la date de début et de fin pour une période donnée"""
    today = date.today()
    
    if periode == "jour":
        return today, today
    elif periode == "semaine":
        debut = today - timedelta(days=today.weekday())
        return debut, today
    elif periode == "mois":
        debut = today.replace(day=1)
        return debut, today
    elif periode == "annee":
        debut = today.replace(month=1, day=1)
        return debut, today
    else:
        return today, today

# ========== ENDPOINTS EXISTANTS ==========

@router.get("/ca")
def get_chiffre_affaires(
    periode: Optional[str] = None,
    date_debut: Optional[date] = None,
    date_fin: Optional[date] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    atelier_id = _tenant_id(current_user)
    if periode:
        date_debut, date_fin = get_date_range(periode)
    elif not date_debut or not date_fin:
        date_debut = date.today()
        date_fin = date.today()
    
    ca = db.query(func.coalesce(func.sum(RendezVous.prix_final), 0.0)).filter(
        RendezVous.atelier_id == atelier_id,
        RendezVous.date_rdv >= date_debut,
        RendezVous.date_rdv <= date_fin,
        RendezVous.statut.in_(["termine", "facture", "paye"])
    ).scalar() or 0
    
    nb_rdv = db.query(RendezVous).filter(
        RendezVous.atelier_id == atelier_id,
        RendezVous.date_rdv >= date_debut,
        RendezVous.date_rdv <= date_fin
    ).count()
    
    panier_moyen = ca / nb_rdv if nb_rdv > 0 else 0
    
    return {
        "periode": periode or "custom",
        "date_debut": date_debut.isoformat(),
        "date_fin": date_fin.isoformat(),
        "chiffre_affaires": round(ca, 2),
        "nombre_rdv": nb_rdv,
        "panier_moyen": round(panier_moyen, 2)
    }

@router.get("/ca/comparatif")
def get_ca_comparatif(db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    atelier_id = _tenant_id(current_user)
    today = date.today()
    
    ca_jour = db.query(func.coalesce(func.sum(RendezVous.prix_final), 0.0)).filter(
        RendezVous.atelier_id == atelier_id,
        RendezVous.date_rdv == today,
        RendezVous.statut.in_(["termine", "facture", "paye"])
    ).scalar() or 0
    
    debut_semaine = today - timedelta(days=today.weekday())
    ca_semaine = db.query(func.coalesce(func.sum(RendezVous.prix_final), 0.0)).filter(
        RendezVous.atelier_id == atelier_id,
        RendezVous.date_rdv >= debut_semaine,
        RendezVous.date_rdv <= today,
        RendezVous.statut.in_(["termine", "facture", "paye"])
    ).scalar() or 0
    
    debut_mois = today.replace(day=1)
    ca_mois = db.query(func.coalesce(func.sum(RendezVous.prix_final), 0.0)).filter(
        RendezVous.atelier_id == atelier_id,
        RendezVous.date_rdv >= debut_mois,
        RendezVous.date_rdv <= today,
        RendezVous.statut.in_(["termine", "facture", "paye"])
    ).scalar() or 0
    
    debut_annee = today.replace(month=1, day=1)
    ca_annee = db.query(func.coalesce(func.sum(RendezVous.prix_final), 0.0)).filter(
        RendezVous.atelier_id == atelier_id,
        RendezVous.date_rdv >= debut_annee,
        RendezVous.date_rdv <= today,
        RendezVous.statut.in_(["termine", "facture", "paye"])
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
            "annee_debut": debut_annee.isoformat()
        }
    }

@router.get("/ponts")
def get_ponts_stats(
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    atelier_id = _tenant_id(current_user)
    """Alias pour /ponts/occupation - Récupère les stats d'occupation des ponts"""
    return get_occupation_ponts(db=db, current_user=current_user)

@router.get("/ponts/occupation")
def get_occupation_ponts(
    date_debut: Optional[date] = None,
    date_fin: Optional[date] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    atelier_id = _tenant_id(current_user)
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
            RendezVous.statut.in_(["confirme", "reception", "en_cours", "termine", "facture", "paye"])
        ).all()
        
        heures_occupees = sum(rdv.temps_final or rdv.temps_estime or 60 for rdv in rdvs) / 60
        taux = (heures_occupees / heures_total * 100) if heures_total > 0 else 0
        
        result.append({
            "pont_id": pont.id,
            "pont_nom": pont.nom,
            "heures_occupees": round(heures_occupees, 1),
            "heures_total": heures_total,
            "taux_occupation": round(min(taux, 100), 1),
            "nb_rdv": len(rdvs)
        })
    
    return {
        "date_debut": date_debut.isoformat(),
        "date_fin": date_fin.isoformat(),
        "heures_ouverture_jour": heures_ouverture_jour,
        "ponts": result
    }

@router.get("/interventions/top")
def get_top_interventions(
    limit: int = 5,
    periode: Optional[str] = "mois",
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    atelier_id = _tenant_id(current_user)
    date_debut, date_fin = get_date_range(periode)
    
    results = db.query(
        RendezVous.type_intervention,
        func.count(RendezVous.id).label("count"),
        func.coalesce(func.sum(RendezVous.prix_final), 0.0).label("ca_total")
    ).filter(
        RendezVous.atelier_id == atelier_id,
        RendezVous.date_rdv >= date_debut,
        RendezVous.date_rdv <= date_fin
    ).group_by(
        RendezVous.type_intervention
    ).order_by(
        func.count(RendezVous.id).desc()
    ).limit(limit).all()
    
    return {
        "periode": periode,
        "date_debut": date_debut.isoformat(),
        "date_fin": date_fin.isoformat(),
        "top_interventions": [
            {
                "type_intervention": r.type_intervention,
                "count": r.count,
                "ca_total": round(r.ca_total or 0, 2)
            }
            for r in results
        ]
    }

@router.get("/clients/fideles")
def get_clients_fideles(
    limit: int = 10,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    atelier_id = _tenant_id(current_user)
    subq = db.query(
        RendezVous.client_id,
        func.count(RendezVous.id).label("nb_rdv"),
        func.coalesce(func.sum(RendezVous.prix_final), 0.0).label("ca_total"),
        func.max(RendezVous.date_rdv).label("dernier_rdv")
    ).filter(
        RendezVous.atelier_id == atelier_id,
        RendezVous.statut.in_(["termine", "facture", "paye"])
    ).group_by(
        RendezVous.client_id
    ).subquery()
    
    results = db.query(
        Client,
        subq.c.nb_rdv,
        subq.c.ca_total,
        subq.c.dernier_rdv
    ).join(
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
                "dernier_rdv": r.dernier_rdv.isoformat() if r.dernier_rdv else None
            }
            for r in results
        ]
    }

@router.get("/evolution-mensuelle")
def get_evolution_mensuelle(
    mois: int = 12,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    atelier_id = _tenant_id(current_user)
    today = date.today()
    result = []
    
    for i in range(mois - 1, -1, -1):
        month_date = today - timedelta(days=i * 30)
        year = month_date.year
        month = month_date.month
        
        if month == 12:
            next_month = date(year + 1, 1, 1)
        else:
            next_month = date(year, month + 1, 1)
        month_start = date(year, month, 1)
        month_end = next_month - timedelta(days=1)
        
        ca = db.query(func.coalesce(func.sum(RendezVous.prix_final), 0.0)).filter(
            RendezVous.atelier_id == atelier_id,
            RendezVous.date_rdv >= month_start,
            RendezVous.date_rdv <= month_end,
            RendezVous.statut.in_(["termine", "facture", "paye"])
        ).scalar() or 0
        
        nb_rdv = db.query(RendezVous).filter(
            RendezVous.atelier_id == atelier_id,
            RendezVous.date_rdv >= month_start,
            RendezVous.date_rdv <= month_end
        ).count()
        
        result.append({
            "mois": f"{year}-{month:02d}",
            "mois_nom": month_date.strftime("%b %Y"),
            "ca": round(ca, 2),
            "nb_rdv": nb_rdv
        })
    
    return {"evolution": result}

@router.get("/dashboard")
def get_dashboard_stats(db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    atelier_id = _tenant_id(current_user)
    today = date.today()
    
    ca_data = get_ca_comparatif(db=db, current_user=current_user)
    
    # RDV stats
    rdv_total = db.query(RendezVous).filter(RendezVous.atelier_id == atelier_id).count()
    rdv_jour = db.query(RendezVous).filter(RendezVous.atelier_id == atelier_id, RendezVous.date_rdv == today).count()
    rdv_en_attente = db.query(RendezVous).filter(
        RendezVous.atelier_id == atelier_id,
        RendezVous.statut.in_(["reserve", "en_attente"])
    ).count()
    rdv_confirme = db.query(RendezVous).filter(RendezVous.atelier_id == atelier_id, RendezVous.statut == "confirme").count()
    rdv_en_cours = db.query(RendezVous).filter(RendezVous.atelier_id == atelier_id, RendezVous.statut == "en_cours").count()
    rdv_termines = db.query(RendezVous).filter(RendezVous.atelier_id == atelier_id, RendezVous.statut.in_(["termine", "facture", "paye"])).count()
    
    # Panier moyen
    rdv_avec_prix = db.query(RendezVous).filter(RendezVous.atelier_id == atelier_id, RendezVous.prix_final.isnot(None)).all()
    panier_moyen = sum(r.prix_final for r in rdv_avec_prix) / len(rdv_avec_prix) if rdv_avec_prix else 0
    
    # Taux conversion
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
            "termine": rdv_termines
        },
        "panier_moyen": round(panier_moyen, 2),
        "taux_conversion": round(taux_conversion, 1),
        "occupation_ponts": occupation_data,
        "top_interventions": top_interventions_data,
        "clients_fideles": clients_fideles_data,
        "evolution": evolution_data,
        "productivite_mecaniciens": productivite_data
    }


# ========== NOUVEAUX ENDPOINTS POUR MÉCANICIENS ==========

@router.get("/mecaniciens")
def get_mecaniciens_stats(
    periode: Optional[str] = "mois",
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Alias pour /mecaniciens/productivite - Récupère les stats des mécaniciens"""
    return get_productivite_mecaniciens(periode=periode, db=db, current_user=current_user)

@router.get("/mecaniciens/productivite")
def get_productivite_mecaniciens(
    periode: Optional[str] = "mois",
    date_debut: Optional[date] = None,
    date_fin: Optional[date] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Récupère la productivité de chaque mécanicien"""
    if periode and not date_debut:
        date_debut, date_fin = get_date_range(periode)
    elif not date_debut:
        date_debut = date.today().replace(day=1)
        date_fin = date.today()
    
    atelier_id = _tenant_id(current_user)
    mecaniciens = db.query(Mecanicien).filter(Mecanicien.is_active == 1, Mecanicien.atelier_id == atelier_id).all()
    
    result = []
    for mecano in mecaniciens:
        rdvs = db.query(RendezVous).filter(
            RendezVous.atelier_id == atelier_id,
            RendezVous.mecanicien_id == mecano.id,
            RendezVous.date_rdv >= date_debut,
            RendezVous.date_rdv <= date_fin
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
        
        result.append({
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
            "temps_moyen_par_rdv": round(temps_moyen, 1)
        })
    
    result.sort(key=lambda x: x["ca_genere"], reverse=True)
    
    return {
        "periode": periode or "custom",
        "date_debut": date_debut.isoformat(),
        "date_fin": date_fin.isoformat(),
        "mecaniciens": result
    }


@router.get("/mecaniciens/{mecanicien_id}/detail")
def get_detail_mecanicien(
    mecanicien_id: int,
    periode: Optional[str] = "mois",
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Récupère les détails d'un mécanicien spécifique"""
    atelier_id = _tenant_id(current_user)
    mecano = db.query(Mecanicien).filter(Mecanicien.id == mecanicien_id, Mecanicien.atelier_id == atelier_id).first()
    if not mecano:
        raise HTTPException(status_code=404, detail="Mécanicien non trouvé")
    
    date_debut, date_fin = get_date_range(periode)
    
    rdvs = db.query(RendezVous).filter(
        RendezVous.atelier_id == atelier_id,
        RendezVous.mecanicien_id == mecanicien_id,
        RendezVous.date_rdv >= date_debut,
        RendezVous.date_rdv <= date_fin
    ).all()
    
    stats_par_statut = {}
    for rdv in rdvs:
        stats_par_statut[rdv.statut] = stats_par_statut.get(rdv.statut, 0) + 1
    
    historique = []
    for rdv in sorted(rdvs, key=lambda x: x.date_rdv, reverse=True)[:10]:
        historique.append({
            "rdv_id": rdv.id,
            "date": rdv.date_rdv.isoformat(),
            "type_intervention": rdv.type_intervention,
            "statut": rdv.statut,
            "temps_estime": rdv.temps_estime,
            "temps_final": rdv.temps_final,
            "prix_final": rdv.prix_final,
            "client": f"{rdv.client.prenom} {rdv.client.nom}" if rdv.client else None,
            "vehicule": f"{rdv.vehicule.marque} {rdv.vehicule.modele}" if rdv.vehicule else None
        })
    
    return {
        "mecanicien": {
            "id": mecano.id,
            "nom": mecano.nom,
            "prenom": mecano.prenom,
            "specialites": mecano.specialites,
            "couleur": mecano.couleur
        },
        "periode": periode,
        "date_debut": date_debut.isoformat(),
        "date_fin": date_fin.isoformat(),
        "stats_par_statut": stats_par_statut,
        "historique_rdv": historique
    }


@router.get("/ponts/occupation-detail")
def get_occupation_ponts_detail(
    date_debut: Optional[date] = None,
    date_fin: Optional[date] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Récupère le taux d'occupation détaillé des ponts avec infos mécaniciens"""
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
    
    atelier_id = _tenant_id(current_user)
    ponts = db.query(Pont).filter(Pont.is_active == 1, Pont.atelier_id == atelier_id).all()
    
    result = []
    for pont in ponts:
        rdvs = db.query(RendezVous).filter(
            RendezVous.atelier_id == atelier_id,
            RendezVous.pont_id == pont.id,
            RendezVous.date_rdv >= date_debut,
            RendezVous.date_rdv <= date_fin
        ).all()
        
        heures_occupees = sum(r.temps_final or r.temps_estime or 60 for r in rdvs) / 60
        taux_occupation = (heures_occupees / heures_disponibles * 100) if heures_disponibles > 0 else 0
        
        mecano_ids = set()
        mecaniciens_utilises = []
        for rdv in rdvs:
            if rdv.mecanicien_id and rdv.mecanicien_id not in mecano_ids:
                mecano_ids.add(rdv.mecanicien_id)
                mecaniciens_utilises.append({
                    "id": rdv.mecanicien.id,
                    "nom": f"{rdv.mecanicien.prenom} {rdv.mecanicien.nom}",
                    "couleur": rdv.mecanicien.couleur
                })
        
        result.append({
            "pont_id": pont.id,
            "pont_nom": pont.nom,
            "type_pont": pont.type_pont,
            "heures_occupees": round(heures_occupees, 1),
            "heures_disponibles": heures_disponibles,
            "taux_occupation": round(min(taux_occupation, 100), 1),
            "nb_rdv_assignes": len(rdvs),
            "nb_rdv_completes": len([r for r in rdvs if r.statut in ["termine", "facture", "paye"]]),
            "mecaniciens_utilises": mecaniciens_utilises
        })
    
    return {
        "date_debut": date_debut.isoformat(),
        "date_fin": date_fin.isoformat(),
        "heures_disponibles_total": heures_disponibles,
        "ponts": result
    }


@router.get("/atelier")
def get_atelier_stats(
    periode: Optional[str] = "mois",
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Alias pour /performance/atelier - Récupère les stats de l'atelier"""
    return get_performance_atelier(periode=periode, db=db, current_user=current_user)

@router.get("/performance/atelier")
def get_performance_atelier(
    periode: Optional[str] = "mois",
    date_debut: Optional[date] = None,
    date_fin: Optional[date] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Récupère les statistiques de performance globales de l'atelier"""
    if periode and not date_debut:
        date_debut, date_fin = get_date_range(periode)
    elif not date_debut:
        date_debut = date.today().replace(day=1)
        date_fin = date.today()
    
    atelier_id = _tenant_id(current_user)
    rdvs = db.query(RendezVous).filter(
        RendezVous.atelier_id == atelier_id,
        RendezVous.date_rdv >= date_debut,
        RendezVous.date_rdv <= date_fin
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
            "taux_completion": round(taux_completion, 1)
        },
        "temps": {
            "estime_total": temps_estime_total,
            "reel_total": temps_reel_total,
            "ecart": ecart_global,
            "ratio_productivite": round(ratio_productivite, 1)
        },
        "ca": {
            "total": round(ca_total, 2),
            "moyen_par_rdv": round(ca_moyen, 2)
        },
        "ponts": {
            "taux_occupation_moyen": round(taux_occupation_moyen, 1),
            "pont_plus_utilise": pont_plus_utilise,
            "pont_moins_utilise": pont_moins_utilise
        }
    }


@router.get("/mecaniciens/comparaison")
def get_comparaison_mecaniciens(
    periode: Optional[str] = "mois",
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Compare les performances des mécaniciens entre eux"""
    date_debut, date_fin = get_date_range(periode) if periode else (date.today().replace(day=1), date.today())
    
    atelier_id = _tenant_id(current_user)
    mecaniciens = db.query(Mecanicien).filter(Mecanicien.is_active == 1, Mecanicien.atelier_id == atelier_id).all()
    
    data = []
    for mecano in mecaniciens:
        rdvs = db.query(RendezVous).filter(
            RendezVous.atelier_id == atelier_id,
            RendezVous.mecanicien_id == mecano.id,
            RendezVous.date_rdv >= date_debut,
            RendezVous.date_rdv <= date_fin
        ).all()
        
        rdv_completes = [r for r in rdvs if r.statut in ["termine", "facture", "paye"]]
        
        data.append({
            "mecanicien_id": mecano.id,
            "nom": f"{mecano.prenom} {mecano.nom}",
            "couleur": mecano.couleur,
            "rdv_total": len(rdvs),
            "rdv_completes": len(rdv_completes),
            "ca_genere": sum(r.prix_final or 0 for r in rdv_completes),
            "temps_moyen": sum(r.temps_final or r.temps_estime or 0 for r in rdv_completes) / len(rdv_completes) if rdv_completes else 0
        })
    
    # Calculer les moyennes
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
        "moyennes": {
            "ca": round(avg_ca, 2),
            "rdv_completes": round(avg_rdv, 1),
            "temps_moyen": round(avg_temps, 1)
        },
        "mecaniciens": data
    }
