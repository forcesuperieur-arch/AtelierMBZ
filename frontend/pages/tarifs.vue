<template>
  <div>
    <AppPageHeader title="Grille Tarifaire" subtitle="Vue rapide des prestations atelier et de leurs prix publics." />

    <UCard class="mb-4">
      <div class="flex gap-3 items-end flex-wrap">
        <UFormField label="Recherche" class="min-w-[260px]">
          <UInput v-model="search" placeholder="Prestation, catégorie..." />
        </UFormField>
        <div class="ml-auto text-xs text-gray-400">
          {{ filteredPrestations.length }} prestation(s) affichée(s)
        </div>
      </div>
    </UCard>

    <AppLoadingState
      v-if="loading"
      title="Chargement des tarifs"
      description="La grille tarifaire est en cours de récupération."
    />

    <AppErrorState
      v-else-if="errorMessage"
      title="Tarifs indisponibles"
      :description="errorMessage"
      @retry="loadPrestations"
    />

    <template v-else>
      <div class="flex gap-2 mb-5 flex-wrap">
        <button
          v-for="cat in categories"
          :key="cat"
          class="category-pill"
          :class="{ 'category-pill--active': selectedCat === cat }"
          @click="selectedCat = selectedCat === cat ? '' : cat"
        >
          {{ cat }}
        </button>
      </div>

      <UCard>
        <UTable v-if="filteredPrestations.length" :data="filteredPrestations" :columns="columns" :loading="loading">
          <template #temps_estime_minutes-cell="{ row }">
            {{ row.original.temps_estime_minutes ?? '–' }} min
          </template>
          <template #prix_base_ht-cell="{ row }">
            {{ formatAmount(row.original.prix_base_ht) }}
          </template>
          <template #prix_base_ttc-cell="{ row }">
            <span class="font-bold text-amber-400">{{ formatAmount(row.original.prix_base_ttc) }}</span>
          </template>
        </UTable>
        <AppEmptyState
          v-else
          title="Aucune prestation ne correspond à la recherche"
          description="Ajuste la catégorie ou le texte saisi pour revoir les tarifs disponibles."
          action-label="Réinitialiser"
          @action="resetFilters"
        />
      </UCard>
    </template>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const toast = useToast()
const { formatCurrency } = useFormat()
const loading = ref(true)
const errorMessage = ref('')
const prestations = ref<any[]>([])
const selectedCat = ref('')
const search = ref('')

const columns = [
  { key: 'nom', label: 'Prestation' },
  { key: 'categorie', label: 'Catégorie' },
  { key: 'temps_estime_minutes', label: 'Temps estimé' },
  { key: 'prix_base_ht', label: 'Prix HT' },
  { key: 'prix_base_ttc', label: 'Prix TTC' },
]

const categories = computed(() => {
  const set = new Set(prestations.value.map(p => p.categorie).filter(Boolean))
  return Array.from(set).sort()
})

const filteredPrestations = computed(() => {
  let list = prestations.value
  if (selectedCat.value) {
    list = list.filter(p => p.categorie === selectedCat.value)
  }
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    list = list.filter(p =>
      p.nom?.toLowerCase().includes(q) ||
      p.categorie?.toLowerCase().includes(q) ||
      p.description?.toLowerCase().includes(q)
    )
  }
  return list
})

function formatAmount(v: number | null) {
  if (v == null) return '–'
  return formatCurrency(v)
}

function resetFilters() {
  selectedCat.value = ''
  search.value = ''
}

async function loadPrestations() {
  loading.value = true
  errorMessage.value = ''
  try {
    const data = await api.get('/prestations')
    prestations.value = Array.isArray(data) ? data : (data?.['hydra:member'] ?? [])
  } catch (e: unknown) {
    errorMessage.value = e instanceof Error ? e.message : 'Impossible de charger les tarifs'
    toast.add({ title: 'Erreur', description: errorMessage.value, color: 'error' })
  } finally {
    loading.value = false
  }
}

onMounted(loadPrestations)
</script>

<style scoped>
.category-pill {
  padding: 6px 14px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 150ms ease;
  font-family: inherit;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.06);
  color: #6B7280;
}
.category-pill:hover {
  background: rgba(255, 255, 255, 0.06);
}
.category-pill--active {
  background: rgba(255, 210, 0, 0.1);
  border-color: rgba(255, 210, 0, 0.3);
  color: #FFD200;
}
</style>
