# Paddock Design System

Paddock est le logiciel d’atelier moto de **Motoblouz** — le leader français de la vente en ligne d’équipement et de pièces pour motards. Deux surfaces : le **front atelier**, utilisé par le personnel (comptoir, mécaniciens, direction), et le **front client**, public, sans compte.

Ce design system est la **couche applicative** de Paddock. Il n’invente ni couleur ni typographie : il applique les fondations Motoblouz (design system « Gazoline ») et documente les décisions propres à un logiciel d’atelier — le poste de travail qui ne se quitte pas, le planning par pont, le thème sombre qui désigne un lieu plutôt qu’une préférence.

## Sources

Tout ce qui est ici est repris d’un bundle de handoff fourni par l’utilisateur (dossier local monté, lecture seule) :

| Source | Ce qui en a été tiré |
| --- | --- |
| `Paddock app redesign-handoff/paddock-app-redesign/project/Paddock Refonte.dc.html` | Le prototype maître : 53 tours de conception, ~14 200 lignes. Toutes les valeurs des composants et des kits UI en viennent. Tours clés : **1** (trois modèles de navigation, le rail d’icônes retenu), **10** (planning + panneaux réception/restitution), **43** (Stat › Explorer), **45** (les sept règles applicatives, le rôle du thème sombre, tactile/focus/mouvement), **50** (front client complet), **53** (landing client). |
| `.../project/_ds/gazoline-design-system-…/tokens/*.css` | Les 7 fichiers de tokens Motoblouz, copiés **verbatim** dans `tokens/mb-*.css`. |
| `ateliermbz/` (dossier local, WSL) | Le dépôt réel : backend Symfony, `frontend/` (staff Nuxt), `client-frontend/` (portail client Nuxt). Le portail client du template en est une recréation écran par écran ; la couche sémantique de `client-frontend/assets/css/tokens.css` est reprise verbatim dans `tokens/app-semantic.css`. |
| `.../project/_ds/motoblouz-design-system-v1-…/fonts/` | Montserrat, 9 graisses × upright/italique, en `.ttf`, copiées dans `fonts/`. |
| `.../project/assets/paddock-logo-*.svg` | Les 4 fichiers de logo Paddock, copiés dans `assets/`. |
| `.../project/refs/*.png` | Captures de l’app **avant** refonte. Non reprises : la refonte les remplace. |
| `uploads/Gazoline - Design System/` (fourni ensuite) | Les sources complètes du design system Motoblouz : 43 primitives + 12 composants métier, 68 cartes, harnais de test, `tokens.json`. On en a repris les **vrais assets** — logos Motoblouz noir/blanc (SVG + PNG), lockup blanc, trait jaune, 7 pictos du site, et le **jeu de 19 glyphes maison** (`primitives/Icon/icon-data.js`) désormais exposé ici comme composant `Icon`. Le reste (les 55 composants e-commerce) n’est pas recopié : il appartient à Gazoline, ce projet ne documente que la couche applicative Paddock. |

Aucun lien Figma, aucun dépôt Git n’a été fourni. Le bundle ne contient **aucun code applicatif** (pas de Vue/Nuxt, pas de React, pas de `package.json`) : ce sont des prototypes HTML/CSS.

## Index

