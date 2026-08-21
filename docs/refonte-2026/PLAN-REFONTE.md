# Refonte Paddock 2026 — plan d'application

Source : bundle *Claude Design* « Paddock app redesign » (53 tours, 131 maquettes),
copié dans [`maquettes/paddock-refonte.dc.html`](maquettes/paddock-refonte.dc.html).

> Règle de lecture posée par le tour 51 : **quand deux maquettes se contredisent,
> la plus récente fait foi.** Les tours anciens restent comme trace du raisonnement.

---

## 1. Ce que la refonte ne change pas

Paddock n'invente ni couleurs ni typographie : il applique le **design system Motoblouz**,
déjà intégré au dépôt (`frontend/assets/css/tokens.css`, échelles `mb*` dans `main.css`,
palette Nuxt UI dans `app.config.ts`, branding `public/branding/`).

La refonte porte donc uniquement la **couche applicative** : navigation, découpage des
écrans, poste de travail, états, et règles de fond.

---

## 2. Les sept règles de fond (tour 45a)

Elles se relisent avant de dessiner ou de coder un écran nouveau.

| # | Règle | Nées de |
|---|-------|---------|
| 1 | **Montrer l'effet avant d'enregistrer.** Un réglage qui change le planning ou les prix affiche sa conséquence chiffrée, et refuse de s'appliquer tant que les dossiers touchés n'ont pas de sort. | 14a, 27a, 44b |
| 2 | **Rien de rétroactif.** Un devis signé, une facture émise, un OR en cours gardent les valeurs sous lesquelles ils ont été établis. | 27a, 28a, 37a |
| 3 | **Un chiffre mène quelque part.** Tout compteur est un lien, et la destination arrive filtrée sur ce que le chiffre disait. | 43b |
| 4 | **Le poste de travail ne se quitte pas.** Réception, restitution, détail d'un RDV : tout s'ouvre en panneau à droite du planning. | 10a, 36a |
| 5 | **Une erreur nomme la valeur attendue.** Jamais « une erreur est survenue » : la cause, la conséquence, la seule action qui sert — et une issue légitime plutôt qu'un blocage. | 29a → 29h |
| 6 | **Le temps calibre les forfaits, pas les gens.** L'écart vendu/pointé s'analyse par prestation ; aucun écran de pilotage n'attribue un temps nominativement. | 33b, 32c |
| 7 | **Dire ce que l'app ne fait pas.** Un module coupé quitte la nav ; il ne devient jamais une entrée grisée ni un lien mort. | 39a, 39b |

## 3. Thème sombre — un rôle, pas une préférence (tour 45b, amendé 51a)

- **Sombre** = les écrans qui vivent dans l'atelier, debout : **poste mécanicien** et
  **affichage mural**. Fond `#141414`, surfaces `#1f1f1f`, texte `#f6f6f6`, jaune `#f1ab00` inchangé.
- **Clair** = les écrans du bureau, lus assis et imprimés — y compris la **tablette de
  réception** (amendement du tour 51a : 45b était trop large).
- Le poste fixe le **défaut** ; la **bascule manuelle reste disponible partout**, mémorisée
  par appareil (décision du 21 août — `AppThemeToggle` est conservé tel quel).

## 4. Tactile, focus, mouvement (tour 45c)

| Axe | Valeur | Portée |
|-----|--------|--------|
| Tactile | **56 px** cible mini atelier, **44 px** bureau, **8 px** d'écart mini entre cibles voisines aux effets opposés | Aucune action essentielle derrière un survol, un appui long ou un glissement |
| Focus | anneau **2 px**, noir sur clair / jaune sur sombre, décalé de 2 px, **jamais supprimé** | `Échap` ferme le panneau et rend le focus à la ligne d'origine ; à l'ouverture, focus au 1er champ |
| Mouvement | **120 ms** états d'un contrôle · **180 ms** panneau latéral et dépliement · **0 ms** grille du planning, tri, saisie | Rien ne clignote ni ne rebondit ; `prefers-reduced-motion` ramène tout à 0 ms |

---

