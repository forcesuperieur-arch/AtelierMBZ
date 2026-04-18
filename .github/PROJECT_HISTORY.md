<!-- markdownlint-disable MD022 MD024 MD032 -->

# Historique projet AtelierMBZ

## Session 2026-04-17 — Companion VO dès la création

### Fait
- [LOT-0] fix — correction des liens documents VO côté front pour le compagnon public
- [LOT-0] ajoute — carte compagnon VO plus visible sur les fiches rachat et dépôt
- [LOT-0] ajoute — activation du compagnon dès la création du dossier via brouillon + QR code PDA
- [LOT-0] fix — finalisation backend d'un brouillon VO sans double création de dossier
- Vérification manuelle après rebuild : pages création rachat et dépôt disponibles

### Décisions
- Le parcours compagnon démarre avant l'enregistrement complet du dossier
- Le QR code doit être affiché immédiatement pour permettre scan, OCR et signature en amont
- Le lot a été poussé en WIP sur la branche atelier-v2-only pour reprise le lendemain

### TODO laissés
- Aucun TODO encore ouvert sur ce lot : le flux compagnon brouillon, la finalisation métier et les tests ciblés ont été livrés dans les sessions suivantes.

### En suspens à arbitrer
- Gérer ou non une deuxième signature distincte selon rachat ou dépôt-vente

## Session 2026-04-18 — Reprise / cadre de session

### Fait
- Lecture systématique du fichier historique réactivée et confirmée
- État WIP précédemment poussé sur le remote pour sécuriser l'avancement
- [LOT-0] fix — sur le rachat, le QR compagnon est maintenant préparé dès l'ouverture de la fenêtre
- [LOT-0] ajoute — synchronisation automatique du brouillon rachat pour remonter les infos scannées dans le dossier
- Migration appliquée pour autoriser un brouillon rachat sans vendeur ni véhicule au démarrage

### Décisions
- Ce fichier devient le point d'entrée systématique de chaque session
- Le rachat peut démarrer par le QR PDA avant la saisie complète du dossier

### TODO laissés
- Aucun TODO encore ouvert sur ce lot : le dépôt-vente suit désormais le même mode brouillon immédiat.

### En suspens à arbitrer
- Aucun.

## Session 2026-04-18 — Remise en etat VO

### Fait
- [LOT-0] ajoute — socle backend Remise en etat VO avec entités campagne, lignes prestations, pièces et migration Doctrine
- [LOT-0] ajoute — API VO dédiée pour lister, créer, modifier et clôturer les campagnes de remise en etat côté rachat et dépôt
- [LOT-0] fix — blocage métier de la vente VO tant qu'une campagne active de remise en etat n'est pas clôturée
- [LOT-0] ajoute — carte front Remise en etat VO sur les fiches rachat et dépôt avec gestion lignes/pieces
- [LOT-0] ajoute — page File atelier VO + entrée de navigation dédiée
- [LOT-0] fix — les wizards VO rachat/dépôt capturent désormais catégorie tarifaire, type atelier et cylindrée pour fiabiliser les prestations et prix proposés en remise en etat
- [LOT-0] test — ajout de Vitest et d'une couverture front ciblée sur le payload véhicule VO utilisé par le wizard
- Migration `Version20260604180000` appliquée avec succès dans le conteneur PHP
- Migration `Version20260604190000` appliquée avec succès dans le conteneur PHP
- Vérifications manuelles après rebuild : `https://localhost/vo/remises-en-etat` et `https://localhost/vo/rachats/new` répondent en 200
- [LOT-0] test — couverture PHPUnit ciblée sur `VORemiseEnEtatService` exécutée avec succès dans le conteneur PHP
- [LOT-0] ajoute — audit des créations, mises à jour et suppressions sensibles sur les campagnes, lignes et pièces de remise en etat VO
- QA navigateur Playwright validée après rebuild du conteneur Nuxt sur `vo/rachats/new`, `vo/depots/new` et `vo/remises-en-etat`
- [LOT-0] ajoute — Companion VO achat/dépôt en vrai mode brouillon : auto-création d'un dépôt brouillon, hydratation prudente depuis le PDA et finalisation du même dossier au lieu d'une recréation
- [LOT-0] ajoute — archivage centralisé des PDF VO générés via `VOGeneratedDocumentService`, déclenché à la signature PDA et à la finalisation quand les prérequis légaux sont réunis
- [LOT-0] fix — verrouillage public du PDA VO après signature pour empêcher toute réédition silencieuse du brouillon signé
- [LOT-0] test — couverture backend/front Companion VO + Remise en etat complétée, puis scénario Playwright déterministe validant l'écart tarifaire entre deux catégories moto

