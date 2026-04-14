<template>
  <div>
    <h1 class="text-2xl font-bold mb-6">Espace Mécanicien</h1>

    <div v-if="loading" class="flex justify-center py-12">
      <UIcon name="i-heroicons-arrow-path" class="animate-spin text-3xl text-gray-400" />
    </div>

    <div v-else>
      <!-- Active intervention -->
      <UCard v-if="activeRdv" class="mb-6 border-orange-500">
        <template #header>
          <div class="flex items-center justify-between">
            <h2 class="font-semibold text-orange-600">
              <UIcon name="i-heroicons-wrench" /> Intervention en cours
            </h2>
            <UButton label="Terminer" color="success" icon="i-heroicons-check-circle" @click="finishWork" :loading="finishing" />
          </div>
        </template>
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div><span class="text-gray-500">Client :</span> {{ activeRdv.client_nom }}</div>
          <div><span class="text-gray-500">Véhicule :</span> {{ activeRdv.vehicule_info }}</div>
          <div><span class="text-gray-500">Type :</span> {{ activeRdv.type_intervention }}</div>
          <div><span class="text-gray-500">Pont :</span> {{ activeRdv.pont_nom }}</div>
        </div>
        <div v-if="activeRdv.description_probleme" class="mt-3 text-sm">
          <span class="text-gray-500">Description :</span>
          <p>{{ activeRdv.description_probleme }}</p>
        </div>
      </UCard>

      <!-- Assigned RDVs -->
      <UCard>
        <template #header><h2 class="font-semibold">Mes interventions du jour</h2></template>
        <div v-if="!myRdvs.length" class="py-4 text-center text-gray-400">
          Aucune intervention prévue
        </div>
        <div v-else class="space-y-3">
          <div
            v-for="rdv in myRdvs"
            :key="rdv.id"
            class="flex items-center justify-between p-3 rounded-lg border hover:bg-gray-50 dark:hover:bg-gray-800"
          >
            <div>
              <p class="font-medium">{{ rdv.heure_debut?.slice(0, 5) }} — {{ rdv.client_nom }}</p>
              <p class="text-sm text-gray-500">{{ rdv.vehicule_info }} — {{ rdv.type_intervention }}</p>
            </div>
            <div class="flex items-center gap-2">
              <StatusBadge :status="rdv.status" />
              <UButton
                v-if="rdv.status === 'reception'"
                size="xs"
                label="Démarrer"
                icon="i-heroicons-play"
                @click="startWork(rdv.id)"
              />
            </div>
          </div>
        </div>
      </UCard>
    </div>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const rdvStore = useRdvStore()
const toast = useToast()
const loading = ref(true)
const finishing = ref(false)
const myRdvs = ref<any[]>([])

const activeRdv = computed(() => myRdvs.value.find(r => r.status === 'en_cours'))

async function startWork(id: number) {
  try {
    await rdvStore.transitionRdv(id, 'start_travail')
    await fetchMyRdvs()
    toast.add({ title: 'Travaux démarrés', color: 'success' })
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  }
}

async function finishWork() {
  if (!activeRdv.value) return
  finishing.value = true
  try {
    await rdvStore.transitionRdv(activeRdv.value.id, 'terminer')
    await fetchMyRdvs()
    toast.add({ title: 'Intervention terminée', color: 'success' })
  } finally {
    finishing.value = false
  }
}

async function fetchMyRdvs() {
  const today = new Date().toISOString().slice(0, 10)
  myRdvs.value = await api.get(`/rendez-vous/mecanicien?date=${today}`)
}

onMounted(async () => {
  try {
    await fetchMyRdvs()
  } finally {
    loading.value = false
  }
})
</script>
