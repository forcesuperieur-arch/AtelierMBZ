# Design system Paddock — la couche applicative

Le design system vit dans **Claude Design**, projet « Paddock Design System »
(`8059f4e6-58fa-4b29-a2f6-fd33e6e5e3d4`). Il se lit et se resynchronise avec l'outil
`DesignSync`. Ce document dit ce qu'il contient, ce qui en est déjà repris ici, et ce
qui reste à en tirer.

## Ce qu'il est

Paddock n'invente ni couleur ni typographie : il applique les fondations Motoblouz
(design system « Gazoline »). Ce projet-ci documente la **couche applicative** — les
décisions propres à un logiciel d'atelier : le poste de travail qui ne se quitte pas,
le planning par pont, le thème sombre qui désigne un lieu plutôt qu'une préférence.

| Chemin distant | Contenu |
|---|---|
| `tokens/mb-*.css` | Les 7 fichiers de fondations Motoblouz, verbatim |
| `tokens/paddock-app.css` | **La couche `--pk-*` + le thème `.pk-workshop`** |
| `components/` | 18 composants React, 5 groupes, chacun avec son `.d.ts` et son `.prompt.md` |
| `ui_kits/atelier/` | 20 écrans du front atelier, 1440 × 900, cliquables |
| `ui_kits/client/` | Le front client, 390 × 844, cliquable |
| `guidelines/` | 18 cartes spécimens (couleurs, type, spacing, focus, mouvement, marque) |
| `assets/` | 4 logos Paddock + les logos et pictos Motoblouz |

Les composants sont en **React**, contrainte de l'outillage. Notre cible est Nuxt/Vue :
ils servent de **référence de valeurs et de contrats de props**, pas de code à importer.

## Ce qui est déjà repris dans le dépôt

`tokens/paddock-app.css` → [`frontend/assets/css/paddock-app.css`](../../frontend/assets/css/paddock-app.css)
et sa copie dans `client-frontend/`, importées par les deux `main.css`. **82 déclarations,
52 tokens.** Elles comblent exactement les trous relevés par la TODO de migration :

| Ce qui manquait | Ce que la couche apporte |
|---|---|
| Aucun token d'anneau de focus | `--pk-focus-ring` (noir sur clair, **jaune sur sombre**), `--pk-focus-width`, `--pk-focus-offset` |
| Une soixantaine de durées en dur | `--pk-duration-state` 120 ms · `--pk-duration-panel` 180 ms · `--pk-duration-none` 0 ms · `--pk-easing` |
| Aucune cible tactile déclarée | `--pk-target-desk` 44 px · `--pk-target-workshop` 56 px · `--pk-target-gap` 8 px |
| Aucune géométrie de shell | rail 64 px · item 44 px · en-tête 52 px · panneau **456 px** · nav de domaine 44 px |
| Statuts dispersés | Un **trio** par statut : surface + filet + encre, à employer ensemble |
| Thème sombre approximatif | `.pk-workshop` : `#141414` / `#1c1c1c` / `#1f1f1f`, filets `#2f2f2f`–`#4a4a4a` |
| `prefers-reduced-motion` non traité | Ramène les deux durées à `0.01ms` |

**Ne pas modifier `paddock-app.css` à la main.** Toute évolution se fait dans le projet
Claude Design, puis se resynchronise.

## Ce qui reste à en tirer

1. **Les 18 contrats de composants** (`components/**/*.d.ts`) sont la spécification des
   composants Vue à écrire : `Button`, `FilterPill`, `StatusBadge`, `Callout`, `Field`,
   `IconRail`, `SideNav`, `TopBar`, `PageHeading`, `SidePanel`, `KpiTile`, `StatStrip`,
   `QueueRow`, `QueuePanel`, `BayCard`, `BayControlCard`, `DataTable`, `PlanningGrid`,
   `AppointmentBlock`, `StepBar`, `ServiceCard`, `StatusTimeline`, `Icon`.
2. **Les 20 écrans du kit atelier** donnent les valeurs réelles de chaque surface —
   plus exploitables que les artboards du prototype, qui restent la source visuelle.
3. **Le `.prompt.md` de chaque composant** dit quand l'employer, avec un exemple et ses
   pièges. À lire avant d'écrire le composant Vue correspondant.

## Les règles de contenu, qui ne sont écrites nulle part ailleurs

- **Français.** Vouvoiement côté client, langage d'atelier côté personnel : le front
  atelier ne s'adresse à personne, il nomme des choses (« Travaux prévus », « À réceptionner »).
- **Le vocabulaire est celui du métier.** Pont, OR, forfait, immat, pointé, vendu,
  restitution, créneau. Jamais « item », « ticket », « statut mis à jour ».
- **Une phrase dit une conséquence, pas une catégorie.** « Sans ça, aucun rendez-vous ne
  peut être posé » — et non « champ obligatoire ».
- **Les boutons disent le résultat, en entier.** « Réceptionner et placer sur le pont 2 »,
  « Voir les créneaux · 289 € ». Le `·` sépare l'action de sa donnée.
- **Casse phrase partout.** CAPITALES réservées aux surtitres et aux mots de statut.
- **Nombres à la française.** `6 h 20`, `28 412 km`, `412,50 €`, espace insécable avant
  l'unité, heures d'interface en `08:30`, guillemets « français », apostrophe ’.
- **Aucun emoji, jamais. Aucun point d'exclamation** : l'atelier ne s'enthousiasme pas,
  il annonce.

## Réserves écrites par le design system lui-même

- **Inter** reste chargée depuis Google Fonts : aucun fichier licencié n'a été fourni.
- **Aucune photographie d'atelier** n'existe dans les sources. Les emplacements d'image
  restent gris avec un glyphe — volontairement, on ne les invente pas.
- **Icônes** : le jeu maison Motoblouz (19 glyphes) est prioritaire quand il couvre le
  besoin ; Remix Icon couvre le vocabulaire d'atelier qu'il n'a pas (calendrier, pont,
  moto, facture, clé, sablier).
- **Le logo Paddock est clair sur fond sombre** et n'a pas de variante sombre : à
  demander avant tout usage sur fond clair.
