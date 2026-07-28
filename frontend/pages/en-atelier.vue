<template>
  <div>
    <div class="page-header">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
        <div>
          <div class="page-title">Motos en atelier</div>
          <div class="page-sub">
            Toutes les motos physiquement présentes, de la plus ancienne à la plus récente.
            L'ancienneté est comptée en heures ouvrées (les jours de fermeture ne comptent pas).
          </div>
        </div>
        <button class="btn btn-ghost" :disabled="loading" @click="load">
          {{ loading ? 'Actualisation…' : '↻ Actualiser' }}
        </button>
      </div>
    </div>

    <AppErrorState v-if="loadError" :description="loadError" @retry="load" />

    <AppLoadingState v-else-if="loading && !motos.length" />

    <template v-else>
      <div class="grid-4" style="margin-bottom:20px;">
        <div class="kpi-card">
          <div class="kpi-label">MOTOS PRÉSENTES</div>
          <div class="kpi-value">{{ stats.total }}</div>
          <div class="kpi-sub">{{ statutsResume }}</div>
        </div>
        <div class="kpi-card" :class="{ 'kpi-card--alerte': stats.total_depassement > 0 }">
          <div class="kpi-label">AU-DELÀ DE {{ stats.seuil_heures }} H OUVRÉES</div>
          <div class="kpi-value">{{ stats.total_depassement }}</div>
          <div class="kpi-sub">à relancer ou débloquer</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-label">ANCIENNETÉ MOYENNE</div>
          <div class="kpi-value">{{ formatCourt(stats.heures_ouvrees_moyenne) }}</div>
          <div class="kpi-sub">hors jours de fermeture</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-label">LA PLUS ANCIENNE</div>
          <div class="kpi-value">{{ formatCourt(stats.heures_ouvrees_max) }}</div>
          <div class="kpi-sub">hors jours de fermeture</div>
        </div>
      </div>

      <div class="filtres">
        <input
          v-model="recherche"
          type="search"
          class="filtre-recherche"
          placeholder="Rechercher (plaque, client, moto, mécanicien)…"
        />
        <button
          v-for="statut in statutsPresents"
          :key="statut.code"
          class="puce"
          :class="{ 'puce--active': statutsSelectionnes.includes(statut.code) }"
          @click="toggleStatut(statut.code)"
        >
          {{ statut.label }} ({{ statut.count }})
        </button>
        <button
          class="puce"
          :class="{ 'puce--active': seulementDepassements }"
          @click="seulementDepassements = !seulementDepassements"
        >
          ⏱ Seulement les dépassements
        </button>
        <button v-if="filtresActifs" class="lien-reset" @click="reinitialiser">Réinitialiser</button>
        <span class="compteur">{{ motosFiltrees.length }} affichée(s) / {{ motos.length - motosFiltrees.length }} masquée(s)</span>
      </div>

      <AppEmptyState
        v-if="!motos.length"
        icon="🏍️"
        title="Aucune moto à l'atelier"
        description="Aucune moto n'est actuellement immobilisée dans l'atelier."
      />

      <div v-else-if="!motosFiltrees.length" class="vide-filtre">
        Aucune moto ne correspond aux filtres.
      </div>

      <div v-else class="table-wrap">
        <table class="table-atelier">
          <thead>
            <tr>
              <th>Ancienneté</th>
              <th>Moto</th>
              <th>Client</th>
              <th>Statut</th>
              <th>Mécanicien</th>
              <th>Pont</th>
              <th>Arrivée</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="moto in motosFiltrees" :key="moto.rdv_id" :class="{ 'ligne--depassement': moto.en_depassement }">
              <td>
                <span class="duree" :class="{ 'duree--alerte': moto.en_depassement }">
                  {{ formatDuree(moto.heures_ouvrees) }}
                </span>
              </td>
              <td>
                <strong>{{ moto.plaque || 'Sans plaque' }}</strong>
                <div class="cell-sub">{{ moto.vehicule || 'Moto' }}</div>
              </td>
              <td>
                {{ moto.client_nom || 'Client inconnu' }}
                <div class="cell-sub">{{ moto.client_telephone || '—' }}</div>
                <div v-if="moto.derniere_relance" class="cell-sub">
                  Relancé le {{ formatDate(moto.derniere_relance) }}
                </div>
              </td>
              <td><span class="badge-statut">{{ statutLabel(moto.statut) }}</span></td>
              <td>{{ moto.mecanicien || 'Non affecté' }}</td>
              <td>{{ moto.pont_nom || '—' }}</td>
              <td>
                {{ formatDate(moto.recu_le) }}
                <div class="cell-sub">RDV du {{ formatDate(moto.date_rdv) }} à {{ moto.heure_rdv }}</div>
              </td>
              <td><button class="lien-action" @click="ouvrirFiche(moto)">Traiter</button></td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <MotoEnAtelierModal
      :open="ficheOuverte"
      :moto="motoSelectionnee"
      @update:open="ficheOuverte = $event"
      @changed="load"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'

