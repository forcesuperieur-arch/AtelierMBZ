<template>
  <div class="ecran-atelier">
    <div class="page-header page-header--atelier">
      <div class="entete-texte">
        <div class="page-title">Motos en atelier</div>
        <div class="page-sub">
          Toutes les motos physiquement présentes, la plus longuement immobilisée en tête.
          L'immobilisation se compte en heures ouvrées : les jours de fermeture ne comptent pas.
        </div>
      </div>
      <AppButton
        icon="i-ri-refresh-line"
        :loading="loading"
        data-testid="en-atelier-actualiser"
        @click="load"
      >
        {{ loading ? 'Actualisation…' : 'Actualiser la liste' }}
      </AppButton>
    </div>

    <AppErrorState
      v-if="loadError"
      title="La liste des motos en atelier n'a pas pu être chargée"
      :description="loadError"
      consequence="Aucune moto n'a bougé : c'est l'affichage qui manque, pas la donnée."
      issue-label="Ouvrir le planning"
      data-testid="en-atelier-erreur"
      @retry="load"
      @issue="ouvrirPlanning"
    />

    <AppLoadingState
      v-else-if="loading && !motos.length"
      title="Chargement des motos présentes"
      :colonnes="8"
      :lignes="6"
    />

    <template v-else>
      <!-- Les deux premiers compteurs mènent à la liste qu'ils comptent (règle 3) :
           cliquer « au-delà du seuil » pose le filtre au lieu de le faire chercher. -->
      <div class="grid-4">
        <button
          type="button"
          class="kpi-card kpi-card--lien"
          :aria-label="`Afficher les ${stats.total} motos présentes, sans filtre`"
          data-testid="en-atelier-kpi-presentes"
          @click="reinitialiser"
        >
          <div class="kpi-label">Motos présentes</div>
          <div class="kpi-value">{{ stats.total }}</div>
          <div class="kpi-sub">{{ statutsResume }}</div>
        </button>

        <button
          type="button"
          class="kpi-card kpi-card--lien"
          :class="{ 'kpi-card--alerte': stats.total_depassement > 0 }"
          :aria-label="`Afficher les ${stats.total_depassement} motos au-delà de ${stats.seuil_heures} heures ouvrées`"
          data-testid="en-atelier-kpi-depassements"
          @click="voirDepassements"
        >
          <div class="kpi-label">Au-delà de {{ stats.seuil_heures }}&nbsp;h ouvrées</div>
          <div class="kpi-value">{{ stats.total_depassement }}</div>
          <div class="kpi-sub">
            {{ stats.total_depassement > 0
              ? 'chacune tient un pont sans avancer'
              : 'aucune moto ne dépasse le seuil' }}
          </div>
        </button>

        <div class="kpi-card">
          <div class="kpi-label">Immobilisation moyenne</div>
          <div class="kpi-value">{{ formatDuree(stats.heures_ouvrees_moyenne) }}</div>
          <div class="kpi-sub">hors jours de fermeture</div>
        </div>

        <div class="kpi-card">
          <div class="kpi-label">Immobilisation la plus longue</div>
          <div class="kpi-value">{{ formatDuree(stats.heures_ouvrees_max) }}</div>
          <div class="kpi-sub">hors jours de fermeture</div>
        </div>
      </div>

      <section class="liste-bloc" aria-labelledby="liste-nom-atelier">
        <header class="liste-tete">
          <AppIcon name="i-ri-motorbike-line" class="liste-icone" />
          <h2 id="liste-nom-atelier" class="liste-nom">Motos présentes</h2>
          <span class="liste-compte">{{ motos.length }}</span>
          <div class="liste-espace" />
          <label class="recherche">
            <AppIcon name="i-ri-search-line" class="recherche-icone" />
            <input
              v-model="recherche"
              type="search"
              class="filtre-recherche"
              placeholder="Immat, client, moto, mécanicien…"
              data-testid="en-atelier-recherche"
            />
            <span class="sr-only">Rechercher parmi les motos présentes</span>
          </label>
        </header>

        <div class="filtres">
          <button
            v-for="statut in statutsPresents"
            :key="statut.code"
            type="button"
            class="puce"
            :class="{ 'puce--active': statutsSelectionnes.includes(statut.code) }"
            :aria-pressed="statutsSelectionnes.includes(statut.code)"
            :data-testid="`en-atelier-filtre-${statut.code}`"
            @click="toggleStatut(statut.code)"
          >
            {{ statut.label }}<span class="puce-compte">{{ statut.count }}</span>
          </button>
          <button
            type="button"
            class="puce"
            :class="{ 'puce--active': seulementDepassements }"
            :aria-pressed="seulementDepassements"
            data-testid="en-atelier-filtre-depassements"
            @click="seulementDepassements = !seulementDepassements"
          >
            <AppIcon name="i-ri-timer-line" /> Seulement les dépassements
          </button>
          <button v-if="filtresActifs" type="button" class="lien-reset" @click="reinitialiser">
            Tout réafficher
          </button>
          <span class="compteur">{{ compteurTexte }}</span>
        </div>

        <AppEmptyState
          v-if="!motos.length"
          icon="i-ri-motorbike-line"
          title="Aucune moto dans l'atelier"
          description="Une moto entre dans cette liste au moment où elle est réceptionnée au comptoir, et en sort à la restitution. Tant que personne n'a réceptionné, l'atelier est vide."
          action-label="Ouvrir la réception du jour"
          @action="ouvrirReception"
        />

        <AppFilterEmptyState
          v-else-if="!motosFiltrees.length"
          title="Aucune moto présente ne correspond"
          :nombre-filtres="nombreFiltres"
          :suggestion="suggestionFiltre"
          @retirer="retirerFiltre"
          @effacer="reinitialiser"
        />

        <div v-else class="table-wrap">
          <table class="table-atelier">
            <caption class="sr-only">
              Motos présentes à l'atelier, de la plus longuement immobilisée à la plus récente.
            </caption>
            <thead>
              <tr>
                <!-- Le tri vient du serveur et ne se règle pas ici : `aria-sort` dit
                     l'ordre réel plutôt que de promettre une colonne cliquable. -->
                <th scope="col" class="col-duree" aria-sort="descending">Immobilisée depuis</th>
                <th scope="col">Moto</th>
                <th scope="col">Client</th>
                <th scope="col">État</th>
                <th scope="col">Mécanicien</th>
                <th scope="col">Pont</th>
                <th scope="col">Reçue le</th>
                <th scope="col" class="col-action"><span class="sr-only">Ouvrir le dossier</span></th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="moto in motosFiltrees"
                :key="moto.rdv_id"
                :class="{ 'ligne--depassement': moto.en_depassement }"
                data-testid="en-atelier-ligne"
              >
                <td class="col-duree">
                  <span class="duree" :class="{ 'duree--alerte': moto.en_depassement }">
                    {{ formatDuree(moto.heures_ouvrees) }}
                  </span>
                  <!-- Le seuil franchi est nommé : sans lui, « en retard » est une
                       couleur sans mesure et personne ne sait de combien. -->
                  <div v-if="depassementNote(moto)" class="duree-note">{{ depassementNote(moto) }}</div>
                </td>
                <td>
                  <div class="cell-titre">
                    {{ moto.vehicule || 'Moto' }}
                    <span class="immat">{{ moto.plaque || 'Immat inconnue' }}</span>
                  </div>
                  <div v-if="moto.type_intervention" class="cell-sub">{{ moto.type_intervention }}</div>
                </td>
                <td>
                  <div class="cell-titre">{{ moto.client_nom || 'Client inconnu' }}</div>
                  <div class="cell-sub">{{ moto.client_telephone || 'Aucun numéro enregistré' }}</div>
                  <div v-if="moto.derniere_relance" class="cell-sub">
                    Relancé le {{ formatDate(moto.derniere_relance) }}
                  </div>
                </td>
                <td>
                  <span class="badge-statut" :class="`badge-statut--${statutTon(moto.statut)}`">
                    {{ statutLabel(moto.statut) }}
                  </span>
                </td>
                <td>
                  <span v-if="moto.mecanicien">{{ moto.mecanicien }}</span>
                  <!-- Une moto à l'atelier sans mécanicien est une anomalie, pas une
                       valeur vide : le filet pointillé du design system la désigne. -->
                  <span v-else class="sans-affectation">Sans affectation</span>
                </td>
                <td>
                  <span v-if="moto.pont_nom">{{ moto.pont_nom }}</span>
                  <span v-else class="vide">Hors pont</span>
                </td>
                <td>
                  {{ formatDateHeure(moto.recu_le) }}
                  <div class="cell-sub">RDV du {{ formatDate(moto.date_rdv) }} à {{ moto.heure_rdv }}</div>
                </td>
                <td class="col-action">
                  <button
                    type="button"
                    class="lien-action"
                    :aria-label="`Traiter ${moto.plaque || moto.vehicule || 'cette moto'} : relance, mécanicien, avancement`"
                    data-testid="en-atelier-traiter"
                    @click="ouvrirFiche(moto)"
                  >
                    Traiter
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
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
 *
 * La colonne structurante est la durée d'immobilisation, et rien d'autre : une
 * moto immobile est un pont pris et un client qui attend. Elle ouvre la ligne,
 * porte la graisse, et le seuil qu'elle franchit est écrit sous elle.
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

