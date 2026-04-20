# Checklist de revue — avant d'accepter le code de Copilot

## Doublons
- [ ] Aucun composable recréé (useApi, useAuth, useFormat, useToast existent déjà)
- [ ] Aucun store Pinia recréé (auth, rdv, vo, mecanicien, vehicule, client existent)
- [ ] Aucun service backend recréé (AuditService, PricingService, VOMarginService, etc.)
- [ ] Aucun composant UI natif utilisé à la place de Nuxt UI (button, input, select, table, modal)
- [ ] Aucune variable CSS inventée (utiliser --dark2, --dark3, --glass-border, --accent, --radius-lg)

## Architecture
- [ ] TenantFilter respecté (atelierId présent, pas de bypass)
- [ ] Workflow utilisé pour les transitions (pas de setStatut direct)
- [ ] Aucun workflow critique n'a deux écrans de vérité concurrents
- [ ] Aucun rôle métier fantôme (si le produit annonce un rôle, les guards et permissions le matérialisent vraiment)
- [ ] Aucun outil interne assisté exposé sous /public par commodité
- [ ] Aucun token, secret ou lien signé passé en query string
- [ ] AuditService::log() appelé sur les actions sensibles
- [ ] bcmath utilisé pour les calculs monétaires (pas de float)
- [ ] Groupes de sérialisation corrects sur les nouvelles propriétés

## Workflow / produit
- [ ] Un seul écran maître par workflow critique (réception, intervention, travaux complémentaires, VO, facturation)
- [ ] Aucun chemin local ne contourne une validation opposable (accord client, signature, avoir, blocage légal)
- [ ] Les champs métier ne mélangent pas plusieurs responsabilités (motif client, notes réception, notes atelier, données mécanicien)
- [ ] Les statuts partagés front/back utilisent le même vocabulaire et la même granularité

## Style
- [ ] PHP : types de retour, constructor promotion, attributs ORM
- [ ] Vue : <script setup lang="ts">, ref/computed/watch
- [ ] Pas de console.log, dump(), var_dump() laissés
- [ ] Pas d'emojis dans le code technique
- [ ] Nommage cohérent avec l'existant (camelCase PHP, camelCase TS, snake_case SQL)

## Légal (si module VO ou facturation)
- [ ] Mentions obligatoires présentes dans les templates PDF
- [ ] Les documents VO réglementés utilisent le vrai CERFA requis ou un rendu strictement conforme à sa structure officielle
- [ ] DA SIV = Cerfa 13751 si la fonctionnalité est couverte
- [ ] Mandat d'immatriculation = Cerfa 13757*03 quand le mandat est utilisé
- [ ] Certificat de cession = Cerfa 15776*02 quand il est requis
- [ ] Numérotation par séquence PostgreSQL (pas MAX+1)
- [ ] Livre de Police : pas de PUT/PATCH/DELETE
- [ ] Pièce d'identité : jamais persistée au-delà de la transcription
- [ ] Justificatif de domicile : jamais persisté au-delà de la transcription
- [ ] Régime TVA cohérent (marge OU normal, jamais les deux)
- [ ] Aucun faux consentement RGPD enregistré côté public sans information et action explicite de l'utilisateur

## Tests
- [ ] Test PHPUnit sur nouveau service métier
- [ ] Test Vitest sur nouveau composable
- [ ] Au moins 1 cas d'erreur testé
