<template>
  <div>
    <AppPageHeader
      title="Stat"
      :subtitle="`${todayDate} · Comparatifs de période, perf méca, charge et catégories de prestation.`"
    />

    <AppBanner
      v-if="blockedModuleBanner"
      variant="warning"
      :title="blockedModuleBanner.title"
      :description="blockedModuleBanner.description"
    />

    <!-- [PHASE-1.4] Bandeau visible quand certaines APIs n'ont pas répondu -->
    <AppBanner
      v-if="partialErrors.length"
      variant="danger"
      icon="⚠️"
      title="Données partielles"
    >
      Certaines sections n'ont pas pu être chargées : {{ partialErrors.join(', ') }}. Le reste du dashboard reste à jour.
    </AppBanner>

    <AppErrorState
      v-if="dashboardError && !partialErrors.length"
      title="Dashboard indisponible"
      :description="dashboardError"
      @retry="loadDashboard"
    />

    <!-- Alert banners dismissables -->
    <template v-for="(a, i) in alerts" :key="i">
      <div v-if="!dismissedAlerts.has(i)" class="alert-banner-item">
        <AppBanner
          :variant="a.type === 'danger' ? 'danger' : 'warning'"
          :icon="a.type === 'danger' ? '⚠️' : '⏰'"
          :title="a.text"
          dismissible
          @dismiss="dismissedAlerts.add(i)"
        />
      </div>
    </template>

    <AppPeriodSelector
      :presets="periodPresets"
      :model-value="{ from: filters.from, to: filters.to }"
      :selected-preset="selectedPeriod"
      @preset="applyPreset"
      @update:model-value="({ from, to }: any) => { filters.from = from; filters.to = to; }"
      @refresh="loadDashboard"
    >
      <template #summary>
        Période analysée : {{ periodSummary }} · comparée automatiquement à la période précédente équivalente.
      </template>
    </AppPeriodSelector>

    <template v-if="loading">
      <AppSkeletonKpi :count="4" />
      <div class="skeleton-sections">
        <AppSkeletonCard :lines="5" />
        <AppSkeletonCard :lines="5" />
      </div>
    </template>

    <template v-else>
      <div class="kpi-grid">
        <div class="stat-card">
          <div class="stat-label">RDV SUR PÉRIODE</div>
          <div class="stat-value">{{ Math.round(Number(comparison.rdvs?.current ?? 0)) }}</div>
          <div class="stat-delta" :style="{ color: metricDeltaColor(comparison.rdvs) }">{{ metricDeltaText(comparison.rdvs) }}</div>
          <div class="stat-bar"><div class="stat-bar-fill" :style="{ width: Math.min(Number(comparison.rdvs?.current ?? 0) / GAUGE_MAX_RDV * 100, 100) + '%', background: '#FFD200' }"></div></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">CA SUR PÉRIODE</div>
          <div class="stat-value">{{ formatCurrency(comparison.ca?.current ?? 0) }}</div>
          <div class="stat-delta" :style="{ color: metricDeltaColor(comparison.ca) }">{{ metricDeltaText(comparison.ca) }}</div>
          <div class="stat-bar"><div class="stat-bar-fill" :style="{ width: Math.min(Number(comparison.ca?.current ?? 0) / GAUGE_MAX_CA * 100, 100) + '%', background: '#14B8A6' }"></div></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">PANIER MOYEN</div>
          <div class="stat-value">{{ formatCurrency(comparison.avg_ticket?.current ?? 0) }}</div>
          <div class="stat-delta" :style="{ color: metricDeltaColor(comparison.avg_ticket) }">{{ metricDeltaText(comparison.avg_ticket) }}</div>
          <div class="stat-bar"><div class="stat-bar-fill" :style="{ width: Math.min(Number(comparison.avg_ticket?.current ?? 0) / GAUGE_MAX_PANIER * 100, 100) + '%', background: '#8B5CF6' }"></div></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">OCCUPATION CAPACITÉ</div>
          <div class="stat-value">{{ Math.round(Number(comparison.occupation?.current ?? occupationRate)) }}%</div>
          <div class="stat-delta" :style="{ color: metricDeltaColor(comparison.occupation) }">{{ pontsOccupes }}/{{ ponts.length }} ponts occupés</div>
          <div class="stat-bar"><div class="stat-bar-fill" :style="{ width: Math.round(Number(comparison.occupation?.current ?? occupationRate)) + '%', background: '#10B981' }"></div></div>
        </div>
      </div>

      <div class="kpi-grid" style="margin-top:14px;">
        <div class="stat-card">
          <div class="stat-label">CHARGE PLANIFIÉE</div>
          <div class="stat-value">{{ formatDuration(comparison.planned_minutes?.current ?? plannedMinutes) }}</div>
          <div class="stat-delta" :style="{ color: metricDeltaColor(comparison.planned_minutes) }">{{ metricDeltaText(comparison.planned_minutes) }}</div>
          <div class="stat-bar"><div class="stat-bar-fill" :style="{ width: Math.min(Number(comparison.planned_minutes?.current ?? plannedMinutes) / GAUGE_MAX_PLANNED_MIN * 100, 100) + '%', background: '#8B5CF6' }"></div></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">DOSSIERS CLÔTURÉS</div>
          <div class="stat-value">{{ Math.round(Number(comparison.completed?.current ?? stats.restitutions ?? completedToday)) }}</div>
          <div class="stat-delta" :style="{ color: metricDeltaColor(comparison.completed) }">{{ metricDeltaText(comparison.completed) }}</div>
          <div class="stat-bar"><div class="stat-bar-fill" :style="{ width: Math.min(Number(comparison.completed?.current ?? stats.restitutions ?? completedToday) / GAUGE_MAX_COMPLETED * 100, 100) + '%', background: '#34D399' }"></div></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">ALERTES PILOTAGE</div>
          <div class="stat-value">{{ alerts.length }}</div>
          <div class="stat-delta" :style="{ color: alerts.length ? '#FCA5A5' : '#9CA3AF' }">⏰ retards, attentes, restitutions</div>
          <div class="stat-bar"><div class="stat-bar-fill" :style="{ width: Math.min(alerts.length / GAUGE_MAX_ALERTS * 100, 100) + '%', background: alerts.length ? '#EF4444' : '#6B7280' }"></div></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">IMPAYÉS / STOCK</div>
          <div class="stat-value">{{ stockModuleEnabled ? (stats.stock_alerts ?? stockAlertes.length) : (stats.impayees_count ?? 0) }}</div>
          <div class="stat-delta" style="color:#FBBF24;">{{ stockModuleEnabled ? '📦 pièces sous mini' : '💸 relances comptables' }}</div>
          <div class="stat-bar"><div class="stat-bar-fill" :style="{ width: Math.min((stockModuleEnabled ? (stats.stock_alerts ?? stockAlertes.length) : (stats.impayees_count ?? 0)) / GAUGE_MAX_STOCK * 100, 100) + '%', background: '#FBBF24' }"></div></div>
        </div>
      </div>

      <div class="dashboard-toolbar">
        <UButton size="xs" variant="ghost" color="neutral" @click="expandAll">
          <UIcon name="i-heroicons-arrows-pointing-out" class="w-4 h-4 mr-1" />
          Voir tout
        </UButton>
        <UButton size="xs" variant="ghost" color="neutral" @click="collapseAll">
          <UIcon name="i-heroicons-arrows-pointing-in" class="w-4 h-4 mr-1" />
          Réduire tout
        </UButton>
      </div>

      <!-- Section Charts -->
      <UCard class="dashboard-section-card" style="margin-top:24px;">
        <div class="dashboard-section-header" @click="sectionStates.charts = !sectionStates.charts">
          <div class="dashboard-section-header-left">
            <UIcon name="i-heroicons-chart-bar" class="w-5 h-5" />
            <span class="font-semibold text-sm">Évolution et rentabilité</span>
          </div>
          <UIcon name="i-heroicons-chevron-down" class="dashboard-section-chevron" :class="{ rotated: !sectionStates.charts }" />
        </div>
        <div v-show="sectionStates.charts" style="margin-top:12px;">
          <div class="grid-auto">
            <UCard>
              <template #header>
                <span class="header-lg">Évolution sur la période</span>
              </template>
              <div v-if="dailyTrend.length" style="display:flex;align-items:flex-end;gap:8px;min-height:180px;overflow-x:auto;padding-top:6px;">
                <div v-for="item in dailyTrend" :key="item.date" style="min-width:44px;display:flex;flex-direction:column;align-items:center;gap:6px;">
                  <span class="text-xs-muted">{{ item.rdvs }}</span>
                  <div style="width:24px;height:120px;display:flex;align-items:flex-end;">
                    <div :style="{ width: '100%', height: Math.max(10, Math.round(Number(item.rdvs ?? 0) / dailyTrendMax * 100)) + '%', borderRadius: '8px 8px 4px 4px', background: 'linear-gradient(180deg, #FFD200 0%, #F59E0B 100%)' }"></div>
                  </div>
                  <span class="text-xs-subtle">{{ formatShortDay(item.date) }}</span>
                </div>
              </div>
              <AppEmptyState
                v-else
                icon="📉"
                title="Pas assez de volume"
                description="L'évolution se remplit automatiquement dès que les RDV sont historisés sur la période choisie."
              />
            </UCard>

            <UCard>
              <template #header>
                <span class="header-lg">Mix rentabilité atelier</span>
              </template>
              <div style="display:flex;flex-direction:column;gap:12px;">
                <div class="card-sm">
                  <div class="flex-between text-sm-value" style="margin-bottom:6px;">
                    <span>Main d'œuvre</span>
                    <span>{{ formatCurrency(revenueMix.mo_ht ?? 0) }}</span>
                  </div>
                  <div class="progress-track-xs"><div :style="{ width: mixShare(revenueMix.mo_ht ?? 0) + '%', height: '100%', background: '#10B981' }"></div></div>
                </div>
                <div class="card-sm">
                  <div class="flex-between text-sm-value" style="margin-bottom:6px;">
                    <span>Pièces</span>
                    <span>{{ formatCurrency(revenueMix.pieces_ht ?? 0) }}</span>
                  </div>
                  <div class="progress-track-xs"><div :style="{ width: mixShare(revenueMix.pieces_ht ?? 0) + '%', height: '100%', background: '#3B82F6' }"></div></div>
                </div>
                <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;">
                  <div class="card-sm">
                    <div class="text-sm-muted">Factures période</div>
                    <div class="stat-value-sm">{{ revenueMix.nb_factures ?? 0 }}</div>
                  </div>
                  <div class="card-sm">
                    <div class="text-sm-muted">Total HT</div>
                    <div class="stat-value-sm">{{ formatCurrency(revenueMix.total_ht ?? 0) }}</div>
                  </div>
                </div>
              </div>
            </UCard>
          </div>
        </div>
      </UCard>

      <!-- Section Perf -->
      <UCard class="dashboard-section-card" style="margin-top:24px;">
        <div class="dashboard-section-header" @click="sectionStates.perf = !sectionStates.perf">
          <div class="dashboard-section-header-left">
            <UIcon name="i-heroicons-wrench-screwdriver" class="w-5 h-5" />
            <span class="font-semibold text-sm">Performance et prestations</span>
            <UBadge v-if="mecanicienStats.length + topServices.length" size="xs" color="info">{{ mecanicienStats.length + topServices.length }}</UBadge>
          </div>
          <UIcon name="i-heroicons-chevron-down" class="dashboard-section-chevron" :class="{ rotated: !sectionStates.perf }" />
        </div>
        <div v-show="sectionStates.perf" style="margin-top:12px;">
          <div class="grid-auto">
            <UCard>
              <template #header>
                <div class="flex-between-wrap flex-wrap-gap-12">
                  <span class="header-lg">Performance mécanos</span>
                  <span class="badge-count">{{ mecanicienStats.length }}</span>
                </div>
              </template>
              <div v-if="mecanicienStats.length" class="flex-col-10">
                <div v-for="meca in mecanicienStats.slice(0, 6)" :key="meca.id" class="card-sm">
                  <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
                    <div>
                      <div class="text-lg-primary">{{ meca.prenom }} {{ meca.nom }}</div>
                      <div class="text-md-muted">{{ meca.nb_rdvs }} interventions · {{ formatDuration(Number(meca.total_minutes || 0)) }} · {{ formatCurrency(meca.ca_genere || 0) }}</div>
                    </div>
                    <div class="text-right">
                      <div class="text-md-muted text-warning" style="font-weight:700;">{{ Math.round(Number(meca.avg_minutes || 0)) }} min</div>
                      <div class="text-xs-subtle">moyenne</div>
                    </div>
                  </div>
                </div>
              </div>
              <AppEmptyState
                v-else
                icon="👨‍🔧"
                title="Pas assez d'historique méca"
                description="La perf des mécaniciens se remplira au fil des interventions clôturées."
              />
            </UCard>

            <UCard>
              <template #header>
                <div class="flex-between-wrap flex-wrap-gap-12">
                  <span class="header-lg">Catégories de prestations</span>
                  <span class="badge-count">{{ topServices.length }}</span>
                </div>
              </template>
              <div v-if="topServices.length" class="flex-col-10">
                <div v-for="service in topServices" :key="service.label" class="card-sm">
                  <div class="flex-between-wrap flex-wrap-gap-12">
                    <div>
                      <div class="text-lg-primary">{{ service.label }}</div>
                      <div class="text-md-muted">{{ service.count }} passages · {{ formatDuration(Number(service.minutes || 0)) }}</div>
                    </div>
                    <div class="text-right">
                      <div class="text-md-muted text-warning" style="font-weight:700;">{{ formatCurrency(service.revenue || 0) }}</div>
                      <div class="text-xs-subtle">potentiel</div>
                    </div>
                  </div>
                </div>
              </div>
              <AppEmptyState
                v-else
                icon="📈"
                title="Aucune catégorie dominante"
                description="Dès que l'atelier a du volume, tu vois ici les prestations qui pèsent vraiment dans l'activité."
              />
            </UCard>
          </div>
        </div>
      </UCard>

      <!-- Section Charge -->
      <UCard class="dashboard-section-card" style="margin-top:24px;">
        <div class="dashboard-section-header" @click="sectionStates.charge = !sectionStates.charge">
          <div class="dashboard-section-header-left">
            <UIcon name="i-heroicons-signal" class="w-5 h-5" />
            <span class="font-semibold text-sm">Occupation et statuts</span>
          </div>
          <UIcon name="i-heroicons-chevron-down" class="dashboard-section-chevron" :class="{ rotated: !sectionStates.charge }" />
        </div>
        <div v-show="sectionStates.charge" style="margin-top:12px;">
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;">
            <UCard>
              <template #header>
                <span class="header-lg">Répartition des statuts</span>
              </template>
              <div v-if="statusBreakdown.length" class="flex-col-10">
                <div v-for="item in statusBreakdown" :key="item.label" style="display:grid;gap:4px;">
                  <div style="display:flex;align-items:center;justify-content:space-between;font-size:12px;">
                    <span class="text-value">{{ item.label }}</span>
                    <span style="color:#9CA3AF;">{{ item.count }}</span>
                  </div>
                  <div style="height:7px;border-radius:999px;background:rgba(255,255,255,0.06);overflow:hidden;">
                    <div :style="{ width: item.percent + '%', height: '100%', background: item.color }"></div>
                  </div>
                </div>
              </div>
              <AppEmptyState
                v-else
                icon="📊"
                title="Pas de flux à afficher"
                description="La répartition des statuts apparaîtra dès qu'il y aura de l'activité atelier."
              />
            </UCard>

            <UCard>
              <template #header>
                <span class="header-lg">Occupation ressources atelier</span>
              </template>
              <div v-if="ponts.length" class="pont-grid" style="margin:0;">
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
                    </div>
                    <p v-else style="color:#6B7280;font-size:13px;">Aucune intervention en cours</p>
                  </div>
                  <div class="pont-card-footer">{{ pont.next_count ?? 0 }} RDV restants aujourd'hui</div>
                </div>
              </div>
              <AppEmptyState
                v-else
                icon="🔧"
                title="Aucun pont remonté"
                description="La vue live des ponts s'affichera dès que la configuration atelier sera disponible."
              />
            </UCard>
          </div>
        </div>
      </UCard>

      <!-- Section Synthèse -->
      <UCard class="dashboard-section-card" style="margin-top:24px;">
        <div class="dashboard-section-header" @click="sectionStates.planning = !sectionStates.planning">
          <div class="dashboard-section-header-left">
            <UIcon name="i-heroicons-clipboard-document-list" class="w-5 h-5" />
            <span class="font-semibold text-sm">Synthèse pilotage</span>
          </div>
          <UIcon name="i-heroicons-chevron-down" class="dashboard-section-chevron" :class="{ rotated: !sectionStates.planning }" />
        </div>
        <div v-show="sectionStates.planning" style="margin-top:12px;">
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
            <div class="card-sm">
              <div class="text-sm-muted">OR ouverts</div>
              <div class="stat-value-md">{{ stats.or_ouverts ?? 0 }}</div>
            </div>
            <div class="card-sm">
              <div class="text-sm-muted">RDV en cours</div>
              <div class="stat-value-md">{{ stats.rdvs_en_cours ?? rdvsInProgress }}</div>
            </div>
            <div class="card-sm">
              <div class="text-sm-muted">Restitutions période</div>
              <div class="stat-value-md">{{ stats.restitutions ?? completedToday }}</div>
            </div>
            <div class="card-sm">
              <div class="text-sm-muted">Activité / jour</div>
              <div class="stat-value-md">{{ avgDailyRdvs }}</div>
            </div>
            <div class="card-sm">
              <div class="text-sm-muted">Factures sur période</div>
              <div class="stat-value-md">{{ revenueMix.nb_factures ?? 0 }}</div>
            </div>
            <div class="card-sm">
              <div class="text-sm-muted">Charge / pont</div>
              <div class="stat-value-md">{{ ponts.length ? formatDuration(Math.round(Number(comparison.planned_minutes?.current ?? 0) / ponts.length)) : '0 min' }}</div>
            </div>
          </div>
        </div>
      </UCard>

      <!-- Section Stock alerts -->
      <UCard v-if="stockModuleEnabled && stockAlertes.length" class="dashboard-section-card" style="margin-top:24px;">
        <div class="dashboard-section-header" @click="sectionStates.alerts = !sectionStates.alerts">
          <div class="dashboard-section-header-left">
            <UIcon name="i-heroicons-exclamation-triangle" class="w-5 h-5" />
            <span class="font-semibold text-sm">Alertes stock</span>
            <UBadge size="xs" color="error">{{ stockAlertes.length }}</UBadge>
          </div>
          <UIcon name="i-heroicons-chevron-down" class="dashboard-section-chevron" :class="{ rotated: !sectionStates.alerts }" />
        </div>
        <div v-show="sectionStates.alerts" style="margin-top:12px;">
          <div style="display:flex;flex-direction:column;gap:8px;">
            <div v-for="p in stockAlertes" :key="p.id" style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;border-radius:10px;border:1px solid rgba(239,68,68,0.15);background:rgba(239,68,68,0.05);font-size:13px;">
              <span class="text-value">{{ p.designation }} ({{ p.reference }})</span>
              <span class="badge-count" style="background:rgba(239,68,68,0.12);color:#FCA5A5;">Stock: {{ p.quantite_stock }}</span>
            </div>
          </div>
        </div>
      </UCard>
    </template>
  </div>
