# Paddock — règles de design pour les agents

Ce dépôt a un design system **hors dépôt** : le projet Claude Design « Paddock Design System ». Ce fichier dit ce qui vient d'où, et ce qu'un agent qui code ici n'a pas le droit de faire.

## Fichiers en lecture seule

Ne les éditez jamais. Ce sont des copies générées ; toute modification sera écrasée à la prochaine propagation, et signalée avant par le CI.

```
design-system/**                          ← le paquet, tel que produit par le design system
frontend/assets/css/tokens.css            ← copie de design-system/tokens/app-semantic.css
frontend/assets/css/paddock-app.css       ← copie de design-system/tokens/paddock-app.css
client-frontend/assets/css/tokens.css     ← idem
client-frontend/assets/css/paddock-app.css← idem
*/public/fonts/**                         ← copies de design-system/fonts/
*/public/branding/**                      ← copies de design-system/assets/
```

**Une valeur de design se change dans le design system, puis se propage** avec `node scripts/design/sync-tokens.mjs`. Si une valeur manque pour finir une tâche, ne l'inventez pas en dur : signalez-la, elle sera ajoutée à la couche de jetons.

## Le sens de circulation

**Les templates du design system sont la cible ; le code s'y aligne.** Ce n'est pas une documentation de l'existant : `templates/atelier/`, `templates/client/`, `templates/public/`, `templates/cockpit/`, `templates/vo/`, `templates/emails/`, `templates/documents/` et `templates/wallboard/` décrivent l'état visé de chaque surface. Quand un écran du dépôt diverge d'un template, c'est l'écran qu'on corrige.

Deux conséquences pratiques :

- **Avant de dessiner un écran, regardez s'il existe en template.** Il porte les espacements, les libellés, les états vides, les enchaînements. Ne les réinventez pas.
- **Un besoin non couvert par un template se remonte au design system**, il ne se tranche pas dans le code. Le template est mis à jour, puis le code suit.

## Ce qui vous appartient

L'implémentation : composants Vue, pages, composables, stores, middleware, backend, tests. Le design system ne dit pas comment le code est écrit — il dit ce que l'écran doit montrer et comment il doit se comporter.

## Les règles à tenir en écrivant du code

**Jamais de couleur en dur.** Pas de `#fff`, pas de `rgba(255,255,255,.07)`, pas de `#FFD200`. Un jeton sémantique, toujours : `--surface-1`, `--content-2`, `--border-1`, `--accent`, `--error`. Une couleur en dur redevient invisible dès qu'on bascule de thème — c'est le défaut que la refonte a corrigé, ne le réintroduisez pas.

**Trois étages de jetons, à ne pas mélanger.**

| Étage | Exemples | Bascule avec le thème ? |
| --- | --- | --- |
| Marque `--mb-*` | `--mb-accent`, `--mb-grey-700`, `--mb-red-400` | Non, jamais. |
| Sémantique | `--surface-1`, `--content-2`, `--accent`, `--error` | Oui. C'est ce que consomme l'application. |
| Paddock `--pk-*` | `--pk-canvas`, `--pk-rail-width`, `--pk-duration-panel` | Le thème atelier `.pk-workshop` les redéfinit. |

Un composant consomme la couche **sémantique**. Il ne lit `--mb-*` que s'il a besoin d'une couleur de marque qui ne doit pas bouger (un logo, une charte imprimée).

**Le thème sombre n'est pas une préférence.** Il marque les écrans qui vivent dans l'atelier : pointage au poste, tablette de réception, planning mural. Les écrans de bureau — devis, factures, clients, Stat, réglages — restent clairs. La bascule manuelle reste offerte partout et se mémorise par appareil, mais c'est le POSTE qui fixe le défaut.

**Trois durées de mouvement, pas quatre.** `--pk-duration-state` (120 ms) pour un survol, un appui, une bascule. `--pk-duration-panel` (180 ms) pour un panneau qui entre ou une ligne qui se déplie. `--pk-duration-none` (0 ms) pour un changement de jour du planning, un tri, la saisie d'un chiffre. N'en ajoutez pas une quatrième.

**Cibles tactiles.** 44 px minimum sur les écrans de bureau (`--pk-target-desk`), 56 px sur les écrans d'atelier (`--pk-target-workshop`) — les mécaniciens portent des gants. 8 px d'écart minimum entre deux cibles aux effets opposés.

**Icônes.** Le jeu maison Motoblouz (19 glyphes, dans `design-system/`) est prioritaire. Remix Icon 4.5.0 couvre le vocabulaire d'atelier que le jeu maison n'a pas — pont, clé, calendrier, moto. Pas d'emoji dans l'interface : ils restent en fin de pile de polices uniquement pour les DONNÉES SAISIES (le nom d'un client, une note d'atelier peuvent en contenir). Pas de SVG dessiné à la main pour remplacer une icône manquante.

**Anguleux par défaut.** `--pk-radius-block` 4 px pour un bloc de planning, `--pk-radius-tile` 6 px pour une tuile, `--pk-radius-card` 8 px pour une carte ou un contrôle, `--pk-radius-pill` pour une pilule de filtre ou une action principale. Pas de 12 px inventé au passage.

## Ce que l'application doit dire à l'utilisateur

Ces règles-là ne sont pas cosmétiques, elles portent le produit.

**Un refus dit quoi faire et à qui s'adresser.** Jamais « accès refusé » seul : qui peut ouvrir l'accès, depuis quel écran, et le nom de la personne s'il est connu.

**Un écran qui affiche un chiffre dit d'où il vient.** Un total, un délai, un pourcentage porte sa base de calcul — « médiane 1 h 10 », « en heures ouvrées », « hors disque arrière ».

**Rien ne s'enregistre en silence quand ça casse quelque chose.** Fermer un atelier plus tôt, poser une absence, retirer un forfait : l'écran montre ce que ça défait et demande d'en décider avant de laisser enregistrer.

**Un message non abouti n'annule pas l'événement.** Le rendez-vous existe, le client ne le sait pas : l'écran le dit dans ces termes, et donne ce qui débloque.

**Le prix signé est le prix payé.** Aucun devis accepté n'est modifié rétroactivement. Un travail découvert en cours d'intervention se propose, il ne se fait pas.

Le détail — ton, casse, tutoiement, exemples de formulation — est dans `design-system/readme.md`, section « Fondamentaux de contenu ».

## Avant de pousser

```bash
node scripts/design/sync-tokens.mjs --check
```

Il échoue si un fichier en lecture seule a été touché. C'est voulu : reprenez la version du design system, et remontez la valeur manquante plutôt que de la coder en dur.
