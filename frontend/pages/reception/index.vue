<template>
  <div data-testid="reception-page" class="rc">
    <!-- Titre, bande de course jaune, puis la bande de mesures : la maquette 2b
         met les trois chiffres du matin AU-DESSUS de la liste, jamais dedans. -->
    <header class="rc-tete">
      <div class="rc-tete-titres">
        <h1 class="rc-titre">Réception du matin</h1>
        <span class="rc-filet-course" aria-hidden="true" />
        <p class="rc-sous-titre">{{ todayLabel }} — check-in et état des lieux d'entrée.</p>
      </div>

      <div class="rc-mesures">
        <div class="rc-mesure">
          <span class="rc-mesure-label">RDV du jour</span>
          <span class="rc-mesure-valeur">{{ kpis.total }}</span>
        </div>
        <div class="rc-mesure">
          <span class="rc-mesure-label">Check-ins signés</span>
          <span class="rc-mesure-valeur rc-mesure-valeur--fait">{{ kpis.signes }}</span>
        </div>
        <div class="rc-mesure">
          <span class="rc-mesure-label">Restants</span>
          <span class="rc-mesure-valeur rc-mesure-valeur--reste">{{ kpis.restants }}</span>
        </div>
      </div>
    </header>

    <!-- 29c : la forme de la liste est déjà là, la page ne saute pas à
         l'arrivée des rendez-vous. -->
    <AppLoadingState
      v-if="loading"
      title="Lecture des motos attendues aujourd'hui"
      :colonnes="4"
      :lignes="5"
    />

    <AppErrorState
      v-else-if="loadError"
      title="La liste des motos attendues n'a pas pu être lue"
      :description="loadError"
      consequence="Aucun check-in n'a été perdu : les brouillons déjà enregistrés sont intacts."
      action-label="Relire la liste du jour"
      @retry="loadData()"
    />

    <!-- 29a : ne pas dire « c'est vide », dire par où la moto arrive ici. -->
    <AppEmptyState
      v-else-if="!receptionRdvs.length"
      icon="i-ri-inbox-line"
      title="Aucune moto attendue aujourd'hui"
      description="Les motos attendues viennent des rendez-vous posés au planning : un rendez-vous confirmé apparaît sur cette liste le matin de sa date."
      action-label="Relire la liste du jour"
      @action="loadData()"
    />

    <section v-else class="rc-cadre">
      <div class="rc-cadre-tete">
        <AppIcon name="i-ri-inbox-line" class="rc-cadre-glyphe" aria-hidden="true" />
        <span class="rc-cadre-titre">Motos attendues aujourd'hui</span>
        <span class="rc-compteur">{{ receptionRdvs.length }}</span>
        <div class="rc-cadre-espace" />
        <!-- Règle 3 : un chiffre mène quelque part. Chaque pastille est
             chiffrée ET filtre la liste, au lieu de compter dans le vide. -->
        <button
          v-for="f in filtres"
          :key="f.cle"
          type="button"
          class="rc-pastille-filtre"
          :class="{ 'est-choisi': filtre === f.cle }"
          :aria-pressed="filtre === f.cle"
          :data-testid="`reception-filtre-${f.cle}`"
          @click="filtre = f.cle"
        >{{ f.libelle }} · {{ f.nombre }}</button>
      </div>

      <!-- 29b : la donnée existe, c'est le filtre qui la cache. On dit combien
           reparaîtrait en le retirant. -->
      <AppFilterEmptyState
        v-if="!rdvsAffiches.length"
        class="rc-filtre-vide"
        title="Aucune moto dans cette vue"
        :nombre-filtres="1"
        :suggestion="suggestionFiltre"
        @retirer="filtre = 'toutes'"
        @effacer="filtre = 'toutes'"
      />

      <ol v-else class="rc-lignes">
        <li
          v-for="rdv in rdvsAffiches"
          :key="rdv.id"
          class="rc-ligne"
          :class="{ 'est-a-faire': !estSigne(rdv.id) }"
          data-testid="reception-rdv-card"
        >
          <!-- L'heure en ancre à gauche : c'est par elle que le comptoir lit
               sa matinée, pas par le nom du client. -->
          <div class="rc-heure">
            <span class="rc-heure-valeur">{{ rdv.heure_debut || '—' }}</span>
            <span v-if="dureeLisible(rdv)" class="rc-heure-duree">{{ dureeLisible(rdv) }}</span>
          </div>
          <span class="rc-separateur" aria-hidden="true" />

          <div class="rc-identite">
            <p class="rc-client">{{ rdv.client_nom || 'Client inconnu' }}</p>
            <p class="rc-moto">
              {{ rdv.vehicule_info || 'Moto non renseignée' }}
              <span v-if="rdv.vehicule_plaque" class="rc-immat">{{ rdv.vehicule_plaque }}</span>
            </p>
            <p v-if="rdv.type_intervention" class="rc-travaux">{{ rdv.type_intervention }}</p>
          </div>

          <div class="rc-marques">
            <!-- Pointillé = anomalie, jamais un statut normal : la moto arrive
                 sans pont ni mécanicien, quelqu'un doit trancher avant midi. -->
            <span v-if="sansAffectation(rdv)" class="rc-pastille rc-pastille--pointille">
              <AppIcon name="i-ri-tools-line" aria-hidden="true" />Sans affectation
            </span>
            <StatusBadge :status="rdv.status" />
            <span
              class="rc-pastille"
              :class="classeEdl(rdv.id)"
              data-testid="edl-badge"
            >{{ edlBadgeLabel(rdv.id) }}</span>
          </div>

          <div class="rc-actions">
            <button
              v-if="edlByRdv[rdv.id]?.signe && edlByRdv[rdv.id]?.pdf_disponible"
              class="btn rc-action"
              type="button"
              data-testid="btn-edl-pdf"
              @click="openEdlPdf(edlByRdv[rdv.id])"
            ><AppIcon name="i-ri-file-text-line" /> PDF de l'EDL</button>
            <button
              class="btn rc-action"
              type="button"
              data-testid="btn-edl-panneau"
              @click="ouvrirEtatDesLieux(rdv)"
            ><AppIcon name="i-ri-camera-line" /> État des lieux</button>
            <button
              class="btn rc-action"
              :class="estSigne(rdv.id) ? 'btn-secondary' : 'btn-primary'"
              type="button"
              data-testid="btn-checkin"
              @click="openCheckin(rdv)"
            >{{ checkinButtonLabel(rdv.id) }}</button>
          </div>
        </li>
      </ol>
    </section>

    <!-- État des lieux photo (47b) : les deux séries côte à côte, entrée et
         sortie dans le même ordre — c'est la comparaison qui fait preuve. Le
         poste de travail ne se quitte pas : ça s'ouvre en panneau. -->
    <AppEtatDesLieuxPanel
      :open="edlPanneauOuvert"
      :rdv-id="edlPanneauRdv?.id ?? null"
      :vehicule="edlPanneauRdv?.vehicule_info || ''"
      :plaque="edlPanneauRdv?.vehicule_plaque || ''"
      :client="edlPanneauRdv?.client_nom || ''"
      @close="edlPanneauOuvert = false"
    />

    <!-- ===== Check-in — panneau de travail (règle 4) =====
         La file du matin reste lisible derrière : le comptoir garde sous les
         yeux qui attend pendant qu'il saisit le relevé de la moto présente. -->
    <AppSidePanel
      :open="checkinOpen"
      icon="i-ri-inbox-line"
      :title="titreCheckin"
      :subtitle="sousTitreCheckin"
      @close="checkinOpen = false"
    >
      <template v-if="checkinRdv">
        <!-- Document signé : plus rien ne se saisit, tout se relit. -->
        <template v-if="isSigned">
          <AppPanelSection label="État des lieux" :aside="currentEdl?.signed_at ? formatDateTime(currentEdl.signed_at) : ''">
            <div class="rc-fige" data-testid="checkin-signed">
              <AppIcon name="i-ri-checkbox-circle-line" class="rc-fige-glyphe" aria-hidden="true" />
              <p class="rc-fige-texte">
                Signé<span v-if="currentEdl?.signed_by"> par {{ currentEdl.signed_by }}</span>.
                Le document est figé : ni le relevé ni les photos d'entrée ne bougeront plus.
              </p>
            </div>
          </AppPanelSection>

          <AppPanelSection label="Relevés d'entrée">
            <div class="rc-releve"><span>Compteur</span><strong>{{ formatKm(currentEdl?.kilometrage) }}</strong></div>
            <div class="rc-releve"><span>Carburant</span><strong>{{ fuelLabel(currentEdl?.niveau_carburant) }}</strong></div>
            <div class="rc-releve"><span>Photos d'entrée</span><strong>{{ photosCount }}</strong></div>
          </AppPanelSection>

          <AppPanelSection v-if="currentEdl?.observations" label="Réserves notées à l'entrée">
            <p class="rc-reserve">{{ currentEdl.observations }}</p>
            <p class="rc-note">Reprises telles quelles sur l'OR et sur le bon de sortie.</p>
          </AppPanelSection>
        </template>

        <!-- Saisie -->
        <template v-else>
          <AppPanelSection label="Relevés d'entrée">
            <label class="rc-champ-label" for="checkin-km">Kilométrage au compteur</label>
            <input
              id="checkin-km"
              v-model.number="form.kilometrage"
              type="number"
              min="0"
              step="1"
              inputmode="numeric"
              class="form-input rc-km"
              placeholder="ex : 24350"
              data-testid="checkin-km"
              :disabled="hydrating"
            />
            <p class="rc-aide">Le relevé d'arrivée sert de référence à la restitution : sans lui, l'écart de kilomètres ne se prouve pas.</p>

            <span class="rc-champ-label rc-champ-label--espace" id="checkin-carburant-label">Niveau de carburant</span>
            <div class="rc-jauge" role="radiogroup" aria-labelledby="checkin-carburant-label">
              <button
                v-for="(level, idx) in FUEL_LEVELS"
                :key="level.value"
                type="button"
                class="rc-jauge-cran"
                :class="{ 'est-choisi': form.niveau_carburant === level.value, 'est-rempli': isFuelFilled(idx) }"
                role="radio"
                :aria-checked="form.niveau_carburant === level.value"
                :data-testid="`fuel-segment-${level.value}`"
                :disabled="hydrating"
                @click="form.niveau_carburant = level.value"
              >
                <span class="rc-jauge-barre" />
                <span class="rc-jauge-label">{{ level.label }}</span>
              </button>
            </div>
          </AppPanelSection>

          <AppPanelSection label="Réserves constatées à l'arrivée">
            <label class="sr-only" for="checkin-obs">Réserves constatées à l'arrivée</label>
            <textarea
              id="checkin-obs"
              v-model="form.observations"
              class="form-input rc-observations"
              rows="3"
              placeholder="Rayure carter droit, chocs de jante, top-case laissé sur la moto…"
              data-testid="checkin-observations"
              :disabled="hydrating"
            />
            <p class="rc-aide">Ce qui est écrit ici est repris tel quel sur l'OR et sur le bon de sortie.</p>
          </AppPanelSection>

          <AppPanelSection label="Photos d'entrée">
            <p
              class="rc-compte-photos"
              :class="{ 'est-court': photosCount < PHOTOS_MIN }"
              data-testid="checkin-photos-count"
            >{{ photosCount }} sur {{ PHOTOS_MIN }} minimum</p>
            <label class="rc-prise-photo">
              <input
                type="file"
                accept="image/*"
                capture="environment"
                multiple
                class="sr-only"
                data-testid="checkin-photo-input"
                :disabled="uploadingPhotos || hydrating"
                @change="onPhotosSelected"
              />
              <span>
                <AppIcon :name="uploadingPhotos ? 'i-ri-hourglass-line' : 'i-ri-camera-line'" />
                {{ uploadingPhotos ? 'Envoi des clichés…' : 'Prendre les photos de la moto' }}
              </span>
            </label>

            <div v-if="sessionPhotos.length" class="rc-cliches">
              <img
                v-for="photo in sessionPhotos"
                :key="photo.id"
                :src="photo.url"
                :alt="photo.filename"
                class="rc-cliche"
                @click="openPhotoInTab(photo.url)"
              />
            </div>

            <p v-if="photosCount > sessionPhotos.length" class="rc-aide">
              {{ photosCount - sessionPhotos.length }} cliché{{ photosCount - sessionPhotos.length > 1 ? 's' : '' }} déjà enregistré{{ photosCount - sessionPhotos.length > 1 ? 's' : '' }} sur ce rendez-vous.
            </p>
            <p class="rc-aide">Une photo ne se supprime pas : reprenez le cliché, l'ancien reste au dossier.</p>
          </AppPanelSection>

          <AppPanelSection v-if="draftError || draftSavedAt">
            <div v-if="draftError" data-testid="checkin-error"><AppFieldError :message="draftError" /></div>
            <p v-else class="rc-brouillon"><AppIcon name="i-ri-save-line" /> Brouillon enregistré à {{ draftSavedAt }}</p>
          </AppPanelSection>
        </template>
      </template>

      <template #footer>
        <template v-if="checkinRdv && !isSigned">
          <!-- Règle 5 : dire ce qui manque et ce que ça empêche, plutôt que de
               cacher la raison derrière l'infobulle d'un bouton grisé. -->
          <p v-if="manqueAvantSignature" class="rc-blocage">{{ manqueAvantSignature }}</p>
          <button
            class="btn btn-primary rc-action"
            type="button"
            :disabled="!canSign || savingDraft || hydrating"
            data-testid="btn-faire-signer"
            @click="openSignature"
          ><AppIcon name="i-ri-quill-pen-line" /> Faire signer le client</button>
          <button
            class="btn rc-action"
            type="button"
            :disabled="savingDraft || hydrating"
            data-testid="btn-save-draft"
            @click="saveDraft(true)"
          ><AppIcon v-if="!savingDraft" name="i-ri-save-line" />{{ savingDraft ? 'Enregistrement…' : 'Enregistrer le brouillon' }}</button>
        </template>

        <template v-else-if="checkinRdv">
          <button
            v-if="currentEdl?.pdf_disponible"
            class="btn rc-action"
            type="button"
            data-testid="btn-edl-pdf-modal"
            @click="openEdlPdf(currentEdl)"
          ><AppIcon name="i-ri-file-text-line" /> Imprimer l'état des lieux</button>
          <button
            v-if="checkinRdv.status === 'confirme'"
            class="btn btn-primary rc-action"
            type="button"
            data-testid="btn-passer-reception"
            :disabled="transitioning"
            @click="passerEnReception(checkinRdv)"
          ><AppIcon v-if="!transitioning" name="i-ri-motorbike-line" />{{ transitioning ? 'Réception en cours…' : libelleReception(checkinRdv) }}</button>
        </template>

        <button class="btn btn-ghost rc-action" type="button" :disabled="hydrating" @click="checkinOpen = false">Fermer le panneau</button>
      </template>
    </AppSidePanel>

    <!-- ===== Signature client ===== -->
    <SignatureModal
      v-if="showSignature"
      title="Signature du client — état des lieux d'entrée"
      confirm-label="Valider et signer"
      :saving="signing"
      :error="signError"
      @close="showSignature = false"
      @signed="onSigned"
    />
  </div>