</template>

<script setup lang="ts">
interface MetricDelta {
  delta_pct?: number | string
  delta?: number | string
}

interface RdvItem {
  client?: { prenom?: string; nom?: string }
  vehicule?: { marque?: string; modele?: string }
  statut?: string
  status?: string
  client_nom?: string
  vehicule_info?: string
  heure_rdv?: string
  heure_debut?: string
  mecanicien?: { prenom?: string; nom?: string }
  mecanicien_nom?: string
  pont?: { nom?: string }
  pont_nom?: string
  type_intervention?: string
  temps_estime?: number
}

const api = useApi()
const route = useRoute()
const atelierStore = useAtelierStore()
const loading = ref(true)
const stats = ref<any>({})
const todayRdvs = ref<any[]>([])
const stockAlertes = ref<any[]>([])
const ponts = ref<any[]>([])
const mecanicienStats = ref<any[]>([])
const stockModuleEnabled = computed(() => atelierStore.isModuleEnabled('stock'))
const selectedPeriod = ref('30d')
const filters = reactive({ from: '', to: '' })
const dashboardError = ref('')
const periodPresets = [
  { key: 'today', label: "Aujourd'hui" },
  { key: '7d', label: '7 jours' },
  { key: '30d', label: '30 jours' },
  { key: '90d', label: '90 jours' },
]

