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

## Donnees locales a ne pas versionner

Avec la nouvelle structure `app/`, les fichiers locaux suivants restent hors Git:
- `app/.env`
- `app/.secret_key`
- `app/signatures/`
- `app/data/logos/`

Ils sont generes ou utilises localement par l'application et ne doivent pas etre pousses.