</template>

<script setup lang="ts">
import { todayLocalISO } from '~/composables/useDates'

const api = useApi()
const config = useRuntimeConfig()
const apiBase = config.public.apiBase as string
const rdvStore = useRdvStore()
const toast = useToast()
const { openPdf } = usePdfDownload()

const PHOTOS_MIN = 4
const DRAFT_DEBOUNCE_MS = 900
const RECEPTION_STATUTS = ['confirme', 'reception']

/** Espace insécable : « 1 h 20 », « 28 412 km » ne se coupent jamais en bout de ligne. */
const INSECABLE = '\u00A0'

const FUEL_LEVELS = [
  { value: 'vide', label: 'Vide' },
  { value: 'quart', label: '1/4' },
  { value: 'moitie', label: '1/2' },
  { value: 'trois_quarts', label: '3/4' },
  { value: 'plein', label: 'Plein' },
] as const

const ERROR_MESSAGES: Record<string, string> = {
  DEJA_SIGNE: "L'état des lieux est déjà signé : il ne peut plus être modifié.",
  DONNEES_INCOMPLETES: 'Le kilométrage et le niveau de carburant doivent être renseignés avant la signature.',
  SIGNATURE_INVALIDE: 'Signature invalide : veuillez recommencer dans le cadre.',
  KILOMETRAGE_INVALIDE: 'Kilométrage invalide : un nombre entier positif est attendu.',
  CARBURANT_INVALIDE: 'Niveau de carburant invalide : choisissez un des 5 niveaux.',
  ETAT_DES_LIEUX_REQUIS: "L'état des lieux d'entrée doit être signé avant de passer la moto en réception.",
  TYPE_PHOTO_INVALIDE: 'Type de photo refusé par le serveur.',
}