| Chemin | Contenu |
| --- | --- |
| `styles.css` | Point d’entrée global. Imports uniquement. C’est ce que lient les consommateurs. |
| `tokens/mb-*.css` | Fondations Motoblouz : couleurs, typographie, spacing, sizing, radius, motion, interactions. Verbatim. |
| `tokens/paddock-app.css` | Couche applicative Paddock (`--pk-*`) + thème atelier `.pk-workshop`. |
| `fonts/` | Montserrat en local + `@font-face`. Inter reste chargée depuis Google Fonts. |
| `assets/` | 4 logos Paddock. |
| `guidelines/` | 18 cartes spécimens (couleurs, type, spacing, marque). |
| `components/` | 18 composants React, 5 groupes. |
| `templates/` | 8 templates — les points de départ que les projets consommateurs copient. Chacun a son `<Nom>.dc.html`, son `boot.js` et son `README.md`. |
| `templates/atelier/` | **Front atelier** — l'app complète, 1440 × 900, 26 écrans câblés (Stat, planning, réception, restitution, état des lieux, ponts, clients, motos, devis, factures, stock, administration complète, poste mécanicien). |
| `templates/client/` | **Portail client** — recréé depuis le code Nuxt réel (`client-frontend/`) : landing, connexion, tableau de bord, mes RDV, détail avec travaux supplémentaires à signer, prise de RDV en 4 étapes, historique, mes motos, mon profil. |
| `templates/public/` | **Front public sans compte** — landing, prise de RDV en ligne, suivi par lien, écran « prête », mot de passe oublié, mentions, CGV. |
| `templates/cockpit/` | **Cockpit SRC** — l'étage réseau, lecture seule, et l'atelier ouvert depuis le cockpit. |
| `templates/vo/` | **Compagnon VO** — rachat d'une occasion en trois étapes, téléphone en main. |
| `templates/emails/` | **E-mails clients** — devis à signer, facture, rappel de rendez-vous. |
| `templates/documents/` | **Documents A4** — ordre de réparation, état des lieux, facture. |
| `templates/wallboard/` | **Affichage mural** — 1920 × 1080, aucune interaction, rien sous 20 px. |
| `handoff/` | Paquet à déposer dans le dépôt `ateliermbz` : jetons, polices, logos, `AGENTS.md`, script de synchronisation inversé, audit d'écart du front staff. |
| `SKILL.md` | Point d’entrée Agent Skill. |

## Composants

`components/core/` — **Button**, **FilterPill**, **StatusBadge** (+ **Counter**), **Callout**, **Field**
`components/shell/` — **IconRail**, **SideNav**, **TopBar** (+ **SearchField**, **IconAction**), **PageHeading** (+ **PillTabs**, **UnderlineTabs**), **SidePanel** (+ **PanelSection**)
`components/data/` — **KpiTile**, **StatStrip**, **QueueRow**, **QueuePanel**, **BayCard**, **BayControlCard**, **DataTable**
`components/planning/` — **PlanningGrid**, **AppointmentBlock**
`components/client/` — **StepBar**, **ServiceCard** (+ **SlotGrid**), **StatusTimeline**
`components/icons/` — **Icon** (les 19 glyphes maison Motoblouz)
`components/states/` — **EmptyState**, **FilterEmptyState**, **NothingToDo**, **LoadingState**, **ErrorState**, **OfflineBanner**, **PermissionCallout**, **FieldError**

Chaque composant a son `.d.ts` (contrat de props) et son `.prompt.md` (quand l’utiliser, exemple, pièges). Chaque dossier a une carte de démonstration.

**Périmètre.** Le prototype ne définit pas de bibliothèque de primitives : il en consomme une (Gazoline, 55 composants) et invente une couche applicative. Les composants ci-dessus sont donc **exactement les motifs que le prototype répète**, rien de plus. Il n’y a pas de Dialog, de Toast, de Tabs générique ni d’Avatar : ils appartiennent à Gazoline.

**Ajouts assumés.** `Icon` reprend le jeu de glyphes maison de Gazoline (mêmes données, même API `name` / `size`) pour que ce kit ne dépende pas du paquet. `Button` et `Field` recréent une part de l’API Gazoline (`variant` / `tone` / `size` / `shape`) pour que ce kit soit utilisable seul, sans installer `@motoblouz/gazoline`. Dans un vrai projet, importer Gazoline et n’utiliser d’ici que les 16 autres.

## Templates

Huit points de départ sous `templates/<slug>/`. Chacun se compose de son entrée `<Nom>.dc.html`, d'un `boot.js` qui charge le design system et les écrans, et des `.jsx` de ses écrans.

**Une seule ligne à changer** dans un projet consommateur : `const base = '../..'` en tête de `boot.js`, à faire pointer sur l'arbre `_ds/<dossier>` du design system.

