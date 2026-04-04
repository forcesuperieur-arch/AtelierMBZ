from sqlalchemy import create_engine, Column, Integer, String, DateTime, Date, Time, Float, Numeric, Text, ForeignKey, Boolean, event
from sqlalchemy.orm import sessionmaker, relationship, declarative_base
from datetime import datetime

Base = declarative_base()


def _apply_default_atelier_id(mapper, connection, target):
    """Compat multi-atelier: tout enregistrement legacy sans atelier reçoit l'atelier 1."""
    if hasattr(target, "atelier_id"):
        current = getattr(target, "atelier_id", None)
        if current in (None, "", 0):
            try:
                setattr(target, "atelier_id", 1)
            except Exception:
                pass


event.listen(Base, "before_insert", _apply_default_atelier_id, propagate=True)

class Atelier(Base):
    __tablename__ = "ateliers"

    id = Column(Integer, primary_key=True, index=True)
    nom = Column(String(200), nullable=False)
    slug = Column(String(100), unique=True, nullable=False, index=True)
    adresse = Column(Text)
    cp = Column(String(20))
    ville = Column(String(100))
    telephone = Column(String(20))
    email = Column(String(200))
    siret = Column(String(20))
    tva_intracom = Column(String(30))
    logo_url = Column(String(500))
    plan = Column(String(50), default="starter")
    actif = Column(Boolean, default=True)
    config_json = Column(Text)
    created_at = Column(DateTime, default=datetime.now)

class Client(Base):
    __tablename__ = "clients"
    
    id = Column(Integer, primary_key=True, index=True)
    atelier_id = Column(Integer, ForeignKey("ateliers.id"), nullable=True, index=True)
    nom = Column(String(100), nullable=False)
    prenom = Column(String(100), nullable=False)
    telephone = Column(String(20), nullable=False)
    email = Column(String(200))
    adresse = Column(Text)
    notes = Column(Text)  # Notes internes sur le client
    created_at = Column(DateTime, default=datetime.now)
    
    rendez_vous = relationship("RendezVous", back_populates="client", order_by="desc(RendezVous.date_rdv)")
    vehicules = relationship("Vehicule", back_populates="client", order_by="Vehicule.id")

class Vehicule(Base):
    __tablename__ = "vehicules"

    id = Column(Integer, primary_key=True, index=True)
    atelier_id = Column(Integer, ForeignKey("ateliers.id"), nullable=True, index=True)
    plaque = Column(String(20), nullable=False, index=True)
    marque = Column(String(100))
    modele = Column(String(100))
    annee = Column(Integer)
    cylindree = Column(String(50))
    type_moto = Column(String(50))

    # Lien direct client
    client_id = Column(Integer, ForeignKey("clients.id"), nullable=True)

    # Références vers la base moto (optionnel mais recommandé)
    categorie_id = Column(Integer, ForeignKey("categorie_motos.id"), nullable=True)
    modele_id = Column(Integer, ForeignKey("modele_motos.id"), nullable=True)

    # Relations
    client = relationship("Client", back_populates="vehicules")
    categorie = relationship("CategorieMoto")
    modele_ref = relationship("ModeleMoto")
    rendez_vous = relationship("RendezVous", back_populates="vehicule")

class RendezVous(Base):
    __tablename__ = "rendez_vous"
    
    id = Column(Integer, primary_key=True, index=True)
    atelier_id = Column(Integer, ForeignKey("ateliers.id"), nullable=True, index=True)
    client_id = Column(Integer, ForeignKey("clients.id"))
    vehicule_id = Column(Integer, ForeignKey("vehicules.id"))
    
    date_rdv = Column(Date, nullable=False)
    heure_rdv = Column(Time, nullable=False)
    type_intervention = Column(String(200), nullable=False)
    commentaire = Column(Text)
    
    # Prix et temps
    prix_estime = Column(Numeric(10, 2))
    prix_final = Column(Numeric(10, 2))  # Prix réel facturé
    temps_estime = Column(Integer)  # en minutes
    temps_final = Column(Integer)  # Temps réel passé
    
    # Suivi temps de travail (interne atelier)
    heure_debut_travail = Column(DateTime)  # Quand le mécano commence
    heure_fin_travail = Column(DateTime)    # Quand le mécano termine
    temps_effectif_minutes = Column(Integer)  # Calculé automatiquement
    
    # OR - Info réception
    kilometrage = Column(Integer)
    etat_vehicule = Column(Text)  # JSON avec points cochés
    photos_etat = Column(Text)  # URLs photos
    pont_id = Column(Integer, ForeignKey("ponts.id"), nullable=True, index=True)
    mecanicien_id = Column(Integer, ForeignKey("mecaniciens.id"), nullable=True, index=True)
    
    statut = Column(String(50), default="en_attente")  # en_attente, confirme, en_cours, termine, annule, facture, paye
    
    created_at = Column(DateTime, default=datetime.now)
    updated_at = Column(DateTime, default=datetime.now, onupdate=datetime.now)
    
    client = relationship("Client", back_populates="rendez_vous")
    vehicule = relationship("Vehicule", back_populates="rendez_vous")
    rapport_technicien = relationship("RapportTechnicien", back_populates="rendez_vous", uselist=False)
    demandes_travaux_supp = relationship("DemandeTravauxSupp", back_populates="rendez_vous", cascade="all, delete-orphan")
    ordres_reparation = relationship("OrdreReparation", back_populates="rendez_vous", cascade="all, delete-orphan")

