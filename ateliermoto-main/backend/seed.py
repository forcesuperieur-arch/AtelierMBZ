from sqlalchemy.orm import Session
from models import InterventionType, Pont, Mecanicien, Fournisseur, PieceDetachee, ConfigAtelier, ForfaitMO, Prestation, CategorieMoto, ModeleMoto

def init_intervention_types(db: Session):
    """Initialise les types d'intervention avec prix et temps"""
    
    interventions = [
        {
            "nom": "Révision 10 000km",
            "description": "Vidange, filtre à huile, contrôle général",
            "prix_base": 180.0,
            "temps_estime": 90
        },
        {
            "nom": "Révision 20 000km",
            "description": "Vidange, filtres, bougies, contrôle général",
            "prix_base": 280.0,
            "temps_estime": 120
        },
        {
            "nom": "Vidange simple",
            "description": "Remplacement huile et filtre",
            "prix_base": 95.0,
            "temps_estime": 45
        },
        {
            "nom": "Changement pneus (x2)",
            "description": "Démontage, montage, équilibrage",
            "prix_base": 45.0,
            "temps_estime": 60
        },
        {
            "nom": "Révision freins avant",
            "description": "Plaquettes, disques, purge",
            "prix_base": 150.0,
            "temps_estime": 75
        },
        {
            "nom": "Révision freins arrière",
            "description": "Plaquettes, disques/tambours, purge",
            "prix_base": 140.0,
            "temps_estime": 75
        },
        {
            "nom": "Remplacement chaîne/courroie",
            "description": "Chaîne, couronne, pignon, réglage",
            "prix_base": 220.0,
            "temps_estime": 90
        },
        {
            "nom": "Remplacement batterie",
            "description": "Batterie + réinitialisation",
            "prix_base": 120.0,
            "temps_estime": 30
        },
        {
            "nom": "Diagnostic électronique",
            "description": "Lecture codes défaut, analyse",
            "prix_base": 65.0,
            "temps_estime": 30
        },
        {
            "nom": "Montage accessoire",
            "description": "Pose d'accessoires divers",
            "prix_base": 60.0,
            "temps_estime": 45
        }
    ]
    
    for interv in interventions:
        existing = db.query(InterventionType).filter(InterventionType.nom == interv["nom"]).first()
        if not existing:
            new_interv = InterventionType(**interv)
            db.add(new_interv)
    
    db.commit()
    
    # Initialiser les ponts
    ponts = [
        {"nom": "Pont 1", "type_pont": "moto", "capacite_kg": 500, "ordre_affichage": 1},
        {"nom": "Pont 2", "type_pont": "moto", "capacite_kg": 500, "ordre_affichage": 2},
        {"nom": "Pont 3", "type_pont": "moto", "capacite_kg": 500, "ordre_affichage": 3},
        {"nom": "Pont Scooter", "type_pont": "scooter", "capacite_kg": 300, "ordre_affichage": 4},
    ]
    
    for pont in ponts:
        existing = db.query(Pont).filter(Pont.nom == pont["nom"]).first()
        if not existing:
            new_pont = Pont(**pont)
            db.add(new_pont)
    
    db.commit()
    
    # Initialiser les mécaniciens
    mecaniciens = [
        {"nom": "Martin", "prenom": "Jean", "specialites": "[\"moteur\", \"revision\"]", "couleur": "#3b82f6"},
        {"nom": "Dubois", "prenom": "Pierre", "specialites": "[\"electricite\", \"diagnostic\"]", "couleur": "#10b981"},
        {"nom": "Bernard", "prenom": "Lucas", "specialites": "[\"carrosserie\", \"peinture\"]", "couleur": "#f59e0b"},
        {"nom": "Petit", "prenom": "Marc", "specialites": "[\"moteur\", \"transmission\"]", "couleur": "#8b5cf6"},
    ]
    
    for mecano in mecaniciens:
        existing = db.query(Mecanicien).filter(
            Mecanicien.nom == mecano["nom"],
            Mecanicien.prenom == mecano["prenom"]
        ).first()
        if not existing:
            new_mecano = Mecanicien(**mecano)
            db.add(new_mecano)
    
    db.commit()
    
    # Initialiser les fournisseurs
    fournisseurs = [
        {
            "nom": "Moto Pieces France",
            "contact": "Jean Dupont",
            "telephone": "01 23 45 67 89",
            "email": "contact@motopieces.fr",
            "adresse": "15 Rue des Pièces, 75011 Paris",
            "siret": "123 456 789 00012",
            "delai_livraison_jours": 2,
            "notes": "Fournisseur principal, bon rapport qualité/prix"
        },
        {
            "nom": "Bike Parts Pro",
            "contact": "Marie Leroy",
            "telephone": "04 56 78 90 12",
            "email": "commandes@bikepartspro.fr",
            "adresse": "42 Avenue de la Mécanique, 69003 Lyon",
            "siret": "987 654 321 00021",
            "delai_livraison_jours": 3,
            "notes": "Spécialisé pièces haute performance"
        },
        {
            "nom": "Scoot Distribution",
            "contact": "Pierre Martin",
            "telephone": "03 20 45 67 89",
            "email": "contact@scootdistribution.fr",
            "adresse": "8 Zone Industrielle Nord, 59000 Lille",
            "siret": "456 789 123 00034",
            "delai_livraison_jours": 1,
            "notes": "Spécialisé scooters et mobylettes"
        },
        {
            "nom": "Huiles et Lubrifiants 2000",
            "contact": "Sophie Bernard",
            "telephone": "05 61 23 45 67",
            "email": "pro@huiles2000.fr",
            "adresse": "25 Rue de l'Industrie, 31000 Toulouse",
            "siret": "789 123 456 00045",
            "delai_livraison_jours": 2,
            "notes": "Toutes marques d'huiles et graisses"
        }
    ]
    
    for fournisseur_data in fournisseurs:
        existing = db.query(Fournisseur).filter(Fournisseur.nom == fournisseur_data["nom"]).first()
        if not existing:
            new_fournisseur = Fournisseur(**fournisseur_data)
            db.add(new_fournisseur)
    
    db.commit()
    
    # Initialiser les pièces détachées
    pieces = [
        # Huiles et filtres
        {
            "reference": "HUI-10W40-1L",
            "reference_fournisseur": "MOTUL-10W40",
            "nom": "Huile moteur 10W40 1L",
            "description": "Huile semi-synthétique pour moteur 4 temps",
            "categorie": "huiles",
            "quantite_stock": 24,
            "quantite_minimale": 12,
            "quantite_maximale": 50,
            "emplacement": "Étagère A1",
            "prix_achat_ht": 8.50,
            "prix_vente_ht": 14.90,
            "tva_taux": 20.0,
            "fournisseur_nom": "Huiles et Lubrifiants 2000"
        },
        {
            "reference": "HUI-10W40-4L",
            "reference_fournisseur": "MOTUL-10W40-4L",
            "nom": "Huile moteur 10W40 4L",
            "description": "Huile semi-synthétique 4L, économique",
            "categorie": "huiles",
            "quantite_stock": 8,
            "quantite_minimale": 4,
            "quantite_maximale": 15,
            "emplacement": "Étagère A1",
            "prix_achat_ht": 28.00,
            "prix_vente_ht": 45.90,
            "tva_taux": 20.0,
            "fournisseur_nom": "Huiles et Lubrifiants 2000"
        },
        {
            "reference": "FIL-HUI-STD",
            "reference_fournisseur": "HF-204",
            "nom": "Filtre à huile standard",
            "description": "Filtre à huile universel 4 temps",
            "categorie": "filtres",
            "quantite_stock": 15,
            "quantite_minimale": 10,
            "quantite_maximale": 40,
            "emplacement": "Étagère A2",
            "prix_achat_ht": 4.50,
            "prix_vente_ht": 9.90,
            "tva_taux": 20.0,
            "fournisseur_nom": "Moto Pieces France"
        },
        {
            "reference": "FIL-AIR-STD",
            "reference_fournisseur": "HA-101",
            "nom": "Filtre à air standard",
            "description": "Filtre à air adaptable multi-marques",
            "categorie": "filtres",
            "quantite_stock": 6,
            "quantite_minimale": 8,
            "quantite_maximale": 25,
            "emplacement": "Étagère A2",
            "prix_achat_ht": 8.00,
            "prix_vente_ht": 16.50,
            "tva_taux": 20.0,
            "fournisseur_nom": "Moto Pieces France"
        },
        # Freinage
        {
            "reference": "PLA-FRE-AV",
            "reference_fournisseur": "BP-AV-STD",
            "nom": "Plaquettes frein avant",
            "description": "Plaquettes frein avant organiques",
            "categorie": "freinage",
            "quantite_stock": 12,
            "quantite_minimale": 6,
            "quantite_maximale": 20,
            "emplacement": "Étagère B1",
            "prix_achat_ht": 12.00,
            "prix_vente_ht": 29.90,
            "tva_taux": 20.0,
            "fournisseur_nom": "Moto Pieces France"
        },
        {
            "reference": "PLA-FRE-AR",
            "reference_fournisseur": "BP-AR-STD",
            "nom": "Plaquettes frein arrière",
            "description": "Plaquettes frein arrière organiques",
            "categorie": "freinage",
            "quantite_stock": 4,
            "quantite_minimale": 5,
            "quantite_maximale": 20,
            "emplacement": "Étagère B1",
            "prix_achat_ht": 11.00,
            "prix_vente_ht": 26.90,
            "tva_taux": 20.0,
            "fournisseur_nom": "Moto Pieces France"
        },
        {
            "reference": "DIS-FRE-AV-260",
            "reference_fournisseur": "DISC-260-BREM",
            "nom": "Disque de frein avant 260mm",
            "description": "Disque de frein avant flottant 260mm",
            "categorie": "freinage",
            "quantite_stock": 3,
            "quantite_minimale": 2,
            "quantite_maximale": 10,
            "emplacement": "Étagère B2",
            "prix_achat_ht": 45.00,
            "prix_vente_ht": 89.90,
            "tva_taux": 20.0,
            "fournisseur_nom": "Bike Parts Pro"
        },
        {
            "reference": "LIQ-FRE-500",
            "reference_fournisseur": "DOT4-500",
            "nom": "Liquide de frein DOT4 500ml",
            "description": "Liquide de frein synthétique DOT4",
            "categorie": "freinage",
            "quantite_stock": 18,
            "quantite_minimale": 6,
            "quantite_maximale": 24,
            "emplacement": "Étagère B3",
            "prix_achat_ht": 3.50,
            "prix_vente_ht": 7.90,
            "tva_taux": 20.0,
            "fournisseur_nom": "Huiles et Lubrifiants 2000"
        },
        # Transmission
        {
            "reference": "CHAI-520-120",
            "reference_fournisseur": "CHAIN-520-120L",
            "nom": "Chaîne 520 x 120 maillons",
            "description": "Chaîne de transmission 520 oring",
            "categorie": "transmission",
            "quantite_stock": 5,
            "quantite_minimale": 3,
            "quantite_maximale": 12,
            "emplacement": "Étagère C1",
            "prix_achat_ht": 35.00,
            "prix_vente_ht": 69.90,
            "tva_taux": 20.0,
            "fournisseur_nom": "Bike Parts Pro"
        },
        {
            "reference": "KIT-CHAINE-YAM-MT07",
            "reference_fournisseur": "AFAM-MT07-KIT",
            "nom": "Kit chaîne Yamaha MT-07",
            "description": "Chaîne + couronne + pignon pour MT-07",
            "categorie": "transmission",
            "quantite_stock": 2,
            "quantite_minimale": 2,
            "quantite_maximale": 8,
            "emplacement": "Étagère C1",
            "prix_achat_ht": 85.00,
            "prix_vente_ht": 159.90,
            "tva_taux": 20.0,
            "fournisseur_nom": "Bike Parts Pro"
        },
        # Électricité
        {
            "reference": "BAT-12V-9AH",
            "reference_fournisseur": "YUASA-YTX9",
            "nom": "Batterie 12V 9Ah",
            "description": "Batterie sans entretien YTX9-BS",
            "categorie": "electricite",
            "quantite_stock": 7,
            "quantite_minimale": 4,
            "quantite_maximale": 15,
            "emplacement": "Étagère D1",
            "prix_achat_ht": 32.00,
            "prix_vente_ht": 59.90,
            "tva_taux": 20.0,
            "fournisseur_nom": "Moto Pieces France"
        },
        {
            "reference": "BOU-IRI-CR9E",
            "reference_fournisseur": "NGK-CR9E",
            "nom": "Bougie NGK CR9E",
            "description": "Bougie d'allumage NGK standard",
            "categorie": "electricite",
            "quantite_stock": 20,
            "quantite_minimale": 12,
            "quantite_maximale": 40,
            "emplacement": "Étagère D2",
            "prix_achat_ht": 6.00,
            "prix_vente_ht": 12.90,
            "tva_taux": 20.0,
            "fournisseur_nom": "Moto Pieces France"
        },
        # Pneumatiques
        {
            "reference": "PNE-120-70-17",
            "reference_fournisseur": "MIC-ROAD-120",
            "nom": "Pneu avant 120/70-17",
            "description": "Pneu route sport 120/70 ZR17",
            "categorie": "pneumatiques",
            "quantite_stock": 4,
            "quantite_minimale": 4,
            "quantite_maximale": 12,
            "emplacement": "Rack Pneus",
            "prix_achat_ht": 65.00,
            "prix_vente_ht": 119.90,
            "tva_taux": 20.0,
            "fournisseur_nom": "Bike Parts Pro"
        },
        {
            "reference": "PNE-180-55-17",
            "reference_fournisseur": "MIC-ROAD-180",
            "nom": "Pneu arrière 180/55-17",
            "description": "Pneu route sport 180/55 ZR17",
            "categorie": "pneumatiques",
            "quantite_stock": 2,
            "quantite_minimale": 4,
            "quantite_maximale": 12,
            "emplacement": "Rack Pneus",
            "prix_achat_ht": 85.00,
            "prix_vente_ht": 149.90,
            "tva_taux": 20.0,
            "fournisseur_nom": "Bike Parts Pro"
        },
        # Consommables
        {
            "reference": "GRA-CHA-400",
            "reference_fournisseur": "MOTUL-C4-400",
            "nom": "Graisse chaîne 400ml",
            "description": "Graisse blanche pour chaîne",
            "categorie": "consommables",
            "quantite_stock": 14,
            "quantite_minimale": 6,
            "quantite_maximale": 20,
            "emplacement": "Étagère E1",
            "prix_achat_ht": 9.00,
            "prix_vente_ht": 16.90,
            "tva_taux": 20.0,
            "fournisseur_nom": "Huiles et Lubrifiants 2000"
        },
        {
            "reference": "NET-CHA-1L",
            "reference_fournisseur": "MOTUL-C1-1L",
            "nom": "Nettoyant chaîne 1L",
            "description": "Dégraissant chaîne et freins",
            "categorie": "consommables",
            "quantite_stock": 9,
            "quantite_minimale": 4,
            "quantite_maximale": 15,
            "emplacement": "Étagère E1",
            "prix_achat_ht": 11.00,
            "prix_vente_ht": 19.90,
            "tva_taux": 20.0,
            "fournisseur_nom": "Huiles et Lubrifiants 2000"
        }
    ]
    
    # Créer les pièces
    for piece_data in pieces:
        existing = db.query(PieceDetachee).filter(PieceDetachee.reference == piece_data["reference"]).first()
        if not existing:
            # Récupérer le fournisseur
            fournisseur_nom = piece_data.pop("fournisseur_nom", None)
            fournisseur_id = None
            if fournisseur_nom:
                fournisseur = db.query(Fournisseur).filter(Fournisseur.nom == fournisseur_nom).first()
                if fournisseur:
                    fournisseur_id = fournisseur.id
            
            piece_data["fournisseur_id"] = fournisseur_id
            new_piece = PieceDetachee(**piece_data)
            db.add(new_piece)
    
    db.commit()
    
    # Initialiser la configuration atelier
    config = db.query(ConfigAtelier).first()
    if not config:
        config = ConfigAtelier(
            taux_horaire_mo_standard=65.0,
            taux_horaire_mo_complexe=85.0,
            taux_horaire_mo_expert=95.0,
            marge_pieces_standard=30.0,
            marge_pieces_consommable=50.0,
            marge_pieces_pneumatique=25.0,
            forfait_mo_minimum=25.0,
            tva_mo_taux=20.0,
            tva_pieces_taux=20.0,
            validite_devis_jours=30,
            accompte_pourcentage=30.0
        )
        db.add(config)
        db.commit()
    
    # Initialiser les forfaits MO
    forfaits = [
        {
            "code": "VID-STD",
            "nom": "Vidange standard",
            "description": "Vidange moteur + filtre à huile",
            "categorie": "vidange",
            "temps_base_minutes": 45,
            "taux_horaire_applique": "standard",
            "prix_forfait_mo_ht": 48.75,
            "prix_forfait_mo_ttc": 58.50,
            "inclut_pieces": 0,
            "type_vehicule": "tous",
            "cylindree_min": None,
            "cylindree_max": None
        },
        {
            "code": "REV-125",
            "nom": "Révision 125cc",
            "description": "Vidange, filtres, contrôles, réglages",
            "categorie": "revision",
            "temps_base_minutes": 90,
            "taux_horaire_applique": "standard",
            "prix_forfait_mo_ht": 97.50,
            "prix_forfait_mo_ttc": 117.00,
            "inclut_pieces": 0,
            "type_vehicule": "moto",
            "cylindree_min": 50,
            "cylindree_max": 125
        },
        {
            "code": "REV-500",
            "nom": "Révision 300-500cc",
            "description": "Vidange, filtres, bougies, contrôles complets",
            "categorie": "revision",
            "temps_base_minutes": 120,
            "taux_horaire_applique": "standard",
            "prix_forfait_mo_ht": 130.00,
            "prix_forfait_mo_ttc": 156.00,
            "inclut_pieces": 0,
            "type_vehicule": "moto",
            "cylindree_min": 300,
            "cylindree_max": 500
        },
        {
            "code": "REV-1000",
            "nom": "Révision +500cc",
            "description": "Révision complète moteur gros cube",
            "categorie": "revision",
            "temps_base_minutes": 150,
            "taux_horaire_applique": "complexe",
            "prix_forfait_mo_ht": 212.50,
            "prix_forfait_mo_ttc": 255.00,
            "inclut_pieces": 0,
            "type_vehicule": "moto",
            "cylindree_min": 500,
            "cylindree_max": None
        },
        {
            "code": "FRE-AV",
            "nom": "Révision freinage avant",
            "description": "Plaquettes, disque, purge circuit",
            "categorie": "freinage",
            "temps_base_minutes": 75,
            "taux_horaire_applique": "standard",
            "prix_forfait_mo_ht": 81.25,
            "prix_forfait_mo_ttc": 97.50,
            "inclut_pieces": 0,
            "type_vehicule": "tous",
            "cylindree_min": None,
            "cylindree_max": None
        },
        {
            "code": "FRE-AR",
            "nom": "Révision freinage arrière",
            "description": "Plaquettes/tambours, purge circuit",
            "categorie": "freinage",
            "temps_base_minutes": 60,
            "taux_horaire_applique": "standard",
            "prix_forfait_mo_ht": 65.00,
            "prix_forfait_mo_ttc": 78.00,
            "inclut_pieces": 0,
            "type_vehicule": "tous",
            "cylindree_min": None,
            "cylindree_max": None
        },
        {
            "code": "PNE-MONT",
            "nom": "Montage pneus (la paire)",
            "description": "Démontage, montage, équilibrage",
            "categorie": "pneumatique",
            "temps_base_minutes": 60,
            "taux_horaire_applique": "standard",
            "prix_forfait_mo_ht": 65.00,
            "prix_forfait_mo_ttc": 78.00,
            "inclut_pieces": 0,
            "type_vehicule": "tous",
            "cylindree_min": None,
            "cylindree_max": None
        },
        {
            "code": "KIT-CHAIN",
            "nom": "Remplacement kit chaîne",
            "description": "Chaîne, couronne, pignon, réglage",
            "categorie": "transmission",
            "temps_base_minutes": 90,
            "taux_horaire_applique": "standard",
            "prix_forfait_mo_ht": 97.50,
            "prix_forfait_mo_ttc": 117.00,
            "inclut_pieces": 0,
            "type_vehicule": "moto",
            "cylindree_min": None,
            "cylindree_max": None
        },
        {
            "code": "DIAG-ELEC",
            "nom": "Diagnostic électronique",
            "description": "Lecture codes défaut, analyse complète",
            "categorie": "diagnostic",
            "temps_base_minutes": 45,
            "taux_horaire_applique": "complexe",
            "prix_forfait_mo_ht": 63.75,
            "prix_forfait_mo_ttc": 76.50,
            "inclut_pieces": 0,
            "type_vehicule": "tous",
            "cylindree_min": None,
            "cylindree_max": None
        },
        {
            "code": "CARBU-NET",
            "nom": "Nettoyage carburateur",
            "description": "Démontage, nettoyage, réglage",
            "categorie": "moteur",
            "temps_base_minutes": 90,
            "taux_horaire_applique": "complexe",
            "prix_forfait_mo_ht": 127.50,
            "prix_forfait_mo_ttc": 153.00,
            "inclut_pieces": 0,
            "type_vehicule": "tous",
            "cylindree_min": None,
            "cylindree_max": None
        },
        {
            "code": "VALV-REG",
            "nom": "Réglage soupapes",
            "description": "Contrôle et réglage jeu aux soupapes",
            "categorie": "moteur",
            "temps_base_minutes": 120,
            "taux_horaire_applique": "expert",
            "prix_forfait_mo_ht": 190.00,
            "prix_forfait_mo_ttc": 228.00,
            "inclut_pieces": 0,
            "type_vehicule": "moto",
            "cylindree_min": None,
            "cylindree_max": None
        },
        {
            "code": "VID-SCOOT",
            "nom": "Vidange scooter",
            "description": "Vidange moteur + filtre",
            "categorie": "vidange",
            "temps_base_minutes": 30,
            "taux_horaire_applique": "standard",
            "prix_forfait_mo_ht": 32.50,
            "prix_forfait_mo_ttc": 39.00,
            "inclut_pieces": 0,
            "type_vehicule": "scooter",
            "cylindree_min": None,
            "cylindree_max": None
        }
    ]
    
    for forfait_data in forfaits:
        existing = db.query(ForfaitMO).filter(ForfaitMO.code == forfait_data["code"]).first()
        if not existing:
            new_forfait = ForfaitMO(**forfait_data)
            db.add(new_forfait)
    
    db.commit()


