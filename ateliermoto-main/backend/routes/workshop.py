from datetime import date, datetime, timedelta, time
import json
import logging
from typing import Optional

from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy import and_, or_
from sqlalchemy.orm import Session, joinedload

from auth import get_current_user
from models import Absence, Atelier, Mecanicien, Pont, RendezVous, RolePermission, User, get_db
from schemas.workshop import AbsenceCreate, AbsenceResponse, MecanicienCreate, PontCreate

logger = logging.getLogger("ateliermoto.api")
router = APIRouter(tags=["workshop"])


def _atelier_id_or_403(current_user: User) -> int:
    # Compatibilite temporaire: users legacy sans atelier_id -> atelier par defaut
    return int(current_user.atelier_id or 1)


def _user_has_permission(current_user: User, db: Session, permission: str) -> bool:
    if current_user.role == "super_admin":
        return True
    rp = db.query(RolePermission).filter(RolePermission.role == (current_user.role or "")).first()
    if rp:
        try:
            perms = json.loads(rp.permissions_json or "[]")
        except Exception:
            perms = []
        return permission in perms
    # Fallback legacy roles
    if permission == "rdv.select_atelier" and current_user.role in {"admin", "receptionnaire", "service_client"}:
        return True
    return False


def _resolve_atelier_id(current_user: User, db: Session, atelier_slug: Optional[str]) -> int:
    if not atelier_slug:
        return _atelier_id_or_403(current_user)
    if not _user_has_permission(current_user, db, "rdv.select_atelier"):
        raise HTTPException(status_code=403, detail="Permission atelier multi-site requise")
    slug = (atelier_slug or "").strip().lower()
    atelier = db.query(Atelier).filter(Atelier.slug == slug, Atelier.actif == True).first()
    if not atelier:
        raise HTTPException(status_code=404, detail="Atelier introuvable")
    return int(atelier.id)