class User(Base):
    __tablename__ = "users"
    
    id = Column(Integer, primary_key=True, index=True)
    atelier_id = Column(Integer, ForeignKey("ateliers.id"), nullable=True, index=True)
    username = Column(String(100), unique=True, nullable=False)
    email = Column(String(200), unique=True, nullable=False)
    hashed_password = Column(String(200), nullable=False)
    role = Column(String(50), default="receptionnaire")  # admin, receptionnaire, mecanicien
    is_active = Column(Integer, default=1)
    created_at = Column(DateTime, default=datetime.now)


class UserAtelierRole(Base):
    __tablename__ = "user_atelier_roles"

    user_id = Column(Integer, ForeignKey("users.id"), primary_key=True)
    atelier_id = Column(Integer, ForeignKey("ateliers.id"), primary_key=True)
    role = Column(String(50), nullable=False, default="receptionnaire")
    created_at = Column(DateTime, default=datetime.now)


class RolePermission(Base):
    __tablename__ = "role_permissions"

    role = Column(String(50), primary_key=True, index=True)
    label = Column(String(120), nullable=False)
    description = Column(Text, nullable=True)
    sections_json = Column(Text, nullable=False, default="[]")
    permissions_json = Column(Text, nullable=False, default="[]")
    is_system = Column(Integer, default=0)  # 1 = role coeur, 0 = role custom
    created_at = Column(DateTime, default=datetime.now)
    updated_at = Column(DateTime, default=datetime.now, onupdate=datetime.now)

class InterventionType(Base):  # DEPRECATED - utiliser Prestation + GrilleTarifaire
    __tablename__ = "intervention_types"
    
    id = Column(Integer, primary_key=True, index=True)
    nom = Column(String(200), nullable=False)
    description = Column(Text)
    prix_base = Column(Numeric(10, 2), nullable=False)
    temps_estime = Column(Integer, nullable=False)  # en minutes
    is_active = Column(Integer, default=1)

class Pont(Base):
    """Poste de travail / pont élévateur"""
    __tablename__ = "ponts"

    id = Column(Integer, primary_key=True, index=True)
    atelier_id = Column(Integer, ForeignKey("ateliers.id"), nullable=True, index=True)
    nom = Column(String(100), nullable=False)  # ex: "Pont 1", "Pont 2"
    type_pont = Column(String(50), default="moto")  # moto, scooter, quad
    capacite_kg = Column(Integer, default=500)  # capacité max
    is_active = Column(Integer, default=1)
    ordre_affichage = Column(Integer, default=0)  # pour l'ordre d'affichage
    mecanicien_id = Column(Integer, ForeignKey("mecaniciens.id"), nullable=True, index=True)  # mécanicien assigné à ce pont

    rendez_vous = relationship("RendezVous", back_populates="pont")
    mecanicien = relationship("Mecanicien")

class Mecanicien(Base):
    """Mécanicien de l'atelier"""
    __tablename__ = "mecaniciens"
    
    id = Column(Integer, primary_key=True, index=True)
    atelier_id = Column(Integer, ForeignKey("ateliers.id"), nullable=True, index=True)
    nom = Column(String(100), nullable=False)
    prenom = Column(String(100), nullable=False)
    specialites = Column(Text)  # JSON: ["moteur", "electricite", "carrosserie"]
    couleur = Column(String(7), default="#3b82f6")  # couleur pour l'affichage calendrier
    is_active = Column(Integer, default=1)
    user_id = Column(Integer, ForeignKey("users.id"), nullable=True, index=True)
    
    rendez_vous = relationship("RendezVous", back_populates="mecanicien")

RendezVous.pont = relationship("Pont", back_populates="rendez_vous")
RendezVous.mecanicien = relationship("Mecanicien", back_populates="rendez_vous")


class Absence(Base):
    """Gestion des absences des mécaniciens"""
    __tablename__ = "absences"
    
    id = Column(Integer, primary_key=True, index=True)
    atelier_id = Column(Integer, ForeignKey("ateliers.id"), nullable=True, index=True)
    mecanicien_id = Column(Integer, ForeignKey("mecaniciens.id"), nullable=False)
    date_debut = Column(Date, nullable=False)
    date_fin = Column(Date, nullable=False)
    motif = Column(String(50), nullable=False)  # conge, maladie, formation, autre
    notes = Column(Text)
    created_at = Column(DateTime, default=datetime.now)
    
    mecanicien = relationship("Mecanicien")


# ========== GESTION DES PIÈCES DÉTACHÉES ==========

class Fournisseur(Base):
    """Fournisseur de pièces détachées"""
    __tablename__ = "fournisseurs"
    
    id = Column(Integer, primary_key=True, index=True)
    atelier_id = Column(Integer, ForeignKey("ateliers.id"), nullable=True, index=True)
    nom = Column(String(200), nullable=False)
    contact = Column(String(200))
    telephone = Column(String(20))
    email = Column(String(200))
    adresse = Column(Text)
    siret = Column(String(20))
    delai_livraison_jours = Column(Integer, default=3)  # Délai moyen de livraison
    notes = Column(Text)
    is_active = Column(Integer, default=1)
    created_at = Column(DateTime, default=datetime.now)
    
    commandes = relationship("CommandeFournisseur", back_populates="fournisseur")


