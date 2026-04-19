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
- [ ] AuditService::log() appelé sur les actions sensibles
- [ ] bcmath utilisé pour les calculs monétaires (pas de float)
- [ ] Groupes de sérialisation corrects sur les nouvelles propriétés

## Style
- [ ] PHP : types de retour, constructor promotion, attributs ORM
- [ ] Vue : <script setup lang="ts">, ref/computed/watch
- [ ] Pas de console.log, dump(), var_dump() laissés
- [ ] Pas d'emojis dans le code technique
- [ ] Nommage cohérent avec l'existant (camelCase PHP, camelCase TS, snake_case SQL)

## Légal (si module VO ou facturation)
- [ ] Mentions obligatoires présentes dans les templates PDF
- [ ] Numérotation par séquence PostgreSQL (pas MAX+1)
- [ ] Livre de Police : pas de PUT/PATCH/DELETE
- [ ] Pièce d'identité : jamais persistée au-delà de la transcription
- [ ] Régime TVA cohérent (marge OU normal, jamais les deux)

## Tests
- [ ] Test PHPUnit sur nouveau service métier
- [ ] Test Vitest sur nouveau composable
- [ ] Au moins 1 cas d'erreur testé
