"""
Configuration partagée pour pytest - Fixtures et hooks.
"""
import pytest
import sys
import os

# Ajouter le répertoire parent au path
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

# Configuration des variables d'environnement pour les tests
os.environ["SECRET_KEY"] = "test-secret-key-for-testing-only"
os.environ["CORS_ORIGINS"] = "http://localhost:3000"
os.environ["DATABASE_URL"] = "sqlite:///:memory:"

from fastapi.testclient import TestClient
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker
from sqlalchemy.pool import StaticPool

# Importer les modèles sans créer l'engine PostgreSQL
from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy import Column, Integer, String, DateTime, Date, Time, Float, Text, ForeignKey
from datetime import datetime

Base = declarative_base()

# Recréer les modèles pour les tests
class Client(Base):
    __tablename__ = "clients"
    id = Column(Integer, primary_key=True)
    nom = Column(String(100), nullable=False)
    prenom = Column(String(100), nullable=False)
    telephone = Column(String(20), nullable=False)
    email = Column(String(200))
    adresse = Column(Text)
    notes = Column(Text)
    created_at = Column(DateTime, default=datetime.now)

class Vehicule(Base):
    __tablename__ = "vehicules"
    id = Column(Integer, primary_key=True)
    plaque = Column(String(20), nullable=False)
    marque = Column(String(100))
    modele = Column(String(100))
    annee = Column(Integer)
    cylindree = Column(String(50))
    type_moto = Column(String(50))

class RendezVous(Base):
    __tablename__ = "rendez_vous"
    id = Column(Integer, primary_key=True)
    client_id = Column(Integer, ForeignKey("clients.id"))
    vehicule_id = Column(Integer, ForeignKey("vehicules.id"))
    date_rdv = Column(Date, nullable=False)
    heure_rdv = Column(Time, nullable=False)
    type_intervention = Column(String(200), nullable=False)
    commentaire = Column(Text)
    prix_estime = Column(Float)
    prix_final = Column(Float)
    temps_estime = Column(Integer)
    temps_final = Column(Integer)
    kilometrage = Column(Integer)
    etat_vehicule = Column(Text)
    statut = Column(String(50), default="en_attente")
    created_at = Column(DateTime, default=datetime.now)
    updated_at = Column(DateTime, default=datetime.now, onupdate=datetime.now)

class User(Base):
    __tablename__ = "users"
    id = Column(Integer, primary_key=True)
    username = Column(String(100), unique=True, nullable=False)
    email = Column(String(200), unique=True, nullable=False)
    hashed_password = Column(String(200), nullable=False)
    role = Column(String(50), default="receptionnaire")
    is_active = Column(Integer, default=1)
    created_at = Column(DateTime, default=datetime.now)

class InterventionType(Base):
    __tablename__ = "intervention_types"
    id = Column(Integer, primary_key=True)
    nom = Column(String(200), nullable=False)
    description = Column(Text)
    prix_base = Column(Float, nullable=False)
    temps_estime = Column(Integer, nullable=False)
    is_active = Column(Integer, default=1)

class Pont(Base):
    __tablename__ = "ponts"
    id = Column(Integer, primary_key=True)
    nom = Column(String(100), nullable=False)
    type_pont = Column(String(50), default="moto")
    capacite_kg = Column(Integer, default=500)
    is_active = Column(Integer, default=1)
    ordre_affichage = Column(Integer, default=0)

class Mecanicien(Base):
    __tablename__ = "mecaniciens"
    id = Column(Integer, primary_key=True)
    nom = Column(String(100), nullable=False)
    prenom = Column(String(100), nullable=False)
    specialites = Column(Text)
    couleur = Column(String(7), default="#3b82f6")
    is_active = Column(Integer, default=1)

class Fournisseur(Base):
    __tablename__ = "fournisseurs"
    id = Column(Integer, primary_key=True)
    nom = Column(String(200), nullable=False)
    contact = Column(String(200))
    telephone = Column(String(20))
    email = Column(String(200))
    adresse = Column(Text)
    siret = Column(String(20))
    delai_livraison_jours = Column(Integer, default=3)
    notes = Column(Text)
    is_active = Column(Integer, default=1)
    created_at = Column(DateTime, default=datetime.now)

class PieceDetachee(Base):
    __tablename__ = "pieces_detachees"
    id = Column(Integer, primary_key=True)
    reference = Column(String(100), unique=True, nullable=False)
    reference_fournisseur = Column(String(100))
    nom = Column(String(200), nullable=False)
    description = Column(Text)
    categorie = Column(String(100))
    quantite_stock = Column(Integer, default=0)
    quantite_minimale = Column(Integer, default=5)
    quantite_maximale = Column(Integer, default=50)
    emplacement = Column(String(100))
    prix_achat_ht = Column(Float, default=0.0)
    prix_vente_ht = Column(Float, default=0.0)
    tva_taux = Column(Float, default=20.0)
    fournisseur_id = Column(Integer, ForeignKey("fournisseurs.id"))
    is_active = Column(Integer, default=1)
    created_at = Column(DateTime, default=datetime.now)
    updated_at = Column(DateTime, default=datetime.now, onupdate=datetime.now)
    
    @property
    def stock_bas(self):
        return self.quantite_stock <= self.quantite_minimale
    
    @property
    def prix_vente_ttc(self):
        return self.prix_vente_ht * (1 + self.tva_taux / 100)

