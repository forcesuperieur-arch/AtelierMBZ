# Portail client — recréation du code Nuxt

**Ce template est la cible, pas un miroir du code.** Il a été établi en lisant `ateliermbz/client-frontend/` — l'application Nuxt en service — parce que le prototype de refonte n'avait dessiné que le parcours de prise de rendez-vous, et que le portail connecté (tableau de bord, motos, profil, historique) existait déjà dans le code sans jamais avoir été dessiné. Le point de départ vient donc du code ; à partir de maintenant, c'est le template qui fait foi et le code qui s'y aligne.

| Fichier | Ce qu'il porte | Source |
| --- | --- | --- |
| `ClientShell.jsx` | Coquille : nav collante avec sa bande de course, contenu à 960 px, pied légal, bascule de thème, `RdvCard`, `RdvTimeline` | `layouts/default.vue`, `components/RdvCard.vue`, `components/RdvTimeline.vue` |
| `ClientAuth.jsx` | Landing, connexion, mot de passe oublié — hors session, `layout: false` | `pages/landing.vue`, `pages/login.vue`, `pages/forgot-password.vue` |
| `ClientScreens.jsx` | Tableau de bord, mes RDV, historique, mes motos, mon profil | `pages/index.vue`, `pages/rdvs/index.vue`, `pages/historique.vue`, `pages/motos.vue`, `pages/profil.vue` |
| `ClientRdv.jsx` | Détail d'un RDV (frise, travaux supplémentaires, signature, état des lieux, OR, annulation) et prise de RDV en 4 étapes | `pages/rdvs/[id].vue`, `pages/rdvs/new.vue` |
| `data.jsx` | Jeu de démonstration à la forme des réponses `/api/client/*` | — |

## Ce que ces écrans portent

**Le seul moment où le client décide.** Le détail d'un RDV porte les travaux supplémentaires : accepter (signature obligatoire) ou refuser. Sans réponse, l'atelier termine ce qui était convenu. Le bloc passe du jaune d'attente au gris une fois tranché, et la frise gagne une étape.

**Le suivi est un polling de 30 s**, pas du temps réel : Mercure n'est pas exposé au public, décision de sécurité prise dans le code.

**Deux jeux de logos.** Le mot-symbole des fichiers de marque est en blanc cassé (« place on dark background ») : c'est le thème CLAIR qui prend la variante `-light.svg`. La bascule est dans `App.jsx`.

**Le thème** applique `data-theme="dark"` sur la racine du document, comme `useTheme()`.

## Jetons

Ces écrans consomment la couche sémantique (`--surface-*`, `--content-*`, `--accent-content`, `--border-*`, `--success/--warning/--error/--info`), rapatriée dans `tokens/app-semantic.css` depuis `client-frontend/assets/css/tokens.css` — la source de vérité de l'application. Les `--pk-*` restent la couche Paddock ; le fichier `paddock-app.css` du dépôt est une copie de celui de ce design system.

## À cliquer

Landing → « Accéder à mon espace client » → « Se connecter » ouvre le tableau de bord. Mes RDV → une carte ouvre le détail ; « Accepter et signer » ouvre la modale de signature et modifie la frise ; « Demander l'annulation » demande confirmation. « Prendre un rendez-vous » enchaîne les 4 étapes. Mes motos → « + Ajouter une moto » déplie le formulaire. Mon profil → « Supprimer mon compte » demande confirmation.

## Un défaut à corriger dans le code

Le mot-symbole de la barre de nav est le logo **empilé** ramené à 28 × 28 (`components/LogoIcon.vue` : `<img :src="stacked" width="28" height="28" object-fit:contain>`). À cette taille, « PADDOCK » et « MOTO WORKSHOP » se réduisent à deux traits gris illisibles.

Le template le reproduit tel quel pour que l'écart soit visible, mais **la cible est le symbole seul** : `assets/paddock-logo-symbol.svg` (ou le favicon), qui garde sa lisibilité à cette taille. Correctif d'une ligne dans `LogoIcon.vue`.

## Écarts assumés

Pas de photos réelles (emplacements gris), pas d'appels API (données figées dans `data.jsx`), l'autocomplétion marque/modèle de `pages/motos.vue` est représentée par ses champs sans la liste de suggestions.
