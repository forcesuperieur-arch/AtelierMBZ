import { defineStore } from 'pinia'

/**
 * Données de la page Stat.
 *
 * Chargement PAR ONGLET : l'ancienne page tirait 8 endpoints en parallèle toutes
 * les 60 s, y compris pour des sections que personne ne regardait. Ici, seul
 * l'onglet affiché charge ses données, et seul l'onglet « Atelier » se
 * rafraîchit automatiquement (c'est le seul dont le contenu change en continu).
 */

export interface ActionRow {
  rdv_id: number
  statut?: string
  date_rdv?: string
  heure_rdv?: string
  type_intervention?: string | null
  pont_nom?: string | null
  mecanicien_nom?: string | null
  client_nom?: string | null
  client_telephone?: string | null
  vehicule_info?: string | null
  vehicule_plaque?: string | null
  detail?: string | null
  numero_or?: string | null
  temps_estime?: number | null
}

export interface ActionGroup {
  kind: string
  severity: 'critical' | 'warning' | 'info'
  titre: string
  action_label: string
  action: { type: 'rdv' } | { type: 'route', to: string }
  rows: ActionRow[]
  total: number
}

const REFRESH_ATELIER_MS = 60_000

export const useDashboardStore = defineStore('dashboard', () => {
  const api = useApi()

  // ── Période analysée (partagée par les onglets Période et Analyse) ──
  const periode = reactive({ preset: '30d', from: '', to: '' })

  // ── Données par onglet ──
  const realtime = ref<any>({})
  const aTraiter = ref<{ items: ActionGroup[], total: number, seuils?: any }>({ items: [], total: 0 })
  const ponts = ref<any[]>([])
  const stockAlertes = ref<any[]>([])
  const stats = ref<any>({})
  const performance = ref<any>({})
  const rentabilite = ref<any>({})
  const thresholds = ref<any>({})
  const forecast = ref<{ historical: any[], forecast: any[] }>({ historical: [], forecast: [] })
  const explore = ref<any[]>([])

  // ── États de chargement / erreur, par onglet ──
  const loading = reactive({ atelier: false, periode: false, analyse: false, explorer: false })
  const loaded = reactive({ atelier: false, periode: false, analyse: false, explorer: false })
  const erreur = reactive<Record<string, string | null>>({ atelier: null, periode: null, analyse: null, explorer: null })

  let refreshTimer: ReturnType<typeof setInterval> | null = null

  function toIsoDate(date: Date): string {
    const y = date.getFullYear()
    const m = String(date.getMonth() + 1).padStart(2, '0')
    const d = String(date.getDate()).padStart(2, '0')
    return `${y}-${m}-${d}`
  }

  /** Applique un raccourci de période (aujourd'hui / 7 / 30 / 90 jours). */
  function applyPreset(key: string) {
    periode.preset = key
    const end = new Date()
    const start = new Date()
    if (key === '7d') start.setDate(start.getDate() - 6)
    else if (key === '30d') start.setDate(start.getDate() - 29)
    else if (key === '90d') start.setDate(start.getDate() - 89)
    periode.from = toIsoDate(start)
    periode.to = toIsoDate(end)
  }

  const periodeQuery = computed(() => {
    const params = new URLSearchParams()
    if (periode.from) params.set('from', periode.from)
    if (periode.to) params.set('to', periode.to)
    if (!periode.from || !periode.to) params.set('period', periode.preset)
    const q = params.toString()
    return q ? `?${q}` : ''
  })

  /**
   * Message d'erreur lisible. Le 403 est un cas métier explicite (page réservée
   * au responsable atelier), pas une panne : il mérite son propre libellé.
   */
  function messageErreur(e: any): string {
    const code = e?.statusCode ?? e?.response?.status
    if (code === 403) return "Cette page est réservée au responsable d'atelier et aux profils supérieurs."
    return 'Impossible de charger les données. Vérifie la connexion puis réessaie.'
  }

  async function loadAtelier() {
    loading.atelier = true
    erreur.atelier = null
    try {
      const stockActif = useAtelierStore().isModuleEnabled('stock')
      const [rt, queue, pontsData, config, alertes] = await Promise.all([
        api.get('/statistiques/realtime'),
        api.get('/statistiques/a-traiter'),
        // Rechargé à chaque tour ici (contrairement à ensurePonts) : c'est
        // l'occupation en direct, elle doit suivre le rafraîchissement.
        api.get('/ponts/status').catch(() => []),
        api.get('/config').catch(() => null),
        stockActif ? api.get('/stock/alertes').catch(() => []) : Promise.resolve([]),
      ])
      realtime.value = rt ?? {}
      aTraiter.value = queue ?? { items: [], total: 0 }
      ponts.value = Array.isArray(pontsData) ? pontsData : (pontsData?.['hydra:member'] ?? pontsData?.member ?? [])
      thresholds.value = config?.dashboardThresholds ?? thresholds.value
      stockAlertes.value = Array.isArray(alertes) ? alertes : []
      loaded.atelier = true
    } catch (e: any) {
      erreur.atelier = messageErreur(e)
    } finally {
      loading.atelier = false
    }
  }

  /**
   * Seuils de l'atelier (cible de rendement, alertes). Chargés une seule fois,
   * quel que soit l'onglet d'arrivée : sans eux, les cibles affichées seraient
   * les valeurs de repli au lieu de la configuration réelle.
   */
  async function ensureConfig() {
    if (Object.keys(thresholds.value ?? {}).length) return
    const config = await api.get('/config').catch(() => null)
    if (config?.dashboardThresholds) thresholds.value = config.dashboardThresholds
  }

  /**
   * Ponts de l'atelier. Utilisés par l'onglet Atelier (occupation en direct) et
   * par l'onglet Période (charge planifiée ramenée au pont) : chargés une fois,
   * quel que soit l'onglet d'arrivée.
   */
  async function ensurePonts() {
    if (ponts.value.length) return
    const data = await api.get('/ponts/status').catch(() => [])
    ponts.value = Array.isArray(data) ? data : (data?.['hydra:member'] ?? data?.member ?? [])
  }

  async function loadPeriode() {
    loading.periode = true
    erreur.periode = null
    try {
      const q = periodeQuery.value
      const [s, perf] = await Promise.all([
        api.get(`/analytics/dashboard${q}`),
        api.get(`/statistiques/performance${q}`).catch(() => ({})),
        ensureConfig(),
        ensurePonts(),
      ])
      stats.value = s ?? {}
      performance.value = perf ?? {}
      loaded.periode = true
    } catch (e: any) {
      erreur.periode = messageErreur(e)
    } finally {
      loading.periode = false
    }
  }

  async function loadAnalyse() {
    loading.analyse = true
    erreur.analyse = null
    try {
      const q = periodeQuery.value
      // L'analyse s'appuie sur les agrégats de période : on les charge si
      // l'utilisateur ouvre directement cet onglet.
      if (!loaded.periode) await loadPeriode()
      rentabilite.value = (await api.get(`/statistiques/rentabilite${q}`).catch(() => ({}))) ?? {}
      loaded.analyse = true
    } catch (e: any) {
      erreur.analyse = messageErreur(e)
    } finally {
      loading.analyse = false
    }
  }

  async function loadForecast(days = 14) {
    try {
      const params = new URLSearchParams({ days: String(days) })
      if (periode.from) params.set('from', periode.from)
      if (periode.to) params.set('to', periode.to)
      const data = await api.get(`/analytics/forecast?${params.toString()}`)
      forecast.value = {
        historical: Array.isArray(data?.historical) ? data.historical : [],
        forecast: Array.isArray(data?.forecast) ? data.forecast : [],
      }
    } catch {
      forecast.value = { historical: [], forecast: [] }
    }
  }

  async function loadExplore(dimension: string, metrics: string[]) {
    try {
      const params = new URLSearchParams({ dimension })
      metrics.forEach(m => params.append('metrics[]', m))
      if (periode.from) params.set('from', periode.from)
      if (periode.to) params.set('to', periode.to)
      const data = await api.get(`/analytics/explore?${params.toString()}`)
      explore.value = Array.isArray(data?.rows) ? data.rows : []
    } catch {
      explore.value = []
    }
  }

  /** Recharge les onglets déjà consultés après un changement de période. */
  async function reloadPeriodeDependants() {
    loaded.periode = false
    loaded.analyse = false
    await loadPeriode()
  }

  function startAutoRefresh() {
    stopAutoRefresh()
    refreshTimer = setInterval(loadAtelier, REFRESH_ATELIER_MS)
  }

  function stopAutoRefresh() {
    if (refreshTimer) clearInterval(refreshTimer)
    refreshTimer = null
  }

  return {
    periode, periodeQuery, applyPreset,
    realtime, aTraiter, ponts, stockAlertes, stats, performance, rentabilite, thresholds, forecast, explore,
    loading, loaded, erreur,
    loadAtelier, loadPeriode, loadAnalyse, loadForecast, loadExplore, reloadPeriodeDependants,
    startAutoRefresh, stopAutoRefresh,
  }
})
