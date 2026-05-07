<template>
  <div>
    <AppPageHeader title="Devis">
      <template #actions>
        <button class="topbar-new-btn" @click="showNew = true">+ Nouveau devis</button>
      </template>
    </AppPageHeader>

    <!-- Filtres -->
    <div class="filter-bar">
      <button v-for="f in statusFilters" :key="f.value" class="btn filter-btn" :class="filterStatus === f.value ? 'btn-primary' : 'btn-ghost'" @click="filterStatus = f.value">
        {{ f.label }}
      </button>
      <div class="flex-1" />
      <input v-model="searchText" class="form-input search-input" placeholder="Rechercher n° devis, client…" />
    </div>

    <UCard>
      <UTable :data="filteredDevis" :columns="columns" :loading="loading">
        <template #statut-cell="{ row }">
          <StatusBadge :status="devisStatusMap[row.original.statut] || 'en_attente'" />
        </template>
        <template #total_ttc-cell="{ row }">
          {{ formatCurrency(row.original.total_ttc) }}
        </template>
        <template #date_creation-cell="{ row }">
          {{ formatDate(row.original.date_creation || row.original.dateCreation) }}
        </template>
        <template #actions-cell="{ row }">
          <AppActionLink :to="`/devis/${row.original.id}`" variant="primary">Voir →</AppActionLink>
        </template>
      </UTable>
    </UCard>

    <!-- Modal Nouveau Devis -->
    <div v-if="showNew" class="app-modal-overlay" @click.self="showNew = false">
      <div class="app-modal-card app-modal-xl">
        <div class="app-modal-header">
          <span class="modal-title">Nouveau devis</span>
          <button class="modal-close-btn" @click="showNew = false">✕</button>
        </div>

        <div class="app-modal-body">
          <!-- Client search -->
          <div class="form-group">
            <label class="form-label">Client *</label>
            <input v-model="newDevis.clientSearch" @input="searchClients" class="form-input" placeholder="Rechercher un client…" />
            <div v-if="clientResults.length" class="dropdown-list">
              <div v-for="c in clientResults" :key="c.id" @click="selectClient(c)" class="dropdown-item hover-row">
                {{ c.prenom }} {{ c.nom }} <span class="dropdown-meta">{{ c.telephone }}</span>
              </div>
            </div>
            <div v-if="newDevis.selectedClient" class="selected-tag">✓ {{ newDevis.selectedClient.prenom }} {{ newDevis.selectedClient.nom }}</div>
          </div>

          <!-- Véhicule -->
          <div class="form-group">
            <label class="form-label">Véhicule (optionnel)</label>
            <input v-model="newDevis.vehiculeSearch" class="form-input" placeholder="Plaque ou marque…" />
          </div>

          <!-- Kilométrage -->
          <div class="form-group">
            <label class="form-label">Kilométrage</label>
            <input v-model.number="newDevis.kilometrage" type="number" class="form-input" placeholder="km" />
          </div>

          <!-- Lignes -->
          <div class="form-group overflow-auto">
            <label class="form-label">Lignes du devis</label>
            <div v-for="(ligne, i) in newDevis.lignes" :key="i" class="ligne-grid">
              <select v-model="ligne.type" class="form-input input-xs">
                <option value="forfait_mo">Forfait MO</option>
                <option value="main_oeuvre_libre">MO libre</option>
                <option value="piece">Pièce</option>
              </select>
              <input v-model="ligne.designation" class="form-input" placeholder="Désignation" />
              <input v-model.number="ligne.quantite" type="number" class="form-input text-center" placeholder="Qté" min="1" />
              <input v-model.number="ligne.prix_unitaire_ht" type="number" class="form-input text-right" placeholder="Prix HT" step="0.01" />
              <select v-model.number="ligne.tva" class="form-input input-xs">
                <option :value="20">TVA 20%</option>
                <option :value="10">TVA 10%</option>
                <option :value="0">TVA 0%</option>
              </select>
              <button @click="newDevis.lignes.splice(i, 1)" class="btn-icon-danger">✕</button>
            </div>
            <button @click="newDevis.lignes.push({ type: 'forfait_mo', designation: '', quantite: 1, prix_unitaire_ht: 0, tva: 20 })" class="btn btn-ghost btn-sm">+ Ajouter ligne</button>
            <div class="total-display">
              Total HT : {{ formatCurrency(newDevis.lignes.reduce((s, l) => s + (l.prix_unitaire_ht || 0) * (l.quantite || 1), 0)) }}
            </div>
          </div>

          <!-- Remise -->
          <div class="form-group">
            <label class="form-label">Remise (%)</label>
            <div class="flex-row-gap">
              <input type="range" v-model.number="newDevis.remise" min="0" max="100" class="flex-1 range-accent" />
              <span class="remise-value">{{ newDevis.remise }}%</span>
            </div>
          </div>

          <!-- Notes -->
          <div>
            <label class="form-label">Notes client</label>
            <textarea v-model="newDevis.notes_client" class="form-input" rows="2" placeholder="Visible par le client…" />
          </div>
        </div>

        <div class="app-modal-footer">
          <button class="btn btn-ghost" @click="showNew = false">Annuler</button>
          <button class="btn btn-primary" @click="submitDevis" :disabled="submitting">{{ submitting ? 'Création…' : 'Créer le devis' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const toast = useToast()
const loading = ref(true)
const devisList = ref<any[]>([])
const showNew = ref(false)
const submitting = ref(false)
const filterStatus = ref('all')
const clientResults = ref<any[]>([])
const searchText = ref('')
const statusFilters = [
  { value: 'all', label: 'Tous' },
  { value: 'brouillon', label: 'Brouillons' },
  { value: 'envoye', label: 'Envoyés' },
  { value: 'accepte', label: 'Acceptés' },
  { value: 'refuse', label: 'Refusés' },
]

const devisStatusMap: Record<string, string> = {
  brouillon: 'en_attente',
  envoye: 'en_cours',
  accepte: 'confirme',
  refuse: 'annule',
  expire: 'annule',
  converti: 'termine',
}

const newDevis = reactive({
  clientSearch: '',
  selectedClient: null as any,
  vehiculeSearch: '',
  kilometrage: null as number | null,
  lignes: [{ type: 'forfait_mo', designation: '', quantite: 1, prix_unitaire_ht: 0, tva: 20 }] as any[],
  notes_client: '',
  remise: 0,
})

const columns = [
  { key: 'numero_devis', label: 'N°' },
  { key: 'date_creation', label: 'Date' },
  { key: 'client_nom', label: 'Client' },
  { key: 'total_ttc', label: 'Total TTC' },
  { key: 'statut', label: 'Statut' },
  { key: 'actions', label: '' },
]

const filteredDevis = computed(() => {
  let list = devisList.value
  if (filterStatus.value !== 'all') list = list.filter(d => d.statut === filterStatus.value)
  if (searchText.value.trim()) {
    const q = searchText.value.toLowerCase().trim()
    list = list.filter(d => {
      const hay = `${d.numero_devis || ''} ${d.client_nom || ''}`.toLowerCase()
      return hay.includes(q)
    })
  }
  return list
})

function formatCurrency(v: number) {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(v || 0)
}

function formatDate(d: string) {
  if (!d) return ''
  try { return new Date(d).toLocaleDateString('fr-FR') } catch { return d }
}

const debouncedSearchClients = useDebounceFn(async () => {
  if (newDevis.clientSearch.length < 2) { clientResults.value = []; return }
  try {
    const data = await api.get(`/clients?search=${encodeURIComponent(newDevis.clientSearch)}`)
    clientResults.value = unwrapHydraOrEmpty(data)
  } catch { clientResults.value = [] }
}, 300)

function searchClients() {
  if (newDevis.clientSearch.length < 2) { clientResults.value = []; debouncedSearchClients.cancel(); return }
  debouncedSearchClients()
}

function selectClient(c: any) {
  newDevis.selectedClient = c
  newDevis.clientSearch = `${c.prenom} ${c.nom}`
  clientResults.value = []
}

async function submitDevis() {
  if (!newDevis.selectedClient) {
    toast.add({ title: 'Sélectionnez un client', color: 'warning' }); return
  }
  submitting.value = true
  try {
    const payload: any = {
      client: `/api/clients/${newDevis.selectedClient.id}`,
      kilometrage: newDevis.kilometrage,
      notes_client: newDevis.notes_client,
      remise_pourcentage: newDevis.remise || 0,
      lignes: newDevis.lignes.filter(l => l.designation).map(l => ({
        type: l.type,
        designation: l.designation,
        quantite: l.quantite,
        prix_unitaire_ht: l.prix_unitaire_ht,
        taux_tva: l.tva ?? 20,
      })),
    }
    await api.post('/devis', payload)
    toast.add({ title: 'Devis créé', color: 'success' })
    showNew.value = false
    await loadDevis()
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: (e instanceof Error ? e.message : 'Erreur inconnue') || 'Échec', color: 'error' })
  } finally {
    submitting.value = false
  }
}