const dismissedAlerts = ref(new Set<number>())

const sectionStates = ref<Record<string, boolean>>({
  alerts: true,
  planning: true,
  charts: false,
  perf: false,
  prestations: false,
  topMeca: false,
  charge: false,
  categories: false,
})

const STORAGE_KEY = 'dashboard_sections_state'

watch(sectionStates, (val) => {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(val))
}, { deep: true })

function expandAll() {
  Object.keys(sectionStates.value).forEach(k => sectionStates.value[k] = true)
}
function collapseAll() {
  Object.keys(sectionStates.value).forEach(k => sectionStates.value[k] = false)
}

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

const comparison = computed(() => stats.value?.comparison ?? {})

// Gauge maximums — cosmetic targets for progress bars, adjustable per business
const GAUGE_MAX_RDV = 40
const GAUGE_MAX_CA = 25000
const GAUGE_MAX_PANIER = 800
const GAUGE_MAX_PLANNED_MIN = 2400
const GAUGE_MAX_COMPLETED = 30
const GAUGE_MAX_ALERTS = 6
const GAUGE_MAX_STOCK = 10

const revenueMix = computed(() => stats.value?.revenue_mix ?? {})
const dailyTrend = computed(() => Array.isArray(stats.value?.daily_trend) ? stats.value.daily_trend : [])
const dailyTrendMax = computed(() => Math.max(1, ...dailyTrend.value.map((item: any) => Number(item.rdvs ?? 0))))
const periodSummary = computed(() => {
  const from = stats.value?.period?.from ?? filters.from
  const to = stats.value?.period?.to ?? filters.to
  if (!from || !to) return 'Période en cours'
  return `${new Date(from).toLocaleDateString('fr-FR')} → ${new Date(to).toLocaleDateString('fr-FR')}`
})
const avgDailyRdvs = computed(() => {
  const total = Number(comparison.value?.rdvs?.current ?? 0)
  const days = Number(stats.value?.period?.days ?? 1) || 1
  return (total / days).toFixed(1)
})

