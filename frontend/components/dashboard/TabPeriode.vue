<template>
  <div class="tab">
    <AppErrorState v-if="store.erreur.periode" :description="store.erreur.periode" @retry="store.loadPeriode()" />

    <template v-else>
      <!-- Quatre mesures, pas vingt-deux. Le CA et le panier moyen n'entrent
           dans la grille que si le module facturation est actif : afficher
           « 0 € » pour un module désactivé apprend à ignorer la page. -->
      <div class="tab-tiles">
        <DashboardStatTile
          label="Rendez-vous"
          :value="formatNombre(comparison.rdvs?.current ?? 0)"
          :comparison="comparison.rdvs"
          good-when="up"
          :hint="`${avgParJour} par jour ouvré en moyenne`"
        />
        <DashboardStatTile
          label="Dossiers clôturés"
          :value="formatNombre(comparison.completed?.current ?? 0)"
          :comparison="comparison.completed"
          good-when="up"
          :hint="tauxCloture !== null ? `${tauxCloture} % des RDV de la période` : undefined"
        />
        <DashboardStatTile
          label="Rendement"
          :value="rendement"
          unit="%"
          :hint="`temps pointé / temps estimé · cible ${cibleRendement} %`"
          :tone="rendement >= cibleRendement ? 'good' : (rendement > 0 ? 'warn' : 'neutral')"
          :meter="{ value: rendement, max: 100 }"
        />
        <DashboardStatTile
          label="Charge planifiée"
          :value="formatMinutes(comparison.planned_minutes?.current ?? 0)"
          :comparison="comparison.planned_minutes"
          good-when="none"
          :hint="chargeParPont"
        />
        <template v-if="hasFacturation">
          <DashboardStatTile
            label="Chiffre d'affaires"
            :value="formatEuro(comparison.ca?.current ?? 0)"
            :comparison="comparison.ca"
            good-when="up"
          />
          <DashboardStatTile
            label="Panier moyen"
            :value="formatEuro(comparison.avg_ticket?.current ?? 0)"
            :comparison="comparison.avg_ticket"
            good-when="up"
            :hint="`${revenueMix.nb_factures ?? 0} facture(s) sur la période`"
          />
        </template>
      </div>

      <!-- Une échelle par graphique. L'ancienne courbe superposait les RDV et
           le CA sur deux axes Y : l'alignement des deux échelles est arbitraire,
           donc la corrélation qu'on croit y lire n'existe pas. -->
      <div class="tab-grid">
        <DashboardSection
          title="Évolution des rendez-vous"
          :subtitle="`${periodeLisible} · un point par jour`"
        >
          <ChartsLineChart v-if="trend.length > 1" :data="trendRdvData" :options="OPTIONS_UNE_SERIE" />
          <AppEmptyState
            v-else
            icon="📉"
            title="Période trop courte"
            description="Choisis au moins deux jours pour voir une évolution."
          />
        </DashboardSection>

        <DashboardSection
          v-if="hasFacturation"
          title="Évolution du chiffre d'affaires"
          :subtitle="`${periodeLisible} · un point par jour`"
        >
          <ChartsLineChart v-if="trend.length > 1" :data="trendCaData" :options="OPTIONS_UNE_SERIE" />
        </DashboardSection>

        <DashboardSection
          title="Où en sont les dossiers"
          subtitle="Répartition des rendez-vous actifs par étape du parcours atelier."
        >
          <DashboardStageFunnel :rows="stats.active_by_status ?? []" />
        </DashboardSection>

        <DashboardSection
          title="Origine des rendez-vous"
          :subtitle="pctEnLigne !== null ? `${formatPourcent(pctEnLigne, 0)} des rendez-vous ont été pris en ligne.` : undefined"
        >
          <DashboardCategoryBars
            :items="origines"
            empty-icon="🌐"
            empty-title="Aucun rendez-vous sur la période"
            empty-description="L'origine est enregistrée à chaque prise de rendez-vous (web, comptoir, téléphone, devis)."
          />
        </DashboardSection>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
/**
 * Onglet « Période » — est-ce qu'on progresse ?
 * Comparatifs, évolution, structure de l'activité. Aucune donnée temps réel.
 */
const store = useDashboardStore()
const atelierStore = useAtelierStore()
const { formatMinutes, formatEuro, formatNombre, formatPourcent, formatJourCourt } = useDashboardFormat()

const hasFacturation = computed(() => atelierStore.isModuleEnabled('facturation'))

