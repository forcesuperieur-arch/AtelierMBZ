# Audit des rôles métiers

Document vivant.

Objectif : centraliser, au fil de l'audit, toutes les incohérences d'usage, failles métier, risques légaux/RGPD, dettes UX et propositions de simplification par rôle.

Règle de travail :
- on ajoute les constats au fur et à mesure, sans écraser l'historique
- on distingue ce qui est factuel, ce qui est risqué, et ce qui est proposé
- à la fin de l'audit de tous les rôles connus, ce document sert de base au plan de refacto

## Statut global

| Rôle | Statut | Remarques |
|---|---|---|
| Réceptionnaire | Audité | Première synthèse consolidée ci-dessous |
| Mécanicien | Audité | Synthèse consolidée ci-dessous |
| Responsable atelier | Audité | Synthèse consolidée ci-dessous |
| Responsable magasin / direction | Audité | Synthèse consolidée ci-dessous |
| Gestionnaire VO | Audité | Synthèse consolidée ci-dessous |
| Comptable | Audité | Synthèse consolidée ci-dessous |
| Super-admin | Audité | Synthèse consolidée ci-dessous |
| Client final | Audité | Synthèse consolidée ci-dessous |

## Format des constats

Chaque rôle est documenté avec 4 niveaux :
- Critique : risque métier, légal, sécurité ou traçabilité
- Important : incohérence de workflow, droits, responsabilité, qualité d'exécution
- Confort : friction UX, duplication, dette d'usage
- Propositions : ajouts, suppressions, simplifications ou recadrages proposés

---

## Réceptionnaire

### Périmètre audité

Parcours étudié :
- planning et réception comptoir
- compagnon PDA interne de réception, actuellement implémenté comme route tokenisée sous /public
- transition de réception côté back
- ordre de réparation
- demandes de travaux complémentaires
- données de réception relues ensuite par le mécanicien

Fichiers principaux relus :
- frontend/pages/planning.vue
- frontend/pages/public/companion.vue
- frontend/pages/ordres/[id].vue
- frontend/pages/admin/demandes-travaux-supp.vue
- frontend/pages/mecanicien.vue
- backend/src/Controller/RendezVousController.php
- backend/src/Controller/CompanionController.php
- backend/src/Controller/DemandeTravauxSuppController.php
- backend/src/Controller/MecanicienController.php
- backend/src/Entity/User.php

### Critique

#### 1. Workflow travaux complémentaires contournable

Constat :
L'écran OR permet au réceptionnaire de créer une demande de travaux complémentaires avec temps estimé, prix estimé, puis de l'approuver ou la refuser localement. En parallèle, le back porte déjà un vrai workflow avec envoi au client, attente de décision et lien public tokenisé.

Pourquoi c'est grave :
- le flux métier n'est plus unique
- la preuve d'accord client peut être court-circuitée
- on ouvre la voie à des travaux validés sans trace opposable
- le réceptionnaire ne sait plus quel écran fait foi

Impact usage :
Le comptoir peut aller plus vite dans un écran local, mais au prix d'une rupture de traçabilité. C'est précisément le genre de raccourci qui finit en litige.

Preuves :
- frontend/pages/ordres/[id].vue : création locale + boutons approuver/refuser
- backend/src/Controller/DemandeTravauxSuppController.php : workflow avec statut en attente de décision client
- frontend/pages/admin/demandes-travaux-supp.vue : cockpit séparé pour envoi du lien client

Décision proposée :
Supprimer la possibilité d'approuver/refuser localement depuis l'OR tant qu'il n'y a pas de réponse client formalisée dans le workflow dédié.

#### 2. Données de réception stockées comme données mécanicien

Constat :
Le compagnon de réception enregistre le checkup et les notes sous les clés mechanic_checkup et mechanic_notes, avec un timestamp last_mechanic_update_at.

Pourquoi c'est grave :
- brouillage complet des responsabilités entre réception et atelier
- ambiguïté dans le dossier atelier sur l'auteur réel des constats
- risque d'interpréter un relevé comptoir comme un diagnostic technique

Impact usage :
Le mécanicien récupère un contexte déjà pollué sémantiquement. Le réceptionnaire pense documenter l'accueil, mais le modèle de données raconte autre chose.

Preuves :
- backend/src/Controller/CompanionController.php : usage de mechanic_checkup, mechanic_notes, last_mechanic_update_at
- backend/src/Controller/MecanicienController.php : remontée downstream de ces données côté atelier

Décision proposée :
Renommer et séparer explicitement les données de réception : reception_checkup, reception_notes, reception_updated_at. Le mécanicien doit lire un bloc réception, pas un pseudo bloc mécanicien.

#### 3. Le champ commentaire change de sens selon l'écran

Constat :
Dans le planning, le commentaire sert d'abord à décrire le besoin client, puis devient en édition une zone de notes réception / atelier. Ensuite, l'espace mécanicien l'affiche comme motif client.

Pourquoi c'est grave :
- un même champ porte plusieurs vérités métier incompatibles
- une note interne réception peut être relue comme parole client
- le mécanicien peut intervenir sur une mauvaise compréhension du besoin

Impact usage :
Source de malentendus, de perte d'information et de tensions comptoir/atelier.

Preuves :
- frontend/pages/planning.vue : création rapide orientée besoin client, édition orientée notes réception / atelier
- frontend/pages/mecanicien.vue : lecture comme motif client

Décision proposée :
Scinder en trois champs d'usage distincts : motif_client, notes_reception, notes_techniques. Interdiction de recycler un champ fourre-tout.

### Important

#### 4. Droits trop larges sur les demandes de travaux complémentaires

Constat :
Les endpoints de liste et d'envoi des demandes sont protégés par un simple ROLE_USER.

Pourquoi c'est risqué :
- le rôle métier comptoir n'est pas réellement imposé
- un utilisateur authentifié non légitime peut manipuler un flux sensible
- on fragilise la séparation des responsabilités

Preuves :
- backend/src/Controller/DemandeTravauxSuppController.php : guards en ROLE_USER sur list et envoyer

Décision proposée :
Restreindre aux profils réceptionnaire et admin, puis aligner avec les permissions granulaires si elles existent déjà côté projet.

#### 5. Escalades pensées pour des rôles métier qui n'existent pas vraiment dans la sécurité

Constat :
Le workflow d'escalade référence ROLE_RESPONSABLE_ATELIER et ROLE_RESPONSABLE_MAGASIN, alors que le mapping réel rabat ces métiers sur ROLE_ADMIN.

Pourquoi c'est risqué :
- escalades potentiellement mal routées
- sentiment de couverture métier sans garantie d'exécution réelle
- dette de modèle entre vocabulaire produit et vocabulaire sécurité

Preuves :
- backend/src/Controller/DemandeTravauxSuppController.php : cibles d'escalade
- backend/src/Entity/User.php : mapping responsable_atelier et responsable_magasin vers ROLE_ADMIN

Décision proposée :
Choisir une vérité unique :
- soit on matérialise vraiment ces rôles dans la sécurité
- soit on remplace partout les rôles métier fantômes par le modèle de permissions réel

#### 6. Le compagnon promet un flux de réception complet, mais seule la signature bloque réellement

Constat :
Le parcours met en avant photos, scan carte grise, checkup express et signature. En pratique, la transition de réception est uniquement bloquée par l'absence de signature OR.

Pourquoi c'est risqué :
- le réceptionnaire croit qu'un process complet est attendu
- l'atelier peut recevoir des réceptions très hétérogènes selon les personnes
- le produit laisse entendre une obligation qui n'existe pas techniquement

Preuves :
- frontend/pages/planning.vue : mise en avant de tout le package de réception
- backend/src/Controller/RendezVousController.php : blocage effectif sur signature OR

Décision proposée :
Choisir l'un des deux modèles, pas un entre-deux :
- soit on assume un minimum légal avec signature seulement
- soit on définit un vrai standard de réception avec champs obligatoires clairement justifiés

#### 7. Modification des données véhicule via un compagnon interne implémenté comme tunnel public tokenisé

Constat :
Le compagnon de réception est censé être un outil interne de comptoir, avec validation ou signature du client sous présence d'un employé. Or son implémentation actuelle permet d'appliquer des modifications sur le véhicule via un tunnel tokenisé exposé sous /public.

Pourquoi c'est risqué :
- un outil interne critique ne devrait pas dépendre d'un tunnel exposé comme s'il était autonome côté client
- confusion entre validation des infos et modification du référentiel
- surface d'erreur plus forte au comptoir, surtout avec OCR imparfait

Preuves :
- frontend/pages/public/companion.vue : application OCR au véhicule
- backend/src/Controller/CompanionController.php : endpoint vehicule en PUT

Décision proposée :
Rebasculer le compagnon sur un vrai mode interne authentifié ou sur un poste compagnon verrouillé par l'employé. À défaut, passer sur un mode brouillon/validation :
- le compagnon propose les corrections
- le réceptionnaire valide explicitement côté écran interne avant écrasement du référentiel véhicule

### Important RGPD / conformité

#### 8. La carte grise est traitée comme une simple photo de réception

Constat :
Le scan carte grise du compagnon est uploadé dans le flux photo avec description Carte grise, puis utilisé pour OCR.

Pourquoi c'est risqué :
- on mélange document administratif et photo de réception
- la qualification documentaire est trop faible
- la politique de conservation n'est pas lisible dans le flux atelier
- pour un simple RDV atelier, la collecte peut être excessive selon le cas d'usage réel

Preuves :
- frontend/pages/public/companion.vue : upload photo Carte grise avant OCR
- backend/src/Controller/CompanionController.php : flux photo commun

Décision proposée :
Sortir la carte grise du flux photo générique et la traiter comme un document métier distinct, avec conservation et visibilité explicitement cadrées.

### Confort

#### 9. Duplication de cockpit pour un même sujet métier

Constat :
Les travaux complémentaires existent dans au moins deux espaces différents : page admin dédiée et bloc embarqué dans l'OR.

Pourquoi c'est gênant :
- apprentissage plus coûteux
- comportements divergents selon l'écran
- dette UX et dette produit en parallèle

Décision proposée :
Choisir un point d'entrée maître pour le réceptionnaire. L'OR peut afficher l'état, mais ne doit pas héberger un workflow alternatif.

### Propositions de simplification produit

#### À ajouter

1. Un vrai bloc Contexte réception distinct du motif client et distinct des notes atelier.
2. Une validation interne explicite des corrections OCR avant modification du véhicule.
3. Un workflow unique de travaux complémentaires avec statuts visibles depuis tous les écrans.
4. Une matrice claire des champs obligatoires à la réception, justifiés par besoin métier ou contrainte légale.

#### À retirer ou réduire

1. Les boutons locaux approuver/refuser hors workflow client.
2. Le vocabulaire mechanic_* dans tout ce qui appartient au comptoir.
3. Le mélange commentaire client / note réception / note atelier dans le même champ.
4. Le scan carte grise présenté comme une simple photo de réception si le besoin exact n'est pas cadré.

#### Refacto cible pour ce rôle

1. Un seul écran source de vérité pour la réception.
2. Un seul workflow source de vérité pour les travaux complémentaires.
3. Un modèle de données séparant clairement client, réception, atelier, signature et documents.
4. Des permissions alignées sur les vrais métiers, pas sur un ROLE_USER fourre-tout.

---

## Notes pour la suite

Quand les autres rôles seront audités, on ajoutera pour chacun :

---

## Responsable magasin / direction

### Périmètre audité

Parcours étudié :
- écran Stat utilisé comme cockpit de pilotage
- accès admin exposés à un profil direction
- notifications et escalades
- arbitrage des demandes complémentaires
- visibilité VO côté direction
- garde-fous spécifiques sur certains workflows VO

Fichiers principaux relus :
- frontend/pages/index.vue
- frontend/pages/admin/index.vue
- frontend/pages/admin/demandes-travaux-supp.vue
- frontend/pages/vo/index.vue
- frontend/composables/useAuth.ts
- frontend/pages/admin/users.vue
- backend/src/Controller/StatistiquesController.php
- backend/src/Controller/NotificationController.php
- backend/src/Controller/DemandeTravauxSuppController.php
- backend/src/Controller/VORemiseEnEtatController.php
- backend/src/Service/UserRoleMapper.php
- backend/src/Entity/User.php

### Critique

#### 1. Le responsable magasin est un rôle fantôme rabattu sur admin

Constat :
Le produit parle d'un responsable magasin / direction distinct, mais le mapping réel le convertit en admin dans les rôles legacy et dans les rôles Symfony. Il n'existe donc pas comme frontière d'autorité autonome sur la majorité de l'application.

Pourquoi c'est grave :
- impossible de cadrer proprement ce qu'un directeur peut voir ou faire sans lui donner le paquet admin
- on mélange pilotage, paramétrage, arbitrage et gestion opérationnelle
- toute analyse de droits ou d'audit devient trompeuse, car le métier annoncé n'est pas le métier techniquement appliqué

Impact usage :
Le directeur n'a pas un poste de pilotage, il hérite d'une caisse à outils admin. C'est mauvais en sécurité et mauvais en ergonomie.