### Décisions
- Une seule campagne de remise en etat VO peut être active par dossier à un instant donné
- La remise en etat VO devient un vrai pont VO ↔ atelier avec file d'attente, prestations catalogue et mini-workflow pièces
- La vente d'un VO reste bloquée tant qu'une campagne active n'est pas clôturée ou annulée
- Les prix/prestations affichés en remise en etat restent pilotés par le catalogue atelier existant; la fiabilité dépend donc de la catégorie tarifaire, du type moto et de la cylindrée portés par le véhicule VO
- Le dépôt-vente Companion doit vivre en `brouillon` réel tant que véhicule, déposant et données légales ne sont pas finalisés; on met à jour le même dossier jusqu'à validation
- L'archivage des documents VO générés est centralisé et ne part qu'au moment où le dossier est juridiquement prêt ou signé; la signature publique verrouille ensuite le PDA

### TODO laissés
- Aucun TODO encore ouvert sur ce lot : la signature dématérialisée et le fallback PDF des remises en etat ont été livrés dans la session suivante.

### En suspens à arbitrer
- Aucun.

## Session 2026-04-18 — Companion VO E2E et clôture atelier

### Fait
- [LOT-0] test — ajout d'un E2E Playwright complet sur le flux Companion VO achat + dépôt jusqu'au verrouillage PDA post-signature puis archivage documentaire après finalisation admin
- [LOT-0] fix — la clôture finale d'une remise en etat VO est désormais réservée à la réception / atelier quand le rôle métier est explicite; le responsable magasin n'est plus accepté pour lever le blocage vente
- [LOT-0] ajoute — les logs d'audit Remise en etat VO embarquent désormais le rôle legacy et le rôle métier de l'acteur sur les actions sensibles
- [LOT-0] test — ajout d'une couverture fonctionnelle PHPUnit pour verrouiller la règle responsable magasin refusé / responsable atelier autorisé sur la clôture

### Décisions
- La clôture qui débloque une vente VO relève de l'atelier; le responsable magasin ne doit plus pouvoir la faire quand son rôle métier est identifié
- Les admins legacy sans rôle métier explicite restent tolérés temporairement pour ne pas casser les comptes historiques déjà en base

### TODO laissés
- Aucun TODO encore ouvert sur ce lot : la signature dématérialisée et le fallback PDF des remises en etat ont été livrés dans la session suivante.

### En suspens à arbitrer
- Aucun.

## Session 2026-04-18 — Signature remise en etat VO

### Fait
- [LOT-0] ajoute — signature dématérialisée des documents de remise en etat VO avec snapshot, hash, acteur, IP et archivage PDF immuable par campagne
- [LOT-0] ajoute — PDF live de campagne de remise en etat + fallback archivé automatique à la clôture quand aucune signature n'a été posée
- [LOT-0] ajoute — carte front de remise en etat enrichie avec téléchargement PDF courant, accès au PDF archivé et zone de signature canvas
- [LOT-0] test — couverture fonctionnelle PHPUnit étendue sur signature + archivage fallback, lint PHP/Twig vert sur les nouveaux fichiers

### Décisions
- Le PDF courant reste toujours disponible en lecture, même après signature; la version archivée reste la preuve figée
- L'archivage documentaire est rattaché à la campagne de remise en etat elle-même, pas seulement au dossier VO, pour éviter tout écrasement entre campagnes historiques
- Si une campagne clôturée n'a jamais été signée, on archive automatiquement un PDF fallback pour conserver une trace exploitable côté atelier/VO

### TODO laissés
- Aucun TODO ouvert sur ce lot.

### En suspens à arbitrer
- Rejouer la suite PHPUnit complète dès qu'un environnement avec l'hôte PostgreSQL `db` est disponible hors Docker intégré.