async function loadDevis() {
  try {
    const data = await api.get('/devis')
    const raw = unwrapHydraOrEmpty(data)
    devisList.value = raw.map((d: any) => {
      const c = d.client
      return {
        ...d,
        client_nom: c ? `${c.prenom} ${c.nom}` : d.client_nom ?? '',
      }
    })
  } catch { /* silent */ }
}

onMounted(async () => {
  await loadDevis()
  loading.value = false
})
</script>

<style scoped>
.filter-bar { display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap; align-items:center; }
.filter-btn { font-size:12px; padding:6px 14px; }
.search-input { width:250px; }
.flex-1 { flex:1; }
.modal-title { font-weight:600; font-size:16px; }
.modal-close-btn { background:none; border:none; color:#9CA3AF; font-size:18px; cursor:pointer; }
.form-group { margin-bottom:16px; }
.dropdown-list { background:var(--dark3); border:1px solid rgba(255,255,255,0.08); border-radius:8px; margin-top:4px; max-height:150px; overflow-y:auto; }
.dropdown-item { padding:8px 12px; cursor:pointer; font-size:13px; color:#E8E9ED; border-bottom:1px solid rgba(255,255,255,0.04); }
.dropdown-meta { color:#6B7280; margin-left:8px; }
.selected-tag { margin-top:6px; font-size:13px; color:#FFD200; }
.overflow-auto { overflow-x:auto; }
.ligne-grid { display:grid; grid-template-columns:120px minmax(260px,2fr) 60px 100px 90px 40px; gap:6px; margin-bottom:8px; align-items:center; min-width:760px; }
.input-xs { font-size:11px; }
.text-center { text-align:center; }
.text-right { text-align:right; }
.btn-icon-danger { background:none; border:none; color:#EF4444; cursor:pointer; font-size:16px; }
.btn-sm { font-size:12px; }
.total-display { margin-top:8px; text-align:right; font-size:13px; color:#FFD200; font-weight:700; }
.flex-row-gap { display:flex; align-items:center; gap:12px; }
.range-accent { accent-color:#FFD200; }
.remise-value { font-size:14px; font-weight:700; color:#10B981; min-width:40px; }
</style>
