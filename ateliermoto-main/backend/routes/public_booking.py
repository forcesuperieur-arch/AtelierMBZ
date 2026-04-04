from datetime import datetime, timedelta
from typing import Optional
import os
import logging

import httpx
from fastapi import HTTPException
from sqlalchemy.exc import OperationalError
from sqlalchemy.orm import Session, joinedload

from models import (
    Absence,
    Atelier,
    CategorieMoto,
    Client,
    GrilleTarifaire,
    HoraireAtelier,
    ModeleMoto,
    Pont,
    Prestation,
    RendezVous,
    Vehicule,
)

logger = logging.getLogger("ateliermoto.api")


def _resolve_atelier(db: Session, atelier_slug: Optional[str] = "default") -> Atelier:
    slug = (atelier_slug or "default").strip().lower()
    atelier = db.query(Atelier).filter(Atelier.slug == slug, Atelier.actif == True).first()
    if not atelier and slug == "default":
        atelier = Atelier(nom="Mon Atelier", slug="default", plan="starter", actif=True)
        db.add(atelier)
        db.commit()
        db.refresh(atelier)
    if not atelier:
        raise HTTPException(status_code=404, detail="Atelier non trouvé")
    return atelier


def _to_minutes(hhmm: str) -> int:
    h, m = hhmm.split(":")
    return int(h) * 60 + int(m)


async def fetch_api_plaque_immatriculation(plaque: str):
    """Récupère les infos d'un véhicule depuis une API plaque si configurée."""
    api_key = os.getenv("API_PLAQUE_IMMATRICULATION_KEY")
    if not api_key:
        return None

    url = f"https://api.plaque-immatriculation.fr/v1/vehicule/{plaque}"
    headers = {"Authorization": f"Bearer {api_key}"}
    try:
        async with httpx.AsyncClient(timeout=10.0) as client:
            response = await client.get(url, headers=headers)
            if response.status_code == 200:
                data = response.json()
                return {
                    "plaque": plaque,
                    "marque": data.get("marque"),
                    "modele": data.get("modele"),
                    "annee": data.get("annee"),
                    "cylindree": data.get("cylindree"),
                    "type_moto": data.get("type_moto"),
                    "source": "api",
                }
            if response.status_code == 404:
                return None
            logger.warning("Erreur API Plaque Immatriculation: %s", response.status_code)
            return None
    except Exception as exc:
        logger.warning("Exception API Plaque Immatriculation: %s", exc)
        return None


def get_prestations_public_handler(atelier_slug: Optional[str], db: Session):
    """Liste les prestations actives avec grille tarifaire par type moto (sans auth)."""
    atelier = _resolve_atelier(db, atelier_slug)
    atelier_id = atelier.id
    prestations = db.query(Prestation).filter(
        Prestation.is_active == 1,
        Prestation.atelier_id == atelier_id,
    ).order_by(Prestation.categorie, Prestation.nom).all()

    grilles_par_presta = {}
    try:
        grilles = db.query(GrilleTarifaire, CategorieMoto.nom).join(
            CategorieMoto, GrilleTarifaire.categorie_moto_id == CategorieMoto.id
        ).filter(
            GrilleTarifaire.is_active == 1,
            GrilleTarifaire.categorie_moto_id.isnot(None),
            GrilleTarifaire.atelier_id == atelier_id,
        ).all()

        for grille, cat_nom in grilles:
            grilles_par_presta.setdefault(grille.prestation_id, {})[cat_nom] = {
                "prix_ttc": grille.prix_ttc,
                "prix_ht": grille.prix_ht,
                "temps_minutes": grille.temps_minutes,
            }
    except OperationalError as exc:
        logger.warning("Fallback tarifs publics sans categorie_moto_id: %s", exc)

    result = []
    for prestation in prestations:
        result.append({
            "id": prestation.id,
            "code": prestation.code,
            "nom": prestation.nom,
            "description": prestation.description,
            "categorie": prestation.categorie,
            "type_tarif": prestation.type_tarif,
            "prix_base_ttc": prestation.prix_promo_ttc if prestation.is_promo and prestation.prix_promo_ttc else prestation.prix_base_ttc,
            "prix_base_ht": prestation.prix_base_ht,
            "temps_estime_minutes": prestation.temps_estime_minutes,
            "is_forfait": prestation.is_forfait,
            "is_promo": prestation.is_promo,
            "prix_promo_ttc": prestation.prix_promo_ttc,
            "tarifs": grilles_par_presta.get(prestation.id, {}),
        })
    return result