const loading = ref(true)
const loadError = ref('')
const edlByRdv = reactive<Record<number, any>>({})

const edlPanneauOuvert = ref(false)
const edlPanneauRdv = ref<any>(null)

function ouvrirEtatDesLieux(rdv: any) {
  // Deux panneaux occupent la même bande à droite : ouvrir l'un ferme l'autre,
  // sinon ils se superposent et la ligne d'origine se perd.
  checkinOpen.value = false
  edlPanneauRdv.value = rdv
  edlPanneauOuvert.value = true
}

const checkinOpen = ref(false)
const checkinRdv = ref<any>(null)
const hydrating = ref(false)
const form = reactive<{ kilometrage: number | null; niveau_carburant: string; observations: string }>({
  kilometrage: null,
  niveau_carburant: '',
  observations: '',
})
const savingDraft = ref(false)
const draftSavedAt = ref('')
const draftError = ref('')
const sessionPhotos = ref<Array<{ id: number; filename: string; url: string }>>([])
const uploadingPhotos = ref(false)
const showSignature = ref(false)
const signing = ref(false)
const signError = ref('')
const transitioning = ref(false)
let draftTimer: ReturnType<typeof setTimeout> | null = null
// Jeton de génération : toute réponse d'hydratation d'une ouverture précédente est jetée
let openSeq = 0

