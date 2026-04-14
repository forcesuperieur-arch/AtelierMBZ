<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Facturation</h1>
    </div>

    <UCard class="mb-4">
      <div class="flex flex-wrap gap-3 items-end">
        <UFormField label="Statut">
          <USelect v-model="filter" :options="statusOptions" />
        </UFormField>
        <UFormField label="Recherche">
          <UInput v-model="search" placeholder="N° facture, client..." icon="i-heroicons-magnifying-glass" />
        </UFormField>
      </div>
    </UCard>

    <UCard>
      <UTable :data="filtered" :columns="columns" :loading="loading">
        <template #statut-cell="{ row }">
          <UBadge
            :color="row.original.statut === 'payee' ? 'success' : row.original.statut === 'partielle' ? 'warning' : 'gray'"
            variant="subtle"
          >
            {{ row.original.statut }}
          </UBadge>
        </template>
        <template #total_ttc-cell="{ row }">
          {{ formatCurrency(row.original.total_ttc) }}
        </template>
        <template #actions-cell="{ row }">
          <div class="flex gap-1">
            <UButton size="xs" variant="ghost" icon="i-heroicons-arrow-down-tray" @click="billingStore.downloadPdf(row.original.id)" />
          </div>
        </template>
      </UTable>
    </UCard>
  </div>
</template>

<script setup lang="ts">
const billingStore = useBillingStore()
const loading = ref(true)
const filter = ref('')
const search = ref('')

const statusOptions = [
  { value: '', label: 'Toutes' },
  { value: 'brouillon', label: 'Brouillon' },
  { value: 'emise', label: 'Émise' },
  { value: 'partielle', label: 'Partielle' },
  { value: 'payee', label: 'Payée' },
  { value: 'annulee', label: 'Annulée' },
]

const columns = [
  { key: 'numero_facture', label: 'N°' },
  { key: 'date_creation', label: 'Date' },
  { key: 'client_nom', label: 'Client' },
  { key: 'total_ttc', label: 'Total TTC' },
  { key: 'statut', label: 'Statut' },
  { key: 'actions', label: '' },
]

const filtered = computed(() => {
  let list = billingStore.factures
  if (filter.value) list = list.filter(f => f.statut === filter.value)
  if (search.value) {
    const s = search.value.toLowerCase()
    list = list.filter(f =>
      f.numero_facture?.toLowerCase().includes(s) ||
      f.client_nom?.toLowerCase().includes(s)
    )
  }
  return list
})

function formatCurrency(v: number) {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(v || 0)
}

onMounted(async () => {
  try {
    await billingStore.fetchFactures()
  } finally {
    loading.value = false
  }
})
</script>