def init_prestations(db: Session):
    """Initialise les prestations du module tarifs"""
    
    prestations = [
        {
            "code": "VID-MOTO-STD",
            "nom": "Vidange moto standard",
            "description": "Vidange moteur et remplacement filtre à huile",
            "categorie": "entretien",
            "sous_categorie": "moteur",
            "prix_base_ht": 48.75,
            "prix_base_ttc": 58.50,
            "temps_estime_minutes": 45,
            "delai_intervention_jours": 1,
            "type_tarif": "forfait",
            "taux_horaire_applique": "standard",
            "type_vehicule": "moto",
            "cylindree_min": 50,
            "cylindree_max": 1000,
            "is_forfait": 0,
            "inclut_pieces": 0,
            "marge_pieces_pourcent": 30.0
        },
        {
            "code": "VID-SCOOT-STD",
            "nom": "Vidange scooter standard",
            "description": "Vidange moteur scooter",
            "categorie": "entretien",
            "sous_categorie": "moteur",
            "prix_base_ht": 32.50,
            "prix_base_ttc": 39.00,
            "temps_estime_minutes": 30,
            "delai_intervention_jours": 1,
            "type_tarif": "forfait",
            "taux_horaire_applique": "standard",
            "type_vehicule": "scooter",
            "cylindree_min": 50,
            "cylindree_max": 650,
            "is_forfait": 0,
            "inclut_pieces": 0,
            "marge_pieces_pourcent": 30.0
        },
        {
            "code": "REV-125-COMP",
            "nom": "Révision complète 125cc",
            "description": "Vidange, filtres, bougies, contrôle et réglages",
            "categorie": "entretien",
            "sous_categorie": "moteur",
            "prix_base_ht": 97.50,
            "prix_base_ttc": 117.00,
            "temps_estime_minutes": 90,
            "delai_intervention_jours": 2,
            "type_tarif": "forfait",
            "taux_horaire_applique": "standard",
            "type_vehicule": "moto",
            "cylindree_min": 50,
            "cylindree_max": 125,
            "is_forfait": 1,
            "inclut_pieces": 1,
            "description_pieces_incluses": "Filtre à huile, filtre à air, bougie",
            "cout_pieces_incluses_ht": 35.0,
            "marge_pieces_pourcent": 30.0
        },
        {
            "code": "REV-500-COMP",
            "nom": "Révision complète 300-500cc",
            "description": "Vidange, filtres, bougies, contrôles complets",
            "categorie": "entretien",
            "sous_categorie": "moteur",
            "prix_base_ht": 130.00,
            "prix_base_ttc": 156.00,
            "temps_estime_minutes": 120,
            "delai_intervention_jours": 2,
            "type_tarif": "forfait",
            "taux_horaire_applique": "standard",
            "type_vehicule": "moto",
            "cylindree_min": 300,
            "cylindree_max": 500,
            "is_forfait": 0,
            "inclut_pieces": 0,
            "marge_pieces_pourcent": 30.0
        },
        {
            "code": "REV-1000-COMP",
            "nom": "Révision complète +500cc",
            "description": "Révision complète moteur gros cube",
            "categorie": "entretien",
            "sous_categorie": "moteur",
            "prix_base_ht": 212.50,
            "prix_base_ttc": 255.00,
            "temps_estime_minutes": 150,
            "delai_intervention_jours": 3,
            "type_tarif": "forfait",
            "taux_horaire_applique": "complexe",
            "type_vehicule": "moto",
            "cylindree_min": 500,
            "cylindree_max": None,
            "is_forfait": 0,
            "inclut_pieces": 0,
            "marge_pieces_pourcent": 30.0
        },
        {
            "code": "FRE-AV-COMP",
            "nom": "Révision freinage avant",
            "description": "Remplacement plaquettes, disque, purge circuit",
            "categorie": "reparation",
            "sous_categorie": "freinage",
            "prix_base_ht": 81.25,
            "prix_base_ttc": 97.50,
            "temps_estime_minutes": 75,
            "delai_intervention_jours": 1,
            "type_tarif": "forfait",
            "taux_horaire_applique": "standard",
            "type_vehicule": "tous",
            "cylindree_min": None,
            "cylindree_max": None,
            "is_forfait": 0,
            "inclut_pieces": 0,
            "marge_pieces_pourcent": 30.0
        },
        {
            "code": "FRE-AR-COMP",
            "nom": "Révision freinage arrière",
            "description": "Remplacement plaquettes/tambours, purge circuit",
            "categorie": "reparation",
            "sous_categorie": "freinage",
            "prix_base_ht": 65.00,
            "prix_base_ttc": 78.00,
            "temps_estime_minutes": 60,
            "delai_intervention_jours": 1,
            "type_tarif": "forfait",
            "taux_horaire_applique": "standard",
            "type_vehicule": "tous",
            "cylindree_min": None,
            "cylindree_max": None,
            "is_forfait": 0,
            "inclut_pieces": 0,
            "marge_pieces_pourcent": 30.0
        },
        {
            "code": "PNE-MONT-PAIR",
            "nom": "Montage pneus (la paire)",
            "description": "Démontage, montage et équilibrage des pneus",
            "categorie": "reparation",
            "sous_categorie": "pneumatique",
            "prix_base_ht": 65.00,
            "prix_base_ttc": 78.00,
            "temps_estime_minutes": 60,
            "delai_intervention_jours": 1,
            "type_tarif": "forfait",
            "taux_horaire_applique": "standard",
            "type_vehicule": "tous",
            "cylindree_min": None,
            "cylindree_max": None,
            "is_forfait": 0,
            "inclut_pieces": 0,
            "marge_pieces_pourcent": 25.0
        },
        {
            "code": "KIT-CHAIN-COMP",
            "nom": "Remplacement kit chaîne complet",
            "description": "Chaîne, couronne, pignon et réglage",
            "categorie": "reparation",
            "sous_categorie": "transmission",
            "prix_base_ht": 97.50,
            "prix_base_ttc": 117.00,
            "temps_estime_minutes": 90,
            "delai_intervention_jours": 2,
            "type_tarif": "forfait",
            "taux_horaire_applique": "standard",
            "type_vehicule": "moto",
            "cylindree_min": None,
            "cylindree_max": None,
            "is_forfait": 0,
            "inclut_pieces": 0,
            "marge_pieces_pourcent": 30.0
        },
        {
            "code": "DIAG-ELEC-FULL",
            "nom": "Diagnostic électronique complet",
            "description": "Lecture codes défaut, analyse et rapport",
            "categorie": "diagnostic",
            "sous_categorie": "electricite",
            "prix_base_ht": 63.75,
            "prix_base_ttc": 76.50,
            "temps_estime_minutes": 45,
            "delai_intervention_jours": 1,
            "type_tarif": "forfait",
            "taux_horaire_applique": "complexe",
            "type_vehicule": "tous",
            "cylindree_min": None,
            "cylindree_max": None,
            "is_forfait": 0,
            "inclut_pieces": 0,
            "marge_pieces_pourcent": 30.0
        },
        {
            "code": "CARBU-NET-COMP",
            "nom": "Nettoyage carburateur complet",
            "description": "Démontage, nettoyage ultrasons, réglage",
            "categorie": "reparation",
            "sous_categorie": "moteur",
            "prix_base_ht": 127.50,
            "prix_base_ttc": 153.00,
            "temps_estime_minutes": 90,
            "delai_intervention_jours": 2,
            "type_tarif": "forfait",
            "taux_horaire_applique": "complexe",
            "type_vehicule": "tous",
            "cylindree_min": None,
            "cylindree_max": None,
            "is_forfait": 0,
            "inclut_pieces": 0,
            "marge_pieces_pourcent": 30.0
        },
        {
            "code": "VALV-REG-JEU",
            "nom": "Réglage jeu aux soupapes",
            "description": "Contrôle et réglage du jeu aux soupapes",
            "categorie": "reparation",
            "sous_categorie": "moteur",
            "prix_base_ht": 190.00,
            "prix_base_ttc": 228.00,
            "temps_estime_minutes": 120,
            "delai_intervention_jours": 3,
            "type_tarif": "forfait",
            "taux_horaire_applique": "expert",
            "type_vehicule": "moto",
            "cylindree_min": None,
            "cylindree_max": None,
            "is_forfait": 0,
            "inclut_pieces": 0,
            "marge_pieces_pourcent": 30.0
        },
        {
            "code": "SUSP-VERIF",
            "nom": "Vérification suspension",
            "description": "Contrôle fourche et amortisseur, réglages",
            "categorie": "entretien",
            "sous_categorie": "suspension",
            "prix_base_ht": 48.75,
            "prix_base_ttc": 58.50,
            "temps_estime_minutes": 45,
            "delai_intervention_jours": 1,
            "type_tarif": "forfait",
            "taux_horaire_applique": "standard",
            "type_vehicule": "tous",
            "cylindree_min": None,
            "cylindree_max": None,
            "is_forfait": 0,
            "inclut_pieces": 0,
            "marge_pieces_pourcent": 30.0
        },
        {
            "code": "BAT-REM",
            "nom": "Remplacement batterie",
            "description": "Remplacement batterie et réinitialisation",
            "categorie": "reparation",
            "sous_categorie": "electricite",
            "prix_base_ht": 32.50,
            "prix_base_ttc": 39.00,
            "temps_estime_minutes": 30,
            "delai_intervention_jours": 1,
            "type_tarif": "forfait",
            "taux_horaire_applique": "standard",
            "type_vehicule": "tous",
            "cylindree_min": None,
            "cylindree_max": None,
            "is_forfait": 0,
            "inclut_pieces": 0,
            "marge_pieces_pourcent": 30.0
        }
    ]
    
    for prestation_data in prestations:
        existing = db.query(Prestation).filter(Prestation.code == prestation_data["code"]).first()
        if not existing:
            new_prestation = Prestation(**prestation_data)
            db.add(new_prestation)

    db.commit()