## 5. Les lots

### Lot 1 — Navigation cible : 17 entrées → 11 ✅
`frontend/layouts/default.vue` listait 16 entrées, dont quatre modules tranchés et le
Cockpit SRC. La cible (tour 52a) en garde 11, groupées :

| Groupe | Entrées |
|--------|---------|
| Pilotage | Stat |
| Atelier | Prise de RDV · Planning · Réception · En atelier · Ponts & Méca · Travaux compl. |
| Commerce | Devis · Clients · VO |
| — | Administration |

Sortent de la nav : **Suivi Live** (supprimé, 8a), **Fiches moto** (fusionné dans le
dossier moto, 4a), **Factures** et **Stock** (hors périmètre, 39a), **Cockpit SRC**
(étage à part, 52a).

### Lot 2 — L'étage SRC (tours 49b, 52a) ✅
Le cockpit n'est pas une page d'atelier : c'est un **étage** avec sa nav noire propre.
Un compte de rôle SRC atterrit dessus à la connexion. « Ouvrir » un atelier bascule dans
l'app d'atelier complète, avec un **bandeau de retour** jaune (« Vous êtes dans l'atelier
de Rouen · ouvert depuis le cockpit ») tant que l'on y est.

Conséquence : le sélecteur d'atelier de la barre du haut ne concerne plus que le **super
admin**. Le SRC change d'atelier en l'ouvrant depuis le cockpit — garder les deux
mécanismes, c'était laisser croire qu'on agit « sur le réseau ».

Recette : [`frontend/tests/e2e/refonte-nav-src.spec.mjs`](../../frontend/tests/e2e/refonte-nav-src.spec.mjs).

### Lot 3 — Les suppressions (tour 8a)
`tarifs.vue` · `suivi.vue` · `rdv/index.vue` · `admin/index.vue` — la donnée existe ailleurs.

### Lot 4 — Les fusions (tour 8a)
- `workshop.vue` + `admin/ponts.vue` → un écran Ponts & Méca (2c) ; l'onglet Absences
  en lecture seule fusionne avec Admin › Absences (15a).
- Les huit pages VO → **une liste + une fiche** (4b) ; l'origine rachat/dépôt est un champ.
- `motos.vue` + `clients/[id].vue` + `devis/[id].vue` → **une entrée, trois vues** :
  la fusion vaut pour la navigation, pas pour les écrans (arbitrage 51a).

### Lot 5 — Le poste de travail (règle 4 · tours 10a, 36a)
Réception, restitution et détail RDV s'ouvrent en **panneau à droite du planning**,
la grille reste lisible derrière.

### Lot 6 — Les erreurs (règle 5 · tours 29a → 29h)
Sept toasts de `planning.vue`, `reception/index.vue` et `AppErrorState.vue` retombent
sur « Erreur inconnue » / « Une erreur est survenue ». Le repli lui-même doit être écrit (29h).
À conserver tel quel : les toasts du planning qui nomment déjà l'action impossible.

### Lot 7 — Les écrans à créer
Affichage mural (47a, remplace 4c) · État des lieux photo (47b) · Mécanicien secondaires :
pièce, blocage, fin de journée (49a) · Concepteur de modèle de document (48a) ·
Fournisseurs d'envoi SMS/e-mail (48b).

### Lot 8 — Le front client
Landing (53a) · Mot de passe oublié et réinitialisation (53b) · CGV (53c) ·
Prise de RDV en ligne sans compte (50a) · Suivi public par lien (50b) ·
Mentions et confidentialité (50c) · États dont le lien expiré (50d).

---

## 6. La décision restée ouverte (tour 51c)

**L'affichage mural affiche-t-il « Karim · 1 h 40 / 2 h » ?** La règle 6 interdit
d'attribuer un temps nominativement sur un écran de pilotage, et un mur se lit de tout
l'atelier. La recommandation du tour 51c est l'**option A** : garder le prénom (il dit qui
appeler), retirer le vendu/pointé du mural (la barre de progression suffit à dire que ça
avance). À trancher avant le lot 7.
