# Paddock — paquet de design system

Ce dossier est **produit par le projet Claude Design « Paddock Design System »**. Il porte les fondations visuelles de Paddock : jetons, polices, logos, règles écrites.

## Où le poser

À la racine du dépôt `ateliermbz`, tel quel :

```
ateliermbz/
  design-system/        ← le contenu de handoff/design-system/
  frontend/
  client-frontend/
  scripts/design/sync-tokens.mjs   ← à remplacer par la version fournie ici
```

## Ce qu'il contient

| Chemin | Contenu |
| --- | --- |
| `styles.css` | Point d'entrée : une liste de `@import`, rien d'autre. |
| `tokens/mb-*.css` | Fondations Motoblouz (Gazoline), reprises verbatim : couleurs, typographie, espacement, tailles, rayons, mouvement, interactions. |
| `tokens/paddock-app.css` | Couche applicative Paddock : encre douce, canevas de travail, surfaces de statut, géométrie du shell, cibles tactiles, thème atelier `.pk-workshop`. |
| `tokens/app-semantic.css` | Couche sémantique consommée par les deux fronts Nuxt : `--surface-*`, `--content-*`, `--border-*`, `--accent*`, `--success/--warning/--error/--info`, plus les alias historiques. |
| `fonts/` | Montserrat, 9 graisses × upright/italique, en `.ttf`, avec ses `@font-face`. |
| `assets/` | Les cinq logos Paddock. |
| `readme.md` | Le guide complet : contexte produit, fondamentaux de contenu, fondations visuelles, iconographie, index. |


## Les templates ne sont pas dans ce paquet

Les huit surfaces dans leur état visé — front atelier (26 écrans), portail client, front public, cockpit SRC, compagnon VO, e-mails, documents A4, affichage mural — **restent dans le projet Claude Design**, où elles se regardent et se cliquent. Un paquet de fichiers ne rend pas un écran ; les ouvrir dans le design system, si.

Chaque surface y a son README qui dit ce que l'écran doit montrer, ce qui est cliquable, et les écarts assumés. C'est la référence à ouvrir avant d'implémenter un écran.

## Le sens de circulation

Une règle par couche, une seule, et pas de retour :

| Couche | Vérité | Qui l'écrit |
| --- | --- | --- |
| Jetons, polices, logos | **Le design system** | Le projet Claude Design. Dans le dépôt, ces fichiers sont **en lecture seule**. |
| Écrans : mise en page, libellés, états, enchaînements | **Le design system** | Les huit templates décrivent l'état visé. Le code s'y aligne ; un écran qui diverge est un écart à corriger, pas une variante. |
| Implémentation : composants Vue, stores, backend, tests | **Le dépôt** | Claude Code. Le design system ne dit pas comment c'est écrit. |
| Règles écrites (ton, iconographie, fondations) | **Le design system** | `readme.md` ici ; `docs/DESIGN-SYSTEM.md` du dépôt en est le reflet côté développeur. |

Ce paquet inverse le sens qui existait : `frontend/assets/css/tokens.css` n'est plus la source, il devient une **copie générée**. Le script `sync-tokens.mjs` fourni ici s'en charge et refuse de passer si une copie a dérivé.

## Mise en place, trois commandes

```bash
# 1. Déposer le paquet
cp -r handoff/design-system ateliermbz/design-system

# 2. Remplacer le script de synchronisation (l'extension .txt tombe ici)
cp handoff/sync-tokens.mjs.txt ateliermbz/scripts/design/sync-tokens.mjs

# 3. Propager
node scripts/design/sync-tokens.mjs
```

Puis brancher le contrôle sur le CI :

```bash
node scripts/design/sync-tokens.mjs --check
```

Il échoue si `frontend/` ou `client-frontend/` a divergé de `design-system/`. Une dérive devient un build rouge au lieu d'un écart silencieux.

## Ce qu'il y a d'autre dans ce dossier

| Fichier | À quoi il sert |
| --- | --- |
| `AGENTS.md` | À poser à la racine du dépôt. Dit à Claude Code ce qui est en lecture seule, et les règles à tenir en écrivant du code. |
| `sync-tokens.mjs.txt` | Remplace le script existant — **à déposer sous le nom `sync-tokens.mjs`**, le suffixe `.txt` n'est là que pour que l'outillage du design system ne tente pas de le compiler. Propage depuis `design-system/`, et `--check` échoue si une copie a dérivé. |
| `AUDIT-ECART.md` | Relecture du front staff contre les fondations : six écarts, classés par rapport bénéfice / effort, avec les remplacements exacts. Une liste de tâches prête à exécuter. |

## Ce que Claude Code ne doit pas faire

Voir `AGENTS.md`, à poser à la racine du dépôt. En un mot : ne jamais éditer un fichier sous `design-system/`, ni les copies générées sous `*/assets/css/tokens.css` et `*/assets/css/paddock-app.css`. Une valeur à changer se change dans le design system, puis se propage.
