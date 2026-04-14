<template>
  <div>
    <h1 class="text-2xl font-bold mb-6">Tableau de bord</h1>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
      <StatsCard
        title="RDV aujourd'hui"
        :value="stats.rdvs_today ?? 0"
        icon="i-heroicons-calendar-days"
        color="primary"
      />
      <StatsCard
        title="RDV cette semaine"
        :value="stats.rdvs_week ?? 0"
        icon="i-heroicons-calendar"
        color="blue"
      />
      <StatsCard
        title="CA du mois"
        :value="stats.ca_month ?? 0"
        icon="i-heroicons-banknotes"
        color="success"
        currency
      />
      <StatsCard
        title="Factures impayées"
        :value="stats.impayees_count ?? 0"
        icon="i-heroicons-exclamation-triangle"
        color="warning"
      />
    </div>

    <!-- Today's RDV list -->
    <UCard>
      <template #header>
        <div class="flex items-center justify-between">
          <h2 class="font-semibold">Rendez-vous du jour</h2>
          <UButton label="Nouveau RDV" icon="i-heroicons-plus" size="sm" to="/rdv/new" />
        </div>
      </template>

      <div v-if="loading" class="flex justify-center py-8">
        <UIcon name="i-heroicons-arrow-path" class="animate-spin text-2xl text-gray-400" />
      </div>

      <UTable
        v-else
        :data="todayRdvs"
        :columns="columns"
      >
        <template #status-cell="{ row }">
          <StatusBadge :status="row.original.status" />
        </template>
        <template #actions-cell="{ row }">
          <UButton size="xs" variant="ghost" icon="i-heroicons-eye" :to="`/rdv/${row.original.id}`" />
        </template>
      </UTable>
    </UCard>

    <!-- Stock alerts -->
    <UCard v-if="stockAlertes.length" class="mt-6">
      <template #header>
        <h2 class="font-semibold text-warning">
          <UIcon name="i-heroicons-exclamation-triangle" class="mr-1" />
          Alertes stock ({{ stockAlertes.length }})
        </h2>
      </template>
      <ul class="space-y-2">
        <li v-for="p in stockAlertes" :key="p.id" class="flex items-center justify-between text-sm">
          <span>{{ p.designation }} ({{ p.reference }})</span>
          <UBadge color="error" variant="subtle">Stock: {{ p.quantite_stock }}</UBadge>
        </li>
      </ul>
    </UCard>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const loading = ref(true)
const stats = ref<any>({})
const todayRdvs = ref<any[]>([])
const stockAlertes = ref<any[]>([])

const columns = [
  { key: 'heure_debut', label: 'Heure' },
  { key: 'client_nom', label: 'Client' },
  { key: 'vehicule_info', label: 'Véhicule' },
  { key: 'type_intervention', label: 'Type' },
  { key: 'status', label: 'Statut' },
  { key: 'mecanicien_nom', label: 'Mécanicien' },
  { key: 'actions', label: '' },
]

function normalizeRdv(r: any) {
  const c = r.client
  const v = r.vehicule
  return {
    ...r,
    status: r.statut ?? r.status,
    client_nom: c ? `${c.prenom} ${c.nom}` : (r.client_nom ?? ''),
    vehicule_info: v ? `${v.marque} ${v.modele}` : (r.vehicule_info ?? ''),
    heure_debut: r.heure_rdv ?? r.heure_debut,
    mecanicien_nom: r.mecanicien ? `${r.mecanicien.prenom} ${r.mecanicien.nom}` : (r.mecanicien_nom ?? ''),
  }
}

onMounted(async () => {
  try {
    const today = new Date().toISOString().slice(0, 10)
    const [s, rdvData, alertes] = await Promise.all([
      api.get('/statistiques/dashboard'),
      api.get(`/rendez-vous?dateRdv[after]=${today}&dateRdv[before]=${today}&itemsPerPage=200`),
      api.get('/stock/alertes').catch(() => []),
    ])
    stats.value = s
    const rawRdvs = rdvData?.['hydra:member'] ?? rdvData?.member ?? (Array.isArray(rdvData) ? rdvData : [])
    todayRdvs.value = rawRdvs.map(normalizeRdv)
    stockAlertes.value = alertes
  } finally {
    loading.value = false
  }
})
</script>
