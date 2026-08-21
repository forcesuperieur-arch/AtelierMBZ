<template>
  <div>
    <!-- En-tête de page du design system : un titre, une ligne qui dit à quoi
         la page répond, et le jeu d'onglets à droite. Le filet jaune est le
         SEUL ornement admis ici — pas d'icône, pas de carte, pas de dégradé. -->
    <header class="page-header stat-head">
      <div class="stat-identite">
        <h1 class="page-title stat-titre">Stat</h1>
        <p class="stat-sous-titre">{{ ongletCourant.sousTitre }}</p>
      </div>

      <div class="stat-outils">
        <!-- Une seule barre d'onglets, une seule barre de filtres. Chaque onglet
             répond à UNE question ; on ne mélange plus le direct et l'analyse. -->
        <nav
          class="stat-onglets"
          role="tablist"
          aria-label="Vues de Stat"
          data-testid="stat-onglets"
        >
          <button
            v-for="onglet in ONGLETS"
            :id="`stat-onglet-${onglet.key}`"
            :key="onglet.key"
            type="button"
            role="tab"
            class="stat-onglet"
            :class="{ 'stat-onglet--on': tab === onglet.key }"
            :aria-selected="tab === onglet.key"
            :aria-controls="PANNEAU_ID"
            :data-testid="`stat-onglet-${onglet.key}`"
            @click="changerOnglet(onglet.key)"
          >
            {{ onglet.label }}
            <span
              v-if="onglet.key === 'atelier' && store.aTraiter.total"
              class="stat-onglet-compte"
              data-testid="stat-onglet-atelier-compte"
            >{{ store.aTraiter.total }}</span>
          </button>
        </nav>

        <!-- Échap referme le menu sans perdre le clavier : le focus est resté
             sur le bouton qui l'a ouvert. Un menu qui ne se ferme qu'à la
             souris piège celui qui navigue au clavier. -->
        <div class="stat-export" @keydown.esc="menuExport = false">
          <AppButton
            variant="ghost"
            class="stat-export-btn"
            aria-haspopup="menu"
            :aria-expanded="menuExport"
            data-testid="stat-export"
            @click="menuExport = !menuExport"
          >
            Exporter
          </AppButton>
          <div v-if="menuExport" class="stat-export-menu" role="menu">
            <button type="button" role="menuitem" class="stat-export-item" @click="exporter('pdf')">
              Exporter en PDF · rapport visuel
            </button>
            <button type="button" role="menuitem" class="stat-export-item" @click="exporter('excel')">
              Exporter en Excel · données brutes
            </button>
          </div>
        </div>
      </div>
    </header>

    <!-- La barre de période ne s'affiche que pour les onglets qu'elle pilote :
         elle n'a aucun sens sur une vue temps réel. -->
    <section
      v-if="tab !== 'atelier'"
      class="periode-carte"
      aria-label="Période analysée"
      data-testid="stat-periode"
    >
      <div class="periode">
        <div class="periode-raccourcis" role="group" aria-label="Raccourcis de période">
          <button
            v-for="preset in PRESETS"
            :key="preset.key"
            type="button"
            class="periode-pilule"
            :class="{ 'periode-pilule--on': store.periode.preset === preset.key }"
            :aria-pressed="store.periode.preset === preset.key"
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
          <AppButton variant="primary" class="periode-appliquer" @click="appliquerDates">
            Appliquer la période
          </AppButton>
        </div>
      </div>
      <p class="periode-note">
        Chaque mesure est comparée à la période précédente, de même durée.
      </p>
    </section>

    <!-- Pendant un rechargement, on garde l'affichage précédent en retrait
         plutôt que de faire clignoter un squelette. -->
    <div
      :id="PANNEAU_ID"
      class="tab-zone"
      role="tabpanel"
      :aria-labelledby="`stat-onglet-${tab}`"
      :class="{ 'tab-zone--loading': chargementCourant && ongletDejaCharge }"
    >
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

/**
 * Les sous-titres nomment ce que l'onglet montre ; ils ne s'adressent à
 * personne et n'annoncent aucun classement de personnes — la règle 6 réserve
 * l'écart vendu / pointé aux prestations, jamais aux mécaniciens.
 */
