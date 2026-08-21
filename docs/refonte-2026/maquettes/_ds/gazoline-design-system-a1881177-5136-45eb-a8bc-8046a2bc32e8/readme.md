# Motoblouz Design System — Documentation

Design system de référence de **Motoblouz**, le leader français de la vente en ligne d'équipement, de vêtements et de pièces pour motards. Signature de marque : **#ENJOY THE RIDE**. L'identité est affirmée, très contrastée et énergique : typographie Montserrat épaisse, jaune sécurité comme accent signature, surfaces noir et blanc pures.

> **À qui s'adresse cette doc** — Elle est mixte : les designers y trouvent les règles d'usage, les tokens et les principes ; les développeurs y trouvent l'API des composants, les noms de tokens CSS et des exemples de code.

---

## Sommaire

1. [Identité & principes de contenu](https://claude.ai/chat/3a7eacb5-1989-41ae-81e3-621c6a72b390#1-identit%C3%A9--principes-de-contenu)

2. [Fondations visuelles](https://claude.ai/chat/3a7eacb5-1989-41ae-81e3-621c6a72b390#2-fondations-visuelles)

   - [Couleurs](https://claude.ai/chat/3a7eacb5-1989-41ae-81e3-621c6a72b390#21-couleurs)

   - [Typographie](https://claude.ai/chat/3a7eacb5-1989-41ae-81e3-621c6a72b390#22-typographie)

   - [Espacement (spacing)](https://claude.ai/chat/3a7eacb5-1989-41ae-81e3-621c6a72b390#23-espacement-spacing)

   - [Dimensionnement (sizing)](https://claude.ai/chat/3a7eacb5-1989-41ae-81e3-621c6a72b390#24-dimensionnement-sizing)

   - [Rayons (radius)](https://claude.ai/chat/3a7eacb5-1989-41ae-81e3-621c6a72b390#25-rayons-radius)

   - [Grille responsive](https://claude.ai/chat/3a7eacb5-1989-41ae-81e3-621c6a72b390#26-grille-responsive)

   - [Mouvement (motion)](https://claude.ai/chat/3a7eacb5-1989-41ae-81e3-621c6a72b390#27-mouvement-motion)

   - [Surfaces, iconographie, motion](https://claude.ai/chat/3a7eacb5-1989-41ae-81e3-621c6a72b390#27-surfaces-iconographie-motion)

3. [Architecture des fichiers](https://claude.ai/chat/3a7eacb5-1989-41ae-81e3-621c6a72b390#3-architecture-des-fichiers)

4. [Conventions transverses](https://claude.ai/chat/3a7eacb5-1989-41ae-81e3-621c6a72b390#4-conventions-transverses)

5. [Bibliothèque de composants](https://claude.ai/chat/3a7eacb5-1989-41ae-81e3-621c6a72b390#5-biblioth%C3%A8que-de-composants)

6. [Cartes spécimens (Guidelines)](https://claude.ai/chat/3a7eacb5-1989-41ae-81e3-621c6a72b390#6-cartes-sp%C3%A9cimens-guidelines)

7. [Réserves & substitutions](https://claude.ai/chat/3a7eacb5-1989-41ae-81e3-621c6a72b390#7-r%C3%A9serves--substitutions)

8. [Note de couverture](https://claude.ai/chat/3a7eacb5-1989-41ae-81e3-621c6a72b390#8-note-de-couverture)

---

## 1. Identité & principes de contenu

Le contenu Motoblouz est direct, entre passionnés, orienté motard. Français d'abord, registre informel (« tu / on ») sur les surfaces marketing, direct et orienté bénéfice sur les pages produit.

| Aspect | Règle |
| --- | --- |
| **Ton** | Énergique, passionné de moto, jamais corporate. La signature « #ENJOY THE RIDE » (en anglais, stylée en hashtag) résume l'esprit. |
| **Casse** | Le logo et les moments d'accroche utilisent des CAPITALES italiques épaisses. Les titres d'interface sont en Montserrat Medium, casse de phrase / Title case. |
| **Emoji** | Ne font pas partie du système. L'expressivité vient du poids typographique et du jaune, pas des emoji. |
| **Voix** | S'adresse au motard (« tu »), célèbre la conduite et l'équipement. |

---

## 2. Fondations visuelles

### 2.1 Couleurs

Le système repose sur le **noir pur (#000)** et le **blanc (#fff)**, avec un unique accent fort — le **jaune Motoblouz #f1ab00 (yellow.500)**. Les gris portent la structure. Rouge / vert / bleu n'existent que comme couleurs de statut sémantiques (erreur / succès / info). Le fort contraste est la règle ; le jaune est utilisé avec parcimonie comme couleur « catch » sur les CTA et les mises en avant.

#### Palette core

**Jaune (accent de marque)**

| Token | Hex |  | Token | Hex |
| --- | --- | --- | --- | --- |
| `--mb-yellow-100` | `#fbe6b3` |  | `--mb-yellow-500` ★ | `#f1ab00` |
| `--mb-yellow-200` | `#f9dd99` |  | `--mb-yellow-600` | `#d99a00` |
| `--mb-yellow-300` | `#f7cd66` |  | `--mb-yellow-700` | `#c18900` |
| `--mb-yellow-400` | `#f4bc33` |  | `--mb-yellow-800` | `#a97800` |
|  |  |  | `--mb-yellow-900` | `#916700` |

**Gris (neutres)**

| Token | Hex |  | Token | Hex |
| --- | --- | --- | --- | --- |
| `--mb-grey-100` | `#fbfbfb` |  | `--mb-grey-600` | `#a5a5a5` |
| `--mb-grey-200` | `#f6f6f6` |  | `--mb-grey-700` | `#6f6e6e` |
| `--mb-grey-300` | `#ececec` |  | `--mb-grey-800` | `#5e5e5e` |
| `--mb-grey-400` | `#d4d4d4` |  | `--mb-grey-900` | `#2f2f2f` |
| `--mb-grey-500` | `#bdbdbd` |  | `--mb-white` / `--mb-black` | `#fff` / `#000` |

**Statuts** — Rouge (erreur), Vert (succès), Bleu (info) déclinés de 050 à 700. Valeurs signatures : erreur `--mb-red-400 #d70321`, succès `--mb-green-400 #179500`, info `--mb-blue-400 #4A8DB7`.

#### Sémantique (thème clair par défaut, `.theme-dark` pour le sombre)

| Rôle | Token | Valeur (clair) |
| --- | --- | --- |
| Fond | `--mb-bg-main` | blanc |
| Conteneur neutre | `--mb-container-neutral` | blanc |
| Conteneur discret | `--mb-container-quiet` | grey-300 |
| Conteneur marque / catch | `--mb-container-brand` / `-catchy` | noir |
| Conteneur accent | `--mb-container-accent` | yellow-500 |
| Conteneur erreur / succès | `--mb-container-error` / `-success` | red-400 / green-400 |
| Texte neutre | `--mb-content-neutral` | noir |
| Texte discret | `--mb-content-quiet` | grey-700 |
| Texte accent | `--mb-content-accent` | yellow-500 |
| Bordure principale | `--mb-border-main` | grey-400 |

> **⚠️ Règles de contraste (accessibilité)** — À respecter impérativement :
>
> - Le jaune `--mb-content-accent` **ne doit jamais servir de couleur de texte sur fond clair** (ratio \~1,9:1). Il est réservé au texte sur fond sombre. Pour du texte accent sur clair, utiliser une nuance assombrie (yellow-800/900).
>
> - Le succès en **texte** sur fond clair doit s'appuyer sur green-500 (green-400 ne passe pas le 4,5:1).
>
> - Les **bordures de contrôle** doivent atteindre un contraste ≥ 3:1 (grey-700 minimum ; grey-400 est insuffisant).
>
> Le détail des correctifs de contraste (dont l'ajout d'une échelle « warning » orange) est suivi dans le document d'audit associé.

### 2.2 Typographie

**Montserrat partout.** Inter est la police alternative pour l'UI dense et les données tabulaires. Line-height serrée sur les titres.

| Usage | Police / poids |
| --- | --- |
| Titres | Montserrat **Medium (500)** |
| Emphase | Montserrat **Bold (700)** |
| Corps de texte & contrôles | Montserrat **Regular (400)** |
| UI dense / tabulaire | Inter |

> **Règle de poids** — Le texte de saisie, les valeurs, les placeholders et les libellés de contrôle sont en **Regular 400**. L'état sélectionné/actif se signale par la couleur, la bordure ou le fond — **jamais** par un changement de graisse (qui déplace la largeur).

#### Échelle de tailles (core)

`11 · 12 · 14 · 16 · 18 · 20 · 22 · 24 · 28 · 32 · 36 · 40 · 56 px` (tokens `--mb-font-size-050` → `--mb-font-size-700`), définis en **rem** (base 16px) pour grandir avec le zoom texte du navigateur (WCAG 1.4.4). Les contrôles à hauteur fixe utilisent `min-height` afin que le texte agrandi ne soit pas rogné.

#### Deux échelles responsives

| Style | Desktop | Mobile |
| --- | --- | --- |
| Display | 56 | 40 |
| Title 1 | 40 | 36 |
| Title 2 | 32 | 28 |
| Title 3 | 28 | 24 |
| Title 4 | 24 | 22 |
| Title 5 | 20 | 20 |
| Title 6 | 18 | 18 |
| Body large | 16 | — |
| Body small | 14 | — |
| Caption | 12 | — |

Classes utilitaires disponibles : `.mb-type-display`, `.mb-type-title-1` … `.mb-type-title-6`, `.mb-type-body-large`, `.mb-type-body-small`, `.mb-type-overline`, `.mb-type-caption`.

> Le 11px (`--mb-font-size-050`) est réservé aux mentions légales, jamais à de l'information utile.

### 2.3 Espacement (spacing)

Échelle core sur base 4px, exposée en tokens numériques **et** en alias sémantiques « t-shirt ». Utilisée pour `gap`, `padding`, `margin`.

| Alias | px |  | Alias | px |
| --- | --- | --- | --- | --- |
| `none` | 0 |  | `2xl` | 24 |
| `4xs` | 1 |  | `3xl` | 32 |
| `3xs` | 2 |  | `4xl` | 40 |
| `2xs` | 4 |  | `5xl` | 48 |
| `xs` | 6 |  | `6xl` | 64 |
| `s` | 8 |  | `7xl` | 80 |
| `m` | 12 |  | `8xl` | 96 |
| `l` | 16 |  | `9xl` | 128 |
| `xl` | 20 |  | `10xl` | 160 |

Tokens : `--mb-spacing-<alias>` (sémantique) et `--mb-space-<n>` (core).

### 2.4 Dimensionnement (sizing)

Même logique que le spacing (icônes, hauteurs de contrôle, avatars). Alias `none` → `9xl`, de 0 à 128px. Le cran **44px** (core `--mb-size-475`) est la cible tactile de référence.

> **⚠️ Divergence à connaître** — Les alias t-shirt de `sizing` et `spacing` ne pointent pas sur les mêmes valeurs (ex. `sizing.m = 16px` mais `spacing.m = 12px`). Vérifier le contexte avant d'utiliser un alias.

### 2.5 Rayons (radius)

Set volontairement restreint : la marque penche vers l'anguleux et l'affirmé plutôt que le doux.

| Sémantique | px | Core |
| --- | --- | --- |
| `--mb-border-radius-none` | 0 | `radius-0` |
| `--mb-border-radius-m` | 8 | `radius-300` |
| `--mb-border-radius-l` | 24 | `radius-700` |
| `--mb-border-radius-full` | 999 | `radius-full` |

Échelle core complète disponible (2, 4, 8, 12, 16, 20, 24, 32px). Un cran intermédiaire à 16px est recommandé pour les cartes (voir doc d'audit).

### 2.6 Grille responsive

| Breakpoint | Colonnes | Gouttière | Marge |
| --- | --- | --- | --- |
| Desktop (≥ 1280px) | 12 | 24px | 32px |
| Tablette (768–1279px) | 8 | 16px | 24px |
| Mobile (< 768px) | 4 | 16px | 16px |

> La grille est une **proposition**, elle ne provient pas de l'export de tokens.

### 2.7 Surfaces, iconographie, motion

**Surfaces & cartes** — Plates, très contrastées. Cartes neutres = blanc + bordure grey-400 ; surfaces « catchy » / marque = noir plein. Pas d'ombres portées lourdes dans le set de tokens : la séparation vient des bordures et des changements de fond.

**Fonds** — Majoritairement blanc ou noir ; grey-200 pour les sections discrètes. La photographie lifestyle plein cadre de motards est centrale (imagerie chaude, orientée action) — fournir de vraies photos par campagne.

**Iconographie** — Set de glyphes maison de style Remix (ligne & fill), rendu via le composant `Icon` (`currentColor`). 17 glyphes fournis. Pas d'emoji.

**Motion & états** — Désormais couverts par une famille de tokens dédiée : voir [2.7 Mouvement](#27-mouvement-motion).

---

### 2.7 Mouvement (motion)

Sixième famille de tokens (`tokens/motion.css`), même modèle de nommage que les autres : `--mb-<famille>-<cran>`.

L'échelle est **dérivée de l'usage réel** relevé dans le design system et dans la fiche produit, pas d'une rampe théorique. Relevé avant travaux : 120ms (dominante, ~14 composants), 140ms (chevron du Select, piste du Toggle), 160ms (SliderIndicator), 200ms (ProgressBar) — et une seule courbe, le mot-clé CSS `ease`.

#### Durées — trois crans

| Token | Valeur | Quand |
| --- | --- | --- |
| `--mb-duration-fast` | 120ms | Retour d'état : wash hover/press, bordure, couleur, focus |
| `--mb-duration-base` | 160ms | Un élément bascule d'état ou se déplace : Toggle, chevron, indicateur |
| `--mb-duration-slow` | 200ms | Parcours d'une distance : progression, apparition de panneau |

#### Courbes — trois crans

| Token | Valeur | Quand |
| --- | --- | --- |
| `--mb-easing-standard` | `ease` | Défaut. Mouvement qui commence et finit à l'écran |
| `--mb-easing-enter` | `cubic-bezier(0, 0, .2, 1)` | Un élément apparaît / se déplie |
| `--mb-easing-exit` | `cubic-bezier(.4, 0, 1, 1)` | Un élément disparaît / se replie |

`--mb-easing-standard` vaut volontairement `ease` : c'est exactement la courbe déjà en place partout, donc **aucun rendu existant ne change**.

#### Raccourcis

`--mb-transition-fast` / `-base` / `-slow` combinent durée + courbe standard :

```css
transition: background var(--mb-transition-fast);
transition: transform var(--mb-duration-base) var(--mb-easing-enter);
```

> **Trois crans, pas plus.** Une durée hors échelle est un bug, pas un nouveau cran. Le cran 140ms relevé à l'audit a été normalisé sur `base` (160ms) plutôt que promu en cran.

#### prefers-reduced-motion — pris en charge par le design system

`tokens/motion.css` porte la media query. Deux couches :

1. les trois durées s'effondrent à `0.01ms` — tout composant et tout consommateur bâti sur les tokens cesse d'animer d'un coup ;
2. un filet de sécurité (`*, *::before, *::after`) neutralise aussi les durées encore écrites en dur chez les consommateurs non migrés. Il ne touche que `animation-duration`, `animation-iteration-count`, `transition-duration` et `scroll-behavior` — ni mise en page, ni couleur.

`0.01ms` et non `0` pour que les événements `transitionend` / `animationend` continuent de se déclencher.

> **Les consommateurs ne redéclarent plus la media query page par page.** Celles déjà en place restent inoffensives (même effet, appliqué deux fois) et peuvent être retirées au fil de l'eau.

---

## 3. Architecture des fichiers

| Fichier | Rôle |
| --- | --- |
| `styles.css` | Point d'entrée global (imports uniquement). C'est ce que les consommateurs lient. |
| `tokens/colors.css` | Échelles core + alias sémantiques clair / `.theme-dark`. |
| `tokens/typography.css` | Montserrat + Inter, échelle de tailles, poids, classes `.mb-type-*`. |
| `tokens/spacing.css` | Échelle core + alias t-shirt. |
| `tokens/sizing.css` | Échelle core + alias sémantiques. |
| `tokens/radius.css` | Rayons core + sémantiques. |
| `tokens/motion.css` | Durées, courbes, raccourcis + prise en charge `prefers-reduced-motion`. |
| `tokens/interactions.css` | États hover / press / focus-visible en CSS, utilitaires `.mb-visually-hidden` et `.mb-skip-link`, animations `Spinner` et `Skeleton`, bascules responsives de `Toast` et `Dialog`. |
| `assets/logo-black.*`, `assets/logo-white.*` | Variantes de logo. |
| `guidelines/*.html` | Cartes spécimens affichées dans l'onglet Design System. |
| `primitives/<nom>/` | **43 composants** génériques, sans logique métier : `Button`, `Input`, `Select`, `SideSheet`… Réutilisables hors e-commerce. |
| `commerce/<nom>/` | **12 composants** métier Motoblouz : `Header`, `Price`, `ProductCard`, `QuantityStepper`, `VariantPicker`, `MotorcycleSelector`, `SizePicker`, `SizePickerGroup`, `SwatchPicker`, `SwatchPickerGroup`, `Sticker`, `StockInfo`. |
| `index.js` / `index.d.ts`, `primitives/index.js`, `commerce/index.js` | Barrels : points d'entrée publics, exports nommés uniquement. |
| `package.json` | Métadonnées du paquet : `exports` (`.`, `./primitives`, `./commerce`, `./styles.css`), `sideEffects: false`, React en `peerDependencies`. |
| `SKILL.md` | Point d'entrée de l'Agent Skill. |

> **`_ds_bundle.js` n'est pas un livrable.** C'est un artefact régénéré depuis les sources par un pipeline séparé. Il n'est ni dans `main`, ni dans `module`, ni dans `exports`, ni dans `files` — le paquet publié sert `index.js` et les sources, jamais le bundle.
>
> **État actuel** — le bundle est **à jour** vis-à-vis des sources courantes. Il reste versionné parce que les 35 cartes spécimens le chargent par chemin relatif. Le risque à surveiller est celui d'un bundle qui décroche des sources : il ne se voit ni au grep, ni au rendu des cartes, ni aux tests, seulement chez le premier consommateur qui installe. Il doit donc être régénéré depuis les sources courantes avant tout tag.
>
> **Contrôle** — la régénération du bundle n'est pas exécutable depuis le dépôt : elle appartient au compilateur de l'outillage Design System, hors de portée d'un script versionné. Le contrôle repose donc sur une **baseline de hash des sources**, `_ds_bundle.sources.sha256` : un SHA-256 par fichier `.jsx`/`.js` de `primitives/` et `commerce/`, plus un hash combiné en tête. Avant tout tag : recalculer, comparer à la baseline. Écart ⇒ le bundle versionné est présumé périmé et doit être régénéré, puis la baseline réécrite dans le même commit. La baseline ne prouve pas que le bundle est juste, seulement qu'il n'a pas décroché depuis la dernière régénération constatée.
>
> **Cible** — sortir le bundle du dépôt et ne le produire qu'en CI. Prérequis : faire charger les cartes autrement qu'en relatif depuis la racine du dépôt.

### Format de distribution — ESM et sources JSX

Le paquet est `"type": "module"` et n'expose **aucune clé `require`** : il ne s'importe qu'en ESM. Un `require('@motoblouz/gazoline')` échoue — c'est assumé, pas un oubli.

Contrainte plus forte, et c'est la contrainte réelle : **le paquet expose du JSX non transpilé.** `index.js` réexporte `primitives/index.js`, qui réexporte des fichiers `.jsx`. Il n'y a pas d'étape de build : servir les sources telles quelles est ce qui rend le DS lisible et éditable en place. Le coût est reporté sur l'outillage du consommateur, qui doit transpiler ce paquet lui-même.

**À vérifier avant d'installer :**

| Contexte | État | Config requise |
| --- | --- | --- |
| Vite | Fonctionne tel quel | Aucune — le pre-bundling esbuild transpile les dépendances. |
| webpack / Next.js | Casse par défaut | Le loader Babel/SWC exclut `node_modules`. Retirer `@motoblouz/gazoline` de l'exclusion (webpack : `exclude: /node_modules\/(?!@motoblouz\/gazoline)/` ; Next.js : `transpilePackages: ['@motoblouz/gazoline']`). Sans ça, erreur de syntaxe sur le premier `<`. |
| Jest | Casse par défaut | `transformIgnorePatterns: ['/node_modules/(?!@motoblouz/gazoline)']` explicite. |
| Node (SSR direct, scripts) | Ne fonctionne pas | Node n'a pas de transformation JSX, quel que soit le format de module. Passer par le bundler du framework. |

Ajouter un double format CJS imposerait une étape de build et un artefact compilé de plus dans un dépôt qui n'en a aucun. Le jour où un consommateur CJS apparaît, ajouter une clé `require` pointant sur un build dédié — pas sur `index.js`.

**La distribution ne passe pas par npm à ce stade.** Le paquet est `"private": true` et `UNLICENSED` : aucun registre n'est câblé, et le flag protège d'un `npm publish` accidentel vers le registre public. `npm pack` reste utilisable pour inspecter le tarball. Le branchement d'un registre interne consistera à ajouter `publishConfig.registry` et retirer `private` **dans le même commit** — jamais l'un sans l'autre.

**Critère de découpage : générique / métier, pas atomique / composite.** La granularité ne dit rien : `primitives/` contient des composants qui en importent d'autres (13 importent `Icon`) et `commerce/` contient des composants sans aucune dépendance interne. Le clivage est l'appartenance au domaine e-commerce.

> **Le test.** *Le composant garderait-il un sens dans un produit qui ne vend rien ?* Oui → `primitives/`. Non → `commerce/`.
>
> C'est ce test qui a déplacé `SizePicker`, `SizePickerGroup`, `SwatchPicker`, `SwatchPickerGroup`, `Sticker` et `StockInfo` en v3.0.0 : une tuile de taille, une pastille de coloris, un badge produit et un niveau de stock ne veulent rien dire hors catalogue. `StarRating` reste en `primitives/` : on note aussi bien un article qu'un film ou un trajet.
>
> Répartition : **43 primitives, 12 composants métier.**

Les dépendances vont dans un seul sens : `commerce/` importe `primitives/`, jamais l'inverse. Aucune primitive n'importe un composant de `commerce/`.

> **Collision avec les globales du navigateur** — `Text` et `Image` portent les mêmes noms que l'interface DOM `Text` et le constructeur `Image`. Dans un module ES la déstructuration est cadrée au module : aucun effet. Dans un **script classique**, en revanche, un `const { Text, Image } = …` au premier niveau masque la globale **pour toute la page**, et tout code tiers appelant `new Image()` casse. Déstructurer dans une portée de fonction, ou aliaser. Les 68 cartes spécimens confinent leur script pour cette raison.

### Importer le design system

```js
import { Button, ProductCard } from '@motoblouz/gazoline';
import { Button } from '@motoblouz/gazoline/primitives';   // sous-chemin
import { ProductCard } from '@motoblouz/gazoline/commerce';
```

```html
<link rel="stylesheet" href="@motoblouz/gazoline/styles.css">
```

**Source de vérité des tokens** : `uploads/tokens.json` (export Tokens Studio, core + sémantique clair/sombre).

---

## 4. Conventions transverses

Ces règles s'appliquent à **tous** les composants et priment sur les détails individuels.

### Nommage des props

- **`type`** = attribut HTML natif (`button` / `submit` / `reset`, etc.).

- **`variant`** = axe de style visuel (ex. `primary` / `secondary` / `tertiary`).

### Versionnage

**Semver.** **Majeur** : renommage ou suppression d'un composant, d'une prop, d'un token ou d'un chemin d'import ; changement de défaut modifiant le rendu. **Mineur** : ajout de composant, de prop ou de token, purement additif. **Correctif** : correction de bug sans changement d'API.

L'absence de publication en registre ne dispense pas du majeur : les consommateurs importent par chemin, leur code casse de la même façon.

### Nommage des composants

- **`Picker` vs `Selector`** — `Picker` = contrôle qui choisit une valeur sur un seul attribut (`SizePicker`, `SwatchPicker`, `VariantPicker`). `Selector` = widget qui orchestre plusieurs étapes ou contrôles pour produire une sélection composée (`MotorcycleSelector`).

### Refs

Les primitives interactives forwardent une ref vers **l'élément focusable**, pas vers la racine : `Input`, `Search`, `Checkbox`, `RadioButton` et `Toggle` la posent sur l'`<input>` interne (c'est lui qui porte `focus()`, `value` et la validation native, pas le `<label>`) ; `Select` sur le `<button>` déclencheur ; `Button`, `IconButton`, `Link`, `SizePicker`, `SwatchPicker` et `Chip` sur leur racine, qui est déjà l'élément focusable.

> **Exception — `RangeSlider`.** Seul composant du kit dont la ref n'est **pas un élément** mais un handle impératif : deux poignées, aucun nœud focusable unique. `ref.current` expose `node` (le nœud racine, à utiliser pour `getBoundingClientRect` ou pour ancrer un positionneur type floating-ui), `focusFrom()`, `focusTo()` et `getThumb('from' | 'to')`. Passer `ref` directement à un positionneur ne marchera pas : il faut `ref.current.node`.

> **`Chip` en mode statique / dismissible.** La racine n'est pas focusable dans ces modes : `ref.current.focus()` est un no-op silencieux. Le nœud focusable y est la croix interne, que la ref n'atteint pas.

### Accessibilité (socle commun)

- **Focus visible** — Tout élément focusable expose un anneau de focus (`--mb-focus-ring`) via `:focus-visible`. Pour les contrôles dont l'input natif est masqué (Checkbox, RadioButton, Toggle), le focus est reporté sur l'élément **visible**.

- **Cibles tactiles** — Zone cliquable de **44px minimum** pour tout contrôle destiné au tactile.

- **États** — hover/press gérés en CSS (`:hover` / `:active`), `disabled` via tokens dédiés (pas d'opacité globale qui délave le texte).

- **L'information ne repose jamais sur la seule couleur** — toujours un second signal (icône, forme, texte, position).

- **Clavier** — Les widgets composites (Select, RangeSlider, groupes de sélection, StarRating cliquable) sont pleinement opérables au clavier (flèches, Entrée, Espace, Échap, Home/Fin).

### Contrôlé / non contrôlé

La plupart des composants d'entrée supportent les deux modes : `value` + `onChange` (contrôlé) ou `defaultValue` (non contrôlé).

---

## 5. Bibliothèque de composants

Composants recréés depuis le kit Figma « MB — Components » : `primitives/` (génériques) et `commerce/` (métier). Chacune est paramétrée sur les axes de variantes exacts du Figma.

### Actions

#### Button

Bouton d'action complet.

| Prop | Type | Défaut | Description |
| --- | --- | --- | --- |
| `variant` | `primary` \| `secondary` \| `tertiary` | `primary` | Poids visuel (plein / contour / texte). |
| `tone` | `neutral` \| `accent` \| `error` | `neutral` | Famille de couleur (noir / jaune / rouge). |
| `size` | `small` \| `medium` \| `large` | `medium` | Hauteur 41 / 52 / 60px. |
| `shape` | `rounded` \| `square` | `rounded` | Pilule (999px) ou 8px. |
| `type` | `button` \| `submit` \| `reset` | `button` | Type HTML natif. |
| `fullWidth` | `boolean` | `false` | S'étire sur toute la largeur. |
| `disabled` | `boolean` | `false` |  |
| `startIcon` / `endIcon` | `ReactNode` |  | Icône avant / après. |

```
<Button variant="primary" tone="accent" size="large">Ajouter au panier</Button>
<Button variant="secondary" tone="neutral">Continuer</Button>
<Button variant="tertiary" endIcon={<Icon name="ArrowRightLine" />}>Voir plus</Button>

```

> Un bouton icône seule (sans texte) doit fournir un `aria-label`.

#### IconButton

Bouton icône seule, même système `variant` / `tone` / états. Tailles 64 / 52 / 41px, `rounded` ou `square`. `aria-label` **obligatoire**.

#### Link

Lien texte inline souligné. `tone` `neutral` / `accent`, `size` `large` (14px) / `small` (12px). Le tone accent utilise une nuance assombrie pour rester lisible.

### Typographie & layout

#### Text

L'atome typographique, et le seul composant qui consomme les classes composites `.mb-type-*` au lieu de réassembler famille, graisse, taille et interligne à la main. C'est par lui que l'échelle responsive Desktop / Mobile du §2.2 atteint enfin l'interface.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `as` | élément | `p` | Élément rendu. À choisir pour le plan du document, indépendamment de `variant`. |
| `variant` | `display` \| `title-1`…`title-6` \| `body-large` \| `body-large-bold` \| `body-small` \| `body-small-bold` \| `overline` \| `caption` \| `caption-bold` | `body-large` | Style typographique sur l'échelle `.mb-type-*`. |
| `tone` | `inherit` \| `neutral` \| `quiet` \| `accent` \| `error` \| `success` \| `warning` \| `disabled` \| `on-brand` \| `on-catchy` \| `on-accent` | `neutral` | Couleur sémantique. |
| `weight` | `regular` \| `medium` \| `semi-bold` \| `bold` | — | Surcharge de graisse. À utiliser rarement : la variante porte déjà la bonne. |
| `align` | `textAlign` | — |  |
| `truncate` | `boolean` \| `number` | `false` | `true` = une ligne avec ellipse. Un nombre = coupe à ce nombre de lignes. |

`tone="accent"` résout sur `--mb-content-accent-strong` (yellow-900, 5,07:1) et **jamais** sur le jaune vif, qui échoue AA en texte sur fond clair. Le composant rend donc impossible la faute que la règle §2.1 décrit.

```jsx
<Text as="h1" variant="title-1">Équipement moto</Text>
<Text variant="body-small" tone="quiet">Livraison offerte dès 80 €</Text>
```

> **Quand ne pas l'utiliser** — Pour du texte qui appartient déjà à un composant (libellé de `Button`, titre de `Dialog`, message d'`Alert`) : ces composants portent leur propre typographie. `Text` sert au contenu, pas à l'habillage des contrôles.

#### Stack

Layout unidimensionnel. Son seul rôle est de faire venir l'espacement de l'échelle au lieu d'une marge ad hoc sur chaque enfant.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `as` | élément | `div` |  |
| `direction` | `vertical` \| `horizontal` | `vertical` |  |
| `gap` | alias t-shirt de spacing | `m` (12px) | Espace entre enfants. Aucune échappatoire en pixels. |
| `align` / `justify` | `alignItems` / `justifyContent` | — |  |
| `wrap` | `boolean` | `false` |  |
| `inline` | `boolean` | `false` | `inline-flex`, pour une pile dans un flux de texte. |

> **Quand ne pas l'utiliser** — Pour une grille : c'est `Grid`. Pour un seul espace entre deux blocs qui ne sont pas frères dans la même pile : c'est `Spacer`.

#### Grid

Layout bidimensionnel. Le mode par défaut est intrinsèquement responsive : `minItemWidth` pilote une liste de pistes `auto-fill`, donc le nombre de colonnes suit le conteneur sans media query ni mesure en JavaScript.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `columns` | `number` | — | Nombre fixe de colonnes. Laisser vide pour le mode responsive. |
| `minItemWidth` | longueur CSS | `16rem` | Largeur mini de piste en mode `auto-fill`. Préférer `rem`, qui suit le zoom texte. |
| `gap` / `rowGap` | alias t-shirt de spacing | `l` (16px) |  |
| `align` / `justify` | `alignItems` / `justifyItems` | — |  |

> **Quand ne pas l'utiliser** — Pour une seule rangée ou une seule colonne : `Stack` dit mieux l'intention. Pour la grille de page (12 / 8 / 4 colonnes du §2.6) : c'est une grille de gabarit, pas de composant.

#### Spacer

Espace explicite, pour les deux cas que `gap` ne couvre pas : pousser des frères aux extrémités d'une rangée flex (`grow`), ou imposer un cran entre deux blocs qui ne sont pas enfants de la même pile. Toujours `aria-hidden`.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `size` | alias t-shirt de spacing | `m` | Cran d'espacement. |
| `axis` | `vertical` \| `horizontal` | `vertical` |  |
| `grow` | `boolean` | `false` | Absorbe l'espace libre du parent flex, avec `size` comme plancher. |

> **Quand ne pas l'utiliser** — Entre les enfants d'un `Stack` ou d'un `Grid` : le `gap` le fait déjà, et un `Spacer` intercalé fausse le comptage des enfants. Un `Spacer` qui apparaît en série est le signe qu'il manque un `Stack`.

#### Image

Ratio maîtrisé, chargement différé, image de repli. Les trois choses qu'un `<img>` nu rate dans un catalogue : il n'a pas de hauteur avant d'être chargé, donc la grille saute ; il charge même trois écrans sous la ligne de flottaison ; et une source en 404 laisse le glyphe d'image brisée du navigateur au milieu de la page.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `src` | `string` | — |  |
| `alt` | `string` | — | **Obligatoire.** `alt=""` est une valeur légitime — décoratif — mais elle doit être écrite, pas oubliée. |
| `ratio` | `square` \| `portrait` \| `landscape` \| `wide` \| valeur CSS | `square` | Réserve la boîte avant chargement. |
| `fit` | `objectFit` | `cover` |  |
| `loading` | `lazy` \| `eager` | `lazy` | `eager` pour la seule image au-dessus de la ligne de flottaison. |
| `fallback` | `ReactNode` | — | Remplace le placeholder par défaut. |
| `fallbackLabel` | `string` | `Image indisponible` | Ignoré si `alt` est non vide — l'`alt` le dit mieux. |
| `radius` | `none` \| `m` \| `l` \| `full` | `none` |  |
| `background` | `quiet` \| `neutral` \| `none` | `quiet` | Couleur de la boîte réservée pendant le chargement. |

> **Quand ne pas l'utiliser** — Pour une icône : c'est `Icon`, qui peint en `currentColor`. Pour un fond décoratif plein cadre : `background-image` en CSS, qui n'a pas besoin d'entrer dans l'arbre d'accessibilité.

#### Card

Conteneur bordé génerique : trois emplacements, aucun domaine. Distinct de `ProductCard`, qui est la tuile produit métier et possède sa propre anatomie.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `variant` | `outlined` \| `elevated` \| `plain` | `outlined` | La bordure est le dispositif de séparation de la marque (§2.7) ; `elevated` pour la surface rare qui doit se détacher. |
| `tone` | `neutral` \| `quiet` \| `catchy` | `neutral` | `catchy` est la surface noire et inverse l'encre et les filets. |
| `padding` | `none` \| `small` \| `medium` \| `large` | `medium` | `none` pour un contenu bord à bord (un hero `Image`). |
| `radius` | `none` \| `m` \| `l` | `m` |  |
| `header` / `footer` | `ReactNode` | — | Emplacements séparés par un filet. |
| `interactive` | `boolean` | `false` | Rend la carte entière activable, **en `<button>`**. |
| `disabled` | `boolean` | `false` | N'a de sens qu'avec `interactive`. |

`interactive` rend un vrai `<button>` : opérable au clavier et annoncé comme un contrôle unique. Un `<div>` muni d'un `onClick` serait invisible au clavier.

> **Quand ne pas l'utiliser** — Pour un produit : `ProductCard`, qui encode le prix, le stock et les déclinaisons. Comme simple boîte à padding : `Stack` suffit, sans bordure ni sémantique. Et une carte `interactive` qui contient d'autres boutons est invalide — un bouton ne peut pas en contenir un autre ; laisser `interactive` à `false` et rendre les actions individuellement.

#### Carousel

Le conteneur défilant que `SliderIndicator` indiquait déjà. Jusqu'ici le kit livrait l'indicateur sans la chose indiquée, et chaque gabarit refaisait son défileur.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `label` | `string` | — | Nom accessible, masqué à l'écran. Dire ce qu'il contient. |
| `slidesToShow` | `number` | `1` |  |
| `peek` | `number` | `0.2` | Fraction de la vignette suivante laissée visible. `0` aligne la piste pile au bord. |
| `gap` | alias t-shirt de spacing | `l` |  |
| `indicator` | `dots` \| `bar` \| `none` | `dots` |  |
| `showArrows` | `boolean` | `true` | Commodité de pointeur : la piste défile toujours au geste, au clavier et par l'indicateur. Masquées sous 768px quoi qu'il arrive. |
| `autoPlay` | `boolean` | `false` | Pause au survol, au focus interne et onglet en arrière-plan ; désactivée sous `prefers-reduced-motion`. |
| `interval` | `number` | `5000` |  |

**Peek** — par défaut 20 % de la vignette suivante restent visibles. Une piste qui s'arrête pile au bord du conteneur se lit comme un bloc terminé, et ce qui suit n'est jamais cherché ; une vignette coupée est l'affordance qui dit « ça défile ». C'est aussi ce qui rend les flèches facultatives au lieu d'être le seul indice — d'où `showArrows={false}` comme configuration de plein droit.

Construit sur le défilement natif et `scroll-snap` plutôt que sur une piste transformée : le geste tactile, l'inertie et le défilement clavier viennent de la plateforme. La piste est `tabIndex={0}` — une zone défilante non focusable est une impasse au clavier, et une violation axe. La préférence de mouvement est lue par **écouteur**, pas une fois au rendu : une valeur lue au montage ne voit jamais l'utilisateur changer son réglage.

> **Quand ne pas l'utiliser** — Pour du contenu que l'utilisateur doit voir : ce qui est hors écran dans un carrousel n'est presque jamais consulté ; une grille montre tout. Pour de la navigation : c'est `Tabs`. Et `autoPlay` sur du contenu porteur de sens déplace le texte sous les yeux de qui lit lentement — le réserver aux mises en avant.

### Saisie & sélection

#### Select (widget unifié)

Composant de sélection unifié fusionnant l'ancien trigger, la liste (popover) et la logique d'ouverture/sélection. Deux axes **indépendants** qui se combinent.

| Prop | Type | Défaut | Description |
| --- | --- | --- | --- |
| `options` | `{ value, label, disabled? }[]` | `[]` | Options. |
| `selectionMode` | `single` \| `multiple` | `single` | Choix unique ou multiple. |
| `value` / `onChange` | `string` \| `string[]` |  | Type selon le mode. |
| `searchable` | `boolean` | `false` | Active le mode combobox (filtrage). |
| `searchPlaceholder` | `string` |  | Placeholder du champ de recherche. |
| `size` | `small` \| `medium` \| `large` |  |  |
| `shape` | `rounded` \| `square` |  |  |
| `invalid` / `disabled` | `boolean` |  |  |

- **single** : sélectionner ferme le popover, coche « check » sur l'option choisie.

- **multiple** : toggle sans fermer, `aria-multiselectable`, cases à cocher dans les options ; le déclencheur affiche un **compteur** (« 3 sélectionnés »).

- **searchable=false** : pattern *listbox* + **typeahead** clavier (taper « F » surligne France, re-taper cycle).

- **searchable=true** : pattern *combobox* (`role="combobox"`, `aria-activedescendant`, `aria-autocomplete="list"`) + filtrage en direct (insensible casse/accents), message « Aucun résultat », champ en haut du popover.

```
<Select options={pays} value={v} onChange={setV} searchable />
<Select options={marques} selectionMode="multiple" value={sel} onChange={setSel} />

```

#### Search

Champ texte avec glyphe de recherche + bouton d'effacement. 3 tailles (échelle de saisie 16 / 16 / 14), `rounded` / `square`, variantes `default` / `filled` / `secondary`. Saisie et placeholder en Montserrat 400.

#### Input

Champ de saisie texte simple avec **label autonome** (jamais remplacé par le placeholder), `helpText` et `error` optionnels sous le champ. États : normal / focus (anneau clavier `:focus-visible` uniquement) / rempli / désactivé / erreur — tous rattachés aux tokens (`--mb-container-*`, `--mb-content-*`, `--mb-border-*`). Occupe 100 % de son conteneur ; `min-height` 48px (cible ≥44px) qui grandit au zoom texte. Label lié via `htmlFor`, `helpText`/`error` liés via `aria-describedby`, `aria-invalid` en erreur, erreur signalée par **icône + texte** (pas la couleur seule).

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `label` | `ReactNode` | | Label affiché au-dessus du champ. |
| `value` / `onChange` | `string` | | Mode contrôlé. |
| `defaultValue` | `string` | `''` | Mode non contrôlé. |
| `placeholder` | `string` | | Texte atténué dans le champ vide. |
| `helpText` | `ReactNode` | | Texte d'aide (masqué en erreur). |
| `error` | `ReactNode` | | Message d'erreur → état erreur. |
| `disabled` | `boolean` | `false` | |
| `required` | `boolean` | `false` | Ajoute l'astérisque. |

```
<Input label="E-mail" placeholder="vous@exemple.fr" helpText="Nous ne partagerons jamais votre e-mail." />
<Input label="Code postal" value={cp} onChange={e => setCp(e.target.value)} error="5 chiffres attendus." />
```

#### FormField

Le trio label / texte d'aide / message d'erreur, extrait d'`Input`. Il y existait depuis la v1, mais en privé : `Select`, `Textarea`, `SizePickerGroup` et `SwatchPickerGroup` n'avaient aucun moyen d'exprimer une erreur, donc **une sélection obligatoire non renseignée ne pouvait pas être signalée**.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `label` | `ReactNode` | — |  |
| `helpText` | `ReactNode` | — | Masqué pendant l'erreur : les deux ne s'empilent jamais. |
| `error` | `ReactNode` | — | Pose `aria-invalid` sur le contrôle et rend icône + texte. |
| `required` / `disabled` | `boolean` | `false` |  |
| `labelFor` | `string` | — | Id du contrôle quand il possède le sien. |
| `as` | élément | `div` | `fieldset` pour un groupe. |
| `labelAs` | `label` \| `legend` \| `span` | `label` | `legend` pour un groupe : un `<label>` ne peut pas nommer un `radiogroup`. |
| `children` | `ReactNode` \| `(control) => ReactNode` | — | Une fonction reçoit les attributs calculés du contrôle. |

Le câblage est tout l'intérêt, et se rate facilement à la main : `htmlFor` vers le contrôle, aide **et** erreur collectées dans `aria-describedby`, `aria-invalid` sur le **contrôle** et non sur le conteneur. `FormField` les passe à son enfant par fonction pour qu'il ne puisse pas être câblé à moitié.

> **Quand ne pas l'utiliser** — Autour d'un `Input`, qui porte déjà son propre trio ; l'envelopper produirait deux labels. Autour d'un `Checkbox` isolé, dont le libellé est son propre label — mais un **groupe** de cases en a besoin.

#### Textarea

Saisie multiligne. Reprend exactement l'habillage de champ d'`Input` (mêmes tokens de bordure, même classe `.mb-field`, même anneau de focus clavier) pour que les deux ne divergent pas, mais ne porte aucun label : cela appartient à `FormField`.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `value` / `onChange` | `string` | — | Mode contrôlé. |
| `defaultValue` | `string` | `''` | Mode non contrôlé. |
| `rows` | `number` | `4` |  |
| `maxLength` | `number` | — |  |
| `showCount` | `boolean` | `false` | Compteur de caractères. Exige `maxLength`. |
| `resize` | `resize` | `vertical` |  |
| `invalid` / `disabled` | `boolean` | `false` |  |

Le compteur visible est `aria-hidden` ; une région live polie n'annonce le décompte qu'à l'approche de la limite. Annoncer chaque frappe rendrait le champ inutilisable au lecteur d'écran.

> **Quand ne pas l'utiliser** — Pour une seule ligne : `Input`. Pour du texte riche : ce serait un éditeur, hors périmètre du design system.

#### Checkbox

Case 24px : `checked` / `indeterminate` / `invalid`, 4 états, label optionnel (Montserrat 16px). Expose `aria-invalid` en erreur.

#### RadioButton

Cercle 24px : `checked` / `invalid`, 4 états, label optionnel. À envelopper dans un `role="radiogroup"` avec un `name` partagé.

#### Toggle

Interrupteur 52×32 : on (noir) / off (grey), poignée blanche, 4 états. `role="switch"`. **Label ou `aria-label` obligatoire.**

#### RangeSlider

Range à deux poignées, remplissage noir + poignées 16px, libellés From/To. Opérable au clavier (flèches ±step, Home/Fin, PageUp/Down) ; bornes ARIA par poignée ; `aria-label` par poignée.

#### Chip

Pilule dismissible (grey-300), survol/press. Croix d'effacement dans une cible de 44px.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `wrap` | `boolean` | `false` | Autorise le libellé à passer sur plusieurs lignes. |

Par défaut le libellé reste sur **une seule ligne** (`white-space: nowrap`) — comportement historique, inchangé. Sur un site multilingue (neuf locales), un libellé de filtre traduit peut dépasser : passer `wrap` fait revenir le texte à la ligne, avec `overflow-wrap: anywhere` pour les mots composés longs (allemand, néerlandais).

```jsx
<Chip wrap selectable selected={on} onSelect={toggle} style={{ maxWidth: 180 }}>
  Wasserdichte Motorradhandschuhe
</Chip>
```

> `wrap` n'a d'effet que si la largeur du chip est bornée (`max-width`, cellule de grille, colonne de filtres). Sans contrainte, un chip s'étire et ne se coupe jamais.
>
> **Géométrie & cible tactile** — Le contenu reste centré verticalement (`align-items: center`) et la croix d'effacement garde sa cible de 44px. Un chip qui revient à la ligne dépasse toujours les 44px requis : ~58px à deux lignes, ~74px à trois. Le cas limite reste le chip **monoligne**, à 41px — inchangé par cette évolution, mais sous la cible.
>
> **État sélectionné** — Fond `--mb-container-catchy` et texte `--mb-content-on-catchy-neutral` s'appliquent au bloc entier : le rendu multiligne est correct, sans rupture de fond entre les lignes.

### Affichage & feedback

#### StarRating

5 étoiles + note & nombre d'avis, tailles medium / large. En mode `clickable`, se comporte comme un **radiogroup** accessible (étoiles entières) ; en affichage, un `aria-label` de synthèse résume la note.

#### ProgressBar

Piste 4px, remplissage `neutral` (noir) / `accent` (jaune). `aria-label` requis pour donner le contexte.

#### Divider

Filet 1px, horizontal / vertical. Prop `decorative` (défaut true) → `aria-hidden` si décoratif, sinon `role="separator"`.

#### Spinner

Chargement indéterminé. `ProgressBar` ne couvre que le déterminé ; `Spinner` couvre « il se passe quelque chose et on ne sait pas combien de temps ».

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `size` | `small` \| `medium` \| `large` | `medium` | 16 / 24 / 40px. |
| `tone` | `neutral` \| `accent` \| `inverse` | `neutral` | `inverse` sur surface sombre. |
| `label` | `string` | `Chargement…` | Annoncé via `role="status"`. Dire **ce qui** charge. |

**Mouvement réduit** — la rotation est ré-autorisée sous `prefers-reduced-motion`, à cadence ralentie. C'est la seule animation que le design system conserve sous cette préférence : un indicateur de chargement figé se lit comme une interface plantée, et une rotation pure sans translation n'est pas ce que la préférence vise. C'est l'échappatoire prévue par le filet global (CHANGELOG v1.1 B2), pas une exception silencieuse.

> **Quand ne pas l'utiliser** — Quand la durée est connue : `ProgressBar`. Pour un chargement de contenu dont la forme finale est prévisible : `Skeleton`, qui réserve la géométrie au lieu de la faire sauter.

#### Skeleton

Placeholder de contenu en cours de chargement. Réserve la géométrie finale pour que la page ne saute pas à l'arrivée des données.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `variant` | `text` \| `rect` \| `circle` | `text` | Ligne de texte / bloc-image / avatar. |
| `width` / `height` | longueur CSS | 100% | Le cercle vaut 40px par défaut. |
| `lines` | `number` | `1` | Nombre de lignes ; la dernière est courte, comme du texte réel. |
| `radius` | `none` \| `m` \| `l` \| `full` | selon la variante |  |

Le squelette est décoratif (`aria-hidden`). **L'attente s'annonce sur le conteneur** qui la possède, via `aria-busy="true"` : annoncer quarante placeholders serait du bruit.

> **Quand ne pas l'utiliser** — Quand la forme du contenu à venir est inconnue : un squelette qui ne ressemble pas au résultat désoriente plus qu'il ne rassure. Quand l'attente est brève (moins d'une demi-seconde) : ne rien montrer vaut mieux qu'un clignotement.

#### Alert

Message système persistant. Distinct de `CommercialBanner`, qui est marketing, et de `Toast`, qui est transitoire : une `Alert` reste jusqu'à ce que la condition qui l'a provoquée soit résolue.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `tone` | `info` \| `success` \| `warning` \| `error` | `info` | Intention sémantique. |
| `title` | `ReactNode` | — | Accroche courte, dans la couleur de tonalité. |
| `children` | `ReactNode` | — | Corps, toujours en `--mb-content-neutral`. |
| `action` | `ReactNode` | — | Une action sous le texte. |
| `onDismiss` | `() => void` | — | Fourni, affiche la croix. Absent, le message n'est pas effaçable. |

Surface blanche, bordure 1px dans la couleur de tonalité, icône et titre colorés. Pas de fond teinté : la palette n'a pas de teinte claire pour `warning` (l'orange n'a ni cran 050 ni 100), et la surface plate bordée est le dispositif de séparation propre à la marque (§2.7). Le sens ne repose jamais sur la couleur seule — chaque tonalité porte son glyphe et un préfixe textuel lu par les technologies d'assistance. `error` rend `role="alert"` (il interrompt) ; les autres `role="status"`.

> **Quand ne pas l'utiliser** — Pour une erreur qui appartient à un champ : le trio `label` / `helpText` / `error` d'`Input` la place au bon endroit. Pour une confirmation fugace : `Toast`. Pour une promotion : `CommercialBanner`.

#### Toast

Message transitoire. `Toast` dit « c'est fait » et s'en va ; `Alert` dit « il y a un problème » et reste.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `tone` | `neutral` \| `success` \| `error` | `neutral` | `success` et `error` ajoutent glyphe et préfixe masqué. |
| `title` / `children` | `ReactNode` | — |  |
| `action` | `ReactNode` | — | Une action, typiquement une annulation. |
| `onDismiss` | `() => void` | — | Appelé par la croix et par le minuteur. Sans lui, le toast ne part jamais. |
| `duration` | `number` | `5000` | Délai d'auto-fermeture en ms. `0` le désactive. |

`ToastContainer` porte l'empilement, la position (`top`/`bottom` × `left`/`center`/`right`) et **la région live** — un `aria-live` créé au même instant que son contenu n'est pas annoncé de façon fiable. Un seul conteneur par page. Le minuteur se met en pause au survol et au focus interne : une action atteinte au clavier ne s'évanouit pas sous les doigts. Sous 768px le conteneur passe pleine largeur en bas d'écran.

> **Quand ne pas l'utiliser** — Comme unique porteur d'une information. Il disparaît en cinq secondes, et un utilisateur de loupe d'écran peut ne jamais le voir : un numéro de commande, un motif d'échec de paiement doivent aussi exister dans la page. Pour une erreur bloquante : `Dialog` ou `Alert`.

#### Tabs

Onglets de même niveau, une section visible à la fois.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `items` | `{ value, label, content?, disabled? }[]` | `[]` |  |
| `value` / `onChange` | `string` | — | Mode contrôlé. |
| `defaultValue` | `string` | premier onglet actif | Mode non contrôlé. |
| `activation` | `automatic` \| `manual` | `automatic` | `automatic` sélectionne à la flèche (recommandation WAI-ARIA) ; `manual` attend Entrée ou Espace, pour un panneau qui déclenche une requête. |
| `fullWidth` | `boolean` | `false` |  |
| `label` | `string` | — | Nom accessible du bandeau. |

**Roving tabindex** : un seul arrêt de tabulation pour tout le bandeau, les flèches déplacent. Tabuler onglet par onglet est l'erreur d'implémentation classique. L'onglet actif est marqué par un filet 2px **et** par la couleur, jamais par la graisse — le readme §2.2 l'interdit, et cela déplacerait les largeurs du bandeau.

**Mobile** — sous 768px le bandeau défile horizontalement au lieu de passer à la ligne : un retour à la ligne casse la lecture « une rangée de pairs » qui rend les onglets lisibles. Au-delà de quatre ou cinq onglets sur téléphone, `Accordion` est la meilleure structure. La bascule est **documentée, pas automatique** : seul le consommateur sait si ses sections sont des pairs.

> **Onglet désactivé** — Dans cette palette `--mb-content-disabled` vaut le même gris que `--mb-content-quiet`, qui est déjà la couleur de l'onglet non sélectionné. Le désactivé se signale donc par un **barré** (convention « épuisé = barré » du `SizePicker`) et un suffixe masqué « (indisponible) », pas par la couleur.

> **Quand ne pas l'utiliser** — Quand l'utilisateur doit comparer deux sections : elles ne sont jamais visibles ensemble. Quand les sections ne sont pas de même niveau : c'est de la navigation, pas des onglets. Quand il y en a plus de six : la lecture du bandeau devient plus coûteuse que le contenu.

#### Table

Données tabulaires. Sémantique `<table>` réelle : `<caption>` (le nom accessible), `<thead>`, et `scope` sur chaque cellule d'en-tête. Une grille de `<div>` n'est annoncée comme rien, et une table sans `scope` force l'utilisateur de lecteur d'écran à devinener quel en-tête possède une cellule.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `caption` | `ReactNode` | — | Le nom accessible. En fournir toujours un. |
| `captionVisible` | `boolean` | `true` | `false` la réserve aux technologies d'assistance. |
| `columns` | `{ key, header, align?, render?, rowHeader? }[]` | `[]` | `rowHeader` sur la première colonne quand ses cellules nomment la ligne. |
| `rows` | `object[]` | `[]` |  |
| `rowKey` | `(row, i) => Key` | index |  |
| `mobile` | `scroll` \| `stack` | `scroll` | Voir ci-dessous. |
| `density` | `compact` \| `medium` \| `comfortable` | `medium` | `comfortable` double environ l'espace vertical, pour une table courte qu'on lit plutôt qu'on ne balaie. |
| `hideHeader` | `boolean` | `false` | Masque la ligne d'en-tête à l'écran en gardant les `<th scope>` dans l'arbre d'accessibilité. |
| `scrollLabel` | `string` | `caption` | Nom accessible de la zone défilante. |
| `children` | `ReactNode` | — | Balisage `<thead>`/`<tbody>` brut, en contournant `columns`/`rows`. |

**Deux stratégies mobiles, documentées et choisies explicitement.** `scroll` garde la forme dans un conteneur défilant **focusable** — une zone défilante inatteignable au clavier est une impasse et une violation axe. `stack` transforme chaque ligne en bloc de paires libellé / valeur sous 768px : juste pour deux ou trois colonnes de données produit, faux pour un guide des tailles, où comparer d'une ligne à l'autre **est** le contenu. Aucun choix automatique : seul le consommateur sait si la comparaison entre lignes fait le sens.

> **Sans en-tête** — `hideHeader` répond au cas de la fiche technique à deux colonnes, où « Caractéristique / Valeur » n'apprend rien au lecteur voyant. Les cellules d'en-tête restent dans l'arbre d'accessibilité et le lecteur d'écran annonce toujours « Poids · 1 490 g ». Supprimer le `<thead>` priverait la table de ce qui en fait une table.

> **Quand ne pas l'utiliser** — Pour de la mise en page : la sémantique de table dit « ces données se comparent », ce qui est faux d'une grille de tuiles. Pour une liste à une seule colonne : `<ul>`. Au-delà de quelques dizaines de lignes, il faut de la pagination ou du tri, que ce composant ne porte pas.

### E-commerce (dossier `commerce/`, groupe « Commerce »)

#### QuantityStepper

Sélecteur de quantité du panier et de la fiche produit. Métier et non primitive : il encode une quantité d'achat, avec un plafond de stock et un plancher qui vaut soit 1 (une ligne de panier), soit 0 (la retirer).

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `value` / `onChange` | `number` | — | Mode contrôlé. `onChange` reçoit une valeur déjà ramenée dans l'intervalle. |
| `defaultValue` | `number` | `1` | Mode non contrôlé. |
| `min` | `number` | `1` | `0` quand atteindre zéro retire la ligne. |
| `max` | `number` | `99` | Normalement le stock restant. |
| `step` | `number` | `1` |  |
| `disabled` | `boolean` | `false` |  |
| `label` | `string` | `Quantité` | Nom accessible du champ, masqué à l'écran. |

Le champ est un vrai `<input type="number">` : le pavé numérique apparaît sur mobile et la valeur se tape au lieu de se taper trente fois. Les deux boutons sont des cibles de 44px et passent en `disabled` aux bornes plutôt que de rester cliquables sans effet.

> **Quand ne pas l'utiliser** — Pour un nombre quelconque dans un formulaire : `Input`. Au-delà d'une dizaine d'unités attendues, la saisie directe est plus rapide que l'incrément et le pas à pas devient une gêne.

#### SizePicker & SizePickerGroup

Tuile de taille sélectionnable (sélectionnée = bordure noire ; épuisée = barré). La sélection est portée par **`SizePickerGroup`** :

| `selectionMode` | Sémantique | Clavier |
| --- | --- | --- |
| `single` (fiche produit) | radiogroup / radio | Flèches, roving tabindex |
| `multiple` (filtres) | group / checkbox | Tab + Espace |

> **Trois états de disponibilité, pas deux.** `unavailable` est une taille épuisée qui **reste cliquable, focusable et atteinte par les flèches** ; elle déclenche `onChange` et `onUnavailableSelect`. C'est le cas de la fiche produit : le client dit quelle taille il voulait, la page répond par des références similaires. Une tuile sur laquelle il ne peut même pas cliquer est une impasse, et le signal le plus utile de la page est justement celui-là. `disabled` reste inerte : la taille n'existe pas pour ce produit, il n'y a rien à proposer. Le rendu des deux est volontairement identique — barré et grisé, « pas en stock » dans les deux cas ; seule l'opérabilité change.

#### SwatchPicker & SwatchPickerGroup

Pastille de couleur (+ label optionnel), sélection = coche + anneau noir. Même modèle de groupe que SizePicker (`selectionMode` single / multiple). La coche s'adapte automatiquement (claire/foncée) à la couleur du swatch.

#### StockInfo

Pastilles de niveau de stock + label (in / low / out). Couleurs issues des tokens (succès / warning / erreur), texte contrasté, `role="status"`. L'info n'est jamais portée par la seule couleur (nombre de pastilles + label).

#### Sticker

Badge produit. `variant` `neutral` (gris) / `accent` (jaune) / `catchy` (noir).

#### Price

Affichage de prix produit. `variant` `default` (noir) / `reduced` (rouge — prix promotionnel, token `--mb-content-error`). `caption` optionnelle au-dessus de la valeur (« Prix public : 429,95€ », « Ancien prix : … »). Montserrat 600 16px / caption 400 12px.

#### MotorcycleSelector

Sélecteur moto en cascade (Genre · Marque · Année · Modèle) servant à qualifier une moto avant de vérifier la compatibilité d'une pièce. Chaque ligne ouvre une liste plein panneau avec `Search`, plutôt qu'un menu déroulant tronqué : le composant se place tel quel dans une `SideSheet` (desktop) ou une `BottomSheet` (mobile). `steps` décrit les niveaux ; les options d'un niveau peuvent être une fonction de la sélection courante (modèles de la marque choisie). `cascade` (défaut) verrouille un niveau tant que le précédent est vide et réinitialise les niveaux aval. Contrôlable via `value` / `onChange`, `onValidate` reçoit la sélection complète.

#### VariantPicker

Sélecteur de déclinaison produit : rangée de vignettes image, indicateur 2px sous la vignette active, puce de débordement « +N » au-delà de `max`. Boutons opérables au clavier, cible ≥44px, groupe labellisé. Il choisit une **déclinaison** représentée par une photo — pour une valeur de couleur (pastille unie), utiliser `SwatchPicker`. Les libellés restent en « coloris », vocabulaire produit côté client.

> Nommé `Colors` jusqu'en v1.2. Le dossier, les exports et les classes CSS (`.mb-variant-picker`, `__thumb`, `__more`) ont été renommés en v1.3 ; ses propres props (`items`, `selected`, `max`, `onSelect`, `onMore`, `moreLabel`) sont inchangées.

> **Gabarits** — `size` vaut `small` 48×54 (défaut), `medium` 64×72 ou `large` 72×80, ce dernier redescendant à 64×72 sous 768px : c'est la paire desktop / mobile de la fiche produit. Les boîtes sont déclarées dans `tokens/interactions.css` et non en style inline, sans quoi aucune media query ne pourrait les atteindre et le palier responsive serait impossible. `swatchWidth` / `swatchHeight` priment sur `size` et suppriment ce palier — à réserver à ce que les trois gabarits ne couvrent pas.

#### ProductCard

Tuile produit e-commerce (kit Ecom Library) composée à partir des composants du kit : hero carré (image produit, Sticker de remise + pastille bénéfice optionnelles) puis marque + titre, `Price` (caption optionnelle), `StarRating`, `StockInfo` et `VariantPicker`. 100 % tokens.

Les déclinaisons pilotent la tuile : toute donnée produit portée par une entrée `variants[i]` (`image`, `priceValue`, `priceOld`, `priceDiscount`, `stockLevel`, `discount`, `brand`, `title`…) remplace la prop de la carte quand cette vignette est sélectionnée. Props : `variants`, `variantsMax`, `selectedVariant`, `defaultSelectedVariant`, `onVariantSelect`, `onVariantsMore`, `reserveVariantsSpace`. Les alias `colors*` hérités d'avant la v1.3 sont **supprimés en v3.0.0**.

### Navigation

- **Breadcrumb** — Fil d'Ariane à séparateurs chevron, page courante en noir. Liens soulignés (au moins au survol/focus), séparateurs `aria-hidden`.

- **Pagination** — Items de page (courant en jaune) + précédent / suivant. Cible 44px, logique d'ellipse pour les longues listes (1 … 4 5 6 … 40).

- **SliderIndicator** — Indicateur de carrousel/scroll : `variant="bar"` (segment noir sur piste grise) ou `variant="dots"` (points de position, short / long).

- **BackToTop** — Bouton flottant de retour en haut de page, réservé au desktop. Apparaît quand la page a défilé de `showAfter` hauteurs d'écran (défaut `2.5`, soit deux à trois « scrolls ») et se masque en approchant du haut ; sous `minWidth` (défaut `1024`) il ne s'affiche jamais. Pastille grise (`--mb-container-quiet`), icône seule.

  | Prop | Type | Défaut | Rôle |
  | --- | --- | --- | --- |
  | `showAfter` | `number` | `2.5` | Seuil d'apparition, en hauteurs de fenêtre défilées. |
  | `minWidth` | `number` | `1024` | Largeur mini d'affichage. `0` = tous les formats. |
  | `size` | `'medium'` \| `'large'` | `'medium'` | Boîte 48px (icône 20) / 56px (icône 24). |
  | `label` | `string` | `'Haut de page'` | Libellé accessible (icône seule à l'écran). |
  | `offset` | `number` | `24` | Marge par rapport aux bords droit et bas. |
  | `scrollElement` | `ref` \| `element` | fenêtre | Conteneur défilant observé et remonté. |
  | `forceVisible` | `boolean` | `false` | État forcé, pour la documentation. |

  Apparition en fondu + montée de 8px (`--mb-duration-base`), remontée en `scroll-behavior: smooth` ; les deux repassent en instantané si `prefers-reduced-motion`. Masqué, il est retiré du parcours clavier (`tabIndex={-1}`, `aria-hidden`).

  ```jsx
  <BackToTop />
  ```

### Conteneurs & overlays

- **Accordion** — En-tête + contenu dépliable, bascule +/−, filet supérieur. `aria-controls` + panneau `role="region"`.

  | Prop | Type | Défaut | Rôle |
  | --- | --- | --- | --- |
  | `headingSize` | `'150'` \| `'200'` \| `'250'` \| `'300'` | `'150'` | Taille typo de l'en-tête (14 / 16 / 18 / 20px). |
  | `headingLevel` | `2`–`6` | — | Enveloppe l'en-tête dans un `<hN>`. Absent = pas d'enveloppe. |

  **Taille d'en-tête** — Les crans reprennent l'échelle typographique existante (`--mb-font-size-150/200/250/300`) ; aucune valeur nouvelle n'est introduite. Le défaut `'150'` reproduit le rendu actuel. Cette prop remplace la surcharge CSS scopée que la fiche produit appliquait à ses accordéons.

  **Structure de titres** — Question vérifiée : un consommateur **ne peut pas** envelopper l'en-tête lui-même. Envelopper `<Accordion>` dans un `<h3>` placerait aussi le **panneau** à l'intérieur du titre — HTML invalide, et le lecteur d'écran annoncerait tout le contenu déplié comme faisant partie du titre. L'enveloppe doit entourer le seul bouton d'en-tête, qui est interne au composant. D'où la prop `headingLevel`, appliquée autour du bouton uniquement ; le panneau reste en dehors. Par défaut la prop est absente et **aucune** enveloppe n'est rendue : rendu strictement identique à aujourd'hui.

  ```jsx
  <Accordion label="Description" headingLevel={2} headingSize="200">…</Accordion>
  ```

  > Le `<hN>` est neutralisé visuellement (`margin: 0; font: inherit`) : il n'ajoute aucun style, uniquement de la sémantique.

- **SideSheet** — Panneau overlay ancré à droite (titre large / medium), en-tête + corps + pied. **Largeur unique `min(640px, 100vw)`** quel que soit l'usage (prop `width` disponible, à ne surcharger qu'en cas exceptionnel). Modale accessible (focus trap, Échap, `aria-labelledby`, blocage du scroll de fond).

- **BottomSheet** — Overlay ancré en bas, coins arrondis + poignée de glissement, en-tête + corps + pied. Mêmes exigences modale que SideSheet.

- **CommercialBanner** — Bandeau promo jaune pleine largeur, précédent / suivant. Rotation auto : contrôle pause, `aria-live`, respect de `prefers-reduced-motion`.

> **Badge de compatibilité (pièces & accessoires)** — `compatibility` affiche une mention du type « Compatibilité garantie » en bas du visuel, dans la même pastille en verre dépoli que `benefit` mais avec un filet gris : la mention doit se lire comme une affirmation du système sur la moto du client, pas se fondre dans la photo. `compatibilityTone` porte la force de l'affirmation : `success` (coche verte) pour une compatibilité **vérifiée sur la moto du client** — « Compatibilité garantie » ; `neutral` (coche noire) quand il n'y a rien à vérifier — « Pièce universelle », où un vert promettrait un contrôle qui n'a pas eu lieu. En P&A, le badge est seul : la compatibilité est l'information décisive de l'achat, et un `benefit` à côté la dilue. Si les deux sont malgré tout renseignés, ils s'empilent, compatibilité au-dessus. Le libellé passe à deux lignes au maximum plutôt que de tronquer : le badge étant en position absolue sur le visuel, sa hauteur ne coûte rien à la tuile, et une mention coupée est pire qu'une mention haute — l'allemand (« Garantierte Kompatibilität ») est environ 40 % plus long que le français. **À n'afficher que lorsque la compatibilité est réellement connue** — par un sélecteur de moto renseigné : une garantie affichée par défaut est un problème de retours, pas une réassurance.

#### Dialog

Modale centrée bloquante. Troisième membre de la famille overlay : `SideSheet` s'ancre à droite et accueille un flux, `BottomSheet` s'ancre en bas pour le mobile, `Dialog` centre une décision courte et bloquante — « retirer cet article ? ».

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `open` | `boolean` | `false` |  |
| `title` | `ReactNode` | — | Rendu en `<h2>`, sert d'`aria-labelledby`. |
| `description` | `ReactNode` | — | Une ligne sous le titre, liée par `aria-describedby`. Y mettre la conséquence de la décision. |
| `onClose` | `() => void` | — |  |
| `footer` | `ReactNode` | — | Boutons d'action, alignés à droite. L'action confirmante en dernier. |
| `size` | `small` \| `medium` \| `large` | `medium` | 22 / 30 / 40rem, plafonné par la fenêtre. |
| `dismissible` | `boolean` | `true` | `false` retire la croix et désactive Échap et le clic sur le voile — pour une décision qui doit être prise. |

Même contrat d'accessibilité que les deux panneaux, par le hook partagé `useModalA11y`. Sous 768px le panneau passe en plein écran ancré en bas : une boîte centrée sur 390px laisse des marges inatteignables et éloigne les actions du pouce.

> **Quand ne pas l'utiliser** — Pour un flux à plusieurs étapes ou un formulaire long : `SideSheet`. Pour une confirmation qui n'a pas besoin de bloquer : `Toast` avec une action d'annulation coûte moins cher à l'utilisateur qu'une modale à valider.

#### Tooltip

Aide contextuelle sur un contrôle. S'ouvre au survol **et** au focus clavier, se referme sur Échap.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `content` | `ReactNode` | — | Le texte d'aide. Une à deux lignes ; ce n'est pas un popover. |
| `placement` | `top` \| `bottom` \| `left` \| `right` | `top` |  |
| `open` | `boolean` | — | État forcé, pour la documentation. |
| `children` | `ReactNode` | — | Le déclencheur : un élément unique capable de recevoir le focus. |

La visibilité est un état JavaScript et non une règle CSS `:hover`, précisément parce que le focus et Échap doivent aussi la piloter. Un tooltip qui ne répond qu'au survol est inatteignable au clavier — c'est la façon la plus courante de rater ce composant.

> **Quand ne pas l'utiliser** — Sur tactile, où le survol n'existe pas : rien d'essentiel ne doit vivre uniquement là. Comme libellé d'un contrôle : le tooltip complète un contrôle déjà nommé, il ne le nomme pas. Pour du contenu riche ou interactif : ce serait un popover, qui n'est pas encore au kit.

### Accessibilité

#### VisuallyHidden

Texte réservé aux technologies d'assistance. Utilise le motif clip-rect de `tokens/interactions.css` (`.mb-visually-hidden`), jamais `display: none` ni `visibility: hidden`, qui retirent le texte de l'arbre d'accessibilité en même temps que de l'écran.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `as` | élément | `span` |  |
| `focusable` | `boolean` | `false` | Révèle le contenu lorsqu'il reçoit le focus clavier. |

> **Quand ne pas l'utiliser** — Pour masquer du contenu à tout le monde : `hidden` ou `display: none`. Pour cacher une icône décorative aux lecteurs d'écran : `aria-hidden`, qui fait l'inverse. Et jamais pour dupliquer un texte déjà lisible : l'annonce serait doublée.

#### SkipLink

Évitement de bloc (WCAG 2.4.1) : premier élément focusable de la page, il permet de sauter l'en-tête et sa navigation pour atteindre le contenu. Invisible tant qu'il n'a pas le focus, il apparaît alors épinglé en haut à gauche.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `href` | `string` | `#main` | Fragment du point de repère principal. |
| `children` | `ReactNode` | `Aller au contenu` |  |

À rendre comme tout premier enfant de `<body>`. **La cible doit pouvoir recevoir le focus** : lui poser `tabIndex={-1}`.

> **Quand ne pas l'utiliser** — Sur une page sans bloc répété avant le contenu : le lien n'évite rien. Il ne remplace pas les points de repère ARIA (`<header>`, `<nav>`, `<main>`), il les complète.

### Marque & structure

- **Icon** — Set de glyphes ligne & fill style Remix (`<Icon name size />`), peint en `currentColor`.

### Composants (organisms)

Assemblages de primitives, regroupés dans l'onglet Design System sous le groupe **« Component »**.

- **MotorcycleSelector** — Sélecteur moto en cascade (Genre · Marque · Année · Modèle), listes plein panneau avec recherche, à placer dans une `SideSheet` ou une `BottomSheet`.
- **Header** — En-tête e-commerce : barre utilitaire sombre (items de réassurance + sélecteur pays/devise), puis barre noire avec bloc **MENU** jaune, `Logotype` inverse, champ `Search` produit et action « fermer la recherche ». Ajout hors kit strict (organism assemblé), cibles tactiles ≥44px, focus clavier hérité de `.mb-focusable`.

---

## 6. Cartes spécimens (Guidelines)

Affichées dans l'onglet Design System, sous le niveau Guidelines :

- **Brand** : logo sur clair, logo sur sombre.

- **Colors** : échelles jaune, gris, rouge, vert, bleu + conteneurs / contenus / bordures sémantiques.

- **Typography** : échelle de titres, échelle de corps, échelle mobile, familles.

- **Spacing / Sizing / Radius** : tables d'échelle.

- **Motion** : échelle de durées et de courbes (démonstration au survol).

- **Layout** : grille responsive.

---

## 7. Réserves & substitutions

- **Polices** — Chargées depuis Google Fonts (Montserrat, Inter). Si Motoblouz utilise des webfonts licenciées / auto-hébergées, les fournir pour un remplacement en `@font-face`.

- **Icônes** — Set maison extrait verbatim du Figma (`primitives/Icon`, 17 glyphes), peint en `currentColor`.

- **Grille responsive** — Proposition, absente de l'export de tokens.

- **Motion / états** — Couverts depuis la v1.1 par `tokens/motion.css`. L'échelle est dérivée de l'usage constaté ; elle n'est **pas** issue de l'export Tokens Studio et doit être remontée dans `uploads/tokens.json` lors du prochain aller-retour Figma.

### Couverture du kit Figma — familles consolidées / écartées

Le kit « MB — Components » expose **99 « familles »** au sens brut du décompte Figma (chaque symbole autonome + chaque set, doublons de pages compris). Le design system implémente **32 composants** qui couvrent **toutes les fonctionnalités distinctes**. L'écart provient uniquement d'artefacts de comptage, pas de composants manquants :

| Entrées Figma | Traitement | Raison |
| --- | --- | --- |
| `search/…` (≈ 64 variantes : `Default/Large/Rounded`, `Secondary/Neutral/Hover/Small/Square`, `Tertiary/…`, `Filled/…`…) | **Consolidées dans `Search`** | Ce sont les variantes (variant × tone × state × size × shape) d'un seul champ. Un composant paramétré est le bon pattern design system — pas 64 composants. |
| `progress bar/Accent` | **Consolidée dans `ProgressBar`** | Simple variante de `tone` (`accent`) du même composant. |
| Sets dupliqués entre pages (`accordion`×3, `divider`×3, `chip`×2, `link`×2, `radiobutton`×2, `sticker`×2, `stock info`×2, `swatch picker`×2, `Toggle`×3, `Size picker`×2, `sideSheet`×2, `slider indicator`×2, `breadcrumb`×2…) | **Un seul composant chacun** | Le même composant apparaît sur plusieurs pages du fichier ; le décompte les additionne. |
| Glyphes d'icônes (`arrow-*`, `check-fill`, `close-*`, `star-*`, `search-line`, `add-line`, `subtract-fill`…) | **Regroupés dans `Icon`** | Set de 17 glyphes rendu via `<Icon name />` — un data-set, pas un composant par glyphe. |
| Sous-parties internes (`itemPagination`, `.label` rendu inline, `dropdownMenu`, `Label`/`swap content`/`Orientation=…` internes) | **Intégrées à leur parent** (`Pagination`, `Breadcrumb`, `Select`, `RangeSlider`, `CommercialBanner`) | Nœuds d'auto-layout privés, non exposés comme API publique. Le menu déroulant (`dropdownMenu`) est fusionné dans `Select` (popover interne). |
| `.footer`, `.heading` | **Retirés volontairement** | Supprimés à la demande de l'équipe (composants de marque/structure hors périmètre). |

> En résumé : les **familles distinctes** du kit sont toutes construites. Le compteur aplatit l'arbre de variantes Figma en « familles » ; re-scinder `Search` ou `Icon` en dizaines de composants irait à l'encontre du pattern design system et n'est **pas** prévu.

---

> **Ancrage** — `pin` décide de ce à quoi le bouton est fixé. `viewport` (défaut) le fixe à la fenêtre, c'est le cas de production ; `container` le positionne en absolu dans le premier ancêtre positionné — panneau défilant, cadre d'appareil, aperçu de page intégré, normalement avec `scrollElement` ; `none` le laisse dans le flux normal, pour une planche de spécimens. Sans cette prop, un consommateur devait surcharger `position` par une règle `!important` scopée, un style inline étant hors de portée du CSS.

#### Palette core

**Jaune (accent de marque)**

| Token | Hex |  | Token | Hex |
| --- | --- | --- | --- | --- |
| `--mb-yellow-100` | `#fbe6b3` |  | `--mb-yellow-500` ★ | `#f1ab00` |
| `--mb-yellow-200` | `#f9dd99` |  | `--mb-yellow-600` | `#d99a00` |
| `--mb-yellow-300` | `#f7cd66` |  | `--mb-yellow-700` | `#c18900` |
| `--mb-yellow-400` | `#f4bc33` |  | `--mb-yellow-800` | `#a97800` |
|  |  |  | `--mb-yellow-900` | `#916700` |

**Gris (neutres)**

| Token | Hex |  | Token | Hex |
| --- | --- | --- | --- | --- |
| `--mb-grey-100` | `#fbfbfb` |  | `--mb-grey-600` | `#a5a5a5` |
| `--mb-grey-200` | `#f6f6f6` |  | `--mb-grey-700` | `#6f6e6e` |
| `--mb-grey-300` | `#ececec` |  | `--mb-grey-800` | `#5e5e5e` |
| `--mb-grey-400` | `#d4d4d4` |  | `--mb-grey-900` | `#2f2f2f` |
| `--mb-grey-500` | `#bdbdbd` |  | `--mb-white` / `--mb-black` | `#fff` / `#000` |

**Statuts** — Rouge (erreur), Vert (succès), Bleu (info) déclinés de 050 à 700. Valeurs signatures : erreur `--mb-red-400 #d70321`, succès `--mb-green-400 #179500`, info `--mb-blue-400 #4A8DB7`.

#### Sémantique (thème clair par défaut, `.theme-dark` pour le sombre)

| Rôle | Token | Valeur (clair) |
| --- | --- | --- |
| Fond | `--mb-bg-main` | blanc |
| Conteneur neutre | `--mb-container-neutral` | blanc |
| Conteneur discret | `--mb-container-quiet` | grey-300 |
| Conteneur marque / catch | `--mb-container-brand` / `-catchy` | noir |
| Conteneur accent | `--mb-container-accent` | yellow-500 |
| Conteneur erreur / succès | `--mb-container-error` / `-success` | red-400 / green-400 |
| Texte neutre | `--mb-content-neutral` | noir |
| Texte discret | `--mb-content-quiet` | grey-700 |
| Texte accent | `--mb-content-accent` | yellow-500 |
| Bordure principale | `--mb-border-main` | grey-400 |

> **⚠️ Règles de contraste (accessibilité)** — À respecter impérativement :
>
> - Le jaune `--mb-content-accent` **ne doit jamais servir de couleur de texte sur fond clair** (ratio \~1,9:1). Il est réservé au texte sur fond sombre. Pour du texte accent sur clair, utiliser une nuance assombrie (yellow-800/900).
>
> - Le succès en **texte** sur fond clair doit s'appuyer sur green-500 (green-400 ne passe pas le 4,5:1).
>
> - Les **bordures de contrôle** doivent atteindre un contraste ≥ 3:1 (grey-700 minimum ; grey-400 est insuffisant).
>
> Le détail des correctifs de contraste (dont l'ajout d'une échelle « warning » orange) est suivi dans le document d'audit associé.

### 2.2 Typographie

**Montserrat partout.** Inter est la police alternative pour l'UI dense et les données tabulaires. Line-height serrée sur les titres.

| Usage | Police / poids |
| --- | --- |
| Titres | Montserrat **Medium (500)** |
| Emphase | Montserrat **Bold (700)** |
| Corps de texte & contrôles | Montserrat **Regular (400)** |
| UI dense / tabulaire | Inter |

> **Règle de poids** — Le texte de saisie, les valeurs, les placeholders et les libellés de contrôle sont en **Regular 400**. L'état sélectionné/actif se signale par la couleur, la bordure ou le fond — **jamais** par un changement de graisse (qui déplace la largeur).

#### Échelle de tailles (core)

`11 · 12 · 14 · 16 · 18 · 20 · 22 · 24 · 28 · 32 · 36 · 40 · 56 px` (tokens `--mb-font-size-050` → `--mb-font-size-700`), définis en **rem** (base 16px) pour grandir avec le zoom texte du navigateur (WCAG 1.4.4). Les contrôles à hauteur fixe utilisent `min-height` afin que le texte agrandi ne soit pas rogné.

#### Deux échelles responsives

| Style | Desktop | Mobile |
| --- | --- | --- |
| Display | 56 | 40 |
| Title 1 | 40 | 36 |
| Title 2 | 32 | 28 |
| Title 3 | 28 | 24 |
| Title 4 | 24 | 22 |
| Title 5 | 20 | 20 |
| Title 6 | 18 | 18 |
| Body large | 16 | — |
| Body small | 14 | — |
| Caption | 12 | — |

Classes utilitaires disponibles : `.mb-type-display`, `.mb-type-title-1` … `.mb-type-title-6`, `.mb-type-body-large`, `.mb-type-body-small`, `.mb-type-overline`, `.mb-type-caption`.

> Le 11px (`--mb-font-size-050`) est réservé aux mentions légales, jamais à de l'information utile.

### 2.3 Espacement (spacing)

Échelle core sur base 4px, exposée en tokens numériques **et** en alias sémantiques « t-shirt ». Utilisée pour `gap`, `padding`, `margin`.

| Alias | px |  | Alias | px |
| --- | --- | --- | --- | --- |
| `none` | 0 |  | `2xl` | 24 |
| `4xs` | 1 |  | `3xl` | 32 |
| `3xs` | 2 |  | `4xl` | 40 |
| `2xs` | 4 |  | `5xl` | 48 |
| `xs` | 6 |  | `6xl` | 64 |
| `s` | 8 |  | `7xl` | 80 |
| `m` | 12 |  | `8xl` | 96 |
| `l` | 16 |  | `9xl` | 128 |
| `xl` | 20 |  | `10xl` | 160 |

Tokens : `--mb-spacing-<alias>` (sémantique) et `--mb-space-<n>` (core).

### 2.4 Dimensionnement (sizing)

Même logique que le spacing (icônes, hauteurs de contrôle, avatars). Alias `none` → `9xl`, de 0 à 128px. Le cran **44px** (core `--mb-size-475`) est la cible tactile de référence.

> **⚠️ Divergence à connaître** — Les alias t-shirt de `sizing` et `spacing` ne pointent pas sur les mêmes valeurs (ex. `sizing.m = 16px` mais `spacing.m = 12px`). Vérifier le contexte avant d'utiliser un alias.

### 2.5 Rayons (radius)

Set volontairement restreint : la marque penche vers l'anguleux et l'affirmé plutôt que le doux.

| Sémantique | px | Core |
| --- | --- | --- |
| `--mb-border-radius-none` | 0 | `radius-0` |
| `--mb-border-radius-m` | 8 | `radius-300` |
| `--mb-border-radius-l` | 24 | `radius-700` |
| `--mb-border-radius-full` | 999 | `radius-full` |

Échelle core complète disponible (2, 4, 8, 12, 16, 20, 24, 32px). Un cran intermédiaire à 16px est recommandé pour les cartes (voir doc d'audit).

### 2.6 Grille responsive

| Breakpoint | Colonnes | Gouttière | Marge |
| --- | --- | --- | --- |
| Desktop (≥ 1280px) | 12 | 24px | 32px |
| Tablette (768–1279px) | 8 | 16px | 24px |
| Mobile (< 768px) | 4 | 16px | 16px |

> La grille est une **proposition**, elle ne provient pas de l'export de tokens.

### 2.7 Surfaces, iconographie, motion

**Surfaces & cartes** — Plates, très contrastées. Cartes neutres = blanc + bordure grey-400 ; surfaces « catchy » / marque = noir plein. Pas d'ombres portées lourdes dans le set de tokens : la séparation vient des bordures et des changements de fond.

**Fonds** — Majoritairement blanc ou noir ; grey-200 pour les sections discrètes. La photographie lifestyle plein cadre de motards est centrale (imagerie chaude, orientée action) — fournir de vraies photos par campagne.

**Iconographie** — Set de glyphes maison de style Remix (ligne & fill), rendu via le composant `Icon` (`currentColor`). 17 glyphes fournis. Pas d'emoji.

**Motion & états** — Désormais couverts par une famille de tokens dédiée : voir [2.7 Mouvement](#27-mouvement-motion).

---

### 2.7 Mouvement (motion)

Sixième famille de tokens (`tokens/motion.css`), même modèle de nommage que les autres : `--mb-<famille>-<cran>`.

L'échelle est **dérivée de l'usage réel** relevé dans le design system et dans la fiche produit, pas d'une rampe théorique. Relevé avant travaux : 120ms (dominante, ~14 composants), 140ms (chevron du Select, piste du Toggle), 160ms (SliderIndicator), 200ms (ProgressBar) — et une seule courbe, le mot-clé CSS `ease`.

#### Durées — trois crans

| Token | Valeur | Quand |
| --- | --- | --- |
| `--mb-duration-fast` | 120ms | Retour d'état : wash hover/press, bordure, couleur, focus |
| `--mb-duration-base` | 160ms | Un élément bascule d'état ou se déplace : Toggle, chevron, indicateur |
| `--mb-duration-slow` | 200ms | Parcours d'une distance : progression, apparition de panneau |

#### Courbes — trois crans

| Token | Valeur | Quand |
| --- | --- | --- |
| `--mb-easing-standard` | `ease` | Défaut. Mouvement qui commence et finit à l'écran |
| `--mb-easing-enter` | `cubic-bezier(0, 0, .2, 1)` | Un élément apparaît / se déplie |
| `--mb-easing-exit` | `cubic-bezier(.4, 0, 1, 1)` | Un élément disparaît / se replie |

`--mb-easing-standard` vaut volontairement `ease` : c'est exactement la courbe déjà en place partout, donc **aucun rendu existant ne change**.

#### Raccourcis

`--mb-transition-fast` / `-base` / `-slow` combinent durée + courbe standard :

```css
transition: background var(--mb-transition-fast);
transition: transform var(--mb-duration-base) var(--mb-easing-enter);
```

> **Trois crans, pas plus.** Une durée hors échelle est un bug, pas un nouveau cran. Le cran 140ms relevé à l'audit a été normalisé sur `base` (160ms) plutôt que promu en cran.

#### prefers-reduced-motion — pris en charge par le design system

`tokens/motion.css` porte la media query. Deux couches :

1. les trois durées s'effondrent à `0.01ms` — tout composant et tout consommateur bâti sur les tokens cesse d'animer d'un coup ;
2. un filet de sécurité (`*, *::before, *::after`) neutralise aussi les durées encore écrites en dur chez les consommateurs non migrés. Il ne touche que `animation-duration`, `animation-iteration-count`, `transition-duration` et `scroll-behavior` — ni mise en page, ni couleur.

`0.01ms` et non `0` pour que les événements `transitionend` / `animationend` continuent de se déclencher.

> **Les consommateurs ne redéclarent plus la media query page par page.** Celles déjà en place restent inoffensives (même effet, appliqué deux fois) et peuvent être retirées au fil de l'eau.

---

## 3. Architecture des fichiers

| Fichier | Rôle |
| --- | --- |
| `styles.css` | Point d'entrée global (imports uniquement). C'est ce que les consommateurs lient. |
| `tokens/colors.css` | Échelles core + alias sémantiques clair / `.theme-dark`. |
| `tokens/typography.css` | Montserrat + Inter, échelle de tailles, poids, classes `.mb-type-*`. |
| `tokens/spacing.css` | Échelle core + alias t-shirt. |
| `tokens/sizing.css` | Échelle core + alias sémantiques. |
| `tokens/radius.css` | Rayons core + sémantiques. |
| `tokens/motion.css` | Durées, courbes, raccourcis + prise en charge `prefers-reduced-motion`. |
| `tokens/interactions.css` | États hover / press / focus-visible en CSS, utilitaires `.mb-visually-hidden` et `.mb-skip-link`, animations `Spinner` et `Skeleton`, bascules responsives de `Toast` et `Dialog`. |
| `assets/logo-black.*`, `assets/logo-white.*` | Variantes de logo. |
| `guidelines/*.html` | Cartes spécimens affichées dans l'onglet Design System. |
| `primitives/<nom>/` | **43 composants** génériques, sans logique métier : `Button`, `Input`, `Select`, `SideSheet`… Réutilisables hors e-commerce. |
| `commerce/<nom>/` | **12 composants** métier Motoblouz : `Header`, `Price`, `ProductCard`, `QuantityStepper`, `VariantPicker`, `MotorcycleSelector`, `SizePicker`, `SizePickerGroup`, `SwatchPicker`, `SwatchPickerGroup`, `Sticker`, `StockInfo`. |
| `index.js` / `index.d.ts`, `primitives/index.js`, `commerce/index.js` | Barrels : points d'entrée publics, exports nommés uniquement. |
| `package.json` | Métadonnées du paquet : `exports` (`.`, `./primitives`, `./commerce`, `./styles.css`), `sideEffects: false`, React en `peerDependencies`. |
| `SKILL.md` | Point d'entrée de l'Agent Skill. |

> **`_ds_bundle.js` n'est pas un livrable.** C'est un artefact régénéré depuis les sources par un pipeline séparé. Il n'est ni dans `main`, ni dans `module`, ni dans `exports`, ni dans `files` — le paquet publié sert `index.js` et les sources, jamais le bundle.
>
> **État actuel** — le bundle est **à jour** vis-à-vis des sources courantes. Il reste versionné parce que les 35 cartes spécimens le chargent par chemin relatif. Le risque à surveiller est celui d'un bundle qui décroche des sources : il ne se voit ni au grep, ni au rendu des cartes, ni aux tests, seulement chez le premier consommateur qui installe. Il doit donc être régénéré depuis les sources courantes avant tout tag.
>
> **Contrôle** — la régénération du bundle n'est pas exécutable depuis le dépôt : elle appartient au compilateur de l'outillage Design System, hors de portée d'un script versionné. Le contrôle repose donc sur une **baseline de hash des sources**, `_ds_bundle.sources.sha256` : un SHA-256 par fichier `.jsx`/`.js` de `primitives/` et `commerce/`, plus un hash combiné en tête. Avant tout tag : recalculer, comparer à la baseline. Écart ⇒ le bundle versionné est présumé périmé et doit être régénéré, puis la baseline réécrite dans le même commit. La baseline ne prouve pas que le bundle est juste, seulement qu'il n'a pas décroché depuis la dernière régénération constatée.
>
> **Cible** — sortir le bundle du dépôt et ne le produire qu'en CI. Prérequis : faire charger les cartes autrement qu'en relatif depuis la racine du dépôt.

### Format de distribution — ESM et sources JSX

Le paquet est `"type": "module"` et n'expose **aucune clé `require`** : il ne s'importe qu'en ESM. Un `require('@motoblouz/gazoline')` échoue — c'est assumé, pas un oubli.

Contrainte plus forte, et c'est la contrainte réelle : **le paquet expose du JSX non transpilé.** `index.js` réexporte `primitives/index.js`, qui réexporte des fichiers `.jsx`. Il n'y a pas d'étape de build : servir les sources telles quelles est ce qui rend le DS lisible et éditable en place. Le coût est reporté sur l'outillage du consommateur, qui doit transpiler ce paquet lui-même.

**À vérifier avant d'installer :**

| Contexte | État | Config requise |
| --- | --- | --- |
| Vite | Fonctionne tel quel | Aucune — le pre-bundling esbuild transpile les dépendances. |
| webpack / Next.js | Casse par défaut | Le loader Babel/SWC exclut `node_modules`. Retirer `@motoblouz/gazoline` de l'exclusion (webpack : `exclude: /node_modules\/(?!@motoblouz\/gazoline)/` ; Next.js : `transpilePackages: ['@motoblouz/gazoline']`). Sans ça, erreur de syntaxe sur le premier `<`. |
| Jest | Casse par défaut | `transformIgnorePatterns: ['/node_modules/(?!@motoblouz/gazoline)']` explicite. |
| Node (SSR direct, scripts) | Ne fonctionne pas | Node n'a pas de transformation JSX, quel que soit le format de module. Passer par le bundler du framework. |

Ajouter un double format CJS imposerait une étape de build et un artefact compilé de plus dans un dépôt qui n'en a aucun. Le jour où un consommateur CJS apparaît, ajouter une clé `require` pointant sur un build dédié — pas sur `index.js`.

**La distribution ne passe pas par npm à ce stade.** Le paquet est `"private": true` et `UNLICENSED` : aucun registre n'est câblé, et le flag protège d'un `npm publish` accidentel vers le registre public. `npm pack` reste utilisable pour inspecter le tarball. Le branchement d'un registre interne consistera à ajouter `publishConfig.registry` et retirer `private` **dans le même commit** — jamais l'un sans l'autre.

**Critère de découpage : générique / métier, pas atomique / composite.** La granularité ne dit rien : `primitives/` contient des composants qui en importent d'autres (13 importent `Icon`) et `commerce/` contient des composants sans aucune dépendance interne. Le clivage est l'appartenance au domaine e-commerce.

> **Le test.** *Le composant garderait-il un sens dans un produit qui ne vend rien ?* Oui → `primitives/`. Non → `commerce/`.
>
> C'est ce test qui a déplacé `SizePicker`, `SizePickerGroup`, `SwatchPicker`, `SwatchPickerGroup`, `Sticker` et `StockInfo` en v3.0.0 : une tuile de taille, une pastille de coloris, un badge produit et un niveau de stock ne veulent rien dire hors catalogue. `StarRating` reste en `primitives/` : on note aussi bien un article qu'un film ou un trajet.
>
> Répartition : **43 primitives, 12 composants métier.**

Les dépendances vont dans un seul sens : `commerce/` importe `primitives/`, jamais l'inverse. Aucune primitive n'importe un composant de `commerce/`.

> **Collision avec les globales du navigateur** — `Text` et `Image` portent les mêmes noms que l'interface DOM `Text` et le constructeur `Image`. Dans un module ES la déstructuration est cadrée au module : aucun effet. Dans un **script classique**, en revanche, un `const { Text, Image } = …` au premier niveau masque la globale **pour toute la page**, et tout code tiers appelant `new Image()` casse. Déstructurer dans une portée de fonction, ou aliaser. Les 68 cartes spécimens confinent leur script pour cette raison.

### Importer le design system

```js
import { Button, ProductCard } from '@motoblouz/gazoline';
import { Button } from '@motoblouz/gazoline/primitives';   // sous-chemin
import { ProductCard } from '@motoblouz/gazoline/commerce';
```

```html
<link rel="stylesheet" href="@motoblouz/gazoline/styles.css">
```

**Source de vérité des tokens** : `uploads/tokens.json` (export Tokens Studio, core + sémantique clair/sombre).

---

## 4. Conventions transverses

Ces règles s'appliquent à **tous** les composants et priment sur les détails individuels.

### Nommage des props

- **`type`** = attribut HTML natif (`button` / `submit` / `reset`, etc.).

- **`variant`** = axe de style visuel (ex. `primary` / `secondary` / `tertiary`).

### Versionnage

**Semver.** **Majeur** : renommage ou suppression d'un composant, d'une prop, d'un token ou d'un chemin d'import ; changement de défaut modifiant le rendu. **Mineur** : ajout de composant, de prop ou de token, purement additif. **Correctif** : correction de bug sans changement d'API.

L'absence de publication en registre ne dispense pas du majeur : les consommateurs importent par chemin, leur code casse de la même façon.

### Nommage des composants

- **`Picker` vs `Selector`** — `Picker` = contrôle qui choisit une valeur sur un seul attribut (`SizePicker`, `SwatchPicker`, `VariantPicker`). `Selector` = widget qui orchestre plusieurs étapes ou contrôles pour produire une sélection composée (`MotorcycleSelector`).

### Refs

Les primitives interactives forwardent une ref vers **l'élément focusable**, pas vers la racine : `Input`, `Search`, `Checkbox`, `RadioButton` et `Toggle` la posent sur l'`<input>` interne (c'est lui qui porte `focus()`, `value` et la validation native, pas le `<label>`) ; `Select` sur le `<button>` déclencheur ; `Button`, `IconButton`, `Link`, `SizePicker`, `SwatchPicker` et `Chip` sur leur racine, qui est déjà l'élément focusable.

> **Exception — `RangeSlider`.** Seul composant du kit dont la ref n'est **pas un élément** mais un handle impératif : deux poignées, aucun nœud focusable unique. `ref.current` expose `node` (le nœud racine, à utiliser pour `getBoundingClientRect` ou pour ancrer un positionneur type floating-ui), `focusFrom()`, `focusTo()` et `getThumb('from' | 'to')`. Passer `ref` directement à un positionneur ne marchera pas : il faut `ref.current.node`.

> **`Chip` en mode statique / dismissible.** La racine n'est pas focusable dans ces modes : `ref.current.focus()` est un no-op silencieux. Le nœud focusable y est la croix interne, que la ref n'atteint pas.

### Accessibilité (socle commun)

- **Focus visible** — Tout élément focusable expose un anneau de focus (`--mb-focus-ring`) via `:focus-visible`. Pour les contrôles dont l'input natif est masqué (Checkbox, RadioButton, Toggle), le focus est reporté sur l'élément **visible**.

- **Cibles tactiles** — Zone cliquable de **44px minimum** pour tout contrôle destiné au tactile.

- **États** — hover/press gérés en CSS (`:hover` / `:active`), `disabled` via tokens dédiés (pas d'opacité globale qui délave le texte).

- **L'information ne repose jamais sur la seule couleur** — toujours un second signal (icône, forme, texte, position).

- **Clavier** — Les widgets composites (Select, RangeSlider, groupes de sélection, StarRating cliquable) sont pleinement opérables au clavier (flèches, Entrée, Espace, Échap, Home/Fin).

### Contrôlé / non contrôlé

La plupart des composants d'entrée supportent les deux modes : `value` + `onChange` (contrôlé) ou `defaultValue` (non contrôlé).

---

## 5. Bibliothèque de composants

Composants recréés depuis le kit Figma « MB — Components » : `primitives/` (génériques) et `commerce/` (métier). Chacune est paramétrée sur les axes de variantes exacts du Figma.

### Actions

#### Button

Bouton d'action complet.

| Prop | Type | Défaut | Description |
| --- | --- | --- | --- |
| `variant` | `primary` \| `secondary` \| `tertiary` | `primary` | Poids visuel (plein / contour / texte). |
| `tone` | `neutral` \| `accent` \| `error` | `neutral` | Famille de couleur (noir / jaune / rouge). |
| `size` | `small` \| `medium` \| `large` | `medium` | Hauteur 41 / 52 / 60px. |
| `shape` | `rounded` \| `square` | `rounded` | Pilule (999px) ou 8px. |
| `type` | `button` \| `submit` \| `reset` | `button` | Type HTML natif. |
| `fullWidth` | `boolean` | `false` | S'étire sur toute la largeur. |
| `disabled` | `boolean` | `false` |  |
| `startIcon` / `endIcon` | `ReactNode` |  | Icône avant / après. |

```
<Button variant="primary" tone="accent" size="large">Ajouter au panier</Button>
<Button variant="secondary" tone="neutral">Continuer</Button>
<Button variant="tertiary" endIcon={<Icon name="ArrowRightLine" />}>Voir plus</Button>

```

> Un bouton icône seule (sans texte) doit fournir un `aria-label`.

#### IconButton

Bouton icône seule, même système `variant` / `tone` / états. Tailles 64 / 52 / 41px, `rounded` ou `square`. `aria-label` **obligatoire**.

#### Link

Lien texte inline souligné. `tone` `neutral` / `accent`, `size` `large` (14px) / `small` (12px). Le tone accent utilise une nuance assombrie pour rester lisible.

### Typographie & layout

#### Text

L'atome typographique, et le seul composant qui consomme les classes composites `.mb-type-*` au lieu de réassembler famille, graisse, taille et interligne à la main. C'est par lui que l'échelle responsive Desktop / Mobile du §2.2 atteint enfin l'interface.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `as` | élément | `p` | Élément rendu. À choisir pour le plan du document, indépendamment de `variant`. |
| `variant` | `display` \| `title-1`…`title-6` \| `body-large` \| `body-large-bold` \| `body-small` \| `body-small-bold` \| `overline` \| `caption` \| `caption-bold` | `body-large` | Style typographique sur l'échelle `.mb-type-*`. |
| `tone` | `inherit` \| `neutral` \| `quiet` \| `accent` \| `error` \| `success` \| `warning` \| `disabled` \| `on-brand` \| `on-catchy` \| `on-accent` | `neutral` | Couleur sémantique. |
| `weight` | `regular` \| `medium` \| `semi-bold` \| `bold` | — | Surcharge de graisse. À utiliser rarement : la variante porte déjà la bonne. |
| `align` | `textAlign` | — |  |
| `truncate` | `boolean` \| `number` | `false` | `true` = une ligne avec ellipse. Un nombre = coupe à ce nombre de lignes. |

`tone="accent"` résout sur `--mb-content-accent-strong` (yellow-900, 5,07:1) et **jamais** sur le jaune vif, qui échoue AA en texte sur fond clair. Le composant rend donc impossible la faute que la règle §2.1 décrit.

```jsx
<Text as="h1" variant="title-1">Équipement moto</Text>
<Text variant="body-small" tone="quiet">Livraison offerte dès 80 €</Text>
```

> **Quand ne pas l'utiliser** — Pour du texte qui appartient déjà à un composant (libellé de `Button`, titre de `Dialog`, message d'`Alert`) : ces composants portent leur propre typographie. `Text` sert au contenu, pas à l'habillage des contrôles.

#### Stack

Layout unidimensionnel. Son seul rôle est de faire venir l'espacement de l'échelle au lieu d'une marge ad hoc sur chaque enfant.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `as` | élément | `div` |  |
| `direction` | `vertical` \| `horizontal` | `vertical` |  |
| `gap` | alias t-shirt de spacing | `m` (12px) | Espace entre enfants. Aucune échappatoire en pixels. |
| `align` / `justify` | `alignItems` / `justifyContent` | — |  |
| `wrap` | `boolean` | `false` |  |
| `inline` | `boolean` | `false` | `inline-flex`, pour une pile dans un flux de texte. |

> **Quand ne pas l'utiliser** — Pour une grille : c'est `Grid`. Pour un seul espace entre deux blocs qui ne sont pas frères dans la même pile : c'est `Spacer`.

#### Grid

Layout bidimensionnel. Le mode par défaut est intrinsèquement responsive : `minItemWidth` pilote une liste de pistes `auto-fill`, donc le nombre de colonnes suit le conteneur sans media query ni mesure en JavaScript.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `columns` | `number` | — | Nombre fixe de colonnes. Laisser vide pour le mode responsive. |
| `minItemWidth` | longueur CSS | `16rem` | Largeur mini de piste en mode `auto-fill`. Préférer `rem`, qui suit le zoom texte. |
| `gap` / `rowGap` | alias t-shirt de spacing | `l` (16px) |  |
| `align` / `justify` | `alignItems` / `justifyItems` | — |  |

> **Quand ne pas l'utiliser** — Pour une seule rangée ou une seule colonne : `Stack` dit mieux l'intention. Pour la grille de page (12 / 8 / 4 colonnes du §2.6) : c'est une grille de gabarit, pas de composant.

#### Spacer

Espace explicite, pour les deux cas que `gap` ne couvre pas : pousser des frères aux extrémités d'une rangée flex (`grow`), ou imposer un cran entre deux blocs qui ne sont pas enfants de la même pile. Toujours `aria-hidden`.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `size` | alias t-shirt de spacing | `m` | Cran d'espacement. |
| `axis` | `vertical` \| `horizontal` | `vertical` |  |
| `grow` | `boolean` | `false` | Absorbe l'espace libre du parent flex, avec `size` comme plancher. |

> **Quand ne pas l'utiliser** — Entre les enfants d'un `Stack` ou d'un `Grid` : le `gap` le fait déjà, et un `Spacer` intercalé fausse le comptage des enfants. Un `Spacer` qui apparaît en série est le signe qu'il manque un `Stack`.

#### Image

Ratio maîtrisé, chargement différé, image de repli. Les trois choses qu'un `<img>` nu rate dans un catalogue : il n'a pas de hauteur avant d'être chargé, donc la grille saute ; il charge même trois écrans sous la ligne de flottaison ; et une source en 404 laisse le glyphe d'image brisée du navigateur au milieu de la page.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `src` | `string` | — |  |
| `alt` | `string` | — | **Obligatoire.** `alt=""` est une valeur légitime — décoratif — mais elle doit être écrite, pas oubliée. |
| `ratio` | `square` \| `portrait` \| `landscape` \| `wide` \| valeur CSS | `square` | Réserve la boîte avant chargement. |
| `fit` | `objectFit` | `cover` |  |
| `loading` | `lazy` \| `eager` | `lazy` | `eager` pour la seule image au-dessus de la ligne de flottaison. |
| `fallback` | `ReactNode` | — | Remplace le placeholder par défaut. |
| `fallbackLabel` | `string` | `Image indisponible` | Ignoré si `alt` est non vide — l'`alt` le dit mieux. |
| `radius` | `none` \| `m` \| `l` \| `full` | `none` |  |
| `background` | `quiet` \| `neutral` \| `none` | `quiet` | Couleur de la boîte réservée pendant le chargement. |

> **Quand ne pas l'utiliser** — Pour une icône : c'est `Icon`, qui peint en `currentColor`. Pour un fond décoratif plein cadre : `background-image` en CSS, qui n'a pas besoin d'entrer dans l'arbre d'accessibilité.

#### Card

Conteneur bordé génerique : trois emplacements, aucun domaine. Distinct de `ProductCard`, qui est la tuile produit métier et possède sa propre anatomie.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `variant` | `outlined` \| `elevated` \| `plain` | `outlined` | La bordure est le dispositif de séparation de la marque (§2.7) ; `elevated` pour la surface rare qui doit se détacher. |
| `tone` | `neutral` \| `quiet` \| `catchy` | `neutral` | `catchy` est la surface noire et inverse l'encre et les filets. |
| `padding` | `none` \| `small` \| `medium` \| `large` | `medium` | `none` pour un contenu bord à bord (un hero `Image`). |
| `radius` | `none` \| `m` \| `l` | `m` |  |
| `header` / `footer` | `ReactNode` | — | Emplacements séparés par un filet. |
| `interactive` | `boolean` | `false` | Rend la carte entière activable, **en `<button>`**. |
| `disabled` | `boolean` | `false` | N'a de sens qu'avec `interactive`. |

`interactive` rend un vrai `<button>` : opérable au clavier et annoncé comme un contrôle unique. Un `<div>` muni d'un `onClick` serait invisible au clavier.

> **Quand ne pas l'utiliser** — Pour un produit : `ProductCard`, qui encode le prix, le stock et les déclinaisons. Comme simple boîte à padding : `Stack` suffit, sans bordure ni sémantique. Et une carte `interactive` qui contient d'autres boutons est invalide — un bouton ne peut pas en contenir un autre ; laisser `interactive` à `false` et rendre les actions individuellement.

#### Carousel

Le conteneur défilant que `SliderIndicator` indiquait déjà. Jusqu'ici le kit livrait l'indicateur sans la chose indiquée, et chaque gabarit refaisait son défileur.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `label` | `string` | — | Nom accessible, masqué à l'écran. Dire ce qu'il contient. |
| `slidesToShow` | `number` | `1` |  |
| `peek` | `number` | `0.2` | Fraction de la vignette suivante laissée visible. `0` aligne la piste pile au bord. |
| `gap` | alias t-shirt de spacing | `l` |  |
| `indicator` | `dots` \| `bar` \| `none` | `dots` |  |
| `showArrows` | `boolean` | `true` | Commodité de pointeur : la piste défile toujours au geste, au clavier et par l'indicateur. Masquées sous 768px quoi qu'il arrive. |
| `autoPlay` | `boolean` | `false` | Pause au survol, au focus interne et onglet en arrière-plan ; désactivée sous `prefers-reduced-motion`. |
| `interval` | `number` | `5000` |  |

**Peek** — par défaut 20 % de la vignette suivante restent visibles. Une piste qui s'arrête pile au bord du conteneur se lit comme un bloc terminé, et ce qui suit n'est jamais cherché ; une vignette coupée est l'affordance qui dit « ça défile ». C'est aussi ce qui rend les flèches facultatives au lieu d'être le seul indice — d'où `showArrows={false}` comme configuration de plein droit.

Construit sur le défilement natif et `scroll-snap` plutôt que sur une piste transformée : le geste tactile, l'inertie et le défilement clavier viennent de la plateforme. La piste est `tabIndex={0}` — une zone défilante non focusable est une impasse au clavier, et une violation axe. La préférence de mouvement est lue par **écouteur**, pas une fois au rendu : une valeur lue au montage ne voit jamais l'utilisateur changer son réglage.

> **Quand ne pas l'utiliser** — Pour du contenu que l'utilisateur doit voir : ce qui est hors écran dans un carrousel n'est presque jamais consulté ; une grille montre tout. Pour de la navigation : c'est `Tabs`. Et `autoPlay` sur du contenu porteur de sens déplace le texte sous les yeux de qui lit lentement — le réserver aux mises en avant.

### Saisie & sélection

#### Select (widget unifié)

Composant de sélection unifié fusionnant l'ancien trigger, la liste (popover) et la logique d'ouverture/sélection. Deux axes **indépendants** qui se combinent.

| Prop | Type | Défaut | Description |
| --- | --- | --- | --- |
| `options` | `{ value, label, disabled? }[]` | `[]` | Options. |
| `selectionMode` | `single` \| `multiple` | `single` | Choix unique ou multiple. |
| `value` / `onChange` | `string` \| `string[]` |  | Type selon le mode. |
| `searchable` | `boolean` | `false` | Active le mode combobox (filtrage). |
| `searchPlaceholder` | `string` |  | Placeholder du champ de recherche. |
| `size` | `small` \| `medium` \| `large` |  |  |
| `shape` | `rounded` \| `square` |  |  |
| `invalid` / `disabled` | `boolean` |  |  |

- **single** : sélectionner ferme le popover, coche « check » sur l'option choisie.

- **multiple** : toggle sans fermer, `aria-multiselectable`, cases à cocher dans les options ; le déclencheur affiche un **compteur** (« 3 sélectionnés »).

- **searchable=false** : pattern *listbox* + **typeahead** clavier (taper « F » surligne France, re-taper cycle).

- **searchable=true** : pattern *combobox* (`role="combobox"`, `aria-activedescendant`, `aria-autocomplete="list"`) + filtrage en direct (insensible casse/accents), message « Aucun résultat », champ en haut du popover.

```
<Select options={pays} value={v} onChange={setV} searchable />
<Select options={marques} selectionMode="multiple" value={sel} onChange={setSel} />

```

#### Search

Champ texte avec glyphe de recherche + bouton d'effacement. 3 tailles (échelle de saisie 16 / 16 / 14), `rounded` / `square`, variantes `default` / `filled` / `secondary`. Saisie et placeholder en Montserrat 400.

#### Input

Champ de saisie texte simple avec **label autonome** (jamais remplacé par le placeholder), `helpText` et `error` optionnels sous le champ. États : normal / focus (anneau clavier `:focus-visible` uniquement) / rempli / désactivé / erreur — tous rattachés aux tokens (`--mb-container-*`, `--mb-content-*`, `--mb-border-*`). Occupe 100 % de son conteneur ; `min-height` 48px (cible ≥44px) qui grandit au zoom texte. Label lié via `htmlFor`, `helpText`/`error` liés via `aria-describedby`, `aria-invalid` en erreur, erreur signalée par **icône + texte** (pas la couleur seule).

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `label` | `ReactNode` | | Label affiché au-dessus du champ. |
| `value` / `onChange` | `string` | | Mode contrôlé. |
| `defaultValue` | `string` | `''` | Mode non contrôlé. |
| `placeholder` | `string` | | Texte atténué dans le champ vide. |
| `helpText` | `ReactNode` | | Texte d'aide (masqué en erreur). |
| `error` | `ReactNode` | | Message d'erreur → état erreur. |
| `disabled` | `boolean` | `false` | |
| `required` | `boolean` | `false` | Ajoute l'astérisque. |

```
<Input label="E-mail" placeholder="vous@exemple.fr" helpText="Nous ne partagerons jamais votre e-mail." />
<Input label="Code postal" value={cp} onChange={e => setCp(e.target.value)} error="5 chiffres attendus." />
```

#### FormField

Le trio label / texte d'aide / message d'erreur, extrait d'`Input`. Il y existait depuis la v1, mais en privé : `Select`, `Textarea`, `SizePickerGroup` et `SwatchPickerGroup` n'avaient aucun moyen d'exprimer une erreur, donc **une sélection obligatoire non renseignée ne pouvait pas être signalée**.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `label` | `ReactNode` | — |  |
| `helpText` | `ReactNode` | — | Masqué pendant l'erreur : les deux ne s'empilent jamais. |
| `error` | `ReactNode` | — | Pose `aria-invalid` sur le contrôle et rend icône + texte. |
| `required` / `disabled` | `boolean` | `false` |  |
| `labelFor` | `string` | — | Id du contrôle quand il possède le sien. |
| `as` | élément | `div` | `fieldset` pour un groupe. |
| `labelAs` | `label` \| `legend` \| `span` | `label` | `legend` pour un groupe : un `<label>` ne peut pas nommer un `radiogroup`. |
| `children` | `ReactNode` \| `(control) => ReactNode` | — | Une fonction reçoit les attributs calculés du contrôle. |

Le câblage est tout l'intérêt, et se rate facilement à la main : `htmlFor` vers le contrôle, aide **et** erreur collectées dans `aria-describedby`, `aria-invalid` sur le **contrôle** et non sur le conteneur. `FormField` les passe à son enfant par fonction pour qu'il ne puisse pas être câblé à moitié.

> **Quand ne pas l'utiliser** — Autour d'un `Input`, qui porte déjà son propre trio ; l'envelopper produirait deux labels. Autour d'un `Checkbox` isolé, dont le libellé est son propre label — mais un **groupe** de cases en a besoin.

#### Textarea

Saisie multiligne. Reprend exactement l'habillage de champ d'`Input` (mêmes tokens de bordure, même classe `.mb-field`, même anneau de focus clavier) pour que les deux ne divergent pas, mais ne porte aucun label : cela appartient à `FormField`.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `value` / `onChange` | `string` | — | Mode contrôlé. |
| `defaultValue` | `string` | `''` | Mode non contrôlé. |
| `rows` | `number` | `4` |  |
| `maxLength` | `number` | — |  |
| `showCount` | `boolean` | `false` | Compteur de caractères. Exige `maxLength`. |
| `resize` | `resize` | `vertical` |  |
| `invalid` / `disabled` | `boolean` | `false` |  |

Le compteur visible est `aria-hidden` ; une région live polie n'annonce le décompte qu'à l'approche de la limite. Annoncer chaque frappe rendrait le champ inutilisable au lecteur d'écran.

> **Quand ne pas l'utiliser** — Pour une seule ligne : `Input`. Pour du texte riche : ce serait un éditeur, hors périmètre du design system.

#### Checkbox

Case 24px : `checked` / `indeterminate` / `invalid`, 4 états, label optionnel (Montserrat 16px). Expose `aria-invalid` en erreur.

#### RadioButton

Cercle 24px : `checked` / `invalid`, 4 états, label optionnel. À envelopper dans un `role="radiogroup"` avec un `name` partagé.

#### Toggle

Interrupteur 52×32 : on (noir) / off (grey), poignée blanche, 4 états. `role="switch"`. **Label ou `aria-label` obligatoire.**

#### RangeSlider

Range à deux poignées, remplissage noir + poignées 16px, libellés From/To. Opérable au clavier (flèches ±step, Home/Fin, PageUp/Down) ; bornes ARIA par poignée ; `aria-label` par poignée.

#### Chip

Pilule dismissible (grey-300), survol/press. Croix d'effacement dans une cible de 44px.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `wrap` | `boolean` | `false` | Autorise le libellé à passer sur plusieurs lignes. |

Par défaut le libellé reste sur **une seule ligne** (`white-space: nowrap`) — comportement historique, inchangé. Sur un site multilingue (neuf locales), un libellé de filtre traduit peut dépasser : passer `wrap` fait revenir le texte à la ligne, avec `overflow-wrap: anywhere` pour les mots composés longs (allemand, néerlandais).

```jsx
<Chip wrap selectable selected={on} onSelect={toggle} style={{ maxWidth: 180 }}>
  Wasserdichte Motorradhandschuhe
</Chip>
```

> `wrap` n'a d'effet que si la largeur du chip est bornée (`max-width`, cellule de grille, colonne de filtres). Sans contrainte, un chip s'étire et ne se coupe jamais.
>
> **Géométrie & cible tactile** — Le contenu reste centré verticalement (`align-items: center`) et la croix d'effacement garde sa cible de 44px. Un chip qui revient à la ligne dépasse toujours les 44px requis : ~58px à deux lignes, ~74px à trois. Le cas limite reste le chip **monoligne**, à 41px — inchangé par cette évolution, mais sous la cible.
>
> **État sélectionné** — Fond `--mb-container-catchy` et texte `--mb-content-on-catchy-neutral` s'appliquent au bloc entier : le rendu multiligne est correct, sans rupture de fond entre les lignes.

### Affichage & feedback

#### StarRating

5 étoiles + note & nombre d'avis, tailles medium / large. En mode `clickable`, se comporte comme un **radiogroup** accessible (étoiles entières) ; en affichage, un `aria-label` de synthèse résume la note.

#### ProgressBar

Piste 4px, remplissage `neutral` (noir) / `accent` (jaune). `aria-label` requis pour donner le contexte.

#### Divider

Filet 1px, horizontal / vertical. Prop `decorative` (défaut true) → `aria-hidden` si décoratif, sinon `role="separator"`.

#### Spinner

Chargement indéterminé. `ProgressBar` ne couvre que le déterminé ; `Spinner` couvre « il se passe quelque chose et on ne sait pas combien de temps ».

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `size` | `small` \| `medium` \| `large` | `medium` | 16 / 24 / 40px. |
| `tone` | `neutral` \| `accent` \| `inverse` | `neutral` | `inverse` sur surface sombre. |
| `label` | `string` | `Chargement…` | Annoncé via `role="status"`. Dire **ce qui** charge. |

**Mouvement réduit** — la rotation est ré-autorisée sous `prefers-reduced-motion`, à cadence ralentie. C'est la seule animation que le design system conserve sous cette préférence : un indicateur de chargement figé se lit comme une interface plantée, et une rotation pure sans translation n'est pas ce que la préférence vise. C'est l'échappatoire prévue par le filet global (CHANGELOG v1.1 B2), pas une exception silencieuse.

> **Quand ne pas l'utiliser** — Quand la durée est connue : `ProgressBar`. Pour un chargement de contenu dont la forme finale est prévisible : `Skeleton`, qui réserve la géométrie au lieu de la faire sauter.

#### Skeleton

Placeholder de contenu en cours de chargement. Réserve la géométrie finale pour que la page ne saute pas à l'arrivée des données.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `variant` | `text` \| `rect` \| `circle` | `text` | Ligne de texte / bloc-image / avatar. |
| `width` / `height` | longueur CSS | 100% | Le cercle vaut 40px par défaut. |
| `lines` | `number` | `1` | Nombre de lignes ; la dernière est courte, comme du texte réel. |
| `radius` | `none` \| `m` \| `l` \| `full` | selon la variante |  |

Le squelette est décoratif (`aria-hidden`). **L'attente s'annonce sur le conteneur** qui la possède, via `aria-busy="true"` : annoncer quarante placeholders serait du bruit.

> **Quand ne pas l'utiliser** — Quand la forme du contenu à venir est inconnue : un squelette qui ne ressemble pas au résultat désoriente plus qu'il ne rassure. Quand l'attente est brève (moins d'une demi-seconde) : ne rien montrer vaut mieux qu'un clignotement.

#### Alert

Message système persistant. Distinct de `CommercialBanner`, qui est marketing, et de `Toast`, qui est transitoire : une `Alert` reste jusqu'à ce que la condition qui l'a provoquée soit résolue.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `tone` | `info` \| `success` \| `warning` \| `error` | `info` | Intention sémantique. |
| `title` | `ReactNode` | — | Accroche courte, dans la couleur de tonalité. |
| `children` | `ReactNode` | — | Corps, toujours en `--mb-content-neutral`. |
| `action` | `ReactNode` | — | Une action sous le texte. |
| `onDismiss` | `() => void` | — | Fourni, affiche la croix. Absent, le message n'est pas effaçable. |

Surface blanche, bordure 1px dans la couleur de tonalité, icône et titre colorés. Pas de fond teinté : la palette n'a pas de teinte claire pour `warning` (l'orange n'a ni cran 050 ni 100), et la surface plate bordée est le dispositif de séparation propre à la marque (§2.7). Le sens ne repose jamais sur la couleur seule — chaque tonalité porte son glyphe et un préfixe textuel lu par les technologies d'assistance. `error` rend `role="alert"` (il interrompt) ; les autres `role="status"`.

> **Quand ne pas l'utiliser** — Pour une erreur qui appartient à un champ : le trio `label` / `helpText` / `error` d'`Input` la place au bon endroit. Pour une confirmation fugace : `Toast`. Pour une promotion : `CommercialBanner`.

#### Toast

Message transitoire. `Toast` dit « c'est fait » et s'en va ; `Alert` dit « il y a un problème » et reste.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `tone` | `neutral` \| `success` \| `error` | `neutral` | `success` et `error` ajoutent glyphe et préfixe masqué. |
| `title` / `children` | `ReactNode` | — |  |
| `action` | `ReactNode` | — | Une action, typiquement une annulation. |
| `onDismiss` | `() => void` | — | Appelé par la croix et par le minuteur. Sans lui, le toast ne part jamais. |
| `duration` | `number` | `5000` | Délai d'auto-fermeture en ms. `0` le désactive. |

`ToastContainer` porte l'empilement, la position (`top`/`bottom` × `left`/`center`/`right`) et **la région live** — un `aria-live` créé au même instant que son contenu n'est pas annoncé de façon fiable. Un seul conteneur par page. Le minuteur se met en pause au survol et au focus interne : une action atteinte au clavier ne s'évanouit pas sous les doigts. Sous 768px le conteneur passe pleine largeur en bas d'écran.

> **Quand ne pas l'utiliser** — Comme unique porteur d'une information. Il disparaît en cinq secondes, et un utilisateur de loupe d'écran peut ne jamais le voir : un numéro de commande, un motif d'échec de paiement doivent aussi exister dans la page. Pour une erreur bloquante : `Dialog` ou `Alert`.

#### Tabs

Onglets de même niveau, une section visible à la fois.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `items` | `{ value, label, content?, disabled? }[]` | `[]` |  |
| `value` / `onChange` | `string` | — | Mode contrôlé. |
| `defaultValue` | `string` | premier onglet actif | Mode non contrôlé. |
| `activation` | `automatic` \| `manual` | `automatic` | `automatic` sélectionne à la flèche (recommandation WAI-ARIA) ; `manual` attend Entrée ou Espace, pour un panneau qui déclenche une requête. |
| `fullWidth` | `boolean` | `false` |  |
| `label` | `string` | — | Nom accessible du bandeau. |

**Roving tabindex** : un seul arrêt de tabulation pour tout le bandeau, les flèches déplacent. Tabuler onglet par onglet est l'erreur d'implémentation classique. L'onglet actif est marqué par un filet 2px **et** par la couleur, jamais par la graisse — le readme §2.2 l'interdit, et cela déplacerait les largeurs du bandeau.

**Mobile** — sous 768px le bandeau défile horizontalement au lieu de passer à la ligne : un retour à la ligne casse la lecture « une rangée de pairs » qui rend les onglets lisibles. Au-delà de quatre ou cinq onglets sur téléphone, `Accordion` est la meilleure structure. La bascule est **documentée, pas automatique** : seul le consommateur sait si ses sections sont des pairs.

> **Onglet désactivé** — Dans cette palette `--mb-content-disabled` vaut le même gris que `--mb-content-quiet`, qui est déjà la couleur de l'onglet non sélectionné. Le désactivé se signale donc par un **barré** (convention « épuisé = barré » du `SizePicker`) et un suffixe masqué « (indisponible) », pas par la couleur.

> **Quand ne pas l'utiliser** — Quand l'utilisateur doit comparer deux sections : elles ne sont jamais visibles ensemble. Quand les sections ne sont pas de même niveau : c'est de la navigation, pas des onglets. Quand il y en a plus de six : la lecture du bandeau devient plus coûteuse que le contenu.

#### Table

Données tabulaires. Sémantique `<table>` réelle : `<caption>` (le nom accessible), `<thead>`, et `scope` sur chaque cellule d'en-tête. Une grille de `<div>` n'est annoncée comme rien, et une table sans `scope` force l'utilisateur de lecteur d'écran à devinener quel en-tête possède une cellule.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `caption` | `ReactNode` | — | Le nom accessible. En fournir toujours un. |
| `captionVisible` | `boolean` | `true` | `false` la réserve aux technologies d'assistance. |
| `columns` | `{ key, header, align?, render?, rowHeader? }[]` | `[]` | `rowHeader` sur la première colonne quand ses cellules nomment la ligne. |
| `rows` | `object[]` | `[]` |  |
| `rowKey` | `(row, i) => Key` | index |  |
| `mobile` | `scroll` \| `stack` | `scroll` | Voir ci-dessous. |
| `density` | `compact` \| `medium` \| `comfortable` | `medium` | `comfortable` double environ l'espace vertical, pour une table courte qu'on lit plutôt qu'on ne balaie. |
| `hideHeader` | `boolean` | `false` | Masque la ligne d'en-tête à l'écran en gardant les `<th scope>` dans l'arbre d'accessibilité. |
| `scrollLabel` | `string` | `caption` | Nom accessible de la zone défilante. |
| `children` | `ReactNode` | — | Balisage `<thead>`/`<tbody>` brut, en contournant `columns`/`rows`. |

**Deux stratégies mobiles, documentées et choisies explicitement.** `scroll` garde la forme dans un conteneur défilant **focusable** — une zone défilante inatteignable au clavier est une impasse et une violation axe. `stack` transforme chaque ligne en bloc de paires libellé / valeur sous 768px : juste pour deux ou trois colonnes de données produit, faux pour un guide des tailles, où comparer d'une ligne à l'autre **est** le contenu. Aucun choix automatique : seul le consommateur sait si la comparaison entre lignes fait le sens.

> **Sans en-tête** — `hideHeader` répond au cas de la fiche technique à deux colonnes, où « Caractéristique / Valeur » n'apprend rien au lecteur voyant. Les cellules d'en-tête restent dans l'arbre d'accessibilité et le lecteur d'écran annonce toujours « Poids · 1 490 g ». Supprimer le `<thead>` priverait la table de ce qui en fait une table.

> **Quand ne pas l'utiliser** — Pour de la mise en page : la sémantique de table dit « ces données se comparent », ce qui est faux d'une grille de tuiles. Pour une liste à une seule colonne : `<ul>`. Au-delà de quelques dizaines de lignes, il faut de la pagination ou du tri, que ce composant ne porte pas.

### E-commerce (dossier `commerce/`, groupe « Commerce »)

#### QuantityStepper

Sélecteur de quantité du panier et de la fiche produit. Métier et non primitive : il encode une quantité d'achat, avec un plafond de stock et un plancher qui vaut soit 1 (une ligne de panier), soit 0 (la retirer).

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `value` / `onChange` | `number` | — | Mode contrôlé. `onChange` reçoit une valeur déjà ramenée dans l'intervalle. |
| `defaultValue` | `number` | `1` | Mode non contrôlé. |
| `min` | `number` | `1` | `0` quand atteindre zéro retire la ligne. |
| `max` | `number` | `99` | Normalement le stock restant. |
| `step` | `number` | `1` |  |
| `disabled` | `boolean` | `false` |  |
| `label` | `string` | `Quantité` | Nom accessible du champ, masqué à l'écran. |

Le champ est un vrai `<input type="number">` : le pavé numérique apparaît sur mobile et la valeur se tape au lieu de se taper trente fois. Les deux boutons sont des cibles de 44px et passent en `disabled` aux bornes plutôt que de rester cliquables sans effet.

> **Quand ne pas l'utiliser** — Pour un nombre quelconque dans un formulaire : `Input`. Au-delà d'une dizaine d'unités attendues, la saisie directe est plus rapide que l'incrément et le pas à pas devient une gêne.

#### SizePicker & SizePickerGroup

Tuile de taille sélectionnable (sélectionnée = bordure noire ; épuisée = barré). La sélection est portée par **`SizePickerGroup`** :

| `selectionMode` | Sémantique | Clavier |
| --- | --- | --- |
| `single` (fiche produit) | radiogroup / radio | Flèches, roving tabindex |
| `multiple` (filtres) | group / checkbox | Tab + Espace |

> **Trois états de disponibilité, pas deux.** `unavailable` est une taille épuisée qui **reste cliquable, focusable et atteinte par les flèches** ; elle déclenche `onChange` et `onUnavailableSelect`. C'est le cas de la fiche produit : le client dit quelle taille il voulait, la page répond par des références similaires. Une tuile sur laquelle il ne peut même pas cliquer est une impasse, et le signal le plus utile de la page est justement celui-là. `disabled` reste inerte : la taille n'existe pas pour ce produit, il n'y a rien à proposer. Le rendu des deux est volontairement identique — barré et grisé, « pas en stock » dans les deux cas ; seule l'opérabilité change.

#### SwatchPicker & SwatchPickerGroup

Pastille de couleur (+ label optionnel), sélection = coche + anneau noir. Même modèle de groupe que SizePicker (`selectionMode` single / multiple). La coche s'adapte automatiquement (claire/foncée) à la couleur du swatch.

#### StockInfo

Pastilles de niveau de stock + label (in / low / out). Couleurs issues des tokens (succès / warning / erreur), texte contrasté, `role="status"`. L'info n'est jamais portée par la seule couleur (nombre de pastilles + label).

#### Sticker

Badge produit. `variant` `neutral` (gris) / `accent` (jaune) / `catchy` (noir).

#### Price

Affichage de prix produit. `variant` `default` (noir) / `reduced` (rouge — prix promotionnel, token `--mb-content-error`). `caption` optionnelle au-dessus de la valeur (« Prix public : 429,95€ », « Ancien prix : … »). Montserrat 600 16px / caption 400 12px.

#### MotorcycleSelector

Sélecteur moto en cascade (Genre · Marque · Année · Modèle) servant à qualifier une moto avant de vérifier la compatibilité d'une pièce. Chaque ligne ouvre une liste plein panneau avec `Search`, plutôt qu'un menu déroulant tronqué : le composant se place tel quel dans une `SideSheet` (desktop) ou une `BottomSheet` (mobile). `steps` décrit les niveaux ; les options d'un niveau peuvent être une fonction de la sélection courante (modèles de la marque choisie). `cascade` (défaut) verrouille un niveau tant que le précédent est vide et réinitialise les niveaux aval. Contrôlable via `value` / `onChange`, `onValidate` reçoit la sélection complète.

#### VariantPicker

Sélecteur de déclinaison produit : rangée de vignettes image, indicateur 2px sous la vignette active, puce de débordement « +N » au-delà de `max`. Boutons opérables au clavier, cible ≥44px, groupe labellisé. Il choisit une **déclinaison** représentée par une photo — pour une valeur de couleur (pastille unie), utiliser `SwatchPicker`. Les libellés restent en « coloris », vocabulaire produit côté client.

> Nommé `Colors` jusqu'en v1.2. Le dossier, les exports et les classes CSS (`.mb-variant-picker`, `__thumb`, `__more`) ont été renommés en v1.3 ; ses propres props (`items`, `selected`, `max`, `onSelect`, `onMore`, `moreLabel`) sont inchangées.

#### ProductCard

Tuile produit e-commerce (kit Ecom Library) composée à partir des composants du kit : hero carré (image produit, Sticker de remise + pastille bénéfice optionnelles) puis marque + titre, `Price` (caption optionnelle), `StarRating`, `StockInfo` et `VariantPicker`. 100 % tokens.

Les déclinaisons pilotent la tuile : toute donnée produit portée par une entrée `variants[i]` (`image`, `priceValue`, `priceOld`, `priceDiscount`, `stockLevel`, `discount`, `brand`, `title`…) remplace la prop de la carte quand cette vignette est sélectionnée. Props : `variants`, `variantsMax`, `selectedVariant`, `defaultSelectedVariant`, `onVariantSelect`, `onVariantsMore`, `reserveVariantsSpace`. Les alias `colors*` hérités d'avant la v1.3 sont **supprimés en v3.0.0**.

### Navigation

- **Breadcrumb** — Fil d'Ariane à séparateurs chevron, page courante en noir. Liens soulignés (au moins au survol/focus), séparateurs `aria-hidden`.

- **Pagination** — Items de page (courant en jaune) + précédent / suivant. Cible 44px, logique d'ellipse pour les longues listes (1 … 4 5 6 … 40).

- **SliderIndicator** — Indicateur de carrousel/scroll : `variant="bar"` (segment noir sur piste grise) ou `variant="dots"` (points de position, short / long).

- **BackToTop** — Bouton flottant de retour en haut de page, réservé au desktop. Apparaît quand la page a défilé de `showAfter` hauteurs d'écran (défaut `2.5`, soit deux à trois « scrolls ») et se masque en approchant du haut ; sous `minWidth` (défaut `1024`) il ne s'affiche jamais. Pastille grise (`--mb-container-quiet`), icône seule.

  | Prop | Type | Défaut | Rôle |
  | --- | --- | --- | --- |
  | `showAfter` | `number` | `2.5` | Seuil d'apparition, en hauteurs de fenêtre défilées. |
  | `minWidth` | `number` | `1024` | Largeur mini d'affichage. `0` = tous les formats. |
  | `size` | `'medium'` \| `'large'` | `'medium'` | Boîte 48px (icône 20) / 56px (icône 24). |
  | `label` | `string` | `'Haut de page'` | Libellé accessible (icône seule à l'écran). |
  | `offset` | `number` | `24` | Marge par rapport aux bords droit et bas. |
  | `scrollElement` | `ref` \| `element` | fenêtre | Conteneur défilant observé et remonté. |
  | `forceVisible` | `boolean` | `false` | État forcé, pour la documentation. |

  Apparition en fondu + montée de 8px (`--mb-duration-base`), remontée en `scroll-behavior: smooth` ; les deux repassent en instantané si `prefers-reduced-motion`. Masqué, il est retiré du parcours clavier (`tabIndex={-1}`, `aria-hidden`).

  ```jsx
  <BackToTop />
  ```

### Conteneurs & overlays

- **Accordion** — En-tête + contenu dépliable, bascule +/−, filet supérieur. `aria-controls` + panneau `role="region"`.

  | Prop | Type | Défaut | Rôle |
  | --- | --- | --- | --- |
  | `headingSize` | `'150'` \| `'200'` \| `'250'` \| `'300'` | `'150'` | Taille typo de l'en-tête (14 / 16 / 18 / 20px). |
  | `headingLevel` | `2`–`6` | — | Enveloppe l'en-tête dans un `<hN>`. Absent = pas d'enveloppe. |

  **Taille d'en-tête** — Les crans reprennent l'échelle typographique existante (`--mb-font-size-150/200/250/300`) ; aucune valeur nouvelle n'est introduite. Le défaut `'150'` reproduit le rendu actuel. Cette prop remplace la surcharge CSS scopée que la fiche produit appliquait à ses accordéons.

  **Structure de titres** — Question vérifiée : un consommateur **ne peut pas** envelopper l'en-tête lui-même. Envelopper `<Accordion>` dans un `<h3>` placerait aussi le **panneau** à l'intérieur du titre — HTML invalide, et le lecteur d'écran annoncerait tout le contenu déplié comme faisant partie du titre. L'enveloppe doit entourer le seul bouton d'en-tête, qui est interne au composant. D'où la prop `headingLevel`, appliquée autour du bouton uniquement ; le panneau reste en dehors. Par défaut la prop est absente et **aucune** enveloppe n'est rendue : rendu strictement identique à aujourd'hui.

  ```jsx
  <Accordion label="Description" headingLevel={2} headingSize="200">…</Accordion>
  ```

  > Le `<hN>` est neutralisé visuellement (`margin: 0; font: inherit`) : il n'ajoute aucun style, uniquement de la sémantique.

- **SideSheet** — Panneau overlay ancré à droite (titre large / medium), en-tête + corps + pied. **Largeur unique `min(640px, 100vw)`** quel que soit l'usage (prop `width` disponible, à ne surcharger qu'en cas exceptionnel). Modale accessible (focus trap, Échap, `aria-labelledby`, blocage du scroll de fond).

- **BottomSheet** — Overlay ancré en bas, coins arrondis + poignée de glissement, en-tête + corps + pied. Mêmes exigences modale que SideSheet.

- **CommercialBanner** — Bandeau promo jaune pleine largeur, précédent / suivant. Rotation auto : contrôle pause, `aria-live`, respect de `prefers-reduced-motion`.

#### Dialog

Modale centrée bloquante. Troisième membre de la famille overlay : `SideSheet` s'ancre à droite et accueille un flux, `BottomSheet` s'ancre en bas pour le mobile, `Dialog` centre une décision courte et bloquante — « retirer cet article ? ».

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `open` | `boolean` | `false` |  |
| `title` | `ReactNode` | — | Rendu en `<h2>`, sert d'`aria-labelledby`. |
| `description` | `ReactNode` | — | Une ligne sous le titre, liée par `aria-describedby`. Y mettre la conséquence de la décision. |
| `onClose` | `() => void` | — |  |
| `footer` | `ReactNode` | — | Boutons d'action, alignés à droite. L'action confirmante en dernier. |
| `size` | `small` \| `medium` \| `large` | `medium` | 22 / 30 / 40rem, plafonné par la fenêtre. |
| `dismissible` | `boolean` | `true` | `false` retire la croix et désactive Échap et le clic sur le voile — pour une décision qui doit être prise. |

Même contrat d'accessibilité que les deux panneaux, par le hook partagé `useModalA11y`. Sous 768px le panneau passe en plein écran ancré en bas : une boîte centrée sur 390px laisse des marges inatteignables et éloigne les actions du pouce.

> **Quand ne pas l'utiliser** — Pour un flux à plusieurs étapes ou un formulaire long : `SideSheet`. Pour une confirmation qui n'a pas besoin de bloquer : `Toast` avec une action d'annulation coûte moins cher à l'utilisateur qu'une modale à valider.

#### Tooltip

Aide contextuelle sur un contrôle. S'ouvre au survol **et** au focus clavier, se referme sur Échap.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `content` | `ReactNode` | — | Le texte d'aide. Une à deux lignes ; ce n'est pas un popover. |
| `placement` | `top` \| `bottom` \| `left` \| `right` | `top` |  |
| `open` | `boolean` | — | État forcé, pour la documentation. |
| `children` | `ReactNode` | — | Le déclencheur : un élément unique capable de recevoir le focus. |

La visibilité est un état JavaScript et non une règle CSS `:hover`, précisément parce que le focus et Échap doivent aussi la piloter. Un tooltip qui ne répond qu'au survol est inatteignable au clavier — c'est la façon la plus courante de rater ce composant.

> **Quand ne pas l'utiliser** — Sur tactile, où le survol n'existe pas : rien d'essentiel ne doit vivre uniquement là. Comme libellé d'un contrôle : le tooltip complète un contrôle déjà nommé, il ne le nomme pas. Pour du contenu riche ou interactif : ce serait un popover, qui n'est pas encore au kit.

### Accessibilité

#### VisuallyHidden

Texte réservé aux technologies d'assistance. Utilise le motif clip-rect de `tokens/interactions.css` (`.mb-visually-hidden`), jamais `display: none` ni `visibility: hidden`, qui retirent le texte de l'arbre d'accessibilité en même temps que de l'écran.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `as` | élément | `span` |  |
| `focusable` | `boolean` | `false` | Révèle le contenu lorsqu'il reçoit le focus clavier. |

> **Quand ne pas l'utiliser** — Pour masquer du contenu à tout le monde : `hidden` ou `display: none`. Pour cacher une icône décorative aux lecteurs d'écran : `aria-hidden`, qui fait l'inverse. Et jamais pour dupliquer un texte déjà lisible : l'annonce serait doublée.

#### SkipLink

Évitement de bloc (WCAG 2.4.1) : premier élément focusable de la page, il permet de sauter l'en-tête et sa navigation pour atteindre le contenu. Invisible tant qu'il n'a pas le focus, il apparaît alors épinglé en haut à gauche.

| Prop | Type | Défaut | Rôle |
| --- | --- | --- | --- |
| `href` | `string` | `#main` | Fragment du point de repère principal. |
| `children` | `ReactNode` | `Aller au contenu` |  |

À rendre comme tout premier enfant de `<body>`. **La cible doit pouvoir recevoir le focus** : lui poser `tabIndex={-1}`.

> **Quand ne pas l'utiliser** — Sur une page sans bloc répété avant le contenu : le lien n'évite rien. Il ne remplace pas les points de repère ARIA (`<header>`, `<nav>`, `<main>`), il les complète.

### Marque & structure

- **Icon** — Set de glyphes ligne & fill style Remix (`<Icon name size />`), peint en `currentColor`.

### Composants (organisms)

Assemblages de primitives, regroupés dans l'onglet Design System sous le groupe **« Component »**.

- **MotorcycleSelector** — Sélecteur moto en cascade (Genre · Marque · Année · Modèle), listes plein panneau avec recherche, à placer dans une `SideSheet` ou une `BottomSheet`.
- **Header** — En-tête e-commerce : barre utilitaire sombre (items de réassurance + sélecteur pays/devise), puis barre noire avec bloc **MENU** jaune, `Logotype` inverse, champ `Search` produit et action « fermer la recherche ». Ajout hors kit strict (organism assemblé), cibles tactiles ≥44px, focus clavier hérité de `.mb-focusable`.

---

## 6. Cartes spécimens (Guidelines)

Affichées dans l'onglet Design System, sous le niveau Guidelines :

- **Brand** : logo sur clair, logo sur sombre.

- **Colors** : échelles jaune, gris, rouge, vert, bleu + conteneurs / contenus / bordures sémantiques.

- **Typography** : échelle de titres, échelle de corps, échelle mobile, familles.

- **Spacing / Sizing / Radius** : tables d'échelle.

- **Motion** : échelle de durées et de courbes (démonstration au survol).

- **Layout** : grille responsive.

---

## 7. Réserves & substitutions

- **Polices** — Chargées depuis Google Fonts (Montserrat, Inter). Si Motoblouz utilise des webfonts licenciées / auto-hébergées, les fournir pour un remplacement en `@font-face`.

- **Icônes** — Set maison extrait verbatim du Figma (`primitives/Icon`, 17 glyphes), peint en `currentColor`.

- **Grille responsive** — Proposition, absente de l'export de tokens.

- **Motion / états** — Couverts depuis la v1.1 par `tokens/motion.css`. L'échelle est dérivée de l'usage constaté ; elle n'est **pas** issue de l'export Tokens Studio et doit être remontée dans `uploads/tokens.json` lors du prochain aller-retour Figma.

### Couverture du kit Figma — familles consolidées / écartées

Le kit « MB — Components » expose **99 « familles »** au sens brut du décompte Figma (chaque symbole autonome + chaque set, doublons de pages compris). Le design system implémente **32 composants** qui couvrent **toutes les fonctionnalités distinctes**. L'écart provient uniquement d'artefacts de comptage, pas de composants manquants :

| Entrées Figma | Traitement | Raison |
| --- | --- | --- |
| `search/…` (≈ 64 variantes : `Default/Large/Rounded`, `Secondary/Neutral/Hover/Small/Square`, `Tertiary/…`, `Filled/…`…) | **Consolidées dans `Search`** | Ce sont les variantes (variant × tone × state × size × shape) d'un seul champ. Un composant paramétré est le bon pattern design system — pas 64 composants. |
| `progress bar/Accent` | **Consolidée dans `ProgressBar`** | Simple variante de `tone` (`accent`) du même composant. |
| Sets dupliqués entre pages (`accordion`×3, `divider`×3, `chip`×2, `link`×2, `radiobutton`×2, `sticker`×2, `stock info`×2, `swatch picker`×2, `Toggle`×3, `Size picker`×2, `sideSheet`×2, `slider indicator`×2, `breadcrumb`×2…) | **Un seul composant chacun** | Le même composant apparaît sur plusieurs pages du fichier ; le décompte les additionne. |
| Glyphes d'icônes (`arrow-*`, `check-fill`, `close-*`, `star-*`, `search-line`, `add-line`, `subtract-fill`…) | **Regroupés dans `Icon`** | Set de 17 glyphes rendu via `<Icon name />` — un data-set, pas un composant par glyphe. |
| Sous-parties internes (`itemPagination`, `.label` rendu inline, `dropdownMenu`, `Label`/`swap content`/`Orientation=…` internes) | **Intégrées à leur parent** (`Pagination`, `Breadcrumb`, `Select`, `RangeSlider`, `CommercialBanner`) | Nœuds d'auto-layout privés, non exposés comme API publique. Le menu déroulant (`dropdownMenu`) est fusionné dans `Select` (popover interne). |
| `.footer`, `.heading` | **Retirés volontairement** | Supprimés à la demande de l'équipe (composants de marque/structure hors périmètre). |

> En résumé : les **familles distinctes** du kit sont toutes construites. Le compteur aplatit l'arbre de variantes Figma en « familles » ; re-scinder `Search` ou `Icon` en dizaines de composants irait à l'encontre du pattern design system et n'est **pas** prévu.

---