const stats = computed(() => store.stats ?? {})
const comparison = computed(() => stats.value.comparison ?? {})
const revenueMix = computed(() => stats.value.revenue_mix ?? {})
const trend = computed(() => Array.isArray(stats.value.daily_trend) ? stats.value.daily_trend : [])

const cibleRendement = computed(() => Number(store.thresholds?.rendement_target_percent ?? 85))
const rendement = computed(() => Math.round(Number(store.performance?.rendement_global?.ratio ?? 0)))

const avgParJour = computed(() => {
  const total = Number(comparison.value.rdvs?.current ?? 0)
  const jours = Number(stats.value.period?.days ?? 1) || 1
  return (total / jours).toFixed(1).replace('.', ',')
})

const tauxCloture = computed(() => {
  const total = Number(comparison.value.rdvs?.current ?? 0)
  if (!total) return null
  return Math.round(Number(comparison.value.completed?.current ?? 0) / total * 100)
})

const chargeParPont = computed(() => {
  const ponts = store.ponts.filter((p: any) => p.is_active !== 0 && p.is_active !== false).length
  if (!ponts) return undefined
  return `${formatMinutes(Number(comparison.value.planned_minutes?.current ?? 0) / ponts)} par pont`
})

const periodeLisible = computed(() => {
  const from = stats.value.period?.from ?? store.periode.from
  const to = stats.value.period?.to ?? store.periode.to
  if (!from || !to) return 'Période en cours'
  return `${new Date(from).toLocaleDateString('fr-FR')} → ${new Date(to).toLocaleDateString('fr-FR')}`
})

const pctEnLigne = computed(() => {
  const v = stats.value.pilote?.pct_rdv_en_ligne
  return v === null || v === undefined ? null : Number(v)
})

/**
 * Couleur attribuée par ENTITÉ et figée : « web » reste bleu même si le
 * comptoir passe devant. L'ordre des créneaux de la palette est fixe.
 */
const ORIGINES = [
  { key: 'web', label: 'En ligne', color: 'var(--viz-2)' },
  { key: 'comptoir', label: 'Comptoir', color: 'var(--viz-1)' },
  { key: 'telephone', label: 'Téléphone', color: 'var(--viz-3)' },
  { key: 'devis', label: 'Devis', color: 'var(--viz-4)' },
  { key: 'inconnu', label: 'Non renseignée', color: 'var(--viz-other)' },
]

const origines = computed(() => {
  const source = stats.value.pilote?.rdv_par_origine ?? {}
  return ORIGINES
    .map(o => ({ ...o, value: Number(source[o.key] ?? 0) }))
    .filter(o => o.value > 0)
})

/** Grille discrète, une seule échelle, pas de légende pour une série unique. */
const OPTIONS_UNE_SERIE = {
  plugins: { legend: { display: false } },
  scales: {
    x: { grid: { display: false }, ticks: { color: '#9CA3AF', font: { size: 10 }, maxTicksLimit: 8 } },
    y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#9CA3AF', font: { size: 10 } }, beginAtZero: true },
  },
}

const trendRdvData = computed(() => ({
  labels: trend.value.map((r: any) => formatJourCourt(r.date)),
  datasets: [{
    label: 'Rendez-vous',
    data: trend.value.map((r: any) => Number(r.rdvs ?? 0)),
    borderColor: '#FFD200',
    backgroundColor: 'rgba(255,210,0,0.12)',
    borderWidth: 2,
    pointRadius: 0,
    pointHoverRadius: 4,
    fill: true,
    tension: 0.3,
  }],
}))

const trendCaData = computed(() => ({
  labels: trend.value.map((r: any) => formatJourCourt(r.date)),
  datasets: [{
    label: 'Chiffre d\'affaires',
    data: trend.value.map((r: any) => Number(r.revenue ?? 0)),
    borderColor: '#0D9488',
    backgroundColor: 'rgba(13,148,136,0.12)',
    borderWidth: 2,
    pointRadius: 0,
    pointHoverRadius: 4,
    fill: true,
    tension: 0.3,
  }],
}))
</script>

<style scoped>
.tab { display: flex; flex-direction: column; gap: 16px; }
.tab-tiles {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
  gap: 12px;
}
.tab-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
  gap: 16px;
  /* Chaque carte prend la hauteur de son contenu : une carte à une ligne ne
     s'étire pas pour suivre sa voisine à cinq lignes. */
  align-items: start;
}
</style>