class PieceDetachee(Base):
    """Pièce détachée en stock"""
    __tablename__ = "pieces_detachees"
    
    id = Column(Integer, primary_key=True, index=True)
    atelier_id = Column(Integer, ForeignKey("ateliers.id"), nullable=True, index=True)
    reference = Column(String(100), unique=True, nullable=False, index=True)  # Référence interne
    reference_fournisseur = Column(String(100))  # Référence chez le fournisseur
    nom = Column(String(200), nullable=False)
    description = Column(Text)
    categorie = Column(String(100))  # moteur, freinage, électricité, etc.
    
    # Stock
    quantite_stock = Column(Integer, default=0)
    quantite_minimale = Column(Integer, default=5)  # Seuil d'alerte stock bas
    quantite_maximale = Column(Integer, default=50)  # Stock max souhaité
    emplacement = Column(String(100))  # Localisation en magasin (ex: "Étagère A3")
    
    # Prix
    prix_achat_ht = Column(Numeric(10, 2), default=0.0)  # Prix d'achat HT
    prix_vente_ht = Column(Numeric(10, 2), default=0.0)  # Prix de vente HT
    tva_taux = Column(Float, default=20.0)  # Taux de TVA (%)
    
    # Fournisseur par défaut
    fournisseur_id = Column(Integer, ForeignKey("fournisseurs.id"), nullable=True)
    fournisseur = relationship("Fournisseur")
    
    is_active = Column(Integer, default=1)
    created_at = Column(DateTime, default=datetime.now)
    updated_at = Column(DateTime, default=datetime.now, onupdate=datetime.now)
    
    # Relations
    lignes_commande = relationship("LigneCommandeFournisseur", back_populates="piece")
    utilisations = relationship("PieceUtilisee", back_populates="piece")
    
    @property
    def stock_bas(self):
        """Retourne True si le stock est sous le seuil minimal"""
        return self.quantite_stock <= self.quantite_minimale
    
    @property
    def prix_vente_ttc(self):
        """Prix de vente TTC"""
        return self.prix_vente_ht * (1 + self.tva_taux / 100)


class CommandeFournisseur(Base):
    """Commande passée à un fournisseur"""
    __tablename__ = "commandes_fournisseur"
    
    id = Column(Integer, primary_key=True, index=True)
    atelier_id = Column(Integer, ForeignKey("ateliers.id"), nullable=True, index=True)
    numero_commande = Column(String(50), unique=True, nullable=False)
    fournisseur_id = Column(Integer, ForeignKey("fournisseurs.id"), nullable=False)
    
    # Statut: en_attente, validee, expediee, receptionnee, annulee
    statut = Column(String(50), default="en_attente")
    
    # Dates
    date_commande = Column(DateTime, default=datetime.now)
    date_prevue_livraison = Column(DateTime)
    date_reception = Column(DateTime)
    
    # Totaux
    total_ht = Column(Numeric(10, 2), default=0.0)
    total_ttc = Column(Numeric(10, 2), default=0.0)
    
    # Notes
    notes = Column(Text)
    
    # Relations
    fournisseur = relationship("Fournisseur", back_populates="commandes")
    lignes = relationship("LigneCommandeFournisseur", back_populates="commande", cascade="all, delete-orphan")


class LigneCommandeFournisseur(Base):
    """Ligne d'une commande fournisseur"""
    __tablename__ = "lignes_commande_fournisseur"
    
    id = Column(Integer, primary_key=True, index=True)
    atelier_id = Column(Integer, ForeignKey("ateliers.id"), nullable=True, index=True)
    commande_id = Column(Integer, ForeignKey("commandes_fournisseur.id"), nullable=False)
    piece_id = Column(Integer, ForeignKey("pieces_detachees.id"), nullable=False)
    
    quantite_demandee = Column(Integer, nullable=False)
    quantite_recue = Column(Integer, default=0)
    prix_unitaire_ht = Column(Numeric(10, 2), nullable=False)
    
    # Relations
    commande = relationship("CommandeFournisseur", back_populates="lignes")
    piece = relationship("PieceDetachee", back_populates="lignes_commande")


class PieceUtilisee(Base):
    """Pièces utilisées pour une intervention (lien RDV - Pièces)"""
    __tablename__ = "pieces_utilisees"
    
    id = Column(Integer, primary_key=True, index=True)
    rendez_vous_id = Column(Integer, ForeignKey("rendez_vous.id"), nullable=False)
    piece_id = Column(Integer, ForeignKey("pieces_detachees.id"), nullable=False)
    
    quantite = Column(Integer, nullable=False)
    prix_vente_unitaire = Column(Numeric(10, 2))  # Prix facturé au client (peut différer du prix catalogue)
    
    created_at = Column(DateTime, default=datetime.now)
    
    # Relations
    rendez_vous = relationship("RendezVous", back_populates="pieces_utilisees")
    piece = relationship("PieceDetachee", back_populates="utilisations")


# Ajout de la relation dans RendezVous
RendezVous.pieces_utilisees = relationship("PieceUtilisee", back_populates="rendez_vous", cascade="all, delete-orphan")

# ========== GESTION DES FORFAITS MAIN D'ŒUVRE ==========