const todayLabel = computed(() => {
  const jour = new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })
  return jour.charAt(0).toUpperCase() + jour.slice(1)
})

const receptionRdvs = computed(() =>
  rdvStore.rdvs
    .filter((r: any) => RECEPTION_STATUTS.includes(r.status))
    .slice()
    .sort((a: any, b: any) => String(a.heure_debut || '').localeCompare(String(b.heure_debut || '')))
)

const kpis = computed(() => {
  const total = receptionRdvs.value.length
  const signes = receptionRdvs.value.filter((r: any) => edlByRdv[r.id]?.signe).length
  return { total, signes, restants: total - signes }
})

/** Vue de la liste. « toutes » par défaut : au chargement, rien n'est masqué. */
const filtre = ref<'toutes' | 'a_receptionner' | 'signes'>('toutes')

const filtres = computed(() => [
  { cle: 'toutes' as const, libelle: 'Toutes', nombre: kpis.value.total },
  { cle: 'a_receptionner' as const, libelle: 'À réceptionner', nombre: kpis.value.restants },
  { cle: 'signes' as const, libelle: 'Signés', nombre: kpis.value.signes },
])

const rdvsAffiches = computed(() => {
  if (filtre.value === 'a_receptionner') return receptionRdvs.value.filter((r: any) => !edlByRdv[r.id]?.signe)
  if (filtre.value === 'signes') return receptionRdvs.value.filter((r: any) => edlByRdv[r.id]?.signe)
  return receptionRdvs.value
})

const suggestionFiltre = computed(() => {
  const actif = filtres.value.find((f) => f.cle === filtre.value)
  if (!actif || filtre.value === 'toutes') return null
  return { filtre: actif.libelle, nombre: kpis.value.total, objet: kpis.value.total > 1 ? 'motos attendues' : 'moto attendue' }
})

const currentEdl = computed(() => (checkinRdv.value ? edlByRdv[checkinRdv.value.id] : null))
const isSigned = computed(() => Boolean(currentEdl.value?.signe))
const photosCount = computed(() => Number(currentEdl.value?.photos_entree_count ?? 0))

const hasValidKm = computed(() =>
  typeof form.kilometrage === 'number' && Number.isFinite(form.kilometrage) && form.kilometrage >= 0
)
const canSaveDraft = computed(() =>
  Boolean(checkinRdv.value) && !isSigned.value && hasValidKm.value && Boolean(form.niveau_carburant)
)
const canSign = computed(() => canSaveDraft.value && photosCount.value >= PHOTOS_MIN)

const titreCheckin = computed(() => `Check-in · ${checkinRdv.value?.client_nom || 'moto attendue'}`)
const sousTitreCheckin = computed(() =>
  [checkinRdv.value?.heure_debut, checkinRdv.value?.vehicule_info, checkinRdv.value?.vehicule_plaque]
    .filter(Boolean)
    .join(' · ')
)

/**
 * Ce qui manque encore, et ce que ça empêche. Écrit à l'écran plutôt que
 * caché dans l'infobulle d'un bouton grisé : une action essentielle ne se
 * découvre pas au survol.
 */
const manqueAvantSignature = computed(() => {
  if (!checkinRdv.value || isSigned.value) return ''
  const manques: string[] = []
  if (!hasValidKm.value) manques.push('le kilométrage au compteur')
  if (!form.niveau_carburant) manques.push('le niveau de carburant')
  const clichesManquants = Math.max(0, PHOTOS_MIN - photosCount.value)
  if (clichesManquants) manques.push(`${clichesManquants} photo${clichesManquants > 1 ? 's' : ''} d'entrée`)
  if (!manques.length) return ''
  const liste = manques.length > 1
    ? `${manques.slice(0, -1).join(', ')} et ${manques[manques.length - 1]}`
    : manques[0]
  return `Il manque ${liste}. Sans ça, le client ne peut pas signer l'état des lieux, et la moto ne peut pas passer en réception.`
})

