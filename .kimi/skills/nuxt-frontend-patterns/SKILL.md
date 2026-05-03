# Nuxt Frontend Patterns — Skill Projet

## Stack
Nuxt 3.21.2 + Vue 3.5 + Pinia + Nuxt UI v3 + Tailwind CSS

## Patterns de page
```vue
<script setup>
definePageMeta({ title: 'Mon titre' })

const { data, refresh } = await useApi('/endpoint')
const toast = useToast()
</script>
```

## Store Pinia (option API)
```typescript
export const useMonStore = defineStore('mon', {
  state: () => ({ items: [], loading: false }),
  getters: { count: (state) => state.items.length },
  actions: {
    async fetchItems() {
      this.loading = true
      try {
        const { data } = await useApi('/endpoint')
        this.items = data.value || []
      } finally {
        this.loading = false
      }
    }
  }
})
```

## Composable useApi (existant)
- `useApi(url, opts?)` — wrapper autour de `$fetch` avec baseURL et auth
- Retourne `{ data, error, refresh, pending }`
- Toujours préférer `useApi` à `$fetch` direct pour la cohérence auth

## Composants UI Nuxt UI v3
| Composant | Usage |
|---|---|
| `UTable` | Listes avec colonnes personnalisées via `#<col>-cell` slots |
| `UButton` | Actions, icon prop pour les icônes |
| `UModal` | Modals avec `v-model:open` |
| `UForm` | Formulaires avec validation |
| `UInput` | Champs texte, number, email |
| `USelectMenu` | Select avec recherche (`v-model`, `:options`) |
| `UBadge` | Statuts (`color="green|orange|red|blue"`) |
| `USkeleton` | États de chargement |

## Conventions UI
- **KPIs** : grille de cards en haut de page (`grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-5`)
- **Tableaux** : `UTable` avec `:rows`, `:columns`, `:loading`, actions par ligne
- **Modals** : formulaire dans `UForm` avec `@submit`, bouton cancel en gris
- **Toasts** : `toast.add({ title: '...', description: '...', color: 'green|red' })`
- **Couleurs stock** : quantité ≤ seuil → texte `red`, sinon `green`

## Build et validation
```bash
npm run build    # Doit passer à 0 erreur
npm run dev      # Dev server
```
- Pas de `eslint.config.js` dans le projet → `npm run lint` échoue, ignorer
- Warnings `@nuxt/icon` et `@vue/shared` sont normaux (deprecation non bloquante)

## Fichiers clés
- `frontend/stores/stock.ts` — Exemple canonique de store avec normalisation
- `frontend/composables/useApi.ts` — Wrapper API
- `frontend/layouts/default.vue` — Layout avec sidebar et quick actions