/* Espace insécable : le design system interdit qu'un nombre soit séparé de son
   unité par un retour à la ligne — « 28 412 » d'un côté, « km » de l'autre. */
const NBSP = ' '

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
  en_attente_pieces: 'En attente de pièces',
  en_attente_reprise: 'En attente de reprise',
  en_gardiennage: 'En gardiennage',
}

/* Le trio surface + filet + encre du design system, choisi par ce que l'état
   coûte à l'atelier : bleu quand la moto vient d'arriver, vert quand quelqu'un
   travaille dessus, orange dès que l'avancement dépend d'un tiers. */
const STATUT_TON: Record<string, string> = {
  reception: 'info',
  en_cours: 'success',
  en_pause: 'warning',
  en_attente_pieces: 'warning',
  en_attente_reprise: 'warning',
  en_gardiennage: 'neutre',
}

/**
 * Le résumé par statut se lit comme une phrase (« 3 en cours · 2 en attente de
 * pièces »), ce qu'un simple `label + s` ne sait pas produire : il écrivait
 * « 2 en pauses ». Chaque état porte donc ses deux formes.
 */
const STATUT_RESUME: Record<string, { un: string; plusieurs: string }> = {
  reception: { un: 'réceptionnée', plusieurs: 'réceptionnées' },
  en_cours: { un: 'en cours', plusieurs: 'en cours' },
  en_pause: { un: 'en pause', plusieurs: 'en pause' },
  en_attente_pieces: { un: 'en attente de pièces', plusieurs: 'en attente de pièces' },
  en_attente_reprise: { un: 'en attente de reprise', plusieurs: 'en attente de reprise' },
  en_gardiennage: { un: 'en gardiennage', plusieurs: 'en gardiennage' },
}

