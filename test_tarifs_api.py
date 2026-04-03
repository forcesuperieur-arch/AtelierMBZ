# tarifs_api.py - Endpoints pour la gestion des tarifs et temps
from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session
from sqlalchemy import func, or_
from typing import Optional, List
from datetime import datetime, timedelta
from pydantic import BaseModel

from models import (
    get_db, GrilleTarifs, Vehicule, CategorieMoto, 
    ModeleMoto, Absence, Pont, RendezVous
)
from auth import get_current_user

router = APIRouter(prefix="/api", tags=["tarifs"])


def _tenant_id(current_user) -> int:
    return int(getattr(current_user, "atelier_id", None) or 1)

# Modèles Pydantic
class GrilleTarifCreate(BaseModel):
    categorie_moto_id: int
    type_intervention: str
    nom: str
    description: Optional[str] = None
    temps_minutes: int
    prix_mo_ht: float
    prix_mo_ttc: float
    pieces_incluses: bool = False

class CalculDevisRequest(BaseModel):
    vehicule_id: int
    prestations: List[dict]

@router.get("/tarifs")
def get_tarifs(
    categorie_id: Optional[int] = None,
    type_intervention: Optional[str] = None,
    actif: Optional[bool] = True,
    db: Session = Depends(get_db),
    current_user = Depends(get_current_user)
):
    """Récupère la grille des tarifs"""
    atelier_id = _tenant_id(current_user)
    query = db.query(GrilleTarifs).join(CategorieMoto).filter(GrilleTarifs.atelier_id == atelier_id)
    
    if categorie_id:
        query = query.filter(GrilleTarifs.categorie_moto_id == categorie_id)
    if type_intervention:
        query = query.filter(GrilleTarifs.type_intervention == type_intervention)
    if actif is not None:
        query = query.filter(GrilleTarifs.actif == actif)
    
    tarifs = query.all()
    
    return [{
        "id": t.id,
        "categorie_moto_id": t.categorie_moto_id,
        "categorie_nom": t.categorie.nom if t.categorie else None,
        "type_intervention": t.type_intervention,
        "nom": t.nom,
        "description": t.description,
        "temps_minutes": t.temps_minutes,
        "temps_formate": f"{t.temps_minutes // 60}h{t.temps_minutes % 60:02d}",
        "prix_mo_ht": t.prix_mo_ht,
        "prix_mo_ttc": t.prix_mo_ttc,
        "pieces_incluses": t.pieces_incluses,
        "actif": t.actif
    } for t in tarifs]

@router.post("/tarifs")
def create_tarif(
    tarif: GrilleTarifCreate,
    db: Session = Depends(get_db),
    current_user = Depends(get_current_user)
):
    """Crée un nouveau tarif"""
    atelier_id = _tenant_id(current_user)
    db_tarif = GrilleTarifs(**tarif.dict(), atelier_id=atelier_id)
    db.add(db_tarif)
    db.commit()
    db.refresh(db_tarif)
    return {"message": "Tarif créé", "id": db_tarif.id}

@router.put("/tarifs/{tarif_id}")
def update_tarif(
    tarif_id: int,
    tarif: GrilleTarifCreate,
    db: Session = Depends(get_db),
    current_user = Depends(get_current_user)
):
    """Met à jour un tarif"""
    atelier_id = _tenant_id(current_user)
    db_tarif = db.query(GrilleTarifs).filter(GrilleTarifs.id == tarif_id, GrilleTarifs.atelier_id == atelier_id).first()
    if not db_tarif:
        raise HTTPException(status_code=404, detail="Tarif non trouvé")
    
    for key, value in tarif.dict().items():
        setattr(db_tarif, key, value)
    
    db.commit()
    return {"message": "Tarif mis à jour"}