async def get_vehicule_by_plaque_handler(plaque: str, db: Session):
    """Récupère les informations d'un véhicule par sa plaque."""
    plaque_clean = plaque.upper().replace(" ", "").replace("-", "")
    vehicule_db = db.query(Vehicule).filter(Vehicule.plaque == plaque_clean).first()
    if vehicule_db:
        categorie_id = None
        if vehicule_db.modele_id:
            modele = db.query(ModeleMoto).filter(ModeleMoto.id == vehicule_db.modele_id).first()
            if modele:
                categorie_id = modele.categorie_id
        return {
            "id": vehicule_db.id,
            "plaque": vehicule_db.plaque,
            "marque": vehicule_db.marque,
            "modele": vehicule_db.modele,
            "annee": vehicule_db.annee,
            "cylindree": vehicule_db.cylindree,
            "type_moto": vehicule_db.type_moto,
            "categorie_id": categorie_id,
            "modele_id": vehicule_db.modele_id,
            "source": "database",
        }

    api_data = await fetch_api_plaque_immatriculation(plaque_clean)
    if api_data:
        new_vehicule = Vehicule(
            plaque=api_data["plaque"],
            marque=api_data["marque"],
            modele=api_data["modele"],
            annee=api_data["annee"],
            cylindree=api_data["cylindree"],
            type_moto=api_data["type_moto"],
        )
        db.add(new_vehicule)
        db.commit()
        return api_data

    return {
        "plaque": plaque_clean,
        "marque": None,
        "modele": None,
        "annee": None,
        "cylindree": None,
        "type_moto": None,
        "source": "not_found",
        "not_found": True,
        "message": "Véhicule non trouvé. Veuillez renseigner les informations.",
    }


