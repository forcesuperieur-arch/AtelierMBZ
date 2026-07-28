<template>
  <div>
    <div class="page-header">
      <div class="stat-head">
        <div>
          <div class="page-title">Stat</div>
          <div class="page-sub">{{ ongletCourant.sousTitre }}</div>
        </div>
        <div class="stat-head-actions">
          <div class="export">
            <button
              type="button"
              class="btn btn-ghost export-btn"
              :aria-expanded="menuExport"
              @click="menuExport = !menuExport"
            >
              Exporter
            </button>
            <div v-if="menuExport" class="export-menu">
              <button type="button" class="export-item" @click="exporter('pdf')">PDF — rapport visuel</button>
              <button type="button" class="export-item" @click="exporter('excel')">Excel — données brutes</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Une seule barre d'onglets, une seule barre de filtres. Chaque onglet
           répond à UNE question ; on ne mélange plus le direct et l'analyse. -->
      <nav class="tabs" role="tablist" aria-label="Vues du tableau de bord">
        <button
          v-for="onglet in ONGLETS"
          :key="onglet.key"
          type="button"
          role="tab"
          class="tab-btn"
          :class="{ 'tab-btn--on': tab === onglet.key }"
          :aria-selected="tab === onglet.key"
          @click="changerOnglet(onglet.key)"
        >
          {{ onglet.label }}
          <span v-if="onglet.key === 'atelier' && store.aTraiter.total" class="tab-badge">{{ store.aTraiter.total }}</span>
        </button>
      </nav>
    </div>

    <!-- La barre de période ne s'affiche que pour les onglets qu'elle pilote :
         elle n'a aucun sens sur une vue temps réel. -->
    <UCard v-if="tab !== 'atelier'" class="periode-card">
      <div class="periode">
        <div class="periode-presets">
          <button
            v-for="preset in PRESETS"
            :key="preset.key"
            type="button"
            class="preset"
            :class="{ 'preset--on': store.periode.preset === preset.key }"
            @click="choisirPreset(preset.key)"
          >
            {{ preset.label }}
          </button>
        </div>
        <div class="periode-dates">
          <label class="sr-only" for="periode-from">Début de période</label>
          <input id="periode-from" v-model="store.periode.from" type="date" class="periode-input" >
          <span class="periode-sep" aria-hidden="true">→</span>
          <label class="sr-only" for="periode-to">Fin de période</label>
          <input id="periode-to" v-model="store.periode.to" type="date" class="periode-input" >
          <button type="button" class="btn btn-primary periode-go" @click="appliquerDates">Appliquer</button>
        </div>
      </div>
      <p class="periode-note">
        Comparé automatiquement à la période précédente de même durée.
      </p>
    </UCard>

    <!-- Pendant un rechargement, on garde l'affichage précédent en retrait
         plutôt que de faire clignoter un squelette. -->
    <div class="tab-zone" :class="{ 'tab-zone--loading': chargementCourant && ongletDejaCharge }">
      <AppLoadingState v-if="!ongletDejaCharge && !erreurCourante" />
      <DashboardTabAtelier v-else-if="tab === 'atelier'" />
      <DashboardTabPeriode v-else-if="tab === 'periode'" />
      <DashboardTabAnalyse v-else-if="tab === 'analyse'" />
      <DashboardTabExplorer v-else />
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Page Stat — coquille et navigation.
 *
 * Refonte : l'ancienne version empilait quatorze sections sur une seule page
 * (10 400 px, neuf écrans), mélangeait le temps réel et l'analyse de période, et
 * ne proposait aucune action. Ici, quatre onglets répondent chacun à une
 * question — le dernier, Explorer, ouvre l'analyse libre — et seul l'onglet
 * consulté charge ses données.
 *
 * L'accès est déjà filtré en amont par `middleware/auth.global.ts`
 * (`hasStatsAccess`), qui redirige les rôles non autorisés — le contrôle
 * serveur restant la vraie barrière (`StatistiquesController::assertStatsAccess`).
 */
