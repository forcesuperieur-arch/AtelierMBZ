# Seed functions pour les tables de paramétrabilité
import json
from models import SessionLocal, TempsIntervention, HoraireAtelier, PontEquipement, InterventionType, CategorieMoto, Pont, Prestation, GrilleTarifaire, ConfigAtelier

def init_temps_interventions(db):
    """Initialiser les durées d'intervention par catégorie moto"""
    # Vérifier si déjà initialisé
    if db.query(TempsIntervention).count() > 0:
        return

    # Mapping: intervention_id, categorie_nom → temps_minutes
    donnees = [
        # Vidange simple (ID 3)
        (3, "Roadster", 45, 1.0),
        (3, "Sportive", 45, 1.0),
        (3, "Trail", 50, 1.1),
        (3, "Scooter", 40, 0.9),
        (3, "Cross/Enduro", 50, 1.1),
        (3, "Cruiser", 50, 1.1),
        (3, "Touring", 50, 1.1),
        (3, "Supermotard", 45, 1.0),

        # Révision 10k (ID 1)
        (1, "Roadster", 90, 1.0),
        (1, "Sportive", 100, 1.1),
        (1, "Trail", 110, 1.2),
        (1, "Scooter", 75, 0.8),
        (1, "Cross/Enduro", 110, 1.2),
        (1, "Cruiser", 100, 1.1),
        (1, "Touring", 110, 1.2),
        (1, "Supermotard", 90, 1.0),

        # Révision 20k (ID 2)
        (2, "Roadster", 120, 1.0),
        (2, "Sportive", 140, 1.2),
        (2, "Trail", 150, 1.3),
        (2, "Scooter", 100, 0.8),
        (2, "Cross/Enduro", 150, 1.3),
        (2, "Cruiser", 130, 1.1),
        (2, "Touring", 150, 1.3),
        (2, "Supermotard", 120, 1.0),

        # Changement pneus (ID 4)
        (4, "Roadster", 60, 1.0),
        (4, "Sportive", 60, 1.0),
        (4, "Trail", 80, 1.3),
        (4, "Scooter", 40, 0.7),
        (4, "Cross/Enduro", 100, 1.7),
        (4, "Cruiser", 80, 1.3),
        (4, "Touring", 70, 1.2),
        (4, "Supermotard", 60, 1.0),
    ]

    for intervention_id, categorie_nom, temps, coeff in donnees:
        categorie = db.query(CategorieMoto).filter(CategorieMoto.nom == categorie_nom).first()
        if not categorie:
            continue

        t = TempsIntervention(
            intervention_type_id=intervention_id,
            categorie_moto_id=categorie.id,
            temps_minutes=temps,
            coefficient_difficulte=coeff
        )
        db.add(t)

    db.commit()
    print("✓ Times interventions initialisées")


def init_horaires_atelier(db):
    """Initialiser les horaires d'atelier (8h-18h sauf dimanche)"""
    if db.query(HoraireAtelier).count() > 0:
        return

    # Jours: 0=Lun, 1=Mar, ..., 6=Dim
    horaires = [
        (0, "08:00", "18:00", "12:00", "13:30", 1),  # Lundi: 8h-18h avec pause 12h-13h30
        (1, "08:00", "18:00", "12:00", "13:30", 1),  # Mardi
        (2, "08:00", "18:00", "12:00", "13:30", 1),  # Mercredi
        (3, "08:00", "18:00", "12:00", "13:30", 1),  # Jeudi
        (4, "08:00", "18:00", "12:00", "13:30", 1),  # Vendredi
        (5, "09:00", "13:00", None, None, 1),        # Samedi: 9h-13h (pas de pause)
        (6, None, None, None, None, 0),              # Dimanche: fermé
    ]

    jours_noms = ["Lundi", "Mardi", "Mercredi", "Jeudi", "Vendredi", "Samedi", "Dimanche"]

    for jour, ouv, ferm, p_deb, p_fin, is_ouv in horaires:
        h = HoraireAtelier(
            jour_semaine=jour,
            heure_ouverture=ouv,
            heure_fermeture=ferm,
            pause_debut=p_deb,
            pause_fin=p_fin,
            is_ouvert=is_ouv
        )
        db.add(h)

    db.commit()
    print("✓ Horaires atelier initialisés")


