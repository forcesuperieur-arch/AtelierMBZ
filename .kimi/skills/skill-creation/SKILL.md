# Skill Creation — Méta-Skill

## Quand créer un nouveau skill ?
Je dois créer un skill quand :
1. Je bloque sur un domaine technique récurrent (ex: "je ne sais pas comment tester les WebSockets")
2. Je répète les mêmes recherches/explications sur plusieurs sessions
3. L'utilisateur me demande quelque chose dans un domaine nouveau pour le projet (ex: "ajoute l'intégration Stripe")
4. Je découvre un pattern complexe qui mérite d'être documenté (ex: workflow de signature électronique)

## Comment créer un skill
1. **Identifier le scope** : ce skill est-il spécifique au projet AtelierMBZ ou général ?
   - Projet → `C:\Users\colin\AtelierMBZ\.kimi\skills\<nom>/SKILL.md`
   - Utilisateur → `C:\Users\colin\.kimi\skills\<nom>/SKILL.md`

2. **Nommer le dossier** : kebab-case, descriptif
   - `stripe-integration`
   - `notification-webhooks`
   - `cerfa-pdf-generation`
   - `oauth2-security`

3. **Rédiger SKILL.md** avec cette structure :
```markdown
# Titre du skill

## Contexte
Pourquoi ce skill existe, dans quel cas l'utiliser

## Prérequis
Dépendances, packages, config nécessaire

## Patterns / How-to
Exemples de code copiables, conventions spécifiques

## Pièges connus
Erreurs fréquentes et comment les éviter

## Fichiers clés
Chemins des fichiers importants liés à ce domaine

## Commandes utiles
```

4. **Valider** : le skill doit être auto-contenu. Quand je le relis, je dois pouvoir implémenter la feature sans poser de question.

5. **Maintenir** : si je modifie une architecture documentée dans un skill, je dois mettre à jour le skill.

## Liste des skills existants sur ce projet
- `atelier-mbz-core` — Contexte projet global
- `symfony-api-testing` — Tests backend
- `nuxt-frontend-patterns` — Patterns frontend
- `db-migrations-seed` — BDD et seeds
- `playwright-e2e` — Tests end-to-end
- `git-workflow` — Workflow Git
- `skill-creation` — Ce fichier (méta)

## Règle d'or
> Si je dois chercher sur le web ou relire 3+ fichiers pour comprendre comment faire quelque chose que j'ai déjà fait, c'est qu'il manque un skill.
