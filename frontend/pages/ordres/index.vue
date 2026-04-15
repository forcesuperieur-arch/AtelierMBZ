<template>
  <div>
    <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
      <div class="page-title">Ordres de réparation</div>
      <button class="btn btn-ghost" style="font-size:12px;" @click="exportCsv">📥 Export CSV</button>
    </div>

    <!-- KPIs -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:20px;">
      <div class="stat-card">
        <div style="font-size:10px;color:#6B7280;text-transform:uppercase;">Ouverts</div>
        <div style="font-size:22px;font-weight:700;color:#F59E0B;">{{ kpis.open }}</div>
      </div>
      <div class="stat-card">
        <div style="font-size:10px;color:#6B7280;text-transform:uppercase;">En atelier</div>
        <div style="font-size:22px;font-weight:700;color:#FFD200;">{{ kpis.live }}</div>
      </div>
      <div class="stat-card">
        <div style="font-size:10px;color:#6B7280;text-transform:uppercase;">À réceptionner</div>
        <div style="font-size:22px;font-weight:700;color:#14B8A6;">{{ kpis.reception }}</div>
      </div>
      <div class="stat-card">
        <div style="font-size:10px;color:#6B7280;text-transform:uppercase;">Finalisés</div>
        <div style="font-size:22px;font-weight:700;color:#10B981;">{{ kpis.completed }}</div>
      </div>
    </div>

    <!-- Search + Filter -->
    <div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
      <input v-model="search" class="form-input" placeholder="Rechercher OR, client, véhicule, plaque…" style="flex:1;min-width:200px;" />
      <select v-model="statusFilter" class="form-input" style="width:180px;">
        <option value="">Tous les statuts</option>
        <option value="reserve">Réservé</option>
        <option value="confirme">Confirmé</option>
        <option value="reception">Réception</option>
        <option value="en_cours">En cours</option>
        <option value="termine">Terminé</option>
        <option value="restitue">Restitué</option>
        <option value="facture">Facturé</option>
      </select>
    </div>

    <UCard>
      <div v-if="loading" style="padding:32px;text-align:center;color:#6B7280;">Chargement…</div>
      <div v-else-if="!filteredOrdres.length" style="padding:32px;text-align:center;color:#6B7280;">
        <div style="font-size:36px;margin-bottom:8px;">🧾</div>
        <p>Aucun OR trouvé</p>
      </div>
      <div v-else style="display:flex;flex-direction:column;gap:10px;">
        <NuxtLink v-for="o in filteredOrdres" :key="o.id" :to="`/ordres/${o.id}`" style="text-decoration:none;display:flex;align-items:center;justify-content:space-between;padding:14px;border-radius:12px;border:1px solid rgba(255,255,255,0.06);background:rgba(255,255,255,0.02);transition:border-color 0.2s;" class="or-card-link">
          <div style="display:flex;align-items:center;gap:14px;">
            <div style="font-size:13px;font-weight:700;color:#FFD200;min-width:110px;">{{ o.numero_or }}</div>
            <div>
              <p style="font-weight:600;color:#E8E9ED;font-size:13px;">{{ o.client_nom || '—' }}</p>
              <p style="font-size:12px;color:#6B7280;">{{ o.vehicule_info }} {{ o.plaque ? '· ' + o.plaque : '' }}</p>
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:12px;">
            <span v-if="o.montant" style="font-size:13px;font-weight:700;color:#FFD200;">{{ formatEuro(o.montant) }}</span>
            <StatusBadge :status="o.status" />
            <span style="font-size:12px;color:#6B7280;">{{ o.date_label }}</span>
            <span style="color:#FFD200;font-size:12px;font-weight:600;">→</span>
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
    const raw = data?.['hydra:member'] ?? data?.member ?? (Array.isArray(data) ? data : [])
    ordres.value = raw.map(normalizeOrdre)
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.or-card-link:hover { border-color: rgba(255,210,0,0.3) !important; }
</style>