@router.delete("/tarifs/{tarif_id}")
def delete_tarif(
    tarif_id: int,
    db: Session = Depends(get_db),
    current_user = Depends(get_current_user)
):
    """Désactive un tarif (soft delete)"""
    atelier_id = _tenant_id(current_user)
    db_tarif = db.query(GrilleTarifs).filter(GrilleTarifs.id == tarif_id, GrilleTarifs.atelier_id == atelier_id).first()
    if not db_tarif:
        raise HTTPException(status_code=404, detail="Tarif non trouvé")
    
    db_tarif.actif = 0
    db.commit()
    return {"message": "Tarif désactivé"}

@router.post("/tarifs/calculer")
def calculer_devis(
    request: CalculDevisRequest,
    db: Session = Depends(get_db),
    current_user = Depends(get_current_user)
):
    """Calcule le temps et le prix total pour un ensemble de prestations"""
    
    # Récupérer le véhicule et sa catégorie
    atelier_id = _tenant_id(current_user)
    vehicule = db.query(Vehicule).filter(Vehicule.id == request.vehicule_id, Vehicule.atelier_id == atelier_id).first()
    if not vehicule:
        raise HTTPException(status_code=404, detail="Véhicule non trouvé")
    
    # Déterminer la catégorie et la cylindrée
    categorie_id = vehicule.categorie_id
    cylindree = vehicule.cylindree or 125
    
    if not categorie_id and vehicule.modele_id:
        modele = db.query(ModeleMoto).filter(ModeleMoto.id == vehicule.modele_id).first()
        if modele:
            categorie_id = modele.categorie_id
    
    # Calculer pour chaque prestation
    prestations_detaillees = []
    temps_total = 0
    prix_mo_total_ht = 0
    prix_mo_total_ttc = 0
    
    for prestation in request.prestations:
        type_intervention = prestation.get("type_intervention")
        
        # Chercher le tarif adapté
        tarif = db.query(GrilleTarifs).filter(
            GrilleTarifs.atelier_id == atelier_id,
            GrilleTarifs.categorie_moto_id == categorie_id,
            GrilleTarifs.type_intervention == type_intervention,
            GrilleTarifs.actif == True
        ).first()
        
        if tarif:
            prestations_detaillees.append({
                "type_intervention": type_intervention,
                "nom": tarif.nom,
                "description": tarif.description,
                "temps_minutes": tarif.temps_minutes,
                "temps_formate": f"{tarif.temps_minutes // 60}h{tarif.temps_minutes % 60:02d}",
                "prix_mo_ht": tarif.prix_mo_ht,
                "prix_mo_ttc": tarif.prix_mo_ttc
            })
            temps_total += tarif.temps_minutes
            prix_mo_total_ht += tarif.prix_mo_ht
            prix_mo_total_ttc += tarif.prix_mo_ttc
        else:
            # Tarif par défaut
            prestations_detaillees.append({
                "type_intervention": type_intervention,
                "nom": type_intervention,
                "description": "Tarif standard",
                "temps_minutes": 60,
                "temps_formate": "1h00",
                "prix_mo_ht": 60.0,
                "prix_mo_ttc": 72.0
            })
            temps_total += 60
            prix_mo_total_ht += 60.0
            prix_mo_total_ttc += 72.0
    
    return {
        "vehicule_id": request.vehicule_id,
        "vehicule": {
            "marque": vehicule.marque,
            "modele": vehicule.modele,
            "cylindree": cylindree
        },
        "prestations": prestations_detaillees,
        "temps_total_minutes": temps_total,
        "temps_total_heures": round(temps_total / 60, 2),
        "temps_total_formate": f"{temps_total // 60}h{temps_total % 60:02d}",
        "prix_mo_total_ht": round(prix_mo_total_ht, 2),
        "prix_mo_total_ttc": round(prix_mo_total_ttc, 2),
        "total_ht": round(prix_mo_total_ht, 2),
        "total_ttc": round(prix_mo_total_ttc, 2),
        "nb_prestations": len(prestations_detaillees)
    }