const store = useDashboardStore()
const { hasStatsAccess } = useAuth()
const auth = useAuthStore()

type TabKey = 'atelier' | 'periode' | 'analyse' | 'explorer'

const ONGLETS: Array<{ key: TabKey, label: string, sousTitre: string }> = [
  { key: 'atelier', label: 'Atelier', sousTitre: "L'état de l'atelier maintenant et ce qu'il y a à traiter." },
  { key: 'periode', label: 'Période', sousTitre: "L'activité sur une période, comparée à la précédente." },
  { key: 'analyse', label: 'Analyse', sousTitre: 'Ce qui explique les chiffres : mécaniciens, délais, prestations.' },
  { key: 'explorer', label: 'Explorer', sousTitre: 'Analyse libre : croise les axes, clique pour filtrer, descends aux rendez-vous.' },
]

const PRESETS = [
  { key: 'today', label: "Aujourd'hui" },
  { key: '7d', label: '7 jours' },
  { key: '30d', label: '30 jours' },
  { key: '90d', label: '90 jours' },
]

const STORAGE_KEY = 'paddock:stat-onglet'

/**
 * Onglet d'arrivée selon le rôle : un responsable d'atelier ouvre la page pour
 * savoir ce qui se passe maintenant, un gérant pour suivre l'activité. Le
 * dernier onglet choisi par l'utilisateur prend le pas sur ce défaut.
 */
function ongletParDefaut(): TabKey {
  const roleMetier = String(auth.user?.role_metier?.code || '').toLowerCase()
  if (roleMetier === 'responsable_atelier') return 'atelier'
  return 'periode'
}

const tab = ref<TabKey>('periode')
const menuExport = ref(false)

const ongletCourant = computed(() => ONGLETS.find(o => o.key === tab.value) ?? ONGLETS[0])
const chargementCourant = computed(() => store.loading[tab.value])
const ongletDejaCharge = computed(() => store.loaded[tab.value])
const erreurCourante = computed(() => store.erreur[tab.value])

async function chargerOnglet(key: TabKey) {
  if (key === 'atelier') {
    store.startAutoRefresh()
    if (!store.loaded.atelier) await store.loadAtelier()
    return
  }
  // Le rafraîchissement automatique ne concerne que le direct : recharger des
  // agrégats de période toutes les minutes ne sert à rien et coûte huit requêtes.
  store.stopAutoRefresh()
  if (key === 'periode' && !store.loaded.periode) await store.loadPeriode()
  if (key === 'analyse' && !store.loaded.analyse) await store.loadAnalyse()
  // L'Explorateur gère lui-même son chargement (catalogue puis requête) :
  // la coquille n'a qu'à le déclarer prêt pour ne pas rester sur son voile.
  if (key === 'explorer') store.loaded.explorer = true
}

function changerOnglet(key: TabKey) {
  tab.value = key
  if (import.meta.client) localStorage.setItem(STORAGE_KEY, key)
  chargerOnglet(key)
}

function choisirPreset(key: string) {
  store.applyPreset(key)
  rechargerPeriode()
}

function appliquerDates() {
  store.periode.preset = 'custom'
  rechargerPeriode()
}

async function rechargerPeriode() {
  await store.reloadPeriodeDependants()
  if (tab.value === 'analyse') await store.loadAnalyse()
}

async function exporter(format: 'pdf' | 'excel') {
  menuExport.value = false
  try {
    const params = new URLSearchParams()
    if (store.periode.from) params.set('from', store.periode.from)
    if (store.periode.to) params.set('to', store.periode.to)
    const url = `/api/analytics/export/${format}${params.toString() ? `?${params.toString()}` : ''}`
    const response = await fetch(url, { credentials: 'include' })
    if (!response.ok) throw new Error(`HTTP ${response.status}`)
    const blob = await response.blob()
    const lien = document.createElement('a')
    lien.href = URL.createObjectURL(blob)
    lien.download = `stat_${store.periode.from || ''}_${store.periode.to || ''}.${format === 'pdf' ? 'pdf' : 'xlsx'}`
    lien.click()
    URL.revokeObjectURL(lien.href)
  } catch {
    useToast().add({
      title: 'Export impossible',
      description: 'Le rapport n\'a pas pu être généré. Réessaie dans un instant.',
      color: 'error',
    })
  }
}

