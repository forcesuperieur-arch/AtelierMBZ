<template>
  <div>
    <AppPageHeader title="Devis">
      <template #actions>
        <button class="topbar-new-btn" @click="showNew = true">+ Nouveau devis</button>
      </template>
    </AppPageHeader>

    <!-- Filtres -->
    <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:center;">
      <button v-for="f in statusFilters" :key="f.value" class="btn" :class="filterStatus === f.value ? 'btn-primary' : 'btn-ghost'" style="font-size:12px;padding:6px 14px;" @click="filterStatus = f.value">
        {{ f.label }}
      </button>
      <div style="flex:1;" />
      <input v-model="searchText" class="form-input" placeholder="Rechercher n° devis, client…" style="width:250px;" />
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
          <NuxtLink :to="`/devis/${row.original.id}`" style="color:#FFD200;font-size:12px;font-weight:600;text-decoration:none;">Voir →</NuxtLink>
        </template>
      </UTable>
    </UCard>

    <!-- Modal Nouveau Devis -->
    <div v-if="showNew" class="app-modal-overlay" @click.self="showNew = false">
      <div class="app-modal-card app-modal-xl">
        <div class="app-modal-header">
          <span style="font-weight:600;font-size:16px;">Nouveau devis</span>
          <button @click="showNew = false" style="background:none;border:none;color:#9CA3AF;font-size:18px;cursor:pointer;">✕</button>
        </div>

        <div class="app-modal-body">
          <!-- Client search -->
          <div style="margin-bottom:16px;">
            <label class="form-label">Client *</label>
            <input v-model="newDevis.clientSearch" @input="searchClients" class="form-input" placeholder="Rechercher un client…" />
            <div v-if="clientResults.length" style="background:var(--dark3);border:1px solid rgba(255,255,255,0.08);border-radius:8px;margin-top:4px;max-height:150px;overflow-y:auto;">
              <div v-for="c in clientResults" :key="c.id" @click="selectClient(c)" style="padding:8px 12px;cursor:pointer;font-size:13px;color:#E8E9ED;border-bottom:1px solid rgba(255,255,255,0.04);" class="hover-row">
                {{ c.prenom }} {{ c.nom }} <span style="color:#6B7280;margin-left:8px;">{{ c.telephone }}</span>
              </div>
            </div>
            <div v-if="newDevis.selectedClient" style="margin-top:6px;font-size:13px;color:#FFD200;">✓ {{ newDevis.selectedClient.prenom }} {{ newDevis.selectedClient.nom }}</div>
          </div>

          <!-- Véhicule -->
          <div style="margin-bottom:16px;">
            <label class="form-label">Véhicule (optionnel)</label>
            <input v-model="newDevis.vehiculeSearch" class="form-input" placeholder="Plaque ou marque…" />
          </div>

          <!-- Kilométrage -->
          <div style="margin-bottom:16px;">
            <label class="form-label">Kilométrage</label>
            <input v-model.number="newDevis.kilometrage" type="number" class="form-input" placeholder="km" />
          </div>

          <!-- Lignes -->
          <div style="margin-bottom:16px;overflow-x:auto;">
            <label class="form-label">Lignes du devis</label>
            <div v-for="(ligne, i) in newDevis.lignes" :key="i" style="display:grid;grid-template-columns:120px minmax(260px,2fr) 60px 100px 90px 40px;gap:6px;margin-bottom:8px;align-items:center;min-width:760px;">
              <select v-model="ligne.type" class="form-input" style="font-size:11px;">
                <option value="forfait_mo">Forfait MO</option>
                <option value="main_oeuvre_libre">MO libre</option>
                <option value="piece">Pièce</option>
              </select>
              <input v-model="ligne.designation" class="form-input" placeholder="Désignation" />
              <input v-model.number="ligne.quantite" type="number" class="form-input" placeholder="Qté" min="1" style="text-align:center;" />
              <input v-model.number="ligne.prix_unitaire_ht" type="number" class="form-input" placeholder="Prix HT" step="0.01" style="text-align:right;" />
              <select v-model.number="ligne.tva" class="form-input" style="font-size:11px;">
                <option :value="20">TVA 20%</option>
                <option :value="10">TVA 10%</option>
                <option :value="0">TVA 0%</option>
              </select>
              <button @click="newDevis.lignes.splice(i, 1)" style="background:none;border:none;color:#EF4444;cursor:pointer;font-size:16px;">✕</button>
            </div>
            <button @click="newDevis.lignes.push({ type: 'forfait_mo', designation: '', quantite: 1, prix_unitaire_ht: 0, tva: 20 })" class="btn btn-ghost" style="font-size:12px;">+ Ajouter ligne</button>
            <div style="margin-top:8px;text-align:right;font-size:13px;color:#FFD200;font-weight:700;">
              Total HT : {{ formatCurrency(newDevis.lignes.reduce((s, l) => s + (l.prix_unitaire_ht || 0) * (l.quantite || 1), 0)) }}
            </div>
          </div>

          <!-- Remise -->
          <div style="margin-bottom:16px;">
            <label class="form-label">Remise (%)</label>
            <div style="display:flex;align-items:center;gap:12px;">
              <input type="range" v-model.number="newDevis.remise" min="0" max="100" style="flex:1;accent-color:#FFD200;" />
              <span style="font-size:14px;font-weight:700;color:#10B981;min-width:40px;">{{ newDevis.remise }}%</span>
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

let searchTimer: any = null
function searchClients() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(async () => {
    if (newDevis.clientSearch.length < 2) { clientResults.value = []; return }
    try {
      const data = await api.get(`/clients?search=${encodeURIComponent(newDevis.clientSearch)}`)
      clientResults.value = data?.['hydra:member'] ?? data?.member ?? (Array.isArray(data) ? data : [])
    } catch { clientResults.value = [] }
  }, 300)
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
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e?.message || 'Échec', color: 'error' })
  } finally {
    submitting.value = false
  }
}

async function loadDevis() {
  try {
    const data = await api.get('/devis')
    const raw = data?.['hydra:member'] ?? data?.member ?? (Array.isArray(data) ? data : [])
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
