<template>
  <div>
    <div class="page-header" style="justify-content:space-between;">
      <div>
        <div class="page-title">Grille Tarifaire</div>
        <div class="page-sub">Vue rapide des prestations atelier et de leurs prix publics.</div>
      </div>
    </div>

    <UCard style="margin-bottom:16px;">
      <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
        <UFormField label="Recherche" style="min-width:260px;">
          <UInput v-model="search" placeholder="Prestation, catégorie..." />
        </UFormField>
        <div style="margin-left:auto;font-size:12px;color:#9CA3AF;">
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
      <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
        <button
          v-for="cat in categories"
          :key="cat"
          style="padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600;cursor:pointer;transition:all 0.15s;font-family:inherit;"
          :style="{
            background: selectedCat === cat ? 'rgba(255,210,0,0.1)' : 'rgba(255,255,255,0.03)',
            border: selectedCat === cat ? '1px solid rgba(255,210,0,0.3)' : '1px solid rgba(255,255,255,0.06)',
            color: selectedCat === cat ? '#FFD200' : '#6B7280',
          }"
          @click="selectedCat = selectedCat === cat ? '' : cat"
        >{{ cat }}</button>
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
            <span style="font-weight:700;color:#FFD200;">{{ formatAmount(row.original.prix_base_ttc) }}</span>
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
  const cats = new Set(prestations.value.map(p => p.categorie).filter(Boolean))
  return Array.from(cats).sort()
})

const filteredPrestations = computed(() => {
  const query = search.value.trim().toLowerCase()
  return prestations.value.filter((p: any) => {
    const matchesCat = !selectedCat.value || p.categorie === selectedCat.value
    if (!matchesCat) return false
    if (!query) return true
    return [p.nom, p.categorie].filter(Boolean).join(' ').toLowerCase().includes(query)
  })
})

function formatAmount(v: number | string) {
  const amount = Number(v)
  return Number.isFinite(amount) && amount > 0 ? formatCurrency(amount) : '—'
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
    const raw = data?.['hydra:member'] ?? data?.member ?? (Array.isArray(data) ? data : [])
    prestations.value = raw.map((p: any) => ({
      ...p,
      temps_estime_minutes: Number(p.temps_estime_minutes ?? 0),
      prix_base_ht: Number(p.prix_base_ht ?? 0),
      prix_base_ttc: Number(p.prix_base_ttc ?? 0),
    }))
  } catch (e: any) {
    errorMessage.value = e?.message || 'La grille tarifaire n’a pas pu être chargée.'
    toast.add({ title: 'Erreur tarifs', description: errorMessage.value, color: 'error' })
  } finally {
    loading.value = false
  }
}

onMounted(loadPrestations)
</script>
