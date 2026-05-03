<template>
  <div>
    <AppPageHeader title="Catalogue Motos" subtitle="Référentiel des modèles pour accélérer la saisie véhicule." />

    <UCard class="mb-4">
      <div class="flex flex-wrap gap-3 items-end">
        <UFormField label="Catégorie">
          <USelect v-model="selectedCat" :options="catOptions" placeholder="Toutes" />
        </UFormField>
        <UFormField label="Recherche">
          <UInput v-model="search" placeholder="Marque, modèle..." />
        </UFormField>
        <div class="ml-auto text-xs text-gray-400">
          {{ filtered.length }} modèle(s) affiché(s)
        </div>
      </div>
    </UCard>

    <AppLoadingState
      v-if="loading"
      title="Chargement du catalogue"
      description="Les modèles moto sont en cours de récupération."
    />

    <AppErrorState
      v-else-if="errorMessage"
      title="Catalogue indisponible"
      :description="errorMessage"
      @retry="loadCatalog"
    />

    <UCard v-else>
      <UTable v-if="filtered.length" :data="filtered" :columns="columns" :loading="loading">
        <template #actions-cell="{ row }">
          <AppActionLink variant="primary" @click="openDetails(row.original)">Détails</AppActionLink>
        </template>
      </UTable>
      <AppEmptyState
        v-else
        title="Aucun modèle ne correspond aux filtres"
        description="Élargis la recherche ou réinitialise la catégorie pour revoir le catalogue complet."
        action-label="Réinitialiser"
        @action="resetFilters"
      />
    </UCard>

    <AppModal v-model:open="detailOpen" size="lg">
      <template #content>
        <UCard v-if="selectedModel">
          <template #header>
            <div class="flex items-center justify-between gap-2.5">
              <div>
                <div class="text-base font-extrabold text-text-primary">{{ selectedModel.marque }} {{ selectedModel.modele }}</div>
                <div class="text-xs text-gray-400">{{ selectedModel.categorie_nom || 'Catégorie non renseignée' }}</div>
              </div>
              <UButton variant="ghost" @click="selectedModel = null">Fermer</UButton>
            </div>
          </template>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="detail-card"><span>Marque</span><strong>{{ selectedModel.marque || '—' }}</strong></div>
            <div class="detail-card"><span>Modèle</span><strong>{{ selectedModel.modele || '—' }}</strong></div>
            <div class="detail-card"><span>Catégorie</span><strong>{{ selectedModel.categorie_nom || '—' }}</strong></div>
            <div class="detail-card"><span>Cylindrée min</span><strong>{{ selectedModel.cylindree_min || '—' }}</strong></div>
            <div class="detail-card"><span>Cylindrée max</span><strong>{{ selectedModel.cylindree_max || '—' }}</strong></div>
            <div class="detail-card"><span>Permis</span><strong>{{ selectedModel.permis ?? '—' }}</strong></div>
            <div class="detail-card"><span>Année début</span><strong>{{ selectedModel.annee_debut || '—' }}</strong></div>
            <div class="detail-card"><span>Année fin</span><strong>{{ selectedModel.annee_fin || '—' }}</strong></div>
          </div>
        </UCard>
      </template>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const toast = useToast()
const loading = ref(true)
const errorMessage = ref('')
const models = ref<any[]>([])
const categories = ref<any[]>([])
const selectedCat = ref('')
const search = ref('')
const selectedModel = ref<any>(null)
const detailOpen = computed({ get: () => !!selectedModel.value, set: (v) => { if (!v) selectedModel.value = null } })

const columns = [
  { key: 'marque', label: 'Marque' },
  { key: 'modele', label: 'Modèle' },
  { key: 'categorie_nom', label: 'Catégorie' },
  { key: 'cylindree_min', label: 'Cylindrée' },
  { key: 'annee_debut', label: 'Année' },
  { key: 'actions', label: '' },
]

const catOptions = computed(() => [
  { label: 'Toutes', value: '' },
  ...categories.value.map(c => ({ label: c.nom, value: c.nom })),
])

const filtered = computed(() => {
  let list = models.value
  if (selectedCat.value) {
    list = list.filter(m => m.categorie_nom === selectedCat.value)
  }
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    list = list.filter(m =>
      m.marque?.toLowerCase().includes(q) ||
      m.modele?.toLowerCase().includes(q)
    )
  }
  return list
})

function resetFilters() {
  selectedCat.value = ''
  search.value = ''
}

function openDetails(m: any) {
  selectedModel.value = m
}

async function loadCatalog() {
  loading.value = true
  errorMessage.value = ''
  try {
    const [mData, cData] = await Promise.all([
      api.get('/motos'),
      api.get('/moto-categories'),
    ])
    models.value = Array.isArray(mData) ? mData : (mData?.['hydra:member'] ?? [])
    categories.value = Array.isArray(cData) ? cData : (cData?.['hydra:member'] ?? [])
  } catch (e: unknown) {
    errorMessage.value = e instanceof Error ? e.message : 'Impossible de charger le catalogue'
    toast.add({ title: 'Erreur', description: errorMessage.value, color: 'error' })
  } finally {
    loading.value = false
  }
}

onMounted(loadCatalog)
</script>

<style scoped>
.detail-card {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 10px 12px;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.03);
}
.detail-card span {
  font-size: 11px;
  color: #6B7280;
}
.detail-card strong {
  font-size: 14px;
  color: #E8E9ED;
}
</style>
