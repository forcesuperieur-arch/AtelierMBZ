<template>
  <div class="tab">
    <AppErrorState v-if="store.erreur.analyse" :description="store.erreur.analyse" @retry="store.loadAnalyse()" />

    <template v-else>
      <!-- Un seul message quand il n'y a rien, au lieu de huit cartes vides
           empilées comme dans l'ancienne version. -->
      <AppEmptyState
        v-if="!aQuelqueChose"
        icon="i-ri-folders-line"
        title="Pas encore assez d'historique"
        description="Cet onglet se remplit dès que des interventions sont clôturées sur la période choisie."
      />

      <div v-else class="tab-grid">
        <DashboardSection
          v-if="mecanos.length"
          title="Activité par mécanicien"
          :count="mecanos.length"
          :subtitle="`Sur ${periodeLisible}. ${hasFacturation ? 'Classé par CA généré.' : 'Classé par nombre d’interventions.'}`"
        >
          <DashboardRankedList :rows="mecanos" />
        </DashboardSection>

        <DashboardSection
          v-if="ecarts.length"
          title="Écart temps pointé / estimé"
          :count="ecarts.length"
          :subtitle="`Un écart positif signifie que l'intervention a pris plus de temps que prévu (seuil d'alerte : +${seuilEcart} min).`"
        >
          <DashboardRankedList :rows="ecarts" />
        </DashboardSection>

        <DashboardSection
          v-if="derives.length"
          title="Délais et productivité"
          subtitle="Indicateurs de fluidité de l'atelier sur la période."
        >
          <div class="derives">
            <div v-for="d in derives" :key="d.label" class="derive">
              <div class="derive-head">
                <span class="derive-label">{{ d.label }}</span>
                <span class="derive-value" :class="`derive-value--${d.tone}`">{{ d.valeur }}</span>
              </div>
              <DashboardMeter :value="d.pct" :max="100" :tone="d.tone" :label="d.repere" />
            </div>
          </div>
        </DashboardSection>

        <DashboardSection
          v-if="prestations.length"
          title="Prestations les plus demandées"
          :count="prestations.length"
          :subtitle="`Sur ${periodeLisible}.`"
        >
          <DashboardRankedList :rows="prestations" />
        </DashboardSection>

        <DashboardSection
          v-if="segments.length"
          title="Segments de clientèle"
          subtitle="Paliers de fidélité — une seule teinte, du plus récent au plus fidèle."
        >
          <DashboardCategoryBars :items="segments" />
        </DashboardSection>

        <!-- Instrumentation du pilote : par quel canal les clients valident les
             travaux supplémentaires, et en combien de temps. -->
        <DashboardSection
          v-if="canauxDecision.length"
          title="Validation des travaux supplémentaires"
          :subtitle="sousTitreDecisions"
        >
          <DashboardCategoryBars :items="canauxDecision" />
        </DashboardSection>

        <DashboardSection
          v-if="hasFacturation && typesRentables.length"
          title="Rentabilité par type d'intervention"
          :count="typesRentables.length"
        >
          <DashboardRankedList :rows="typesRentables" />
        </DashboardSection>
      </div>

      <!-- Exploration libre : conservée, elle rend un vrai service quand on
           cherche une réponse qui n'est pas déjà dans une carte. -->
      <DashboardSection
        title="Exploration libre"
        subtitle="Croise une dimension avec une ou plusieurs mesures sur la période choisie."
      >
        <div class="explore-controls">
          <label class="field">
            <span class="field-label">Dimension</span>
            <select v-model="dimension" class="field-input">
              <option value="type_intervention">Type d'intervention</option>
              <option value="statut_rdv">Statut du RDV</option>
              <option value="mecanicien_nom">Mécanicien</option>
              <option value="client_segment">Segment client</option>
              <option value="vehicule_marque">Marque du véhicule</option>
              <option value="pont_nom">Pont</option>
            </select>
          </label>

          <fieldset class="field field--metrics">
            <legend class="field-label">Mesures</legend>
            <div class="metrics-row">
              <label v-for="m in metricOptions" :key="m.value" class="metric">
                <input v-model="metrics" type="checkbox" :value="m.value" >
                <span>{{ m.label }}</span>
              </label>
            </div>
          </fieldset>

          <button type="button" class="btn btn-primary explore-go" @click="lancerExploration">Explorer</button>
        </div>

        <DashboardRankedList
          v-if="exploreRows.length"
          :rows="exploreRows"
          :limit="8"
        />
        <p v-else class="explore-hint">
          Choisis une dimension et au moins une mesure, puis clique sur Explorer.
        </p>
      </DashboardSection>

      <DashboardSection
        v-if="hasFacturation"
        title="Prévision de chiffre d'affaires"
        subtitle="Projection à partir de l'historique récent — indicative, pas un engagement."
      >
        <template #actions>
          <span class="horizon-label">Horizon</span>
          <button
            v-for="d in [7, 14, 30]"
            :key="d"
            type="button"
            class="horizon"
            :class="{ 'horizon--on': horizon === d }"
            @click="changerHorizon(d)"
          >
            {{ d }} j
          </button>
        </template>
        <ChartsLineChart
          v-if="store.forecast.historical.length || store.forecast.forecast.length"
          :data="forecastData"
          :options="OPTIONS_FORECAST"
        />
        <AppEmptyState
          v-else
          icon="i-ri-magic-line"
          title="Historique insuffisant"
          description="La prévision demande au moins sept jours de données facturées."
        />
      </DashboardSection>
    </template>
  </div>