class ConfigAtelier(Base):
    """Configuration globale de l'atelier (tarifs, marges)"""
    __tablename__ = "config_atelier"
    
    id = Column(Integer, primary_key=True, index=True)
    atelier_id = Column(Integer, ForeignKey("ateliers.id"), nullable=True, index=True)
    
    # Tarifs horaires main d'œuvre (€/heure)
    taux_horaire_mo_standard = Column(Numeric(10, 2), default=65.0)
    taux_horaire_mo_complexe = Column(Numeric(10, 2), default=85.0)  # Électricité, injection...
    taux_horaire_mo_expert = Column(Numeric(10, 2), default=95.0)    # Moteur, haute perf...
    
    # Marges sur pièces (%)
    marge_pieces_standard = Column(Float, default=30.0)     # 30% de marge
    marge_pieces_consommable = Column(Float, default=50.0)  # Huiles, filtres...
    marge_pieces_pneumatique = Column(Float, default=25.0)  # Pneus (marge plus faible)
    
    # Forfait main d'œuvre minimum (€)
    forfait_mo_minimum = Column(Numeric(10, 2), default=25.0)
    
    # TVA
    tva_mo_taux = Column(Float, default=20.0)   # TVA sur main d'œuvre
    tva_pieces_taux = Column(Float, default=20.0)  # TVA sur pièces
    
    # Paramètres devis
    validite_devis_jours = Column(Integer, default=30)
    accompte_pourcentage = Column(Float, default=30.0)  # % d'acompte demandé
    
    updated_at = Column(DateTime, default=datetime.now, onupdate=datetime.now)


class ForfaitMO(Base):
    """Forfait main d'œuvre configurable (révision, vidange, etc.)"""
    __tablename__ = "forfaits_mo"
    
    id = Column(Integer, primary_key=True, index=True)
    atelier_id = Column(Integer, ForeignKey("ateliers.id"), nullable=True, index=True)
    code = Column(String(50), unique=True, nullable=False, index=True)  # ex: REV-125, VID-SCOOTER
    nom = Column(String(200), nullable=False)  # ex: "Révision 125cc", "Vidange scooter"
    description = Column(Text)
    
    # Catégorie
    categorie = Column(String(100))  # revision, vidange, pneumatique, freinage, etc.
    
    # Temps et tarification
    temps_base_minutes = Column(Integer, nullable=False)  # Temps standard en minutes
    taux_horaire_applique = Column(String(50), default="standard")  # standard, complexe, expert
    
    # Prix calculé (peut être modifié manuellement)
    prix_forfait_mo_ht = Column(Numeric(10, 2), nullable=False)  # Prix HT du forfait MO
    prix_forfait_mo_ttc = Column(Numeric(10, 2), nullable=False)  # Prix TTC
    
    # Forfait peut inclure des pièces (kit révision, etc.)
    inclut_pieces = Column(Integer, default=0)  # 0 = MO seule, 1 = MO + pièces
    description_pieces_incluses = Column(Text)  # Description des pièces incluses
    prix_pieces_incluses_ht = Column(Numeric(10, 2), default=0.0)
    
    # Applicabilité
    type_vehicule = Column(String(50), default="tous")  # tous, moto, scooter, quad
    cylindree_min = Column(Integer, nullable=True)  # cc minimum
    cylindree_max = Column(Integer, nullable=True)  # cc maximum
    
    # Options
    is_active = Column(Integer, default=1)
    is_promo = Column(Integer, default=0)  # En promotion ?
    prix_promo_mo_ttc = Column(Numeric(10, 2), nullable=True)  # Prix promo si applicable
    
    created_at = Column(DateTime, default=datetime.now)
    updated_at = Column(DateTime, default=datetime.now, onupdate=datetime.now)
    
    # Relations
    lignes_devis = relationship("LigneDevis", back_populates="forfait_mo")


class Devis(Base):
    """Devis pour un client"""
    __tablename__ = "devis"
    
    id = Column(Integer, primary_key=True, index=True)
    atelier_id = Column(Integer, ForeignKey("ateliers.id"), nullable=True, index=True)
    numero_devis = Column(String(50), unique=True, nullable=False, index=True)  # DEV-2024-0001
    
    # Client
    client_id = Column(Integer, ForeignKey("clients.id"), nullable=False)
    client = relationship("Client")
    
    # Véhicule
    vehicule_id = Column(Integer, ForeignKey("vehicules.id"), nullable=True)
    vehicule = relationship("Vehicule")
    
    # Informations devis
    date_creation = Column(DateTime, default=datetime.now)
    date_validite = Column(Date, nullable=False)
    statut = Column(String(50), default="brouillon")  # brouillon, envoye, accepte, refuse, expire
    
    # Kilométrage estimé
    kilometrage = Column(Integer, nullable=True)
    
    # Totaux
    total_mo_ht = Column(Numeric(10, 2), default=0.0)      # Total main d'œuvre HT
    total_pieces_ht = Column(Numeric(10, 2), default=0.0)  # Total pièces HT
    total_ht = Column(Numeric(10, 2), default=0.0)         # Total HT
    total_ttc = Column(Numeric(10, 2), default=0.0)        # Total TTC
    
    # Remise éventuelle
    remise_pourcentage = Column(Numeric(10, 2), default=0.0)
    remise_montant = Column(Numeric(10, 2), default=0.0)
    
    # Acompte
    acompte_demande = Column(Numeric(10, 2), default=0.0)
    
    # Notes
    notes_client = Column(Text)  # Visible sur le devis
    notes_internes = Column(Text)  # Interne seulement
    
    # Lien vers RDV si converti
    rendez_vous_id = Column(Integer, ForeignKey("rendez_vous.id"), nullable=True)
    
    created_at = Column(DateTime, default=datetime.now)
    updated_at = Column(DateTime, default=datetime.now, onupdate=datetime.now)
    
    # Relations
    lignes = relationship("LigneDevis", back_populates="devis", cascade="all, delete-orphan")


