<template>
  <div>
    <div class="flex items-center gap-3 mb-6">
      <UButton icon="i-heroicons-arrow-left" variant="ghost" to="/admin" />
      <h1 class="text-2xl font-bold">Configuration atelier</h1>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <UIcon name="i-heroicons-arrow-path" class="animate-spin text-3xl text-gray-400" />
    </div>

    <form v-else @submit.prevent="saveConfig" class="max-w-2xl space-y-6">
      <UCard>
        <template #header><h2 class="font-semibold">Informations générales</h2></template>
        <div class="grid grid-cols-2 gap-3">
          <UFormField label="Taux TVA MO (%)"><UInput v-model="config.tva_mo_taux" type="number" step="0.1" /></UFormField>
          <UFormField label="Taux TVA Pièces (%)"><UInput v-model="config.tva_pieces_taux" type="number" step="0.1" /></UFormField>
          <UFormField label="Durée créneau (min)"><UInput v-model="config.duree_creneau" type="number" /></UFormField>
          <UFormField label="Délai rappel (heures)"><UInput v-model="config.delai_rappel_heures" type="number" /></UFormField>
        </div>
      </UCard>

      <UCard>
        <template #header><h2 class="font-semibold">Horaires d'ouverture</h2></template>
        <div class="space-y-3">
          <div v-for="h in horaires" :key="h.jour_semaine" class="grid grid-cols-6 gap-2 items-center text-sm">
            <span class="font-medium">{{ jourLabel(h.jour_semaine) }}</span>
            <UInput v-model="h.heure_ouverture" type="time" :disabled="!h.is_ouvert" size="sm" />
            <UInput v-model="h.heure_fermeture" type="time" :disabled="!h.is_ouvert" size="sm" />
            <UInput v-model="h.pause_debut" type="time" :disabled="!h.is_ouvert" size="sm" placeholder="Pause début" />
            <UInput v-model="h.pause_fin" type="time" :disabled="!h.is_ouvert" size="sm" placeholder="Pause fin" />
            <label class="flex items-center gap-1 text-xs">
              <input type="checkbox" v-model="h.is_ouvert" />
              Ouvert
            </label>
          </div>
        </div>
      </UCard>

      <div class="flex justify-end">
        <UButton type="submit" label="Enregistrer" :loading="saving" />
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const toast = useToast()
const loading = ref(true)
const saving = ref(false)
const config = ref<any>({})
const horaires = ref<any[]>([])

const jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche']
function jourLabel(i: number) { return jours[i] || '' }

async function saveConfig() {
  saving.value = true
  try {
    await api.put('/config', { config: config.value, horaires: horaires.value })
    toast.add({ title: 'Configuration sauvegardée', color: 'success' })
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  try {
    const [c, h] = await Promise.all([
      api.get('/config'),
      api.get('/config/horaires'),
    ])
    config.value = c
    horaires.value = h
  } finally {
    loading.value = false
  }
})
</script>
