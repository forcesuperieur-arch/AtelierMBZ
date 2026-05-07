<template>
  <div>
    <div class="flex items-center justify-between flex-wrap gap-3 mb-6">
      <div class="page-title">Ordres de réparation</div>
      <PitButton variant="ghost" size="sm" @click="exportCsv">
        <UIcon name="i-heroicons-arrow-down-tray" class="w-4 h-4 mr-1" />
        Export CSV
      </PitButton>
    </div>

    <!-- KPIs -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
      <PitCard padded hoverable>
        <div class="text-[10px] text-text-tertiary uppercase">Ouverts</div>
        <div class="text-[22px] font-bold text-warning">{{ kpis.open }}</div>
      </PitCard>
      <PitCard padded hoverable>
        <div class="text-[10px] text-text-tertiary uppercase">En atelier</div>
        <div class="text-[22px] font-bold text-accent">{{ kpis.live }}</div>
      </PitCard>
      <PitCard padded hoverable>
        <div class="text-[10px] text-text-tertiary uppercase">À réceptionner</div>
        <div class="text-[22px] font-bold text-success">{{ kpis.reception }}</div>
      </PitCard>
      <PitCard padded hoverable>
        <div class="text-[10px] text-text-tertiary uppercase">Finalisés</div>
        <div class="text-[22px] font-bold text-success">{{ kpis.completed }}</div>
      </PitCard>
    </div>

    <!-- Search + Filter -->
    <div class="flex gap-3 mb-4 flex-wrap">
      <PitInput v-model="search" placeholder="Rechercher OR, client, véhicule, plaque…" class="flex-1 min-w-[200px]" />
      <PitSelect v-model="statusFilter" class="w-[180px]">
        <option value="">Tous les statuts</option>
        <option value="reserve">Créneau réservé</option>
        <option value="confirme">Confirmé atelier</option>
        <option value="reception">Réception</option>
        <option value="en_cours">En cours</option>
        <option value="termine">Terminé</option>
        <option value="restitue">Restitué</option>
        <option value="facture">Facturé</option>
      </PitSelect>
    </div>

    <PitCard>
      <div v-if="loading" class="py-8 text-center text-text-tertiary">Chargement…</div>
      <div v-else-if="!filteredOrdres.length" class="py-8 text-center text-text-tertiary">
        <div class="text-4xl mb-2"><UIcon name="i-heroicons-clipboard-document-list" class="w-10 h-10 text-[var(--text-tertiary)]" /></div>
        <p>Aucun OR trouvé</p>
      </div>
      <div v-else class="flex flex-col gap-2.5">
        <div v-for="o in filteredOrdres" :key="o.id" class="cursor-pointer flex items-center justify-between p-3.5 rounded-xl border border-border-default bg-bg-surface hover:border-accent/30 transition-colors" @click="openOrModal(o.id)">
          <div class="flex items-center gap-3.5">
            <div class="text-sm font-bold text-accent min-w-[110px]">{{ o.numero_or }}</div>
            <div>
              <p class="font-semibold text-text-primary text-sm">{{ o.client_nom || '—' }}</p>
              <p class="text-xs text-text-tertiary">{{ o.vehicule_info }} {{ o.plaque ? '· ' + o.plaque : '' }}</p>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <span v-if="o.montant && !isMecanicien" class="text-sm font-bold text-accent">{{ formatEuro(o.montant) }}</span>
            <StatusBadge :status="o.status" />
            <span class="text-xs text-text-tertiary">{{ o.date_label }}</span>
            <UIcon name="i-heroicons-arrow-right" class="w-3 h-3 text-[var(--accent)]" />
          </div>
        </div>
      </div>
    </PitCard>
  </div>
  <OrDetailModal v-model:open="showOrModal" :ordre-id="selectedOrId || undefined" />
</template>

<script setup lang="ts">
const showOrModal = ref(false)
const selectedOrId = ref<string | number | null>(null)
function openOrModal(id: string | number) {
  selectedOrId.value = id
  showOrModal.value = true
}
const api = useApi()
const auth = useAuth()
const currentRole = computed(() => String(auth.user.value?.role || ''))
const roles = computed(() => auth.user.value?.roles ?? [])
const isMecanicien = computed(() => currentRole.value === 'mecanicien' || roles.value.includes('ROLE_MECANICIEN'))
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
  // API returns camelCase (rendezVous, dateRdv, etc.) — handle both for resilience
  const rdv = o.rendezVous ?? o.rendez_vous ?? {}
  const c = rdv.client
  const v = rdv.vehicule
  const dateRdv = rdv.dateRdv ?? rdv.date_rdv
  const year = dateRdv ? String(dateRdv).substring(0, 4) : String(new Date().getFullYear())
  return {
    ...o,
    numero_or: o.numeroOr ?? o.numero_or ?? `OR-${year}-${String(o.id || 0).padStart(3, '0')}`,
    client_nom: c ? `${c.prenom ?? ''} ${c.nom ?? ''}`.trim() : '',
    vehicule_info: v ? `${v.marque ?? ''} ${v.modele ?? ''}`.trim() : '',
    plaque: v?.plaque ?? '',
    status: rdv.statut ?? '',
    montant: isMecanicien.value ? null : (o.montantEstime ?? o.montant_estime ?? rdv.prixEstime ?? rdv.prix_estime ?? null),
    date_label: dateRdv ? new Date(dateRdv).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' }) : '',
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
