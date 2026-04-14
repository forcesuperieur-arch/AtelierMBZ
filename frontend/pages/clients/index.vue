<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Clients</h1>
      <UButton label="Nouveau client" icon="i-heroicons-plus" @click="showNew = true" />
    </div>

    <UCard class="mb-4">
      <UInput v-model="search" placeholder="Rechercher un client..." icon="i-heroicons-magnifying-glass" @input="debouncedFetch" />
    </UCard>

    <UCard>
      <UTable :data="clients" :columns="columns" :loading="loading">
        <template #vehicules-cell="{ row }">
          <span class="text-sm">{{ row.original.vehicules_count ?? 0 }} véhicule(s)</span>
        </template>
        <template #actions-cell="{ row }">
          <UButton size="xs" variant="ghost" icon="i-heroicons-eye" :to="`/clients/${row.original.id}`" />
        </template>
      </UTable>
    </UCard>

    <!-- New client modal -->
    <UModal v-model:open="showNew">
      <template #default>
        <UCard>
          <template #header><h2 class="font-semibold">Nouveau client</h2></template>
          <form @submit.prevent="createClient" class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
              <UFormField label="Prénom"><UInput v-model="newClient.prenom" required /></UFormField>
              <UFormField label="Nom"><UInput v-model="newClient.nom" required /></UFormField>
              <UFormField label="Téléphone"><UInput v-model="newClient.telephone" required /></UFormField>
              <UFormField label="Email"><UInput v-model="newClient.email" type="email" /></UFormField>
            </div>
            <UFormField label="Adresse"><UInput v-model="newClient.adresse" /></UFormField>
            <div class="flex justify-end gap-2">
              <UButton label="Annuler" variant="outline" @click="showNew = false" />
              <UButton type="submit" label="Créer" :loading="creating" />
            </div>
          </form>
        </UCard>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const toast = useToast()
const loading = ref(true)
const clients = ref<any[]>([])
const search = ref('')
const showNew = ref(false)
const creating = ref(false)

const newClient = reactive({ prenom: '', nom: '', telephone: '', email: '', adresse: '' })

const columns = [
  { key: 'nom', label: 'Nom' },
  { key: 'prenom', label: 'Prénom' },
  { key: 'telephone', label: 'Téléphone' },
  { key: 'email', label: 'Email' },
  { key: 'vehicules', label: 'Véhicules' },
  { key: 'actions', label: '' },
]

async function fetchClients() {
  loading.value = true
  try {
    const qs = search.value ? `?nom=${encodeURIComponent(search.value)}` : ''
    const data = await api.get(`/clients${qs}`)
    const raw = data?.['hydra:member'] ?? data?.member ?? (Array.isArray(data) ? data : [])
    clients.value = raw.map((c: any) => ({
      ...c,
      vehicules_count: c.vehicules?.length ?? 0,
    }))
  } finally {
    loading.value = false
  }
}

let timeout: ReturnType<typeof setTimeout>
function debouncedFetch() {
  clearTimeout(timeout)
  timeout = setTimeout(fetchClients, 300)
}

async function createClient() {
  creating.value = true
  try {
    const c = await api.post('/clients', newClient)
    clients.value.unshift(c)
    showNew.value = false
    toast.add({ title: 'Client créé', color: 'success' })
    Object.assign(newClient, { prenom: '', nom: '', telephone: '', email: '', adresse: '' })
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  } finally {
    creating.value = false
  }
}

onMounted(fetchClients)
</script>
