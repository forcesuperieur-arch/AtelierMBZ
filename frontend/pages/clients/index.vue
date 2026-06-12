<template>
  <div>
    <div class="page-header">
      <div class="page-title">Clients</div>
      <button class="topbar-new-btn" @click="showNew = true">+ Nouveau client</button>
    </div>

    <!-- Stat cards -->
    <div class="grid-4" style="margin-bottom:20px;">
      <div class="stat-card">
        <div class="stat-label">👥 Total Clients</div>
        <div class="stat-value">{{ stats.total }}</div>
        <div class="stat-bar"><div class="stat-bar-fill" style="background:var(--blue);" :style="{ width: '100%' }"></div></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">📅 Avec RDV</div>
        <div class="stat-value">{{ stats.avec_rdv }}</div>
        <div class="stat-delta" style="color:#10B981;">{{ stats.total ? Math.round(stats.avec_rdv / stats.total * 100) : 0 }}% actifs</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">🏍️ Total Véhicules</div>
        <div class="stat-value">{{ stats.vehicules }}</div>
        <div class="stat-delta" style="color:#9CA3AF;">{{ stats.total ? (stats.vehicules / stats.total).toFixed(1) : 0 }} / client</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">💰 CA Total</div>
        <div class="stat-value">{{ formatCA(stats.ca_total) }}</div>
        <div class="stat-bar"><div class="stat-bar-fill" style="background:var(--orange);" :style="{ width: stats.ca_total > 0 ? '65%' : '0%' }"></div></div>
      </div>
    </div>

    <UCard style="margin-bottom:16px;">
      <UInput v-model="search" placeholder="Rechercher un client..." @input="debouncedFetch" />
    </UCard>

    <UCard>
      <UTable :data="clients" :columns="columns" :loading="loading">
        <template #vehicules-cell="{ row }">
          <span class="text-sm">{{ row.original.vehicules_count ?? 0 }} véhicule(s)</span>
        </template>
        <template #actions-cell="{ row }">
          <NuxtLink :to="`/clients/${row.original.id}`" style="color:#FFD200;font-size:12px;font-weight:600;text-decoration:none;">Voir →</NuxtLink>
        </template>
      </UTable>
      <!-- Pagination -->
      <div v-if="totalPages > 1" style="display:flex;justify-content:center;gap:6px;margin-top:16px;padding-top:12px;border-top:1px solid rgba(255,255,255,0.06);">
        <button class="btn btn-ghost" :disabled="page <= 1" @click="page--; fetchClients()" style="font-size:12px;padding:6px 12px;">← Préc</button>
        <button v-for="p in visiblePages" :key="p" class="btn" :class="p === page ? 'btn-primary' : 'btn-ghost'" @click="page = p; fetchClients()" style="font-size:12px;padding:6px 12px;min-width:36px;">{{ p }}</button>
        <button class="btn btn-ghost" :disabled="page >= totalPages" @click="page++; fetchClients()" style="font-size:12px;padding:6px 12px;">Suiv →</button>
      </div>
      <div style="text-align:center;font-size:11px;color:#6B7280;margin-top:6px;">{{ totalItems }} client(s) au total</div>
    </UCard>

    <!-- New client modal -->
    <AppModal v-model:open="showNew" size="lg">
      <template #default>
        <UCard>
          <template #header><span style="font-size:15px;font-weight:700;color:#E8E9ED;">Nouveau client</span></template>
          <form @submit.prevent="createClient" style="display:flex;flex-direction:column;gap:12px;">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
              <UFormField label="Prénom"><UInput v-model="newClient.prenom" required /></UFormField>
              <UFormField label="Nom"><UInput v-model="newClient.nom" required /></UFormField>
              <UFormField label="Téléphone"><UInput v-model="newClient.telephone" required /></UFormField>
              <UFormField label="Email"><UInput v-model="newClient.email" type="email" /></UFormField>
            </div>
            <UFormField label="Adresse"><UInput v-model="newClient.adresse" /></UFormField>
            <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;">
              <input type="checkbox" v-model="consentRGPD" style="margin-top:3px;accent-color:#FFD200;" />
              <span style="font-size:12px;color:#9CA3AF;">Le client consent au traitement de ses données personnelles conformément à notre <NuxtLink to="/public/politique-confidentialite" target="_blank" style="color:#FFD200;">politique de confidentialité</NuxtLink>.</span>
            </label>
            <div style="display:flex;justify-content:flex-end;gap:8px;">
              <UButton label="Annuler" variant="outline" @click="showNew = false" />
              <UButton type="submit" label="Créer" :loading="creating" :disabled="!consentRGPD" />
            </div>
          </form>
        </UCard>
      </template>
    </AppModal>
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
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  } finally {
    creating.value = false
  }
}

onMounted(() => {
  fetchClients()
  api.get('/clients/stats').then((s: any) => {
    if (s) Object.assign(stats, s)
  }).catch(() => {
    // stats not available — compute from loaded clients
    const c = clients.value
    stats.total = c.length
    stats.avec_rdv = c.filter((x: any) => x.rdv_count > 0).length
    stats.vehicules = c.reduce((a: number, x: any) => a + (x.vehicules_count ?? 0), 0)
  })
})
</script>
