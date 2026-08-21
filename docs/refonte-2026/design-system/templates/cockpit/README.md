# Cockpit SRC — 1440 × 900

L'étage réseau du service relation client. Tour 49b, arbitré au tour 52 : **version A retenue**, le cockpit est un étage à part, pas une page de la nav d'atelier.

| Fichier | Écrans |
| --- | --- |
| `CockpitApp.jsx` | `CockpitScreen` (vue d'ensemble, nav noire, cinq ateliers comparés), `CockpitSiteView` (l'atelier ouvert depuis le cockpit, bandeau jaune de provenance), `CockpitApp` (les deux enchaînés) |

**Règles.** Lecture seule : aucune action de production depuis le cockpit, on entre dans l'atelier pour agir. Un compte au rôle SRC atterrit ici après le SSO, pas sur le Stat d'un atelier. « Ouvrir » bascule dans l'app d'atelier complète, avec le filtre hérité du cockpit et un bandeau qui dit d'où l'on vient tant qu'on y est.

**À cliquer.** N'importe quel « Ouvrir » ou une entrée d'atelier dans la nav ; « Revenir au cockpit » en sort.

La version B (cockpit comme page d'accueil dans la nav commune, sélecteur SA/SC en haut) a été écartée au tour 52 et n'est pas recréée.
