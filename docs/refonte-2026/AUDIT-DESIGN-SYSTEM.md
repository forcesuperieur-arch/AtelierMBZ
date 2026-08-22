<!-- Produit le 22/08/2026 par un audit en cinq dimensions, chacune menée
     indépendamment contre les 131 maquettes de la refonte, contre le
     miroir du design system et contre le code des deux fronts. -->

# Design system Paddock — verdict de complétude

## La réponse

**Non, pas l'application entière — mais rien ne manque qui oblige à redessiner la cible.** On peut coder aujourd'hui tout écran de consultation ; on ne peut coder ni un formulaire, ni une liste, ni un graphique sans inventer hors système.

**Le chiffre qui le porte : 0.** Zéro balise `<input>`, `<select>`, `<textarea>` ou `<label>` dans les 44 écrans du kit, et zéro contrat de champ saisissable sur 38 composants — alors que la réception, le devis, la prise de RDV et les huit réglages sont des écrans de formulaire (`Field.d.ts` : *« it displays, it does not type »*).

**Huit chantiers bloquants**, tous cernés : ~6 contrats à écrire, 3 arbitrages de tokens à trancher, 1 resynchronisation du miroir. Aucun ne remet en cause une décision de conception.

## Par dimension

| Dimension | Verdict | Couverture | Le manque le plus grave |
|---|---|---|---|
| **Surfaces** | Presque | 28/33 écrans complets, 8/8 canevas, toutes les surfaces hors atelier représentées | **« En atelier » (7a)** : la nav l'annonce, aucun écran ne la porte, l'app l'a en production |
| **Composants** | Partiel | 38 composants, 24 consommés ; 1 canevas sur 8 consomme le catalogue ; 10 motifs redessinés à la main | **Aucun contrat de saisie** — ni champ éditable, ni select, ni case à cocher, ni interrupteur |
| **Règles (tour 45)** | Partiel | 11/11 écrites, 8/11 dans `handoff/AGENTS.md`, 1/11 outillée, **0 contrôle exécutable**, 0 CI | L'anneau de focus est écrit juste puis **écrasé deux fois dans la même feuille**, sans alerte |
| **Tokens** | Partiel | 480 jetons, 13/13 valeurs 45b/45c portées par `--pk-*` | **4 de ces 13 valeurs sont contredites par une couche voisine du même `styles.css`** |
| **Langue & états** | Partiel | 8/8 états contractualisés mais **0/44 écrans en emploie un** ; 3 e-mails sur 9, 1 SMS sur 6 | Les e-mails du kit **ne survivent à aucun client de messagerie** (flex, `var()`, icon fonts, 0 `<table>`) |

## Ce qui bloque

À faire **avant** la première ligne de code applicatif.