const ONGLETS: Array<{ key: TabKey, label: string, sousTitre: string }> = [
  { key: 'atelier', label: 'Atelier', sousTitre: "L'état de l'atelier maintenant et ce qu'il y a à traiter." },
  { key: 'periode', label: 'Période', sousTitre: "L'activité sur une période, comparée à la précédente." },
  { key: 'analyse', label: 'Analyse', sousTitre: 'Ce qui explique les chiffres : délais, prestations, marges.' },
  { key: 'explorer', label: 'Explorer', sousTitre: 'Analyse libre : les axes se croisent, le résultat descend jusqu\'aux rendez-vous.' },
]

const PRESETS = [
  { key: 'today', label: "Aujourd'hui" },
  { key: '7d', label: '7 jours' },
  { key: '30d', label: '30 jours' },
  { key: '90d', label: '90 jours' },
]

const STORAGE_KEY = 'paddock:stat-onglet'

/** Cible des onglets : un `tablist` sans `tabpanel` ne s'annonce pas. */
const PANNEAU_ID = 'stat-panneau'

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
    // `messageErreur` nomme la cause à partir du code HTTP ; un Error dont le
    // message vaut « HTTP 500 » le court-circuiterait et afficherait ce
    // libellé tel quel au comptoir. On laisse donc le message vide.
    if (!response.ok) throw Object.assign(new Error(''), { statusCode: response.status })
    const blob = await response.blob()
    const lien = document.createElement('a')
    lien.href = URL.createObjectURL(blob)
    lien.download = `stat_${store.periode.from || ''}_${store.periode.to || ''}.${format === 'pdf' ? 'pdf' : 'xlsx'}`
    lien.click()
    URL.revokeObjectURL(lien.href)
  } catch (erreur) {
    useToast().add({
      title: 'Export impossible',
      description: messageErreur(erreur, "le rapport de la période n'a pas été produit"),
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
/* ── En-tête ─────────────────────────────────────────────────────────────
   `.page-header` de la feuille globale centre ses enfants ; ici le bloc de
   gauche porte deux lignes et le jeu d'onglets une seule, l'alignement se
   fait donc sur la ligne de base du bas. */
.stat-head { align-items: flex-end; }
.stat-identite { flex: 1 1 320px; min-width: 0; }

/* Le design system n'accorde au titre de page qu'un filet jaune de 4 px. La
   feuille globale en dessine un de 3 px, en dégradé et biseauté, hérité de
   l'ancienne charte : on le ramène à la valeur du système sans toucher à la
   feuille, qui sert toutes les autres pages. */
.stat-titre { margin: 0; color: var(--pk-ink); }
.stat-titre::after {
  height: 4px;
  background: var(--pk-accent);
  clip-path: none;
}

.stat-sous-titre {
  margin: 6px 0 0;
  font-size: 13px;
  color: var(--pk-ink-quiet);
}

.stat-outils {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: var(--pk-target-gap);
}

/* ── Onglets en pilules ─────────────────────────────────────────────────── */
.stat-onglets { display: flex; flex-wrap: wrap; gap: 4px; }

.stat-onglet {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: var(--pk-target-desk);
  padding: 0 16px;
  border: 1px solid transparent;
  border-radius: var(--pk-radius-pill);
  background: transparent;
  color: var(--pk-ink-quiet);
  font-family: inherit;
  font-size: 13px;
  /* Graisse constante d'un onglet à l'autre : signaler l'actif par le poids
     déplacerait la largeur de toute la série à chaque clic. L'état se dit par
     l'aplat et la couleur — c'est la règle du design system. */
  font-weight: 600;
  cursor: pointer;
  transition:
    background var(--pk-duration-state) var(--pk-easing),
    border-color var(--pk-duration-state) var(--pk-easing),
    color var(--pk-duration-state) var(--pk-easing);
}
.stat-onglet:hover { background: var(--pk-neutral-surface); color: var(--pk-ink); }
.stat-onglet--on,
.stat-onglet--on:hover {
  background: var(--pk-ink);
  border-color: var(--pk-ink);
  color: var(--pk-surface-raised);
}

/* Compteur de la file à traiter. Neutre et non coloré : c'est un nombre, pas
   un statut — la gravité se lit dans la file elle-même, pas sur l'onglet. */
.stat-onglet-compte {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 20px;
  height: 20px;
  padding: 0 6px;
  border-radius: var(--pk-radius-pill);
  background: var(--pk-ink);
  color: var(--pk-surface-raised);
  font-size: 11px;
  font-weight: 700;
}
.stat-onglet--on .stat-onglet-compte {
  background: var(--pk-surface-raised);
  color: var(--pk-ink);
}

/* ── Export ─────────────────────────────────────────────────────────────── */
.stat-export { position: relative; }
.stat-export-btn { min-height: var(--pk-target-desk); }

.stat-export-menu {
  position: absolute;
  top: calc(100% + var(--pk-target-gap));
  right: 0;
  z-index: 50;
  min-width: 268px;
  padding: 6px;
  /* Surface levée + filet : le design system sépare par la bordure et le
     changement de fond, jamais par une ombre portée. */
  border: 1px solid var(--pk-border);
  border-radius: var(--pk-radius-card);
  background: var(--pk-surface-raised);
}
.stat-export-item {
  display: block;
  width: 100%;
  min-height: var(--pk-target-desk);
  padding: 0 12px;
  border: 0;
  border-radius: var(--pk-radius-card);
  background: transparent;
  color: var(--pk-ink);
  font-family: inherit;
  font-size: 13px;
  text-align: left;
  cursor: pointer;
  transition: background var(--pk-duration-state) var(--pk-easing);
}
.stat-export-item:hover { background: var(--pk-neutral-surface); }

/* ── Barre de période ───────────────────────────────────────────────────── */
.periode-carte {
  margin-bottom: 16px;
  padding: 16px 18px;
  border: 1px solid var(--pk-border);
  border-radius: var(--pk-radius-card);
  background: var(--pk-surface);
}
.periode {
  /* Groupés à gauche : en space-between sur un grand écran, les raccourcis et
     les dates se retrouvaient aux deux extrémités avec un trou au milieu. */
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  /* Un raccourci recadre la période, « Appliquer la période » valide une
     saisie manuelle : deux effets opposés, donc l'écart minimal du système. */
  gap: var(--pk-target-gap) 28px;
}
.periode-raccourcis { display: flex; flex-wrap: wrap; gap: var(--pk-target-gap); }

.periode-pilule {
  min-height: var(--pk-target-desk);
  padding: 0 16px;
  border: 1px solid var(--pk-border-control);
  border-radius: var(--pk-radius-pill);
  background: transparent;
  color: var(--pk-ink);
  font-family: inherit;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition:
    background var(--pk-duration-state) var(--pk-easing),
    border-color var(--pk-duration-state) var(--pk-easing),
    color var(--pk-duration-state) var(--pk-easing);
}
.periode-pilule:hover { border-color: var(--pk-border-strong); }
/* Pilule retenue = aplat plein, comme la FilterPill du planning. */
.periode-pilule--on,
.periode-pilule--on:hover {
  background: var(--pk-ink);
  border-color: var(--pk-ink);
  color: var(--pk-surface-raised);
}

.periode-dates { display: flex; align-items: center; flex-wrap: wrap; gap: var(--pk-target-gap); }
.periode-input {
  /* La feuille globale impose `width: 100%` à tous les inputs de `.content` ;
     dans une rangée flex, c'est la base flex qui tranche — sans elle, les deux
     champs de date passent à la ligne chacun sur sa propre rangée. */
  flex: 0 0 170px;
  /* La même règle globale pose `min-height: 40px` avec une spécificité qu'une
     feuille de page ne bat pas ; `height` n'y figure pas, c'est donc par elle
     qu'on atteint la cible de bureau de 44 px sans toucher à `main.css`. */
  height: var(--pk-target-desk);
}
.periode-sep { color: var(--pk-ink-muted); }
.periode-appliquer { min-height: var(--pk-target-desk); }
.periode-note { margin: 12px 0 0; font-size: 12px; color: var(--pk-ink-muted); }

/* ── Zone d'onglet ──────────────────────────────────────────────────────── */
.tab-zone { transition: opacity var(--pk-duration-state) var(--pk-easing); }
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
