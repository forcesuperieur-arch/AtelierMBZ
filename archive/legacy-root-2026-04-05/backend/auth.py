#!/usr/bin/env python3
"""
Script pour exécuter tous les tests de l'application Atelier Moto.
Usage: python run_tests.py [options]
"""
import subprocess
import sys
import argparse


def run_backend_tests(args):
    """Exécute les tests backend avec pytest"""
    print("🧪 Exécution des tests backend...")
    print("=" * 60)
    
    cmd = ["python", "-m", "pytest"]
    
    if args.verbose:
        cmd.append("-v")
    if args.coverage:
        cmd.extend(["--cov=.", "--cov-report=term-missing"])
    if args.markers:
        cmd.extend(["-m", args.markers])
    if args.k:
        cmd.extend(["-k", args.k])
    
    cmd.append("tests/")
    
    result = subprocess.run(cmd, cwd="backend")
    return result.returncode


def run_frontend_tests(args):
    """Exécute les tests frontend (si Node.js est disponible)"""
    print("\n🌐 Exécution des tests frontend...")
    print("=" * 60)
    
    # Vérifier si Node.js est installé
    result = subprocess.run(["which", "node"], capture_output=True)
    if result.returncode != 0:
        print("⚠️  Node.js non installé, impossible d'exécuter les tests frontend")
        print("   Les tests frontend peuvent être exécutés dans un navigateur:")
        print("   Ouvrez frontend/tests/index.html")
        return 0
    
    print("✅ Node.js détecté")
    print("   Les tests frontend sont dans frontend/tests/")
    print("   Ouvrez frontend/tests/index.html dans un navigateur pour les exécuter")
    return 0


def run_all_tests(args):
    """Exécute tous les tests"""
    print("🚀 Lancement de tous les tests - Atelier Moto")
    print("=" * 60)
    
    backend_code = run_backend_tests(args)
    frontend_code = run_frontend_tests(args)
    
    print("\n" + "=" * 60)
    print("📊 RÉSUMÉ")
    print("=" * 60)
    
    if backend_code == 0:
        print("✅ Tests backend: PASS")
    else:
        print("❌ Tests backend: FAIL")
    
    if frontend_code == 0:
        print("✅ Tests frontend: PASS (ou non exécutés)")
    else:
        print("❌ Tests frontend: FAIL")
    
    return backend_code or frontend_code


def main():
    parser = argparse.ArgumentParser(
        description="Exécute les tests de l'application Atelier Moto"
    )
    parser.add_argument(
        "-v", "--verbose",
        action="store_true",
        help="Mode verbeux"
    )
    parser.add_argument(
        "--coverage",
        action="store_true",
        help="Générer un rapport de couverture"
    )
    parser.add_argument(
        "-m", "--markers",
        help="Exécuter uniquement les tests avec ces marqueurs (ex: unit, api, integration)"
    )
    parser.add_argument(
        "-k",
        help="Exécuter uniquement les tests correspondant à l'expression"
    )
    parser.add_argument(
        "--backend-only",
        action="store_true",
        help="Exécuter uniquement les tests backend"
    )
    parser.add_argument(
        "--frontend-only",
        action="store_true",
        help="Exécuter uniquement les tests frontend"
    )
    
    args = parser.parse_args()
    
    if args.frontend_only:
        return run_frontend_tests(args)
    elif args.backend_only:
        return run_backend_tests(args)
    else:
        return run_all_tests(args)


if __name__ == "__main__":
    sys.exit(main())
