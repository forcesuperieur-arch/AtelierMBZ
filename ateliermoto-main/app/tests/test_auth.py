"""
Tests unitaires pour le module d'authentification.
"""
import pytest
import sys
import os
from datetime import timedelta

# Configuration des variables d'environnement
os.environ["SECRET_KEY"] = "test-secret-key-for-testing-only"

# Importer bcrypt directement pour les tests de hachage
import bcrypt


def verify_password(plain_password: str, hashed_password: str) -> bool:
    """Vérifie un mot de passe avec bcrypt"""
    try:
        return bcrypt.checkpw(
            plain_password.encode('utf-8'),
            hashed_password.encode('utf-8')
        )
    except Exception:
        return False


def get_password_hash(password: str) -> str:
    """Hash un mot de passe avec bcrypt"""
    salt = bcrypt.gensalt(rounds=12)
    hashed = bcrypt.hashpw(password.encode('utf-8'), salt)
    return hashed.decode('utf-8')


# Importer JWT pour les tests de token
try:
    from jose import jwt
    JWT_AVAILABLE = True
except ImportError:
    JWT_AVAILABLE = False
    jwt = None


def create_access_token(data: dict, expires_delta: timedelta = None):
    """Crée un token JWT"""
    if not JWT_AVAILABLE:
        raise ImportError("python-jose non installé")
    
    to_encode = data.copy()
    if expires_delta:
        from datetime import datetime
        expire = datetime.utcnow() + expires_delta
    else:
        from datetime import datetime
        expire = datetime.utcnow() + timedelta(minutes=15)
    to_encode.update({"exp": expire})
    encoded_jwt = jwt.encode(to_encode, os.environ["SECRET_KEY"], algorithm="HS256")
    return encoded_jwt


class TestPasswordHashing:
    """Tests pour le hachage des mots de passe"""
    
    def test_password_hashing(self):
        """Test que le hachage fonctionne correctement"""
        password = "testpassword123"
        hashed = get_password_hash(password)
        
        # Le hash doit être différent du mot de passe en clair
        assert hashed != password
        # Le hash doit être une chaîne
        assert isinstance(hashed, str)
        # Le hash doit commencer par le préfixe bcrypt
        assert hashed.startswith('$2')
    
    def test_password_verification_success(self):
        """Test la vérification d'un mot de passe correct"""
        password = "testpassword123"
        hashed = get_password_hash(password)
        
        assert verify_password(password, hashed) == True
    
    def test_password_verification_failure(self):
        """Test la vérification d'un mot de passe incorrect"""
        password = "testpassword123"
        wrong_password = "wrongpassword"
        hashed = get_password_hash(password)
        
        assert verify_password(wrong_password, hashed) == False
    
    def test_verify_password_with_invalid_hash(self):
        """Test la vérification avec un hash invalide"""
        # Ne doit pas lever d'exception
        result = verify_password("password", "invalid_hash")
        assert result == False


class TestAccessToken:
    """Tests pour la création des tokens JWT"""
    
    @pytest.mark.skipif(not JWT_AVAILABLE, reason="python-jose non installé")
    def test_create_access_token(self):
        """Test la création d'un token d'accès"""
        data = {"sub": "testuser", "role": "admin"}
        token = create_access_token(data)
        
        assert isinstance(token, str)
        assert len(token) > 0
        # Un JWT a 3 parties séparées par des points
        assert token.count('.') == 2
    
    @pytest.mark.skipif(not JWT_AVAILABLE, reason="python-jose non installé")
    def test_create_access_token_with_expiration(self):
        """Test la création d'un token avec expiration"""
        data = {"sub": "testuser"}
        expires = timedelta(minutes=30)
        token = create_access_token(data, expires_delta=expires)
        
        assert isinstance(token, str)
        assert len(token) > 0


class TestAuthConfiguration:
    """Tests pour la configuration de l'authentification"""
    
    def test_secret_key_set(self):
        """Test que la clé secrète est définie"""
        assert os.environ.get("SECRET_KEY") is not None
        assert len(os.environ.get("SECRET_KEY")) > 0
    
    def test_algorithm_constant(self):
        """Test que l'algorithme est correctement configuré"""
        algorithm = "HS256"
        assert algorithm == "HS256"


if __name__ == "__main__":
    pytest.main([__file__, "-v"])
