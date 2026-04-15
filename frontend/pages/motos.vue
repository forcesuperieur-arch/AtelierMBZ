<template>
  <div>
    <div class="page-header">
      <div class="page-title">Catalogue Motos</div>
    </div>

    <UCard style="margin-bottom:16px;">
      <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
        <UFormField label="Catégorie">
          <USelect v-model="selectedCat" :options="catOptions" placeholder="Toutes" />
        </UFormField>
        <UFormField label="Recherche">
          <UInput v-model="search" placeholder="Marque, modèle..." />
        </UFormField>
      </div>
    </UCard>

    <UCard>
      <UTable :data="filtered" :columns="columns" :loading="loading">
        <template #actions-cell="{ row }">
          <button style="color:#FFD200;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="selectedModel = row.original">Détails</button>
        </template>
      </UTable>
    </UCard>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const loading = ref(true)
const models = ref<any[]>([])
const categories = ref<any[]>([])
const selectedCat = ref('')
const search = ref('')
const selectedModel = ref<any>(null)

const catOptions = computed(() => [
  { value: '', label: 'Toutes' },
  ...categories.value.map(c => ({ value: String(c.id), label: c.nom })),
])

const filtered = computed(() => {
  let list = models.value
  if (selectedCat.value) list = list.filter(m => String(m.categorie_id) === selectedCat.value)
  if (search.value) {
    const s = search.value.toLowerCase()
    list = list.filter(m => m.marque?.toLowerCase().includes(s) || m.modele?.toLowerCase().includes(s))
  }
  return list
})

const columns = [
  { key: 'marque', label: 'Marque' },
  { key: 'modele', label: 'Modèle' },
  { key: 'categorie_nom', label: 'Catégorie' },
  { key: 'cylindree_min', label: 'Cylindrée' },
  { key: 'actions', label: '' },
]

onMounted(async () => {
  try {
    const [mData, cData] = await Promise.all([
      api.get('/motos/modeles'),
      api.get('/motos/categories'),
    ])
    const rawCats = cData?.['hydra:member'] ?? cData?.member ?? (Array.isArray(cData) ? cData : [])
    categories.value = rawCats
    const rawModels = mData?.['hydra:member'] ?? mData?.member ?? (Array.isArray(mData) ? mData : [])
    models.value = rawModels.map((m: any) => ({
      ...m,
      categorie_id: m.categorie?.id ?? m.categorie_id,
      categorie_nom: m.categorie?.nom ?? m.categorie_nom ?? '',
    }))
  } finally {
    loading.value = false
  }
})
</script>