**Réglages exposés en Tweaks.** Le template atelier porte : thème clair / sombre (le sombre applique le scope `.pk-workshop`, celui du poste mécanicien), nom et logo de l'atelier, rail déplié ou réduit, densité (échelle de l'app à 0,92 / 1 / 1,06) et écran de démarrage parmi les 19 destinations. Les autres templates exposent ce qui a du sens chez eux : écran de départ, disposition, document ou e-mail affiché.

**Données d'exemple.** Elles restent celles de Paddock — Atelier Principal, Nadia Belkacem, la MT-07, les OR 2418 et 2431. Un template qui démarre vide ne montre pas ce que le produit sait faire.

## Fondamentaux de contenu

**Français, vouvoiement côté client, langage d’atelier côté personnel.** Le front client dit « vous » (« Quelle moto voulez-vous nous confier ? »). Le front atelier ne s’adresse à personne : il nomme des choses (« Travaux prévus », « À réceptionner », « Charge du jour »).

**Le vocabulaire est celui du métier, jamais celui du logiciel.** Pont, OR, forfait, immat, pointé, vendu, restitution, réception, créneau. Pas d’« item », de « ticket », de « statut mis à jour ».

**Une phrase dit une conséquence, pas une catégorie.** C’est la règle qui gouverne toute la copie :

- « Sans ça, aucun rendez-vous ne peut être posé » — et non « champ obligatoire ».
- « À dire au client maintenant : les plaquettes arrivent lundi, la moto ressort ce soir sans ce travail. »
- « Le panneau se referme sur le planning, la case passe en « en cours ». »
- « Libère le pont 1 dans la grille et écrit le nouveau RDV de jeudi. »
- Jamais « une erreur est survenue ». Une erreur nomme ce qui s’est passé, ce que ça empêche, la seule action qui sert.

**Les boutons disent le résultat, en entier.** « Réceptionner et placer sur le pont 2 », « Encaisser, restituer et poser le RDV », « Réserver le mardi 26 à 8 h », « Voir les créneaux · 289 € ». Un libellé long est préférable à un libellé ambigu ; le `·` sépare l’action de sa donnée.

**Casse.** Phrase partout. CAPITALES réservées aux surtitres (11–12 px, 700, 0,08 em) et aux mots de statut (« CRITIQUE », « LIBRE »). Le logo est le seul endroit avec des capitales espacées.

**Nombres et unités à la française.** `6 h 20`, `28 412 km`, `412,50 €`, `68 %`, espace insécable avant l’unité. Les heures d’interface en `08:30`. Guillemets « français », apostrophe typographique ’.

**Aucun emoji, jamais.** L’expressivité vient du poids typographique, du jaune et du noir. Aucun point d’exclamation non plus : l’atelier ne s’enthousiasme pas, il annonce.

**Ce que l’app ne fait pas est écrit.** Facturation et stock vivent ailleurs ; un module coupé quitte la navigation au lieu de devenir un lien mort.

## Fondations visuelles

**Palette.** Noir pur `#000`, blanc `#fff`, et un seul accent : le jaune Motoblouz `#f1ab00`. Les gris portent la structure (`#fbfbfb` surface, `#f6f6f6` page, `#ececec` canvas, `#d4d4d4` bordure, `#6f6e6e` bordure de contrôle et texte discret). L’encre est `#1a1a1a` et non `#000` : sur des écrans denses lus toute la journée, le noir pur fatigue. Le jaune est une couleur « catch » : un seul aplat par région d’écran, jamais en texte sur fond clair (ratio 1,9:1) — pour du texte accent sur clair, `#916700` ou `#7d5600`.

**Statuts par trio.** Chaque statut est une **surface + un filet + une encre**, utilisés ensemble : succès `#eafbe7 / #179500 / #0f5c00`, attention `#fff6e0 / #d96500 / #8a4100`, erreur `#ffecef / #d70321 / #b00219`, info `#eaf4ff / #4a8db7 / #1a4a66`. Le sens ne repose jamais sur la couleur seule : chaque état porte aussi son glyphe et son mot écrit.