Preuves :
- backend/src/Service/UserRoleMapper.php : responsable_magasin vers admin
- backend/src/Entity/User.php : responsable_magasin vers ROLE_ADMIN
- frontend/pages/admin/users.vue : responsable_magasin remappé en admin

Décision proposée :
Décider enfin si responsable magasin est un vrai rôle ou un simple libellé UX. Si c'est un vrai métier, il faut une matrice de permissions dédiée et plus aucun rabattement implicite vers admin.

#### 2. L'escalade des demandes complémentaires cible un rôle inexistant dans la sécurité effective

Constat :
Le moteur d'escalade promet un SMS à ROLE_RESPONSABLE_MAGASIN à T+30 minutes. Or ce rôle n'existe pas dans la sécurité opérationnelle telle qu'elle est aujourd'hui câblée.

Pourquoi c'est grave :
- faux sentiment de couverture sur un flux sensible et potentiellement litigieux
- risque que l'escalade terminale n'atteigne personne de qualifié
- le produit raconte une gouvernance d'exception que le code ne garantit pas

Impact usage :
En cas de blocage client ou d'absence de réponse, la direction peut croire qu'elle sera alertée alors que le chaînage réel est bancal.

Preuves :
- backend/src/Controller/DemandeTravauxSuppController.php : escalade vers ROLE_RESPONSABLE_MAGASIN
- backend/src/Entity/User.php : absence de rôle Symfony dédié, rabattement sur ROLE_ADMIN

Décision proposée :
Soit on implémente vraiment ROLE_RESPONSABLE_MAGASIN et son routage, soit on remplace partout cette fiction par une cible réelle et vérifiable.

#### 3. Le cockpit direction est un agrégat de métriques atelier, pas un poste de décision magasin

Constat :
La page Stat consolide RDV, charge, occupation ponts, perf mécanos, mix MO/pièces, alertes et volume factures. Mais elle ne porte ni arbitrages commerciaux, ni litiges, ni remises, ni vieillissement créances, ni synthèse inter-domaines magasin.

Pourquoi c'est grave :
- le rôle direction est ramené à observer l'atelier, pas à piloter le commerce et les exceptions
- aucune vue fiable sur les gestes commerciaux, les impayés détaillés, les tickets à risque ou les dossiers bloquants
- la direction n'a pas de vrai tableau d'actions, seulement un tableau de chiffres

Impact usage :
Le directeur doit naviguer entre planning, facturation, VO, demandes complémentaires et admin pour reconstruire sa journée. Ce n'est pas un cockpit, c'est une chasse aux infos.

Preuves :
- frontend/pages/index.vue : KPIs majoritairement atelier et capacité
- backend/src/Controller/StatistiquesController.php : calculs centrés sur RDV, temps, factures, occupation, top services

Décision proposée :
Séparer la stat atelier du cockpit direction. Le cockpit direction doit être orienté décisions à prendre et exceptions à traiter, pas seulement performance historique.

### Important

#### 4. Les accès direction passent par le menu Administration

Constat :
Les entrées utiles à la direction sont noyées dans Administration : utilisateurs, configuration, audit, notifications, demandes complémentaires, templates, clauses légales.

Pourquoi c'est risqué :
- on pousse un profil de pilotage vers un menu de paramétrage
- on entretient la confusion entre gouvernance opérationnelle et administration système
- on augmente le risque de mauvaise manipulation par un profil qui cherche juste à suivre l'activité

Preuves :
- frontend/pages/admin/index.vue : les leviers exposés à la direction sont dans la même grille que les fonctions admin

Décision proposée :
Créer un espace Direction distinct avec uniquement les modules de pilotage, d'arbitrage et d'exception. L'admin doit rester un back-office technique.

#### 5. Les notifications sont diffusées à ROLE_USER, pas à une responsabilité de pilotage qualifiée

Constat :
Le contrôleur de notifications est ouvert à tout utilisateur authentifié et filtre par cible utilisateur ou rôle, mais la logique de pilotage direction n'est pas matérialisée par une boîte de réception prioritaire dédiée.

Pourquoi c'est risqué :
- pas de file claire des urgences de direction
- mélange probable entre notifications d'exécution et notifications d'arbitrage
- difficile de garantir qu'une alerte critique a été vue par le bon décideur

Preuves :
- backend/src/Controller/NotificationController.php : accès global ROLE_USER
- backend/src/Controller/DemandeTravauxSuppController.php : escalades définies séparément sans cockpit dédié visible

Décision proposée :
Mettre en place une inbox direction centrée sur les exceptions métier : demandes complémentaires non traitées, impayés critiques, mandats VO expirants, litiges, anomalies de facturation, retours garantie.

#### 6. Le rôle direction a des accès Stat explicites, mais peu de droits métiers direction explicites ailleurs

Constat :
Le front et le back reconnaissent responsable_magasin pour l'accès à Stat, et un garde-fou VO lui interdit même une clôture atelier. En dehors de ces cas, le comportement retombe largement sur admin générique.

Pourquoi c'est risqué :
- on a des îlots de logique métier, pas un cadre cohérent
- chaque nouveau module risque de réinventer son propre traitement du rôle direction
- la maintenance va continuer à produire des incohérences d'accès

Preuves :
- frontend/composables/useAuth.ts : accès Stat explicite
- backend/src/Controller/StatistiquesController.php : accès Stat explicite
- backend/src/Controller/VORemiseEnEtatController.php : cas spécial pour responsable_magasin

Décision proposée :
Formaliser une policy direction transverse, sinon le rôle va continuer à exister uniquement par exceptions locales.

#### 7. La direction n'a pas de visibilité actionnable sur les remises et arbitrages commerciaux

Constat :
Le code consulté montre bien l'existence de remises dans les devis, mais aucun cockpit direction dédié ne remonte les gestes commerciaux, leur fréquence, leur montant, leur auteur ou leur justification.

Pourquoi c'est risqué :
- absence de pilotage de marge côté magasin
- pas de contrôle réel des dérives commerciales
- impossibilité de distinguer remise maîtrisée et fuite de rentabilité

Preuves :
- frontend/pages/index.vue : aucun indicateur dédié aux remises
- frontend/pages/admin/index.vue : aucun accès direction explicite à un suivi des arbitrages commerciaux

Décision proposée :
Ajouter un panneau de décisions commerciales avec remises du jour, dépassements de seuil, dossiers à arbitrer et historique des validations.

### Confort

#### 8. Le dashboard mélange observation temps réel et analyse de période sans hiérarchie claire

Constat :
La page Stat empile alertes du jour, comparatif de période, perf mécanos, catégories, occupation ressources et synthèse pilotage.

Pourquoi c'est gênant :
- le directeur ne sait pas immédiatement ce qui exige une action maintenant
- l'urgence du jour est noyée parmi des données analytiques froides
- l'interface ressemble davantage à un tableau de bord générique qu'à un poste de commandement

Décision proposée :
Séparer clairement :
- À traiter maintenant
- Risques des 24 prochaines heures
- Tendances et performance

#### 9. Le cockpit VO est utile mais reste séparé du reste du pilotage magasin

Constat :
La page VO a ses propres alertes prioritaires, son stock prioritaire et ses métriques. Elle fonctionne comme un mini-dashboard isolé.

Pourquoi c'est gênant :
- le directeur doit passer d'un cockpit atelier à un cockpit VO sans synthèse globale
- pas de vision consolidée des priorités inter-activités

Décision proposée :
Créer une synthèse magasin unique qui remonte aussi les alertes VO majeures dans le cockpit direction central.

### Propositions de simplification produit

#### À ajouter

1. Un vrai cockpit direction distinct du dashboard atelier.
2. Une inbox d'exceptions priorisées avec accusé de prise en charge.
3. Une vue consolidée marge, remises, impayés, retours garantie, alertes VO et demandes complémentaires.
4. Un rôle direction autonome avec permissions explicites et auditables.

#### À retirer ou réduire

1. Le rabattement automatique de responsable_magasin vers admin.
2. Les escalades vers des rôles théoriques non matérialisés.
3. Le mélange menu direction / administration technique.
4. Le faux cockpit qui informe beaucoup mais aide peu à décider.

#### Refacto cible pour ce rôle

1. Un espace Direction séparé d'Administration et séparé du dashboard atelier.
2. Un modèle de responsabilités explicite : pilotage, arbitrage, exception, pas paramétrage global par défaut.
3. Des alertes liées à des décisions attendues, pas uniquement à des compteurs.
4. Une sécurité cohérente entre vocabulaire métier, rôles techniques et escalades automatiques.

---

## Super-admin

### Périmètre audité

Parcours étudié :
- gestion multi-atelier et changement de contexte
- provisioning et validation des comptes
- gestion des rôles système et rôles métier
- audit global visible dans l'interface
- bypass TenantFilter et accès cross-atelier
- garde-fous sur la survie du dernier super-admin

Fichiers principaux relus :
- frontend/pages/admin/index.vue
- frontend/pages/admin/ateliers.vue
- frontend/pages/admin/audit.vue
- frontend/pages/admin/roles.vue
- frontend/pages/admin/roles-metier/index.vue
- backend/src/Controller/AdminAtelierController.php
- backend/src/Controller/AdminUserProvisioningController.php
- backend/src/Controller/AuthController.php
- backend/src/EventListener/TenantFilterListener.php
- backend/src/Security/UserSecurityGuard.php
- backend/src/Security/RolePermissionVoter.php
- backend/src/Service/UserRoleMapper.php
- backend/src/EventSubscriber/UserRoleMetierSyncSubscriber.php
- backend/src/Entity/AuditLog.php
- backend/src/Controller/ClauseLegaleController.php

### Critique

#### 1. L'audit global n'est pas réservé au super-admin

Constat :
L'entité AuditLog est exposée en lecture à ROLE_ADMIN. Le journal visible dans l'interface n'est donc pas un véritable audit global réservé au rôle le plus sensible, mais un journal accessible à l'administration classique.

Pourquoi c'est grave :
- un admin local peut potentiellement consulter des traces qui relèvent de la gouvernance supérieure
- on brouille la frontière entre pilotage d'atelier et supervision globale
- un audit de sécurité ou d'abus de privilèges perd sa valeur si son périmètre d'accès est trop large

Impact usage :
Le super-admin n'a pas un espace d'audit réellement souverain. Le journal ressemble plus à un historique admin générique qu'à une tour de contrôle de conformité.

Preuves :
- backend/src/Entity/AuditLog.php : sécurité en lecture sur ROLE_ADMIN
- frontend/pages/admin/audit.vue : interface sans cadrage explicite super-admin

Décision proposée :
Réserver le journal global au super-admin. Si un admin atelier a besoin d'historique, il faut un audit limité à son atelier et à ses permissions, pas le même flux.

#### 2. Le modèle mélange encore rôle super-admin et rôle métier responsable atelier

Constat :
Le mapper legacy convertit super_admin vers responsable_atelier lorsqu'il cherche un rôle métier. Même si le subscriber évite cette conversion sur un user déjà super_admin, la règle existe dans le modèle et entretient une confusion structurelle.

Pourquoi c'est grave :
- le rôle ultime de gouvernance est semantiquement rabaissé vers un métier opérationnel atelier
- cela prépare de futures régressions dès qu'un flux oublie un garde-fou local
- le vocabulaire métier ne reflète plus les responsabilités réelles du compte

Impact usage :
Le projet continue à superposer deux axes qui devraient rester séparés : gouvernance plateforme et métiers atelier.

Preuves :
- backend/src/Service/UserRoleMapper.php : super_admin => responsable_atelier
- backend/src/EventSubscriber/UserRoleMetierSyncSubscriber.php : exception spéciale pour ne pas synchroniser les super_admin

Décision proposée :
Sortir totalement le super-admin du mapping rôle métier. Le super-admin ne doit jamais être projeté comme un métier d'atelier.

#### 3. Le bypass tenant est techniquement puissant mais produit peu explicite et peu encadré visuellement

Constat :
Le TenantFilter est complètement désactivé pour le super-admin s'il n'y a pas d'atelier résolu. Le changement de contexte atelier existe, mais le produit ne montre pas clairement quand l'utilisateur est en mode atelier ciblé versus en mode global.

Pourquoi c'est grave :
- risque de travailler sans s'en rendre compte hors contexte atelier
- risque de confusion lors d'opérations sensibles ou de support
- difficulté à prouver qu'une action a été faite volontairement en scope global ou local

Impact usage :
Le super-admin a un énorme pouvoir transverse, mais l'interface ne lui oppose pas une discipline visuelle à la hauteur du risque.

Preuves :
- backend/src/EventListener/TenantFilterListener.php : bypass complet sans atelier résolu
- frontend/pages/admin/ateliers.vue : activation de contexte via cookie active_atelier_id
- backend/src/Controller/AuthController.php : cookie active_atelier_id posé au login

Décision proposée :
Imposer un mode explicite : contexte global versus contexte atelier. Chaque écran sensible doit afficher clairement le scope courant et empêcher les ambiguïtés.

### Important

#### 4. Le super-admin n'a pas un cockpit de gouvernance, seulement une juxtaposition de pages admin