function statutLabel(statut: string): string {
  return STATUT_LABELS[statut] || statut
}

function statutTon(statut: string): string {
  return STATUT_TON[statut] || 'neutre'
}

const statutsPresents = computed(() =>
  Object.entries(stats.value.par_statut)
    .map(([code, count]) => ({ code, label: statutLabel(code), count }))
    .sort((a, b) => b.count - a.count),
)

const statutsResume = computed(() =>
  statutsPresents.value
    .map((s) => {
      const formes = STATUT_RESUME[s.code]
      const mot = formes ? (s.count > 1 ? formes.plusieurs : formes.un) : s.label.toLowerCase()
      return `${s.count} ${mot}`
    })
    .join(' · ') || '—',
)

const nombreFiltres = computed(
  () =>
    (recherche.value.trim() ? 1 : 0)
    + statutsSelectionnes.value.length
    + (seulementDepassements.value ? 1 : 0),
)

const filtresActifs = computed(() => nombreFiltres.value > 0)

/**
 * Le filtrage est isolé de son état pour pouvoir être rejoué SANS un critère :
 * c'est ce qui permet de chiffrer, sur l'écran filtré à blanc, combien de motos
 * réapparaîtraient si l'on retirait tel filtre plutôt qu'un autre.
 */
function filtrer(terme: string, statuts: string[], depassements: boolean): MotoEnAtelier[] {
  const t = terme.trim().toLowerCase()
  return motos.value.filter((moto) => {
    if (depassements && !moto.en_depassement) return false
    if (statuts.length && !statuts.includes(moto.statut)) return false
    if (!t) return true
    return [moto.plaque, moto.vehicule, moto.client_nom, moto.mecanicien, moto.type_intervention]
      .some(champ => (champ || '').toLowerCase().includes(t))
  })
}