class LigneDevis(Base):
    """Ligne d'un devis (forfait MO ou pièce)"""
    __tablename__ = "lignes_devis"
    
    id = Column(Integer, primary_key=True, index=True)
    devis_id = Column(Integer, ForeignKey("devis.id"), nullable=False)
    
    # Type de ligne
    type_ligne = Column(String(50), nullable=False)  # forfait_mo, piece, main_oeuvre_libre
    
    # Référence forfait si applicable
    forfait_mo_id = Column(Integer, ForeignKey("forfaits_mo.id"), nullable=True)
    forfait_mo = relationship("ForfaitMO", back_populates="lignes_devis")
    
    # Référence pièce si applicable
    piece_id = Column(Integer, ForeignKey("pieces_detachees.id"), nullable=True)
    piece = relationship("PieceDetachee")
    
    # Description
    designation = Column(String(300), nullable=False)  # "Révision 125cc", "Huile moteur"
    description_detail = Column(Text)  # Détails supplémentaires
    
    # Quantité et prix
    quantite = Column(Integer, default=1)
    prix_unitaire_ht = Column(Numeric(10, 2), nullable=False)
    taux_tva = Column(Float, default=20.0)
    
    # Totaux ligne
    total_ligne_ht = Column(Numeric(10, 2), nullable=False)
    total_ligne_ttc = Column(Numeric(10, 2), nullable=False)
    
    # Ordre d'affichage
    ordre = Column(Integer, default=0)
    
    devis = relationship("Devis", back_populates="lignes")


# ========== BASE DE DONNÉES MOTO (CATÉGORIES ET MODÈLES) ==========

class CategorieMoto(Base):
    """Catégorie de moto (Roadster, Sportive, Trail, etc.)"""
    __tablename__ = "categorie_motos"

    id = Column(Integer, primary_key=True, index=True)
    nom = Column(String(100), nullable=False, unique=True)
    description = Column(Text)
    created_at = Column(DateTime, default=datetime.now)

    modeles = relationship("ModeleMoto", back_populates="categorie")


class ModeleMoto(Base):
    """Modèle de moto référencé dans la base"""
    __tablename__ = "modele_motos"

    id = Column(Integer, primary_key=True, index=True)
    marque = Column(String(100), nullable=False, index=True)
    modele = Column(String(100), nullable=False)
    categorie_id = Column(Integer, ForeignKey("categorie_motos.id"), nullable=False)

    # Cylindrée (plage pour les modèles qui ont plusieurs déclinaisons)
    cylindree_min = Column(Integer, nullable=True)  # en cc
    cylindree_max = Column(Integer, nullable=True)  # en cc

    # Années de production
    annee_debut = Column(Integer, nullable=True)
    annee_fin = Column(Integer, nullable=True)

    created_at = Column(DateTime, default=datetime.now)
    updated_at = Column(DateTime, default=datetime.now, onupdate=datetime.now)

    categorie = relationship("CategorieMoto", back_populates="modeles")

    @property
    def cylindree_display(self):
        """Affichage formaté de la cylindrée"""
        if self.cylindree_min and self.cylindree_max:
            if self.cylindree_min == self.cylindree_max:
                return f"{self.cylindree_min}cc"
            return f"{self.cylindree_min}-{self.cylindree_max}cc"
        elif self.cylindree_min:
            return f"{self.cylindree_min}cc+"
        return "N/A"

    @property
    def annees_display(self):
        """Affichage formaté des années"""
        if self.annee_debut and self.annee_fin:
            return f"{self.annee_debut}-{self.annee_fin}"
        elif self.annee_debut:
            return f"Depuis {self.annee_debut}"
        return "N/A"


# ========== GESTION DES TARIFS - PRESTATIONS ==========