Constat :
L'entrée Administration assemble ateliers, rôles, rôles métier, audit, notifications, templates et configuration. Mais il n'existe pas de poste de commande global réunissant onboarding, incidents, sécurité, modules actifs, utilisateurs en attente, ateliers incomplets et alertes critiques plateforme.

Pourquoi c'est risqué :
- la supervision globale est éclatée
- le super-admin doit naviguer à la main pour reconstituer l'état de la plateforme
- les incidents de configuration ou de provisioning peuvent rester invisibles trop longtemps

Preuves :
- frontend/pages/admin/index.vue : menu en grille, sans synthèse globale
- frontend/pages/admin/ateliers.vue : KPI limités à volume/état des ateliers
- backend/src/Controller/AdminUserProvisioningController.php : provisioning isolé de la vue multi-atelier

Décision proposée :
Créer un cockpit super-admin dédié avec files d'attente, ateliers incomplets, utilisateurs pendants, erreurs de configuration, audit sensible et incidents multi-sites.

#### 5. Le provisioning des comptes est centré sur l'approbation, pas sur la gouvernance du parc utilisateurs

Constat :
Le contrôleur super-admin sait approuver et rejeter des comptes, avec affectation atelier et rôle métier. Mais on ne voit pas de vue consolidée des comptes orphelins, des comptes désactivés, des comptes sans atelier, des comptes à privilèges forts ou des écarts de provisioning.

Pourquoi c'est risqué :
- supervision RH/sécurité incomplète
- le super-admin traite les tickets au fil de l'eau sans vue de santé du parc utilisateurs
- les erreurs d'affectation ou comptes dormants sont plus difficiles à détecter

Preuves :
- backend/src/Controller/AdminUserProvisioningController.php : focus pending/approve/reject
- backend/src/Controller/AuthController.php : notifications sur comptes Google en attente

Décision proposée :
Passer d'un simple écran d'approbation à une vraie console utilisateurs globale avec statuts, affectations, derniers accès et alertes de gouvernance.

#### 6. Les garde-fous existent, mais ils restent principalement techniques et peu lisibles côté produit

Constat :
Il y a un garde-fou sur le dernier super-admin actif et une prévention d'escalade de privilèges. Mais ces règles vivent surtout dans le back sans traduction claire dans le produit ni explication de ce qui est autorisé ou non.

Pourquoi c'est risqué :
- expérience de gestion imprévisible pour l'utilisateur le plus sensible
- erreurs comprises trop tard, uniquement au moment de l'échec API
- gouvernance réelle difficile à transmettre à l'équipe

Preuves :
- backend/src/Security/UserSecurityGuard.php : last super-admin + prevent escalation
- frontend/pages/admin/roles.vue : interface simple sans mise en garde métier forte

Décision proposée :
Rendre visibles les règles de gouvernance directement dans les écrans sensibles : suppression impossible, dernier super-admin protégé, portée des rôles et effets du changement de rôle.

#### 7. Le super-admin bypasse toutes les permissions granulaires

Constat :
Le voter de permissions rend automatiquement true pour ROLE_SUPER_ADMIN. Techniquement c'est cohérent, mais cela signifie qu'aucune limitation fine n'existe pour les opérations sensibles une fois ce rôle accordé.

Pourquoi c'est risqué :
- rôle trop puissant sans compartimentation interne
- moindre erreur de session ou de poste ouvert a un impact maximal
- impossible d'outiller des profils support avancés sans leur donner trop de pouvoir

Preuves :
- backend/src/Security/RolePermissionVoter.php : bypass total pour ROLE_SUPER_ADMIN

Décision proposée :
Conserver le bypass si nécessaire, mais introduire à terme des modes d'intervention ou au minimum une journalisation renforcée sur les actions à haut risque.

### Confort

#### 8. Le multi-atelier est géré proprement, mais reste pauvre en signaux de santé plateforme

Constat :
La page ateliers permet créer, modifier, activer un contexte et voir si une config existe. C'est utile, mais insuffisant pour piloter une plateforme multi-site.

Pourquoi c'est gênant :
- pas d'indicateurs sur ateliers sans utilisateurs, sans branding, sans configuration complète, sans activité ou avec modules incohérents
- le super-admin doit ouvrir atelier par atelier pour comprendre où agir

Décision proposée :
Ajouter des drapeaux de santé multi-atelier : configuration incomplète, modules incohérents, zéro utilisateur actif, onboarding bloqué, derniers incidents.

#### 9. Les rôles et rôles métier sont encore présentés comme deux écrans parallèles à comprendre mentalement

Constat :
Le super-admin gère d'un côté les rôles système simples, de l'autre les rôles métier avancés. Le lien entre les deux reste implicite et demande de connaître le modèle interne.

Pourquoi c'est gênant :
- courbe d'apprentissage inutilement élevée
- source d'erreur conceptuelle lors de l'attribution ou de la maintenance des accès
- dette de gouvernance documentaire

Décision proposée :
Créer une vue unifiée qui explique la hiérarchie : rôle système, rôle métier, permissions effectives, portée atelier et exceptions.

### Propositions de simplification produit

#### À ajouter

1. Un cockpit super-admin global avec files d'attente, santé multi-atelier et alertes de gouvernance.
2. Un mode explicite global/atelier visible partout quand le TenantFilter est contourné.
3. Une vue audit globale réservée au super-admin, avec déclinaison atelier séparée pour les admins locaux.
4. Une console utilisateurs globale avec risques de provisioning et comptes sensibles.

#### À retirer ou réduire

1. Le mapping implicite du super-admin vers un rôle métier opérationnel.
2. L'accès trop large de l'audit global à ROLE_ADMIN.
3. Le mélange conceptuel entre rôles système, rôles métier et pouvoirs effectifs.
4. Les écrans admin dispersés sans synthèse de gouvernance.

#### Refacto cible pour ce rôle

1. Un super-admin pensé comme rôle de plateforme, jamais comme métier d'atelier.
2. Une gouvernance multi-atelier visible, traçable et explicitement scoped.
3. Un audit fort sur toutes les actions transverses et les changements de contexte.
4. Une séparation nette entre administration locale d'atelier et administration globale de la plateforme.

---

## Gestionnaire VO

### Périmètre audité

Parcours étudié :
- création et finalisation des rachats VO
- création et finalisation des dépôts-vente
- détail de dossier achat / dépôt
- parcours compagnon VO interne assisté, actuellement implémenté comme route tokenisée sous /public
- contrôle des documents, SIV, Livre de Police, vente et PDF générés
- garde-fous remise en état avant vente

Fichiers principaux relus :
- frontend/pages/vo/rachats/new.vue
- frontend/pages/vo/rachats/[id].vue
- frontend/pages/vo/depots/new.vue
- frontend/pages/vo/depots/[id].vue
- frontend/pages/public/vo-companion.vue
- frontend/composables/useVoHelpers.ts
- backend/src/Controller/VOController.php
- backend/src/Controller/PublicVoCompanionController.php
- backend/src/Entity/VOPurchase.php
- backend/src/Entity/VODepotVente.php
- backend/src/Entity/VODocument.php
- backend/src/Entity/Trait/VOCompanionTrait.php
- backend/src/Service/VODocumentService.php
- backend/src/Service/VOCompanionWorkflowService.php
- backend/src/Service/VOGeneratedDocumentService.php
- backend/src/Service/VOLivrePoliceService.php

### Règle documentaire cible

Décision figée :
Les CERFA obligatoires listés dans .github font partie du périmètre cible VO et doivent être inclus sans arbitrage ultérieur. Ce n'est plus une option produit.

Pour le VO, il faut distinguer strictement deux familles de documents :
- formulaires réglementés : utiliser le vrai CERFA ou sa structure officielle à l'identique, jamais un “faux PDF maison” qui imite vaguement le document
- documents métier internes ou contractuels non CERFA : PDF maison autorisé, à condition qu'il respecte les mentions légales réellement applicables

En pratique :
- déclaration d'achat SIV : Cerfa 13751 obligatoire, pas seulement un PDF de préparation interne
- mandat d'immatriculation : Cerfa 13757*03 obligatoire quand le mandat est utilisé
- certificat de cession : Cerfa 15776*02 obligatoire quand il est requis
- PV de rachat : ce n'est pas un CERFA, donc document métier maison acceptable
- contrat de dépôt-vente : ce n'est pas un CERFA, donc document métier maison acceptable

Source de référence projet :
- .github/copilot-instructions.md : DA Cerfa 13751, mandat Cerfa 13757*03, certificat de cession Cerfa 15776*02

Conséquence de pilotage :
Les templates backend actuellement nommés vo_da_siv et vo_mandat_immatriculation ne doivent pas être considérés comme aboutis s'ils restent de simples “préparations internes”. Ils doivent soit devenir de vrais rendus réglementaires, soit être explicitement renommés comme supports internes de préremplissage non opposables.

### Critique

#### 1. Le compagnon VO interne est exposé via un token en query string et encodé dans le QR

Constat :
Le compagnon VO est censé être un outil interne utilisé par le personnel, avec présence d'un employé au moment de la validation ou de la signature. Pourtant le lien est construit sous la forme /public/vo-companion?token=... et sert directement de cible au QR code.

Pourquoi c'est grave :
- le token fuit plus facilement dans l'historique navigateur, les logs proxy, les captures d'écran et les outils analytics
- c'est explicitement contraire à la règle projet qui interdit les tokens en URL query string
- un outil interne sensible se retrouve techniquement exposé comme un parcours public manipulant des données sensibles du dossier VO

Impact usage :
Le gestionnaire VO croit ouvrir un poste compagnon interne pratique, mais en réalité il diffuse un secret d'accès dans le canal le plus bavard possible.

Preuves :
- backend/src/Entity/Trait/VOCompanionTrait.php : getCompanionPublicPath
- frontend/pages/vo/rachats/new.vue : QR compagnon prêt dès l'ouverture
- frontend/pages/vo/depots/new.vue : QR compagnon prêt dès l'ouverture

Décision proposée :
Basculer sur un token en path segment ou sur un lien court à usage unique régénérable, jamais en query string.

#### 2. Pièce d'identité et justificatif de domicile sont bien stockés, puis purgés plus tard seulement dans un compagnon censé rester interne

Constat :
Le compagnon VO autorise l'upload de pièce d'identité et de justificatif de domicile. Ces fichiers sont persistés comme VODocument, puis supprimés seulement plus tard par une commande planifiée après transcription LP. Même si l'intention produit est interne, l'implémentation actuelle reste exposée comme tunnel tokenisé.

Pourquoi c'est grave :
- la règle métier et RGPD dit destruction après transcription, pas conservation jusqu'au prochain batch
- en pratique, les documents peuvent rester plusieurs heures ou plusieurs jours selon le moment de la journée et l'état du planificateur
- le design produit banalise le dépôt de documents ultra sensibles dans un parcours public

Impact usage :
Le gestionnaire VO pense être conforme parce que la rétention est à 0, mais le comportement réel est une conservation différée.

Preuves :
- frontend/pages/public/vo-companion.vue : upload pièce d'identité et justificatif
- backend/src/Controller/PublicVoCompanionController.php : saveSeller + saveDocument
- backend/src/Entity/VODocument.php : retentionYears à 0 pour identité et domicile
- backend/src/Service/VODocumentService.php : purgeExpiredIdentityDocuments
- backend/src/Schedule.php : purge quotidienne à 4h

Décision proposée :
Supprimer le stockage durable de ces pièces du parcours public. Soit on fait OCR/transcription puis destruction immédiate, soit on retire complètement l'upload et on garde seulement la retranscription contrôlée.

#### 3. Le compagnon VO interne est implémenté avec un payload trop riche pour un simple poste de collecte assistée

Constat :
Le payload du compagnon VO renvoie prénom, nom, téléphone, email, adresse, type/numéro/date de pièce et l'ensemble des documents déjà déposés.

Pourquoi c'est grave :
- même pour un poste assisté en présence d'un employé, ce n'est pas minimisé
- l'adresse et l'email ne sont pas nécessaires pour signer ou compléter le dossier sur PDA
- plus le payload est riche, plus l'impact d'une fuite de token ou d'un poste laissé ouvert devient sérieux

Impact usage :
Le compagnon VO est traité comme une mini application interne trop large, et en plus exposée techniquement comme une page publique tokenisée.

Preuves :
- backend/src/Controller/PublicVoCompanionController.php : buildPayload

Décision proposée :
Réduire le payload du compagnon au strict nécessaire : identité partielle affichable, étapes à compléter, état des validations, jamais adresse complète ni données secondaires non indispensables.

### Important

#### 4. Le produit pousse un mode compagnon “prêt dès l'ouverture” alors que le dossier peut être juridiquement incomplet

Constat :
Les écrans de création rachat et dépôt mettent en avant le QR compagnon immédiatement, y compris en brouillon, avant rattachement complet du vendeur/déposant et du véhicule.

Pourquoi c'est risqué :
- le flux donne l'impression qu'on peut lancer le juridique avant d'avoir cadré le dossier
- on inverse le processus : on ouvre un tunnel de collecte avant d'avoir verrouillé le minimum de contexte administratif
- cela favorise les dossiers hybrides, avec signature ou documents partiels sur des entités encore mouvantes