class CommandeFournisseur(Base):
    __tablename__ = "commandes_fournisseur"
    id = Column(Integer, primary_key=True)
    numero_commande = Column(String(50), unique=True, nullable=False)
    fournisseur_id = Column(Integer, ForeignKey("fournisseurs.id"), nullable=False)
    statut = Column(String(50), default="en_attente")
    date_commande = Column(DateTime, default=datetime.now)
    date_prevue_livraison = Column(DateTime)
    date_reception = Column(DateTime)
    total_ht = Column(Float, default=0.0)
    total_ttc = Column(Float, default=0.0)
    notes = Column(Text)

class ConfigAtelier(Base):
    __tablename__ = "config_atelier"
    id = Column(Integer, primary_key=True)
    taux_horaire_mo_standard = Column(Float, default=65.0)
    taux_horaire_mo_complexe = Column(Float, default=85.0)
    taux_horaire_mo_expert = Column(Float, default=95.0)
    marge_pieces_standard = Column(Float, default=30.0)
    marge_pieces_consommable = Column(Float, default=50.0)
    marge_pieces_pneumatique = Column(Float, default=25.0)
    forfait_mo_minimum = Column(Float, default=25.0)
    tva_mo_taux = Column(Float, default=20.0)
    tva_pieces_taux = Column(Float, default=20.0)
    validite_devis_jours = Column(Integer, default=30)
    accompte_pourcentage = Column(Float, default=30.0)
    updated_at = Column(DateTime, default=datetime.now, onupdate=datetime.now)

class ForfaitMO(Base):
    __tablename__ = "forfaits_mo"
    id = Column(Integer, primary_key=True)
    code = Column(String(50), unique=True, nullable=False)
    nom = Column(String(200), nullable=False)
    description = Column(Text)
    categorie = Column(String(100))
    temps_base_minutes = Column(Integer, nullable=False)
    taux_horaire_applique = Column(String(50), default="standard")
    prix_forfait_mo_ht = Column(Float, nullable=False)
    prix_forfait_mo_ttc = Column(Float, nullable=False)
    inclut_pieces = Column(Integer, default=0)
    description_pieces_incluses = Column(Text)
    prix_pieces_incluses_ht = Column(Float, default=0.0)
    type_vehicule = Column(String(50), default="tous")
    cylindree_min = Column(Integer)
    cylindree_max = Column(Integer)
    is_active = Column(Integer, default=1)
    is_promo = Column(Integer, default=0)
    prix_promo_mo_ttc = Column(Float)
    created_at = Column(DateTime, default=datetime.now)
    updated_at = Column(DateTime, default=datetime.now, onupdate=datetime.now)

# Base de données en mémoire pour les tests
SQLALCHEMY_DATABASE_URL = "sqlite:///:memory:"
engine = create_engine(
    SQLALCHEMY_DATABASE_URL,
    connect_args={"check_same_thread": False},
    poolclass=StaticPool,
)
TestingSessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

def get_db():
    db = TestingSessionLocal()
    try:
        yield db
    finally:
        db.close()

def override_get_db():
    """Override de la dépendance get_db pour les tests"""
    try:
        db = TestingSessionLocal()
        yield db
    finally:
        db.close()

# Importer FastAPI pour créer une app de test
from fastapi import FastAPI
app = FastAPI()
app.dependency_overrides[get_db] = override_get_db

# Base de données en mémoire pour les tests
SQLALCHEMY_DATABASE_URL = "sqlite:///:memory:"
engine = create_engine(
    SQLALCHEMY_DATABASE_URL,
    connect_args={"check_same_thread": False},
    poolclass=StaticPool,
)
TestingSessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)


def override_get_db():
    """Override de la dépendance get_db pour les tests"""
    try:
        db = TestingSessionLocal()
        yield db
    finally:
        db.close()


app.dependency_overrides[get_db] = override_get_db


@pytest.fixture(scope="session", autouse=True)
def setup_database():
    """Fixture pour créer les tables de la base de données"""
    Base.metadata.create_all(bind=engine)
    yield
    Base.metadata.drop_all(bind=engine)


@pytest.fixture(scope="function")
def db_session():
    """Fixture pour une session de base de données propre par test"""
    connection = engine.connect()
    transaction = connection.begin()
    session = TestingSessionLocal(bind=connection)
    
    yield session
    
    session.close()
    transaction.rollback()
    connection.close()