class Prestation(Base):
    """Prestation de l'atelier avec tarification et délai"""
    __tablename__ = "prestations"
    
    id = Column(Integer, primary_key=True, index=True)
    atelier_id = Column(Integer, ForeignKey("ateliers.id"), nullable=True, index=True)
    code = Column(String(50), unique=True, nullable=False, index=True)  # ex: VID-STD, REV-COMPL
    nom = Column(String(200), nullable=False)  # ex: "Vidange standard", "Révision complète"
    description = Column(Text)
    
    # Catégorie de prestation
    categorie = Column(String(100), nullable=False, default="entretien")  # entretien, reparation, diagnostic, personnalisation
    sous_categorie = Column(String(100))  # ex: moteur, freinage, suspension
    
    # Tarification
    prix_base_ht = Column(Numeric(10, 2), nullable=False, default=0.0)  # Prix de base HT
    prix_base_ttc = Column(Numeric(10, 2), nullable=False, default=0.0)  # Prix de base TTC
    
    # Temps et délai
    temps_estime_minutes = Column(Integer, nullable=False, default=30)  # Temps estimé en minutes
    delai_intervention_jours = Column(Integer, default=1)  # Délai d'intervention en jours
    
    # Type de tarification
    type_tarif = Column(String(50), default="forfait")  # forfait, horaire, devis
    taux_horaire_applique = Column(String(50), default="standard")  # standard, complexe, expert
    
    # Applicabilité
    type_vehicule = Column(String(50), default="tous")  # tous, moto, scooter, quad
    cylindree_min = Column(Integer, nullable=True)  # cc minimum
    cylindree_max = Column(Integer, nullable=True)  # cc maximum
    
    # Options
    is_active = Column(Integer, default=1)
    is_forfait = Column(Integer, default=0)  # 1 = forfait clé en main
    is_promo = Column(Integer, default=0)
    prix_promo_ttc = Column(Numeric(10, 2), nullable=True)
    
    # Composantes du forfait (si applicable)
    inclut_pieces = Column(Integer, default=0)
    description_pieces_incluses = Column(Text)
    cout_pieces_incluses_ht = Column(Numeric(10, 2), default=0.0)
    
    # Marge appliquée
    marge_pieces_pourcent = Column(Float, default=30.0)  # % de marge sur pièces
    
    created_at = Column(DateTime, default=datetime.now)
    updated_at = Column(DateTime, default=datetime.now, onupdate=datetime.now)


class GrilleTarifaire(Base):
    """Grille tarifaire par type de moto et/ou cylindrée"""
    __tablename__ = "grille_tarifaire"

    id = Column(Integer, primary_key=True, index=True)
    atelier_id = Column(Integer, ForeignKey("ateliers.id"), nullable=True, index=True)
    prestation_id = Column(Integer, ForeignKey("prestations.id"), nullable=False)

    # Critère principal : catégorie de moto (Roadster, Sportive, Trail, etc.)
    categorie_moto_id = Column(Integer, ForeignKey("categorie_motos.id"), nullable=True)

    # Critères secondaires (ignorés si categorie_moto_id est renseigné)
    type_vehicule = Column(String(50), default="tous")
    cylindree_min = Column(Integer, nullable=True)
    cylindree_max = Column(Integer, nullable=True)

    # Tarifs spécifiques
    prix_ht = Column(Numeric(10, 2), nullable=False)
    prix_ttc = Column(Numeric(10, 2), nullable=False)
    temps_minutes = Column(Integer, nullable=False)

    # Délai spécifique
    delai_jours = Column(Integer, default=1)

    is_active = Column(Integer, default=1)
    created_at = Column(DateTime, default=datetime.now)

    prestation = relationship("Prestation")
    categorie_moto = relationship("CategorieMoto")


class CalculTarif(Base):
    """Historique des calculs de tarifs pour devis"""
    __tablename__ = "calculs_tarifs"
    
    id = Column(Integer, primary_key=True, index=True)
    
    # Références
    client_id = Column(Integer, ForeignKey("clients.id"), nullable=True)
    vehicule_id = Column(Integer, ForeignKey("vehicules.id"), nullable=True)
    
    # Détails du calcul
    prestations_ids = Column(Text)  # JSON array des IDs prestations
    pieces_ids = Column(Text)  # JSON array des IDs pièces
    
    # Résultat
    total_mo_ht = Column(Numeric(10, 2), default=0.0)
    total_pieces_ht = Column(Numeric(10, 2), default=0.0)
    total_ht = Column(Numeric(10, 2), default=0.0)
    total_ttc = Column(Numeric(10, 2), default=0.0)
    marge_pieces = Column(Numeric(10, 2), default=0.0)
    
    # Délai total estimé
    delai_total_jours = Column(Integer, default=1)
    
    created_at = Column(DateTime, default=datetime.now)
    
    client = relationship("Client")
    vehicule = relationship("Vehicule")


# Table pour la nouvelle grille de tarifs (migration SQL)
class GrilleTarifs(Base):
    __tablename__ = "grille_tarifs"
    
    id = Column(Integer, primary_key=True, index=True)
    categorie_moto_id = Column(Integer, ForeignKey("categorie_motos.id"))
    type_intervention = Column(String(100), nullable=False)
    nom = Column(String(200), nullable=False)
    description = Column(Text)
    temps_minutes = Column(Integer, nullable=False)
    prix_mo_ht = Column(Numeric(10, 2), nullable=False)
    prix_mo_ttc = Column(Numeric(10, 2), nullable=False)
    pieces_incluses = Column(Boolean, default=False)
    actif = Column(Boolean, default=True)
    created_at = Column(DateTime, default=datetime.now)
    
    categorie = relationship("CategorieMoto")


# Table pour les rapports techniciens
class RapportTechnicien(Base):
    __tablename__ = "rapports_technicien"
    
    id = Column(Integer, primary_key=True, index=True)
    rendez_vous_id = Column(Integer, ForeignKey("rendez_vous.id"), nullable=False, unique=True)
    
    # Points de contrôle (JSON: {"niveau_huile": "ok", "pression_pneus": "nok", ...})
    # Valeurs possibles: "non_verifie", "ok", "nok"
    points_controle = Column(Text, default="{}")
    
    # Alertes et problèmes détectés
    alertes = Column(Text)
    
    # Recommandations pour le client
    recommandations = Column(Text)
    
    # Travaux réalisés (description détaillée)
    travaux_realises = Column(Text)
    
    # Pièces utilisées (JSON: [{"nom": "Filtre à huile", "quantite": 1, "reference": "FH123"}, ...])
    pieces_utilisees = Column(Text, default="[]")
    
    # Statut du rapport
    statut = Column(String(50), default="en_cours")  # en_cours, termine, attente_piece
    
    # Timestamps
    date_debut = Column(DateTime, default=datetime.now)
    date_fin = Column(DateTime)
    
    # Relation
    rendez_vous = relationship("RendezVous", back_populates="rapport_technicien")