</template>

<script setup lang="ts">
/**
 * Onglet « Analyse » — pourquoi les chiffres sont ce qu'ils sont.
 * Consulté à la semaine ou au mois, pas plusieurs fois par jour : c'est
 * précisément ce qui n'avait rien à faire sur l'écran d'accueil.
 */
/** Forme d'une ligne de DashboardRankedList. */
interface RankedRow {
  key?: string | number
  label: string
  sub?: string
  value: string
  valueSub?: string
  tone?: 'neutral' | 'good' | 'warn'
}

const store = useDashboardStore()
const atelierStore = useAtelierStore()
const { formatMinutes, formatEuro, formatNombre, formatPourcent } = useDashboardFormat()

const hasFacturation = computed(() => atelierStore.isModuleEnabled('facturation'))
const stats = computed(() => store.stats ?? {})
const seuilEcart = computed(() => Number(store.thresholds?.overrun_warning_minutes ?? 15))
const cibleRendement = computed(() => Number(store.thresholds?.rendement_target_percent ?? 85))

const periodeLisible = computed(() => {
  const from = stats.value.period?.from ?? store.periode.from
  const to = stats.value.period?.to ?? store.periode.to
  if (!from || !to) return 'la période en cours'
  return `${new Date(from).toLocaleDateString('fr-FR')} → ${new Date(to).toLocaleDateString('fr-FR')}`
})

// ── Mécaniciens : on n'affiche que ceux qui ont travaillé sur la période.
// L'ancienne page listait les 40 mécaniciens de la base, dont 36 à zéro.
const mecanos = computed<RankedRow[]>(() => {
  const rows = Array.isArray(stats.value.mecaniciens) ? stats.value.mecaniciens : []
  return rows
    .filter((m: any) => Number(m.nb_rdvs ?? 0) > 0)
    .sort((a: any, b: any) => hasFacturation.value
      ? Number(b.ca_genere ?? 0) - Number(a.ca_genere ?? 0)
      : Number(b.nb_rdvs ?? 0) - Number(a.nb_rdvs ?? 0))
    .map((m: any) => {
      const pointe = Number(m.total_minutes ?? 0) > 0
      const nb = Number(m.nb_rdvs ?? 0)
      return {
        key: m.id,
        label: [m.prenom, m.nom].filter(Boolean).join(' ') || m.nom || 'Mécanicien',
        // « 0 min pointées » n'informe pas : dire que le temps n'a pas été
        // pointé, c'est déjà une information exploitable.
        sub: `${formatNombre(nb)} intervention${nb > 1 ? 's' : ''} · ${pointe ? `${formatMinutes(m.total_minutes)} pointées` : 'temps non pointé'}`,
        value: hasFacturation.value ? formatEuro(m.ca_genere) : formatNombre(nb),
        valueSub: pointe ? `${formatMinutes(m.avg_minutes)} par intervention` : undefined,
      }
    })
})