def init_pont_equipements(db):
    """Initialiser les équipements par pont"""
    if db.query(PontEquipement).count() > 0:
        return

    ponts = db.query(Pont).all()

    # Équipements génériques (tous les ponts)
    equipements_generiques = [
        ("Élévateur hydraulique", "Pont élévateur standard"),
        ("Compresseur", "Compresseur air"),
        ("Prise OBD", "Prise diagnostic OBD2"),
    ]

    # Équipements spécifiques par pont
    equipements_specifiques = {
        1: ["Démonte-pneu", "Équilibreuse"],  # Pont 1
        2: ["Démonte-pneu", "Équilibreuse", "Bac vidange"],  # Pont 2
        3: ["Valise diagnostic", "Oscilloscope"],  # Pont 3
        4: [],  # Pont Scooter (recevra juste les génériques)
    }

    for pont in ponts:
        # Ajouter équipements génériques
        for nom, desc in equipements_generiques:
            eq = PontEquipement(
                pont_id=pont.id,
                nom=nom,
                description=desc,
                is_present=1
            )
            db.add(eq)

        # Ajouter équipements spécifiques
        specifiques = equipements_specifiques.get(pont.id, [])
        for nom in specifiques:
            eq = PontEquipement(
                pont_id=pont.id,
                nom=nom,
                description=f"{nom} installé sur {pont.nom}",
                is_present=1
            )
            db.add(eq)

    db.commit()
    print("✓ Équipements ponts initialisés")


def init_parametres(db):
    """Lancer toutes les initialisations de paramétrabilité"""
    init_temps_interventions(db)
    init_horaires_atelier(db)
    init_pont_equipements(db)
    init_grille_tarifaire(db)


def init_grille_tarifaire(db):
    """Initialiser la grille tarifaire par type de moto pour chaque prestation"""
    if db.query(GrilleTarifaire).filter(GrilleTarifaire.categorie_moto_id.isnot(None)).count() > 0:
        return

    # Récupérer la config TVA
    config = db.query(ConfigAtelier).first()
    tva_taux = config.tva_mo_taux if config else 20.0

    # Récupérer toutes les catégories moto
    categories = db.query(CategorieMoto).all()
    if not categories:
        return

    # Récupérer toutes les prestations actives
    prestations = db.query(Prestation).filter(Prestation.is_active == 1).all()
    if not prestations:
        return

    # Coefficients par catégorie moto (temps et prix)
    # Base = Roadster (1.0), ajustements pour les autres
    coefs = {
        "Roadster": 1.0,
        "Sportive": 1.10,
        "Trail": 1.15,
        "Scooter": 0.85,
        "Cruiser": 1.10,
        "Cross/Enduro": 1.20,
        "Touring": 1.15,
        "Supermotard": 1.0,
    }

    for presta in prestations:
        for cat in categories:
            coef = coefs.get(cat.nom, 1.0)

            # Calculer prix et temps ajustés
            prix_ttc = round(presta.prix_base_ttc * coef, 2)
            prix_ht = round(prix_ttc / (1 + tva_taux / 100), 2)
            temps = max(30, round(presta.temps_estime_minutes * coef / 15) * 15)  # arrondi 15min

            grille = GrilleTarifaire(
                prestation_id=presta.id,
                categorie_moto_id=cat.id,
                prix_ht=prix_ht,
                prix_ttc=prix_ttc,
                temps_minutes=temps,
                delai_jours=presta.delai_intervention_jours or 1,
                is_active=1
            )
            db.add(grille)

    db.commit()
    print("✓ Grille tarifaire par type moto initialisée")