@router.get("/creneaux/par-duree")
def get_creneaux_par_duree(
    date_debut: str,
    date_fin: str,
    duree_heures: int,
    db: Session = Depends(get_db),
    current_user = Depends(get_current_user)
):
    """Récupère les créneaux disponibles pour une plage de dates et une durée spécifique"""
    
    duree_minutes = duree_heures * 60
    debut = datetime.strptime(date_debut, "%Y-%m-%d").date()
    fin = datetime.strptime(date_fin, "%Y-%m-%d").date()
    
    # Récupérer les mécaniciens absents sur toute la période
    absences = db.query(Absence).filter(
        Absence.atelier_id == atelier_id,
        Absence.date_debut <= fin,
        Absence.date_fin >= debut
    ).all()
    mecaniciens_absents_par_jour = {}
    for a in absences:
        current = max(a.date_debut, debut)
        while current <= min(a.date_fin, fin):
            if current not in mecaniciens_absents_par_jour:
                mecaniciens_absents_par_jour[current] = set()
            mecaniciens_absents_par_jour[current].add(a.mecanicien_id)
            current += timedelta(days=1)
    
    # Récupérer tous les ponts actifs
    tous_ponts = db.query(Pont).filter(Pont.is_active == 1, Pont.atelier_id == atelier_id).all()
    nb_ponts_total = len(tous_ponts)
    
    # Récupérer tous les RDV sur la période
    rdvs_existants = db.query(RendezVous).filter(
        RendezVous.atelier_id == atelier_id,
        RendezVous.date_rdv >= debut,
        RendezVous.date_rdv <= fin,
        RendezVous.statut.in_(["en_attente", "confirme", "en_cours"])
    ).all()
    rdvs_par_jour = {}
    for rdv in rdvs_existants:
        if rdv.date_rdv not in rdvs_par_jour:
            rdvs_par_jour[rdv.date_rdv] = []
        rdvs_par_jour[rdv.date_rdv].append(rdv)
    
    # Créer les créneaux pour chaque jour
    heures = ["09:00", "10:00", "11:00", "14:00", "15:00", "16:00", "17:00"]
    tous_creneaux = []
    
    current_date = debut
    while current_date <= fin:
        if current_date.weekday() < 5:  # Lundi-Vendredi
            # Nombre de mécaniciens absents ce jour
            absents = mecaniciens_absents_par_jour.get(current_date, set())
            nb_absents = len(absents)
            
            # Calculer les ponts disponibles (tous les ponts moins ceux sans mécanicien disponible)
            # Pour l'instant, on considère que tous les ponts sont disponibles
            # sauf si tous les mécaniciens sont absents
            nb_ponts = max(1, nb_ponts_total - nb_absents) if nb_absents < nb_ponts_total else 0
            
            # RDV ce jour
            rdvs_jour = rdvs_par_jour.get(current_date, [])
            
            for heure in heures:
                heure_datetime = datetime.strptime(heure, "%H:%M")
                heure_fin = heure_datetime + timedelta(minutes=duree_minutes)
                
                if heure_fin.time() > datetime.strptime("18:00", "%H:%M").time():
                    continue
                
                # Compter les places occupées
                places_occupees = 0
                for rdv in rdvs_jour:
                    rdv_heure = datetime.combine(current_date, rdv.heure_rdv)
                    rdv_duree = rdv.temps_estime or 60
                    rdv_fin = rdv_heure + timedelta(minutes=rdv_duree)
                    
                    creneau_debut = datetime.combine(current_date, heure_datetime.time())
                    creneau_fin = datetime.combine(current_date, heure_fin.time())
                    
                    if (creneau_debut < rdv_fin and creneau_fin > rdv_heure):
                        places_occupees += 1
                
                places_restantes = nb_ponts - places_occupees
                
                tous_creneaux.append({
                    "date": current_date.isoformat(),
                    "heure": heure,
                    "heure_fin": heure_fin.strftime("%H:%M"),
                    "disponible": places_restantes > 0,
                    "places_restantes": max(0, places_restantes),
                    "places_totales": nb_ponts
                })
        
        current_date += timedelta(days=1)
    
    return tous_creneaux
    atelier_id = _tenant_id(current_user)
