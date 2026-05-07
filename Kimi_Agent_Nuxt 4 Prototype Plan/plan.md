# Plan — AtelierPaddock Prototype Hyper-Complet v2

## Objectif
Transformer la maquette HTML v7 PRO (22 pages fonctionnelles + 3 pages design system) en prototype Nuxt 4 interactif, navigable, avec données mockées, pour servir de vue d'ensemble avant câblage sur le logiciel existant.

## Architecture
- **Framework**: Nuxt 4 (Vue 3, TypeScript)
- **Styling**: Tailwind CSS v3 avec tokens Paddock
- **State**: Pinia
- **Routing**: Hash mode (SPA statique)

## Pages existantes (à améliorer)
| Page | Fichier | Statut |
|------|---------|--------|
| Dashboard | `pages/index.vue` | Améliorer KPIs + interactivité |
| Planning | `pages/planning.vue` | Ajouter vue "Par mécano" |
| Atelier | `pages/atelier.vue` | Améliorer fidélité |
| Clients | `pages/clients.vue`, `clients/[id].vue` | OK, petites corrections |
| VO | `pages/vo.vue` | OK |
| Stock | `pages/stock.vue` | OK |
| Devis | `pages/devis.vue` | Améliorer avec HT/TVA/TTC |
| Factures | `pages/factures.vue` | OK |
| Login | `pages/login.vue` | OK |
| 2FA | `pages/2fa.vue` | OK |
| Wizard RDV | `pages/rdv/nouveau.vue` | OK |
| Détails RDV | `pages/rdv/[id].vue` | OK |
| Paramètres | `pages/params.vue` | Ajouter onglets manquants |

## Pages manquantes (à créer)
| Page | Fichier | Description |
|------|---------|-------------|
| Cover | `pages/cover.vue` | Page d'accueil / tableau de bord de navigation avec liens vers toutes les pages |
| Mécano FTN | `pages/mecano/[id].vue` | Fiche travail mécanicien avec checklist, timer, photos, bottom nav |
| Popin Travaux | `components/PopinTravaux.vue` | Modal 3 états : Demande/Réponse/Confirmé |
| Popin Signature | `components/PopinSignature.vue` | Modal rapport + signature digitale + email |
| Admin Users | `pages/admin/users.vue` | Gestion utilisateurs, rôles, permissions |
| Inventaire | `pages/stock/inventaire.vue` | Inventaire détaillé avec mouvements |
| EmptyState | `components/EmptyState.vue` | Composant état vide |
| Skeleton | `components/SkeletonLoader.vue` | Composant squelette de chargement |
| Slideover | `components/SlideoverClient.vue` | Panneau latéral création client rapide |
| Design System | `pages/design-system.vue` | Documentation interne des tokens et composants |

## Composants globaux à créer/améliorer
1. `EmptyState.vue` — État vide réutilisable (icon, titre, desc, action)
2. `SkeletonLoader.vue` — Loading shimmer (lignes, cards, table)
3. `SlideoverClient.vue` — Création client rapide latérale
4. `PopinTravaux.vue` — Travaux supplémentaires (3 états)
5. `PopinSignature.vue` — Rapport + signature + email

## Plan d'exécution

### Phase 1: Composants globaux (parallèle)
- Agent Composants: EmptyState, Skeleton, Slideover

### Phase 2: Pages spécialisées (parallèle)
- Agent Mécano: Page mécano FTN + 2 popins
- Agent Admin: Admin users + Inventaire + Design System
- Agent Amélioration: Dashboard, Devis, Atelier, Params

### Phase 3: Intégration
- Main agent: Merge, connexion des liens, build, deploy

## Design System v7 PRO (règles pour tous les agents)
- Couleur accent: #e85913, dark: #c44d00
- Header: #141428
- Body: #f8f7f4, page: #e4e3de
- Texte: primary #1a1a2e, secondary #5a5a6e, tertiary #8a8a9a
- Danger: #dc2626, Success: #16a34a, Warning: #d97706, Info: #2563eb
- Radius: sm 8px, md 12px, lg 18px, xl 24px
- Ombres chaudes (pas bleues)
- Boutons bottom mécano: 56px+ hauteur
- Touch target: 48px min
- Animations: hover -translate-y-0.5, glow accent

## Fichiers sources de référence
- Maquette HTML: `/mnt/agents/upload/maquettes-paddock-final-v7.html`
- Projet Nuxt: `/home/kimi/paddock-app/`