const ecarts = computed<RankedRow[]>(() => {
  const rows = store.performance?.ecarts_mecaniciens ?? []
  return rows
    .filter((m: any) => Number(m.nb_rdvs ?? 0) > 0)
    .map((m: any) => {
      const ecart = Math.round(Number(m.avg_ecart_min ?? 0))
      return {
        key: m.id,
        label: [m.prenom, m.nom].filter(Boolean).join(' '),
        sub: `${formatNombre(m.nb_rdvs)} intervention${Number(m.nb_rdvs) > 1 ? 's' : ''} clôturée${Number(m.nb_rdvs) > 1 ? 's' : ''}`,
        value: `${ecart > 0 ? '+' : ecart < 0 ? '−' : ''}${formatMinutes(Math.abs(ecart))}`,
        valueSub: 'écart moyen',
        tone: ecart > seuilEcart.value ? 'warn' : (ecart <= 0 ? 'good' : 'neutral'),
      } as RankedRow
    })
})

const derives = computed(() => {
  const liste: Array<{ label: string, valeur: string, pct: number, repere: string, tone: 'good' | 'warn' | 'neutral' }> = []

  const delai = Number(store.performance?.delai_restitution?.avg_minutes ?? 0)
  const seuilResti = Number(store.thresholds?.restitution_warning_minutes ?? 15)
  if (delai > 0) {
    liste.push({
      label: 'Délai moyen fin de travaux → restitution',
      valeur: formatMinutes(delai),
      // Repère de lecture : 2 h = mauvais, on borne l'échelle là.
      pct: Math.min(delai / 120 * 100, 100),
      repere: `seuil d'alerte ${seuilResti} min`,
      tone: delai > seuilResti ? 'warn' : 'good',
    })
  }

  const sav = Number(store.performance?.taux_retour_sav?.taux_pct ?? 0)
  const savCount = Number(store.performance?.taux_retour_sav?.sav_count ?? 0)
  if (savCount > 0) {
    liste.push({
      label: 'Taux de retour SAV',
      valeur: formatPourcent(sav),
      pct: Math.min(sav * 5, 100),
      repere: `${savCount} retour${savCount > 1 ? 's' : ''} sur la période`,
      tone: sav > 5 ? 'warn' : 'good',
    })
  }

  if (hasFacturation.value) {
    const prod = Number(store.rentabilite?.mo_analysis?.productivite_pct ?? 0)
    if (prod > 0) {
      liste.push({
        label: 'Productivité main-d\'œuvre',
        valeur: formatPourcent(prod),
        pct: Math.min(prod, 100),
        repere: `cible ${cibleRendement.value} %`,
        tone: prod >= cibleRendement.value ? 'good' : 'warn',
      })
    }
    const tauxMo = Number(store.rentabilite?.global?.taux_mo_pct ?? 0)
    if (tauxMo > 0) {
      liste.push({
        label: 'Part de la main-d\'œuvre dans le CA',
        valeur: formatPourcent(tauxMo),
        pct: Math.min(tauxMo, 100),
        repere: 'le reste vient des pièces',
        tone: 'neutral',
      })
    }
  }

  return liste
})

const prestations = computed<RankedRow[]>(() => {
  const rows = Array.isArray(stats.value.top_services) ? stats.value.top_services : []
  return rows
    .filter((s: any) => Number(s.count ?? 0) > 0)
    .map((s: any) => ({
      key: s.label,
      label: s.label || 'Atelier',
      sub: Number(s.minutes ?? 0) > 0
        ? `${formatMinutes(s.minutes)} de charge cumulée`
        : 'durée non renseignée',
      value: `${formatNombre(s.count)} RDV`,
      valueSub: hasFacturation.value && Number(s.revenue ?? 0) > 0 ? formatEuro(s.revenue) : undefined,
    }))
})

