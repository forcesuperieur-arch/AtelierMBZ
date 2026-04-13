from datetime import datetime, timedelta

from sqlalchemy.orm import Session

from models import Absence, Pont, RendezVous


WORKSHOP_SLOT_LABELS = ["09:00", "10:00", "11:00", "14:00", "15:00", "16:00", "17:00"]


def list_available_duration_slots(
    db: Session,
    *,
    atelier_id: int,
    date_debut: str,
    date_fin: str,
    duree_heures: int,
) -> list[dict]:
    """Retourne les créneaux disponibles pour une durée donnée."""
    duree_minutes = duree_heures * 60
    debut = datetime.strptime(date_debut, "%Y-%m-%d").date()
    fin = datetime.strptime(date_fin, "%Y-%m-%d").date()

    absences = db.query(Absence).filter(
        Absence.atelier_id == atelier_id,
        Absence.date_debut <= fin,
        Absence.date_fin >= debut,
    ).all()
    mecaniciens_absents_par_jour = {}
    for absence in absences:
        current = max(absence.date_debut, debut)
        while current <= min(absence.date_fin, fin):
            mecaniciens_absents_par_jour.setdefault(current, set()).add(absence.mecanicien_id)
            current += timedelta(days=1)

    nb_ponts_total = db.query(Pont).filter(Pont.is_active == 1, Pont.atelier_id == atelier_id).count()
    rdvs_existants = db.query(RendezVous).filter(
        RendezVous.atelier_id == atelier_id,
        RendezVous.date_rdv >= debut,
        RendezVous.date_rdv <= fin,
        RendezVous.statut.in_(["en_attente", "confirme", "en_cours"]),
    ).all()
    rdvs_par_jour = {}
    for rdv in rdvs_existants:
        rdvs_par_jour.setdefault(rdv.date_rdv, []).append(rdv)

    tous_creneaux = []
    current_date = debut
    while current_date <= fin:
        if current_date.weekday() < 5:
            nb_absents = len(mecaniciens_absents_par_jour.get(current_date, set()))
            nb_ponts = max(1, nb_ponts_total - nb_absents) if nb_absents < nb_ponts_total else 0
            rdvs_jour = rdvs_par_jour.get(current_date, [])

            for heure in WORKSHOP_SLOT_LABELS:
                heure_datetime = datetime.strptime(heure, "%H:%M")
                heure_fin = heure_datetime + timedelta(minutes=duree_minutes)
                if heure_fin.time() > datetime.strptime("18:00", "%H:%M").time():
                    continue

                places_occupees = 0
                for rdv in rdvs_jour:
                    rdv_heure = datetime.combine(current_date, rdv.heure_rdv)
                    rdv_fin = rdv_heure + timedelta(minutes=rdv.temps_estime or 60)
                    creneau_debut = datetime.combine(current_date, heure_datetime.time())
                    creneau_fin = datetime.combine(current_date, heure_fin.time())
                    if creneau_debut < rdv_fin and creneau_fin > rdv_heure:
                        places_occupees += 1

                places_restantes = nb_ponts - places_occupees
                tous_creneaux.append({
                    "date": current_date.isoformat(),
                    "heure": heure,
                    "heure_fin": heure_fin.strftime("%H:%M"),
                    "disponible": places_restantes > 0,
                    "places_restantes": max(0, places_restantes),
                    "places_totales": nb_ponts,
                })
        current_date += timedelta(days=1)

    return tous_creneaux