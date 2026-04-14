<template>
  <div class="max-w-lg w-full">
    <UCard>
      <template #header>
        <div class="text-center">
          <UIcon name="i-heroicons-magnifying-glass" class="text-4xl text-primary mb-2" />
          <h1 class="text-xl font-bold">Suivi de rendez-vous</h1>
        </div>
      </template>

      <div v-if="!rdv" class="space-y-4">
        <UFormField label="Code de suivi">
          <UInput v-model="token" placeholder="Entrez votre code de suivi..." icon="i-heroicons-ticket" />
        </UFormField>
        <UButton label="Rechercher" block @click="lookup" :loading="loading" />
        <p v-if="error" class="text-sm text-red-500 text-center">{{ error }}</p>
      </div>

      <div v-else class="space-y-4">
        <div class="text-center mb-4">
          <StatusBadge :status="rdv.statut" />
        </div>

        <div class="grid grid-cols-2 gap-3 text-sm">
          <div><span class="text-gray-500">Date :</span> {{ rdv.date_rdv }}</div>
          <div><span class="text-gray-500">Heure :</span> {{ rdv.heure_rdv }}</div>
          <div><span class="text-gray-500">Type :</span> {{ rdv.type_intervention }}</div>
          <div><span class="text-gray-500">Véhicule :</span> {{ rdv.vehicule ? `${rdv.vehicule.marque} ${rdv.vehicule.modele}` : '' }}</div>
        </div>

        <!-- Progress steps -->
        <div class="mt-6">
          <div class="flex items-center justify-between">
            <div
              v-for="(step, i) in progressSteps"
              :key="step.key"
              class="flex flex-col items-center flex-1"
            >
              <div
                :class="[
                  'w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold',
                  step.done ? 'bg-primary text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-400'
                ]"
              >
                {{ i + 1 }}
              </div>
              <span class="text-[10px] mt-1 text-center">{{ step.label }}</span>
            </div>
          </div>
        </div>

        <UButton label="Nouvelle recherche" variant="outline" block @click="rdv = null; token = ''" class="mt-4" />
      </div>
    </UCard>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'public' })

const config = useRuntimeConfig()
const baseURL = config.public.apiBase as string
const route = useRoute()

const token = ref((route.query.token as string) || '')
const loading = ref(false)
const error = ref('')
const rdv = ref<any>(null)

const statusOrder = ['en_attente', 'reserve', 'confirme', 'reception', 'en_cours', 'termine', 'restitue', 'facture', 'paye']

const progressSteps = computed(() => {
  if (!rdv.value) return []
  const currentIdx = statusOrder.indexOf(rdv.value.statut)
  return [
    { key: 'reserve', label: 'Réservé', done: currentIdx >= 1 },
    { key: 'confirme', label: 'Confirmé', done: currentIdx >= 2 },
    { key: 'reception', label: 'Réception', done: currentIdx >= 3 },
    { key: 'en_cours', label: 'En cours', done: currentIdx >= 4 },
    { key: 'termine', label: 'Terminé', done: currentIdx >= 5 },
    { key: 'restitue', label: 'Prêt', done: currentIdx >= 6 },
  ]
})

async function lookup() {
  if (!token.value) return
  loading.value = true
  error.value = ''
  try {
    const res = await fetch(`${baseURL}/public/suivi/${token.value}`)
    if (!res.ok) throw new Error()
    rdv.value = await res.json()
  } catch {
    error.value = 'Aucun rendez-vous trouvé avec ce code'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  if (token.value) lookup()
})
</script>
