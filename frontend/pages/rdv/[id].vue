<template>
  <div>
    <div class="flex items-center gap-3 mb-6">
      <UButton icon="i-heroicons-arrow-left" variant="ghost" to="/rdv" />
      <h1 class="text-2xl font-bold">RDV #{{ rdv?.id }}</h1>
      <StatusBadge v-if="rdv" :status="rdv.statut" />
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <UIcon name="i-heroicons-arrow-path" class="animate-spin text-3xl text-gray-400" />
    </div>

    <div v-else-if="rdv" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Main info -->
      <div class="lg:col-span-2 space-y-6">
        <UCard>
          <template #header>
            <h2 class="font-semibold">Informations</h2>
          </template>
          <div class="grid grid-cols-2 gap-4 text-sm">
            <div><span class="text-gray-500">Date :</span> {{ formatDate(rdv.date_rdv) }}</div>
            <div><span class="text-gray-500">Heure :</span> {{ formatTime(rdv.heure_rdv) }}</div>
            <div><span class="text-gray-500">Type :</span> {{ rdv.type_intervention }}</div>
            <div><span class="text-gray-500">Pont :</span> {{ rdv.pont?.nom }}</div>
            <div><span class="text-gray-500">Mécanicien :</span> {{ rdv.mecanicien ? (rdv.mecanicien.prenom + ' ' + rdv.mecanicien.nom) : '—' }}</div>
            <div><span class="text-gray-500">Durée prévue :</span> {{ rdv.temps_estime ?? '—' }} min</div>
          </div>
          <div v-if="rdv.commentaire" class="mt-4">
            <span class="text-gray-500 text-sm">Description :</span>
            <p class="mt-1">{{ rdv.commentaire }}</p>
          </div>
        </UCard>

        <!-- Client & Vehicle -->
        <UCard>
          <template #header><h2 class="font-semibold">Client & Véhicule</h2></template>
          <div class="grid grid-cols-2 gap-4 text-sm">
            <div v-if="rdv.client">
              <p class="font-medium">{{ rdv.client.prenom }} {{ rdv.client.nom }}</p>
              <p class="text-gray-500">{{ rdv.client.telephone }}</p>
              <p v-if="rdv.client.email" class="text-gray-500">{{ rdv.client.email }}</p>
            </div>
            <div v-if="rdv.vehicule">
              <p class="font-medium">{{ rdv.vehicule.marque }} {{ rdv.vehicule.modele }}</p>
              <p class="text-gray-500">Plaque: {{ rdv.vehicule.plaque }}</p>
            </div>
          </div>
        </UCard>

        <!-- Notes / Travaux -->
        <UCard>
          <template #header><h2 class="font-semibold">Notes internes</h2></template>
          <UTextarea v-model="notes" placeholder="Notes..." rows="3" />
          <div class="mt-2 flex justify-end">
            <UButton label="Sauvegarder" size="sm" @click="saveNotes" :loading="saving" />
          </div>
        </UCard>
      </div>

      <!-- Sidebar actions -->
      <div class="space-y-6">
        <UCard>
          <template #header><h2 class="font-semibold">Actions</h2></template>
          <div class="space-y-2">
            <UButton
              v-for="t in availableTransitions"
              :key="t.name"
              :label="t.label"
              :color="t.color"
              :icon="t.icon"
              block
              @click="applyTransition(t.name)"
              :loading="transitioning === t.name"
            />
            <UButton
              v-if="canInvoice"
              label="Créer Facture"
              icon="i-heroicons-banknotes"
              color="primary"
              variant="outline"
              block
              @click="createInvoice"
            />
          </div>
        </UCard>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const route = useRoute()
const rdvStore = useRdvStore()
const billingStore = useBillingStore()
const api = useApi()
const toast = useToast()
const { formatDate, formatTime } = useFormat()

const id = Number(route.params.id)
const loading = ref(true)
const saving = ref(false)
const transitioning = ref('')
const notes = ref('')

const rdv = computed(() => rdvStore.currentRdv)

const transitionConfig = [
  { name: 'reserver', label: 'Réserver', color: 'blue' as const, icon: 'i-heroicons-bookmark', from: ['en_attente'] },
  { name: 'confirmer', label: 'Confirmer', color: 'info' as const, icon: 'i-heroicons-check', from: ['en_attente', 'reserve'] },
  { name: 'reception', label: 'Réceptionner', color: 'warning' as const, icon: 'i-heroicons-truck', from: ['confirme'] },
  { name: 'start_travail', label: 'Démarrer travaux', color: 'orange' as const, icon: 'i-heroicons-play', from: ['reception'] },
  { name: 'terminer', label: 'Terminer', color: 'success' as const, icon: 'i-heroicons-check-circle', from: ['en_cours'] },
  { name: 'restituer', label: 'Restituer', color: 'primary' as const, icon: 'i-heroicons-hand-raised', from: ['termine'] },
  { name: 'annuler', label: 'Annuler', color: 'error' as const, icon: 'i-heroicons-x-circle', from: ['en_attente', 'reserve', 'confirme'] },
]

const availableTransitions = computed(() => {
  if (!rdv.value) return []
  return transitionConfig.filter(t => t.from.includes(rdv.value!.statut))
})

const canInvoice = computed(() => rdv.value?.statut === 'restitue')

async function applyTransition(name: string) {
  transitioning.value = name
  try {
    await rdvStore.transitionRdv(id, name)
    toast.add({ title: 'Transition effectuée', color: 'success' })
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  } finally {
    transitioning.value = ''
  }
}

async function saveNotes() {
  saving.value = true
  try {
    await rdvStore.updateRdv(id, { commentaire: notes.value })
    toast.add({ title: 'Notes sauvegardées', color: 'success' })
  } finally {
    saving.value = false
  }
}

async function createInvoice() {
  try {
    await billingStore.createFacture(id)
    toast.add({ title: 'Facture créée', color: 'success' })
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  }
}

onMounted(async () => {
  try {
    await rdvStore.fetchRdv(id)
    notes.value = rdv.value?.commentaire || ''
  } finally {
    loading.value = false
  }
})
</script>