const motosFiltrees = computed(() =>
  filtrer(recherche.value, statutsSelectionnes.value, seulementDepassements.value),
)

const compteurTexte = computed(() => {
  const total = motos.value.length
  if (!filtresActifs.value) return `${total} moto${total > 1 ? 's' : ''} présente${total > 1 ? 's' : ''}`
  const affichees = motosFiltrees.value.length
  const masquees = total - affichees
  return `${affichees} sur ${total} · ${masquees} masquée${masquees > 1 ? 's' : ''} par les filtres`
})

/** Le retrait de filtre le plus rentable, chiffré. `null` si aucun ne rouvre rien. */
const suggestionFiltre = computed(() => {
  const actuel = motosFiltrees.value.length
  const candidats: { code: string; filtre: string; nombre: number }[] = []

  if (recherche.value.trim()) {
    candidats.push({
      code: 'recherche',
      filtre: recherche.value.trim(),
      nombre: filtrer('', statutsSelectionnes.value, seulementDepassements.value).length,
    })
  }
  if (seulementDepassements.value) {
    candidats.push({
      code: 'depassements',
      filtre: 'Seulement les dépassements',
      nombre: filtrer(recherche.value, statutsSelectionnes.value, false).length,
    })
  }
  for (const code of statutsSelectionnes.value) {
    candidats.push({
      code: `statut:${code}`,
      filtre: statutLabel(code),
      nombre: filtrer(
        recherche.value,
        statutsSelectionnes.value.filter(s => s !== code),
        seulementDepassements.value,
      ).length,
    })
  }

  const meilleur = candidats.filter(c => c.nombre > actuel).sort((a, b) => b.nombre - a.nombre)[0]
  return meilleur ? { ...meilleur, objet: meilleur.nombre > 1 ? 'motos' : 'moto' } : null
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

/**
 * Le compteur des dépassements mène à sa propre liste (règle 3) : on écarte les
 * autres filtres, sans quoi la liste affichée ne vaudrait plus le chiffre cliqué.
 */
function voirDepassements() {
  recherche.value = ''
  statutsSelectionnes.value = []
  seulementDepassements.value = true
}

/** Retire exactement le filtre que la suggestion vient de chiffrer. */
function retirerFiltre() {
  const suggestion = suggestionFiltre.value
  if (!suggestion) return
  if (suggestion.code === 'recherche') {
    recherche.value = ''
    return
  }
  if (suggestion.code === 'depassements') {
    seulementDepassements.value = false
    return
  }
  statutsSelectionnes.value = statutsSelectionnes.value.filter(
    code => `statut:${code}` !== suggestion.code,
  )
}

/** Durée en heures ouvrées, écrite à la française : « 6 h 20 », « 2 j 5 h ». */
function formatDuree(heures: number): string {
  const total = Math.max(0, heures)
  if (total >= 24) {
    const jours = Math.floor(total / 24)
    const reste = Math.round(total % 24)
    // 47,8 h arrondit à « 1 j 24 h » : on remonte le jour plutôt que d'écrire 24 h.
    if (reste >= 24) return `${jours + 1}${NBSP}j`
    return reste > 0 ? `${jours}${NBSP}j ${reste}${NBSP}h` : `${jours}${NBSP}j`
  }
  const h = Math.floor(total)
  const minutes = Math.round((total - h) * 60)
  if (minutes >= 60) return `${h + 1}${NBSP}h`
  return minutes > 0 ? `${h}${NBSP}h ${String(minutes).padStart(2, '0')}` : `${h}${NBSP}h`
}

/** L'écart au seuil, en toutes lettres : une couleur seule ne dit pas « de combien ». */
function depassementNote(moto: MotoEnAtelier): string {
  const ecart = moto.heures_ouvrees - stats.value.seuil_heures
  if (!moto.en_depassement || ecart <= 0) return ''
  return `+ ${formatDuree(ecart)} au-delà de ${stats.value.seuil_heures}${NBSP}h`
}

function formatDate(valeur: string): string {
  if (!valeur) return '—'
  const d = new Date(valeur)
  return Number.isNaN(d.getTime())
    ? valeur
    : d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: '2-digit' })
}