function frError(e: any): string {
  const code = e?.data?.code
  if (code === 'PHOTOS_MANQUANTES') {
    const missing = Number(e?.data?.missing ?? 0)
    return missing > 0
      ? `Il manque ${missing} photo${missing > 1 ? 's' : ''} d'entrée (minimum ${PHOTOS_MIN}).`
      : `Photos d'entrée insuffisantes (minimum ${PHOTOS_MIN}).`
  }
  if (code && ERROR_MESSAGES[code]) return ERROR_MESSAGES[code]
  return messageErreur(e, "la réception n'a pas été enregistrée")
}

function fuelLabel(value: string | null | undefined): string {
  return FUEL_LEVELS.find((l) => l.value === value)?.label ?? '—'
}

function isFuelFilled(idx: number): boolean {
  const selectedIdx = FUEL_LEVELS.findIndex((l) => l.value === form.niveau_carburant)
  return selectedIdx >= 0 && idx <= selectedIdx
}

function formatDateTime(value: string): string {
  const d = new Date(value)
  return Number.isNaN(d.getTime())
    ? value
    : d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' }) + ' à ' + d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
}

/** 28 412 km — groupes de milliers et unité collés par des insécables. */
function formatKm(value: number | null | undefined): string {
  if (typeof value !== 'number' || !Number.isFinite(value)) return '—'
  return `${value.toLocaleString('fr-FR').replace(/[\u202F\u2009\u00A0 ]/g, INSECABLE)}${INSECABLE}km`
}

/** Durée vendue du créneau, en écriture d'atelier : 1 h 30, 45 min. */
function dureeLisible(rdv: any): string {
  const minutes = Number(rdv?.temps_estime ?? rdv?.duree_estimee ?? 0)
  if (!Number.isFinite(minutes) || minutes <= 0) return ''
  const heures = Math.floor(minutes / 60)
  const reste = minutes % 60
  if (!heures) return `${reste}${INSECABLE}min`
  return reste
    ? `${heures}${INSECABLE}h${INSECABLE}${String(reste).padStart(2, '0')}`
    : `${heures}${INSECABLE}h`
}

function estSigne(rdvId: number): boolean {
  return Boolean(edlByRdv[rdvId]?.signe)
}

/** Ni pont ni mécanicien : la moto arrive sans place où la mettre. */
function sansAffectation(rdv: any): boolean {
  return !rdv?.pont_nom && !rdv?.mecanicien_nom
}

function libelleReception(rdv: any): string {
  return rdv?.pont_nom ? `Réceptionner la moto · ${rdv.pont_nom}` : 'Réceptionner la moto'
}

function edlBadgeLabel(rdvId: number): string {
  const edl = edlByRdv[rdvId]
  if (edl?.signe) return 'Check-in signé'
  if (edl?.exists) return 'EDL · Saisie en cours'
  return 'EDL · À faire'
}

function classeEdl(rdvId: number): string {
  const edl = edlByRdv[rdvId]
  if (edl?.signe) return 'rc-pastille--fait'
  if (edl?.exists) return 'rc-pastille--encours'
  return 'rc-pastille--neutre'
}

function checkinButtonLabel(rdvId: number): string {
  const edl = edlByRdv[rdvId]
  if (edl?.signe) return 'Revoir le check-in'
  if (edl?.exists) return 'Reprendre le check-in'
  return 'Démarrer le check-in'
}

async function refreshEdl(rdvId: number) {
  try {
    edlByRdv[rdvId] = await api.get(`/rendez-vous/${rdvId}/etat-des-lieux`)
  } catch {
    // RDV inconnu / API momentanément indisponible : on retombe sur « À faire »
    if (!edlByRdv[rdvId]) edlByRdv[rdvId] = null
  }
}

async function loadData(showSpinner = true) {
  if (showSpinner) loading.value = true
  loadError.value = ''
  try {
    await rdvStore.fetchRdvs({ date: todayLocalISO() })
    await Promise.all(receptionRdvs.value.map((r: any) => refreshEdl(r.id)))
  } catch (e: any) {
    loadError.value = messageErreur(e, "la liste des motos attendues n'a pas pu être lue")
  } finally {
    loading.value = false
  }
}

async function openCheckin(rdv: any) {
  const seq = ++openSeq
  hydrating.value = true
  // Un seul panneau à la fois dans la bande de droite.
  edlPanneauOuvert.value = false
  checkinRdv.value = rdv
  sessionPhotos.value = []
  draftError.value = ''
  draftSavedAt.value = ''
  signError.value = ''
  // Réinitialisation AVANT le premier await : le formulaire ne montre JAMAIS
  // les valeurs du RDV précédent pendant l'hydratation.
  form.kilometrage = null
  form.niveau_carburant = ''
  form.observations = ''
  checkinOpen.value = true

  await refreshEdl(rdv.id)
  // Réponse obsolète (autre fiche ouverte entre-temps) : on jette tout.
  if (seq !== openSeq) return
  const edl = edlByRdv[rdv.id]
  form.kilometrage = typeof edl?.kilometrage === 'number' ? edl.kilometrage : null
  form.niveau_carburant = edl?.niveau_carburant || ''
  form.observations = edl?.observations || ''

  await nextTick()
  if (seq !== openSeq) return
  hydrating.value = false
}

