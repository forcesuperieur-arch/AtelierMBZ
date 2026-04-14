<template>
  <div class="max-w-lg w-full">
    <UCard>
      <template #header>
        <div class="text-center">
          <UIcon name="i-heroicons-calendar-days" class="text-4xl text-primary mb-2" />
          <h1 class="text-xl font-bold">Réserver un rendez-vous</h1>
          <p class="text-sm text-gray-500">Choisissez un créneau disponible</p>
        </div>
      </template>

      <form @submit.prevent="submitBooking" class="space-y-4">
        <div class="grid grid-cols-2 gap-3">
          <UFormField label="Prénom"><UInput v-model="form.client_prenom" required /></UFormField>
          <UFormField label="Nom"><UInput v-model="form.client_nom" required /></UFormField>
          <UFormField label="Téléphone"><UInput v-model="form.client_telephone" required /></UFormField>
          <UFormField label="Email"><UInput v-model="form.client_email" type="email" /></UFormField>
        </div>

        <UFormField label="Marque moto"><UInput v-model="form.vehicule_marque" required /></UFormField>
        <div class="grid grid-cols-2 gap-3">
          <UFormField label="Modèle"><UInput v-model="form.vehicule_modele" required /></UFormField>
          <UFormField label="Plaque"><UInput v-model="form.vehicule_plaque" required /></UFormField>
        </div>

        <UFormField label="Type d'intervention">
          <USelect v-model="form.type_intervention" :options="typeOptions" required />
        </UFormField>

        <UFormField label="Date souhaitée">
          <UInput v-model="form.date_rdv" type="date" :min="minDate" required @change="fetchSlots" />
        </UFormField>

        <div v-if="slots.length" class="grid grid-cols-3 gap-2">
          <UButton
            v-for="slot in slots"
            :key="slot"
            :label="slot"
            :variant="form.heure_debut === slot ? 'solid' : 'outline'"
            size="sm"
            @click="form.heure_debut = slot"
          />
        </div>
        <p v-else-if="form.date_rdv && !loadingSlots" class="text-sm text-gray-400">Aucun créneau disponible</p>

        <UFormField label="Description du problème">
          <UTextarea v-model="form.description_probleme" rows="3" />
        </UFormField>

        <UButton type="submit" block :loading="submitting" :disabled="!form.heure_debut" label="Confirmer le rendez-vous" />
      </form>

      <div v-if="confirmation" class="mt-4 p-4 bg-green-50 dark:bg-green-950 rounded-lg text-center">
        <UIcon name="i-heroicons-check-circle" class="text-3xl text-green-500 mb-2" />
        <p class="font-medium text-green-700 dark:text-green-300">Rendez-vous confirmé !</p>
        <p class="text-sm text-green-600 dark:text-green-400 mt-1">
          Votre code de suivi : <strong>{{ confirmation.token_suivi }}</strong>
        </p>
        <UButton label="Suivre mon RDV" class="mt-3" :to="`/public/suivi?token=${confirmation.token_suivi}`" />
      </div>
    </UCard>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'public' })

const config = useRuntimeConfig()
const baseURL = config.public.apiBase as string

const submitting = ref(false)
const loadingSlots = ref(false)
const slots = ref<string[]>([])
const confirmation = ref<any>(null)

const minDate = new Date().toISOString().slice(0, 10)

const form = reactive({
  client_prenom: '', client_nom: '', client_telephone: '', client_email: '',
  vehicule_marque: '', vehicule_modele: '', vehicule_plaque: '',
  type_intervention: 'entretien', date_rdv: '', heure_debut: '',
  description_probleme: '',
})

const typeOptions = [
  { value: 'entretien', label: 'Entretien' },
  { value: 'reparation', label: 'Réparation' },
  { value: 'diagnostic', label: 'Diagnostic' },
  { value: 'revision', label: 'Révision' },
  { value: 'pneus', label: 'Pneus' },
]

async function fetchSlots() {
  if (!form.date_rdv) return
  loadingSlots.value = true
  form.heure_debut = ''
  try {
    const res = await fetch(`${baseURL}/public/slots?date=${form.date_rdv}&atelier_id=1`)
    slots.value = await res.json()
  } finally {
    loadingSlots.value = false
  }
}

async function submitBooking() {
  submitting.value = true
  try {
    const res = await fetch(`${baseURL}/public/booking`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ...form, atelier_id: 1 }),
    })
    if (!res.ok) throw new Error('Erreur de réservation')
    confirmation.value = await res.json()
  } catch (e) {
    alert('Erreur lors de la réservation. Veuillez réessayer.')
  } finally {
    submitting.value = false
  }
}
</script>