// Les segments sont des PALIERS (nouveau → fidèle) : donnée ordonnée, donc une
// seule teinte en dégradé, pas cinq couleurs sans rapport.
const ORDRE_SEGMENTS = ['nouveau', 'occasionnel', 'regulier', 'fidele', 'vip']
const segments = computed(() => {
  const rows = Array.isArray(stats.value.client_segments) ? stats.value.client_segments : []
  return rows
    .map((s: any) => ({ segment: String(s.segment ?? 'inconnu'), clients: Number(s.clients ?? 0), ca: Number(s.ca ?? 0) }))
    .filter((s: any) => s.clients > 0)
    .sort((a: any, b: any) => ORDRE_SEGMENTS.indexOf(a.segment) - ORDRE_SEGMENTS.indexOf(b.segment))
    .map((s: any, i: number) => ({
      key: s.segment,
      label: s.segment.charAt(0).toUpperCase() + s.segment.slice(1),
      value: s.clients,
      display: `${formatNombre(s.clients)} client${s.clients > 1 ? 's' : ''}${hasFacturation.value && s.ca > 0 ? ` · ${formatEuro(s.ca)}` : ''}`,
      color: `var(--ramp-${Math.min(i + 1, 5)})`,
    }))
})

const typesRentables = computed<RankedRow[]>(() => {
  const rows = store.rentabilite?.par_type ?? []
  return rows.map((t: any) => ({
    key: t.type,
    label: t.type,
    sub: `${formatNombre(t.nb_rdvs)} RDV · ${formatEuro(t.ca_ht)} HT`,
    value: `${formatPourcent(t.taux_mo_pct, 0)} de MO`,
    valueSub: `${formatEuro(t.avg_ticket)} par ticket`,
  }))
})

/**
 * Canaux de décision : couleur figée par canal (le lien client reste bleu même
 * si le téléphone prend le dessus).
 */
const CANAUX = [
  { key: 'client_token', label: 'Lien envoyé au client', color: 'var(--viz-2)' },
  { key: 'client_portail', label: 'Espace client', color: 'var(--viz-3)' },
  { key: 'staff_telephone', label: 'Téléphone (saisi par le staff)', color: 'var(--viz-1)' },
]

const canauxDecision = computed(() => {
  const source = stats.value.pilote?.decisions_travaux_supp_par_canal ?? {}
  return CANAUX
    .map(c => ({ ...c, value: Number(source[c.key] ?? 0) }))
    .filter(c => c.value > 0)
})

const sousTitreDecisions = computed(() => {
  const p = stats.value.pilote ?? {}
  const morceaux: string[] = []
  if (p.delai_decision_moyen_minutes !== null && p.delai_decision_moyen_minutes !== undefined) {
    morceaux.push(`Délai moyen de réponse en ligne : ${formatMinutes(p.delai_decision_moyen_minutes)}.`)
  }
  const attente = Number(p.accords_telephone_en_attente_signature ?? 0)
  if (attente > 0) {
    morceaux.push(`${attente} accord${attente > 1 ? 's' : ''} téléphonique${attente > 1 ? 's' : ''} en attente de signature au comptoir.`)
  }
  return morceaux.join(' ') || undefined
})

const aQuelqueChose = computed(() =>
  mecanos.value.length > 0 || ecarts.value.length > 0 || derives.value.length > 0
  || prestations.value.length > 0 || segments.value.length > 0 || canauxDecision.value.length > 0)

// ── Exploration libre ──
const metricOptions = computed(() => [
  { value: 'count', label: 'Nombre de RDV' },
  { value: 'temps_estime', label: 'Temps estimé' },
  { value: 'temps_effectif', label: 'Temps pointé' },
  ...(hasFacturation.value
    ? [
        { value: 'ca_ht', label: 'CA HT' },
        { value: 'ca_mo_ht', label: 'CA main-d\'œuvre' },
        { value: 'ca_pieces_ht', label: 'CA pièces' },
      ]
    : []),
])

const dimension = ref('type_intervention')
const metrics = ref<string[]>(['count'])

const exploreRows = computed<RankedRow[]>(() => store.explore.map((row: any, i: number) => ({
  key: `${row.label}-${i}`,
  label: row.label || '(non renseigné)',
  sub: metrics.value
    .filter(m => m !== 'count')
    .map(m => `${metricOptions.value.find(o => o.value === m)?.label} : ${m.startsWith('ca') ? formatEuro(row[m]) : formatMinutes(row[m])}`)
    .join(' · ') || undefined,
  value: `${formatNombre(row.count)} RDV`,
})))

