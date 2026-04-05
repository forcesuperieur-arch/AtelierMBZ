import os

os.environ.setdefault("SECRET_KEY", "test-secret-key-for-auth-runtime")

from auth import get_password_hash, verify_password


def test_verify_password_uses_real_bcrypt_hash():
    password = "TestPass123"
    hashed = get_password_hash(password)

    assert verify_password(password, hashed) is True
    assert verify_password("WrongPass999", hashed) is False


def test_verify_password_returns_false_for_invalid_hashes():
    assert verify_password("password", "") is False
    assert verify_password("password", "invalid_hash") is False
