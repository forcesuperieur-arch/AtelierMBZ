<template>
  <div>
    <div class="flex items-center gap-3 mb-6">
      <UButton icon="i-heroicons-arrow-left" variant="ghost" to="/clients" />
      <h1 class="text-2xl font-bold">{{ client?.prenom }} {{ client?.nom }}</h1>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <UIcon name="i-heroicons-arrow-path" class="animate-spin text-3xl text-gray-400" />
    </div>

    <div v-else-if="client" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 space-y-6">
        <!-- Info -->
        <UCard>
          <template #header><h2 class="font-semibold">Coordonnées</h2></template>
          <div class="grid grid-cols-2 gap-3 text-sm">
            <div><span class="text-gray-500">Téléphone :</span> {{ client.telephone }}</div>
            <div><span class="text-gray-500">Email :</span> {{ client.email || '—' }}</div>
            <div class="col-span-2"><span class="text-gray-500">Adresse :</span> {{ client.adresse || '—' }}</div>
          </div>
        </UCard>

        <!-- Vehicles -->
        <UCard>
          <template #header>
            <div class="flex items-center justify-between">
              <h2 class="font-semibold">Véhicules</h2>
            </div>
          </template>
          <div v-if="client.vehicules?.length" class="space-y-3">
            <div v-for="v in client.vehicules" :key="v.id" class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
              <div>
                <p class="font-medium">{{ v.marque }} {{ v.modele }}</p>
                <p class="text-sm text-gray-500">{{ v.plaque }} — {{ v.annee }}</p>
              </div>
            </div>
          </div>
          <p v-else class="text-gray-400">Aucun véhicule enregistré</p>
        </UCard>

        <!-- RDV History -->
        <UCard>
          <template #header><h2 class="font-semibold">Historique RDV</h2></template>
          <UTable :data="clientRdvs" :columns="rdvColumns">
            <template #status-cell="{ row }">
              <StatusBadge :status="row.original.status" />
            </template>
            <template #actions-cell="{ row }">
              <UButton size="xs" variant="ghost" icon="i-heroicons-eye" :to="`/rdv/${row.original.id}`" />
            </template>
          </UTable>
        </UCard>
      </div>

      <!-- Sidebar stats -->
      <div class="space-y-6">
        <UCard>
          <template #header><h2 class="font-semibold">Statistiques</h2></template>
          <div class="space-y-3 text-sm">
            <div class="flex justify-between"><span class="text-gray-500">Total RDV</span><span class="font-medium">{{ client.rendez_vous?.length || 0 }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Véhicules</span><span class="font-medium">{{ client.vehicules?.length || 0 }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Depuis</span><span class="font-medium">{{ client.created_at?.slice(0, 10) }}</span></div>
          </div>
        </UCard>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const route = useRoute()
const api = useApi()
const loading = ref(true)
const client = ref<any>(null)
const clientRdvs = ref<any[]>([])

const rdvColumns = [
  { key: 'date_rdv', label: 'Date' },
  { key: 'type_intervention', label: 'Type' },
  { key: 'vehicule_info', label: 'Véhicule' },
  { key: 'status', label: 'Statut' },
  { key: 'actions', label: '' },
]

function normalizeRdv(r: any) {
  const v = r.vehicule
  return {
    ...r,
    status: r.statut ?? r.status,
    vehicule_info: v ? `${v.marque} ${v.modele}` : r.vehicule_info ?? '',
  }
}

onMounted(async () => {
  try {
    const [c, rdvData] = await Promise.all([
      api.get(`/clients/${route.params.id}`),
      api.get(`/rendez-vous?client.id=${route.params.id}&order[dateRdv]=desc`),
    ])
    client.value = c
    const raw = rdvData?.['hydra:member'] ?? rdvData?.member ?? (Array.isArray(rdvData) ? rdvData : [])
    clientRdvs.value = raw.map(normalizeRdv)
  } finally {
    loading.value = false
  }
})
</script>