1. **La couche de saisie.** Écrire les contrats absents : champ éditable (`type`, `name`, `onChange`, `required`), `Select`, `Textarea`, `DatePicker`, `QuantityStepper`, `Checkbox`, `Radio`, `Switch`. L'interrupteur est le cas le plus urgent : redessiné 4 fois, à 4 tailles (38×21, 40×22, 46×26), en `<span>` sans rôle, sans anneau de focus, sans cible 44/56 px — exactement la dérive qu'un contrat empêche.
2. **`DataTable` utilisable comme écran de liste.** Ajouter barre d'outils, `onRowClick`, tri, sélection, en-tête collant, zébrage. Preuve du manque : `<DataTable>` n'apparaît que dans 1 écran sur 44, contre `gridTemplateColumns` inline **51 fois dans 25 fichiers**.
3. **Une couche de visualisation.** Ni contrat ni carte spécimen, alors que le module Stat est une surface « tenue » et que la production porte déjà 5 composants (`Meter`, `CategoryBars`, `RankedList`, `StageFunnel`, `LineChart`). Sans ça, chaque écran de stat réinvente sa barre en `div`.
4. **Trancher le focus, une fois.** `tokens/mb-interactions.css` est la seule feuille qui peigne réellement un anneau (3 px, blanc en sombre) ; `--pk-focus-*` (2 px, jaune en sombre) n'est lu par personne. Côté app, `main.css` pose la bonne règle ligne 32 puis la neutralise lignes 814 et 1545. Décider la valeur, sortir la feuille de composants Gazoline du dossier `tokens/`, supprimer les deux surcharges, et écrire la règle dans `AGENTS.md` — elle n'y figure pas.
5. **Un seul commutateur de thème sombre.** `.theme-dark`, `[data-theme='dark']` et `.pk-workshop` cohabitent sans passerelle CSS ; deux canevas sur huit ont déjà choisi deux commutateurs différents, et les deux rampes ne s'accordent pas sur le fond de page (#141414 vs #000000). Le pont existe — mais dans `frontend/plugins/`, donc ni pour le mural, ni pour le papier, ni pour les e-mails.
6. **Les deux surfaces annoncées et absentes.** « En atelier » (7a) et le réglage « Ateliers » (8e) existent en production et sont dans la nav du kit sans destination. Les dessiner ou les retirer de la nav — la règle 7 interdit le lien mort.
7. **Resynchroniser le miroir local.** Il est en retard de plusieurs générations : `templates/atelier/index.html` charge trois fichiers absents (le canevas ne boote pas), `client/` a 2 fichiers sur 15, deux surfaces sur 33 y sont invisibles. Tout audit ou toute reprise partant du dossier local se trompera de constat.
8. **Les messages, si le lot notification est dans le périmètre initial.** Refaire `Mails.jsx` en tables HTML avec valeurs littérales (il n'existe aucun `tokens.json` aplati), et écrire le corpus SMS : 1 corps rédigé sur 6, aucune règle de canal, contre 26 gabarits déjà en production dont les textes violent les fondamentaux de contenu.

## Ce qui peut suivre

| Ce qui manque | Pourquoi ça ne bloque pas |
|---|---|
| ~19 surfaces de détail : VO desktop + livre de police, compagnon réception 390 px, tour 34 (⌘K, notifications, bascule d'atelier), 32b/32c, tour 38, tour 42, 6 e-mails, feuille d'atelier A4 | Le canevas, les jetons et le shell existent ; chacune se dessine dans son lot, sans dette système. Aucune n'invente de motif nouveau. |
| Contrats de motifs répétés : carte/section, surtitre, emplacement photo, signature, accordéon, plaque d'immat, `Pill` d'affichage | Dette de duplication mesurable (63 surtitres inline sur 31 fichiers, `--pk-surface` recomposé dans 20 écrans) mais chaque écran fonctionne. À contractualiser au fil des refactos. |
| `SidePanel` contourné par 5 panneaux sur 7, largeurs de 400 à 520 px contre 456 spécifiés | Écart cosmétique, rattrapable écran par écran une fois le contrat imposé. |
| Absence de `--pk-space-*` : 1856 px littéraux, `padding: 14px 18px` × 42, 13 hex orphelins | Dérive réelle, mais elle se rattrape par lint. Poser l'échelle en même temps que le point suivant. |
| Aucun contrôle exécutable, `_adherence.oxlintrc.json` branché nulle part, pas de `.github/workflows` | **Arbitrage** : l'absence de garde-fou ne bloque pas l'écriture du code — ce sont les *valeurs fausses* (points 4 et 5) qui bloquent. Brancher le lint et `check-interaction.mjs` juste après, sinon la dette se reconstitue. |
| Cible tactile violée par le kit lui-même (`Button` small = 41 px, `Field` dense = 38 px), `--pk-target-workshop` jamais lu | Corrigeable en une passe de valeurs, sans conséquence d'architecture. |
| Deux échelles de mouvement (120/160/200 vs 120/180/0) | **Arbitrage** : conflit déjà tranché par écrit (`AGENTS.md`, surcharge `paddock-app.css`). Reste à retirer les alias legacy exposés. Non bloquant. |
| Rédactionnel : glossaire métier (« SRC » jamais développé), catalogue de codes d'erreur, lexique des mots de statut, règle de registre du front atelier (fausse : 27 vouvoiements sur 9 fichiers), règle de confirmation (13c) | Une passe de readme. Le coût de ne pas le faire est la divergence de vocabulaire, pas l'impossibilité de coder. |
| États à d'autres échelles (47c mural, 49c poste sombre, 50d client 390 px), aucun composant d'état employé par les écrans, palette d'impression `@media print` | Les 8 contrats existent et sont justes ; il manque des variantes et de l'adoption, pas la fondation. Le papier ne devient bloquant qu'au lot documents. |
| `readme.md` annonce « 18 composants, 5 groupes » et « 18 cartes » pour 31 composants, 7 groupes, 19 cartes ; `app-semantic.css` (202 jetons) absent de l'index | Faux dimensionnement pour qui lit l'index — 40 % d'écart. Correction triviale, à faire tôt. |

## Ce qui a l'air de manquer et n'en est pas

**Ne pas ajouter.** Chaque ligne est une décision écrite, pas un oubli.

- **Dialog, Toast, Tabs générique, Avatar** — et donc le piège à focus et l'Échap de modale. `readme.md` l. 58 : *« ils appartiennent à Gazoline »*. Couvre `AppModal.vue`, `NotificationPopIn.vue` et les 5 modales métier. Seule manque la *règle* Paddock d'emploi de la confirmation (13c), pas le composant.
- **Les 23 contrats sans `.jsx`, `app-semantic.css`, `fonts/fonts.css`, 6 templates tronqués** — artefacts du miroir local. Le projet distant a les 31 `.jsx` et les feuilles manquantes ; `styles.css` n'est pas cassé. Ne jamais conclure depuis le dossier local (voir point 7).
- **Les 8 composants de `states/` non consommés par les écrans** — les templates sont des maquettes de chemin nominal. Les 8 sont implémentés ici et ont chacun leur jumeau Vue en production.
- **Suivi Live (4c)** — supprimé au profit du mural 47a, livré. **Version B du cockpit (52b)** — écartée au tour 52, dit par écrit. **Document de restitution A4** — écart assumé et documenté.
- **13a–13c** (glisser-déposer) : détails d'interaction à l'échelle 1, pas des surfaces. **29a–29h** : 8 états portés au bon niveau par 8 composants. **11c** : variante de thème (prop `theme`). **37b** : porté par `BaysScreen.jsx`.
- **Les cartes d'analyse et d'arbitrage** (1a, 1b, 5b, 8a, 45a–45c, 46a, 51a–51c, 52a…) n'appellent aucun template. Seul 1c devait être incarné, et il l'est.
- **Factures et Stock** : hors périmètre déclaré — et pourtant dessinés. C'est de la sur-couverture, pas un trou.
- **550 des 597 hex bruts des templates** correspondent exactement à un jeton existant : non-utilisation, pas insuffisance. Les composants, eux, sont propres (0 hex, 169 `var(--pk-*)`).
- **`--pk-duration-none` jamais référencé** : 0 ms veut dire « n'écrivez pas de transition ». **`prefers-reduced-motion`** est réellement implémenté à deux étages. **Les « 49 `outline: none` »** de la TODO sont résorbés (8 réels, le reste est du `.nuxt/` généré).
- **Un catalogue i18n** : Paddock est monolingue français, sans dépendance i18n dans les deux fronts. Des règles d'écriture valent mieux qu'un catalogue de clés.
- **Les 55 composants e-commerce Gazoline** : ce kit ne documente que la couche applicative Paddock.