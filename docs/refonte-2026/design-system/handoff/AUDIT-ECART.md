# Audit d'écart — front staff (`frontend/`)

Relecture du code contre les fondations du design system Paddock. Établi le 21 août 2026, sur la version rattachée du dépôt `ateliermbz`.

**Portée.** Cet audit ne porte que sur les FONDATIONS — jetons, rayons, durées, ombres, icônes. L'écart entre les écrans du dépôt et leur état visé se lit dans les templates du design system (`templates/<surface>/`), qui sont la cible ; ce document ne le couvre pas.

**Verdict d'ensemble : le dépôt est propre sur le point le plus difficile.** Les couleurs sont dans des jetons, la sémantique est respectée, les commentaires du fichier de jetons documentent chaque écart avec son ratio de contraste. Ce qui suit est du réglage, pas une reprise.

Les tâches sont classées par le rapport bénéfice / effort. Chacune est autonome.

---

## 1. Les rayons ne suivent aucune échelle — ~200 occurrences

**Le constat.** Le design system pose quatre rayons et pas un de plus :

| Jeton | Valeur | Emploi |
| --- | --- | --- |
| `--pk-radius-block` | 4 px | bloc de planning |
| `--pk-radius-tile` | 6 px | tuile de pont, emplacement photo |
| `--pk-radius-card` | 8 px | carte, contrôle, contrôle segmenté |
| `--pk-radius-pill` | 999 px | pilule de filtre, action principale, compteur |

Le code emploie 3, 10, 12, 14, 16 et 20 px, la plupart du temps écrits en dur dans un attribut `style` inline. `pages/public/booking.vue` en aligne une trentaine à lui seul ; `pages/vo/**`, `pages/planning.vue`, `pages/admin/config.vue` et les cartes VO suivent.

**Pourquoi ça compte.** Six rayons pour trois familles d'objets, c'est ce qui fait qu'une carte de devis n'a pas l'air de la même application qu'une carte de planning. Personne ne le remarque écran par écran ; tout le monde le sent en passant de l'un à l'autre.

**La tâche.** Remplacer par le jeton correspondant :

```
3px  → var(--pk-radius-block)     (pastilles de légende, 10×10)
10px → var(--pk-radius-card)
12px → var(--pk-radius-card)
14px → var(--pk-radius-card)
16px → var(--pk-radius-card)      (sauf carte flottante de connexion, voir plus bas)
20px → var(--pk-radius-pill)      si c'est une pilule, sinon --pk-radius-card
```

Deux exceptions à garder telles quelles, elles sont motivées : la carte de connexion (`pages/login.vue`, 20 px) et la carte de compagnon VO (`pages/public/vo-companion.vue`, 20 px) sont des surfaces flottantes plein écran, pas des cartes de liste.

**Effort.** Une passe mécanique par fichier, plus une relecture visuelle des pages VO et du planning.

---

## 2. `pages/mural.vue` n'utilise aucun jeton — ~40 valeurs en dur

**Le constat.** L'affichage mural écrit ses couleurs directement : `#141414`, `#1f1f1f`, `#2f2f2f`, `#f1ab00`, `#7ee08a`, `#ff8095`, `#a5a5a5`, `#333`. C'est la seule page du dépôt dans ce cas.

**Pourquoi c'est arrivé, et pourquoi ce n'est pas grave.** Le mural est sombre en permanence — il ne bascule jamais. Écrire les valeurs en dur ne casse donc rien aujourd'hui. Mais ce sont exactement les valeurs du thème atelier `.pk-workshop`, recopiées à la main : `#1f1f1f` est `--pk-surface-raised`, `#2f2f2f` est `--pk-border`, `#f1ab00` est `--pk-accent`. Le jour où une de ces valeurs bouge dans le design system, le mural sera le seul écran à ne pas suivre.

**La tâche.** Poser `class="pk-workshop"` sur la racine de la page et remplacer :

```
#141414 → var(--pk-canvas)          #1f1f1f → var(--pk-surface-raised)
#2f2f2f → var(--pk-border)          #333    → var(--pk-border-quiet)
#f6f6f6 → var(--pk-ink)             #a5a5a5 → var(--pk-ink-quiet)
#6f6e6e → var(--pk-ink-muted)       #f1ab00 → var(--pk-accent)
#7ee08a → var(--pk-success-line)    #ff8095 → var(--pk-error-line)
```

Les trois tons vifs (`#7ee08a`, `#ff8095`, `#f59e4b`) existent déjà dans le thème atelier sous `--pk-success-line`, `--pk-error-line` et `--pk-warning-line`.

**Vérifier après.** Le mural est lu à quatre mètres : contrôler que rien ne passe sous 20 px et que les trois tons restent distinguables.

---

## 3. Trois durées de transition hors échelle — 12 occurrences

**Le constat.** Le design system n'admet que trois durées : 120 ms (état d'un contrôle), 180 ms (panneau, dépliement), 0 ms (grille, tri, saisie). Le code emploie `0.2s`, `0.3s` et un `cubic-bezier` maison.