/**
 * Onglet de suivi « Motos en atelier » : la liste de toutes les motos physiquement
 * présentes avec leur ancienneté en heures OUVRÉES (calcul serveur, jours de
 * fermeture exclus), les dépassements du seuil étant mis en évidence.
 */
interface MotoEnAtelier {
  rdv_id: number
  statut: string
  recu_le: string
  date_rdv: string
  heure_rdv: string
  type_intervention: string | null
  pont_nom: string | null
  heures_ouvrees: number
  en_depassement: boolean
  client_nom: string | null
  client_telephone: string | null
  vehicule: string | null
  plaque: string | null
  mecanicien: string | null
  derniere_relance?: string | null
}

const REFRESH_MS = 2 * 60 * 1000

const api = useApi()
const motos = ref<MotoEnAtelier[]>([])
const motoSelectionnee = ref<MotoEnAtelier | null>(null)
const ficheOuverte = ref(false)
const stats = ref({
  seuil_heures: 72,
  total: 0,
  total_depassement: 0,
  heures_ouvrees_moyenne: 0,
  heures_ouvrees_max: 0,
  par_statut: {} as Record<string, number>,
})
const loading = ref(true)
const loadError = ref('')
const recherche = ref('')
const statutsSelectionnes = ref<string[]>([])
const seulementDepassements = ref(false)
let timer: ReturnType<typeof setInterval> | null = null

const STATUT_LABELS: Record<string, string> = {
  reception: 'Réceptionnée',
  en_cours: 'En cours',
  en_pause: 'En pause',
  en_attente_pieces: 'Attente pièces',
  en_attente_reprise: 'Attente reprise',
  en_gardiennage: 'Gardiennage',
}

function statutLabel(statut: string): string {
  return STATUT_LABELS[statut] || statut
}

const statutsPresents = computed(() =>
  Object.entries(stats.value.par_statut)
    .map(([code, count]) => ({ code, label: statutLabel(code), count }))
    .sort((a, b) => b.count - a.count),
)

const statutsResume = computed(() =>
  statutsPresents.value
    .map(s => `${s.count} ${s.label.toLowerCase()}${s.count > 1 && s.label.endsWith('e') ? 's' : ''}`)
    .join(' · ') || '—',
)

const filtresActifs = computed(
  () => recherche.value.trim() !== '' || statutsSelectionnes.value.length > 0 || seulementDepassements.value,
)

const motosFiltrees = computed(() => {
  const terme = recherche.value.trim().toLowerCase()
  return motos.value.filter((moto) => {
    if (seulementDepassements.value && !moto.en_depassement) return false
    if (statutsSelectionnes.value.length && !statutsSelectionnes.value.includes(moto.statut)) return false
    if (!terme) return true
    return [moto.plaque, moto.vehicule, moto.client_nom, moto.mecanicien, moto.type_intervention]
      .some(champ => (champ || '').toLowerCase().includes(terme))
  })
})

function toggleStatut(code: string) {
  statutsSelectionnes.value = statutsSelectionnes.value.includes(code)
    ? statutsSelectionnes.value.filter(s => s !== code)
    : [...statutsSelectionnes.value, code]
}

function reinitialiser() {
  recherche.value = ''
  statutsSelectionnes.value = []
  seulementDepassements.value = false
}

