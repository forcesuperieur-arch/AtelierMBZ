# Design system Motoblouz dans Paddock

Ce document explique comment le design system Motoblouz est appliqué à l'application,
ce qui en a été repris tel quel, ce qui en a été écarté et pourquoi, et comment
travailler avec sans le casser.

## En un paragraphe

L'application portait ses couleurs de thème sombre **écrites en dur** : un texte
`#E8E9ED`, une bordure `rgba(255,255,255,0.07)`, un jaune `#FFD200`. Il n'existait
qu'un seul thème possible, et le jaune n'était pas celui de la marque. Toutes ces
valeurs ont été remplacées par des **tokens** qui basculent entre un thème clair et
un thème sombre, avec le jaune officiel `#F1AB00`, la police Montserrat et les
formes du design system.

## Les trois étages de `tokens.css`

La source de vérité est **`frontend/assets/css/tokens.css`**. Une copie identique
vit dans `client-frontend/assets/css/tokens.css` : les deux applications Nuxt sont
des projets séparés, montés séparément dans Docker, un fichier partagé hors de leur
arborescence ne serait pas visible du conteneur.

| Étage | Exemples | Bascule avec le thème ? |
|---|---|---|
| **Marque** `--mb-*` | `--mb-accent: #F1AB00`, `--mb-grey-700`, `--mb-red-400` | Non. Une couleur de marque ne change pas de valeur. |
| **Sémantique** | `--surface-1`, `--content-2`, `--border-1`, `--accent`, `--error` | **Oui.** C'est ce que consomme l'application. |
| **Alias historiques** | `--dark2`, `--orange`, `--gray`, `--pad-text` | Suivent la sémantique. Ils existent pour que les feuilles déjà écrites basculent sans être réécrites. |

**Règle de travail : n'écrivez jamais une couleur en dur.** Utilisez un token
sémantique. Un `#FFFFFF` posé en dur redeviendra invisible dans un thème.

### Choisir le bon token

- une surface : `--surface-0` (fond de page) → `--surface-3` (survol, élément élevé) ;
- un texte : `--content-1` (titre, valeur) → `--content-3` (libellé, métadonnée),
  `--content-disabled` pour un élément désactivé ;
- une bordure : `--border-2` (filet discret), `--border-1` (séparation porteuse),
  `--border-control` (limite d'un champ ou d'un bouton — **doit** tenir 3:1) ;
- le jaune : `--accent` pour un **aplat**, `--accent-ink` pour le texte **posé sur**
  cet aplat, `--accent-content` pour du jaune **utilisé comme texte**,
  `--accent-graphic` pour une puce ou une série de graphique ;
- un état : `--success` / `--warning` / `--error` / `--info` pour un **aplat**,
  la variante `-content` pour du **texte**, la variante `-soft` pour un **fond
  teinté**, et `--on-success` / `--on-warning` / `--on-error` / `--on-info` pour
  le texte **posé sur** l'aplat.