const pontsOccupes = computed(() => ponts.value.filter(p => p.current_rdv).length)
const occupationRate = computed(() => {
  if (!ponts.value.length) return 0
  return Math.round(pontsOccupes.value / ponts.value.length * 100)
})
const rdvsInProgress = computed(() => todayRdvs.value.filter(r => ['reception', 'en_cours'].includes(r.status)).length)
const completedToday = computed(() => todayRdvs.value.filter(r => ['termine', 'restitue', 'facture', 'paye'].includes(r.status)).length)
const plannedMinutes = computed(() => Number(stats.value?.planned_minutes_today ?? todayRdvs.value.reduce((sum, rdv) => sum + Number(rdv.temps_estime ?? 60), 0)))

const statusBreakdown = computed(() => {
  const rows = Array.isArray(stats.value?.active_by_status) ? stats.value.active_by_status : []
  const total = rows.reduce((sum: number, row: any) => sum + Number(row.count ?? 0), 0) || 1
  const colors: Record<string, string> = {
    en_attente: '#6B7280',
    reserve: '#60A5FA',
    confirme: '#FBBF24',
    reception: '#A78BFA',
    en_cours: '#F59E0B',
    termine: '#34D399',
    restitue: '#14B8A6',
    facture: '#22C55E',
    paye: '#10B981',
    annule: '#EF4444',
  }

  return rows
    .map((row: any) => ({
      label: String(row.statut ?? 'atelier').replaceAll('_', ' '),
      count: Number(row.count ?? 0),
      percent: Math.round(Number(row.count ?? 0) / total * 100),
      color: colors[String(row.statut ?? '')] ?? '#9CA3AF',
    }))
    .sort((a: any, b: any) => b.count - a.count)
})

