# Référence architecture — AtelierMBZ

## Composables existants (NE PAS RECRÉER)
- `useApi()` → retourne `api.get/post/put/patch/delete` avec auth JWT auto
- `useAuth()` → `user`, `isAdmin`, `isSuperAdmin`, `isMecanicien`, `isVoManager`, `logout()`
- `useFormat()` → `formatDate()`, `formatPrice()`, `formatPhone()`
- `useToast()` → `toast.add({ title, description, color })` — couleurs: success, error, warning

## Stores Pinia existants (NE PAS RECRÉER)
- `stores/auth.ts` → gère le JWT, le user, le refresh
- `stores/rdv.ts` → CRUD rendez-vous, transitions workflow
- `stores/vo.ts` → CRUD rachats, dépôts, factures VO, stats
- `stores/mecanicien.ts` → liste mécaniciens, affectations
- `stores/vehicule.ts` → CRUD véhicules, recherche par plaque
- `stores/client.ts` → CRUD clients, recherche multi-critères

## Composants Nuxt UI v3 à utiliser (NE PAS RECODER)
- `<UButton>` — pas de `<button>` natif
- `<UCard>` — pas de `<div class="card">`
- `<UInput>` — pas de `<input>` natif
- `<UFormField>` — wrapper label + erreur
- `<UTextarea>` — pas de `<textarea>` natif
- `<UTable>` — pas de `<table>` natif
- `<UModal>` — pas de modale custom
- `<USelect>` — pas de `<select>` natif

## Services backend existants (NE PAS RECRÉER)
- `AuditService` → `log(action, entity, data, user)`
- `PricingService` → calcul prix selon mode tarif (forfait/horaire/sur_devis)
- `VOMarginService` → calcul marge VO, simulation
- `VODocumentService` → vérif docs manquants par dossier
- `VOLivrePoliceService` → enregistrement LP, numérotation séquence
- `PdfService` → génération PDF via DomPDF + Twig
- `SmsService` → envoi SMS via Messenger async
- `TokenService` → génération de tokens pour parcours bornés ; jamais de secret en query string

## Entités clés et leurs relations
- `RendezVous` → hasMany `OrdreReparation`, belongsTo `Client`, `Vehicule`, `Mecanicien`, `Pont`
- `OrdreReparation` → belongsTo `RendezVous`, hasMany `LigneOr`, hasOne signature
- `Client` → hasMany `Vehicule`, `RendezVous`
- `Vehicule` → belongsTo `Client`, uses `VOTrait` (mileage, vin, couleur, dateMEC, CT)
- `VOPurchase` → belongsTo `Vehicule`, `Client` (vendeur), hasOne `VOFacture`, `VOLivrePolice`
- `VODepotVente` → belongsTo `Vehicule`, `Client` (déposant)
- `VOFacture` → belongsTo `VOPurchase` ou `VODepotVente`, hasMany `Paiement`
- `VOLivrePolice` → belongsTo `VOPurchase` ou `VODepotVente`

## Variables CSS existantes (NE PAS INVENTER de nouvelles)
- `--dark2`, `--dark3` → backgrounds
- `--glass-border` → bordures translucides
- `--radius-lg` → border-radius standard
- `--accent`, `--accent-hover` → couleur principale

## Conventions de nommage
- Backend : camelCase pour propriétés PHP, snake_case pour colonnes SQL
- Frontend : camelCase partout (variables, fonctions, props)
- Routes API : kebab-case `/api/vo/depot-ventes/{id}/prolonger`
- Fichiers pages : kebab-case `pages/vo/rachats/[id].vue`

## Frontières d'interface actées
- Outils internes authentifiés : administration, atelier, VO, comptabilité ; jamais exposés sous `/public`
- Outils PDA assistés : surfaces utilisées par un employé en présence du client ; périmètre strictement borné à la collecte ou à la signature
- Parcours publics autonomes : réservation, suivi, validation distante réellement voulue ; données minimales et finalité unique

## Contraintes de sécurité et d'exposition
- Aucun token, secret ou jeton de parcours ne doit apparaître en query string
- Un outil interne assisté ne doit pas être implémenté comme un faux tunnel public par commodité
- Tout parcours tokenisé doit exposer le minimum de données utile à l'action immédiate
- Les pages légales publiques doivent être réellement accessibles sans authentification quand elles sont citées dans un tunnel public

## Contraintes workflow
- Un workflow critique = un écran maître ; les autres écrans affichent l'état mais ne recréent pas une logique concurrente
- Les validations opposables ne se contournent jamais localement : accord client, signature, avoir, blocage légal
- La transition d'état doit refléter la réalité métier terminée, pas un brouillon à compléter plus tard

## Contraintes rôles et permissions
- Un rôle métier annoncé par le produit doit correspondre à de vraies permissions et de vrais guards
- `ROLE_ADMIN` ne doit pas servir de béquille générique pour simuler responsable atelier, responsable magasin ou comptable
- `ROLE_SUPER_ADMIN` est un rôle de plateforme ; il ne doit jamais être remappé conceptuellement comme métier opérationnel

## Contraintes VO / conformité
- Les formulaires VO réglementés utilisent le vrai CERFA requis ou un rendu strictement conforme à sa structure officielle
- DA SIV = Cerfa 13751 quand la fonctionnalité est couverte
- Mandat d'immatriculation = Cerfa 13757*03 quand le mandat est utilisé
- Certificat de cession = Cerfa 15776*02 quand il est requis
- Pièce d'identité et justificatif de domicile ne doivent jamais être conservés après transcription
