# Guide Utilisateur

## Verification avant livraison (technique)

Avant de finaliser une evolution, executer:

```bash
./scripts/check_code_hygiene.sh
```

Le script verifie:
- la taille maximale des fichiers source (200 lignes)
- l'absence de mots de passe/secrets evidents en dur

Si le script affiche une erreur, corriger les points signales puis relancer.

