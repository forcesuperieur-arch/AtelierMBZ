<template>
  <div>
    <div class="page-header">
      <div>
        <div class="page-title">Stat</div>
        <div class="page-sub">{{ todayDate }} · Comparatifs de période, perf méca, charge et catégories de prestation.</div>
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

    <UCard style="margin-bottom:16px;">
      <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;">
        <div style="display:flex;flex-wrap:wrap;gap:8px;">
          <button
            v-for="preset in periodPresets"
            :key="preset.key"
            type="button"
            class="topbar-btn"
            :style="{ background: selectedPeriod === preset.key ? 'rgba(255,210,0,0.14)' : 'rgba(255,255,255,0.04)', color: selectedPeriod === preset.key ? '#FFD200' : '#D1D5DB', borderColor: selectedPeriod === preset.key ? 'rgba(255,210,0,0.3)' : 'rgba(255,255,255,0.08)' }"
            @click="applyPreset(preset.key)"
          >
            {{ preset.label }}
          </button>
        </div>

        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;">
          <input v-model="filters.from" type="date" style="padding:8px 10px;border-radius:10px;border:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.04);color:#E5E7EB;" />
          <input v-model="filters.to" type="date" style="padding:8px 10px;border-radius:10px;border:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.04);color:#E5E7EB;" />
          <button type="button" class="topbar-new-btn" style="font-size:12px;padding:8px 12px;" @click="loadDashboard">Actualiser</button>
        </div>
      </div>
      <div style="margin-top:8px;font-size:12px;color:#9CA3AF;">
        Période analysée : {{ periodSummary }} · comparée automatiquement à la période précédente équivalente.
      </div>
    </UCard>

    <div class="grid-4">
      <div class="stat-card">
        <div class="stat-label">RDV SUR PÉRIODE</div>
        <div class="stat-value">{{ Math.round(Number(comparison.rdvs?.current ?? 0)) }}</div>
        <div class="stat-delta" :style="{ color: metricDeltaColor(comparison.rdvs) }">{{ metricDeltaText(comparison.rdvs) }}</div>
        <div class="stat-bar"><div class="stat-bar-fill" :style="{ width: Math.min(Number(comparison.rdvs?.current ?? 0) / 40 * 100, 100) + '%', background: '#FFD200' }"></div></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">CA SUR PÉRIODE</div>
        <div class="stat-value">{{ formatCurrency(comparison.ca?.current ?? 0) }}</div>
        <div class="stat-delta" :style="{ color: metricDeltaColor(comparison.ca) }">{{ metricDeltaText(comparison.ca) }}</div>
        <div class="stat-bar"><div class="stat-bar-fill" :style="{ width: Math.min(Number(comparison.ca?.current ?? 0) / 25000 * 100, 100) + '%', background: '#14B8A6' }"></div></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">PANIER MOYEN</div>
        <div class="stat-value">{{ formatCurrency(comparison.avg_ticket?.current ?? 0) }}</div>
        <div class="stat-delta" :style="{ color: metricDeltaColor(comparison.avg_ticket) }">{{ metricDeltaText(comparison.avg_ticket) }}</div>
        <div class="stat-bar"><div class="stat-bar-fill" :style="{ width: Math.min(Number(comparison.avg_ticket?.current ?? 0) / 800 * 100, 100) + '%', background: '#8B5CF6' }"></div></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">OCCUPATION CAPACITÉ</div>
        <div class="stat-value">{{ Math.round(Number(comparison.occupation?.current ?? occupationRate)) }}%</div>
        <div class="stat-delta" :style="{ color: metricDeltaColor(comparison.occupation) }">{{ pontsOccupes }}/{{ ponts.length }} ponts occupés</div>
        <div class="stat-bar"><div class="stat-bar-fill" :style="{ width: Math.round(Number(comparison.occupation?.current ?? occupationRate)) + '%', background: '#10B981' }"></div></div>
      </div>
    </div>

    <div class="grid-4" style="margin-top:14px;">
      <div class="stat-card">
        <div class="stat-label">CHARGE PLANIFIÉE</div>
        <div class="stat-value">{{ formatDuration(comparison.planned_minutes?.current ?? plannedMinutes) }}</div>
        <div class="stat-delta" :style="{ color: metricDeltaColor(comparison.planned_minutes) }">{{ metricDeltaText(comparison.planned_minutes) }}</div>
        <div class="stat-bar"><div class="stat-bar-fill" :style="{ width: Math.min(Number(comparison.planned_minutes?.current ?? plannedMinutes) / 2400 * 100, 100) + '%', background: '#8B5CF6' }"></div></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">DOSSIERS CLÔTURÉS</div>
        <div class="stat-value">{{ Math.round(Number(comparison.completed?.current ?? stats.restitutions ?? completedToday)) }}</div>
        <div class="stat-delta" :style="{ color: metricDeltaColor(comparison.completed) }">{{ metricDeltaText(comparison.completed) }}</div>
        <div class="stat-bar"><div class="stat-bar-fill" :style="{ width: Math.min(Number(comparison.completed?.current ?? stats.restitutions ?? completedToday) / 30 * 100, 100) + '%', background: '#34D399' }"></div></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">ALERTES PILOTAGE</div>
        <div class="stat-value">{{ alerts.length }}</div>
        <div class="stat-delta" :style="{ color: alerts.length ? '#FCA5A5' : '#9CA3AF' }">⏰ retards, attentes, restitutions</div>
        <div class="stat-bar"><div class="stat-bar-fill" :style="{ width: Math.min(alerts.length / 6 * 100, 100) + '%', background: alerts.length ? '#EF4444' : '#6B7280' }"></div></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">IMPAYÉS / STOCK</div>
        <div class="stat-value">{{ stockModuleEnabled ? (stats.stock_alerts ?? stockAlertes.length) : (stats.impayees_count ?? 0) }}</div>
        <div class="stat-delta" style="color:#FBBF24;">{{ stockModuleEnabled ? '📦 pièces sous mini' : '💸 relances comptables' }}</div>
        <div class="stat-bar"><div class="stat-bar-fill" :style="{ width: Math.min((stockModuleEnabled ? (stats.stock_alerts ?? stockAlertes.length) : (stats.impayees_count ?? 0)) / 10 * 100, 100) + '%', background: '#FBBF24' }"></div></div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;margin:24px 0;">
      <UCard>
        <template #header>
          <span style="font-size:15px;font-weight:700;color:#E8E9ED;">Évolution sur la période</span>
        </template>
        <div v-if="dailyTrend.length" style="display:flex;align-items:flex-end;gap:8px;min-height:180px;overflow-x:auto;padding-top:6px;">
          <div v-for="item in dailyTrend" :key="item.date" style="min-width:44px;display:flex;flex-direction:column;align-items:center;gap:6px;">
            <span style="font-size:10px;color:#9CA3AF;">{{ item.rdvs }}</span>
            <div style="width:24px;height:120px;display:flex;align-items:flex-end;">
              <div :style="{ width: '100%', height: Math.max(10, Math.round(Number(item.rdvs ?? 0) / dailyTrendMax * 100)) + '%', borderRadius: '8px 8px 4px 4px', background: 'linear-gradient(180deg, #FFD200 0%, #F59E0B 100%)' }"></div>
            </div>
            <span style="font-size:10px;color:#6B7280;">{{ formatShortDay(item.date) }}</span>
          </div>
        </div>
        <AppEmptyState
          v-else
          icon="📉"
          title="Pas assez de volume"
          description="L’évolution se remplit automatiquement dès que les RDV sont historisés sur la période choisie."
        />
      </UCard>

      <UCard>
        <template #header>
          <span style="font-size:15px;font-weight:700;color:#E8E9ED;">Mix rentabilité atelier</span>
        </template>
        <div style="display:flex;flex-direction:column;gap:12px;">
          <div style="padding:12px;border-radius:12px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
            <div style="display:flex;justify-content:space-between;font-size:12px;color:#D1D5DB;margin-bottom:6px;">
              <span>Main d’œuvre</span>
              <span>{{ formatCurrency(revenueMix.mo_ht ?? 0) }}</span>
            </div>
            <div style="height:8px;border-radius:999px;background:rgba(255,255,255,0.06);overflow:hidden;"><div :style="{ width: mixShare(revenueMix.mo_ht ?? 0) + '%', height: '100%', background: '#10B981' }"></div></div>
          </div>
          <div style="padding:12px;border-radius:12px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
            <div style="display:flex;justify-content:space-between;font-size:12px;color:#D1D5DB;margin-bottom:6px;">
              <span>Pièces</span>
              <span>{{ formatCurrency(revenueMix.pieces_ht ?? 0) }}</span>
            </div>
            <div style="height:8px;border-radius:999px;background:rgba(255,255,255,0.06);overflow:hidden;"><div :style="{ width: mixShare(revenueMix.pieces_ht ?? 0) + '%', height: '100%', background: '#3B82F6' }"></div></div>
          </div>
          <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;">
            <div style="padding:10px 12px;border-radius:12px;background:rgba(255,255,255,0.03);">
              <div style="font-size:11px;color:#9CA3AF;">Factures période</div>
              <div style="font-size:18px;font-weight:700;color:#E8E9ED;">{{ revenueMix.nb_factures ?? 0 }}</div>
            </div>
            <div style="padding:10px 12px;border-radius:12px;background:rgba(255,255,255,0.03);">
              <div style="font-size:11px;color:#9CA3AF;">Total HT</div>
              <div style="font-size:18px;font-weight:700;color:#E8E9ED;">{{ formatCurrency(revenueMix.total_ht ?? 0) }}</div>
            </div>
          </div>
        </div>
      </UCard>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;margin:24px 0;">
      <UCard>
        <template #header>
          <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
            <span style="font-size:15px;font-weight:700;color:#E8E9ED;">Performance mécanos</span>
            <span class="badge-count">{{ mecanicienStats.length }}</span>
          </div>
        </template>
        <div v-if="mecanicienStats.length" style="display:flex;flex-direction:column;gap:10px;">
          <div v-for="meca in mecanicienStats.slice(0, 6)" :key="meca.id" style="padding:10px 12px;border-radius:12px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
              <div>
                <div style="font-size:13px;font-weight:700;color:#E8E9ED;">{{ meca.prenom }} {{ meca.nom }}</div>
                <div style="font-size:12px;color:#9CA3AF;">{{ meca.nb_rdvs }} interventions · {{ formatDuration(Number(meca.total_minutes || 0)) }} · {{ formatCurrency(meca.ca_genere || 0) }}</div>
              </div>
              <div style="text-align:right;">
                <div style="font-size:12px;color:#FFD200;font-weight:700;">{{ Math.round(Number(meca.avg_minutes || 0)) }} min</div>
                <div style="font-size:10px;color:#6B7280;">moyenne</div>
              </div>
            </div>
          </div>
        </div>
        <AppEmptyState
          v-else
          icon="👨‍🔧"
          title="Pas assez d’historique méca"
          description="La perf des mécaniciens se remplira au fil des interventions clôturées."
        />
      </UCard>

      <UCard>
        <template #header>
          <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
            <span style="font-size:15px;font-weight:700;color:#E8E9ED;">Catégories de prestations</span>
            <span class="badge-count">{{ topServices.length }}</span>
          </div>
        </template>
        <div v-if="topServices.length" style="display:flex;flex-direction:column;gap:10px;">
          <div v-for="service in topServices" :key="service.label" style="padding:10px 12px;border-radius:12px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
              <div>
                <div style="font-size:13px;font-weight:700;color:#E8E9ED;">{{ service.label }}</div>
                <div style="font-size:12px;color:#9CA3AF;">{{ service.count }} passages · {{ formatDuration(Number(service.minutes || 0)) }}</div>
              </div>
              <div style="text-align:right;">
                <div style="font-size:12px;color:#FFD200;font-weight:700;">{{ formatCurrency(service.revenue || 0) }}</div>
                <div style="font-size:10px;color:#6B7280;">potentiel</div>
              </div>
            </div>
          </div>
        </div>
        <AppEmptyState
          v-else
          icon="📈"
          title="Aucune catégorie dominante"
          description="Dès que l’atelier a du volume, tu vois ici les prestations qui pèsent vraiment dans l’activité."
        />
      </UCard>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;margin-bottom:24px;">
      <UCard>
        <template #header>
          <span style="font-size:15px;font-weight:700;color:#E8E9ED;">Répartition des statuts</span>
        </template>
        <div v-if="statusBreakdown.length" style="display:flex;flex-direction:column;gap:10px;">
          <div v-for="item in statusBreakdown" :key="item.label" style="display:grid;gap:4px;">
            <div style="display:flex;align-items:center;justify-content:space-between;font-size:12px;">
              <span style="color:#D1D5DB;">{{ item.label }}</span>
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
          description="La répartition des statuts apparaîtra dès qu’il y aura de l’activité atelier."
        />
      </UCard>

      <UCard>
        <template #header>
          <span style="font-size:15px;font-weight:700;color:#E8E9ED;">Occupation ressources atelier</span>
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
          description="La vue live des ponts s’affichera dès que la configuration atelier sera disponible."
        />
      </UCard>
    </div>

    <UCard>
      <template #header>
        <span style="font-size:15px;font-weight:700;color:#E8E9ED;">Synthèse pilotage</span>
      </template>

      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
        <div style="padding:12px;border-radius:12px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
          <div style="font-size:11px;color:#9CA3AF;">OR ouverts</div>
          <div style="font-size:22px;font-weight:700;color:#E8E9ED;">{{ stats.or_ouverts ?? 0 }}</div>
        </div>
        <div style="padding:12px;border-radius:12px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
          <div style="font-size:11px;color:#9CA3AF;">RDV en cours</div>
          <div style="font-size:22px;font-weight:700;color:#E8E9ED;">{{ stats.rdvs_en_cours ?? rdvsInProgress }}</div>
        </div>
        <div style="padding:12px;border-radius:12px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
          <div style="font-size:11px;color:#9CA3AF;">Restitutions période</div>
          <div style="font-size:22px;font-weight:700;color:#E8E9ED;">{{ stats.restitutions ?? completedToday }}</div>
        </div>
        <div style="padding:12px;border-radius:12px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
          <div style="font-size:11px;color:#9CA3AF;">Activité / jour</div>
          <div style="font-size:22px;font-weight:700;color:#E8E9ED;">{{ avgDailyRdvs }}</div>
        </div>
        <div style="padding:12px;border-radius:12px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
          <div style="font-size:11px;color:#9CA3AF;">Factures sur période</div>
          <div style="font-size:22px;font-weight:700;color:#E8E9ED;">{{ revenueMix.nb_factures ?? 0 }}</div>
        </div>
        <div style="padding:12px;border-radius:12px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
          <div style="font-size:11px;color:#9CA3AF;">Charge / pont</div>
          <div style="font-size:22px;font-weight:700;color:#E8E9ED;">{{ ponts.length ? formatDuration(Math.round(Number(comparison.planned_minutes?.current ?? 0) / ponts.length)) : '0 min' }}</div>
        </div>
      </div>
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
const mecanicienStats = ref<any[]>([])
const stockModuleEnabled = computed(() => atelierStore.isModuleEnabled('stock'))
const selectedPeriod = ref('30d')
const filters = reactive({ from: '', to: '' })
const periodPresets = [
  { key: 'today', label: "Aujourd'hui" },
  { key: '7d', label: '7 jours' },
  { key: '30d', label: '30 jours' },
  { key: '90d', label: '90 jours' },
]

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
  const total = Math.max(0, Number(minutes ?? 0))
  const hours = Math.floor(total / 60)
  const mins = total % 60
  if (!hours) return `${mins} min`
  if (!mins) return `${hours} h`
  return `${hours} h ${mins}`
}