**Typographie.** Montserrat partout. Titres en **Medium 500** avec interlignage serré (1,1) et tracking négatif (−0,015 em) ; sous-titres et libellés en **600** ; corps en **400** à 1,5 ; chiffres en **700**. Inter est réservée aux données tabulaires denses (immat, hex, kilométrages). Échelle : 10 · 11 · 12 · 13 · 14 · 15 · 17 · 20 · 22 · 24 · 27 · 28 · 32. L’état sélectionné ne change **jamais** la graisse — cela déplacerait les largeurs.

**Spacing.** Base 4 px. En pratique : 3 px de marge autour d’un bloc de planning, 8 px entre pilules, 10–12 px entre cartes, `14px 16px` de padding pour une tuile ou une ligne, `14px 18px` pour une section de panneau, 20 px de gouttière d’écran. Géométrie fixe : rail 64 px, en-tête 52 px, panneau 456 px, item de rail 44 px.

**Angles.** Le système penche vers l’anguleux. `0` par défaut — les champs du front client et les aplats pleins n’ont aucun rayon. 4 px pour un bloc de planning, 6 px pour une tuile de pont ou une vignette photo, 8 px pour une carte ou un contrôle, 999 px pour une pilule, une action principale, un compteur.

**Cartes et surfaces.** Plates, bordées, **sans aucune ombre portée** : la séparation vient du filet 1 px `#d4d4d4` et du changement de fond. Une carte neutre = `#fbfbfb` + bordure + rayon 8. Une tuile occupée = fond blanc + coiffe jaune 3 px. Une chose vide = pointillé `#6f6e6e` sans fond. Une surface « catchy » = noir plein, rayon 0, accent jaune.

**Fonds.** Aplats unis uniquement. Aucun dégradé, aucune texture, aucun motif, aucune photographie dans l’app. Le front client est du blanc et du noir avec des photos absentes — le bundle n’en fournit aucune, et on ne les invente pas. La photographie lifestyle de motards du système Motoblouz est marketing ; Paddock, lui, n’affiche que les photos prises par l’atelier lui-même (état des lieux), et ce sont des emplacements gris `#ececec` avec un glyphe tant qu’elles ne sont pas là.

**Transparence et flou.** Aucun. Pas de verre dépoli, pas d’overlay flouté. Un panneau est opaque et pousse le contenu ; il ne le voile pas.

**Mouvement.** Trois valeurs, pas quatre. **120 ms** pour les états d’un contrôle (survol, appui, bascule), **180 ms** pour un panneau qui entre par la droite ou une ligne qui se déplie, **0 ms** pour la grille du planning au changement de jour, le tri d’une liste, la saisie d’un chiffre. Courbe `ease`. Rien ne clignote, rien ne rebondit, rien n’attend une animation pour être lisible. `prefers-reduced-motion` ramène tout à 0 sans rien casser.

**Survol.** Un fond gris très léger (`#ececec`) ou l’apparition d’une bordure. Une tuile chiffrée se signale au survol par **sa bordure**, jamais par un soulignement : le chiffre doit rester un chiffre. **Appui.** Assombrissement du fond, aucun changement d’échelle — un bouton qui rétrécit sous un gant ne se sent pas.

**Focus.** Anneau 2 px décalé de 2 px : noir sur clair, jaune sur sombre. Jamais supprimé. À l’ouverture d’un panneau, le focus va au premier champ à remplir, pas au titre. Échap ferme le panneau et rend le focus à la ligne d’où il venait.

**Tactile.** 56 px de cible minimale sur les écrans d’atelier — gants compris — 44 px au bureau. 8 px d’écart minimal entre deux cibles voisines aux effets opposés. Aucune action essentielle derrière un survol, un appui long ou un glissement.