async function saveDraft(manual = false): Promise<boolean> {
  if (!checkinRdv.value || isSigned.value || hydrating.value) return false
  if (!canSaveDraft.value) {
    if (manual) draftError.value = 'Renseignez le kilométrage et le niveau de carburant pour enregistrer le brouillon.'
    return false
  }

  // rdvId + payload capturés AVANT l'await : si la fiche change pendant l'envoi,
  // on poste quand même les bonnes valeurs sur le bon RDV et on jette la réponse
  // côté UI (plus de Math.round(null) fantôme ni d'écriture sur le nouveau RDV).
  const rdvId: number = checkinRdv.value.id
  const payload = {
    kilometrage: Math.round(form.kilometrage as number),
    niveau_carburant: form.niveau_carburant,
    observations: form.observations || null,
  }

  savingDraft.value = true
  try {
    await api.post(`/rendez-vous/${rdvId}/etat-des-lieux`, payload)
    // Le cache est indexé par rdvId capturé : toujours juste, même si la fiche a changé
    edlByRdv[rdvId] = {
      ...(edlByRdv[rdvId] || {}),
      exists: true,
      ...payload,
    }
    if (checkinRdv.value?.id === rdvId) {
      draftError.value = ''
      draftSavedAt.value = new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
    }
    return true
  } catch (e: any) {
    if (checkinRdv.value?.id === rdvId) draftError.value = frError(e)
    return false
  } finally {
    savingDraft.value = false
  }
}

// Sauvegarde du brouillon au fil de l'eau (débounce)
watch(
  () => [form.kilometrage, form.niveau_carburant, form.observations],
  () => {
    if (hydrating.value || !checkinOpen.value || isSigned.value || !canSaveDraft.value) return
    if (draftTimer) clearTimeout(draftTimer)
    draftTimer = setTimeout(() => {
      draftTimer = null
      saveDraft(false)
    }, DRAFT_DEBOUNCE_MS)
  }
)

// Flush du débounce en attente : à la fermeture du panneau (ou au démontage),
// une saisie valide non signée est poussée immédiatement au lieu d'être perdue.
function flushPendingDraft() {
  if (!draftTimer) return
  clearTimeout(draftTimer)
  draftTimer = null
  if (checkinRdv.value && !isSigned.value && canSaveDraft.value) {
    void saveDraft(false)
  }
}

async function onPhotosSelected(event: Event) {
  const input = event.target as HTMLInputElement
  const files = Array.from(input.files || [])
  if (!files.length || !checkinRdv.value) return

  // rdvId capturé UNE FOIS avant la boucle : chaque upload part sur CE RDV,
  // même si le staff ferme le panneau ou ouvre une autre fiche pendant l'envoi.
  const rdvId: number = checkinRdv.value.id
  uploadingPhotos.value = true
  let uploaded = 0
  try {
    for (const file of files) {
      // La fiche affichée a changé : on abandonne les fichiers restants
      // plutôt que de les envoyer sur le mauvais RDV.
      if (checkinRdv.value?.id !== rdvId) break
      const fd = new FormData()
      fd.append('photo', file)
      fd.append('rendez_vous_id', String(rdvId))
      fd.append('type', 'checkin')
      const res = await api.upload('/photos/upload', fd)
      uploaded++
      // Réponse arrivée après un changement de fiche : on n'écrit pas
      // les miniatures dans l'état du nouveau RDV.
      if (checkinRdv.value?.id !== rdvId) break
      sessionPhotos.value = [
        ...sessionPhotos.value,
        { id: res.id, filename: res.filename, url: `${apiBase}/photos/file/${res.filename}` },
      ]
    }
  } catch (e: any) {
    toast.add({ title: "Cliché non enregistré", description: frError(e), color: 'error' })
  } finally {
    uploadingPhotos.value = false
    input.value = ''
    // Compteur rafraîchi sur le RDV capturé (edlByRdv est indexé par id : sans effet
    // sur la fiche affichée si elle a changé entre-temps).
    if (uploaded) await refreshEdl(rdvId)
  }
}

function openPhotoInTab(url: string) {
  if (url) window.open(url, '_blank')
}

async function openSignature() {
  // On pousse la dernière version du brouillon avant de présenter la tablette au client
  const saved = await saveDraft(true)
  if (!saved) return
  signError.value = ''
  showSignature.value = true
}

async function onSigned(dataUrl: string) {
  if (!checkinRdv.value) return
  signing.value = true
  signError.value = ''
  try {
    await api.post(`/rendez-vous/${checkinRdv.value.id}/etat-des-lieux/sign`, { signature: dataUrl })
    showSignature.value = false
    await refreshEdl(checkinRdv.value.id)
    toast.add({
      title: 'État des lieux signé',
      description: 'Le document est figé et le PDF archivé.',
      color: 'success',
    })
  } catch (e: any) {
    signError.value = frError(e)
  } finally {
    signing.value = false
  }
}

async function openEdlPdf(edl: any) {
  if (!edl?.id) return
  try {
    await openPdf(`/etat-des-lieux/${edl.id}/pdf`)
  } catch (e: any) {
    toast.add({ title: 'PDF indisponible', description: messageErreur(e, "le PDF de l'état des lieux n'a pas été ouvert"), color: 'error' })
  }
}

async function passerEnReception(rdv: any) {
  transitioning.value = true
  try {
    await rdvStore.transitionRdv(rdv.id, 'reception')
    toast.add({ title: 'Moto passée en réception', description: `${rdv.client_nom} — ${rdv.vehicule_info}`, color: 'success' })
    checkinOpen.value = false
    await loadData(false)
  } catch (e: any) {
    toast.add({ title: 'Réception impossible', description: frError(e), color: 'error' })
  } finally {
    transitioning.value = false
  }
}