> **Piège vérifié au contrôle** : l'encre posée sur un aplat ne suit PAS le
> thème, elle suit la **luminosité de l'aplat**. En thème sombre les aplats
> d'état sont les crans clairs de l'échelle : une pastille de compteur en blanc
> sur un rouge clair ne faisait que 2,7:1. D'où les tokens `--on-*`.
> Symétriquement, un aplat employé comme couleur de TEXTE est une erreur :
> `--success` (#179500) en texte sur blanc ne fait que 3,6:1, il faut
> `--success-content`.

## Bascule clair / sombre

Le thème se matérialise par l'attribut **`data-theme="dark|light"` sur `<html>`**.

- **Front staff** : `@nuxtjs/color-mode` (fourni par `@nuxt/ui`), configuré dans
  `nuxt.config.ts` avec `dataValue: 'theme'`. Il pose l'attribut **et** la classe
  `dark`, celle que lisent les composants Nuxt UI (`UCard`, `UInput`, `UTable`…).
  Les deux doivent rester alignés, sinon les composants Nuxt UI resteraient sombres
  sur une interface passée en clair. Le module injecte son propre script avant la
  première peinture : pas de flash.
- **Portail client** : pas de module (en ajouter un imposerait de reconstruire
  l'image Docker). Même comportement reproduit à la main dans
  `composables/useTheme.ts`, avec la **même** clé de stockage `paddock-theme` et le
  même repli, plus un script en ligne dans `nuxt.config.ts` pour éviter le flash.

Préférence par défaut : **système**, repli **sombre**. Le premier clic sur la
bascule rend le choix explicite et le mémorise.

> **Piège** : `frontend/app.vue` forçait `colorMode.preference = 'dark'` à chaque
> démarrage et enveloppait l'application dans un `<div class="dark">`. Tant que ces
> trois lignes existaient, aucune bascule n'était possible. Ne les réintroduisez pas.

## Composants Nuxt UI

L'application compte **375 composants Nuxt UI** (128 `UCard`, 102 `UInput`,
93 `UFormField`, 24 `UButton`, 18 `UTable`…). Ils ne lisent **pas** `tokens.css` :
leur accent vient de la palette Tailwind que Nuxt UI désigne dans
`app.config.ts`. Sans configuration, `--ui-primary` restait le **vert par
défaut du framework** — ni Paddock, ni Motoblouz.

Deux pièces sont donc nécessaires :

1. un bloc `@theme static` dans `main.css` qui déclare les échelles Tailwind
   `mbyellow`, `mbred`, `mbgreen`, `mbblue`, `mborange`, `mbgrey` (le pas 500
   porte la couleur de référence, c'est celui que Nuxt UI prend comme base) ;
2. `frontend/app.config.ts` qui pointe `primary`, `error`, `success`,
   `warning`, `info` et `neutral` sur ces échelles.

Résultat vérifié : `--ui-primary` vaut `#f1ab00` en thème clair et `#f4bc33` en
sombre (Nuxt UI remonte d'un cran en sombre, ce qui est le bon réflexe).

> **Ce bloc `@theme` est fragile.** Il déclare aussi l'échelle de rayons, parce
> que Tailwind n'émettait plus `--radius-md` et que `rounded-md` ne produisait
> alors **rien** (rayon 0 sur tous les composants Nuxt UI). Y ajouter des alias
> qui référencent une variable absente casse l'émission de tout le bloc, rayons
> compris. Toute modification ici se vérifie en lisant `--radius-md` dans le
> navigateur.

> **Défaut préexistant identifié, non corrigé.** Un `UButton` « solid » sort
> **sans fond** dans cette application : `bg-primary` ne produit rien, si bien
> qu'un bouton primaire de Nuxt UI est une étiquette noire sans aplat —
> illisible en thème sombre. Vérifié sur l'application **inchangée** : le défaut
> est antérieur à cette refonte, et il explique que l'application ait ses
> propres `.btn-primary` partout. Le sujet appartient au chantier « composant
> `AppButton` unique ».

## Graphiques

Un `<canvas>` **ne résout pas** les propriétés CSS personnalisées : une série
déclarée `backgroundColor: 'var(--accent)'` sortirait sans couleur. Les pages
continuent de déclarer des tokens — c'est ce qui permet aux graphiques de suivre le
thème — et `frontend/composables/useChartTheme.ts` les convertit en valeurs
calculées juste avant de les passer à Chart.js, puis recalcule à chaque bascule.

Si vous créez un nouveau graphique, passez par `BarChart` / `LineChart` /
`DoughnutChart` / `Sparkline` : la résolution y est déjà branchée.

## Icônes

L'application utilisait des **emoji** comme icônes — 457 occurrences, une
centaine de formes différentes. Le design system les proscrit au profit d'un
jeu tracé : **RemixIcon**. Ils ont tous été remplacés.

Une icône s'écrit **`<AppIcon name="i-ri-…" />`**, et rien d'autre.

### Pourquoi un composant plutôt que `<UIcon>` directement

Parce que les deux fronts doivent écrire le **même** balisage alors qu'ils
n'ont pas les mêmes moyens :

| | Front staff | Portail client |
|---|---|---|
| `components/AppIcon.vue` | enveloppe `UIcon` (`@nuxt/icon`, fourni par Nuxt UI) | rend du **SVG en ligne** |
| Source des tracés | collection `@iconify-json/ri` | tracés inscrits dans le fichier, **généré** |

Le portail n'a pas de module d'icônes et n'a pas à en gagner un pour trois
icônes : c'est une application publique dont le temps de premier affichage
compte. Son `AppIcon.vue` est donc **produit par un script** qui relève les
icônes réellement employées et n'inscrit que celles-là. Ne le modifiez pas à
la main.

> **Piège vérifié au build.** Par défaut, `@nuxt/icon` n'embarque que les
> **noms** et va chercher les tracés à l'affichage sur `api.iconify.design`.
> Un poste d'atelier sans accès sortant afficherait une interface **sans
> aucune icône**. `nuxt.config.ts` impose donc `clientBundle.scan` (les tracés
> employés entrent dans le bundle : 92 icônes, 26 Ko) et `fallbackToApi: false`
> (le repli réseau est interdit, une icône manquante se voit au build).

### Accessibilité

Une icône est **décorative** : `AppIcon` pose `aria-hidden` et il n'y a pas à
y revenir. Elle double toujours un texte visible.

Quand elle est le **seul** contenu d'un bouton, ce n'est pas l'icône qu'il faut
nommer mais **le bouton**, avec un `aria-label` — c'est lui que le lecteur
d'écran annonce. Le contrôle le vérifie et refuse toute commande muette. La
migration a ainsi révélé **31 boutons et liens** qui n'avaient déjà aucun nom
accessible, croix de fermeture des modales en tête.

### Ce qui n'est PAS devenu une icône

- **`→` dans une phrase** (« Réception → Atelier ») : c'est de la typographie,
  la remplacer couperait la phrase.
- **Les emoji d'un `<option>`** : un `<option>` natif ne peut pas contenir
  d'élément. Ils ont été **supprimés**, le libellé porte le sens.
- **Les emoji décoratifs en pleine phrase** (« Tout est terminé 🎉 ») :
  supprimés, ils n'apportaient aucune information.
- **Les libellés consommés comme texte pur** — ceux qui alimentent un message
  de notification ou une comparaison de chaînes : l'emoji est retiré, mais
  aucune icône n'est posée, une icône n'ayant pas sa place dans une chaîne.

### Ajouter une icône

1. choisir un nom dans RemixIcon et l'écrire `i-ri-…` ;
2. `node scripts/design/check-icons.mjs` — il refuse un nom qui n'existe pas.
   **Ce contrôle n'est pas facultatif** : un nom inventé ne lève aucune erreur
   à l'exécution, il rend un carré vide ;
3. si l'icône est destinée au **portail client**,
   `node scripts/design/build-client-icons.mjs` pour régénérer son composant.

## Palette de data-visualisation

Elle **n'est pas** dérivée de la palette de marque, et c'est volontaire. Elle
vient du travail fait pour la refonte de la page Stat : bande de clarté OKLCH
0,48–0,67, chroma ≥ 0,10, séparation daltonisme ΔE 14,2 (protan/deutan),
contraste ≥ 3:1. Une dérivation naïve de la palette Motoblouz remettrait du
rouge et du vert côte à côte — le pire couple en deutéranopie — et perdrait
cette validation.

Elle a donc été **conservée telle quelle** et seulement étendue au thème clair
(mêmes teintes, assombries juste ce qu'il faut pour tenir 3:1 sur blanc). Le
jaune de marque reste hors de cette bande : trop clair, il éblouit en grande
surface ; il sert d'accent d'interface et de trait pour une courbe unique.

Trois familles à ne pas confondre :

| Famille | Rôle | Ne jamais servir à |
|---|---|---|
| `--viz-1` … `--viz-5`, `--viz-other` | identité de **série** (catégoriel) | exprimer un état |
| `--ramp-1` … `--ramp-5` | progression **ordonnée** (étapes, paliers) | distinguer des catégories |
| `--success` / `--warning` / `--error` | **état** (bon / attention / critique) | identifier une série |

## Écarts assumés par rapport au design system

Le design system Motoblouz est une bibliothèque de **boutique en ligne**. Appliqué
tel quel à un ERP dense, il pose six problèmes concrets ; voici les arbitrages.

1. **Bordures en thème sombre.** Le DS pose `border-main: #A5A5A5`. Sur les
   centaines de bordures d'un ERP (tableaux, panneaux, cartes de pont) ce gris franc
   transforme l'écran en filaire. Le ton du DS est conservé pour les séparations
   porteuses (`--border-1`) et un cran discret a été ajouté pour les séparations de
   masse (`--border-2`).
2. **Gris de texte.** Le DS réserve `#A4A4A4` au texte désactivé. Paddock utilise ce
   ton pour des **libellés de formulaire**, or `#A5A5A5` sur blanc ne fait que
   2,5:1. Les tons de texte descendent donc d'un cran dans l'échelle officielle
   (grey-800 / grey-700) ; le gris clair du DS reste disponible en
   `--content-disabled`, que la WCAG dispense de contraste.
3. **Jaune en texte et en bordure.** `#F1AB00` sur blanc ne fait que 1,99:1 :
   inutilisable pour du texte, insuffisant pour une limite de composant.
   `--accent-content` descend à `--mb-yellow-900` en thème clair (5:1) et
   `--accent-graphic` à `--mb-yellow-800` (3,9:1). Le jaune de **remplissage** reste
   la couleur de marque, avec du noir dessus comme le prescrit le DS.
4. **Alerte.** `#F37004` ne fait que 2,95:1 sur blanc, juste sous le seuil des
   éléments graphiques : même teinte, ton assombri (`#D96500`).
5. **Surfaces en retrait du thème clair** très proches du blanc (`#FBFBFB` /
   `#F6F6F6`). Un cran plus foncé (`#ECECEC`, le « container quiet » du DS)
   faisait passer les libellés de puce et le jaune de texte sous 4,5:1. La
   séparation vient donc des **bordures**, ce que le DS demande explicitement
   (« Prefer borders over shadows for card separation »).
6. **Aucune astuce d'opacité.** Le DS l'écrit (« No opacity hacks — use explicit
   colors ») : les compteurs de puce du planning passaient par `opacity: .6`,
   remplacé par une graisse explicite.

Ces écarts existent pour tenir le niveau **WCAG AA**. Ils sont vérifiables :

```bash
node scripts/design/check-contrast.mjs     # 62 paires, deux thèmes
```

### Ce qui a été retiré parce que le DS l'exclut

- **Glassmorphism** : les 11 `backdrop-filter` ont disparu. Le DS demande
  explicitement un voile `rgba(0,0,0,0.5)` **sans flou** derrière les modales.
- **Dégradés sur les boutons** : le primaire est un aplat franc. Les états passent
  par la **teinte** (`--accent-hover`, `--accent-active`), plus par un
  déplacement ni un halo (« No bounces »).
- **Halo jaune** (`--shadow-glow`) : n'existe pas au DS, vaut `none`.
- **Lueurs d'ambiance** de la page de connexion : trois dégradés radiaux jaunes
  superposés qui délavaient le thème clair.
- **Violet et cyan** : absents du DS, redirigés vers le bleu `--info`.

### Formes

Le DS met tout l'interactif en **pilule** (999 px). C'est appliqué aux boutons.
Les **champs de saisie** gardent `--radius-m` (8 px, token officiel du DS) : une
pilule sur les centaines de champs d'un ERP dense nuit à la lecture en colonnes.

## Logos

Les fichiers de marque portaient la mention « Place on dark background » et
écrivaient le mot-symbole en blanc cassé : sur le thème clair il **disparaissait**.
`scripts/design/build-logos.mjs` produit des variantes `-light.svg` encrées en noir,
aligne le doré du tracé (`#D4A843`, un troisième jaune) sur le jaune de marque et
passe le mot-symbole en Montserrat.

Le choix du fichier est fait par `frontend/composables/useBrandLogo.ts` (staff) et
directement dans les composants du portail client.

## Outils

| Commande | Rôle |
|---|---|
| `node scripts/design/check-contrast.mjs` | Vérifie les seuils WCAG des tokens dans les deux thèmes. Sort en erreur si une paire passe sous le seuil. |
| `node scripts/design/apply-tokens.mjs --check` | Liste les couleurs écrites en dur qui devraient être des tokens. **Ne modifie rien.** |
| `node scripts/design/apply-tokens.mjs` | Applique la migration. Idempotent : rejouable sur des fichiers arrivés après coup. |
| `node scripts/design/sync-tokens.mjs [--check]` | Propage `tokens.css` vers le portail client. |
| `node scripts/design/build-logos.mjs [--check]` | Régénère les déclinaisons de logo. |
| `node scripts/design/check-icons.mjs` | Vérifie que chaque nom d'icône existe dans RemixIcon, qu'aucun emoji ne subsiste, et qu'aucune commande réduite à une icône n'est muette. |
| `node scripts/design/apply-icons.mjs [--check]` | Remplace les emoji par des icônes dans les gabarits. Idempotent. Signale ce qu'il ne sait pas traiter seul. |
| `node scripts/design/build-client-icons.mjs [--check]` | Régénère l'`AppIcon` du portail client à partir des icônes qu'il emploie. |

Le contrôle de contraste porte sur **62 paires** de tokens (texte, encre sur
aplat, bordures de composant, éléments graphiques), dans les deux thèmes. Il a
été complété au fil de la vérification : les gris de texte sont désormais
éprouvés sur les surfaces en retrait, pas seulement sur le blanc.

`apply-tokens.mjs` est **conscient de la propriété** : le même `#10B981` devient
`var(--success-content)` derrière `color:` et `var(--success)` derrière
`background:`. Il signale toute couleur qu'il ne sait pas classer plutôt que de la
laisser passer en silence.

### Reprendre un écran arrivé après la migration

```bash
node scripts/design/apply-tokens.mjs frontend/pages/mon-nouvel-ecran.vue
node scripts/design/apply-icons.mjs  frontend/pages/mon-nouvel-ecran.vue
node scripts/design/check-contrast.mjs
node scripts/design/check-icons.mjs
```

## Reste à faire

- **Composants uniques.** Il subsiste deux boutons primaires concurrents
  (`.btn-primary`, `.topbar-new-btn`) et plusieurs systèmes de modale. Ils partagent
  désormais les mêmes tokens, mais pas encore le même composant.
- **Casse des titres.** Le DS demande des titres en casse normale ; les titres de
  page restent en capitales (`text-transform: uppercase`), signature conservée.
