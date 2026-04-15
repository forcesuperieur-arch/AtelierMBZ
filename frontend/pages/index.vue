<template>
  <div>
    <div class="page-header">
      <div>
        <div class="page-title">Vue d'ensemble</div>
        <div class="page-sub">{{ todayDate }}</div>
      </div>
    </div>

    <div v-if="blockedModuleBanner" style="margin-bottom:16px;border:1px solid rgba(245,158,11,0.2);border-radius:12px;padding:12px 14px;background:rgba(245,158,11,0.06);">
      <div style="font-size:13px;font-weight:700;color:#FBBF24;">{{ blockedModuleBanner.title }}</div>
      <div style="font-size:12px;color:#E5E7EB;margin-top:4px;">{{ blockedModuleBanner.description }}</div>
    </div>

    <!-- Alert strip -->
    <div v-if="alerts.length" class="alert-strip">
      <div v-for="(a, i) in alerts" :key="i" class="alert-chip" :class="a.type">
        <span class="alert-icon">{{ a.type === 'danger' ? '⚠️' : '⏰' }}</span>
        {{ a.text }}
      </div>
    </div>

    <!-- 4 Stat cards like legacy -->
    <div class="grid-4">
      <div class="stat-card">
        <div class="stat-label">RDV AUJOURD'HUI</div>
        <div class="stat-value">{{ stats.rdvs_today ?? 0 }}</div>
        <div class="stat-delta" style="color:#FFD200;">🏍️ {{ todayRdvs.length }} planifiés</div>
        <div class="stat-bar"><div class="stat-bar-fill" :style="{ width: Math.min((stats.rdvs_today ?? 0) / 10 * 100, 100) + '%', background: '#FFD200' }"></div></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">OR OUVERTS</div>
        <div class="stat-value">{{ stats.or_ouverts ?? 0 }}</div>
        <div class="stat-delta" style="color:#F59E0B;">🔧 En cours</div>
        <div class="stat-bar"><div class="stat-bar-fill" :style="{ width: Math.min((stats.or_ouverts ?? 0) / 10 * 100, 100) + '%', background: '#F59E0B' }"></div></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">TAUX D'OCCUPATION</div>
        <div class="stat-value">{{ occupationRate }}%</div>
        <div class="stat-delta" style="color:#10B981;">⚡ {{ pontsOccupes }}/{{ ponts.length }} ponts</div>
        <div class="stat-bar"><div class="stat-bar-fill" :style="{ width: occupationRate + '%', background: '#10B981' }"></div></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">RESTITUTIONS</div>
        <div class="stat-value">{{ stats.restitutions ?? 0 }}</div>
        <div class="stat-delta" style="color:#14B8A6;">📦 À restituer</div>
        <div class="stat-bar"><div class="stat-bar-fill" :style="{ width: Math.min((stats.restitutions ?? 0) / 8 * 100, 100) + '%', background: '#14B8A6' }"></div></div>
      </div>
    </div>

    <!-- Ponts status grid -->
    <div v-if="ponts.length" style="margin-bottom:24px;">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
        <span style="font-size:15px;font-weight:700;color:#E8E9ED;">PONTS</span>
        <span style="font-size:12px;color:#6B7280;">— État en temps réel</span>
      </div>
      <div class="pont-grid">
        <div v-for="pont in ponts" :key="pont.id" class="pont-card" :class="pont.current_rdv ? 'pont-occupe' : 'pont-libre'">
          <div class="pont-card-header">
            <div style="display:flex;align-items:center;gap:8px;">
              <span class="live-dot" :style="{ background: pont.current_rdv ? '#F59E0B' : '#10B981' }"></span>
              <span class="pont-name">{{ pont.nom }}</span>
            </div>
            <StatusBadge :status="pont.current_rdv ? 'en_cours' : (pont.est_actif === false ? 'annule' : 'confirme')" />
          </div>
          <div class="pont-card-body">
            <div v-if="pont.current_rdv">
              <p style="font-weight:600;color:#E8E9ED;font-size:14px;">{{ pont.current_rdv.vehicule_info }}</p>
              <p style="color:#6B7280;font-size:12px;">{{ pont.current_rdv.client_nom }} · {{ pont.current_rdv.type_intervention }}</p>
              <div v-if="pont.current_rdv.mecanicien_nom" style="margin-top:6px;display:flex;align-items:center;gap:6px;">
                <div style="width:22px;height:22px;border-radius:50%;background:#8B5CF6;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;">{{ pont.current_rdv.mecanicien_nom?.charAt(0) }}</div>
                <span style="font-size:12px;color:#9CA3AF;">{{ pont.current_rdv.mecanicien_nom }}</span>
              </div>
            </div>
            <p v-else style="color:#6B7280;font-size:13px;">Aucune intervention en cours</p>
          </div>
          <div class="pont-card-footer">
            Prochains : {{ pont.next_count ?? 0 }} RDV aujourd'hui
          </div>
        </div>
      </div>
    </div>

    <!-- Today's RDV table -->
    <UCard>
      <template #header>
        <div style="display:flex;align-items:center;justify-content:space-between;">
          <span style="font-size:15px;font-weight:700;color:#E8E9ED;">RDV du jour</span>
          <div style="display:flex;align-items:center;gap:12px;">
            <span class="badge-count">{{ todayRdvs.length }} rendez-vous</span>
            <NuxtLink to="/rdv/new" class="topbar-new-btn" style="font-size:12px;padding:6px 12px;">+ Nouveau RDV</NuxtLink>
          </div>
        </div>
      </template>

      <div v-if="loading" class="loading-shimmer" style="height:200px;"></div>

      <UTable
        v-else
        :data="todayRdvs"
        :columns="columns"
      >
        <template #status-cell="{ row }">
          <StatusBadge :status="row.original.status" />
        </template>
        <template #actions-cell="{ row }">
          <NuxtLink :to="`/rdv/${row.original.id}`" style="color:#FFD200;font-size:12px;font-weight:600;text-decoration:none;">
            Voir →
          </NuxtLink>
        </template>
      </UTable>
    </UCard>

    <!-- Stock alerts -->
    <UCard v-if="stockModuleEnabled && stockAlertes.length" style="margin-top:24px;border-color:rgba(239,68,68,0.2);">
      <template #header>
        <span style="font-size:15px;font-weight:700;color:#FCA5A5;">
          ⚠ Alertes stock ({{ stockAlertes.length }})
        </span>
      </template>
      <div style="display:flex;flex-direction:column;gap:8px;">
        <div v-for="p in stockAlertes" :key="p.id" style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;border-radius:10px;border:1px solid rgba(239,68,68,0.15);background:rgba(239,68,68,0.05);font-size:13px;">
          <span style="color:#D1D5DB;">{{ p.designation }} ({{ p.reference }})</span>
          <span class="badge-count" style="background:rgba(239,68,68,0.12);color:#FCA5A5;">Stock: {{ p.quantite_stock }}</span>
        </div>
      </div>
    </UCard>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const route = useRoute()