watch(checkinOpen, (open) => {
  if (!open) {
    flushPendingDraft()
    showSignature.value = false
  }
})

onBeforeUnmount(() => {
  flushPendingDraft()
})

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.rc {
  display: flex;
  flex-direction: column;
  gap: 16px;
  color: var(--pk-ink);
}

/* === Tête de page === */
.rc-tete {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}
.rc-tete-titres {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}
.rc-titre {
  margin: 0;
  font-size: 28px;
  font-weight: 500;
  line-height: 1.1;
  letter-spacing: -0.015em;
  color: var(--pk-ink);
}
/* La bande de course du design system : 44 × 4, jaune franc, sans dégradé. */
.rc-filet-course {
  width: 44px;
  height: 4px;
  background: var(--pk-accent);
}
.rc-sous-titre {
  margin: 4px 0 0;
  font-size: 13px;
  color: var(--pk-ink-quiet);
}

.rc-mesures {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}
.rc-mesure {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 130px;
  padding: 10px 18px;
  background: var(--pk-surface);
  border: 1px solid var(--pk-border);
  border-radius: var(--pk-radius-card);
}
/* Le seul endroit, avec les mots de statut, où les CAPITALES sont admises. */
.rc-mesure-label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--pk-ink-muted);
}
.rc-mesure-valeur {
  font-size: 26px;
  font-weight: 700;
  line-height: 1;
  font-variant-numeric: tabular-nums;
}
.rc-mesure-valeur--fait { color: var(--pk-success-ink); }
.rc-mesure-valeur--reste { color: var(--pk-link); }

/* === Cadre de la liste === */
.rc-cadre {
  display: flex;
  flex-direction: column;
  min-width: 0;
  background: var(--pk-surface);
  border: 1px solid var(--pk-border);
  border-radius: var(--pk-radius-card);
  overflow: hidden;
}
.rc-cadre-tete {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  border-bottom: 1px solid var(--pk-border);
  flex-wrap: wrap;
}
.rc-cadre-glyphe { font-size: 17px; }
.rc-cadre-titre { font-size: 15px; font-weight: 600; }
.rc-cadre-espace { flex: 1; }
.rc-compteur {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 20px;
  height: 20px;
  padding: 0 6px;
  border-radius: var(--pk-radius-pill);
  background: var(--pk-ink);
  color: var(--pk-surface);
  font-size: 11px;
  font-weight: 700;
}

/* Pastille de filtre : l'état choisi se signale par l'APLAT, jamais par une
   graisse plus lourde qui déplacerait la largeur du libellé. */
.rc-pastille-filtre {
  min-height: var(--pk-target-desk);
  padding: 5px 11px;
  border: 1px solid var(--pk-border-control);
  border-radius: var(--pk-radius-pill);
  background: transparent;
  color: var(--pk-ink);
  font: inherit;
  font-size: 12px;
  cursor: pointer;
  white-space: nowrap;
  transition: background var(--pk-duration-state) var(--pk-easing),
    color var(--pk-duration-state) var(--pk-easing),
    border-color var(--pk-duration-state) var(--pk-easing);
}
.rc-pastille-filtre:hover { background: var(--pk-neutral-surface); }
.rc-pastille-filtre.est-choisi {
  background: var(--pk-ink);
  border-color: var(--pk-ink);
  color: var(--pk-surface);
  font-weight: 600;
}
.rc-pastille-filtre:focus-visible {
  outline: var(--pk-focus-width) solid var(--pk-focus-ring);
  outline-offset: var(--pk-focus-offset);
}

.rc-filtre-vide { margin: 16px; }

/* === Lignes === */
.rc-lignes {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
}
.rc-ligne {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 14px 16px;
  border-bottom: 1px solid var(--pk-border-quiet);
  background: var(--pk-surface-raised);
  flex-wrap: wrap;
}
.rc-ligne:last-child { border-bottom: none; }
/* Le filet jaune à gauche désigne ce qui reste à faire : la ligne déjà signée
   n'a pas de filet et prend le fond soulevé, elle se lit sans se travailler. */
.rc-ligne.est-a-faire {
  border-left: 3px solid var(--pk-accent);
  background: transparent;
}

.rc-heure {
  width: 58px;
  flex: none;
  text-align: center;
}
.rc-heure-valeur {
  display: block;
  font-size: 18px;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}
.rc-heure-duree {
  display: block;
  font-size: 11px;
  color: var(--pk-ink-muted);
}
.rc-separateur {
  width: 1px;
  height: 40px;
  flex: none;
  background: var(--pk-border-quiet);
}

.rc-identite { flex: 1; min-width: 180px; }
.rc-client { margin: 0; font-size: 14px; font-weight: 600; }
.rc-moto { margin: 0; font-size: 13px; color: var(--pk-ink-quiet); }
.rc-travaux { margin: 0; font-size: 12px; color: var(--pk-ink-muted); }
/* L'immat se lit comme une plaque : encadrée, resserrée, jamais colorée. */
.rc-immat {
  display: inline-block;
  margin-left: 6px;
  padding: 1px 6px;
  border: 1px solid var(--pk-border-control);
  border-radius: var(--pk-radius-block);
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.04em;
  color: var(--pk-ink);
}