Impact usage :
Très séduisant en démo, mais mauvais en exploitation. Le gestionnaire VO a besoin d'un parcours robuste, pas d'un “on commence et on verra après”.

Preuves :
- frontend/pages/vo/rachats/new.vue : compagnon PDA prêt dès l'ouverture
- frontend/pages/vo/depots/new.vue : même logique sur dépôt-vente
- backend/src/Controller/VOController.php : createPurchase / createDepot autorisent le brouillon incomplet

Décision proposée :
Conserver le brouillon rapide, mais interdire l'activation du compagnon tant que le triplet minimum n'est pas établi : type de dossier, tiers rattaché, véhicule rattaché ou brouillon véhicule explicitement validé.

#### 5. Le compagnon VO interne permet de modifier le référentiel véhicule trop tôt et trop directement

Constat :
Depuis le compagnon VO, l'utilisateur peut créer ou modifier les données véhicule après OCR, y compris sur un véhicule encore incomplet, avec plaque temporaire au besoin.

Pourquoi c'est risqué :
- l'OCR peut injecter des données imparfaites directement dans le référentiel
- la frontière entre préremplissage et validation définitive n'existe pas vraiment
- le gestionnaire VO peut se retrouver à corriger après coup un véhicule déjà utilisé ailleurs dans le dossier

Preuves :
- frontend/pages/public/vo-companion.vue : OCR puis validation véhicule
- backend/src/Controller/PublicVoCompanionController.php : saveVehicleData crée un véhicule si nécessaire et persiste les champs

Décision proposée :
Transformer le compagnon en mode suggestion : OCR et saisie créent un brouillon de données, puis validation interne obligatoire côté dossier VO avant écriture définitive sur le véhicule.

#### 6. La vente est correctement bloquée par la DA SIV, le mandat expiré et la remise en état, mais le front mélange encore readiness produit et conformité légale

Constat :
Le back bloque bien la vente d'un rachat sans DA SIV enregistrée, d'un dépôt sans mandat actif, ou d'un dossier avec remise en état bloquante. En revanche le front continue à présenter un sentiment de “dossier presque prêt” avec barres de progression, cartes marketing et CTA précoces.

Pourquoi c'est risqué :
- le gestionnaire VO reçoit un message UX optimiste alors que le droit impose un verrou dur
- les “pourcentages de complétion” suggèrent qu'une vente peut être proche alors qu'un seul verrou légal suffit à tout bloquer

Preuves :
- frontend/pages/vo/rachats/[id].vue : progressions conformité / préparation vente
- frontend/pages/vo/depots/[id].vue : progressions conformité / cycle du mandat
- backend/src/Controller/VOController.php : canSell + saleBlockers
- backend/src/Service/VODocumentService.php : getPurchaseSaleBlockers / getDepotSaleBlockers

Décision proposée :
Remplacer la logique de score visuel par une logique d'autorisations explicites : vendable maintenant / non vendable, avec raisons juridiques hiérarchisées.

#### 7. Les documents déposés sont affichés dans le compagnon VO, mais leurs liens restent incohérents avec un usage interne assisté

Constat :
Le compagnon VO affiche des chips de documents déjà déposés. Le helper construit prioritairement l'URL de téléchargement sécurisée /api/vo/documents/{id}/download, laquelle exige ROLE_VO_MANAGER.

Pourquoi c'est risqué :
- soit les liens sont cassés dans le compagnon, donc l'UX ment
- soit on sera tenté plus tard d'ouvrir ce téléchargement via le tunnel tokenisé, ce qui serait dangereux pour les pièces sensibles

Impact usage :
Le gestionnaire VO croit disposer d'un poste compagnon cohérent, mais les accès documentaires ne sont pas pensés proprement pour un usage assisté et borné.

Preuves :
- frontend/pages/public/vo-companion.vue : affichage sellerDocuments / vehicleDocuments / extraDocuments
- frontend/composables/useVoHelpers.ts : buildVoDocumentUrl
- backend/src/Controller/VOController.php : downloadDocument protégé par ROLE_VO_MANAGER

Décision proposée :
Décider clairement :
- soit aucun document archivé n'est réouvrable depuis le compagnon
- soit seuls certains rendus non sensibles sont consultables via des URLs internes ou temporaires, signées, courtes et auditables

#### 8. La TVA du dépôt-vente est partiellement hardcodée dans l'entité

Constat :
Le calcul de commission TTC dans le dépôt-vente applique 20% en dur.

Pourquoi c'est risqué :
- c'est contraire à la règle anti-hardcode du projet
- si l'atelier ou le régime évolue, le calcul métier est faux au niveau du coeur de domaine

Preuves :
- backend/src/Entity/VODepotVente.php : getCommissionVatAmount

Décision proposée :
Sortir le taux de TVA du modèle et le porter dans une configuration atelier ou un service métier dédié.

### Confort

#### 9. Le gestionnaire VO travaille dans trop de sous-cockpits parallèles

Constat :
Le module répartit l'activité entre tableau de bord VO, liste rachats, liste dépôts, détail dossier, documents, factures, livre de police et remises en état.

Pourquoi c'est gênant :
- la charge mentale est trop haute pour une activité déjà juridiquement dense
- l'utilisateur doit reconstruire mentalement l'état du dossier à partir de plusieurs écrans latéraux
- cela augmente le risque d'oublier un verrou SIV, un document ou une remise en état active

Décision proposée :
Faire du dossier VO la vraie source de vérité, avec documents, blocages, SIV, LP, mandat et remise en état visibles sans changer d'écran.

#### 10. Le vocabulaire “compagnon prêt”, “préparer SIV”, “ouvrir la vente” est plus commercial qu'opérationnel

Constat :
Plusieurs CTA poussent à avancer vite, alors que le rôle a surtout besoin de statuts opposables et de garde-fous clairs.

Pourquoi c'est gênant :
- cela pousse à l'action avant la compréhension
- le module VO ressemble parfois à un tunnel commercial, pas à un registre juridique et de revente sécurisé

Décision proposée :
Passer à un vocabulaire de conformité et d'action contrôlée : dossier incomplet, action autorisée, action interdite, justificatif attendu.

### Propositions de simplification produit

#### À ajouter

1. Un vrai mode “pré-dossier” VO sans QR ni signature tant que le minimum de cadrage n'est pas fait.
2. Un bloc unique “vente autorisée / interdite” avec motifs juridiques, motifs métier et actions correctives.
3. Une purge immédiate ou quasi immédiate des pièces d'identité après transcription LP, avec audit explicite.
4. Une validation interne des données OCR avant écriture définitive sur le véhicule.

#### À retirer ou réduire

1. Le token VO en query string.
2. L'exposition inutile de l'adresse, de l'email et des métadonnées non indispensables dans le compagnon.
3. Les promesses de compagnon “prêt” sur un dossier encore flou.
4. Les liens implicites vers des documents déjà déposés tant que la politique d'accès n'est pas clarifiée.

#### Refacto cible pour ce rôle

1. Un seul dossier VO central avec lecture immédiate des blocages légaux.
2. Un compagnon interne assisté, borné au strict tunnel de collecte et signature, pas un miroir du dossier interne exposé par token.
3. Une séparation stricte entre préremplissage, validation métier et archivage légal.
4. Un modèle VO où la rapidité d'entrée ne peut jamais contourner la conformité LP, DA SIV, mandat et RGPD.

---

## Responsable atelier

### Périmètre audité

Parcours étudié :
- cockpit atelier et pilotage des ponts
- affectation structurelle pont ↔ mécanicien
- accès aux statistiques “responsable atelier”
- transitions rapides depuis la vue atelier
- rectification OR et rapport d'intervention
- gestion des no-show automatisés

Fichiers principaux relus :
- frontend/pages/workshop.vue
- frontend/pages/admin/ponts.vue
- frontend/pages/ordres/[id].vue
- frontend/middleware/auth.global.ts
- backend/src/Service/OrdreReparationPolicy.php
- backend/src/Controller/OrdreReparationController.php
- backend/src/Controller/RapportInterventionController.php
- backend/src/Controller/StatistiquesController.php
- backend/src/Command/CheckNoShowCommand.php
- backend/src/Service/RendezVousWorkflowService.php
- backend/src/Service/UserRoleMapper.php
- backend/src/Entity/User.php

### Critique

#### 1. Le responsable atelier n'existe pas vraiment comme rôle technique autonome

Constat :
Le produit parle de responsable atelier, les stats lui sont dédiées, la politique OR le cite, mais le mapping réel le rabat largement sur admin.

Pourquoi c'est grave :
- le rôle métier et le rôle de sécurité ne racontent pas la même chose
- on risque de donner trop de droits à un chef d'atelier juste pour qu'il puisse faire son travail
- l'audit des responsabilités devient flou : a-t-on affaire à un pilote de production ou à un administrateur local ?

Impact usage :
Le chef d'atelier n'a pas un poste de commande propre. Il hérite de droits admin pour compenser l'absence de modélisation correcte du métier.

Preuves :
- backend/src/Service/UserRoleMapper.php : responsable_atelier => admin
- backend/src/Entity/User.php : responsable_atelier => ROLE_ADMIN
- frontend/middleware/auth.global.ts : zone /admin réservée à l'administration
- backend/src/Controller/StatistiquesController.php : page Stat pensée pour responsable_atelier

Décision proposée :
Créer une vraie couche de permissions chef d'atelier distincte de l'admin, puis réaligner tous les écrans et contrôleurs sur cette vérité unique.

#### 2. La rectification du rapport d'intervention n'est pas correctement protégée

Constat :
L'endpoint de rectification du rapport ne porte ni IsGranted, ni contrôle explicite de rôle métier équivalent à celui de l'OR.

Pourquoi c'est grave :
- un document signé et figé peut être réémis sans garde-fou cohérent avec la criticité métier
- on casse la hiérarchie projet qui réserve les rectifications sensibles à des rôles d'encadrement
- la sécurité n'est pas homogène entre OR et rapport alors que les deux sont des documents figés et opposables

Impact usage :
Le chef d'atelier est supposé être le garant des corrections exceptionnelles, mais le système ne verrouille pas clairement cette responsabilité.

Preuves :
- backend/src/Controller/RapportInterventionController.php : rectifier
- backend/src/Controller/OrdreReparationController.php : rectifier protégé par ROLE_ADMIN
- backend/src/Service/OrdreReparationPolicy.php : canRectify restreint aux rôles d'encadrement

Décision proposée :
Aligner la rectification du rapport sur la même politique que l'OR : permission dédiée, audit fort, et restriction explicite aux rôles d'encadrement autorisés.

### Important

#### 3. Le cockpit atelier est trop pauvre pour un vrai pilotage d'équipe

Constat :
La page atelier permet surtout de voir les ponts, réaffecter un mécanicien à un pont, réceptionner un RDV ou le démarrer. Elle ne donne pas de vision robuste des retards, urgences, arbitrages en attente, travaux complémentaires ou blocages documentaires.

Pourquoi c'est risqué :
- le chef d'atelier pilote la production à l'aveugle sur les sujets réellement sensibles
- on lui donne un écran de monitoring, pas un écran de commandement
- il doit reconstituer la vérité entre planning, OR, rapports et autres écrans latéraux

Preuves :
- frontend/pages/workshop.vue : onglets ponts, mécanos, temps, absences
- frontend/pages/workshop.vue : getPontQuickAction limité à réception / démarrer / ouvrir

Décision proposée :
Transformer la vue atelier en poste de supervision réel avec alertes de retard, RDV bloqués, demandes complémentaires en attente, pièces en attente, essai routier manquant et conflits de charge.

#### 4. Le produit confond affectation structurelle et ordonnancement quotidien

Constat :
La page admin ponts insiste sur l'affectation structurelle des ponts, tandis que la page atelier permet déjà de modifier le mécanicien rattaché “sans passer par l'admin”.

Pourquoi c'est risqué :
- deux écrans modifient le même levier avec deux intentions produit différentes
- la frontière entre configuration d'atelier et pilotage opérationnel devient floue
- le chef d'atelier peut faire de l'administration sans cadre clair, ou à l'inverse être bloqué selon son vrai niveau de droits

Preuves :
- frontend/pages/admin/ponts.vue : “affectation structurelle des ponts, pas l’ordonnancement des RDV”
- frontend/pages/workshop.vue : “tu changes le mécanicien rattaché sans passer par l’admin”

Décision proposée :
Séparer les deux usages :
- configuration structurelle en admin
- réaffectation opérationnelle de journée dans un outil dédié, avec traçabilité et effet borné au planning du jour

#### 5. Le no-show est décidé automatiquement par batch sans validation métier

Constat :
Un cron marque automatiquement les RDV confirmés en no-show 30 minutes après l'heure prévue.

Pourquoi c'est risqué :
- un retard réel, un accueil comptoir en cours ou une situation exceptionnelle peut être requalifié trop tôt par le système
- le chef d'atelier perd la main sur une décision opérationnelle qui peut impacter relation client, charge et facturation
- le produit automatise une sanction de workflow là où il faudrait au minimum une alerte ou une file de validation

