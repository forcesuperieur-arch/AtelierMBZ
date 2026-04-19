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
- `TokenService` → génération tokens publics (companion, suivi client)

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