const atelierStore = useAtelierStore()
const loading = ref(true)
const stats = ref<any>({})
const todayRdvs = ref<any[]>([])
const stockAlertes = ref<any[]>([])
const ponts = ref<any[]>([])
const stockModuleEnabled = computed(() => atelierStore.isModuleEnabled('stock'))

const blockedModuleBanner = computed(() => {
  const key = String(route.query.moduleDisabled || '')
  if (!key) return null

  const labels: Record<string, string> = {
    stock: 'Stock',
    facturation: 'Facturation',
    devis: 'Devis',
    suivi: 'Suivi live',
    motos: 'Catalogue motos',
  }

  return {
    title: `${labels[key] || 'Ce module'} est désactivé pour cet atelier`,
    description: 'Réactive ce module dans la configuration atelier si tu veux le rendre à nouveau accessible.',
  }
})

const todayDate = computed(() => {
  return new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
})

const pontsOccupes = computed(() => ponts.value.filter(p => p.current_rdv).length)
const occupationRate = computed(() => {
  if (!ponts.value.length) return 0
  return Math.round(pontsOccupes.value / ponts.value.length * 100)
})

const alerts = computed(() => {
  const result: { type: string; text: string }[] = []
  const now = new Date()
  for (const rdv of todayRdvs.value) {
    if (rdv.status === 'en_cours' && rdv.temps_estime) {
      const started = rdv.heure_debut_travaux || rdv.started_at
      if (started) {
        const startTime = new Date(started)
        if (!isNaN(startTime.getTime())) {
          const elapsed = (now.getTime() - startTime.getTime()) / 60000
          const overrun = Math.round(elapsed - rdv.temps_estime)
          if (overrun > 10) {
            result.push({ type: 'danger', text: `🔧 ${rdv.vehicule_info} (${rdv.client_nom}) — dépassement +${overrun}min` })
          }
        }
      }
    }
    if (rdv.status === 'termine') {
      const finished = rdv.heure_fin_travaux || rdv.finished_at
      if (finished) {
        const finishedTime = new Date(finished)
        if (!isNaN(finishedTime.getTime())) {
          const waitingMin = Math.round((now.getTime() - finishedTime.getTime()) / 60000)
          if (waitingMin > 15) {
            result.push({ type: 'danger', text: `📦 ${rdv.vehicule_info} (${rdv.client_nom}) — restitution en attente +${waitingMin}min` })
          }
        }
      }
    }
    if (rdv.status === 'confirme' || rdv.status === 'reception') {
      const h = rdv.heure_debut?.split(':')
      if (h) {
        const scheduled = new Date()
        scheduled.setHours(Number(h[0]), Number(h[1]), 0)
        const diff = (now.getTime() - scheduled.getTime()) / 60000
        if (diff > 10) {
          result.push({ type: 'danger', text: `${rdv.heure_debut} — ${rdv.vehicule_info} (${rdv.client_nom}) en retard +${Math.round(diff)}m` })
        } else if (diff > -15 && diff <= 0) {
          result.push({ type: 'warning', text: `${rdv.heure_debut} — ${rdv.vehicule_info} arrive bientôt` })
        }
      }
    }
  }
  return result
})