Preuves :
- backend/src/Command/CheckNoShowCommand.php
- backend/src/Service/RendezVousWorkflowService.php : recordCancellation

Décision proposée :
Passer à une logique d'alerte “retard critique / à confirmer no-show” avec validation humaine côté réception ou responsable atelier, plutôt qu'une bascule silencieuse et automatique.

#### 6. Les statistiques “responsable atelier” existent, mais pas le chemin de résolution

Constat :
La page stats réserve des indicateurs à ce rôle, mais ces métriques ne débouchent pas sur des actions concrètes depuis le cockpit atelier.

Pourquoi c'est risqué :
- on mesure sans permettre d'agir
- le chef d'atelier voit l'occupation, les minutes planifiées et les volumes, mais ne peut pas corriger facilement les causes depuis le même périmètre

Preuves :
- backend/src/Controller/StatistiquesController.php : assertStatsAccess
- frontend/pages/workshop.vue : aucun prolongement direct vers arbitrages métier sensibles

Décision proposée :
Relier chaque KPI atelier à un backlog d'actions exploitables : retards du jour, ponts surchargés, absences impactantes, RDV en attente pièces, rapports manquants, essais routiers non faits.

#### 7. La rectification OR existe, mais reste enfouie dans un écran documentaire plutôt que dans un flux d'encadrement

Constat :
La rectification OR est disponible depuis la page OR, via une modale. Elle n'est pas portée par un poste de contrôle chef d'atelier ni par une file des exceptions.

Pourquoi c'est risqué :
- la décision d'exception se prend au fond d'un écran dossier, pas dans un cockpit de supervision
- aucune vue globale des OR rectifiés, à re-signer ou en anomalie n'apparaît pour l'encadrement

Preuves :
- frontend/pages/ordres/[id].vue : modal rectifier OR
- backend/src/Controller/OrdreReparationController.php : création d'un OR rectifié

Décision proposée :
Créer une file “exceptions atelier” regroupant OR rectificatifs, rapports rectifiés, retards critiques et demandes complémentaires bloquées.

### Confort

#### 8. Le cockpit atelier ressemble trop à une vue terrain enrichie, pas à une vue chef d'équipe

Constat :
L'interface parle surtout en termes de pont libre, prochain RDV, ouvrir l'intervention, nouveau RDV. C'est utile au quotidien, mais insuffisant pour quelqu'un qui arbitre charge, priorités et incidents.

Pourquoi c'est gênant :
- le chef d'atelier n'a pas une lecture synthétique des exceptions
- l'écran privilégie le flux linéaire plutôt que le pilotage par écart

Décision proposée :
Faire passer la vue atelier d'une logique “qu'est-ce qui est sur ce pont ?” à une logique “qu'est-ce qui menace la journée ?”.

### Propositions de simplification produit

#### À ajouter

1. Un vrai rôle chef d'atelier avec permissions propres, séparé de l'admin.
2. Un cockpit des exceptions atelier : retards, no-show à confirmer, essais routiers manquants, rapports/OR à rectifier, attentes pièces, demandes complémentaires bloquées.
3. Une réaffectation opérationnelle des ressources bornée à la journée et tracée.
4. Une politique homogène de rectification pour OR et rapports.

#### À retirer ou réduire

1. Le mélange entre configuration admin des ponts et pilotage atelier quotidien.
2. La bascule automatique en no-show sans validation humaine.
3. Le faux rôle responsable_atelier qui n'est en réalité qu'un admin rebadgé.

#### Refacto cible pour ce rôle

1. Un chef d'atelier qui pilote la production sans hériter des droits admin inutiles.
2. Une vue atelier centrée sur les écarts et les arbitrages, pas seulement sur les ponts.
3. Une traçabilité explicite de toutes les décisions sensibles de supervision.
4. Un workflow atelier où l'encadrement valide les exceptions au lieu de les subir ou de les chercher dans plusieurs écrans.

---

## Client final

### Périmètre audité

Parcours étudiés :
- prise de rendez-vous publique
- suivi public du RDV
- décision publique sur travaux complémentaires
- pages publiques légales et confidentialité

Recadrage produit du 2026-04-20 :
Les compagnons PDA atelier et VO ne doivent pas être considérés comme des parcours autonomes du client final. Ce sont des outils internes utilisés par le personnel, avec signature ou validation d'information sous présence d'un employé. Les constats liés à ces compagnons sont donc portés prioritairement dans les sections Réceptionnaire et Gestionnaire VO. Côté client final autonome, il reste surtout la réservation, le suivi et, si on le maintient, le lien de décision sur travaux complémentaires.

Fichiers principaux relus :
- frontend/pages/public/booking.vue
- frontend/pages/public/suivi.vue
- frontend/pages/public/companion.vue
- frontend/pages/public/demande/[token].vue
- frontend/pages/public/vo-companion.vue
- frontend/pages/public/mentions-legales.vue
- frontend/pages/public/politique-confidentialite.vue
- frontend/middleware/auth.global.ts
- backend/src/Controller/PublicBookingController.php
- backend/src/Controller/SuiviController.php
- backend/src/Controller/CompanionController.php
- backend/src/Controller/DemandeTravauxSuppController.php
- backend/src/Controller/PublicVoCompanionController.php
- backend/src/Controller/PublicPhotoController.php
- backend/src/Entity/RendezVous.php

### Critique

#### 1. Le lien client de décision travaux complémentaires n'est pas réellement public

Constat :
La page publique de décision client existe bien en /public/demande/{token}, mais le middleware global n'exempte pas ce chemin. Il n'exempte que booking, suivi, companion et vo-companion.

Pourquoi c'est grave :
- le réceptionnaire peut envoyer un lien qui renvoie finalement le client vers la connexion
- un workflow critique “accord client avant travaux” devient aléatoire en production
- le produit prétend proposer une validation distante alors que le tunnel peut être cassé dès l'entrée

Impact usage :
Le client reçoit un lien de décision, clique, puis tombe potentiellement sur une barrière d'authentification qui n'a rien à faire dans son parcours.

Preuves :
- frontend/pages/public/demande/[token].vue : page explicitement pensée publique
- frontend/middleware/auth.global.ts : /public/demande absent de la whitelist
- backend/src/Controller/DemandeTravauxSuppController.php : endpoints publics bien exposés côté API

Décision proposée :
Rendre le tunnel réellement public de bout en bout, ou supprimer la promesse produit. Dans l'état, on vend un parcours qui peut ne pas fonctionner.

#### 2. Les tunnels publics sensibles ne sont pas protégés de manière homogène

Constat :
La réservation publique et le suivi ont un rate limiter. La décision de travaux complémentaires n'en a pas. Et si les routes compagnon atelier / VO restent techniquement exposées sous /public alors qu'elles devraient être internes, elles n'en ont pas non plus.

Pourquoi c'est grave :
- les parcours les plus sensibles sont précisément ceux qui exposent le plus de données et d'actions en écriture
- cela augmente le risque d'énumération, de test massif de tokens et de surcharge ciblée
- la politique de sécurité varie selon les pages sans justification produit

Impact usage :
Le client légitime ne voit rien, mais le système n'a pas un niveau de protection cohérent face aux usages malveillants ou aux abus automatisés.

Preuves :
- backend/src/Controller/PublicBookingController.php : limiter présent
- backend/src/Controller/SuiviController.php : limiter présent
- backend/src/Controller/CompanionController.php : aucun limiter
- backend/src/Controller/DemandeTravauxSuppController.php : endpoints publics sans limiter
- backend/src/Controller/PublicVoCompanionController.php : aucun limiter

Décision proposée :
Appliquer une politique unique sur tous les tunnels publics tokenisés : rate limiting, journalisation minimale, et messages d'erreur non bavards.

#### 3. Le produit expose encore des routes compagnon sous /public alors qu'elles ne devraient pas relever du client final autonome

Constat :
Le compagnon atelier renvoie prénom, nom, téléphone, email, plaque, VIN, année, cylindrée, commentaire, photos, statut de signature, et permet ensuite d'uploader des photos, modifier le véhicule, compléter les données de réception et signer l'OR. Or ce parcours ne devrait pas être analysé comme un lien autonome client, mais comme un outil interne assisté.

Pourquoi c'est grave :
- le périmètre produit et le périmètre technique se contredisent
- on expose techniquement une mini application interne sous un schéma de route publique tokenisée
- une fuite de token donne un pouvoir de lecture et d'écriture beaucoup trop large sur le dossier client

Impact usage :
Le client final n'a pas besoin de tout voir ni de tout pouvoir modifier pour signer au comptoir. Le vrai problème est que l'outil interne n'est pas implémenté comme un outil interne.

Preuves :
- backend/src/Controller/CompanionController.php : payload client et véhicule très riche
- backend/src/Controller/CompanionController.php : endpoints publics d'écriture sur photo, signature, véhicule et réception
- frontend/pages/public/companion.vue : tunnel complet de réception sur lien public

Décision proposée :
Sortir ces compagnons du périmètre client final autonome. Soit ils deviennent réellement internes/authentifiés, soit ils sont réduits à une simple surface de signature assistée sans pouvoir métier.

#### 4. Le compagnon VO ne devrait pas relever du client final autonome et ne doit pas rester exposé comme tunnel public riche

Constat :
Le compagnon VO demande explicitement de téléverser pièce d'identité et justificatif de domicile. Le back les stocke comme VODocument dans le dossier via le même tunnel tokenisé, alors que ce poste devrait rester un outil interne assisté.

Pourquoi c'est grave :
- la charge RGPD est énorme pour un parcours techniquement exposé comme public
- on banalise le dépôt de documents à très forte sensibilité dans un tunnel web
- le projet impose la destruction après transcription, pas une persistance documentaire classique

Impact usage :
Le client ou vendeur est incité à confier des documents critiques dans un parcours dont la portée réelle et la conservation ne sont pas clairement expliquées.

Preuves :
- frontend/pages/public/vo-companion.vue : uploads pièce d'identité et justificatif
- backend/src/Controller/PublicVoCompanionController.php : saveSeller persiste ces fichiers via documentService

Décision proposée :
Retirer ces uploads du parcours public tant qu'il n'existe pas de traitement immédiat, borné et explicable de transcription puis destruction.

### Important

#### 5. La réservation publique est mono-atelier en dur

Constat :
Le front appelle les créneaux publics avec atelier_id=1 et poste aussi atelier_id: 1 à la création du RDV.

Pourquoi c'est risqué :
- l'expérience publique n'est pas compatible avec un vrai multi-atelier
- un client peut réserver dans le mauvais atelier sans le savoir
- la promesse produit “application multi-atelier” ne tient plus dès l'entrée publique

Impact usage :
Très mauvais pour un client final : il pense réserver “chez son atelier”, alors qu'il réserve potentiellement dans l'atelier 1 du système.

Preuves :
- frontend/pages/public/booking.vue : atelier_id=1 sur chargement des slots
- frontend/pages/public/booking.vue : atelier_id: 1 sur submit

Décision proposée :
Rattacher le tunnel public à un atelier explicite via domaine, slug d'atelier ou lien signé. Un parcours public multi-atelier ne peut pas reposer sur une constante codée en dur.

#### 6. Le consentement RGPD est enregistré sans consentement explicite côté interface

Constat :
Le back enregistre consentDate et consentSource=public_booking sur le client créé depuis la réservation publique. Pourtant l'interface de réservation ne contient ni case d'information, ni validation explicite, ni lien visible vers la politique de confidentialité.

Pourquoi c'est risqué :
- on déclare un consentement qui n'a pas été recueilli de manière claire
- la base légale affichée dans la politique de confidentialité devient incohérente avec l'interface réelle
- cela fragilise la conformité documentaire du parcours public

Impact usage :
Le client donne ses coordonnées sans être clairement informé de ce qui est collecté, pourquoi et pour combien de temps.

Preuves :
- backend/src/Controller/PublicBookingController.php : setConsentDate et setConsentSource
- frontend/pages/public/booking.vue : absence de bloc consentement ou lien légal visible

Décision proposée :
Requalifier la base légale réelle et afficher un bloc d'information clair dans le formulaire. Ne jamais enregistrer un consentement non exprimé comme tel.

#### 7. Les pages légales publiques ne sont probablement pas accessibles sans connexion

Constat :
Les pages mentions légales et politique de confidentialité sont bien présentes, mais le middleware global ne les classe pas parmi les routes publiques.

Pourquoi c'est risqué :
- le client peut être empêché d'accéder aux informations légales censées lui être présentées
- la transparence RGPD devient théorique, pas opérationnelle
- le parcours public manque son obligation d'information de base

Preuves :
- frontend/pages/public/mentions-legales.vue : page publique présente
- frontend/pages/public/politique-confidentialite.vue : page publique présente
- frontend/middleware/auth.global.ts : aucune exemption pour ces deux routes

Décision proposée :
Rendre toutes les pages légales réellement publiques et les lier depuis chaque tunnel concerné.

#### 8. Le suivi public est trop pauvre pour être réellement utile au client

Constat :
Le suivi public montre un statut, une date, une heure, un type d'intervention et un véhicule. Il n'affiche ni estimation de fin, ni blocage, ni attente de validation client, ni prochaine action attendue.

