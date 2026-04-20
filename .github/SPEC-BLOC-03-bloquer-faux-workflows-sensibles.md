# SPEC-BLOC-03 — Bloquer les faux workflows sensibles

## Objectif

Supprimer les écarts entre workflow annoncé et réalité exécutable sur les sujets les plus risqués : intervention atelier, travaux complémentaires, facturation corrective et RGPD public/VO.

Ce bloc ne refond pas encore tous les écrans maîtres, mais il doit fermer les contournements les plus dangereux.

## Problèmes à corriger

1. Une intervention peut être marquée terminée avant rapport mécanicien signé.
2. Les travaux complémentaires peuvent être validés depuis une logique locale concurrente au workflow opposable.
3. La facturation expose une fiction d'annulation sans vrai flux d'avoir.
4. Le booking public enregistre un consentement qui n'est pas exprimé clairement.
5. Les pièces d'identité et justificatifs VO sont détruits trop tard par rapport à la règle métier fixée.

## Décisions figées

1. Le statut `termine` doit signifier intervention finie et rapport mécanicien verrouillé, pas “travail fini, document plus tard”.
2. Une demande de travaux complémentaires n'est jamais considérée validée sans la preuve d'accord attendue par le workflow cible.
3. Une facture émise ne s'annule pas librement ; la correction passe par un avoir ou, à minima dans ce bloc, l'absence d'annulation simple doit être assumée explicitement.
4. Le booking public ne doit plus enregistrer un faux consentement RGPD explicite.
5. Pièce d'identité et justificatif VO ne doivent pas survivre au-delà de la transcription nécessaire.

## Périmètre inclus

- Workflow mécanicien / rapport d'intervention
- Workflow travaux complémentaires
- Garde-fous minimum sur facturation corrective
- Booking public et base légale affichée
- Flux VO d'identité et justificatif

## Hors périmètre

- Refonte complète de l'écran mécanicien
- Refonte complète de la réception
- Refonte complète du module comptable
- Refonte complète du dossier VO

## Fichiers de départ à relire avant implémentation

Backend :
- backend/src/Controller/RendezVousController.php
- backend/src/Controller/MecanicienController.php
- backend/src/Controller/RapportInterventionController.php
- backend/src/Service/RapportInterventionService.php
- backend/src/Controller/DemandeTravauxSuppController.php
- backend/src/Controller/FacturationController.php
- backend/src/Controller/PublicBookingController.php
- backend/src/Controller/PublicVoCompanionController.php
- backend/src/Service/VODocumentService.php
- backend/src/Entity/VODocument.php
- backend/src/Schedule.php

Frontend :
- frontend/pages/mecanicien.vue
- frontend/pages/ordres/[id].vue
- frontend/pages/admin/demandes-travaux-supp.vue
- frontend/pages/facturation/index.vue
- frontend/pages/public/booking.vue
- frontend/pages/public/vo-companion.vue

## Implémentation attendue par couche

### 1. Intervention atelier / rapport

À faire :
- empêcher la transition de fin d'intervention tant que le rapport mécanicien n'est pas complet et signé
- réaligner la séquence front pour que le passage à `termine` ne précède plus la signature
- vérifier la cohérence avec l'essai routier déjà obligatoire

Contraintes :
- ne pas autoriser un contournement via un endpoint alternatif
- le statut final doit refléter une réalité documentaire complète

### 2. Travaux complémentaires

À faire :
- supprimer côté front la logique locale d'approbation/refus qui court-circuite le workflow cible
- vérifier côté back qu'aucune action locale ne peut produire un effet équivalent sans preuve d'accord
- conserver un affichage d'état transverse, mais pas une seconde conduite du workflow

Contraintes :
- si la décision distante client reste maintenue, le flux opposable doit rester unique
- si un arbitrage interne existe temporairement, il doit être explicitement distingué d'un accord client

### 3. Facturation corrective minimale

À faire :
- retirer de l'UI et du vocabulaire les statuts ou actions laissant croire qu'une facture peut être simplement annulée
- interdire côté back toute opération implicite assimilable à une annulation simple si elle existe
- préparer le terrain pour le futur flux d'avoir en rendant la limite explicite

Contraintes :
- ne pas inventer un faux avoir simplifié dans ce bloc
- ne pas masquer le problème par simple renommage sans blocage réel

### 4. Booking public / RGPD

À faire :
- retirer l'enregistrement d'un consentement explicite fictif si l'interface ne le recueille pas
- afficher clairement l'information de traitement et le lien vers la politique de confidentialité
- si un consentement spécifique est réellement nécessaire pour un usage donné, ajouter l'action explicite correspondante

Contraintes :
- ne pas confondre base légale contractuelle / précontractuelle avec consentement marketing

### 5. VO — identité et justificatif

À faire :
- supprimer le stockage durable de pièce d'identité et justificatif tant que la destruction immédiate n'est pas garantie
- soit passer à transcription puis destruction immédiate,
- soit retirer temporairement l'upload de ces documents du parcours assisté
- ajuster la logique de purge planifiée si elle ne porte plus que sur des résidus exceptionnels

Contraintes :
- ne pas laisser croire que `retentionYears = 0` suffit si le fichier reste stocké jusqu'au prochain batch
- ne pas déplacer ces pièces dans un autre stockage durable non cadré

### 6. Tests

Back :
- tests empêchant `terminer` sans rapport signé
- tests empêchant la validation locale d'une demande complémentaire hors workflow cible
- tests garantissant l'absence d'annulation simple de facture si UI/API la proposaient
- tests sur booking sans faux consentement explicite
- tests sur absence de persistance durable des pièces VO sensibles

Front :
- tests sur séquence de fin d'intervention
- tests sur disparition des actions locales d'approbation/refus
- tests sur affichage légal du booking public

## Critères d'acceptation

1. Un RDV ne peut plus devenir `termine` avant rapport mécanicien signé.
2. L'OR ou un autre écran local ne peut plus approuver/refuser des travaux complémentaires comme s'il remplaçait le workflow opposable.
3. L'interface ne laisse plus croire qu'une facture émise peut être simplement annulée.
4. Le booking public n'enregistre plus un consentement explicite fictif.
5. Pièce d'identité et justificatif VO ne sont plus conservés durablement après transcription.

## Validation manuelle

1. Tenter de clôturer une intervention sans rapport signé.
2. Vérifier le comportement des travaux complémentaires depuis OR, page dédiée et lien client.
3. Vérifier les filtres et libellés de facturation pour supprimer l'idée d'annulation simple.
4. Réserver un RDV depuis le tunnel public et vérifier l'information RGPD affichée et la donnée réellement enregistrée.
5. Simuler un parcours VO avec pièce d'identité et justificatif et vérifier qu'aucun stockage durable injustifié ne subsiste.

## Risques / vigilance

- Ne pas livrer un blocage métier sans issue UX claire pour l'utilisateur.
- Ne pas transformer une suppression de faux workflow en régression silencieuse non documentée.
- Ce bloc ferme des contournements ; la vraie refonte d'écran maître viendra ensuite dans les lots 04 à 07.