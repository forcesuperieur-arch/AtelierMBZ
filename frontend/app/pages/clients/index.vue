<template>
  <div>
    <PitPageHeader title="Clients">
      <template #actions>
        <PitButton variant="primary" @click="showNew = true">
          <UIcon name="i-heroicons-plus" class="w-4 h-4" />
          <span class="hidden sm:inline">Nouveau client</span>
        </PitButton>
      </template>
    </PitPageHeader>

    <!-- Stat cards -->
    <PitKpiGrid class="mb-6">
      <PitTachometer
        :value="stats.total"
        label="Clients totaux"
        icon="i-heroicons-users"
      />
      <PitTachometer
        :value="stats.avec_rdv"
        label="Avec RDV ce mois"
        :hint="`${stats.total ? Math.round(stats.avec_rdv / stats.total * 100) : 0}% actifs`"
        icon="i-heroicons-calendar"
      />
      <PitTachometer
        :value="stats.vehicules"
        label="Véhicules"
        :hint="`${stats.total ? (stats.vehicules / stats.total).toFixed(1) : 0} / client`"
        icon="i-heroicons-truck"
      />
      <PitTachometer
        :value="formatCA(stats.ca_total)"
        label="CA total"
        icon="i-heroicons-banknotes"
      />
    </PitKpiGrid>

    <!-- Search -->
    <PitCard class="mb-4">
      <PitInput
        v-model="search"
        placeholder="Rechercher un client..."
        icon="i-heroicons-magnifying-glass"
        @update:model-value="debouncedFetch"
      />
    </PitCard>

    <!-- Table -->
    <PitCard>
      <PitTable
        :data="clients"
        :columns="columns"
        :loading="loading"
      >
        <template #vehicules-cell="{ row }">
          <span class="text-sm text-secondary">{{ row.original.vehicules_count ?? 0 }} véhicule(s)</span>
        </template>
        <template #actions-cell="{ row }">
          <NuxtLink
            :to="`/clients/${row.original.id}`"
            class="text-sm font-semibold text-primary hover:text-accent-bright transition-colors inline-flex items-center gap-1"
          >
            Voir
            <UIcon name="i-heroicons-arrow-right" class="w-3 h-3" />
          </NuxtLink>
        </template>
      </PitTable>

      <!-- Pagination -->
      <div
        v-if="totalPages > 1"
        class="flex justify-center gap-2 mt-4 pt-3 border-t border-default"
      >
        <PitButton
          variant="ghost"
          size="sm"
          :disabled="page === 1"
          @click="page--; fetchClients()"
        >
          <UIcon name="i-heroicons-chevron-left" class="w-4 h-4" />
        </PitButton>
        <PitButton
          v-for="p in visiblePages"
          :key="p"
          :variant="p === page ? 'primary' : 'ghost'"
          size="sm"
          @click="page = p; fetchClients()"
        >
          {{ p }}
        </PitButton>
        <PitButton
          variant="ghost"
          size="sm"
          :disabled="page >= totalPages"
          @click="page++; fetchClients()"
        >
          <UIcon name="i-heroicons-chevron-right" class="w-4 h-4" />
        </PitButton>
      </div>
      <div class="text-center text-xs text-tertiary mt-2">
        {{ totalItems }} client(s) au total
      </div>
    </PitCard>

    <!-- New client slideover -->
    <AppDetailSlideover v-model:open="showNew" title="Nouveau client">
      <form @submit.prevent="createClient" class="flex flex-col gap-4">
        <div class="grid grid-cols-2 gap-4">
          <PitInput v-model="newClient.prenom" label="Prénom" required />
          <PitInput v-model="newClient.nom" label="Nom" required />
          <PitInput v-model="newClient.telephone" label="Téléphone" required />
          <PitInput v-model="newClient.email" label="Email" type="email" />
        </div>
        <PitInput v-model="newClient.adresse" label="Adresse" />
        <label class="flex items-start gap-2 cursor-pointer">
          <UCheckbox v-model="consentRGPD" color="primary" />
          <span class="text-xs text-secondary">
            Le client consent au traitement de ses données personnelles conformément à notre
            <NuxtLink to="/public/politique-confidentialite" target="_blank" class="text-primary hover:text-accent-bright transition-colors">
              politique de confidentialité
            </NuxtLink>.
          </span>
        </label>
        <div class="flex justify-end gap-3 mt-2">
          <PitButton variant="ghost" @click="showNew = false">Annuler</PitButton>
          <PitButton variant="primary" type="submit" :loading="creating" :disabled="!consentRGPD">
            Créer
          </PitButton>
        </div>
      </form>
    </AppDetailSlideover>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const toast = useToast()
const { validateClientFields } = useValidation()
const loading = ref(true)
const clients = ref<any[]>([])
const search = ref('')
const showNew = ref(false)
const creating = ref(false)
const page = ref(1)
const pageSize = 50
const totalItems = ref(0)
const totalPages = computed(() => Math.ceil(totalItems.value / pageSize) || 1)
const visiblePages = computed(() => {
  const pages: number[] = []
  const start = Math.max(1, page.value - 2)
  const end = Math.min(totalPages.value, page.value + 2)
  for (let i = start; i <= end; i++) pages.push(i)
  return pages
})

const newClient = reactive({ prenom: '', nom: '', telephone: '', email: '', adresse: '' })
const consentRGPD = ref(false)

const stats = reactive({ total: 0, avec_rdv: 0, vehicules: 0, ca_total: 0 })

function formatCA(val: number) {
  if (!val) return '0 €'
  return val >= 1000 ? `${(val / 1000).toFixed(1)}k €` : `${Math.round(val)} €`
}

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
    const params = new URLSearchParams()
    if (search.value.trim()) params.set('search', search.value.trim())
    params.set('page', String(page.value))
    params.set('limit', String(pageSize))
    const data = await api.get(`/clients?${params}`)
    const raw = data?.['hydra:member'] ?? data?.member ?? (Array.isArray(data) ? data : [])
    totalItems.value = data?.['hydra:totalItems'] ?? data?.totalItems ?? raw.length
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
  page.value = 1
  timeout = setTimeout(fetchClients, 300)
}

async function createClient() {
  creating.value = true
  try {
    const formatErrors = validateClientFields({
      telephone: newClient.telephone,
      email: newClient.email,
    })
    if (formatErrors.length) {
      toast.add({ title: 'Format invalide', description: formatErrors.join(' — '), color: 'error' })
      return
    }
    const c = await api.post('/clients', {
      ...newClient,
      consentDate: new Date().toISOString(),
      consentSource: 'backoffice_form',
    })
    clients.value.unshift(c)
    showNew.value = false
    toast.add({ title: 'Client créé', color: 'success' })
    Object.assign(newClient, { prenom: '', nom: '', telephone: '', email: '', adresse: '' })
    consentRGPD.value = false
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
  } finally {
    creating.value = false
  }
}

onMounted(() => {
  fetchClients()
  api.get('/clients/stats').then((s: any) => {
    if (s) Object.assign(stats, s)
  }).catch(() => {
    const c = clients.value
    stats.total = c.length
    stats.avec_rdv = c.filter((x: any) => x.rdv_count > 0).length
    stats.vehicules = c.reduce((a: number, x: any) => a + (x.vehicules_count ?? 0), 0)
  })
})
</script>
