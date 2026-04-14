<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Ordres de réparation</h1>
    </div>

    <UCard>
      <UTable :data="ordres" :columns="columns" :loading="loading">
        <template #status-cell="{ row }">
          <UBadge
            :color="row.original.statut === 'cloture' ? 'success' : 'warning'"
            variant="subtle"
          >
            {{ row.original.statut }}
          </UBadge>
        </template>
        <template #actions-cell="{ row }">
          <UButton size="xs" variant="ghost" icon="i-heroicons-eye" :to="`/ordres/${row.original.id}`" />
        </template>
      </UTable>
    </UCard>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const loading = ref(true)
const ordres = ref<any[]>([])

const columns = [
  { key: 'numero_or', label: 'N° OR' },
  { key: 'created_at', label: 'Date' },
  { key: 'client_nom', label: 'Client' },
  { key: 'vehicule_info', label: 'Véhicule' },
  { key: 'status', label: 'Statut' },
  { key: 'actions', label: '' },
]

function normalizeOrdre(o: any) {
  const rdv = o.rendez_vous ?? {}
  const c = rdv.client
  const v = rdv.vehicule
  return {
    ...o,
    client_nom: c ? `${c.prenom} ${c.nom}` : '',
    vehicule_info: v ? `${v.marque} ${v.modele}` : '',
    status: rdv.statut ?? '',
  }
}

onMounted(async () => {
  try {
    const data = await api.get('/ordres-reparation')
    const raw = data?.['hydra:member'] ?? data?.member ?? (Array.isArray(data) ? data : [])
    ordres.value = raw.map(normalizeOrdre)
  } finally {
    loading.value = false
  }
})
</script>