@router.get("/api/ponts")
def get_ponts(
    tous: bool = False,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Récupère la liste des ponts"""
    atelier_id = _atelier_id_or_403(current_user)
    query = db.query(Pont).options(joinedload(Pont.mecanicien)).filter(Pont.atelier_id == atelier_id)
    if not tous:
        query = query.filter(Pont.is_active == 1)
    ponts = query.order_by(Pont.ordre_affichage).all()
    return [{
        "id": p.id,
        "nom": p.nom,
        "type_pont": p.type_pont,
        "capacite_kg": p.capacite_kg,
        "actif": p.is_active == 1,
        "mecanicien_id": (p.mecanicien_id if (p.mecanicien and p.mecanicien.is_active == 1) else None),
        "mecanicien": {
            "id": p.mecanicien.id,
            "nom": p.mecanicien.nom,
            "prenom": p.mecanicien.prenom
        } if (p.mecanicien and p.mecanicien.is_active == 1) else None
    } for p in ponts]

@router.get("/api/ponts/status")
def get_ponts_status(db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    """Retourne le statut en temps réel de chaque pont avec progression et ETA"""
    atelier_id = _atelier_id_or_403(current_user)
    today = date.today()
    now = datetime.now()
    # Vue operationnelle: uniquement les ponts actifs
    ponts = db.query(Pont).options(joinedload(Pont.mecanicien)).filter(
        Pont.atelier_id == atelier_id,
        Pont.is_active == 1
    ).order_by(Pont.ordre_affichage).all()

    today_rdvs = db.query(RendezVous).options(
        joinedload(RendezVous.vehicule),
        joinedload(RendezVous.client)
    ).filter(RendezVous.date_rdv == today, RendezVous.atelier_id == atelier_id).all()

    # Verifier les absences du jour
    absences = db.query(Absence).filter(
        Absence.date_debut <= today,
        Absence.date_fin >= today,
        Absence.atelier_id == atelier_id
    ).all()
    mecaniciens_absents = {a.mecanicien_id for a in absences}

    result = []
    for p in ponts:
        # Determiner la disponibilite du pont
        meca_active = p.mecanicien if (p.mecanicien and p.mecanicien.is_active == 1) else None
        meca_absent = meca_active.id in mecaniciens_absents if meca_active else False
        sans_mecano = meca_active is None
        indispo = p.is_active != 1 or meca_absent or sans_mecano

        if p.is_active != 1:
            status = "maintenance"
        elif sans_mecano:
            status = "sans_mecanicien"
        elif meca_absent:
            status = "mecanicien_absent"
        else:
            status = "libre"

        pont_data = {
            "id": p.id,
            "nom": p.nom,
            "type_pont": p.type_pont,
            "capacite_kg": p.capacite_kg,
            "actif": p.is_active == 1,
            "mecanicien_id": meca_active.id if meca_active else None,
            "mecanicien": {
                "id": meca_active.id,
                "nom": meca_active.nom,
                "prenom": meca_active.prenom,
                "couleur": getattr(meca_active, 'couleur', None)
            } if meca_active else None,
            "mecanicien_absent": meca_absent,
            "sans_mecanicien": sans_mecano,
            "status": status,
            "rdv_en_cours": None,
            "prochain_rdv": None,
            "progression": 0,
            "heure_fin_estimee": None
        }

        rdv_en_cours = None
        prochain_rdv = None
        for rdv in today_rdvs:
            if rdv.pont_id != p.id:
                continue
            if rdv.statut == 'en_cours':
                rdv_en_cours = rdv
            elif rdv.statut in ('reserve', 'confirme', 'en_attente', 'reception') and not prochain_rdv:
                if not rdv_en_cours:
                    prochain_rdv = rdv

        if rdv_en_cours and p.is_active == 1:
            pont_data["status"] = "occupe"
            v = rdv_en_cours.vehicule
            c = rdv_en_cours.client
            meca = p.mecanicien

            progression = 50
            heure_fin = None
            if rdv_en_cours.heure_debut_travail:
                elapsed = (now - rdv_en_cours.heure_debut_travail).total_seconds() / 60
                duree_est = rdv_en_cours.temps_estime or 60
                progression = min(95, int((elapsed / duree_est) * 100))
                fin = rdv_en_cours.heure_debut_travail + timedelta(minutes=duree_est)
                heure_fin = fin.strftime("%Hh%M")
            elif rdv_en_cours.heure_rdv and rdv_en_cours.temps_estime:
                try:
                    h_rdv = datetime.combine(today, rdv_en_cours.heure_rdv) if isinstance(rdv_en_cours.heure_rdv, time) else datetime.combine(today, time(*[int(x) for x in str(rdv_en_cours.heure_rdv).split(":")[:2]]))
                    elapsed = (now - h_rdv).total_seconds() / 60
                    progression = min(95, max(5, int((elapsed / rdv_en_cours.temps_estime) * 100)))
                    fin = h_rdv + timedelta(minutes=rdv_en_cours.temps_estime)
                    heure_fin = fin.strftime("%Hh%M")
                except Exception as e:
                    logger.warning("Erreur calcul progression pont_id=%s rdv_id=%s: %s", p.id, rdv_en_cours.id, e)

            pont_data["progression"] = progression
            pont_data["heure_fin_estimee"] = heure_fin
            pont_data["rdv_en_cours"] = {
                "id": rdv_en_cours.id,
                "type_intervention": rdv_en_cours.type_intervention,
                "heure_rdv": str(rdv_en_cours.heure_rdv)[:5] if rdv_en_cours.heure_rdv else None,
                "temps_estime": rdv_en_cours.temps_estime,
                "vehicule": {
                    "marque": v.marque if v else None,
                    "modele": v.modele if v else None,
                    "plaque": v.plaque if v else None
                } if v else None,
                "client": {
                    "nom": c.nom if c else None,
                    "prenom": c.prenom if c else None
                } if c else None,
                "mecanicien": {
                    "nom": meca.nom if meca else None,
                    "prenom": meca.prenom if meca else None
                } if meca else None
            }
        elif prochain_rdv and p.is_active == 1:
            pont_data["prochain_rdv"] = {
                "heure_rdv": str(prochain_rdv.heure_rdv)[:5] if prochain_rdv.heure_rdv else None,
                "type_intervention": prochain_rdv.type_intervention
            }

        result.append(pont_data)

    return result


@router.post("/api/ponts")
def create_pont(
    pont: PontCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Crée un nouveau pont"""
    atelier_id = _atelier_id_or_403(current_user)
    new_pont = Pont(
        atelier_id=atelier_id,
        nom=pont.nom,
        type_pont=pont.type_pont,
        capacite_kg=pont.capacite_kg,
        ordre_affichage=pont.ordre_affichage,
        is_active=pont.get_is_active(),
        mecanicien_id=pont.mecanicien_id
    )
    db.add(new_pont)
    db.commit()
    db.refresh(new_pont)
    return {"message": "Pont créé", "id": new_pont.id}

@router.put("/api/ponts/{pont_id}")
def update_pont(
    pont_id: int,
    pont: PontCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Met à jour un pont"""
    atelier_id = _atelier_id_or_403(current_user)
    db_pont = db.query(Pont).filter(Pont.id == pont_id, Pont.atelier_id == atelier_id).first()
    if not db_pont:
        raise HTTPException(status_code=404, detail="Pont non trouvé")

    db_pont.nom = pont.nom
    db_pont.type_pont = pont.type_pont
    db_pont.capacite_kg = pont.capacite_kg
    db_pont.ordre_affichage = pont.ordre_affichage
    db_pont.is_active = pont.get_is_active()
    db_pont.mecanicien_id = pont.mecanicien_id

    db.commit()
    db.refresh(db_pont)
    return {"message": "Pont mis à jour", "id": db_pont.id}

@router.delete("/api/ponts/{pont_id}")
def delete_pont(
    pont_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Supprime un pont (soft delete)"""
    atelier_id = _atelier_id_or_403(current_user)
    db_pont = db.query(Pont).filter(Pont.id == pont_id, Pont.atelier_id == atelier_id).first()
    if not db_pont:
        raise HTTPException(status_code=404, detail="Pont non trouvé")
    
    db_pont.is_active = 0
    # Desaffecter le mecanicien du pont supprime
    db_pont.mecanicien_id = None

    # Les RDV futurs/non finalises ne doivent plus rester planifies sur un pont inactif
    today = date.today()
    rdvs = db.query(RendezVous).filter(
        RendezVous.atelier_id == atelier_id,
        RendezVous.pont_id == pont_id,
        or_(
            RendezVous.date_rdv > today,
            and_(
                RendezVous.date_rdv == today,
                RendezVous.statut.notin_(["termine", "facture", "paye", "annule"])
            )
        )
    ).all()
    for rdv in rdvs:
        rdv.pont_id = None
        rdv.mecanicien_id = None
    db.commit()
    return {"message": "Pont supprimé"}

# ========== MÉCANICIENS ==========

@router.get("/api/mecaniciens")
def get_mecaniciens(
    tous: bool = False,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Récupère la liste des mécaniciens"""
    atelier_id = _atelier_id_or_403(current_user)
    query = db.query(Mecanicien).filter(Mecanicien.atelier_id == atelier_id)
    if not tous:
        query = query.filter(Mecanicien.is_active == 1)
    mecaniciens = query.order_by(Mecanicien.nom).all()
    return [{
        "id": m.id,
        "user_id": m.user_id,
        "nom": m.nom,
        "prenom": m.prenom,
        "specialites": m.specialites,
        "couleur": m.couleur,
        "actif": m.is_active == 1
    } for m in mecaniciens]

@router.post("/api/mecaniciens")
def create_mecanicien(
    mecanicien: MecanicienCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Crée un nouveau mécanicien"""
    atelier_id = _atelier_id_or_403(current_user)
    new_mecanicien = Mecanicien(
        atelier_id=atelier_id,
        nom=mecanicien.nom,
        prenom=mecanicien.prenom,
        specialites=mecanicien.specialites,
        couleur=mecanicien.couleur,
        is_active=mecanicien.get_is_active()
    )
    db.add(new_mecanicien)
    db.commit()
    db.refresh(new_mecanicien)
    return {"message": "Mécanicien créé", "id": new_mecanicien.id}

@router.put("/api/mecaniciens/{mecanicien_id}")
def update_mecanicien(
    mecanicien_id: int,
    mecanicien: MecanicienCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Met à jour un mécanicien"""
    atelier_id = _atelier_id_or_403(current_user)
    db_mecanicien = db.query(Mecanicien).filter(Mecanicien.id == mecanicien_id, Mecanicien.atelier_id == atelier_id).first()
    if not db_mecanicien:
        raise HTTPException(status_code=404, detail="Mécanicien non trouvé")
    
    db_mecanicien.nom = mecanicien.nom
    db_mecanicien.prenom = mecanicien.prenom
    db_mecanicien.specialites = mecanicien.specialites
    db_mecanicien.couleur = mecanicien.couleur
    db_mecanicien.is_active = mecanicien.get_is_active()
    
    db.commit()
    db.refresh(db_mecanicien)
    return {"message": "Mécanicien mis à jour", "id": db_mecanicien.id}

@router.delete("/api/mecaniciens/{mecanicien_id}")
def delete_mecanicien(
    mecanicien_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Supprime un mécanicien (soft delete)"""
    atelier_id = _atelier_id_or_403(current_user)
    db_mecanicien = db.query(Mecanicien).filter(Mecanicien.id == mecanicien_id, Mecanicien.atelier_id == atelier_id).first()
    if not db_mecanicien:
        raise HTTPException(status_code=404, detail="Mécanicien non trouvé")
    
    db_mecanicien.is_active = 0
    db.query(Pont).filter(Pont.mecanicien_id == mecanicien_id, Pont.atelier_id == atelier_id).update(
        {"mecanicien_id": None},
        synchronize_session=False
    )
    db.commit()
    return {"message": "Mécanicien supprimé"}

# ========== ABSENCES ==========

@router.get("/api/absences")
def get_absences(
    date: str | None = None,
    mecanicien_id: int | None = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Récupère les absences (filtre par date et/ou mécanicien)"""
    atelier_id = _atelier_id_or_403(current_user)
    query = db.query(Absence).join(Mecanicien).filter(Absence.atelier_id == atelier_id, Mecanicien.atelier_id == atelier_id)
    
    if date:
        target_date = datetime.strptime(date, "%Y-%m-%d").date()
        query = query.filter(
            and_(Absence.date_debut <= target_date, Absence.date_fin >= target_date)
        )
    
    if mecanicien_id:
        query = query.filter(Absence.mecanicien_id == mecanicien_id)
    
    absences = query.order_by(Absence.date_debut.desc()).all()
    
    return [{
        "id": a.id,
        "mecanicien_id": a.mecanicien_id,
        "mecanicien_nom": a.mecanicien.nom,
        "mecanicien_prenom": a.mecanicien.prenom,
        "date_debut": a.date_debut.isoformat(),
        "date_fin": a.date_fin.isoformat(),
        "motif": a.motif,
        "notes": a.notes
    } for a in absences]

@router.post("/api/absences")
def create_absence(
    absence: AbsenceCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Crée une nouvelle absence"""
    atelier_id = _atelier_id_or_403(current_user)
    # Vérifier que le mécanicien existe
    mecano = db.query(Mecanicien).filter(Mecanicien.id == absence.mecanicien_id, Mecanicien.atelier_id == atelier_id).first()
    if not mecano:
        raise HTTPException(status_code=404, detail="Mécanicien non trouvé")
    
    db_absence = Absence(**absence.model_dump(), atelier_id=atelier_id)
    db.add(db_absence)
    db.commit()
    db.refresh(db_absence)
    
    return {"message": "Absence créée", "id": db_absence.id}

@router.put("/api/absences/{absence_id}")
def update_absence(
    absence_id: int,
    absence: AbsenceCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Met à jour une absence"""
    atelier_id = _atelier_id_or_403(current_user)
    db_absence = db.query(Absence).filter(Absence.id == absence_id, Absence.atelier_id == atelier_id).first()
    if not db_absence:
        raise HTTPException(status_code=404, detail="Absence non trouvée")

    mecano = db.query(Mecanicien).filter(Mecanicien.id == absence.mecanicien_id, Mecanicien.atelier_id == atelier_id).first()
    if not mecano:
        raise HTTPException(status_code=404, detail="Mécanicien non trouvé")

    db_absence.mecanicien_id = absence.mecanicien_id
    db_absence.date_debut = absence.date_debut
    db_absence.date_fin = absence.date_fin
    db_absence.motif = absence.motif
    db_absence.notes = absence.notes
    db.commit()
    db.refresh(db_absence)
    return {"message": "Absence mise à jour", "id": db_absence.id}

@router.delete("/api/absences/{absence_id}")
def delete_absence(
    absence_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Supprime une absence"""
    atelier_id = _atelier_id_or_403(current_user)
    absence = db.query(Absence).filter(Absence.id == absence_id, Absence.atelier_id == atelier_id).first()
    if not absence:
        raise HTTPException(status_code=404, detail="Absence non trouvée")
    
    db.delete(absence)
    db.commit()
    return {"message": "Absence supprimée"}

# ========== PLANNING ==========

@router.get("/api/planning")
def get_planning(
    date: Optional[str] = None,
    atelier_slug: Optional[str] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Récupère le planning des RDV pour une date donnée (défaut: aujourd'hui)"""
    atelier_id = _resolve_atelier_id(current_user, db, atelier_slug)
    if date:
        target_date = datetime.strptime(date, "%Y-%m-%d").date()
    else:
        target_date = datetime.now().date()
    
    # Récupérer les RDV du jour avec les relations
    rdvs = db.query(RendezVous).options(
        joinedload(RendezVous.client),
        joinedload(RendezVous.vehicule),
        joinedload(RendezVous.pont),
        joinedload(RendezVous.mecanicien)
    ).filter(
        RendezVous.date_rdv == target_date,
        RendezVous.atelier_id == atelier_id
    ).order_by(RendezVous.heure_rdv).all()
    
    # Récupérer ressources actives + celles référencées par des RDV (historique lisible)
    ponts = db.query(Pont).filter(Pont.is_active == 1, Pont.atelier_id == atelier_id).order_by(Pont.ordre_affichage).all()
    mecaniciens = db.query(Mecanicien).filter(Mecanicien.is_active == 1, Mecanicien.atelier_id == atelier_id).order_by(Mecanicien.nom).all()
    used_pont_ids = {r.pont_id for r in rdvs if r.pont_id}
    used_meca_ids = {r.mecanicien_id for r in rdvs if r.mecanicien_id}
    if used_pont_ids:
        extra_ponts = db.query(Pont).filter(Pont.atelier_id == atelier_id, Pont.id.in_(used_pont_ids)).all()
        by_id = {p.id: p for p in ponts}
        for p in extra_ponts:
            by_id[p.id] = p
        ponts = sorted(by_id.values(), key=lambda x: (x.ordre_affichage or 0, x.id))
    if used_meca_ids:
        extra_mecas = db.query(Mecanicien).filter(Mecanicien.atelier_id == atelier_id, Mecanicien.id.in_(used_meca_ids)).all()
        by_id_m = {m.id: m for m in mecaniciens}
        for m in extra_mecas:
            by_id_m[m.id] = m
        mecaniciens = sorted(by_id_m.values(), key=lambda x: (x.nom or "", x.prenom or ""))
    
    # Formater les RDV
    rdvs_formatted = []
    for rdv in rdvs:
        rdvs_formatted.append({
            "id": rdv.id,
            "date_rdv": rdv.date_rdv.isoformat(),
            "heure_rdv": str(rdv.heure_rdv)[:5],
            "type_intervention": rdv.type_intervention,
            "statut": rdv.statut,
            "prix_estime": rdv.prix_estime,
            "temps_estime": rdv.temps_estime,
            "pont_id": rdv.pont_id,
            "mecanicien_id": rdv.mecanicien_id,
            "client": {
                "nom": rdv.client.nom,
                "prenom": rdv.client.prenom,
                "telephone": rdv.client.telephone
            },
            "vehicule": {
                "plaque": rdv.vehicule.plaque,
                "marque": rdv.vehicule.marque,
                "modele": rdv.vehicule.modele
            },
            "pont": {
                "id": rdv.pont.id,
                "nom": rdv.pont.nom
            } if rdv.pont else None,
            "mecanicien": {
                "id": rdv.mecanicien.id,
                "nom": rdv.mecanicien.nom,
                "prenom": rdv.mecanicien.prenom,
                "couleur": rdv.mecanicien.couleur
            } if rdv.mecanicien else None,
            "kilometrage": rdv.kilometrage,
            "etat_vehicule": rdv.etat_vehicule
        })
    
    return {
        "date": target_date.isoformat(),
        "ponts": [{"id": p.id, "nom": p.nom, "type_pont": p.type_pont, "actif": p.is_active == 1} for p in ponts],
        "mecaniciens": [{"id": m.id, "nom": m.nom, "prenom": m.prenom, "couleur": m.couleur, "actif": m.is_active == 1} for m in mecaniciens],
        "rendez_vous": rdvs_formatted
    }

@router.get("/api/planning/semaine")
def get_planning_semaine(
    date_debut: Optional[str] = None,
    atelier_slug: Optional[str] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Récupère le planning sur 7 jours à partir de la date donnée (semaine fixe Lundi-Dimanche)"""
    atelier_id = _resolve_atelier_id(current_user, db, atelier_slug)
    if date_debut:
        start_date = datetime.strptime(date_debut, "%Y-%m-%d").date()
    else:
        start_date = datetime.now().date()
    
    # Toujours commencer par le lundi de la semaine
    # weekday(): Lundi=0, Dimanche=6
    days_since_monday = start_date.weekday()
    start_date = start_date - timedelta(days=days_since_monday)
    
    end_date = start_date + timedelta(days=6)
    
    rdvs = db.query(RendezVous).options(
        joinedload(RendezVous.client),
        joinedload(RendezVous.vehicule),
        joinedload(RendezVous.pont),
        joinedload(RendezVous.mecanicien)
    ).filter(
        RendezVous.date_rdv >= start_date,
        RendezVous.date_rdv <= end_date,
        RendezVous.atelier_id == atelier_id
    ).order_by(RendezVous.date_rdv, RendezVous.heure_rdv).all()
    
    # Grouper par jour
    jours = {}
    for i in range(7):
        jour = start_date + timedelta(days=i)
        jours[jour.isoformat()] = []
    
    for rdv in rdvs:
        jour_key = rdv.date_rdv.isoformat()
        if jour_key in jours:
            jours[jour_key].append({
                "id": rdv.id,
                "date_rdv": rdv.date_rdv.isoformat(),
                "heure_rdv": str(rdv.heure_rdv)[:5],
                "type_intervention": rdv.type_intervention,
                "statut": rdv.statut,
                "prix_estime": rdv.prix_estime,
                "temps_estime": rdv.temps_estime,
                "pont_id": rdv.pont_id,
                "mecanicien_id": rdv.mecanicien_id,
                "client": {
                    "nom": rdv.client.nom,
                    "prenom": rdv.client.prenom,
                    "telephone": rdv.client.telephone
                },
                "vehicule": {
                    "plaque": rdv.vehicule.plaque,
                    "marque": rdv.vehicule.marque,
                    "modele": rdv.vehicule.modele
                },
                "pont": {
                    "id": rdv.pont.id,
                    "nom": rdv.pont.nom
                } if rdv.pont else None,
                "mecanicien": {
                    "id": rdv.mecanicien.id,
                    "nom": rdv.mecanicien.nom,
                    "prenom": rdv.mecanicien.prenom,
                    "couleur": rdv.mecanicien.couleur
                } if rdv.mecanicien else None,
                "kilometrage": rdv.kilometrage,
                "etat_vehicule": rdv.etat_vehicule
            })
    
    return {
        "date_debut": start_date.isoformat(),
        "date_fin": end_date.isoformat(),
        "jours": jours
    }