# Table pour les demandes de travaux supplementaires
class DemandeTravauxSupp(Base):
    __tablename__ = "demandes_travaux_supp"

    id = Column(Integer, primary_key=True, index=True)
    rendez_vous_id = Column(Integer, ForeignKey("rendez_vous.id"), nullable=False)

    description = Column(Text)  # Notes libres du mecanicien
    prestations_demandees = Column(Text)  # JSON: [{"prestation_id":1,"nom":"Vidange","code":"VID01"},...]
    urgence = Column(String(50), default="normal")  # normal, urgent, critique

    # Devis fait par le receptionniste (pas le mecanicien)
    temps_estime = Column(Integer)  # en minutes - rempli par receptionniste
    prix_estime = Column(Numeric(10, 2))  # rempli par receptionniste
    statut = Column(String(50), default="en_attente")  # en_attente, devis_fait, approuve, refuse
    notes_receptionniste = Column(Text)
    decision_client = Column(String(50))  # accepte, refuse
    decision_client_at = Column(DateTime)

    created_at = Column(DateTime, default=datetime.now)
    approved_at = Column(DateTime)
    approved_by = Column(Integer, ForeignKey("users.id"), nullable=True)

    rendez_vous = relationship("RendezVous", back_populates="demandes_travaux_supp")


# Table pour archivage des ordres de reparation
class OrdreReparation(Base):
    __tablename__ = "ordres_reparation"

    id = Column(Integer, primary_key=True, index=True)
    rendez_vous_id = Column(Integer, ForeignKey("rendez_vous.id"), nullable=False)
    numero_or = Column(String(50), nullable=False, index=True)
    type_or = Column(String(50), default="initial")  # initial, supplementaire

    kilometrage = Column(Integer)
    etat_vehicule = Column(Text)
    travaux = Column(Text)

    demande_travaux_supp_id = Column(Integer, ForeignKey("demandes_travaux_supp.id"), nullable=True)
    signature_client = Column(Text, nullable=True)
    created_at = Column(DateTime, default=datetime.now)

    rendez_vous = relationship("RendezVous", back_populates="ordres_reparation")
    demande_travaux_supp = relationship("DemandeTravauxSupp")


# ========== FACTURATION & PAIEMENTS ==========

class Facture(Base):
    __tablename__ = "factures"

    id = Column(Integer, primary_key=True, index=True)
    atelier_id = Column(Integer, ForeignKey("ateliers.id"), nullable=True, index=True)
    numero_facture = Column(String(50), unique=True, nullable=False, index=True)

    rendez_vous_id = Column(Integer, ForeignKey("rendez_vous.id"), nullable=False)
    client_id = Column(Integer, ForeignKey("clients.id"), nullable=False)
    vehicule_id = Column(Integer, ForeignKey("vehicules.id"), nullable=True)

    # Breakdown facturation (snapshot)
    total_mo_ht = Column(Numeric(10, 2), default=0.0)
    total_pieces_ht = Column(Numeric(10, 2), default=0.0)
    total_ht = Column(Numeric(10, 2), default=0.0)
    tva_mo = Column(Numeric(10, 2), default=0.0)
    tva_pieces = Column(Numeric(10, 2), default=0.0)
    total_tva = Column(Numeric(10, 2), default=0.0)
    total_ttc = Column(Numeric(10, 2), default=0.0)

    remise_pourcentage = Column(Numeric(10, 2), default=0.0)
    remise_montant = Column(Numeric(10, 2), default=0.0)

    # Snapshot taux au moment de la facturation
    temps_facture_minutes = Column(Integer, default=0)
    taux_horaire = Column(Numeric(10, 2), default=65.0)
    tva_mo_taux = Column(Float, default=20.0)
    tva_pieces_taux = Column(Float, default=20.0)

    # Statut et dates
    statut = Column(String(50), default="emise")  # emise, payee, partiellement_payee, annulee
    date_creation = Column(DateTime, default=datetime.now)
    date_echeance = Column(Date, nullable=True)

    notes = Column(Text)
    created_at = Column(DateTime, default=datetime.now)
    updated_at = Column(DateTime, default=datetime.now, onupdate=datetime.now)

    rendez_vous = relationship("RendezVous", backref="factures")
    client = relationship("Client")
    vehicule = relationship("Vehicule")
    paiements = relationship("Paiement", back_populates="facture", cascade="all, delete-orphan")
    lignes = relationship("LigneFacture", back_populates="facture", cascade="all, delete-orphan", order_by="LigneFacture.ordre")


