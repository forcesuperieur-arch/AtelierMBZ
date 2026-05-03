<template>
  <div>
    <div class="page-header-flex">
      <div class="page-title">Ordres de réparation</div>
      <button class="btn btn-ghost btn-sm" @click="exportCsv">📥 Export CSV</button>
    </div>

    <!-- KPIs -->
    <div class="kpi-grid">
      <AppKpiCard label="Ouverts" :value="kpis.open" variant="amber" />
      <AppKpiCard label="En atelier" :value="kpis.live" />
      <AppKpiCard label="À réceptionner" :value="kpis.reception" variant="green" />
      <AppKpiCard label="Finalisés" :value="kpis.completed" variant="green" />
    </div>

    <!-- Search + Filter -->
    <div class="filter-bar">
      <input v-model="search" class="form-input flex-1" placeholder="Rechercher OR, client, véhicule, plaque…" />
      <select v-model="statusFilter" class="form-input input-fixed-w">
        <option value="">Tous les statuts</option>
        <option value="reserve">Créneau réservé</option>
        <option value="confirme">Confirmé atelier</option>
        <option value="reception">Réception</option>
        <option value="en_cours">En cours</option>
        <option value="termine">Terminé</option>
        <option value="restitue">Restitué</option>
        <option value="facture">Facturé</option>
      </select>
    </div>

    <UCard>
      <div v-if="loading" class="loading-center">Chargement…</div>
      <AppEmptyState v-else-if="!filteredOrdres.length" icon="🧾" title="Aucun OR trouvé" />
      <div v-else class="or-list">
        <NuxtLink v-for="o in filteredOrdres" :key="o.id" :to="`/ordres/${o.id}`" class="or-card">
          <div class="or-left">
            <div class="or-number">{{ o.numero_or }}</div>
            <div>
              <p class="or-client">{{ o.client_nom || '—' }}</p>
              <p class="or-meta">{{ o.vehicule_info }} {{ o.plaque ? '· ' + o.plaque : '' }}</p>
            </div>
          </div>
          <div class="or-right">
            <span v-if="o.montant" class="or-amount">{{ formatEuro(o.montant) }}</span>
            <StatusBadge :status="o.status" />
            <span class="or-date">{{ o.date_label }}</span>
            <span class="or-arrow">→</span>
          </div>
        </NuxtLink>
      </div>
    </UCard>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const loading = ref(true)
const ordres = ref<any[]>([])
const search = ref('')
const statusFilter = ref('')

const kpis = computed(() => {
  const all = ordres.value
  const active = all.filter(o => !['annule', 'non_presente'].includes(o.status))
  return {
    open: active.filter(o => !['termine', 'restitue', 'facture', 'paye'].includes(o.status)).length,
    live: active.filter(o => o.status === 'en_cours').length,
    reception: active.filter(o => o.status === 'reception').length,
    completed: active.filter(o => ['termine', 'restitue', 'facture', 'paye'].includes(o.status)).length,
  }
})

const filteredOrdres = computed(() => {
  let list = ordres.value
  if (statusFilter.value) list = list.filter(o => o.status === statusFilter.value)
  if (search.value.trim()) {
    const q = search.value.toLowerCase().trim()
    list = list.filter(o => {
      const hay = `${o.numero_or} ${o.client_nom} ${o.vehicule_info} ${o.plaque}`.toLowerCase()
      return hay.includes(q)
    })
  }
  return list
})

function formatEuro(v: number) {
  return v.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' })
}

function normalizeOrdre(o: any) {
  const rdv = o.rendez_vous ?? {}
  const c = rdv.client
  const v = rdv.vehicule
  const year = rdv.date_rdv ? String(rdv.date_rdv).substring(0, 4) : String(new Date().getFullYear())
  return {
    ...o,
    numero_or: o.numero_or || `OR-${year}-${String(o.id || 0).padStart(3, '0')}`,
    client_nom: c ? `${c.prenom ?? ''} ${c.nom ?? ''}`.trim() : '',
    vehicule_info: v ? `${v.marque ?? ''} ${v.modele ?? ''}`.trim() : '',
    plaque: v?.plaque ?? '',
    status: rdv.statut ?? '',
    montant: o.montant_estime ?? rdv.prix_estime ?? null,
    date_label: rdv.date_rdv ? new Date(rdv.date_rdv).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' }) : '',
  }
}

function exportCsv() {
  const rows = [['OR', 'Date', 'Client', 'Véhicule', 'Plaque', 'Statut', 'Montant']]
  filteredOrdres.value.forEach(o => {
    rows.push([o.numero_or, o.date_label, o.client_nom, o.vehicule_info, o.plaque, o.status, o.montant != null ? String(o.montant) : ''])
  })
  const csv = rows.map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(';')).join('\n')
  const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `ordres_reparation_${new Date().toISOString().slice(0, 10)}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

onMounted(async () => {
  try {
    const data = await api.get('/ordres-reparation')
    ordres.value = unwrapHydraOrEmpty(data).map(normalizeOrdre)
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.page-header-flex { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; }
.kpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:12px; margin-bottom:20px; }
.filter-bar { display:flex; gap:12px; margin-bottom:16px; flex-wrap:wrap; }
.flex-1 { flex:1; min-width:200px; }
.input-fixed-w { width:180px; }
.loading-center { padding:32px; text-align:center; color:#6B7280; }
.or-list { display:flex; flex-direction:column; gap:10px; }
.or-card { text-decoration:none; display:flex; align-items:center; justify-content:space-between; padding:14px; border-radius:12px; border:1px solid rgba(255,255,255,0.06); background:rgba(255,255,255,0.02); transition:border-color 0.2s; }
.or-card:hover { border-color: rgba(255,210,0,0.3); }
.or-left { display:flex; align-items:center; gap:14px; }
.or-number { font-size:13px; font-weight:700; color:#FFD200; min-width:110px; }
.or-client { font-weight:600; color:#E8E9ED; font-size:13px; }
.or-meta { font-size:12px; color:#6B7280; }
.or-right { display:flex; align-items:center; gap:12px; }
.or-amount { font-size:13px; font-weight:700; color:#FFD200; }
.or-date { font-size:12px; color:#6B7280; }
.or-arrow { color:#FFD200; font-size:12px; font-weight:600; }
</style>
