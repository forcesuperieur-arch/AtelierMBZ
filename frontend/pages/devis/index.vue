<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Devis</h1>
      <UButton label="Nouveau devis" icon="i-heroicons-plus" @click="showNew = true" />
    </div>

    <UCard>
      <UTable :data="devisList" :columns="columns" :loading="loading">
        <template #statut-cell="{ row }">
          <UBadge
            :color="row.original.statut === 'accepte' ? 'success' : row.original.statut === 'refuse' ? 'error' : 'gray'"
            variant="subtle"
          >
            {{ row.original.statut }}
          </UBadge>
        </template>
        <template #total_ttc-cell="{ row }">
          {{ formatCurrency(row.original.total_ttc) }}
        </template>
        <template #actions-cell="{ row }">
          <UButton size="xs" variant="ghost" icon="i-heroicons-eye" :to="`/devis/${row.original.id}`" />
        </template>
      </UTable>
    </UCard>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const loading = ref(true)
const devisList = ref<any[]>([])
const showNew = ref(false)

const columns = [
  { key: 'numero_devis', label: 'N°' },
  { key: 'date_creation', label: 'Date' },
  { key: 'client_nom', label: 'Client' },
  { key: 'total_ttc', label: 'Total TTC' },
  { key: 'statut', label: 'Statut' },
  { key: 'actions', label: '' },
]

function formatCurrency(v: number) {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(v || 0)
}

onMounted(async () => {
  try {
    const data = await api.get('/devis')
    const raw = data?.['hydra:member'] ?? data?.member ?? (Array.isArray(data) ? data : [])
    devisList.value = raw.map((d: any) => {
      const c = d.client
      return {
        ...d,
        client_nom: c ? `${c.prenom} ${c.nom}` : d.client_nom ?? '',
      }
    })
  } finally {
    loading.value = false
  }
})
</script>
