<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Rendez-vous</h1>
      <UButton label="Nouveau RDV" icon="i-heroicons-plus" to="/rdv/new" />
    </div>

    <!-- Filters -->
    <UCard class="mb-4">
      <div class="flex flex-wrap gap-3 items-end">
        <UFormField label="Date">
          <UInput v-model="filters.date" type="date" />
        </UFormField>
        <UFormField label="Statut">
          <USelect v-model="filters.status" :options="statusOptions" placeholder="Tous" />
        </UFormField>
        <UFormField label="Recherche">
          <UInput v-model="filters.search" placeholder="Client, plaque..." icon="i-heroicons-magnifying-glass" />
        </UFormField>
        <UButton label="Filtrer" @click="fetchData" />
        <UButton label="Reset" variant="outline" @click="resetFilters" />
      </div>
    </UCard>

    <UCard>
      <div v-if="rdvStore.loading" class="flex justify-center py-8">
        <UIcon name="i-heroicons-arrow-path" class="animate-spin text-2xl text-gray-400" />
      </div>

      <UTable v-else :data="rdvStore.rdvs" :columns="columns">
        <template #status-cell="{ row }">
          <StatusBadge :status="row.original.status" />
        </template>
        <template #actions-cell="{ row }">
          <div class="flex gap-1">
            <UButton size="xs" variant="ghost" icon="i-heroicons-eye" :to="`/rdv/${row.original.id}`" />
          </div>
        </template>
      </UTable>
    </UCard>
  </div>
</template>

<script setup lang="ts">
const rdvStore = useRdvStore()

const filters = reactive({
  date: new Date().toISOString().slice(0, 10),
  status: '',
  search: '',
})

const statusOptions = [
  { value: '', label: 'Tous' },
  { value: 'en_attente', label: 'En attente' },
  { value: 'reserve', label: 'Réservé' },
  { value: 'confirme', label: 'Confirmé' },
  { value: 'reception', label: 'Réception' },
  { value: 'en_cours', label: 'En cours' },
  { value: 'termine', label: 'Terminé' },
  { value: 'restitue', label: 'Restitué' },
  { value: 'facture', label: 'Facturé' },
  { value: 'paye', label: 'Payé' },
  { value: 'annule', label: 'Annulé' },
]

const columns = [
  { key: 'date_rdv', label: 'Date' },
  { key: 'heure_debut', label: 'Heure' },
  { key: 'client_nom', label: 'Client' },
  { key: 'vehicule_info', label: 'Véhicule' },
  { key: 'type_intervention', label: 'Type' },
  { key: 'pont_nom', label: 'Pont' },
  { key: 'mecanicien_nom', label: 'Mécanicien' },
  { key: 'status', label: 'Statut' },
  { key: 'actions', label: '' },
]

function fetchData() {
  rdvStore.fetchRdvs(filters)
}

function resetFilters() {
  filters.date = new Date().toISOString().slice(0, 10)
  filters.status = ''
  filters.search = ''
  fetchData()
}

onMounted(fetchData)
</script>