onMounted(() => {
  if (!hasStatsAccess()) return
  store.applyPreset('30d')
  const memorise = localStorage.getItem(STORAGE_KEY) as TabKey | null
  tab.value = memorise && ONGLETS.some(o => o.key === memorise) ? memorise : ongletParDefaut()
  chargerOnglet(tab.value)
})

onUnmounted(() => store.stopAutoRefresh())
</script>

<style scoped>
.stat-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
}
.stat-head-actions { display: flex; align-items: center; gap: 8px; }

.export { position: relative; }
.export-btn { font-size: 12px; padding: 7px 14px; }
.export-menu {
  position: absolute;
  top: 100%;
  right: 0;
  margin-top: 6px;
  padding: 6px;
  min-width: 210px;
  border-radius: var(--radius);
  background: var(--dark3);
  border: 1px solid var(--glass-border);
  box-shadow: var(--shadow-lg);
  z-index: 50;
}
.export-item {
  display: block;
  width: 100%;
  text-align: left;
  padding: 8px 10px;
  border: 0;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--ink-body);
  font-family: inherit;
  font-size: 12px;
  cursor: pointer;
}
.export-item:hover { background: rgba(255, 255, 255, 0.06); color: var(--ink); }

.tabs {
  display: flex;
  gap: 4px;
  margin-top: 16px;
  border-bottom: 1px solid var(--glass-border);
}
.tab-btn {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 9px 16px;
  border: 0;
  border-bottom: 2px solid transparent;
  background: transparent;
  color: var(--ink-muted);
  font-family: inherit;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: color var(--transition), border-color var(--transition);
}
.tab-btn:hover { color: var(--ink-body); }
.tab-btn--on { color: var(--orange); border-bottom-color: var(--orange); }
.tab-btn:focus-visible { outline: 2px solid var(--orange); outline-offset: -2px; }
.tab-badge {
  font-size: 10px;
  font-weight: 800;
  padding: 1px 6px;
  border-radius: 999px;
  background: var(--status-warn-soft);
  color: var(--status-warn);
}

.periode-card { margin-bottom: 16px; }
.periode {
  /* Groupés à gauche : en space-between sur un grand écran, les raccourcis et
     les dates se retrouvaient aux deux extrémités avec un trou au milieu. */
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px 28px;
}
.periode-presets { display: flex; flex-wrap: wrap; gap: 6px; }
.preset {
  min-height: 34px;
  padding: 7px 14px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--glass-border);
  background: rgba(255, 255, 255, 0.04);
  color: var(--ink-body);
  font-family: inherit;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}
.preset:hover { border-color: var(--border-hover); }
.preset--on {
  border-color: rgba(255, 210, 0, 0.35);
  background: rgba(255, 210, 0, 0.12);
  color: var(--orange);
}
.periode-dates { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
.periode-input {
  /* La feuille globale impose `width: 100%` à tous les inputs de `.content` ;
     dans une rangée flex, c'est la base flex qui tranche — sans elle, les deux
     champs de date passent à la ligne chacun sur sa propre rangée. */
  flex: 0 0 170px;
  padding: 7px 10px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--glass-border);
  background: rgba(255, 255, 255, 0.04);
  color: var(--ink-body);
  font-family: inherit;
  font-size: 13px;
}
.periode-sep { color: var(--ink-muted); }
.periode-go { font-size: 12px; padding: 7px 14px; }
.periode-note { margin: 10px 0 0; font-size: 12px; color: var(--ink-muted); }

.tab-zone { transition: opacity var(--transition); }
.tab-zone--loading { opacity: 0.55; }

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
</style>
