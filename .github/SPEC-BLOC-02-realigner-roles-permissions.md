# SPEC-BLOC-02 — Réaligner rôles et permissions

## Objectif

Transformer les rôles métiers annoncés par le produit en réalité technique stable.

Aujourd'hui, plusieurs rôles existent dans le discours mais pas dans les guards réels, ou sont rabattus sur `ROLE_ADMIN` / `ROLE_USER`. Ce bloc doit remettre une seule vérité sur les permissions, les menus, les escalades et l'audit.

## Problèmes à corriger

1. `responsable_atelier`, `responsable_magasin` et `comptable` existent partiellement dans le produit mais pas comme contrat technique homogène.
2. `ROLE_ADMIN` sert de béquille générique pour compenser l'absence de vraie modélisation métier.
3. Le super-admin est partiellement mélangé à des logiques de rôle métier opérationnel.
4. Les escalades automatiques et les guards ne ciblent pas toujours des rôles réellement matérialisés.
5. Les menus et cockpits exposent des zones non alignées avec les responsabilités réelles.

## Décisions figées

1. Un rôle métier annoncé doit correspondre à des permissions vérifiables dans le back et visibles dans le front.
2. `ROLE_SUPER_ADMIN` est un rôle de plateforme, jamais un rôle métier d'atelier.
3. `ROLE_ADMIN` ne doit plus simuler à lui seul responsable atelier, responsable magasin et comptable.
4. L'audit global relève du super-admin ; l'admin local ne doit voir qu'un périmètre atelier borné.

## Périmètre inclus

- Mapping rôles legacy / Symfony / rôles métier
- Guards controllers et voters
- Menus et accès frontend par rôle
- Escalades automatiques et ciblage des notifications
- Visibilité audit globale vs audit atelier

## Hors périmètre

- Refonte complète des cockpits direction / atelier
- Refonte fonctionnelle détaillée de chaque module métier

## Fichiers de départ à relire avant implémentation

Backend :
- backend/src/Entity/User.php
- backend/src/Service/UserRoleMapper.php
- backend/src/EventSubscriber/UserRoleMetierSyncSubscriber.php
- backend/src/Security/RolePermissionVoter.php
- backend/src/Security/UserSecurityGuard.php
- backend/src/Entity/AuditLog.php
- backend/src/Controller/AdminUserProvisioningController.php
- backend/src/Controller/DemandeTravauxSuppController.php
- backend/src/Controller/FacturationController.php
- backend/src/Controller/StatistiquesController.php
- backend/src/Controller/AdminAtelierController.php

Frontend :
- frontend/composables/useAuth.ts
- frontend/middleware/auth.global.ts
- frontend/pages/admin/index.vue
- frontend/pages/admin/audit.vue
- frontend/pages/admin/users.vue
- frontend/pages/admin/roles.vue
- frontend/pages/admin/roles-metier/index.vue
- frontend/pages/index.vue

## Implémentation attendue par couche

### 1. Modèle de rôles

À faire :
- définir la matrice cible minimale pour les quatre périmètres critiques : super-admin, responsable atelier, responsable magasin, comptable
- décider, pour chaque rôle, son ancrage technique : rôle Symfony, permissions fines, ou combinaison explicite des deux
- supprimer les mappings implicites mensongers du type super-admin → responsable atelier
- éviter les alias silencieux qui font croire à un rôle autonome alors qu'il n'existe pas réellement

Contraintes :
- conserver la compatibilité minimale nécessaire avec l'existant sans prolonger l'ambiguïté
- si un rôle legacy reste stocké, il doit mapper vers une vérité explicite documentée

### 2. Guards, voters et contrôleurs

À faire :
- revoir les endpoints sensibles pour remplacer les guards trop larges `ROLE_USER` / `ROLE_ADMIN` par des permissions ou rôles métier cohérents
- réserver la lecture audit globale au super-admin
- vérifier les contrôleurs de facturation, statistiques, demandes complémentaires, provisioning et administration multi-atelier
- aligner les escalades automatiques sur des cibles réellement résolubles

Contraintes :
- ne pas introduire de régression silencieuse où un rôle perd un accès indispensable sans alternative prévue
- si un rôle n'est pas encore totalement matérialisé, le code doit au minimum rendre l'écart explicite plutôt que le masquer

### 3. Frontend — accès, menus et cockpits

À faire :
- mettre `useAuth()` et le middleware en cohérence avec la matrice cible
- retirer les entrées de menu qui reposent sur une fiction de rôle
- séparer ce qui relève d'un back-office admin, d'un cockpit direction, d'un cockpit atelier et d'un usage comptable
- éviter qu'un rôle voie un menu qu'il ne peut pas réellement exécuter côté API

Contraintes :
- ne pas laisser subsister de “faux accès” front menant à 403 systématiques
- ne pas continuer à appeler “direction” ou “chef d'atelier” une zone qui reste purement admin technique

### 4. Audit et gouvernance

À faire :
- distinguer audit global plateforme et audit atelier
- renforcer la visibilité du scope quand un super-admin agit en contexte global ou atelier
- vérifier les actions sensibles à logguer selon les nouveaux périmètres de rôle

Contraintes :
- ne pas exposer un flux d'audit global aux admins locaux par simple commodité

### 5. Tests

Back :
- tests d'accès par rôle sur endpoints sensibles
- tests sur le mapping de rôle métier vers permissions effectives
- tests de non-régression sur la protection du dernier super-admin

Front :
- tests sur `useAuth()` / middleware pour la visibilité des sections par rôle
- tests sur menus conditionnels et garde de navigation

## Critères d'acceptation

1. Les rôles responsable atelier, responsable magasin et comptable ne reposent plus sur un simple rabattement implicite vers `ROLE_ADMIN` ou `ROLE_USER`.
2. Le super-admin n'est plus remappé conceptuellement comme métier atelier.
3. Les guards API, les voters, les menus front et les escalades utilisent la même vérité de permissions.
4. L'audit global n'est plus lisible comme un audit admin local.
5. Un utilisateur ne voit plus d'entrée UI majeure qu'il ne peut pas réellement opérer.

## Validation manuelle

1. Tester un compte super-admin, un compte admin local, un compte responsable atelier, un compte responsable magasin, un compte comptable.
2. Vérifier menus, accès API, audit visible et pages statistiques.
3. Vérifier le comportement des escalades de demandes complémentaires.
4. Vérifier le changement de contexte atelier du super-admin et l'affichage du scope.

## Risques / vigilance

- Ne pas livrer une pseudo matrice de rôles qui continue à dépendre d'exceptions locales cachées.
- Ne pas déplacer la complexité du mapping legacy vers le front uniquement.
- Toute règle introduite ici devra être réutilisée dans les blocs suivants ; ne pas faire du temporaire opaque.