/** Date et heure d'arrivée : c'est l'instant où le compteur d'immobilisation part. */
function formatDateHeure(valeur: string): string {
  if (!valeur) return '—'
  const d = new Date(valeur)
  if (Number.isNaN(d.getTime())) return valeur
  const jour = d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: '2-digit' })
  const heure = d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
  return `${jour} à ${heure}`
}

function ouvrirFiche(moto: MotoEnAtelier) {
  motoSelectionnee.value = moto
  ficheOuverte.value = true
}

function ouvrirPlanning() {
  navigateTo('/planning')
}

function ouvrirReception() {
  navigateTo('/reception')
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
    loadError.value = messageErreur(e, "les motos présentes n'ont pas pu être listées")
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
.page-header--atelier {
  align-items: flex-start;
  justify-content: space-between;
}

.entete-texte {
  min-width: 0;
}

/* Le design system pose 44 px de cible au bureau : `.btn` s'arrête à 38 px. */
.ecran-atelier :deep(.btn) {
  min-height: var(--pk-target-desk);
}

/* ---- Les quatre mesures ---- */
.grid-4 {
  gap: 12px;
  margin-bottom: 16px;
}

.kpi-card {
  display: block;
  width: 100%;
  padding: 14px 16px;
  text-align: left;
  border-radius: var(--pk-radius-card);
  background: var(--pk-surface);
  border: 1px solid var(--pk-border);
  transition: border-color var(--pk-duration-state) var(--pk-easing),
    background var(--pk-duration-state) var(--pk-easing);
}

.kpi-card--lien {
  cursor: pointer;
  font: inherit;
  color: inherit;
  min-height: var(--pk-target-desk);
}

.kpi-card--lien:hover {
  border-color: var(--pk-border-strong);
}

.kpi-card--alerte {
  background: var(--pk-warning-surface);
  border-color: var(--pk-warning-line);
}

/* Le survol ne doit pas effacer la couleur d'alerte : il la fonce. */
.kpi-card--alerte.kpi-card--lien:hover {
  border-color: var(--pk-warning-ink);
}

/* Surtitre : la seule casse capitale autorisée avec les mots de statut. */
.kpi-label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--pk-ink-muted);
}

.kpi-card--alerte .kpi-label,
.kpi-card--alerte .kpi-value,
.kpi-card--alerte .kpi-sub {
  color: var(--pk-warning-ink);
}

.kpi-value {
  margin-top: 4px;
  font-size: 26px;
  font-weight: 700;
  line-height: 1.1;
  color: var(--pk-ink);
}

.kpi-sub {
  margin-top: 2px;
  font-size: 11px;
  color: var(--pk-ink-muted);
}

/* ---- Le bloc de liste ---- */
.liste-bloc {
  background: var(--pk-surface);
  border: 1px solid var(--pk-border);
  border-radius: var(--pk-radius-card);
  overflow: hidden;
}

.liste-tete {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  border-bottom: 1px solid var(--pk-border);
  flex-wrap: wrap;
}

.liste-icone {
  font-size: 17px;
  color: var(--pk-ink);
}