**Le thème sombre a un rôle.** Ce n’est pas une préférence utilisateur : il marque les écrans qui vivent dans l’atelier, debout, sous néon ou en plein jour — pointage au poste, tablette de réception, écran mural du planning. Fond `#141414`, surfaces `#1f1f1f`, filets `#333`/`#4a4a4a`, texte `#f6f6f6`. Le jaune ne bouge pas. Les écrans du bureau (devis, facturation, clients, Stat, réglages — tout ce qui se lit assis et s’imprime) restent clairs. Le poste fixe le défaut ; la bascule manuelle reste disponible partout, mémorisée par appareil.

**Mise en page.** Le rail est fixe et ne se déplie pas au survol : la position des icônes ne bouge jamais. L’en-tête est fixe. Le planning occupe tout l’espace restant et le travail se fait dans un panneau à droite : **le poste de travail ne se quitte pas**. Une page ne défile jamais horizontalement ; une grille dense défile verticalement, en gardant son en-tête.

## Iconographie

**Deux jeux, et une frontière nette.**

**1. Le jeu maison Motoblouz — 19 glyphes, fournis en fichiers.** Repris de `primitives/Icon/icon-data.js` du design system Gazoline (matérialisé depuis le kit Figma « MB — Components ») et exposé ici par le composant `Icon` : `AddLine`, `SubtractFill`, les six flèches (`ArrowUpSLine`, `ArrowDownSLine`, `ArrowLeftSLine`, `ArrowRightSLine`, `ArrowLeftLine`, `ArrowRightLine`), `CheckFill`, `CheckboxBlankCircleFill`, `CloseLine`, `CloseFill`, `SearchLine`, les quatre étoiles (`StarLine`, `StarFill`, `StarHalfLine`, `StarHalfFill`), `ShoppingBasket2Line`, `ShoppingBasket2Fill`. ViewBox 24, tracé en `currentColor`. **Ce jeu est prioritaire dès qu’il couvre le besoin.**

**2. Remix Icon 4.5.0 pour tout le reste**, chargée depuis `https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css`. Le jeu maison est de forme e-commerce : il n’a ni calendrier, ni pont, ni moto, ni facture, ni clé, ni sablier — c’est-à-dire aucun des glyphes dont un logiciel d’atelier a besoin. Le prototype Paddock utilise donc Remix, et le système Motoblouz décrit lui-même son jeu maison comme « de style Remix » : les deux se ressemblent assez pour cohabiter dans un même écran. Ce n’est pas une substitution par défaut d’assets, c’est un complément documenté.

**Les 7 pictos du site** (`assets/picto-*.svg`, `picto-faq.png`) sont des illustrations de réassurance — moto, compte, panier, livraison, paiement, retour, aide — dessinées pour le fond noir. Ce ne sont pas des icônes d’interface : ne pas les utiliser dans une liste ou un bouton.

- **Style ligne** (`-line`) partout. Le plein (`-fill`) est réservé à un état acquis : `ri-checkbox-circle-fill` en vert pour une étape franchie, une moto reconnue.
- L’icône hérite de la couleur du texte (`currentColor`) et n’est jamais la seule porteuse d’un sens.
- Tailles : 13 px dans un bloc de planning, 15–18 px en ligne de liste, 20 px dans le rail, 22–24 px pour une confirmation.
- Le jeu réellement utilisé : `ri-bar-chart-2-line`, `ri-calendar-line`, `ri-calendar-2-line`, `ri-calendar-check-line`, `ri-hourglass-line`, `ri-tools-line`, `ri-hammer-line`, `ri-group-line`, `ri-motorbike-line`, `ri-draft-line`, `ri-bank-card-line`, `ri-archive-line`, `ri-inbox-line`, `ri-key-2-line`, `ri-store-2-line`, `ri-search-line`, `ri-notification-3-line`, `ri-settings-3-line`, `ri-contrast-2-line`, `ri-error-warning-line`, `ri-alert-line`, `ri-information-line`, `ri-lightbulb-line`, `ri-check-line`, `ri-checkbox-circle-fill`, `ri-close-line`, `ri-close-circle-line`, `ri-camera-line`, `ri-image-line`, `ri-qr-code-line`, `ri-pen-nib-line`, `ri-save-line`, `ri-file-excel-2-line`, `ri-import-line`, `ri-map-pin-line`, `ri-phone-line`, `ri-time-line`, `ri-star-line`, `ri-cursor-line`, `ri-arrow-*`.
- **Aucun emoji.** Aucun caractère unicode en guise d’icône, à deux exceptions présentes dans le prototype : `⌘K` dans le champ de recherche et `·` comme séparateur de contenu.