def create_rendez_vous_public_handler(rdv_data, db: Session):
    """Crée un rendez-vous depuis l'interface publique (sans authentification)."""
    atelier = _resolve_atelier(db, getattr(rdv_data, "atelier_slug", "default"))
    atelier_id = atelier.id

    client = db.query(Client).filter(
        Client.telephone == rdv_data.client["telephone"],
        Client.atelier_id == atelier_id,
    ).first()
    if not client:
        client = Client(
            atelier_id=atelier_id,
            nom=rdv_data.client["nom"],
            prenom=rdv_data.client["prenom"],
            telephone=rdv_data.client["telephone"],
            email=rdv_data.client.get("email"),
        )
        db.add(client)
        db.flush()

    plaque_clean = rdv_data.vehicule["plaque"].upper().replace(" ", "").replace("-", "")
    vehicule = db.query(Vehicule).filter(
        Vehicule.plaque == plaque_clean,
        Vehicule.atelier_id == atelier_id,
    ).first()
    if not vehicule:
        vehicule = Vehicule(
            atelier_id=atelier_id,
            plaque=plaque_clean,
            marque=rdv_data.vehicule.get("marque", "Inconnue"),
            modele=rdv_data.vehicule.get("modele", "Inconnu"),
            annee=rdv_data.vehicule.get("annee"),
            cylindree=rdv_data.vehicule.get("cylindree"),
            type_moto=rdv_data.vehicule.get("type_moto"),
            categorie_id=rdv_data.vehicule.get("categorie_id"),
            client_id=client.id,
        )
        db.add(vehicule)
        db.flush()
    else:
        if not vehicule.type_moto and rdv_data.vehicule.get("type_moto"):
            vehicule.type_moto = rdv_data.vehicule["type_moto"]
        if not vehicule.client_id:
            vehicule.client_id = client.id

    try:
        date_heure_str = rdv_data.date_heure.replace("Z", "+00:00")
        if ":" in date_heure_str:
            date_heure = datetime.fromisoformat(date_heure_str)
        else:
            date_heure = datetime.fromisoformat(date_heure_str + "T09:00:00")
    except Exception as exc:
        logger.warning("Erreur parsing date publique: %s (%s)", exc, rdv_data.date_heure)
        date_heure = datetime.now().replace(hour=9, minute=0, second=0, microsecond=0)

    horaire = db.query(HoraireAtelier).filter(
        HoraireAtelier.atelier_id == atelier_id,
        HoraireAtelier.jour_semaine == date_heure.date().weekday(),
    ).first()
    if horaire and not horaire.is_ouvert:
        raise HTTPException(status_code=400, detail="Atelier ferme ce jour")
    if horaire and (not horaire.heure_ouverture or not horaire.heure_fermeture):
        horaire = None
    if horaire:
        slot = date_heure.hour * 60 + date_heure.minute
        if slot < _to_minutes(horaire.heure_ouverture) or slot >= _to_minutes(horaire.heure_fermeture):
            raise HTTPException(status_code=400, detail="Creneau en dehors des horaires d'ouverture")
        if horaire.pause_debut and horaire.pause_fin and _to_minutes(horaire.pause_debut) <= slot < _to_minutes(horaire.pause_fin):
            raise HTTPException(status_code=400, detail="Creneau pendant la fermeture midi")

    prestations = db.query(Prestation).filter(
        Prestation.id.in_(rdv_data.prestations),
        Prestation.atelier_id == atelier_id,
    ).all()
    types_intervention = ", ".join([prestation.nom for prestation in prestations]) or "Intervention à définir"
    temps_total = sum(prestation.temps_estime_minutes or 60 for prestation in prestations) or 60

    if horaire:
        close_min = _to_minutes(horaire.heure_fermeture)
        start_min = date_heure.hour * 60 + date_heure.minute
        if (start_min + temps_total) > close_min:
            raise HTTPException(status_code=400, detail="Duree intervention depasse l'heure de fermeture")

    assigned_mecanicien_id = None
    if rdv_data.pont_id:
        pont = db.query(Pont).filter(
            Pont.id == rdv_data.pont_id,
            Pont.atelier_id == atelier_id,
            Pont.is_active == 1,
        ).first()
        if not pont:
            raise HTTPException(status_code=400, detail="Pont indisponible pour cet atelier")
        if not pont.mecanicien_id:
            raise HTTPException(status_code=400, detail="Pont sans technicien assigne")
        assigned_mecanicien_id = pont.mecanicien_id

    rdv = RendezVous(
        atelier_id=atelier_id,
        client_id=client.id,
        vehicule_id=vehicule.id,
        date_rdv=date_heure.date(),
        heure_rdv=date_heure.time(),
        type_intervention=types_intervention,
        prix_estime=rdv_data.montant_estime,
        temps_estime=temps_total,
        statut="reserve",
        commentaire=rdv_data.commentaires,
        pont_id=rdv_data.pont_id,
        mecanicien_id=assigned_mecanicien_id,
    )
    db.add(rdv)
    db.commit()
    db.refresh(rdv)
    return {
        "id": rdv.id,
        "message": "Rendez-vous créé avec succès",
        "date": rdv.date_rdv.isoformat(),
        "heure": rdv.heure_rdv.isoformat() if rdv.heure_rdv else None,
    }


