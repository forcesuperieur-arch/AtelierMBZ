<template>
  <div>
    <div class="page-header">
      <div class="page-title">Grille Tarifaire</div>
    </div>

    <div v-if="loading" class="loading-shimmer" style="height:300px;border-radius:14px;"></div>

    <template v-else>
      <!-- Category filter -->
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
        <UTable :data="filteredPrestations" :columns="columns" :loading="loading">
          <template #temps_estime_minutes-cell="{ row }">
            {{ row.original.temps_estime_minutes ?? '–' }} min
          </template>
          <template #prix_base_ht-cell="{ row }">
            {{ formatPrice(row.original.prix_base_ht) }}
          </template>
          <template #prix_base_ttc-cell="{ row }">
            <span style="font-weight:700;color:#FFD200;">{{ formatPrice(row.original.prix_base_ttc) }}</span>
          </template>
        </UTable>
      </UCard>
    </template>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const loading = ref(true)
const prestations = ref<any[]>([])
const selectedCat = ref('')

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
  if (!selectedCat.value) return prestations.value
  return prestations.value.filter(p => p.categorie === selectedCat.value)
})

function formatPrice(v: number | string) {
  const amount = Number(v || 0)
  if (!amount) return '–'
  return amount.toFixed(2).replace('.', ',') + ' €'
}

onMounted(async () => {
  try {
    const data = await api.get('/prestations')
    const raw = data?.['hydra:member'] ?? data?.member ?? (Array.isArray(data) ? data : [])
    prestations.value = raw.map((p: any) => ({
      ...p,
      temps_estime_minutes: Number(p.temps_estime_minutes ?? 0),
      prix_base_ht: Number(p.prix_base_ht ?? 0),
      prix_base_ttc: Number(p.prix_base_ttc ?? 0),
    }))
  } finally {
    loading.value = false
  }
})
</script>
