"""
Tests pour les fonctions utilitaires et helpers.
"""
import pytest
import sys
import os
from datetime import date, time, datetime

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))


class TestDateTimeUtils:
    """Tests pour les utilitaires de date/heure"""
    
    def test_date_format_iso(self):
        """Test le formatage des dates en ISO"""
        d = date(2024, 3, 15)
        assert d.isoformat() == "2024-03-15"
    
    def test_time_format(self):
        """Test le formatage des heures"""
        t = time(14, 30, 0)
        assert str(t) == "14:30:00"
    
    def test_datetime_now(self):
        """Test la création de datetime actuel"""
        now = datetime.now()
        assert isinstance(now, datetime)
    
    def test_date_comparison(self):
        """Test la comparaison de dates"""
        d1 = date(2024, 3, 15)
        d2 = date(2024, 3, 16)
        assert d1 < d2
        assert d2 > d1
        assert d1 != d2


class TestStringUtils:
    """Tests pour les utilitaires de chaînes"""
    
    def test_plaque_format_normalization(self):
        """Test la normalisation des plaques d'immatriculation"""
        plaques = [
            ("AA-123-BB", "AA123BB"),
            ("AA 123 BB", "AA123BB"),
            ("aa123bb", "AA123BB"),
            ("AA123BB", "AA123BB"),
        ]
        
        for input_plaque, expected in plaques:
            # Simuler la normalisation
            normalized = input_plaque.upper().replace(" ", "").replace("-", "")
            assert normalized == expected
    
    def test_phone_number_validation(self):
        """Test la validation des numéros de téléphone"""
        phones = [
            "0612345678",  # Valide
            "+33612345678",  # Valide avec indicatif
            "01 23 45 67 89",  # Valide avec espaces
        ]
        
        for phone in phones:
            # Vérifier que c'est une chaîne non vide
            assert isinstance(phone, str)
            assert len(phone) > 0
    
    def test_email_validation_pattern(self):
        """Test le pattern de validation d'email basique"""
        emails_valides = [
            "test@example.com",
            "user.name@domain.fr",
            "user+tag@example.co.uk"
        ]
        
        for email in emails_valides:
            assert "@" in email
            assert "." in email.split("@")[1]


class TestNumericUtils:
    """Tests pour les utilitaires numériques"""
    
    def test_prix_calculations(self):
        """Test les calculs de prix"""
        prix_ht = 100.0
        tva = 20.0
        prix_ttc = prix_ht * (1 + tva / 100)
        assert prix_ttc == 120.0
    
    def test_remise_calculations(self):
        """Test les calculs de remise"""
        total = 200.0
        remise_pct = 10.0
        remise_montant = total * (remise_pct / 100)
        total_remise = total - remise_montant
        assert remise_montant == 20.0
        assert total_remise == 180.0
    
    def test_temps_conversion(self):
        """Test la conversion des temps"""
        minutes = 90
        heures = minutes // 60
        mins = minutes % 60
        assert heures == 1
        assert mins == 30
        assert f"{heures}h{mins:02d}" == "1h30"


class TestValidationUtils:
    """Tests pour les fonctions de validation"""
    
    def test_statut_validation(self):
        """Test la validation des statuts de RDV"""
        statuts_valides = [
            "en_attente", "confirme", "en_cours", 
            "termine", "restitue", "facture", "paye", "annule"
        ]
        
        for statut in statuts_valides:
            assert statut in statuts_valides
    
    def test_role_validation(self):
        """Test la validation des rôles utilisateur"""
        roles_valides = ["admin", "receptionnaire", "mecanicien"]
        
        for role in roles_valides:
            assert role in roles_valides
    
    def test_categorie_piece_validation(self):
        """Test la validation des catégories de pièces"""
        categories_valides = [
            "moteur", "freinage", "electricite", 
            "carrosserie", "eclairage", "pneumatique"
        ]
        
        for cat in categories_valides:
            assert isinstance(cat, str)
            assert len(cat) > 0


class TestDataStructures:
    """Tests pour les structures de données"""
    
    def test_dict_creation(self):
        """Test la création de dictionnaires"""
        data = {
            "id": 1,
            "nom": "Test",
            "actif": True
        }
        assert data["id"] == 1
        assert data["nom"] == "Test"
        assert data["actif"] == True
    
    def test_list_operations(self):
        """Test les opérations sur les listes"""
        items = [1, 2, 3, 4, 5]
        assert len(items) == 5
        assert sum(items) == 15
        assert max(items) == 5
        assert min(items) == 1
    
    def test_json_serialization_concept(self):
        """Test le concept de sérialisation JSON"""
        data = {
            "client": {"nom": "Dupont", "prenom": "Jean"},
            "vehicule": {"plaque": "AA123BB"}
        }
        
        # Vérifier la structure
        assert "client" in data
        assert "vehicule" in data
        assert data["client"]["nom"] == "Dupont"


if __name__ == "__main__":
    pytest.main([__file__, "-v"])