def get_creneaux_disponibles_handler(date_str: str, duree_minutes: int, atelier_slug: Optional[str], db: Session):
    """Récupère les créneaux disponibles pour une date donnée avec gestion des absences."""
    atelier = _resolve_atelier(db, atelier_slug)
    atelier_id = atelier.id
    target_date = datetime.strptime(date_str, "%Y-%m-%d").date()

    absences = db.query(Absence).filter(
        Absence.date_debut <= target_date,
        Absence.date_fin >= target_date,
        Absence.atelier_id == atelier_id,
    ).all()
    mecaniciens_absents = [absence.mecanicien_id for absence in absences]

    query_ponts = db.query(Pont).filter(
        Pont.is_active == 1,
        Pont.mecanicien_id.isnot(None),
        Pont.atelier_id == atelier_id,
    )
    if mecaniciens_absents:
        query_ponts = query_ponts.filter(~Pont.mecanicien_id.in_(mecaniciens_absents))
    ponts_disponibles = query_ponts.count()

    rdvs_existants = db.query(RendezVous).filter(
        RendezVous.date_rdv == target_date,
        RendezVous.atelier_id == atelier_id,
        RendezVous.statut.in_(["reserve", "en_attente", "confirme", "reception", "en_cours"]),
    ).all()

    requested_duration = max(15, int(duree_minutes or 60))
    horaire = db.query(HoraireAtelier).filter(
        HoraireAtelier.atelier_id == atelier_id,
        HoraireAtelier.jour_semaine == target_date.weekday(),
    ).first()

    creneaux = []
    is_closed = horaire and not horaire.is_ouvert
    if not is_closed:
        open_min = _to_minutes(horaire.heure_ouverture) if horaire and horaire.heure_ouverture else _to_minutes("08:00")
        close_min = _to_minutes(horaire.heure_fermeture) if horaire and horaire.heure_fermeture else _to_minutes("18:00")
        pause_start = _to_minutes(horaire.pause_debut) if (horaire and horaire.pause_debut) else None
        pause_end = _to_minutes(horaire.pause_fin) if (horaire and horaire.pause_fin) else None

        for start_min in range(open_min, close_min, 15):
            end_min = start_min + requested_duration
            if end_min > close_min:
                continue
            if pause_start is not None and pause_end is not None and pause_start <= start_min < pause_end:
                continue

            ponts_occupes = 0
            for rdv in rdvs_existants:
                if not rdv.heure_rdv:
                    continue
                rdv_start = rdv.heure_rdv.hour * 60 + rdv.heure_rdv.minute
                rdv_end = rdv_start + int(rdv.temps_estime or 60)
                if pause_start is not None and pause_end is not None and rdv_start < pause_start and rdv_end > pause_start:
                    segments = [(rdv_start, pause_start), (pause_end, pause_end + (rdv_end - pause_start))]
                else:
                    segments = [(rdv_start, rdv_end)]
                overlaps = any(start_min < seg_end and end_min > seg_start for seg_start, seg_end in segments)
                if overlaps:
                    ponts_occupes += 1

            places_restantes = ponts_disponibles - ponts_occupes
            creneaux.append({
                "heure": f"{start_min // 60:02d}:{start_min % 60:02d}",
                "disponible": places_restantes > 0,
                "places_restantes": max(0, places_restantes),
                "places_totales": ponts_disponibles,
            })

    return {
        "date": date_str,
        "ponts_disponibles": ponts_disponibles,
        "mecaniciens_absents": len(mecaniciens_absents),
        "creneaux": creneaux,
    }


