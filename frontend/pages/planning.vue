<template>
  <div>
    <h1 class="text-2xl font-bold mb-6">Planning</h1>

    <div v-if="loading" class="flex justify-center py-12">
      <UIcon name="i-heroicons-arrow-path" class="animate-spin text-3xl text-gray-400" />
    </div>

    <PlanningGrid
      v-else
      :ponts="ponts"
      :rdvs="normalizedRdvs"
      @select-rdv="onSelectRdv"
    />
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const loading = ref(true)
const ponts = ref<any[]>([])
const rawRdvs = ref<any[]>([])

const normalizedRdvs = computed(() => rawRdvs.value.map(r => ({
  ...r,
  status: r.statut ?? r.status,
  client_nom: r.client ? `${r.client.prenom ?? ''} ${r.client.nom ?? ''}`.trim() : (r.client_nom ?? ''),
  heure_debut: r.heure_rdv ?? r.heure_debut,
  pont_id: r.pont?.id ?? r.pont_id,
  date_rdv: r.date_rdv,
  type_intervention: r.type_intervention,
})))

function onSelectRdv(rdv: any) {
  navigateTo(`/rdv/${rdv.id}`)
}

onMounted(async () => {
  try {
    const [p, r] = await Promise.all([
      api.get('/ponts'),
      api.get('/rendez-vous?itemsPerPage=200'),
    ])
    const pontItems = Array.isArray(p) ? p : (p['hydra:member'] ?? p['member'] ?? [])
    const rdvItems = Array.isArray(r) ? r : (r['hydra:member'] ?? r['member'] ?? [])
    ponts.value = pontItems
    rawRdvs.value = rdvItems
  } finally {
    loading.value = false
  }
})
</script>
