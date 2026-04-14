<template>
  <div>
    <div class="flex items-center gap-3 mb-6">
      <UButton icon="i-heroicons-arrow-left" variant="ghost" to="/rdv" />
      <h1 class="text-2xl font-bold">Nouveau rendez-vous</h1>
    </div>

    <form @submit.prevent="submit" class="max-w-2xl space-y-6">
      <UCard>
        <template #header><h2 class="font-semibold">Client</h2></template>
        <div class="space-y-3">
          <UFormField label="Recherche client">
            <UInput v-model="clientSearch" placeholder="Nom, téléphone..." icon="i-heroicons-magnifying-glass" @input="searchClients" />
          </UFormField>
          <div v-if="clientResults.length" class="border rounded-lg divide-y max-h-40 overflow-y-auto">
            <div
              v-for="c in clientResults"
              :key="c.id"
              class="p-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer"
              @click="selectClient(c)"
            >
              {{ c.prenom }} {{ c.nom }} — {{ c.telephone }}
            </div>
          </div>
          <p v-if="selectedClient" class="text-sm text-primary">
            Client sélectionné: {{ selectedClient.prenom }} {{ selectedClient.nom }}
          </p>
          <div v-if="!selectedClient" class="grid grid-cols-2 gap-3">
            <UFormField label="Prénom"><UInput v-model="form.client_prenom" required /></UFormField>
            <UFormField label="Nom"><UInput v-model="form.client_nom" required /></UFormField>
            <UFormField label="Téléphone"><UInput v-model="form.client_telephone" required /></UFormField>
            <UFormField label="Email"><UInput v-model="form.client_email" type="email" /></UFormField>
          </div>
        </div>
      </UCard>

      <UCard>
        <template #header><h2 class="font-semibold">Véhicule</h2></template>
        <div class="grid grid-cols-2 gap-3">
          <UFormField label="Marque"><UInput v-model="form.vehicule_marque" required /></UFormField>
          <UFormField label="Modèle"><UInput v-model="form.vehicule_modele" required /></UFormField>
          <UFormField label="Plaque"><UInput v-model="form.vehicule_plaque" required /></UFormField>
          <UFormField label="Année"><UInput v-model="form.vehicule_annee" type="number" /></UFormField>
        </div>
      </UCard>

      <UCard>
        <template #header><h2 class="font-semibold">Rendez-vous</h2></template>
        <div class="grid grid-cols-2 gap-3">
          <UFormField label="Date">
            <UInput v-model="form.date_rdv" type="date" required />
          </UFormField>
          <UFormField label="Heure">
            <UInput v-model="form.heure_debut" type="time" required />
          </UFormField>
          <UFormField label="Type d'intervention">
            <USelect v-model="form.type_intervention" :options="typeOptions" required />
          </UFormField>
          <UFormField label="Durée estimée (min)">
            <UInput v-model="form.duree_estimee" type="number" required />
          </UFormField>
          <UFormField label="Pont">
            <USelect v-model="form.pont_id" :options="pontOptions" />
          </UFormField>
          <UFormField label="Mécanicien">
            <USelect v-model="form.mecanicien_id" :options="mecaOptions" />
          </UFormField>
        </div>
        <UFormField label="Description" class="mt-3">
          <UTextarea v-model="form.description_probleme" rows="3" placeholder="Décrivez le problème..." />
        </UFormField>
      </UCard>

      <div class="flex justify-end gap-3">
        <UButton label="Annuler" variant="outline" to="/rdv" />
        <UButton type="submit" label="Créer le RDV" :loading="submitting" />
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const rdvStore = useRdvStore()
const toast = useToast()

const submitting = ref(false)
const clientSearch = ref('')
const clientResults = ref<any[]>([])
const selectedClient = ref<any>(null)
const pontOptions = ref<any[]>([])
const mecaOptions = ref<any[]>([])

const form = reactive({
  client_id: null as number | null,
  client_prenom: '',
  client_nom: '',
  client_telephone: '',
  client_email: '',
  vehicule_marque: '',
  vehicule_modele: '',
  vehicule_plaque: '',
  vehicule_annee: '',
  date_rdv: new Date().toISOString().slice(0, 10),
  heure_debut: '09:00',
  type_intervention: 'entretien',
  duree_estimee: 60,
  pont_id: null as number | null,
  mecanicien_id: null as number | null,
  description_probleme: '',
})

const typeOptions = [
  { value: 'entretien', label: 'Entretien' },
  { value: 'reparation', label: 'Réparation' },
  { value: 'diagnostic', label: 'Diagnostic' },
  { value: 'revision', label: 'Révision' },
  { value: 'pneus', label: 'Pneus' },
  { value: 'custom', label: 'Personnalisation' },
]

let searchTimeout: ReturnType<typeof setTimeout>
function searchClients() {
  clearTimeout(searchTimeout)
  if (clientSearch.value.length < 2) { clientResults.value = []; return }
  searchTimeout = setTimeout(async () => {
    const data = await api.get(`/clients?nom=${encodeURIComponent(clientSearch.value)}`)
    const raw = data?.['hydra:member'] ?? data?.member ?? (Array.isArray(data) ? data : [])
    clientResults.value = raw
  }, 300)
}

function selectClient(c: any) {
  selectedClient.value = c
  form.client_id = c.id
  clientResults.value = []
}

async function submit() {
  submitting.value = true
  try {
    const rdv = await rdvStore.createRdv(form)
    toast.add({ title: 'RDV créé', color: 'success' })
    navigateTo(`/rdv/${rdv.id}`)
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  } finally {
    submitting.value = false
  }
}

onMounted(async () => {
  const [pontsData, mecasData] = await Promise.all([
    api.get('/ponts').catch(() => []),
    api.get('/mecaniciens').catch(() => []),
  ])
  const ponts = pontsData?.['hydra:member'] ?? pontsData?.member ?? (Array.isArray(pontsData) ? pontsData : [])
  const mecas = mecasData?.['hydra:member'] ?? mecasData?.member ?? (Array.isArray(mecasData) ? mecasData : [])
  pontOptions.value = ponts.map((p: any) => ({ value: p.id, label: p.nom }))
  mecaOptions.value = mecas.map((m: any) => ({ value: m.id, label: `${m.prenom} ${m.nom}` }))
})
</script>