def get_creneaux_avec_ponts_handler(date_str: str, duree_minutes: int, atelier_slug: Optional[str], db: Session):
    """Récupère les créneaux disponibles avec les ponts spécifiques libres."""
    atelier = _resolve_atelier(db, atelier_slug)
    atelier_id = atelier.id
    target_date = datetime.strptime(date_str, "%Y-%m-%d").date()

    absences = db.query(Absence).filter(
        Absence.date_debut <= target_date,
        Absence.date_fin >= target_date,
        Absence.atelier_id == atelier_id,
    ).all()
    mecaniciens_absents = [absence.mecanicien_id for absence in absences]

    query_ponts = db.query(Pont).options(joinedload(Pont.mecanicien)).filter(
        Pont.is_active == 1,
        Pont.mecanicien_id.isnot(None),
        Pont.atelier_id == atelier_id,
    )
    if mecaniciens_absents:
        query_ponts = query_ponts.filter(~Pont.mecanicien_id.in_(mecaniciens_absents))
    ponts_disponibles = query_ponts.all()

    rdvs_existants = db.query(RendezVous).filter(
        RendezVous.date_rdv == target_date,
        RendezVous.atelier_id == atelier_id,
        RendezVous.statut.in_(["reserve", "en_attente", "confirme", "reception", "en_cours"]),
    ).all()

    rdv_plages = []
    for rdv in rdvs_existants:
        if rdv.heure_rdv and rdv.pont_id:
            heure_debut = datetime.combine(target_date, rdv.heure_rdv)
            heure_fin = heure_debut + timedelta(minutes=(rdv.temps_estime or 60))
            rdv_plages.append({"pont_id": rdv.pont_id, "debut": heure_debut, "fin": heure_fin})

    requested_duration = max(15, int(duree_minutes or 60))
    horaire = db.query(HoraireAtelier).filter(
        HoraireAtelier.atelier_id == atelier_id,
        HoraireAtelier.jour_semaine == target_date.weekday(),
    ).first()

    creneaux = []
    is_closed = horaire and not horaire.is_ouvert
    if not is_closed:
        open_min = _to_minutes(horaire.heure_ouverture) if horaire and horaire.heure_ouverture else _to_minutes("08:00")
        close_min = _to_minutes(horaire.heure_fermeture) if horaire and horaire.heure_fermeture else _to_minutes("18:00")
        pause_start = _to_minutes(horaire.pause_debut) if (horaire and horaire.pause_debut) else None
        pause_end = _to_minutes(horaire.pause_fin) if (horaire and horaire.pause_fin) else None

        for start_min in range(open_min, close_min, 15):
            end_min = start_min + requested_duration
            if end_min > close_min:
                continue
            if pause_start is not None and pause_end is not None and pause_start <= start_min < pause_end:
                continue

            slot_start = datetime.combine(target_date, datetime.min.time()) + timedelta(minutes=start_min)
            slot_end = slot_start + timedelta(minutes=requested_duration)
            ponts_libres = []
            for pont in ponts_disponibles:
                occupied = False
                for plage in rdv_plages:
                    if plage["pont_id"] != pont.id:
                        continue
                    if slot_start < plage["fin"] and slot_end > plage["debut"]:
                        occupied = True
                        break
                if not occupied:
                    ponts_libres.append({
                        "id": pont.id,
                        "nom": pont.nom,
                        "mecanicien": f"{pont.mecanicien.prenom} {pont.mecanicien.nom}" if getattr(pont, "mecanicien", None) else None,
                    })

            creneaux.append({
                "heure": f"{start_min // 60:02d}:{start_min % 60:02d}",
                "disponible": len(ponts_libres) > 0,
                "nb_ponts_libres": len(ponts_libres),
                "ponts_disponibles": ponts_libres,
            })

    return {
        "date": date_str,
        "creneaux": creneaux,
        "ponts_disponibles": len(ponts_disponibles),
        "mecaniciens_absents": len(mecaniciens_absents),
    }


def get_delais_intervention_handler(prestation_ids: Optional[str], db: Session):
    """Récupère les délais d'intervention pour des prestations."""
    delais = {}
    if prestation_ids:
        ids = [int(id_) for id_ in prestation_ids.split(",") if str(id_).strip()]
        prestations = db.query(Prestation).filter(Prestation.id.in_(ids)).all()
        for prestation in prestations:
            delais[prestation.id] = {
                "nom": prestation.nom,
                "delai_jours": prestation.delai_intervention_jours,
                "temps_minutes": prestation.temps_estime_minutes,
            }
    return {
        "delais_par_prestation": delais,
        "delai_total_jours": max([d["delai_jours"] for d in delais.values()]) if delais else 1,
    }
