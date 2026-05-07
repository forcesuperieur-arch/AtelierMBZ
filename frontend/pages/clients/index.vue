<template>
  <div>
    <AppPageHeader title="Clients">
      <template #actions>
        <button class="topbar-new-btn" @click="showNew = true">+ Nouveau client</button>
      </template>
    </AppPageHeader>

    <!-- Stat cards -->
    <div class="grid-4 mb-5">
      <div class="stat-card">
        <div class="stat-label">👥 Total Clients</div>
        <div class="stat-value">{{ stats.total }}</div>
        <div class="stat-bar"><div class="stat-bar-fill blue w-full"></div></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">📅 Avec RDV</div>
        <div class="stat-value">{{ stats.avec_rdv }}</div>
        <div class="stat-delta text-green">{{ stats.total ? Math.round(stats.avec_rdv / stats.total * 100) : 0 }}% actifs</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">🏍️ Total Véhicules</div>
        <div class="stat-value">{{ stats.vehicules }}</div>
        <div class="stat-delta text-muted">{{ stats.total ? (stats.vehicules / stats.total).toFixed(1) : 0 }} / client</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">💰 CA Total</div>
        <div class="stat-value">{{ formatCA(stats.ca_total) }}</div>
        <div class="stat-bar"><div class="stat-bar-fill orange" :class="stats.ca_total > 0 ? 'w-65' : 'w-0'"></div></div>
      </div>
    </div>

    <UCard class="mb-4">
      <UInput v-model="search" placeholder="Rechercher un client..." @input="debouncedFetch" />
    </UCard>

    <UCard>
      <UTable :data="clients" :columns="columns" :loading="loading">
        <template #vehicules-cell="{ row }">
          <span class="text-sm">{{ row.original.vehicules_count ?? 0 }} véhicule(s)</span>
        </template>
        <template #actions-cell="{ row }">
          <AppInlineActions>
            <AppActionLink :to="`/clients/${row.original.id}`" variant="primary">Voir →</AppActionLink>
            <button
              v-if="row.original.can_anonymize !== false"
              class="btn btn-ghost btn-danger-xs"
              @click="anonymizeClient(row.original)"
              :disabled="anonymizingId === row.original.id"
            >
              {{ anonymizingId === row.original.id ? '…' : '🧹 Anonymiser' }}
            </button>
          </AppInlineActions>
        </template>
      </UTable>
      <!-- Pagination -->
      <div v-if="totalPages > 1" class="pagination">
        <button class="btn btn-ghost pagination-btn" :disabled="page <= 1" @click="page--; fetchClients()">← Préc</button>
        <button v-for="p in visiblePages" :key="p" class="btn pagination-btn" :class="p === page ? 'btn-primary' : 'btn-ghost'" @click="page = p; fetchClients()">{{ p }}</button>
        <button class="btn btn-ghost pagination-btn" :disabled="page >= totalPages" @click="page++; fetchClients()">Suiv →</button>
      </div>
      <div class="pagination-total">{{ totalItems }} client(s) au total</div>
    </UCard>

    <!-- New client modal -->
    <AppModal v-model:open="showNew" size="lg">
      <template #default>
        <UCard>
          <template #header><span class="modal-title">Nouveau client</span></template>
          <form @submit.prevent="createClient" class="form-stack">
            <div class="form-grid-2">
              <UFormField label="Prénom"><UInput v-model="newClient.prenom" required /></UFormField>
              <UFormField label="Nom"><UInput v-model="newClient.nom" required /></UFormField>
              <UFormField label="Téléphone"><UInput v-model="newClient.telephone" required /></UFormField>
              <UFormField label="Email"><UInput v-model="newClient.email" type="email" /></UFormField>
            </div>
            <UFormField label="Adresse"><UInput v-model="newClient.adresse" /></UFormField>
            <label class="checkbox-label">
              <input type="checkbox" v-model="consentRGPD" class="checkbox-input" />
              <span class="checkbox-text">Le client consent au traitement de ses données personnelles conformément à notre <NuxtLink to="/public/politique-confidentialite" target="_blank" class="link-yellow">politique de confidentialité</NuxtLink>.</span>
            </label>
            <div class="form-footer">
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
const anonymizingId = ref<number | null>(null)

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
    const paginated = unwrapHydraPaginated(data)
    totalItems.value = paginated.totalItems
    clients.value = paginated.items.map((c: any) => ({
      ...c,
      vehicules_count: c.vehicules?.length ?? 0,
    }))
  } finally {
    loading.value = false
  }
}

const debouncedFetch = useDebounceFn(() => {
  page.value = 1
  fetchClients()
}, 300)

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

async function anonymizeClient(client: any) {
  if (!confirm(`⚠️ ATTENTION : Cette action est IRRÉVERSIBLE.\n\nToutes les données personnelles de ${client.prenom} ${client.nom} seront effacées.\nLes factures et ordres conserveront un snapshot conforme aux obligations légales.\n\nConfirmez-vous l'anonymisation ?`)) {
    return
  }
  anonymizingId.value = client.id
  try {
    await api.post(`/clients/${client.id}/anonymize`)
    toast.add({ title: 'Client anonymisé', description: 'Les données personnelles ont été effacées.', color: 'success' })
    await fetchClients()
  } catch (e: unknown) {
    toast.add({ title: 'Erreur anonymisation', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
  } finally {
    anonymizingId.value = null
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

<style scoped>
.mb-5 { margin-bottom:20px; }
.mb-4 { margin-bottom:16px; }
.stat-bar-fill.blue { background:var(--blue); }
.stat-bar-fill.orange { background:var(--orange); }
.w-full { width:100%; }
.w-65 { width:65%; }
.w-0 { width:0%; }
.text-green { color:#10B981; }
.text-muted { color:#9CA3AF; }
.pagination { display:flex; justify-content:center; gap:6px; margin-top:16px; padding-top:12px; border-top:1px solid rgba(255,255,255,0.06); }
.pagination-btn { font-size:12px; padding:6px 12px; min-width:36px; }
.pagination-total { text-align:center; font-size:11px; color:#6B7280; margin-top:6px; }
.modal-title { font-size:15px; font-weight:700; color:#E8E9ED; }
.form-stack { display:flex; flex-direction:column; gap:12px; }
.form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.checkbox-label { display:flex; align-items:flex-start; gap:8px; cursor:pointer; }
.checkbox-input { margin-top:3px; accent-color:#FFD200; }
.checkbox-text { font-size:12px; color:#9CA3AF; }
.link-yellow { color:#FFD200; }
.form-footer { display:flex; justify-content:flex-end; gap:8px; }
.btn-danger-xs { font-size:11px; padding:4px 8px; color:#EF4444; }
</style>
