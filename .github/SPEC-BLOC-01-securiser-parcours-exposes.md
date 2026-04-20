# SPEC-BLOC-01 — Sécuriser les parcours exposés

## Objectif

Supprimer la confusion entre outils internes, PDA assistés et vrais parcours publics.

Le produit a figé les décisions suivantes :
- le compagnon atelier est un outil assisté de comptoir, pas un portail client autonome
- le compagnon VO est un outil interne assisté, pas un portail public autonome
- les seuls parcours publics autonomes tolérés sont ceux explicitement voulus comme tels : réservation, suivi, pages légales et éventuellement validation distante des travaux complémentaires si elle est maintenue
- aucun token, secret ou jeton de parcours ne doit apparaître en query string

Ce bloc doit ramener l'architecture exposée à une surface cohérente, minimale et défendable côté sécurité/RGPD.

## Problèmes à corriger

1. Des outils internes assistés sont encore exposés sous `/public` avec un modèle de tunnel autonome.
2. Des tokens circulent en query string dans les liens et QR codes.
3. Les payloads exposés par token sont trop riches pour leur finalité réelle.
4. Les routes publiques sont inégalement protégées côté front et côté back.
5. Certaines pages publiques légales ou métier ne sont pas réellement publiques de bout en bout.

## Décisions figées

1. Les compagnons atelier et VO sortent du périmètre “client final autonome”.
2. Tout token de parcours passe en segment de chemin ou via un lien court régénérable, jamais en query string.
3. Un tunnel tokenisé n'expose que les données strictement nécessaires à l'action immédiate.
4. Les pages publiques légales doivent être accessibles sans authentification quand elles sont citées dans un formulaire ou tunnel public.
5. Tout tunnel public conservé doit avoir rate limiting, messages d'erreur non bavards et journalisation minimale.

## Périmètre inclus

- Frontend routes publiques et middleware d'accès
- Backend controllers publics ou semi-publics
- Helpers de génération de liens et QR codes
- Payloads exposés par token
- Pages légales et tunnels publics conservés

## Hors périmètre

- Refonte complète UX de réception et VO
- Refonte globale des rôles et permissions métier
- Refonte complète du workflow de travaux complémentaires

## Fichiers de départ à relire avant implémentation

Frontend :
- frontend/pages/public/booking.vue
- frontend/pages/public/suivi.vue
- frontend/pages/public/companion.vue
- frontend/pages/public/vo-companion.vue
- frontend/pages/public/demande/[token].vue
- frontend/pages/public/mentions-legales.vue
- frontend/pages/public/politique-confidentialite.vue
- frontend/pages/planning.vue
- frontend/pages/vo/rachats/new.vue
- frontend/pages/vo/depots/new.vue
- frontend/composables/useVoHelpers.ts
- frontend/middleware/auth.global.ts

Backend :
- backend/src/Controller/PublicBookingController.php
- backend/src/Controller/SuiviController.php
- backend/src/Controller/CompanionController.php
- backend/src/Controller/PublicVoCompanionController.php
- backend/src/Controller/DemandeTravauxSuppController.php
- backend/src/Controller/PublicPhotoController.php
- backend/src/Entity/Trait/VOCompanionTrait.php
- backend/src/Service/TokenService.php

## Implémentation attendue par couche

### 1. Backend — périmètre d'exposition

À faire :
- recenser tous les endpoints réellement publics et les classer en trois familles : public autonome, assisté, interne
- retirer du périmètre public tout endpoint compagnon atelier/VO qui réalise des opérations métier larges ou modifie le référentiel principal sans validation interne
- si un mode assisté tokenisé reste nécessaire à court terme, le borner à la signature ou à la validation finale, pas à l'édition riche du dossier
- unifier le format des liens tokenisés en path segment, pas en query string
- appliquer un rate limiter sur tous les endpoints publics réellement conservés
- durcir les réponses d'erreur pour ne pas révéler la validité d'un token plus que nécessaire

Contraintes :
- ne pas casser la réservation publique si elle reste maintenue
- ne pas exposer de nouvelles routes publiques pour compenser une suppression
- si un endpoint est rebasculé en interne/authentifié, l'UI correspondante doit être réajustée dans le même bloc

### 2. Backend — payloads et minimisation

À faire :
- réduire les payloads des tunnels tokenisés au strict nécessaire
- supprimer des payloads publics les données non nécessaires à l'action immédiate : adresse complète, email, téléphone, historique, documents archivés, détails véhicule non utiles, états internes du dossier
- borner les opérations d'écriture publiques restantes à des actions finalisées et explicitement assumées

Contraintes :
- si une donnée n'est pas strictement utile à l'écran courant, elle sort du payload
- les documents sensibles ne doivent pas devenir téléchargeables par simple commodité depuis un tunnel assisté

### 3. Frontend — routing et surfaces visibles

À faire :
- corriger `auth.global.ts` pour que les vraies routes publiques soient réellement exemptées, et seulement celles-là
- retirer ou masquer les entrées UI qui ouvrent des compagnons “publics” là où le produit les requalifie en outils assistés
- remplacer tous les constructeurs de lien `?token=` par des routes à segment de chemin
- mettre à jour la génération locale des QR codes pour pointer vers le nouveau format
- rendre les liens vers les pages légales visibles dans les formulaires publics concernés

Contraintes :
- aucun lien public ne doit être cassé silencieusement ; si le parcours change, l'UI doit l'expliquer
- ne pas conserver un écran public orphelin sans back correspondant

### 4. Journalisation / traçabilité

À faire :
- journaliser au minimum l'accès et les actions sensibles sur tunnels publics conservés
- distinguer clairement accès public autonome et usage assisté si ce dernier reste temporairement tokenisé

Contraintes :
- pas de log bavard avec données personnelles inutiles
- pas de token en clair dans les logs applicatifs

### 5. Tests

Back :
- tests sur le format de génération des liens tokenisés
- tests de refus des anciens formats en query string si applicable
- tests de rate limiting sur les endpoints publics conservés
- tests de non-exposition des champs sensibles dans les payloads publics

Front :
- tests sur le middleware public/authentifié
- tests sur la construction des liens publics et QR codes

## Critères d'acceptation

1. Aucun token de parcours n'apparaît encore en query string.
2. Les compagnons atelier et VO ne sont plus traités comme tunnels publics riches autonomes.
3. Les seules routes publiques accessibles sont celles explicitement assumées par le produit.
4. Les pages légales publiques sont réellement accessibles sans connexion.
5. Les payloads publics ne contiennent plus de données non nécessaires à l'action immédiate.
6. Les parcours publics conservés ont rate limiting et messages d'erreur sobres.

## Validation manuelle

1. Ouvrir chaque QR/lien généré depuis planning, suivi, rachat et dépôt.
2. Vérifier qu'aucun URL ne contient `?token=`.
3. Vérifier qu'un utilisateur non authentifié ne peut accéder qu'aux surfaces réellement publiques.
4. Vérifier qu'un compagnon assisté ne permet plus de modifier silencieusement le dossier complet.
5. Vérifier que les pages légales sont accessibles depuis les formulaires publics.

## Risques / vigilance

- Ne pas casser un flux utilisé en prod sans solution de remplacement explicite côté front.
- Ne pas confondre “route publique” et “route tokenisée” : les deux doivent être justifiées.
- Ne pas déplacer le problème en conservant un tunnel quasi public mais sous un nouveau nom.