Pourquoi c'est risqué :
- on donne un faux sentiment de suivi sans véritable information décisionnelle
- le client doit toujours appeler l'atelier pour comprendre ce qui se passe
- l'expérience publique promet de la transparence, mais fournit surtout un badge d'état

Impact usage :
Le client final ne gagne pas réellement d'autonomie. Le tunnel public devient cosmétique.

Preuves :
- frontend/pages/public/suivi.vue : affichage très limité
- backend/src/Controller/SuiviController.php : payload minimal sans message orienté action

Décision proposée :
Faire du suivi un vrai outil client : statut compréhensible, prochaine étape, éventuelle action requise, estimation temporelle quand elle existe.

#### 9. Les tokens publics circulent encore en query string sur plusieurs parcours

Constat :
Le suivi public et le compagnon atelier sont construits avec ?token=..., côté front et dans les liens copiés/QR.

Pourquoi c'est risqué :
- fuite facilitée dans historique, presse-papiers, logs proxy et captures d'écran
- incohérence avec les propres règles projet interdisant les secrets en query string
- niveau de risque accru puisque ces tokens donnent accès à des parcours publics sensibles

Preuves :
- frontend/pages/planning.vue : construction du lien companion avec ?token=
- frontend/pages/public/booking.vue : lien suivi avec ?token=

Décision proposée :
Passer systématiquement les tokens en segment de chemin ou via un mécanisme de lien court régénérable.

### Confort

#### 10. Le produit mélange encore “outil interne tenu par un employé” et “parcours réellement autonome pour le client”

Constat :
Le compagnon atelier et le compagnon VO ont un look public, mais portent des actions qui relèvent en réalité d'une médiation comptoir. À l'inverse, le vrai suivi client reste très pauvre.

Pourquoi c'est gênant :
- on investit beaucoup dans des faux parcours publics assistés
- le client autonome n'obtient pas les bonnes informations au bon moment
- l'atelier garde la charge de réexpliquer ce que le produit devrait rendre évident

Décision proposée :
Assumer deux familles distinctes de tunnels :
- parcours assistés comptoir, opérés par un salarié sur PDA
- parcours clients distants, très simples, très bornés, orientés information et décision

### Propositions de simplification produit

#### À ajouter

1. Une vraie stratégie de tunnels publics par usage : suivi, décision, signature, dépôt documentaire, avec niveau de données minimal explicite.
2. Des pages publiques légales réellement accessibles et liées depuis chaque formulaire public.
3. Un suivi client orienté action : où en est la moto, que doit faire le client, quand aura-t-il des nouvelles.
4. Un rattachement atelier clair dans tout parcours public multi-atelier.

#### À retirer ou réduire

1. Les actions métier lourdes dans le compagnon public de réception.
2. Les tokens en query string.
3. La collecte publique de pièce d'identité et justificatif tant que la destruction immédiate n'est pas garantie.
4. Le faux consentement implicite enregistré comme consentement RGPD explicite.

#### Refacto cible pour ce rôle

1. Un client final qui comprend immédiatement ce qu'il peut faire, ce qu'il ne peut pas faire et ce qui est attendu de lui.
2. Des tunnels publics courts, robustes, non ambigus, chacun avec une seule finalité.
3. Une exposition de données strictement minimale sur tous les liens tokenisés.
4. Une séparation nette entre parcours public client, PDA assisté comptoir et outils internes atelier.

---

## Comptable

### Périmètre audité

Parcours étudiés :
- liste des factures atelier
- encaissement partiel ou total
- génération de facture depuis un RDV
- aperçu de facture avant émission
- envoi PDF par email
- structure de l'entité facture et paiements
- mentions PDF et rôle de sécurité comptable

Fichiers principaux relus :
- frontend/pages/facturation/index.vue
- frontend/stores/billing.ts
- backend/src/Controller/FacturationController.php
- backend/src/Controller/RendezVousFacturationCompatController.php
- backend/src/Entity/Facture.php
- backend/src/Entity/Paiement.php
- backend/templates/pdf/facture.html.twig

### Critique

#### 1. Les statuts de paiement sont incohérents entre le front et le back

Constat :
Le back positionne la facture en partiellement_payee après un encaissement incomplet. Le front filtre et affiche partielle. Il masque aussi le bouton d'encaissement selon payee et annulee, jamais selon partiellement_payee.

Pourquoi c'est grave :
- l'état comptable réel n'est pas représenté correctement dans l'interface
- le comptable peut filtrer dans le vide ou ne jamais retrouver les factures partiellement encaissées
- on casse la lisibilité des relances et du reste à payer

Impact usage :
Le comptable voit des factures “bizarres”, mal classées, avec un statut qui ne correspond pas aux filtres ni aux badges. C'est une dette directe sur le suivi des encaissements.

Preuves :
- frontend/pages/facturation/index.vue : filtre partielle
- backend/src/Controller/FacturationController.php : statut partiellement_payee

Décision proposée :
Définir un vocabulaire unique de statuts comptables, puis l'appliquer partout sans traduction locale improvisée.

#### 2. Le rôle comptable n'est pas réellement câblé sur les endpoints de facturation

Constat :
Les opérations sensibles de facturation sont protégées en ROLE_ADMIN ou ROLE_USER. Le rôle comptable existe dans le modèle utilisateur et dans le seed, mais les contrôleurs ne s'appuient pas dessus.

Pourquoi c'est grave :
- le comptable ne dispose pas d'un périmètre métier propre
- des opérations comptables peuvent être indûment ouvertes à des profils non comptables ou au contraire fermées au comptable réel
- la séparation des responsabilités est fictive alors que la facturation est un domaine légal sensible

Impact usage :
On retombe sur le même défaut structurel que pour d'autres rôles : le métier existe dans le discours produit, pas dans la sécurité effective.

Preuves :
- backend/src/Controller/FacturationController.php : guards ROLE_USER et ROLE_ADMIN
- backend/src/Entity/User.php : présence de ROLE_COMPTABLE
- backend/src/Command/SeedCommand.php : rôle métier comptable configuré

Décision proposée :
Créer une vraie politique d'accès comptable fondée sur ROLE_COMPTABLE et/ou permissions fines perm.facturation.*.

#### 3. La facture atelier peut être émise sans logique d'avoir ni annulation comptable conforme

Constat :
Le module expose des statuts annulee côté front, mais je ne trouve ni flux d'avoir, ni endpoint d'annulation de facture, ni modèle d'écriture correctrice. L'interface prévoit même déjà l'existence d'un statut qui n'est pas réellement porté par un workflow comptable.

Pourquoi c'est grave :
- une facture émise ne doit pas disparaître ni être “annulée” librement ; elle doit être corrigée par avoir
- l'outil crée une illusion de gestion comptable alors qu'il manque l'opération de correction légale de base
- le jour où l'équipe voudra “annuler” une facture, elle n'aura pas de chemin conforme

Impact usage :
Le comptable se retrouve sans solution opposable pour corriger une erreur de facturation.

Preuves :
- frontend/pages/facturation/index.vue : statut annulee présent dans les filtres
- backend/src/Controller/FacturationController.php : aucun endpoint d'avoir ou d'annulation conforme
- backend/src/Entity/Facture.php : aucune logique d'avoir ou de rattachement correctif

Décision proposée :
Supprimer toute fiction d'annulation simple de facture et introduire un vrai flux d'avoir, traçable et lié à la facture d'origine.

### Important

#### 4. La facture générée depuis un RDV ne reflète pas une comptabilité atelier crédible

Constat :
La génération “facturer le RDV” reconstruit une facture quasi minimale à partir de prixEstime ou d'un taux horaire fallback. Elle pose une seule ligne main d'oeuvre, sans pièces, avec une logique très simplifiée.

Pourquoi c'est risqué :
- la facture devient un reflet du devis approximatif ou du temps estimé, pas du réalisé ni des pièces effectivement consommées
- on brouille la frontière entre production atelier et document comptable final
- le comptable ne peut pas auditer une facture qui ne raconte pas correctement la composition de la prestation

Impact usage :
Le document de facturation ressemble à un reçu atelier simplifié, pas à un support de comptabilité fiable.

Preuves :
- backend/src/Controller/FacturationController.php : buildRdvInvoicePreview
- backend/src/Controller/FacturationController.php : facturerRendezVous

Décision proposée :
La facture doit être bâtie à partir des lignes réelles de MO, pièces, remises et taxes, pas d'un fallback atelier déguisé en comptabilité.

#### 5. L'aperçu facture et le PDF manquent de rigueur sur les mentions comptables

Constat :
Le PDF affiche l'atelier, le client, le véhicule et les lignes. Mais il ne montre pas clairement la TVA intracom atelier, l'échéance, les modalités d'annulation/correction, ni une structuration comptable robuste des mentions. Le footer de pénalités reste générique.

Pourquoi c'est risqué :
- une facture est un document légal, pas juste un export joli
- certaines mentions obligatoires ou fortement attendues ne sont pas clairement maîtrisées dans le rendu
- on laisse trop de responsabilité à un template “minimum viable” sur un sujet qui ne devrait pas l'être

Impact usage :
Le comptable n'a pas de garantie que le PDF sorti en production soit réellement opposable ou prêt pour un contrôle.

Preuves :
- backend/templates/pdf/facture.html.twig

Décision proposée :
Faire une revue légale dédiée des mentions facture atelier avec checklist explicite avant de considérer le module comme comptablement fiable.

#### 6. Il n'existe pas de vrai parcours comptable d'export ou de clôture

Constat :
Je ne vois ni export FEC, ni export comptable, ni vue de relance structurée, ni journal des encaissements, ni workflow d'impayés. Le comptable hérite d'une liste de factures avec encaissement manuel et email.

Pourquoi c'est risqué :
- le rôle comptable est réduit à “imprimer, encaisser, envoyer”
- aucune interface ne soutient réellement le rapprochement, l'export cabinet ou le pilotage des impayés
- l'application promet un rôle comptable, mais livre surtout un écran de caisse amélioré

Impact usage :
Le comptable devra sortir du produit pour tenir sa réalité métier.

Preuves :
- frontend/pages/facturation/index.vue : périmètre fonctionnel très limité
- absence de contrôleur d'export comptable/FEC identifié dans le périmètre relu

Décision proposée :
Soit on assume un périmètre “encaissement atelier” et on cesse de parler de rôle comptable complet, soit on livre les fondamentaux manquants : exports, journal, relances, avoirs.

#### 7. Les montants sont saisis librement à l'encaissement sans garde-fou métier suffisant

Constat :
L'écran d'encaissement permet de saisir un montant, un mode, une référence et des notes. Le back ne montre pas de validation métier explicite contre les montants négatifs, les surpaiements grossiers ou certains cas incohérents de mode différé.

Pourquoi c'est risqué :
- le contrôle métier repose surtout sur l'interface, pas sur une règle serveur robuste clairement visible
- la saisie d'un encaissement est une écriture sensible et doit être durcie côté serveur
- un comptable a besoin d'un comportement strict, pas permissif

Impact usage :
Les anomalies d'encaissement risquent d'être découvertes tardivement, lors du rapprochement ou de l'audit.

Preuves :
- frontend/pages/facturation/index.vue : formulaire d'encaissement libre
- backend/src/Controller/FacturationController.php : addPaiement sans contrôle métier explicite lisible sur montant max ou logique d'exception

Décision proposée :
Centraliser les validations d'encaissement côté serveur : montant strictement positif, anti-surpaiement, statut cohérent, gestion formelle du différé.

### Confort

#### 8. Le comptable travaille dans un écran trop orienté réception/atelier

Constat :
La page facturation mélange consultation, encaissement, emailing et génération depuis RDV. Elle ressemble davantage à un prolongement du flux atelier/réception qu'à un poste comptable.

Pourquoi c'est gênant :
- les besoins de caisse immédiate et les besoins de tenue comptable ne sont pas les mêmes
- le comptable doit composer avec une ergonomie centrée sur l'action rapide, pas sur le contrôle, l'historique et la justification

Décision proposée :
Séparer l'usage caisse/réception de l'usage comptable, ou au minimum créer deux modes de lecture sur le même module.

### Propositions de simplification produit

#### À ajouter

1. Un vrai rôle comptable avec droits propres.
2. Un flux d'avoir obligatoire pour toute correction de facture émise.
3. Un journal des encaissements et un export comptable/FEC.
4. Une construction de facture à partir des lignes réelles atelier, pas d'une approximation globale.

#### À retirer ou réduire

1. Les statuts de facture incohérents ou fictifs.
2. La confusion entre écran de caisse atelier et module comptable.
3. La génération de facture “simplifiée” si elle ne reflète pas les lignes réelles.

#### Refacto cible pour ce rôle

1. Un module comptable qui distingue émission, correction, encaissement, relance et export.
2. Une sécurité alignée sur le vrai métier comptable.
3. Une facture juridiquement propre, techniquement traçable et comptablement exploitable.
4. Un poste comptable qui permet le contrôle, pas seulement l'action rapide.
- les incohérences propres au rôle
- les collisions entre rôles
- les contradictions de workflow transverse
- les opportunités de simplification commune