const columns = [
  { key: 'heure_debut', label: 'Heure' },
  { key: 'vehicule_info', label: 'Véhicule' },
  { key: 'client_nom', label: 'Client' },
  { key: 'type_intervention', label: 'Opération' },
  { key: 'pont_nom', label: 'Pont' },
  { key: 'mecanicien_nom', label: 'Mécanicien' },
  { key: 'status', label: 'Statut' },
  { key: 'actions', label: '' },
]

function normalizeRdv(r: any) {
  const c = r.client
  const v = r.vehicule
  return {
    ...r,
    status: r.statut ?? r.status,
    client_nom: c ? `${c.prenom} ${c.nom}` : (r.client_nom ?? ''),
    vehicule_info: v ? `${v.marque} ${v.modele}` : (r.vehicule_info ?? ''),
    heure_debut: r.heure_rdv ?? r.heure_debut,
    mecanicien_nom: r.mecanicien ? `${r.mecanicien.prenom} ${r.mecanicien.nom}` : (r.mecanicien_nom ?? ''),
    pont_nom: r.pont?.nom ?? '',
  }
}

onMounted(async () => {
  await loadDashboard()
  refreshInterval = setInterval(loadDashboard, 30000)
})

onUnmounted(() => {
  if (refreshInterval) clearInterval(refreshInterval)
})

let refreshInterval: ReturnType<typeof setInterval> | null = null

async function loadDashboard() {
  try {
    const today = new Date().toISOString().slice(0, 10)
    const [s, rdvData, alertes, pontsData] = await Promise.all([
      api.get('/statistiques/dashboard'),
      api.get(`/rendez-vous?dateRdv[after]=${today}&dateRdv[before]=${today}&itemsPerPage=200`),
      stockModuleEnabled.value ? api.get('/stock/alertes').catch(() => []) : Promise.resolve([]),
      api.get('/ponts/status').catch(() => []),
    ])
    stats.value = s
    const rawRdvs = rdvData?.['hydra:member'] ?? rdvData?.member ?? (Array.isArray(rdvData) ? rdvData : [])
    todayRdvs.value = rawRdvs.map(normalizeRdv)
    stockAlertes.value = alertes
    ponts.value = Array.isArray(pontsData) ? pontsData : (pontsData?.['hydra:member'] ?? pontsData?.member ?? [])
  } finally {
    loading.value = false
  }
}
</script>