const topServices = computed(() => {
  const source = Array.isArray(stats.value?.top_services) ? stats.value.top_services : []
  if (source.length) {
    return source.map((item: any, index: number) => ({
      label: item.label || 'Atelier',
      count: Number(item.count ?? 0),
      minutes: Number(item.minutes ?? 0),
      revenue: Number(item.revenue ?? 0),
      rank: index + 1,
    }))
  }

  const grouped = new Map<string, { label: string; count: number; minutes: number }>()
  for (const rdv of todayRdvs.value) {
    const label = rdv.type_intervention || 'Atelier'
    const existing = grouped.get(label) ?? { label, count: 0, minutes: 0 }
    existing.count += 1
    existing.minutes += Number(rdv.temps_estime ?? 60)
    grouped.set(label, existing)
  }

  return Array.from(grouped.values())
    .sort((a, b) => b.count - a.count || b.minutes - a.minutes)
    .slice(0, 5)
    .map((item, index) => ({ ...item, rank: index + 1 }))
})

function formatCurrency(value: number | string): string {
  const amount = Number(value ?? 0)
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(amount)
}

function formatDuration(minutes: number | string): string {
  const total = Math.max(0, Math.round(Number(minutes ?? 0)))
  const h = Math.floor(total / 60)
  const m = total % 60
  return `${h}:${String(m).padStart(2, '0')}`
}