La synthèse finale devra produire un plan de refacto en 3 paquets :
- corrections bloquantes métier / légal / sécurité
- simplifications de workflow et permissions
- dette UX et dette de modèle de données

---

## Mécanicien

### Périmètre audité

Parcours étudié :
- écran atelier mécanicien
- démarrage et fin d'intervention
- checkup atelier
- essai routier
- rapport d'intervention
- signature mécanicien
- données de réception relues par l'atelier
- disponibilité des photos et demandes complémentaires pendant l'intervention

Fichiers principaux relus :
- frontend/pages/mecanicien.vue
- frontend/pages/ordres/[id].vue
- backend/src/Controller/MecanicienController.php
- backend/src/Controller/RendezVousController.php
- backend/src/Controller/RapportInterventionController.php
- backend/src/Controller/DemandeTravauxSuppController.php
- backend/src/Service/RapportInterventionService.php

### Critique

#### 1. L'intervention peut être terminée avant signature du rapport mécanicien

Constat :
L'écran mécanicien fait passer le RDV en statut termine, puis ouvre seulement ensuite le rapport à compléter et signer.

Pourquoi c'est grave :
- l'ordre métier officiel est cassé
- on marque une intervention terminée alors que le document de fin d'intervention n'est ni complété ni signé
- on crée une zone grise où le véhicule est techniquement fini mais administrativement incomplet

Impact usage :
Le mécanicien peut clôturer sa journée avec plusieurs RDV "terminés" et des rapports encore en retard. C'est exactement l'inverse d'un atelier pilotable.

Preuves :
- frontend/pages/mecanicien.vue : transition terminer puis ouverture du rapport
- frontend/pages/mecanicien.vue : badge Rapport à compléter sur les RDV déjà terminés
- backend/src/Controller/RendezVousController.php : terminer ne bloque pas sur rapport signé, seulement sur l'essai routier

Décision proposée :
Inverser la séquence. Le mécanicien doit compléter et signer son rapport avant la transition terminer. Le statut termine doit signifier "travail fini et rapport mécanicien verrouillé", pas "on verra le document plus tard".

#### 2. Le rapport exige 3 photos de restitution, mais l'écran mécanicien ne permet aucune prise de photo

Constat :
La signature du rapport est bloquée si moins de 3 photos de restitution existent. Pourtant l'écran mécanicien n'offre aucun flux photo, aucun bouton caméra, aucune galerie, aucune liaison à l'API photo.

Pourquoi c'est grave :
- règle bloquante sans outil dans l'écran métier principal
- le mécanicien est sanctionné par une exigence qu'il ne peut pas satisfaire depuis son parcours naturel
- on pousse soit à naviguer dans un autre écran non prévu pour lui, soit à contourner le process

Impact usage :
Blocage direct en fin d'intervention. Mauvais produit, même si la règle métier de preuve photo est pertinente.

Preuves :
- backend/src/Service/RapportInterventionService.php : minimum 3 photos de restitution requises pour signer
- frontend/pages/mecanicien.vue : aucun flux photo disponible
- frontend/pages/ordres/[id].vue : les photos typées existent ailleurs, hors écran mécanicien

Décision proposée :
Brancher un vrai module photo dans l'écran mécanicien, mobile-first, avec types en_cours, apres_travaux et restitution. Si on impose 3 photos pour signer, il faut les rendre capturables à l'endroit exact où la signature se fait.

#### 3. Le mécanicien ne peut pas créer sa demande de travaux complémentaires depuis son écran métier

Constat :
Le besoin métier dit que le mécanicien doit pouvoir signaler un problème découvert et créer une demande de travaux complémentaires. Le back dispose d'un flux de création dédié, mais l'écran mécanicien n'expose aucun bouton ni formulaire pour cette action.

Pourquoi c'est grave :
- besoin métier central non couvert dans le parcours atelier
- le mécanicien est forcé de sortir de son flux, de passer par un tiers ou d'utiliser un écran non conçu pour lui
- on augmente le risque de demande orale non tracée

Impact usage :
Perte de temps, oubli, validation tardive, contournement par téléphone ou discussion informelle.

Preuves :
- backend/src/Controller/DemandeTravauxSuppController.php : flux de création de demande côté back
- frontend/pages/mecanicien.vue : aucun point d'entrée pour créer une demande complémentaire

Décision proposée :
Ajouter dans l'écran mécanicien un bouton simple "Travaux complémentaires" qui permet de saisir constat + priorité + éventuellement photo, sans prix ni estimation financière. La qualification commerciale reste côté réception.

### Important

#### 4. Le mécanicien voit un lien d'appel direct au client

Constat :
L'écran atelier expose directement un lien téléphone client.

Pourquoi c'est risqué :
- brouillage du rôle réceptionnaire / mécanicien
- risque de promesse ou d'information client donnée hors procédure
- affaiblissement de la traçabilité sur l'accord client

Impact usage :
Le plus rapide finit par appeler directement. À court terme ça semble pratique, à moyen terme ça casse la discipline comptoir.

Preuves :
- frontend/pages/mecanicien.vue : lien tel direct dans le header de l'intervention en cours

Décision proposée :
Retirer l'appel direct depuis l'espace mécanicien. À la place : bouton "Demander rappel réception" ou création de demande complémentaire/escalade interne.

#### 5. L'écran mécanicien ne couvre pas la mise en pause / attente pièces pourtant prévue par le workflow

Constat :
L'interface atelier permet de démarrer, valider l'essai et terminer, mais n'expose pas de vraie action de pause, attente pièces ou reprise. Pourtant ces statuts existent dans le workflow général.

Pourquoi c'est risqué :
- besoin atelier quotidien non couvert
- le mécanicien ne peut pas refléter son état réel de production depuis son propre écran
- les responsables perdent une vue fiable de l'avancement

Impact usage :
On fige des interventions en cours alors qu'elles sont en attente réelle. C'est mauvais pour la charge atelier et les promesses client.

Preuves :
- frontend/pages/mecanicien.vue : actions limitées à démarrer, checkup, essai, terminer
- frontend/pages/rdv/[id].vue : le workflow prévoit attendre_pieces, mettre_en_attente_reprise, reprendre_apres_pieces, reprendre_demain

Décision proposée :
Ajouter les transitions atelier utiles directement dans l'espace mécanicien, avec vocabulaire simple : Attente pièces, Pause, Reprise.

#### 6. Le kilométrage de restitution est prérempli avec le kilométrage de réception

Constat :
Le brouillon de rapport initialise kilometrageRestitution avec le kilométrage saisi à la réception.

Pourquoi c'est risqué :
- faux défaut silencieux
- si le mécanicien oublie de corriger, le document final ment sur le kilométrage de restitution
- contradiction directe avec la règle métier qui impose une saisie de fin d'essai / fin d'intervention

Impact usage :
Erreur plausible, discrète, et difficile à voir au comptoir si tout le monde va vite.

Preuves :
- backend/src/Service/RapportInterventionService.php : prefill du kilometrageRestitution depuis rdv.kilometrage
- frontend/pages/mecanicien.vue : champ Km restitution éditable mais non protégé contre cette mauvaise valeur par défaut

Décision proposée :
Ne jamais préremplir le kilométrage de restitution avec le kilométrage de réception. Au mieux, proposer la valeur d'essai km_fin quand l'essai est validé.

### Important produit / cohérence de données

#### 7. Le mécanicien relit encore un champ commentaire ambigu présenté comme motif client

Constat :
L'écran mécanicien affiche comme motif client un champ qui, ailleurs, peut avoir servi à des notes réception / atelier.

Pourquoi c'est risqué :
- le mécanicien croit lire une parole client alors qu'il peut lire une note interne
- le diagnostic est pollué par une donnée sémantiquement instable

Preuves :
- frontend/pages/mecanicien.vue : motif client lu depuis commentaire_client ou commentaire
- backend/src/Controller/MecanicienController.php : commentaire_client alimenté depuis rdv.commentaire

Décision proposée :
Le mécanicien doit lire un vrai motif client figé, séparé des notes réception et séparé des notes atelier.

### Confort

#### 8. Le checkup atelier et l'essai routier sont saisis deux fois dans le même écran logique

Constat :
L'écran active intervention et le panneau rapport réexposent les mêmes informations d'essai routier et une partie de la logique atelier.

Pourquoi c'est gênant :
- sensation de doublon
- compréhension moins nette de ce qui est "travail en cours" versus "document final"
- risque de friction en mobile si le mécanicien doit chercher où compléter la bonne version

Décision proposée :
Conserver un seul bloc de saisie vivant pendant l'intervention, puis le refléter en lecture ou en validation dans le rapport, sans rééditer le même contenu à deux endroits.

### Propositions de simplification produit

#### À ajouter

1. Un bouton photo toujours visible pendant l'intervention avec prise caméra native.
2. Un bouton travaux complémentaires sans prix ni temps estimé, orienté constat technique.
3. Des actions atelier de pause, attente pièces, reprise.
4. Une séquence guidée de clôture : checkup final, essai routier, photos restitution, rapport, signature mécanicien.

#### À retirer ou réduire

1. Le lien direct d'appel client dans l'espace mécanicien.
2. Le passage au statut termine avant signature du rapport.
3. Le préremplissage trompeur du kilométrage de restitution avec la valeur de réception.

#### Refacto cible pour ce rôle

1. Un écran atelier vraiment mobile-first, centré action immédiate.
2. Une séparation nette entre saisie d'intervention, preuve photo, demande complémentaire et document final.
3. Une fin d'intervention impossible tant que la preuve atelier minimale n'est pas complète.
4. Un rôle mécanicien recentré sur le technique, sans contact client direct et sans responsabilité commerciale.

---

## Synthèse transverse

### Lecture d'ensemble

L'application ne souffre pas d'un seul bug dominant. Elle souffre d'un défaut de charpente.

Le même motif revient partout :
- le produit raconte un métier
- la sécurité implémente autre chose
- l'interface expose un troisième modèle
- les workflows finissent par se contredire

Autrement dit, le problème principal n'est pas l'absence de fonctionnalités. Le problème principal est l'absence de source de vérité unique sur quatre axes :
- qui fait quoi
- dans quel écran
- avec quel niveau d'autorité
- à quel moment du workflow

### Causes racines

#### 1. Les rôles métier ne sont pas réellement modélisés de bout en bout

Constat transverse :
- responsable atelier, responsable magasin et comptable existent dans le discours produit
- mais la sécurité réelle retombe souvent sur ROLE_ADMIN ou ROLE_USER
- super-admin lui-même reste partiellement mélangé avec le métier responsable_atelier

Conséquence :
- rôles fantômes
- périmètres de responsabilité flous
- audit et sécurité incohérents
- difficulté croissante à faire évoluer le produit sans créer de nouvelles exceptions locales

Verdict :
Le système de rôles actuel n'est pas une base fiable pour un ERP métier multi-profils.

#### 2. Plusieurs workflows ont plusieurs écrans de vérité concurrents

Constat transverse :
- demandes de travaux complémentaires : OR local, page admin dédiée, lien de décision
- réception : planning, compagnon PDA, OR, données relues par le mécanicien
- VO : dashboard VO, détails dossier, compagnon, documents, remises en état, vente
- facturation : liste front, preview atelier, génération simplifiée, PDF légal

Conséquence :
- duplication fonctionnelle
- règles différentes selon le point d'entrée
- perte de traçabilité
- erreurs d'usage probables même avec une équipe formée

Verdict :
Tant qu'un workflow peut être conduit depuis plusieurs surfaces non hiérarchisées, les incohérences vont continuer à réapparaître.

#### 3. Le produit mélange outils internes assistés et tunnels réellement publics

Constat transverse :
- le compagnon atelier et le compagnon VO sont pensés comme outils internes tenus par un employé
- mais le code les expose encore comme routes /public tokenisées
- en parallèle, certains vrais parcours client distants sont soit cassés, soit trop pauvres, soit mal protégés

Conséquence :
- mauvaise minimisation des données
- modèle de menace confus
- confusion UX entre “client autonome” et “poste PDA comptoir”
- risque RGPD et sécurité plus élevé que nécessaire

Verdict :
Il faut séparer strictement trois familles d'interfaces :
- outils internes authentifiés
- outils assistés comptoir/PDA
- vrais tunnels client distants

#### 4. Le modèle de données laisse encore des champs ambigus porter plusieurs responsabilités

Constat transverse :
- commentaire utilisé tour à tour comme motif client, note réception, note atelier
- données de réception stockées sous mechanic_*
- statuts de paiement divergents entre front et back
- mapping legacy et rôle métier superposés sans vraie doctrine

Conséquence :
- confusion métier permanente
- effet domino sur front, API, PDF et reporting
- dette difficile à corriger localement car elle touche la sémantique même des objets

Verdict :
Le projet a besoin d'un nettoyage sémantique du modèle avant toute accélération fonctionnelle.

#### 5. Les cockpits montrent beaucoup, mais aident peu à décider

Constat transverse :
- dashboard atelier, VO, admin, facturation et audit sont fragmentés
- les KPIs existent, mais les files d'action manquent
- les rôles de pilotage n'ont pas de vraies inbox d'exceptions