function formatShortDay(value: string): string {
  const date = new Date(value)
  return date.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' })
}

function metricDeltaText(metric: any): string {
  const pct = Number(metric?.delta_pct ?? 0)
  const sign = pct > 0 ? '+' : ''
  return `${sign}${pct}% vs période préc.`
}

function metricDeltaColor(metric: any): string {
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

onMounted(() => {
  applyPreset('30d')
  refreshInterval = setInterval(loadDashboard, 60000)
})

onUnmounted(() => {
  if (refreshInterval) clearInterval(refreshInterval)
})

let refreshInterval: ReturnType<typeof setInterval> | null = null

async function loadDashboard() {
  try {
    const today = toIsoDate(new Date())
    const params = new URLSearchParams()
    if (filters.from) params.set('from', filters.from)
    if (filters.to) params.set('to', filters.to)
    if (!filters.from || !filters.to) params.set('period', selectedPeriod.value)
    const query = params.toString() ? `?${params.toString()}` : ''

    const [s, rdvData, alertes, pontsData, mecaData] = await Promise.all([
      api.get(`/statistiques/dashboard${query}`),
      api.get(`/rendez-vous?dateRdv[after]=${today}&dateRdv[before]=${today}&itemsPerPage=200`),
      stockModuleEnabled.value ? api.get('/stock/alertes').catch(() => []) : Promise.resolve([]),
      api.get('/ponts/status').catch(() => []),
      api.get(`/statistiques/mecaniciens${query}`).catch(() => []),
    ])
    stats.value = s
    const rawRdvs = rdvData?.['hydra:member'] ?? rdvData?.member ?? (Array.isArray(rdvData) ? rdvData : [])
    todayRdvs.value = rawRdvs.map(normalizeRdv)
    stockAlertes.value = alertes
    ponts.value = Array.isArray(pontsData) ? pontsData : (pontsData?.['hydra:member'] ?? pontsData?.member ?? [])
    mecanicienStats.value = Array.isArray(mecaData) ? mecaData : (mecaData?.['hydra:member'] ?? mecaData?.member ?? [])
  } finally {
    loading.value = false
  }
}
</script>