def init_base_moto(db: Session):
    """Initialise la base de données des catégories et modèles de moto"""

    # Catégories de moto
    categories = [
        {"nom": "Roadster", "description": "Moto nue, polyvalente et maniable"},
        {"nom": "Sportive", "description": "Moto performante, position sportive"},
        {"nom": "Trail", "description": "Moto tout-terrain et route"},
        {"nom": "Scooter", "description": "Scooter urbain et GT"},
        {"nom": "Cruiser", "description": "Custom, américaine, position relax"},
        {"nom": "Cross/Enduro", "description": "Moto tout-terrain compétition"},
        {"nom": "Touring", "description": "Moto voyage, grand confort"},
        {"nom": "Supermotard", "description": "Moto cross déclinée route"}
    ]

    for cat_data in categories:
        existing = db.query(CategorieMoto).filter(CategorieMoto.nom == cat_data["nom"]).first()
        if not existing:
            new_cat = CategorieMoto(**cat_data)
            db.add(new_cat)

    db.commit()

    # Récupérer les IDs des catégories
    cat_roadster = db.query(CategorieMoto).filter(CategorieMoto.nom == "Roadster").first()
    cat_sportive = db.query(CategorieMoto).filter(CategorieMoto.nom == "Sportive").first()
    cat_trail = db.query(CategorieMoto).filter(CategorieMoto.nom == "Trail").first()
    cat_scooter = db.query(CategorieMoto).filter(CategorieMoto.nom == "Scooter").first()
    cat_cruiser = db.query(CategorieMoto).filter(CategorieMoto.nom == "Cruiser").first()
    cat_cross = db.query(CategorieMoto).filter(CategorieMoto.nom == "Cross/Enduro").first()
    cat_touring = db.query(CategorieMoto).filter(CategorieMoto.nom == "Touring").first()
    cat_supermotard = db.query(CategorieMoto).filter(CategorieMoto.nom == "Supermotard").first()

    # Modèles de moto par marque
    modeles = [
        # YAMAHA
        {"marque": "YAMAHA", "modele": "MT-07", "categorie_id": cat_roadster.id, "cylindree_min": 689, "cylindree_max": 689, "annee_debut": 2014, "annee_fin": None},
        {"marque": "YAMAHA", "modele": "MT-09", "categorie_id": cat_roadster.id, "cylindree_min": 847, "cylindree_max": 890, "annee_debut": 2013, "annee_fin": None},
        {"marque": "YAMAHA", "modele": "MT-10", "categorie_id": cat_roadster.id, "cylindree_min": 998, "cylindree_max": 998, "annee_debut": 2016, "annee_fin": None},
        {"marque": "YAMAHA", "modele": "R1", "categorie_id": cat_sportive.id, "cylindree_min": 998, "cylindree_max": 998, "annee_debut": 1998, "annee_fin": None},
        {"marque": "YAMAHA", "modele": "R6", "categorie_id": cat_sportive.id, "cylindree_min": 599, "cylindree_max": 599, "annee_debut": 1999, "annee_fin": None},
        {"marque": "YAMAHA", "modele": "R7", "categorie_id": cat_sportive.id, "cylindree_min": 689, "cylindree_max": 689, "annee_debut": 2021, "annee_fin": None},
        {"marque": "YAMAHA", "modele": "Ténéré 700", "categorie_id": cat_trail.id, "cylindree_min": 689, "cylindree_max": 689, "annee_debut": 2019, "annee_fin": None},
        {"marque": "YAMAHA", "modele": "Tracer 900", "categorie_id": cat_touring.id, "cylindree_min": 847, "cylindree_max": 890, "annee_debut": 2015, "annee_fin": None},
        {"marque": "YAMAHA", "modele": "TMAX", "categorie_id": cat_scooter.id, "cylindree_min": 530, "cylindree_max": 562, "annee_debut": 2001, "annee_fin": None},
        {"marque": "YAMAHA", "modele": "XSR700", "categorie_id": cat_roadster.id, "cylindree_min": 689, "cylindree_max": 689, "annee_debut": 2016, "annee_fin": None},
        {"marque": "YAMAHA", "modele": "XMAX 300", "categorie_id": cat_scooter.id, "cylindree_min": 292, "cylindree_max": 292, "annee_debut": 2017, "annee_fin": None},
        {"marque": "YAMAHA", "modele": "WR450F", "categorie_id": cat_cross.id, "cylindree_min": 450, "cylindree_max": 450, "annee_debut": 2003, "annee_fin": None},

        # HONDA
        {"marque": "HONDA", "modele": "CB650R", "categorie_id": cat_roadster.id, "cylindree_min": 649, "cylindree_max": 649, "annee_debut": 2019, "annee_fin": None},
        {"marque": "HONDA", "modele": "CB1000R", "categorie_id": cat_roadster.id, "cylindree_min": 998, "cylindree_max": 998, "annee_debut": 2008, "annee_fin": None},
        {"marque": "HONDA", "modele": "CBR650R", "categorie_id": cat_sportive.id, "cylindree_min": 649, "cylindree_max": 649, "annee_debut": 2019, "annee_fin": None},
        {"marque": "HONDA", "modele": "CBR1000RR-R", "categorie_id": cat_sportive.id, "cylindree_min": 1000, "cylindree_max": 1000, "annee_debut": 2020, "annee_fin": None},
        {"marque": "HONDA", "modele": "Africa Twin", "categorie_id": cat_trail.id, "cylindree_min": 998, "cylindree_max": 1084, "annee_debut": 2016, "annee_fin": None},
        {"marque": "HONDA", "modele": "NC750X", "categorie_id": cat_trail.id, "cylindree_min": 745, "cylindree_max": 745, "annee_debut": 2014, "annee_fin": None},
        {"marque": "HONDA", "modele": "Forza 350", "categorie_id": cat_scooter.id, "cylindree_min": 330, "cylindree_max": 330, "annee_debut": 2021, "annee_fin": None},
        {"marque": "HONDA", "modele": "PCX 125", "categorie_id": cat_scooter.id, "cylindree_min": 125, "cylindree_max": 125, "annee_debut": 2010, "annee_fin": None},
        {"marque": "HONDA", "modele": "Rebel 500", "categorie_id": cat_cruiser.id, "cylindree_min": 471, "cylindree_max": 471, "annee_debut": 2017, "annee_fin": None},
        {"marque": "HONDA", "modele": "Gold Wing", "categorie_id": cat_touring.id, "cylindree_min": 1833, "cylindree_max": 1833, "annee_debut": 1975, "annee_fin": None},
        {"marque": "HONDA", "modele": "CRF450R", "categorie_id": cat_cross.id, "cylindree_min": 450, "cylindree_max": 450, "annee_debut": 2002, "annee_fin": None},
        {"marque": "HONDA", "modele": "CRF300L", "categorie_id": cat_trail.id, "cylindree_min": 286, "cylindree_max": 286, "annee_debut": 2021, "annee_fin": None},

        # KAWASAKI
        {"marque": "KAWASAKI", "modele": "Z650", "categorie_id": cat_roadster.id, "cylindree_min": 649, "cylindree_max": 649, "annee_debut": 2017, "annee_fin": None},
        {"marque": "KAWASAKI", "modele": "Z900", "categorie_id": cat_roadster.id, "cylindree_min": 948, "cylindree_max": 948, "annee_debut": 2017, "annee_fin": None},
        {"marque": "KAWASAKI", "modele": "Z1000", "categorie_id": cat_roadster.id, "cylindree_min": 1043, "cylindree_max": 1043, "annee_debut": 2003, "annee_fin": None},
        {"marque": "KAWASAKI", "modele": "Ninja 650", "categorie_id": cat_sportive.id, "cylindree_min": 649, "cylindree_max": 649, "annee_debut": 2017, "annee_fin": None},
        {"marque": "KAWASAKI", "modele": "Ninja ZX-10R", "categorie_id": cat_sportive.id, "cylindree_min": 998, "cylindree_max": 998, "annee_debut": 2004, "annee_fin": None},
        {"marque": "KAWASAKI", "modele": "Ninja ZX-6R", "categorie_id": cat_sportive.id, "cylindree_min": 636, "cylindree_max": 636, "annee_debut": 1995, "annee_fin": None},
        {"marque": "KAWASAKI", "modele": "Versys 650", "categorie_id": cat_trail.id, "cylindree_min": 649, "cylindree_max": 649, "annee_debut": 2007, "annee_fin": None},
        {"marque": "KAWASAKI", "modele": "Versys 1000", "categorie_id": cat_touring.id, "cylindree_min": 1043, "cylindree_max": 1043, "annee_debut": 2012, "annee_fin": None},
        {"marque": "KAWASAKI", "modele": "KLR 650", "categorie_id": cat_trail.id, "cylindree_min": 652, "cylindree_max": 652, "annee_debut": 1987, "annee_fin": None},
        {"marque": "KAWASAKI", "modele": "KX450F", "categorie_id": cat_cross.id, "cylindree_min": 449, "cylindree_max": 449, "annee_debut": 2006, "annee_fin": None},
        {"marque": "KAWASAKI", "modele": "Vulcan S", "categorie_id": cat_cruiser.id, "cylindree_min": 649, "cylindree_max": 649, "annee_debut": 2015, "annee_fin": None},
        {"marque": "KAWASAKI", "modele": "Eliminator 450", "categorie_id": cat_cruiser.id, "cylindree_min": 451, "cylindree_max": 451, "annee_debut": 2024, "annee_fin": None},

        # SUZUKI
        {"marque": "SUZUKI", "modele": "SV650", "categorie_id": cat_roadster.id, "cylindree_min": 645, "cylindree_max": 645, "annee_debut": 1999, "annee_fin": None},
        {"marque": "SUZUKI", "modele": "GSX-S750", "categorie_id": cat_roadster.id, "cylindree_min": 749, "cylindree_max": 749, "annee_debut": 2015, "annee_fin": None},
        {"marque": "SUZUKI", "modele": "GSX-S1000", "categorie_id": cat_roadster.id, "cylindree_min": 999, "cylindree_max": 999, "annee_debut": 2015, "annee_fin": None},
        {"marque": "SUZUKI", "modele": "GSX-R750", "categorie_id": cat_sportive.id, "cylindree_min": 750, "cylindree_max": 750, "annee_debut": 1985, "annee_fin": None},
        {"marque": "SUZUKI", "modele": "GSX-R1000", "categorie_id": cat_sportive.id, "cylindree_min": 988, "cylindree_max": 1000, "annee_debut": 2001, "annee_fin": None},
        {"marque": "SUZUKI", "modele": "V-Strom 650", "categorie_id": cat_trail.id, "cylindree_min": 645, "cylindree_max": 645, "annee_debut": 2004, "annee_fin": None},
        {"marque": "SUZUKI", "modele": "V-Strom 1050", "categorie_id": cat_touring.id, "cylindree_min": 1037, "cylindree_max": 1037, "annee_debut": 2020, "annee_fin": None},
        {"marque": "SUZUKI", "modele": "Burgman 400", "categorie_id": cat_scooter.id, "cylindree_min": 400, "cylindree_max": 400, "annee_debut": 1999, "annee_fin": None},
        {"marque": "SUZUKI", "modele": "RM-Z450", "categorie_id": cat_cross.id, "cylindree_min": 449, "cylindree_max": 449, "annee_debut": 2005, "annee_fin": None},
        {"marque": "SUZUKI", "modele": "DR-Z400SM", "categorie_id": cat_supermotard.id, "cylindree_min": 398, "cylindree_max": 398, "annee_debut": 2005, "annee_fin": None},
        {"marque": "SUZUKI", "modele": "Boulevard M109R", "categorie_id": cat_cruiser.id, "cylindree_min": 1783, "cylindree_max": 1783, "annee_debut": 2006, "annee_fin": None},

        # BMW
        {"marque": "BMW", "modele": "F900R", "categorie_id": cat_roadster.id, "cylindree_min": 895, "cylindree_max": 895, "annee_debut": 2020, "annee_fin": None},
        {"marque": "BMW", "modele": "S1000R", "categorie_id": cat_roadster.id, "cylindree_min": 999, "cylindree_max": 999, "annee_debut": 2014, "annee_fin": None},
        {"marque": "BMW", "modele": "S1000RR", "categorie_id": cat_sportive.id, "cylindree_min": 999, "cylindree_max": 999, "annee_debut": 2009, "annee_fin": None},
        {"marque": "BMW", "modele": "M1000RR", "categorie_id": cat_sportive.id, "cylindree_min": 1001, "cylindree_max": 1001, "annee_debut": 2021, "annee_fin": None},
        {"marque": "BMW", "modele": "F850GS", "categorie_id": cat_trail.id, "cylindree_min": 853, "cylindree_max": 853, "annee_debut": 2018, "annee_fin": None},
        {"marque": "BMW", "modele": "R1250GS", "categorie_id": cat_trail.id, "cylindree_min": 1254, "cylindree_max": 1254, "annee_debut": 2019, "annee_fin": None},
        {"marque": "BMW", "modele": "R1250RT", "categorie_id": cat_touring.id, "cylindree_min": 1254, "cylindree_max": 1254, "annee_debut": 2019, "annee_fin": None},
        {"marque": "BMW", "modele": "C400X", "categorie_id": cat_scooter.id, "cylindree_min": 350, "cylindree_max": 350, "annee_debut": 2018, "annee_fin": None},
        {"marque": "BMW", "modele": "R18", "categorie_id": cat_cruiser.id, "cylindree_min": 1802, "cylindree_max": 1802, "annee_debut": 2020, "annee_fin": None},
        {"marque": "BMW", "modele": "G310R", "categorie_id": cat_roadster.id, "cylindree_min": 313, "cylindree_max": 313, "annee_debut": 2016, "annee_fin": None},

        # DUCATI
        {"marque": "DUCATI", "modele": "Monster 821", "categorie_id": cat_roadster.id, "cylindree_min": 821, "cylindree_max": 821, "annee_debut": 2014, "annee_fin": 2020},
        {"marque": "DUCATI", "modele": "Monster 937", "categorie_id": cat_roadster.id, "cylindree_min": 937, "cylindree_max": 937, "annee_debut": 2021, "annee_fin": None},
        {"marque": "DUCATI", "modele": "Streetfighter V4", "categorie_id": cat_roadster.id, "cylindree_min": 1103, "cylindree_max": 1103, "annee_debut": 2020, "annee_fin": None},
        {"marque": "DUCATI", "modele": "Panigale V4", "categorie_id": cat_sportive.id, "cylindree_min": 1103, "cylindree_max": 1103, "annee_debut": 2018, "annee_fin": None},
        {"marque": "DUCATI", "modele": "Panigale V2", "categorie_id": cat_sportive.id, "cylindree_min": 955, "cylindree_max": 955, "annee_debut": 2020, "annee_fin": None},
        {"marque": "DUCATI", "modele": "Multistrada V4", "categorie_id": cat_trail.id, "cylindree_min": 1158, "cylindree_max": 1158, "annee_debut": 2021, "annee_fin": None},
        {"marque": "DUCATI", "modele": "Scrambler 800", "categorie_id": cat_roadster.id, "cylindree_min": 803, "cylindree_max": 803, "annee_debut": 2015, "annee_fin": None},
        {"marque": "DUCATI", "modele": "Diavel 1260", "categorie_id": cat_cruiser.id, "cylindree_min": 1262, "cylindree_max": 1262, "annee_debut": 2019, "annee_fin": None},
        {"marque": "DUCATI", "modele": "Hypermotard 950", "categorie_id": cat_supermotard.id, "cylindree_min": 937, "cylindree_max": 937, "annee_debut": 2019, "annee_fin": None},
        {"marque": "DUCATI", "modele": "SuperSport 950", "categorie_id": cat_sportive.id, "cylindree_min": 937, "cylindree_max": 937, "annee_debut": 2017, "annee_fin": None},

        # KTM
        {"marque": "KTM", "modele": "Duke 390", "categorie_id": cat_roadster.id, "cylindree_min": 373, "cylindree_max": 373, "annee_debut": 2013, "annee_fin": None},
        {"marque": "KTM", "modele": "Duke 790", "categorie_id": cat_roadster.id, "cylindree_min": 799, "cylindree_max": 790, "annee_debut": 2018, "annee_fin": None},
        {"marque": "KTM", "modele": "Duke 890", "categorie_id": cat_roadster.id, "cylindree_min": 889, "cylindree_max": 889, "annee_debut": 2020, "annee_fin": None},
        {"marque": "KTM", "modele": "Duke 1290 Super", "categorie_id": cat_roadster.id, "cylindree_min": 1301, "cylindree_max": 1301, "annee_debut": 2014, "annee_fin": None},
        {"marque": "KTM", "modele": "RC 390", "categorie_id": cat_sportive.id, "cylindree_min": 373, "cylindree_max": 373, "annee_debut": 2014, "annee_fin": None},
        {"marque": "KTM", "modele": "390 Adventure", "categorie_id": cat_trail.id, "cylindree_min": 373, "cylindree_max": 373, "annee_debut": 2020, "annee_fin": None},
        {"marque": "KTM", "modele": "890 Adventure", "categorie_id": cat_trail.id, "cylindree_min": 889, "cylindree_max": 889, "annee_debut": 2021, "annee_fin": None},
        {"marque": "KTM", "modele": "1290 Super Adventure", "categorie_id": cat_trail.id, "cylindree_min": 1301, "cylindree_max": 1301, "annee_debut": 2015, "annee_fin": None},
        {"marque": "KTM", "modele": "450 SX-F", "categorie_id": cat_cross.id, "cylindree_min": 450, "cylindree_max": 450, "annee_debut": 2007, "annee_fin": None},
        {"marque": "KTM", "modele": "450 SMR", "categorie_id": cat_supermotard.id, "cylindree_min": 450, "cylindree_max": 450, "annee_debut": 2005, "annee_fin": None},
        {"marque": "KTM", "modele": "690 SMC R", "categorie_id": cat_supermotard.id, "cylindree_min": 693, "cylindree_max": 693, "annee_debut": 2008, "annee_fin": None},

        # TRIUMPH
        {"marque": "TRIUMPH", "modele": "Street Triple 765", "categorie_id": cat_roadster.id, "cylindree_min": 765, "cylindree_max": 765, "annee_debut": 2017, "annee_fin": None},
        {"marque": "TRIUMPH", "modele": "Speed Triple 1200", "categorie_id": cat_roadster.id, "cylindree_min": 1160, "cylindree_max": 1160, "annee_debut": 2021, "annee_fin": None},
        {"marque": "TRIUMPH", "modele": "Trident 660", "categorie_id": cat_roadster.id, "cylindree_min": 660, "cylindree_max": 660, "annee_debut": 2021, "annee_fin": None},
        {"marque": "TRIUMPH", "modele": "Daytona 660", "categorie_id": cat_sportive.id, "cylindree_min": 660, "cylindree_max": 660, "annee_debut": 2024, "annee_fin": None},
        {"marque": "TRIUMPH", "modele": "Tiger 900", "categorie_id": cat_trail.id, "cylindree_min": 888, "cylindree_max": 888, "annee_debut": 2020, "annee_fin": None},
        {"marque": "TRIUMPH", "modele": "Tiger 1200", "categorie_id": cat_trail.id, "cylindree_min": 1160, "cylindree_max": 1160, "annee_debut": 2022, "annee_fin": None},
        {"marque": "TRIUMPH", "modele": "Rocket 3", "categorie_id": cat_cruiser.id, "cylindree_min": 2458, "cylindree_max": 2458, "annee_debut": 2020, "annee_fin": None},
        {"marque": "TRIUMPH", "modele": "Bonneville T120", "categorie_id": cat_roadster.id, "cylindree_min": 1200, "cylindree_max": 1200, "annee_debut": 2016, "annee_fin": None},
        {"marque": "TRIUMPH", "modele": "Thruxton RS", "categorie_id": cat_roadster.id, "cylindree_min": 1200, "cylindree_max": 1200, "annee_debut": 2020, "annee_fin": None},
        {"marque": "TRIUMPH", "modele": "Scrambler 1200", "categorie_id": cat_trail.id, "cylindree_min": 1200, "cylindree_max": 1200, "annee_debut": 2019, "annee_fin": None},
    ]

    for modele_data in modeles:
        existing = db.query(ModeleMoto).filter(
            ModeleMoto.marque == modele_data["marque"],
            ModeleMoto.modele == modele_data["modele"]
        ).first()
        if not existing:
            new_modele = ModeleMoto(**modele_data)
            db.add(new_modele)

    db.commit()