function lancerExploration() {
  if (!metrics.value.length) metrics.value = ['count']
  store.loadExplore(dimension.value, metrics.value)
}

// ── Prévision ──
const horizon = ref(14)
function changerHorizon(d: number) {
  horizon.value = d
  store.loadForecast(d)
}

const OPTIONS_FORECAST = {
  plugins: { legend: { display: true, labels: { color: 'var(--content-3)', font: { size: 11 } } } },
  scales: {
    x: { grid: { display: false }, ticks: { color: 'var(--content-3)', font: { size: 10 }, maxTicksLimit: 8 } },
    y: { grid: { color: 'var(--content-3)' }, ticks: { color: 'var(--content-3)', font: { size: 10 } }, beginAtZero: true },
  },
}

const forecastData = computed(() => {
  const hist = store.forecast.historical ?? []
  const prev = store.forecast.forecast ?? []
  return {
    labels: [...hist.map((h: any) => h.date), ...prev.map((f: any) => f.date)],
    datasets: [
      {
        label: 'Réalisé',
        data: [...hist.map((h: any) => Number(h.ca_ht ?? 0)), ...prev.map(() => null)],
        borderColor: 'var(--accent)',
        backgroundColor: 'var(--accent-soft)',
        borderWidth: 2,
        pointRadius: 0,
        fill: true,
        tension: 0.3,
      },
      {
        label: 'Prévu',
        data: [...hist.map(() => null), ...prev.map((f: any) => Number(f.ca_ht ?? 0))],
        borderColor: 'var(--viz-3)',
        backgroundColor: 'var(--viz-3-soft)',
        borderWidth: 2,
        // Le pointillé est réservé à ce qui n'est pas encore arrivé : ici il
        // porte du sens, contrairement à une grille en pointillés.
        borderDash: [6, 4],
        pointRadius: 0,
        fill: true,
        tension: 0.3,
      },
    ],
  }
})

onMounted(() => {
  if (hasFacturation.value) store.loadForecast(horizon.value)
})
</script>

<style scoped>
.tab { display: flex; flex-direction: column; gap: 16px; }
.tab-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
  gap: 16px;
  /* Chaque carte prend la hauteur de son contenu : une carte à une ligne ne
     s'étire pas pour suivre sa voisine à cinq lignes. */
  align-items: start;
}

.derives { display: flex; flex-direction: column; gap: 16px; }
.derive-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
}
.derive-label { font-size: 13px; color: var(--ink-body); }
.derive-value { font-size: 14px; font-weight: 700; color: var(--ink); }
.derive-value--good { color: var(--success-content); }
.derive-value--warn { color: var(--warning-content); }

.explore-controls {
  /* Aligné en haut : en flex-end, le champ « Dimension » (une ligne) se
     collait au bas du bloc « Mesures » (trois lignes) et son étiquette
     partait au milieu de nulle part. */
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 14px 24px;
  margin-bottom: 16px;
}
.field { display: flex; flex-direction: column; gap: 5px; border: 0; margin: 0; padding: 0; }
.field-label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  color: var(--ink-muted);
  padding: 0;
}
.field-input {
  padding: 8px 10px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--glass-border);
  background: var(--overlay-soft);
  color: var(--ink-body);
  font-family: inherit;
  font-size: 13px;
}
.field--metrics { flex: 1 1 300px; max-width: 520px; }
.metrics-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 16px;
}
.metric {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 28px;
  font-size: 12px;
  color: var(--ink-body);
  cursor: pointer;
}
.metric input { accent-color: var(--orange); }
/* Aligné sur la ligne des champs, pas sur celle des étiquettes. */
.explore-go { margin-top: 22px; font-size: 13px; }
.explore-hint { font-size: 13px; color: var(--ink-muted); margin: 0; }

.horizon-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.03em; color: var(--ink-muted); font-weight: 700; }
.horizon {
  min-height: 28px;
  padding: 4px 10px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--glass-border);
  background: transparent;
  color: var(--ink-body);
  font-family: inherit;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}
.horizon--on {
  border-color: var(--accent-graphic);
  background: var(--accent-soft);
  color: var(--accent-content);
}
</style>