class LigneFacture(Base):
    __tablename__ = "lignes_facture"

    id = Column(Integer, primary_key=True, index=True)
    atelier_id = Column(Integer, ForeignKey("ateliers.id"), nullable=True, index=True)
    facture_id = Column(Integer, ForeignKey("factures.id"), nullable=False)

    type_ligne = Column(String(50), nullable=False)  # main_oeuvre, piece
    designation = Column(String(300), nullable=False)
    reference = Column(String(100))
    quantite = Column(Float, default=1)
    prix_unitaire_ht = Column(Numeric(10, 2), nullable=False)
    taux_tva = Column(Float, default=20.0)
    total_ligne_ht = Column(Numeric(10, 2), nullable=False)
    total_ligne_ttc = Column(Numeric(10, 2), nullable=False)

    ordre = Column(Integer, default=0)

    facture = relationship("Facture", back_populates="lignes")


class Paiement(Base):
    __tablename__ = "paiements"

    id = Column(Integer, primary_key=True, index=True)
    facture_id = Column(Integer, ForeignKey("factures.id"), nullable=False)

    montant = Column(Numeric(10, 2), nullable=False)
    mode_paiement = Column(String(50), nullable=False)  # cb, especes, cheque, virement, differe
    reference = Column(String(200))  # n° cheque, ref virement

    date_paiement = Column(DateTime, default=datetime.now)
    notes = Column(Text)
    created_at = Column(DateTime, default=datetime.now)

    facture = relationship("Facture", back_populates="paiements")

class TempsIntervention(Base):  # DEPRECATED - utiliser GrilleTarifaire avec categorie_moto_id
    """Durée d'intervention selon la catégorie moto et le type intervention"""
    __tablename__ = "temps_interventions"

    id = Column(Integer, primary_key=True, index=True)
    categorie_moto_id = Column(Integer, ForeignKey("categorie_motos.id"), nullable=False)
    intervention_type_id = Column(Integer, ForeignKey("intervention_types.id"), nullable=False)

    # Temps en minutes (peut varier par catégorie)
    temps_minutes = Column(Integer, nullable=False)  # ex: 45, 90, 120

    # Coefficient de difficulté (1.0=standard, 1.5=complexe, 2.0=expert)
    coefficient_difficulte = Column(Float, default=1.0)

    # Timestamps
    created_at = Column(DateTime, default=datetime.now)
    updated_at = Column(DateTime, default=datetime.now, onupdate=datetime.now)

    # Relations
    categorie_moto = relationship("CategorieMoto")
    intervention_type = relationship("InterventionType")


class HoraireAtelier(Base):
    """Horaires d'ouverture/fermeture de l'atelier par jour"""
    __tablename__ = "horaires_atelier"

    id = Column(Integer, primary_key=True, index=True)
    atelier_id = Column(Integer, ForeignKey("ateliers.id"), nullable=True, index=True)

    # Jour de la semaine (0=Lundi, 6=Dimanche)
    jour_semaine = Column(Integer, nullable=False)  # 0-6

    # Horaires
    heure_ouverture = Column(String(5))  # Format HH:MM ex: "08:00"
    heure_fermeture = Column(String(5))  # Format HH:MM ex: "18:00"

    # Pause déjeuner
    pause_debut = Column(String(5), nullable=True)  # "12:00"
    pause_fin = Column(String(5), nullable=True)    # "13:30"

    # Ouvert ce jour? (colonne integer en base)
    is_ouvert = Column(Integer, default=1)  # 1=ouvert, 0=fermé

    # Timestamps
    updated_at = Column(DateTime, default=datetime.now, onupdate=datetime.now)


class PontEquipement(Base):
    """Équipements disponibles sur chaque pont"""
    __tablename__ = "pont_equipements"

    id = Column(Integer, primary_key=True, index=True)
    pont_id = Column(Integer, ForeignKey("ponts.id"), nullable=False)

    # Nom équipement
    nom = Column(String(200), nullable=False)  # "Élévateur hydraulique", "Démonte-pneu", etc.
    description = Column(Text, nullable=True)

    # Présent sur ce pont?
    is_present = Column(Boolean, default=True)  # True=oui, False=non

    # Timestamps
    created_at = Column(DateTime, default=datetime.now)
    updated_at = Column(DateTime, default=datetime.now, onupdate=datetime.now)

    # Relation
    pont = relationship("Pont")

class AtelierCategorieMoto(Base):
    """Activation/désactivation des catégories moto par atelier"""
    __tablename__ = "atelier_categorie_motos"

    id = Column(Integer, primary_key=True, index=True)
    atelier_id = Column(Integer, ForeignKey("ateliers.id"), nullable=False, index=True)
    categorie_moto_id = Column(Integer, ForeignKey("categorie_motos.id"), nullable=False, index=True)
    is_active = Column(Boolean, default=True)
    created_at = Column(DateTime, default=datetime.now)
    updated_at = Column(DateTime, default=datetime.now, onupdate=datetime.now)


class RevokedToken(Base):
    __tablename__ = "revoked_tokens"

    id = Column(Integer, primary_key=True, index=True)
    jti = Column(String(255), unique=True, nullable=False, index=True)
    expires_at = Column(DateTime, nullable=False, index=True)
    reason = Column(String(100), nullable=False, default="manual")
    created_at = Column(DateTime, default=datetime.now, nullable=False)


# Configuration de la base de données
import os
from dotenv import load_dotenv

load_dotenv()
DATABASE_URL = os.getenv("DATABASE_URL", "postgresql://atelier:atelier@db:5432/atelier_moto")
engine = create_engine(DATABASE_URL, connect_args={"check_same_thread": False} if "sqlite" in DATABASE_URL else {})
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

def get_db():
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()

def init_db():
    Base.metadata.create_all(bind=engine)