.liste-nom {
  margin: 0;
  font-size: 15px;
  font-weight: 600;
  letter-spacing: 0;
  color: var(--pk-ink);
}

/* Compteur en aplat inverse : il se lit d'un coup d'œil sans être une alerte. */
.liste-compte {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 20px;
  height: 20px;
  padding: 0 6px;
  border-radius: var(--pk-radius-pill);
  background: var(--pk-border-strong);
  color: var(--pk-surface);
  font-size: 11px;
  font-weight: 700;
}

.liste-espace {
  flex: 1;
}

.recherche {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 0 1 280px;
  min-width: 200px;
  min-height: var(--pk-target-desk);
  padding: 0 12px;
  border-radius: var(--pk-radius-card);
  background: var(--pk-surface-raised);
  border: 1px solid var(--pk-border-control);
  transition: border-color var(--pk-duration-state) var(--pk-easing);
}

.recherche:focus-within {
  border-color: var(--pk-border-strong);
}

.recherche-icone {
  font-size: 15px;
  color: var(--pk-ink-muted);
}

/* La feuille globale habille TOUT `input` de `.content` avec une spécificité de
   (0,4,1) — fond, bordure, 40 px de haut. Sans cette chaîne de classes, le champ
   garderait cet habillage au lieu de se fondre dans la barre de recherche, qui
   porte déjà le filet et l'icône. L'anneau de focus, lui, n'est jamais retiré :
   seuls le liseré et le halo de la feuille globale sont neutralisés. */
.ecran-atelier .liste-tete .recherche .filtre-recherche {
  flex: 1;
  width: auto;
  min-width: 0;
  min-height: 0;
  padding: 0;
  border: none;
  border-radius: 0;
  background: transparent;
  color: var(--pk-ink);
  font-size: 12px;
}

.ecran-atelier .liste-tete .recherche .filtre-recherche:focus {
  border-color: transparent;
  box-shadow: none;
}

/* ---- Les filtres ---- */
.filtres {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: var(--pk-target-gap);
  padding: 10px 16px;
  border-bottom: 1px solid var(--pk-border);
  background: var(--pk-surface-raised);
}

.puce {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  /* Cible de bureau du design system : ces pilules se touchent aussi au doigt
     sur la tablette du comptoir, jamais seulement à la souris. */
  min-height: var(--pk-target-desk);
  padding: 0 14px;
  border-radius: var(--pk-radius-pill);
  background: var(--pk-surface);
  border: 1px solid var(--pk-border-control);
  color: var(--pk-ink-quiet);
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  transition: background var(--pk-duration-state) var(--pk-easing),
    border-color var(--pk-duration-state) var(--pk-easing),
    color var(--pk-duration-state) var(--pk-easing);
}

.puce:hover {
  border-color: var(--pk-border-strong);
  color: var(--pk-ink);
}

/* Sélection = aplat plein, comme le pose le design system. */
.puce--active {
  background: var(--pk-border-strong);
  border-color: var(--pk-border-strong);
  color: var(--pk-surface);
  font-weight: 600;
}

