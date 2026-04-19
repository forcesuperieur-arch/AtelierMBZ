<template>
  <div>
    <div class="page-header" style="justify-content:space-between;">
      <div>
        <div class="page-title">Catalogue Motos</div>
        <div class="page-sub">Référentiel des modèles pour accélérer la saisie véhicule.</div>
      </div>
    </div>

    <UCard style="margin-bottom:16px;">
      <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
        <UFormField label="Catégorie">
          <USelect v-model="selectedCat" :options="catOptions" placeholder="Toutes" />
        </UFormField>
        <UFormField label="Recherche">
          <UInput v-model="search" placeholder="Marque, modèle..." />
        </UFormField>
        <div style="margin-left:auto;font-size:12px;color:#9CA3AF;">
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
          <button style="color:#FFD200;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="openDetails(row.original)">Détails</button>
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
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
              <div>
                <div style="font-size:16px;font-weight:800;color:#E8E9ED;">{{ selectedModel.marque }} {{ selectedModel.modele }}</div>
                <div style="font-size:12px;color:#9CA3AF;">{{ selectedModel.categorie_nom || 'Catégorie non renseignée' }}</div>
              </div>
              <button class="btn btn-ghost" @click="selectedModel = null">Fermer</button>
            </div>
          </template>

          <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">
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

const detailOpen = computed({
  get: () => Boolean(selectedModel.value),
  set: (value: boolean) => {
    if (!value) selectedModel.value = null
  },
})

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

function openDetails(model: any) {
  selectedModel.value = model
}

function resetFilters() {
  selectedCat.value = ''
  search.value = ''
}

async function loadCatalog() {
  loading.value = true
  errorMessage.value = ''

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
  } catch (e: any) {
    errorMessage.value = e?.message || 'Le catalogue moto n’a pas pu être chargé.'
    toast.add({ title: 'Catalogue indisponible', description: errorMessage.value, color: 'error' })
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
  gap: 4px;
  padding: 12px;
  border-radius: 12px;
  background: rgba(255,255,255,0.02);
  border: 1px solid rgba(255,255,255,0.06);
}

.detail-card span {
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #9CA3AF;
}

.detail-card strong {
  color: #E8E9ED;
  font-size: 14px;
}
</style>