function formatShortDay(value: string): string {
  const date = new Date(value)
  return date.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' })
}

function metricDeltaText(metric: MetricDelta): string {
  const pct = Number(metric?.delta_pct ?? 0)
  const sign = pct > 0 ? '+' : ''
  return `${sign}${pct}% vs période préc.`
}

function metricDeltaColor(metric: MetricDelta): string {
  return Number(metric?.delta ?? 0) >= 0 ? '#34D399' : '#FCA5A5'
}

function mixShare(value: number | string): number {
  const total = Number(revenueMix.value?.total_ht ?? 0)
  if (!total) return 0
  return Math.min(100, Math.round(Number(value ?? 0) / total * 100))
}

function toIsoDate(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function applyPreset(key: string) {
  selectedPeriod.value = key
  const end = new Date()
  const start = new Date()

  if (key === 'today') {
    // same day
  } else if (key === '7d') {
    start.setDate(start.getDate() - 6)
  } else if (key === '90d') {
    start.setDate(start.getDate() - 89)
  } else {
    start.setDate(start.getDate() - 29)
  }

  filters.from = toIsoDate(start)
  filters.to = toIsoDate(end)
  loadDashboard()
}

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
            result.push({ type: 'danger', text: `🔧 ${rdv.vehicule_info} (${rdv.client_nom}) — dépassement +${formatDuration(overrun)}` })
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
            result.push({ type: 'danger', text: `📦 ${rdv.vehicule_info} (${rdv.client_nom}) — restitution en attente +${formatDuration(waitingMin)}` })
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

function normalizeRdv(r: RdvItem) {
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

onMounted(() => {
  try {
    const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}')
    Object.assign(sectionStates.value, saved)
  } catch { /* ignore */ }
  applyPreset('30d')
  refreshInterval = setInterval(loadDashboard, 60000)
})

onUnmounted(() => {
  if (refreshInterval) clearInterval(refreshInterval)
})

let refreshInterval: ReturnType<typeof setInterval> | null = null

// [PHASE-1.4] Tracking des erreurs API partielles pour bandeau visible
const partialErrors = ref<string[]>([])

async function loadDashboard() {
  dashboardError.value = ''
  try {
    const today = toIsoDate(new Date())
    const params = new URLSearchParams()
    if (filters.from) params.set('from', filters.from)
    if (filters.to) params.set('to', filters.to)
    if (!filters.from || !filters.to) params.set('period', selectedPeriod.value)
    const query = params.toString() ? `?${params.toString()}` : ''

    const issues: string[] = []
    const trackError = (label: string) => (e: unknown) => { issues.push(label); return [] }

    const [s, rdvData, alertes, pontsData, mecaData] = await Promise.all([
      api.get(`/statistiques/dashboard${query}`),
      api.get(`/rendez-vous?dateRdv[after]=${today}&dateRdv[before]=${today}&itemsPerPage=200`),
      stockModuleEnabled.value ? api.get('/stock/alertes').catch(trackError('alertes stock')) : Promise.resolve([]),
      api.get('/ponts/status').catch(trackError('ponts')),
      api.get(`/statistiques/mecaniciens${query}`).catch(trackError('perf mécanos')),
    ])
    stats.value = s
    const rawRdvs = rdvData?.['hydra:member'] ?? rdvData?.member ?? (Array.isArray(rdvData) ? rdvData : [])
    todayRdvs.value = rawRdvs.map(normalizeRdv)
    stockAlertes.value = alertes
    ponts.value = Array.isArray(pontsData) ? pontsData : (pontsData?.['hydra:member'] ?? pontsData?.member ?? [])
    mecanicienStats.value = Array.isArray(mecaData) ? mecaData : (mecaData?.['hydra:member'] ?? mecaData?.member ?? [])
    partialErrors.value = issues
  } catch (e: unknown) {
    partialErrors.value = []
    dashboardError.value = (e instanceof Error ? e.message : 'Erreur inconnue') || "Le dashboard n'a pas pu être chargé. Vérifie la connexion API puis réessaie."
  } finally {
    loading.value = false
  }
}
</script>