.puce-compte {
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.lien-reset {
  min-height: var(--pk-target-desk);
  padding: 0 4px;
  background: transparent;
  border: none;
  /* Le DS écrit ses liens en encre soulignée : le jaune de texte ne tenait pas
     4,5:1 sur la ligne teintée. */
  color: var(--pk-ink);
  text-decoration: underline;
  text-underline-offset: 3px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.compteur {
  margin-left: auto;
  font-size: 11px;
  color: var(--pk-ink-muted);
  font-variant-numeric: tabular-nums;
}

/* ---- La liste ---- */
.table-wrap {
  overflow-x: auto;
}

/* Les cinq règles qui suivent portent le préfixe `.ecran-atelier` pour une seule
   raison : la feuille globale habille tout `table` de `.content` (fond, rayon,
   padding, filets) avec une spécificité que la seule classe scopée ne dépasse
   pas. Le tableau garderait sinon la densité générique au lieu de celle du
   design system. */
.ecran-atelier .table-atelier {
  width: 100%;
  border-collapse: collapse;
  border-radius: 0;
  background: transparent;
  font-size: 12px;
}

.ecran-atelier .table-atelier th {
  padding: 10px 16px;
  text-align: left;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--pk-ink-muted);
  background: var(--pk-surface-raised);
  border-bottom: 1px solid var(--pk-border);
  white-space: nowrap;
}

.ecran-atelier .table-atelier td {
  padding: 14px 16px;
  color: var(--pk-ink);
  border-top: 1px solid var(--pk-border-quiet);
  border-bottom: none;
  vertical-align: top;
}

.ecran-atelier .table-atelier tbody tr:first-child td {
  border-top: none;
}

.ecran-atelier .table-atelier tbody tr:hover td {
  background: var(--pk-surface-raised);
}

/* La colonne structurante : elle ouvre la ligne et un filet la sépare du reste,
   comme la colonne d'heure de la file de réception. */
.col-duree {
  width: 132px;
  border-right: 1px solid var(--pk-border-quiet);
}

.col-action {
  width: 1%;
  white-space: nowrap;
}

/* Le dépassement se dit par la teinte ET par un liseré : la couleur seule ne
   se voit pas sur un écran d'atelier en plein jour. */
.ecran-atelier .table-atelier tbody tr.ligne--depassement td,
.ecran-atelier .table-atelier tbody tr.ligne--depassement:hover td {
  background: var(--pk-warning-surface);
}

.ecran-atelier .table-atelier tbody tr.ligne--depassement td:first-child {
  box-shadow: inset 3px 0 0 var(--pk-warning-line);
}

.duree {
  display: block;
  font-size: 18px;
  font-weight: 700;
  line-height: 1.2;
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
}

.duree--alerte {
  color: var(--pk-warning-ink);
}

.duree-note {
  margin-top: 2px;
  font-size: 11px;
  font-weight: 600;
  color: var(--pk-warning-ink);
}

.cell-titre {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  font-size: 13px;
  font-weight: 600;
  color: var(--pk-ink);
}

.cell-sub {
  margin-top: 2px;
  font-size: 11px;
  color: var(--pk-ink-muted);
}

/* La plaque se lit comme sur le carénage : encadrée, resserrée, jamais en gras. */
.immat {
  padding: 1px 6px;
  border: 1px solid var(--pk-border-control);
  border-radius: var(--pk-radius-block);
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.04em;
  color: var(--pk-ink-quiet);
  white-space: nowrap;
}

.badge-statut {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: var(--pk-radius-pill);
  border: 1px solid transparent;
  font-size: 11px;
  font-weight: 600;
  white-space: nowrap;
}

.badge-statut--info {
  background: var(--pk-info-surface);
  border-color: var(--pk-info-line);
  color: var(--pk-info-ink);
}

.badge-statut--success {
  background: var(--pk-success-surface);
  border-color: var(--pk-success-line);
  color: var(--pk-success-ink);
}

.badge-statut--warning {
  background: var(--pk-warning-surface);
  border-color: var(--pk-warning-line);
  color: var(--pk-warning-ink);
}

.badge-statut--neutre {
  background: var(--pk-neutral-surface);
  border-color: var(--pk-border);
  color: var(--pk-ink-quiet);
}

.sans-affectation {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border: 1px dashed var(--pk-border-control);
  border-radius: var(--pk-radius-pill);
  font-size: 11px;
  font-weight: 600;
  color: var(--pk-ink-quiet);
  white-space: nowrap;
}

.vide {
  color: var(--pk-ink-muted);
}

.lien-action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: var(--pk-target-desk);
  padding: 0 14px;
  border-radius: var(--pk-radius-pill);
  border: 1px solid var(--pk-border-control);
  background: var(--pk-surface);
  color: var(--pk-ink);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: background var(--pk-duration-state) var(--pk-easing),
    border-color var(--pk-duration-state) var(--pk-easing);
}

.lien-action:hover {
  background: var(--pk-surface-raised);
  border-color: var(--pk-border-strong);
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip-path: inset(50%);
  white-space: nowrap;
}
</style>