@pytest.fixture
def test_client():
    """Fixture pour le client HTTP de test avec état isolé"""
    with TestClient(app) as client:
        yield client


@pytest.fixture
def auth_token(test_client):
    """Fixture pour obtenir un token d'authentification valide"""
    from auth import get_password_hash
    from models import User
    
    db = TestingSessionLocal()
    user = db.query(User).filter(User.username == "testuser").first()
    if not user:
        user = User(
            username="testuser",
            email="test@test.com",
            hashed_password=get_password_hash("testpass"),
            role="admin"
        )
        db.add(user)
        db.commit()
        db.refresh(user)
    db.close()
    
    response = test_client.post(
        "/api/auth/login",
        data={"username": "testuser", "password": "testpass"}
    )
    assert response.status_code == 200
    return response.json()["access_token"]


@pytest.fixture
def auth_headers(auth_token):
    """Fixture pour les headers d'authentification"""
    return {"Authorization": f"Bearer {auth_token}"}


@pytest.fixture
def sample_client(db_session):
    """Fixture pour créer un client de test"""
    from models import Client
    
    client = Client(
        nom="Dupont",
        prenom="Jean",
        telephone="0612345678",
        email="jean.dupont@test.com"
    )
    db_session.add(client)
    db_session.commit()
    db_session.refresh(client)
    return client


@pytest.fixture
def sample_vehicule(db_session):
    """Fixture pour créer un véhicule de test"""
    from models import Vehicule
    
    vehicule = Vehicule(
        plaque="TEST123",
        marque="YAMAHA",
        modele="MT-07",
        annee=2020
    )
    db_session.add(vehicule)
    db_session.commit()
    db_session.refresh(vehicule)
    return vehicule


@pytest.fixture
def sample_rendez_vous(db_session, sample_client, sample_vehicule):
    """Fixture pour créer un rendez-vous de test"""
    from models import RendezVous
    from datetime import date, time
    
    rdv = RendezVous(
        client_id=sample_client.id,
        vehicule_id=sample_vehicule.id,
        date_rdv=date.today(),
        heure_rdv=time(10, 0),
        type_intervention="Révision",
        statut="en_attente"
    )
    db_session.add(rdv)
    db_session.commit()
    db_session.refresh(rdv)
    return rdv


@pytest.fixture
def sample_pont(db_session):
    """Fixture pour créer un pont de test"""
    from models import Pont
    
    pont = Pont(
        nom="Pont Test",
        type_pont="moto",
        capacite_kg=500
    )
    db_session.add(pont)
    db_session.commit()
    db_session.refresh(pont)
    return pont


@pytest.fixture
def sample_mecanicien(db_session):
    """Fixture pour créer un mécanicien de test"""
    from models import Mecanicien
    
    mecano = Mecanicien(
        nom="Martin",
        prenom="Pierre",
        specialites="moteur",
        couleur="#ff0000"
    )
    db_session.add(mecano)
    db_session.commit()
    db_session.refresh(mecano)
    return mecano


@pytest.fixture
def sample_fournisseur(db_session):
    """Fixture pour créer un fournisseur de test"""
    from models import Fournisseur
    
    fournisseur = Fournisseur(
        nom="Fournisseur Test",
        contact="Jean Test",
        telephone="0123456789",
        email="test@fournisseur.fr"
    )
    db_session.add(fournisseur)
    db_session.commit()
    db_session.refresh(fournisseur)
    return fournisseur


@pytest.fixture
def sample_piece(db_session, sample_fournisseur):
    """Fixture pour créer une pièce de test"""
    from models import PieceDetachee
    
    piece = PieceDetachee(
        reference="TEST-PIECE-001",
        nom="Pièce de test",
        categorie="moteur",
        quantite_stock=10,
        quantite_minimale=5,
        prix_achat_ht=20.0,
        prix_vente_ht=35.0,
        fournisseur_id=sample_fournisseur.id
    )
    db_session.add(piece)
    db_session.commit()
    db_session.refresh(piece)
    return piece


# Hooks pytest

def pytest_configure(config):
    """Configuration pytest personnalisée"""
    config.addinivalue_line("markers", "unit: Tests unitaires")
    config.addinivalue_line("markers", "api: Tests d'API")
    config.addinivalue_line("markers", "integration: Tests d'intégration")
    config.addinivalue_line("markers", "slow: Tests lents")
    config.addinivalue_line("markers", "auth: Tests d'authentification")


def pytest_collection_modifyitems(config, items):
    """Modifier les items de collection"""
    pass


@pytest.fixture(autouse=True)
def reset_database(db_session):
    """Reset la base de données avant chaque test"""
    yield
    # Le rollback est géré par db_session