| Fichier | Valeur | Remplacer par |
| --- | --- | --- |
| `layouts/default.vue` (4 occurrences) | `all 0.2s` | `var(--pk-duration-state) var(--pk-easing)` |
| `components/SidebarLink.vue` | `all 0.2s cubic-bezier(...)` | `var(--pk-duration-state) var(--pk-easing)` |
| `components/PlanningGrid.vue` (2) | `all 0.2s` | `var(--pk-duration-state) var(--pk-easing)` |
| `components/VONav.vue` | `all 0.2s ease` | `var(--pk-duration-state) var(--pk-easing)` |
| `pages/login.vue` (2) | `0.2s` | `var(--pk-duration-state)` |
| `pages/restitution/[token].vue` (2) | `0.2s` | `var(--pk-duration-state)` |
| `pages/public/booking.vue` | `all 0.2s` | `var(--pk-duration-state)` |
| `pages/public/suivi.vue` | `all 0.3s` | `var(--pk-duration-panel)` |
| `components/dashboard/ExplorerTable.vue` | `width 0.3s ease` | `var(--pk-duration-panel) var(--pk-easing)` |

**Au passage :** `transition: all` est à remplacer par la propriété réellement animée. `all` anime aussi ce qu'on ne voulait pas — c'est la cause habituelle des jauges qui glissent au chargement.

---

## 4. Trois ombres écrites en dur

`pages/login.vue` (deux), `pages/restitution/[token].vue`, `pages/public/vo-companion.vue`. Le design system pose `--elevation-1/2/3`, qui basculent avec le thème ; les valeurs en dur ne basculent pas et restent calées sur le sombre.

```
box-shadow: 0 12px 32px rgba(0,0,0,0.4)   → var(--elevation-3)
box-shadow: 0 24px 80px rgba(0,0,0,0.35)  → var(--elevation-3)
background: rgba(0,0,0,0.25)              → var(--scrim)  (voile de modale)
```

Le halo jaune de la connexion (`0 0 80px rgba(217,101,0,0.05)`) et l'anneau de focus (`0 0 0 3px rgba(217,101,0,0.15)`) sont des effets propres à cette page : les garder, mais exprimer la couleur avec `color-mix(in srgb, var(--accent) 15%, transparent)` plutôt qu'un RGB recopié.

---

## 5. Trois icônes Heroicons résiduelles

`pages/admin/notifications/providers.vue`, `pages/admin/audit.vue`, `pages/admin/users.vue` emploient `i-heroicons-magnifying-glass`. Le système pose le jeu maison Motoblouz en premier, Remix Icon pour le reste. Une loupe Heroicons n'a ni la même graisse de trait ni le même terminal que les Remix voisines.

```
i-heroicons-magnifying-glass → i-ri-search-line
```

`scripts/design/check-icons.mjs` existe déjà : y ajouter une règle qui refuse tout préfixe autre que `i-ri-` et le jeu maison.

---

## 6. Un alias historique laissé en repli

`pages/facturation/index.vue` ligne 158 : `background: var(--dark3, var(--surface-2))`. L'alias historique est en première position, le jeton sémantique en repli — c'est l'inverse de ce qu'il faut. À réduire à `var(--surface-2)`.

Ce cas est isolé, mais il vaut la peine de passer les alias `--dark*`, `--orange*`, `--gray*` en revue : ils existent pour que les feuilles anciennes basculent sans réécriture, pas pour être écrits dans du code neuf.

---

## Ce qui n'est PAS un écart

À ne pas « corriger » :

- **Les couleurs en dur dans `tokens.css` et `paddock-app.css`.** C'est leur rôle : ce sont les fichiers qui définissent les valeurs.
- **Les écarts documentés du design system Motoblouz** — bordures du thème sombre, jaune de texte descendu à `--mb-yellow-950`, vert et rouge remontés d'un cran en sombre. Chacun porte son ratio de contraste en commentaire, chacun est justifié. Les toucher casserait l'accessibilité.
- **`--mb-yellow-950`**, cran ajouté hors échelle officielle. Il prolonge l'arithmétique de l'échelle et n'existe que pour le jaune de texte sur fond clair. Légitime.
- **La palette de visualisation `--viz-*`.** Elle exclut volontairement le jaune de marque, trop clair pour une série de graphique. C'est une décision, pas un oubli.

---

## Ordre suggéré

1. Les rayons (§1) — le plus visible, le plus mécanique.
2. Le mural (§2) — un seul fichier, effet immédiat sur la cohérence du thème atelier.
3. Les durées et les ombres (§3, §4) — une passe, faible risque.
4. Les icônes et l'alias (§5, §6) — quelques minutes, à faire pendant qu'on y est.

Après chaque étape : `node scripts/design/sync-tokens.mjs --check` doit passer, et les captures Playwright de `tests-screenshots/` servent de comparatif avant / après.