## Logo

Quatre fichiers fournis : `paddock-logo-horizontal.svg` (600 × 140, mot + baseline « MOTO WORKSHOP »), `paddock-logo-stacked.svg` (300 × 300), `paddock-logo-symbol.svg`, `paddock-logo-favicon.svg` (utilisé à 40 px en tête du rail).

La marque est **claire sur fond sombre** : texte `#f2efe8`, tracé `#D4A843`. Elle se pose sur du noir, jamais sur du blanc ni sur du jaune. Aucune variante sombre n’a été fournie pour Paddock — à demander avant tout usage sur fond clair.

**Le logo Motoblouz**, lui, existe dans les deux polarités : `assets/motoblouz-logo-black.svg`/`.png` et `motoblouz-logo-white.svg`/`.png`, plus le lockup blanc et le trait jaune isolé. C’est une marque **distincte** : elle n’est pas une variante du logo Paddock et ne s’y substitue pas. On l’affiche quand le lien à la maison mère doit être dit — « Atelier Motoblouz à Dunkerque » sur le front client, un pied de page, un document imprimé.

## Les sept règles applicatives

Elles viennent du tour 45 et se relisent avant de dessiner un écran nouveau.

1. **Montrer l’effet avant d’enregistrer.** Un réglage qui change le planning ou les prix affiche sa conséquence chiffrée, et refuse de s’appliquer tant que les dossiers touchés n’ont pas de sort.
2. **Rien de rétroactif.** Un devis signé, une facture émise, un OR en cours gardent les valeurs sous lesquelles ils ont été établis. Le client paie ce qu’il a signé.
3. **Un chiffre mène quelque part.** Tout compteur est un lien, et la destination arrive filtrée sur ce que le chiffre disait.
4. **Le poste de travail ne se quitte pas.** Réception, restitution, détail d’un RDV : tout s’ouvre en panneau à droite du planning.
5. **Une erreur nomme la valeur attendue.** Dire ce qui s’est passé, ce que ça empêche, la seule action qui sert — et laisser une issue légitime plutôt que bloquer.
6. **Le temps calibre les forfaits, pas les gens.** L’écart vendu/pointé s’analyse par prestation. Aucun écran de pilotage n’attribue un temps nominativement.
7. **Dire ce que l’app ne fait pas.** Un module coupé quitte la navigation ; il ne devient jamais une entrée grisée ni un lien mort.

## Réserves

- **Polices** : Montserrat est fournie en local, en `.ttf`. Inter reste chargée depuis Google Fonts (aucun fichier licencié fourni) — à remplacer par des `@font-face` locaux si la licence existe.
- **Images** : aucune photographie d’atelier n’était fournie. Les emplacements d’images sont laissés en gris avec un glyphe, volontairement. Les seules photos disponibles dans les sources Gazoline sont des visuels produit (blousons) : ils appartiennent au site e-commerce et n’ont pas de place dans Paddock, donc non repris.
- **Icônes** : le jeu maison (19 glyphes) est intégré et prioritaire ; Remix Icon en CDN couvre le vocabulaire d’atelier que le jeu maison n’a pas. Si Motoblouz produit ces glyphes-là un jour, ils remplacent les `ri-*` correspondants.
- **Écran mécanicien** : le poste de pointage (`templates/atelier` › thème sombre) est reconstruit à partir du spécimen sombre du tour 45 et des règles de pointage ; il est plus succinct que les tours 32 et 47 du prototype.
- **Cible technique** : les composants sont en React (contrainte de l’outillage). Si la cible réelle est Nuxt/Vue, ils servent de référence de valeurs, pas de code à importer.
