<template>
  <div>
    <h1 class="text-2xl font-bold mb-6">Atelier — Vue ponts</h1>

    <div v-if="loading" class="flex justify-center py-12">
      <UIcon name="i-heroicons-arrow-path" class="animate-spin text-3xl text-gray-400" />
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
      <UCard v-for="pont in ponts" :key="pont.id">
        <template #header>
          <div class="flex items-center justify-between">
            <h3 class="font-semibold">{{ pont.nom }}</h3>
            <UBadge :color="pont.current_rdv ? 'warning' : 'success'" variant="subtle">
              {{ pont.current_rdv ? 'Occupé' : 'Libre' }}
            </UBadge>
          </div>
        </template>

        <div v-if="pont.current_rdv" class="space-y-2 text-sm">
          <p class="font-medium">{{ pont.current_rdv.client_nom }}</p>
          <p class="text-gray-500">{{ pont.current_rdv.vehicule_info }}</p>
          <p class="text-gray-500">{{ pont.current_rdv.type_intervention }}</p>
          <StatusBadge :status="pont.current_rdv.status" />
          <div class="pt-2">
            <UButton size="xs" label="Voir RDV" :to="`/rdv/${pont.current_rdv.id}`" />
          </div>
        </div>
        <p v-else class="text-gray-400 text-sm">Aucune intervention en cours</p>

        <template #footer>
          <div class="text-xs text-gray-400">
            Prochains: {{ pont.next_count ?? 0 }} RDV aujourd'hui
          </div>
        </template>
      </UCard>
    </div>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const loading = ref(true)
const ponts = ref<any[]>([])

onMounted(async () => {
  try {
    ponts.value = await api.get('/ponts/status')
  } finally {
    loading.value = false
  }
})
</script>