Conséquence :
- les responsables doivent reconstruire eux-mêmes la situation
- les alertes ne se traduisent pas naturellement en décisions
- le produit informe, mais n'oriente pas assez l'action

Verdict :
Le problème n'est pas le manque de chiffres. Le problème est le manque d'écrans de décision.

### Contradictions majeures à traiter en premier

#### 1. Métier annoncé vs sécurité réelle

Exemples :
- responsable_magasin annoncé, admin réel
- comptable annoncé, ROLE_USER ou ROLE_ADMIN réel sur plusieurs endpoints
- super-admin annoncé comme gouvernance globale, mais partiellement raccordé au rôle métier responsable_atelier

#### 2. Workflow annoncé vs ordre réel des actions

Exemples :
- intervention terminée avant signature mécanicien
- OR et demandes complémentaires manipulables depuis plusieurs surfaces
- facture atelier générée depuis une logique de production simplifiée plutôt que depuis une logique comptable robuste

#### 3. Outil interne annoncé vs exposition technique réelle

Exemples :
- compagnon atelier sous /public
- compagnon VO sous /public
- tokens encore en query string

#### 4. Contrôle légal annoncé vs exécution technique partielle

Exemples :
- faux consentement de réservation publique
- stockage différé de pièces d'identité VO avant purge
- absence de vrai flux d'avoir comptable
- audit global accessible trop largement

### Arbitrage produit global

Ce qu'il faut assumer clairement :

#### 1. Les compagnons PDA ne sont pas des portails clients

Décision cible :
- compagnon atelier = outil comptoir assisté
- compagnon VO = outil interne assisté
- client final autonome = réservation, suivi, décision distante éventuelle, rien de plus

#### 2. Un rôle métier doit devenir un vrai contrat technique

Décision cible :
- un rôle métier = permissions explicites + sections visibles + garde-fous workflow + audit attendu
- fin des rabattements implicites vers admin quand le produit prétend autre chose

#### 3. Chaque workflow doit avoir un écran maître

Décision cible :
- un seul point de conduite par workflow
- les autres écrans montrent l'état, mais ne recréent pas une logique alternative

#### 4. Les données sensibles doivent suivre un design de minimisation, pas un design de commodité

Décision cible :
- ne montrer que ce qui sert à l'action immédiate
- ne stocker que ce qui est justifié
- ne jamais exposer un outil interne comme un pseudo tunnel public si ce n'est pas le vrai besoin

### Plan de refacto en 3 paquets

#### Paquet 1. Corrections bloquantes métier / légal / sécurité

Objectif : arrêter les contradictions dangereuses.

À traiter en priorité haute :
1. Sortir les compagnons atelier et VO du faux modèle “public autonome” et les recadrer techniquement selon leur vrai usage.
2. Supprimer les tokens en query string sur tous les parcours tokenisés.
3. Réserver l'audit global au super-admin et créer au besoin une vue audit atelier distincte.
4. Corriger les rôles fantômes les plus critiques : comptable, responsable atelier, responsable magasin, super-admin.
5. Bloquer les workflows non conformes : fin d'intervention avant rapport signé, fiction d'annulation de facture sans avoir, demandes complémentaires contournables.
6. Revoir immédiatement les points RGPD les plus exposés : consentement booking, pièces d'identité VO, données trop riches dans les compagnons.

Résultat attendu :
Plus aucun écart majeur entre ce que le produit promet et ce que le code autorise réellement sur les sujets sensibles.

#### Paquet 2. Simplifications de workflow et permissions

Objectif : remettre une seule vérité par métier.

À traiter ensuite :
1. Désigner un écran maître pour chaque workflow critique : réception, travaux complémentaires, VO, facturation, intervention.
2. Refaire la matrice de rôles et permissions autour des métiers réels, sans dépendre de ROLE_ADMIN comme béquille générale.
3. Isoler clairement les espaces : opération atelier, direction, comptabilité, super-admin, VO.
4. Transformer les cockpits en écrans d'action avec files d'attente, exceptions et décisions attendues.

Résultat attendu :
Chaque métier sait où agir, ce qu'il peut faire et ce qui relève d'un autre rôle.

#### Paquet 3. Dette UX et dette de modèle de données

Objectif : nettoyer durablement le socle.

À traiter en troisième vague :
1. Renommer les champs ambigus et séparer les responsabilités sémantiques.
2. Harmoniser tous les statuts partagés front/back.
3. Revoir les payloads, formulaires et PDFs selon une doctrine unique de minimisation et de conformité.
4. Recomposer les dashboards pour qu'ils servent la décision plutôt que la simple consultation.

Résultat attendu :
Le modèle devient compréhensible, maintenable et capable d'accueillir de nouvelles fonctionnalités sans replanter les mêmes incohérences.

### Priorisation nette

Si je dois trancher brutalement l'ordre réel de travail :

1. Sécurité et périmètre des compagnons.
2. Rôles et permissions réels.
3. Workflows critiques avec écran maître unique.
4. Facturation/compta conforme.
5. Nettoyage sémantique du modèle et cockpits de pilotage.

### Conclusion opérationnelle

Le projet n'est pas en échec fonctionnel. Il est en dette d'alignement.

Ce qu'il faut faire maintenant n'est pas “ajouter encore des options”.
Ce qu'il faut faire, c'est rétablir des frontières nettes :
- un rôle
- un périmètre
- un workflow
- un écran maître
- une vérité de données

Tant que ces cinq points ne sont pas stabilisés, chaque ajout fonctionnel créera une nouvelle incohérence au lieu de renforcer le produit.

---

## Lecture comité de pilotage

### Position franche

Si je prends une posture comité de pilotage, il faut arrêter de piloter ce produit comme un empilement de fonctionnalités.

Le sujet n'est plus “qu'est-ce qu'on peut encore ajouter ?”.
Le sujet est devenu :
- qu'est-ce qu'on garde parce que c'est sain
- qu'est-ce qu'on coupe parce que ça ment ou expose trop
- qu'est-ce qu'on reconstruit parce que le socle est faux

Aujourd'hui, le plus gros risque n'est pas le manque de richesse fonctionnelle.
Le plus gros risque, c'est de continuer à industrialiser des zones mal cadrées et de rendre la refonte plus coûteuse dans 3 mois qu'aujourd'hui.

### À garder

#### 1. Les vraies briques métier déjà présentes dans le back

À garder :
- les garde-fous VO sur DA SIV, mandat, vente bloquée, Livre de Police
- les bases du workflow RDV / réception / intervention / restitution
- la logique de rôles métier avancés comme intention de départ
- les briques d'audit existantes
- les bases multi-atelier et le changement de contexte super-admin

Pourquoi on garde :
Le projet n'est pas vide. Il a déjà les bonnes intuitions de domaine. Le problème vient surtout du branchement produit et sécurité, pas d'une absence totale de fondations.

Verdict :
On garde les noyaux métier robustes. On évite la tentation de tout jeter.

#### 2. Les parcours qui ont une finalité simple et lisible

À garder :
- réservation publique, sous réserve de correction multi-atelier et RGPD
- suivi client, mais seulement s'il devient réellement utile
- écrans VO dossier quand ils servent de source de vérité
- espace mécanicien recentré sur l'exécution technique

Pourquoi on garde :
Ces surfaces ont une finalité claire. Elles sont récupérables sans chirurgie lourde si on leur enlève les ambiguïtés.

Verdict :
On garde ce qui peut redevenir lisible vite.

### À supprimer

#### 1. Les fictions produit

À supprimer réellement :
- l'idée qu'un rôle existe si la sécurité ne le matérialise pas
- l'idée qu'un workflow est conforme s'il a plusieurs écrans de vérité
- l'idée qu'un audit est global s'il est lisible par l'admin local
- l'idée qu'un compagnon “public” est acceptable alors qu'il s'agit en réalité d'un poste interne assisté

Pourquoi il faut couper :
Ces fictions empoisonnent toutes les discussions produit. Elles font perdre du temps, car l'équipe raisonne sur une carte du produit qui n'est pas la vraie.

Verdict :
On retire ces promesses du discours, des specs et des arbitrages immédiatement.

#### 2. Les patterns techniquement dangereux sans vraie valeur produit

À supprimer ou désactiver très vite :
- tokens en query string
- exposition des compagnons sous des routes publiques si leur usage est interne
- faux statuts ou faux workflows comptables
- duplications de conduite de workflow entre plusieurs écrans

Pourquoi il faut couper :
Ce sont des multiplicateurs de dette sans avantage stratégique réel. Ils compliquent la conformité, la sécurité et le support, sans créer un bénéfice utilisateur proportionnel.

Verdict :
Ce qui est dangereux et remplaçable doit disparaître avant la prochaine vague de features.

#### 3. Les zones “cosmétiques” qui donnent une illusion de maturité

À supprimer ou réduire :
- dashboards qui comptent sans orienter l'action
- suivis clients trop pauvres pour servir réellement
- progress bars VO qui masquent des verrous juridiques durs
- menus admin qui donnent l'impression d'un cockpit alors qu'ils ne sont qu'un empilement de cartes

Pourquoi il faut couper :
Le cosmétique crée de la dette politique. L'équipe croit que le besoin est couvert alors qu'il ne l'est pas.

Verdict :
Mieux vaut une surface plus petite mais honnête qu'un faux sentiment de complétude.

### À reconstruire

#### 1. La matrice rôles / permissions / responsabilités

À reconstruire complètement :
- comptable
- responsable atelier
- responsable magasin
- super-admin
- articulation rôle système / rôle métier / permissions fines

Pourquoi il faut reconstruire :
Le modèle actuel est la cause racine d'une grande partie des incohérences. Tant qu'il n'est pas repris sérieusement, chaque correction restera locale et fragile.

Verdict :
Ce n'est pas un patch. C'est un chantier de refondation.

#### 2. Les workflows critiques avec écran maître unique

À reconstruire :
- réception atelier
- travaux complémentaires
- intervention + rapport mécanicien
- chaîne VO de collecte / conformité / vente
- facturation et correction comptable

Pourquoi il faut reconstruire :
Le défaut n'est pas seulement technique. C'est un défaut de conduite produit. Il faut réimposer un seul chemin maître par processus critique.

Verdict :
Chaque workflow critique doit ressortir avec :
- un responsable métier
- un écran maître
- un ordre des étapes non ambigu
- des règles de blocage explicites

#### 3. L'architecture des interfaces exposées

À reconstruire :
- distinction entre outils internes authentifiés
- outils PDA assistés par employé
- parcours clients distants réellement autonomes

Pourquoi il faut reconstruire :
Aujourd'hui ces trois familles sont mélangées. C'est une erreur d'architecture produit, pas juste une erreur de routing.

Verdict :
Tant que ces trois familles ne sont pas séparées, on continuera à mélanger UX, sécurité et RGPD.

### Ce que je couperais dès maintenant si on devait sécuriser le produit en urgence

1. Les usages “publics” des compagnons atelier et VO tels qu'ils sont aujourd'hui implémentés.
2. Toute ambiguïté sur l'annulation simple de facture sans avoir.
3. Les tokens en query string.
4. Toute validation locale de travaux complémentaires qui contourne la preuve d'accord.

### Ce que je laisserais vivre pendant la refonte

1. Le dashboard atelier, mais sans lui faire porter un rôle de cockpit de direction.
2. Le suivi public, mais uniquement si on accepte qu'il est aujourd'hui basique.
3. Les dossiers VO existants, à condition de traiter rapidement les sujets RGPD et tokens.
4. Le multi-atelier, à condition de rendre le scope plus explicite pour le super-admin.

### Ce que je considère comme non négociable

1. Un rôle métier doit devenir une réalité technique, pas un libellé.
2. Un outil interne ne doit plus être exposé comme faux parcours public par commodité.
3. Une facture ne peut pas être traitée comme un document atelier approximatif.
4. Les données sensibles doivent être minimisées par design, pas corrigées après coup.
5. Un workflow critique ne doit jamais avoir plusieurs vérités concurrentes.

### Arbitrage coût / risque

Lecture dure :
- risque actuel élevé sur sécurité, conformité et cohérence opérationnelle
- coût de refonte modéré à fort si on agit maintenant
- coût de refonte très fort si on laisse encore grossir le modèle actuel

Traduction directe :
Reporter la remise à plat n'est pas une économie. C'est une dette qui capitalise.

### Décision de pilotage recommandée

Si j'étais en comité de pilotage, je proposerais :

1. Gel temporaire des ajouts fonctionnels sur les zones réception compagnon, VO compagnon, rôles/permissions et compta tant que les fondations ne sont pas recadrées.
2. Refonte en premier des frontières de sécurité et de périmètre produit.
3. Reprise ensuite des workflows critiques avec écran maître unique.
4. Reprise enfin des cockpits, dashboards et enrichissements UX.

### Conclusion comité

Le produit a de la valeur.
Mais il commence à ressembler à un système qui raconte mieux ce qu'il voudrait être que ce qu'il est réellement.

À ce stade, la bonne décision n'est pas d'accélérer.
La bonne décision est de couper les zones mensongères, stabiliser les zones critiques, puis reconstruire proprement ce qui mérite de survivre.