.rc-marques {
  display: flex;
  align-items: center;
  gap: var(--pk-target-gap);
  flex-wrap: wrap;
}
.rc-pastille {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border: 1px solid transparent;
  border-radius: var(--pk-radius-pill);
  font-size: 12px;
  font-weight: 600;
  white-space: nowrap;
}
.rc-pastille--neutre { background: var(--pk-neutral-surface); color: var(--pk-ink-quiet); }
.rc-pastille--encours {
  background: var(--pk-warning-surface);
  border-color: var(--pk-warning-line);
  color: var(--pk-warning-ink);
}
.rc-pastille--fait {
  background: var(--pk-success-surface);
  border-color: var(--pk-success-line);
  color: var(--pk-success-ink);
}
/* Pointillé = anomalie, réservé à ça. */
.rc-pastille--pointille {
  border: 1px dashed var(--pk-border-control);
  color: var(--pk-ink-quiet);
}

.rc-actions {
  display: flex;
  gap: var(--pk-target-gap);
  flex-wrap: wrap;
}
.rc-action {
  min-height: var(--pk-target-desk);
  padding: 10px 16px;
  font-size: 13px;
}

/* === Panneau de check-in === */
.rc-champ-label {
  display: block;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--pk-ink-muted);
}
.rc-champ-label--espace { margin-top: 6px; }

.rc-aide {
  margin: 0;
  font-size: 12px;
  line-height: 1.45;
  color: var(--pk-ink-quiet);
}

/* Le compteur est le chiffre le plus recopié du comptoir : cible d'atelier,
   chiffres tabulaires, taille lisible à bout de bras. */
.rc-km {
  min-height: var(--pk-target-workshop);
  font-size: 18px;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}
.rc-observations { min-height: 96px; }

.rc-jauge { display: flex; gap: 6px; }
.rc-jauge-cran {
  flex: 1;
  min-height: var(--pk-target-workshop);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 8px 4px;
  border: 1px solid var(--pk-border-control);
  border-radius: var(--pk-radius-tile);
  background: var(--pk-surface-raised);
  color: var(--pk-ink-quiet);
  font: inherit;
  cursor: pointer;
  transition: background var(--pk-duration-state) var(--pk-easing),
    border-color var(--pk-duration-state) var(--pk-easing),
    color var(--pk-duration-state) var(--pk-easing);
}
.rc-jauge-barre {
  display: block;
  width: 100%;
  height: 8px;
  border-radius: var(--pk-radius-block);
  background: var(--pk-neutral-surface);
}
.rc-jauge-cran.est-rempli .rc-jauge-barre { background: var(--pk-accent-soft); }
.rc-jauge-cran.est-choisi {
  border-color: var(--pk-border-strong);
  background: var(--pk-accent-soft);
  color: var(--pk-accent-ink);
}
.rc-jauge-cran.est-choisi .rc-jauge-barre { background: var(--pk-accent); }
.rc-jauge-cran:disabled { cursor: not-allowed; opacity: 0.55; }
.rc-jauge-cran:focus-visible {
  outline: var(--pk-focus-width) solid var(--pk-focus-ring);
  outline-offset: var(--pk-focus-offset);
}
/* L'état choisi passe par la couleur et la bordure, jamais par la graisse. */
.rc-jauge-label { font-size: 12px; font-weight: 600; }

/* Le compte reste une phrase de statut, pas un compteur décoratif : sous le
   minimum il prend l'encre d'avertissement, au-dessus celle du fait acquis. */
.rc-compte-photos {
  margin: 0;
  font-size: 12px;
  font-weight: 600;
  color: var(--pk-success-ink);
}
.rc-compte-photos.est-court { color: var(--pk-warning-ink); }

.rc-prise-photo {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: var(--pk-target-workshop);
  border: 1px dashed var(--pk-accent);
  border-radius: var(--pk-radius-tile);
  background: var(--pk-accent-soft);
  color: var(--pk-accent-ink);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background var(--pk-duration-state) var(--pk-easing);
}
/* Le champ de fichier est masqué : sans cet anneau, l'appui clavier sur la
   zone de prise de vue ne se verrait nulle part. */
.rc-prise-photo:focus-within {
  outline: var(--pk-focus-width) solid var(--pk-focus-ring);
  outline-offset: var(--pk-focus-offset);
}

.rc-cliches {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(84px, 1fr));
  gap: var(--pk-target-gap);
}
.rc-cliche {
  width: 100%;
  aspect-ratio: 1;
  object-fit: cover;
  border: 1px solid var(--pk-border);
  border-radius: var(--pk-radius-tile);
  cursor: zoom-in;
}

.rc-brouillon {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0;
  font-size: 12px;
  color: var(--pk-success-ink);
}

.rc-fige {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px;
  border: 1px solid var(--pk-success-line);
  border-radius: var(--pk-radius-tile);
  background: var(--pk-success-surface);
}
.rc-fige-glyphe { font-size: 20px; flex-shrink: 0; color: var(--pk-success-line); }
.rc-fige-texte { margin: 0; font-size: 13px; line-height: 1.45; color: var(--pk-success-ink); }

.rc-releve {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  font-size: 13px;
  color: var(--pk-ink-quiet);
}
.rc-releve strong { color: var(--pk-ink); font-variant-numeric: tabular-nums; }

.rc-reserve { margin: 0; font-size: 13px; line-height: 1.5; }
.rc-note { margin: 0; font-size: 12px; color: var(--pk-ink-muted); }

.rc-blocage {
  margin: 0;
  font-size: 12px;
  line-height: 1.45;
  color: var(--pk-warning-ink);
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip-path: inset(50%);
  white-space: nowrap;
}

@media (max-width: 900px) {
  .rc-separateur { display: none; }
  .rc-actions { width: 100%; }
}
</style>