function formatDuree(heures: number): string {
  const jours = Math.floor(heures / 24)
  const reste = Math.round(heures % 24)
  return jours > 0 ? `${jours} j ${reste} h` : `${Math.round(heures)} h`
}

function formatCourt(heures: number): string {
  return heures >= 24 ? formatDuree(heures) : `${Math.round(heures)} h`
}

function formatDate(valeur: string): string {
  if (!valeur) return '—'
  const d = new Date(valeur)
  return Number.isNaN(d.getTime())
    ? valeur
    : d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: '2-digit' })
}

function ouvrirFiche(moto: MotoEnAtelier) {
  motoSelectionnee.value = moto
  ficheOuverte.value = true
}

async function load() {
  loading.value = true
  try {
    const data = await api.get('/sejour-atelier/motos')
    motos.value = data?.motos ?? []
    stats.value = {
      seuil_heures: data?.seuil_heures ?? 72,
      total: data?.total ?? 0,
      total_depassement: data?.total_depassement ?? 0,
      heures_ouvrees_moyenne: data?.heures_ouvrees_moyenne ?? 0,
      heures_ouvrees_max: data?.heures_ouvrees_max ?? 0,
      par_statut: data?.par_statut ?? {},
    }
    loadError.value = ''

    // La fiche ouverte doit refléter les données fraîches (relance, mécanicien) ;
    // si la moto a quitté l'atelier entre-temps, on referme la fiche.
    if (motoSelectionnee.value) {
      const aJour = motos.value.find(m => m.rdv_id === motoSelectionnee.value?.rdv_id)
      motoSelectionnee.value = aJour ?? null
      if (!aJour) ficheOuverte.value = false
    }
  } catch (e: any) {
    loadError.value = e?.data?.error || e?.message || 'Impossible de charger les motos en atelier.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  load()
  timer = setInterval(load, REFRESH_MS)
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
})
</script>

<style scoped>
.kpi-card {
  padding: 14px 16px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.06);
}

.kpi-card--alerte {
  border-color: rgba(245, 158, 11, 0.35);
  background: rgba(245, 158, 11, 0.07);
}

.kpi-label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.04em;
  color: #9CA3AF;
}

.kpi-value {
  margin-top: 4px;
  font-size: 26px;
  font-weight: 800;
  color: #E8E9ED;
}

.kpi-sub {
  margin-top: 2px;
  font-size: 11px;
  color: #9CA3AF;
}

.filtres {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 14px;
}

.filtre-recherche {
  flex: 1 1 240px;
  min-width: 200px;
  padding: 7px 12px;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.08);
  color: #E5E7EB;
  font-size: 12px;
}

.puce {
  padding: 6px 12px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.08);
  color: #D1D5DB;
  font-size: 12px;
  cursor: pointer;
}

.puce--active {
  background: rgba(255, 210, 0, 0.16);
  border-color: #FFD200;
  color: #FFD200;
  font-weight: 600;
}

.lien-reset,
.lien-action {
  background: transparent;
  border: none;
  color: #FFD200;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  text-decoration: underline;
}

.compteur {
  margin-left: auto;
  font-size: 11px;
  color: #9CA3AF;
}

.table-wrap {
  overflow-x: auto;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.06);
}

.table-atelier {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px;
}

.table-atelier th {
  padding: 10px 12px;
  text-align: left;
  font-size: 11px;
  letter-spacing: 0.04em;
  color: #9CA3AF;
  background: rgba(255, 255, 255, 0.03);
  white-space: nowrap;
}

.table-atelier td {
  padding: 10px 12px;
  color: #E5E7EB;
  border-top: 1px solid rgba(255, 255, 255, 0.05);
  vertical-align: top;
}

.ligne--depassement {
  background: rgba(245, 158, 11, 0.06);
}

.duree {
  font-weight: 700;
  white-space: nowrap;
}

.duree--alerte {
  color: #FBBF24;
}

.cell-sub {
  margin-top: 2px;
  font-size: 11px;
  color: #9CA3AF;
}

.badge-statut {
  padding: 2px 8px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.06);
  color: #D1D5DB;
  white-space: nowrap;
}

.vide-filtre {
  padding: 20px;
  text-align: center;
  font-size: 12px;
  color: #9CA3AF;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.06);
}
</style>
