<template>
  <div data-testid="reception-page">
    <div class="page-header reception-header">
      <div>
        <div class="page-title">Réception du matin</div>
        <div class="page-sub">{{ todayLabel }} — check-in / état des lieux d'entrée</div>
      </div>
      <div class="reception-kpis">
        <div class="stat-card reception-kpi">
          <div class="reception-kpi-label">RDV DU JOUR</div>
          <div class="reception-kpi-value" style="color:var(--content-1);">{{ kpis.total }}</div>
        </div>
        <div class="stat-card reception-kpi">
          <div class="reception-kpi-label">CHECK-INS SIGNÉS</div>
          <div class="reception-kpi-value" style="color:var(--success-content);">{{ kpis.signes }}</div>
        </div>
        <div class="stat-card reception-kpi">
          <div class="reception-kpi-label">RESTANTS</div>
          <div class="reception-kpi-value" style="color:var(--accent-content);">{{ kpis.restants }}</div>
        </div>
      </div>
    </div>

    <div v-if="loading" class="reception-loading">Chargement…</div>

    <div v-else-if="loadError" class="reception-error">
      <p>{{ loadError }}</p>
      <button class="btn btn-ghost" type="button" @click="loadData()">Réessayer</button>
    </div>

    <UCard v-else>
      <template #header>
        <span style="font-size:15px;font-weight:700;color:var(--content-1);"><AppIcon name="i-ri-inbox-line" /> Motos attendues aujourd'hui ({{ receptionRdvs.length }})</span>
      </template>

      <div v-if="!receptionRdvs.length" class="reception-empty">
        Aucun rendez-vous à réceptionner aujourd'hui.
      </div>

      <div v-else class="reception-list">
        <div
          v-for="rdv in receptionRdvs"
          :key="rdv.id"
          class="reception-card"
          data-testid="reception-rdv-card"
        >
          <div class="reception-card-heure">{{ rdv.heure_debut || '—' }}</div>
          <div class="reception-card-infos">
            <p class="reception-card-client">{{ rdv.client_nom || 'Client inconnu' }}</p>
            <p class="reception-card-vehicule">
              {{ rdv.vehicule_info || 'Véhicule non renseigné' }}
              <span v-if="rdv.vehicule_plaque" class="reception-card-plaque">{{ rdv.vehicule_plaque }}</span>
            </p>
            <p v-if="rdv.type_intervention" class="reception-card-type">{{ rdv.type_intervention }}</p>
          </div>
          <div class="reception-card-badges">
            <StatusBadge :status="rdv.status" />
            <span class="edl-badge" :style="edlBadgeStyle(rdv.id)" data-testid="edl-badge">{{ edlBadgeLabel(rdv.id) }}</span>
          </div>
          <div class="reception-card-actions">
            <button
              v-if="edlByRdv[rdv.id]?.signe && edlByRdv[rdv.id]?.pdf_disponible"
              class="btn btn-ghost reception-btn"
              type="button"
              data-testid="btn-edl-pdf"
              @click="openEdlPdf(edlByRdv[rdv.id])"
            ><AppIcon name="i-ri-file-text-line" /> PDF</button>
            <button
              class="btn btn-primary reception-btn"
              type="button"
              data-testid="btn-checkin"
              @click="openCheckin(rdv)"
            >{{ checkinButtonLabel(rdv.id) }}</button>
          </div>
        </div>
      </div>
    </UCard>

    <!-- ===== Panneau check-in ===== -->
    <AppModal v-model:open="checkinOpen" size="lg">
      <template #header>
        <div>
          <div style="font-size:16px;font-weight:800;color:var(--content-1);" class="modal-title"><AppIcon name="i-ri-inbox-line" /> Check-in — état des lieux d'entrée</div>
          <div v-if="checkinRdv" style="font-size:12px;color:var(--content-3);margin-top:2px;">
            {{ checkinRdv.heure_debut }} · {{ checkinRdv.client_nom }} · {{ checkinRdv.vehicule_info }}
            <span v-if="checkinRdv.vehicule_plaque"> ({{ checkinRdv.vehicule_plaque }})</span>
          </div>
        </div>
      </template>

      <template #content>
        <div v-if="checkinRdv" class="checkin-body">
          <!-- État signé : lecture seule -->
          <div v-if="isSigned" class="checkin-signed" data-testid="checkin-signed">
            <div style="font-size:32px;"><AppIcon name="i-ri-checkbox-circle-line" /></div>
            <p style="color:var(--success-content);font-weight:700;font-size:15px;">État des lieux signé</p>
            <p v-if="currentEdl?.signed_at" style="font-size:12px;color:var(--content-3);">
              Signé le {{ formatDateTime(currentEdl.signed_at) }}<span v-if="currentEdl?.signed_by"> — accueilli par {{ currentEdl.signed_by }}</span>
            </p>
            <div class="checkin-signed-recap">
              <div><span class="checkin-recap-label">Kilométrage :</span> {{ currentEdl?.kilometrage ?? '—' }} km</div>
              <div><span class="checkin-recap-label">Carburant :</span> {{ fuelLabel(currentEdl?.niveau_carburant) }}</div>
              <div><span class="checkin-recap-label">Photos d'entrée :</span> {{ photosCount }}</div>
              <div v-if="currentEdl?.observations" style="grid-column:1/-1;"><span class="checkin-recap-label">Observations :</span> {{ currentEdl.observations }}</div>
            </div>
            <div class="checkin-signed-actions">
              <button
                v-if="currentEdl?.pdf_disponible"
                class="btn btn-ghost reception-btn"
                type="button"
                data-testid="btn-edl-pdf-modal"
                @click="openEdlPdf(currentEdl)"
              ><AppIcon name="i-ri-file-text-line" /> Voir le PDF</button>
              <button
                v-if="checkinRdv.status === 'confirme'"
                class="btn btn-primary reception-btn"
                type="button"
                data-testid="btn-passer-reception"
                :disabled="transitioning"
                @click="passerEnReception(checkinRdv)"
              ><AppIcon v-if="!(transitioning)" name="i-ri-motorbike-line" />{{ transitioning ? 'Transition…' : 'Passer en réception' }}</button>
            </div>
          </div>

          <!-- Saisie -->
          <template v-else>
            <div class="form-group">
              <label class="form-label" for="checkin-km">Kilométrage compteur <span style="color:var(--error-content);">*</span></label>
              <input
                id="checkin-km"
                v-model.number="form.kilometrage"
                type="number"
                min="0"
                step="1"
                inputmode="numeric"
                class="form-input checkin-km-input"
                placeholder="ex : 24350"
                data-testid="checkin-km"
                :disabled="hydrating"
              />
            </div>

            <div class="form-group">
              <label class="form-label">Niveau de carburant <span style="color:var(--error-content);">*</span></label>
              <div class="fuel-gauge" role="radiogroup" aria-label="Niveau de carburant">
                <button
                  v-for="(level, idx) in FUEL_LEVELS"
                  :key="level.value"
                  type="button"
                  class="fuel-segment"
                  :class="{ 'is-selected': form.niveau_carburant === level.value, 'is-filled': isFuelFilled(idx) }"
                  role="radio"
                  :aria-checked="form.niveau_carburant === level.value"
                  :data-testid="`fuel-segment-${level.value}`"
                  :disabled="hydrating"
                  @click="form.niveau_carburant = level.value"
                >
                  <span class="fuel-segment-bar" />
                  <span class="fuel-segment-label">{{ level.label }}</span>
                </button>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="checkin-obs">Observations (rayures, chocs, accessoires…)</label>
              <textarea
                id="checkin-obs"
                v-model="form.observations"
                class="form-input"
                rows="3"
                placeholder="État général du véhicule à l'arrivée…"
                data-testid="checkin-observations"
                :disabled="hydrating"
              />
            </div>

            <!-- Photos -->
            <div class="form-group">
              <div class="checkin-photos-header">
                <label class="form-label" style="margin-bottom:0;">Photos d'entrée</label>
                <span class="checkin-photos-count" :style="{ color: photosCount >= PHOTOS_MIN ? 'var(--success-content)' : 'var(--warning-content)' }" data-testid="checkin-photos-count">
                  {{ photosCount }}/{{ PHOTOS_MIN }} photos minimum
                </span>
              </div>
              <label class="checkin-photo-btn">
                <input
                  type="file"
                  accept="image/*"
                  capture="environment"
                  multiple
                  style="display:none;"
                  data-testid="checkin-photo-input"
                  :disabled="uploadingPhotos || hydrating"
                  @change="onPhotosSelected"
                />
                <span><AppIcon :name="uploadingPhotos ? 'i-ri-hourglass-line' : 'i-ri-camera-line'" /> {{ uploadingPhotos ? 'Envoi en cours…' : 'Prendre / ajouter des photos' }}</span>
              </label>
              <div v-if="sessionPhotos.length" class="checkin-photo-grid">
                <img
                  v-for="photo in sessionPhotos"
                  :key="photo.id"
                  :src="photo.url"
                  :alt="photo.filename"
                  class="checkin-photo-thumb"
                  @click="openPhotoInTab(photo.url)"
                />
              </div>
              <p v-if="photosCount > sessionPhotos.length" class="checkin-photos-hint">
                {{ photosCount - sessionPhotos.length }} photo{{ photosCount - sessionPhotos.length > 1 ? 's' : '' }} déjà enregistrée{{ photosCount - sessionPhotos.length > 1 ? 's' : '' }} sur ce RDV.
              </p>
              <p class="checkin-photos-hint">La suppression de photos n'est pas disponible — reprenez une photo si besoin.</p>
            </div>

            <div v-if="draftError" class="checkin-alert checkin-alert-error" data-testid="checkin-error">{{ draftError }}</div>
            <div v-else-if="draftSavedAt" class="checkin-alert checkin-alert-ok"><AppIcon name="i-ri-save-line" /> Brouillon enregistré à {{ draftSavedAt }}</div>
          </template>
        </div>
      </template>

      <template #footer>
        <div class="checkin-footer">
          <button class="btn btn-ghost reception-btn" type="button" :disabled="hydrating" @click="checkinOpen = false">Fermer</button>
          <template v-if="checkinRdv && !isSigned">
            <button
              class="btn btn-ghost reception-btn"
              type="button"
              :disabled="savingDraft || hydrating"
              data-testid="btn-save-draft"
              @click="saveDraft(true)"
            ><AppIcon v-if="!(savingDraft)" name="i-ri-save-line" />{{ savingDraft ? 'Sauvegarde…' : 'Enregistrer le brouillon' }}</button>
            <button
              class="btn btn-primary reception-btn"
              type="button"
              :disabled="!canSign || savingDraft || hydrating"
              :title="canSign ? '' : 'Kilométrage, carburant et 4 photos minimum requis'"
              data-testid="btn-faire-signer"
              @click="openSignature"
            ><AppIcon name="i-ri-quill-pen-line" /> Faire signer le client</button>
          </template>
        </div>
      </template>
    </AppModal>

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

const todayLabel = computed(() =>
  new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })
)

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

function edlBadgeLabel(rdvId: number): string {
  const edl = edlByRdv[rdvId]
  if (edl?.signe) return 'Signé'
  if (edl?.exists) return 'Saisie en cours'
  return 'À faire'
}

function edlBadgeStyle(rdvId: number): Record<string, string> {
  const edl = edlByRdv[rdvId]
  if (edl?.signe) return { background: 'var(--success-soft)', color: 'var(--success-content)' }
  if (edl?.exists) return { background: 'var(--warning-soft)', color: 'var(--warning-content)' }
  return { background: 'var(--surface-3)', color: 'var(--content-2)' }
}

function checkinButtonLabel(rdvId: number): string {
  const edl = edlByRdv[rdvId]
  if (edl?.signe) return 'Voir le check-in'
  if (edl?.exists) return 'Reprendre le check-in'
  return 'Check-in'
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
    loadError.value = e?.message || 'Chargement des rendez-vous impossible.'
  } finally {
    loading.value = false
  }
}

async function openCheckin(rdv: any) {
  const seq = ++openSeq
  hydrating.value = true
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

// Flush du débounce en attente : à la fermeture de la modale (ou au démontage),
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
  // même si le staff ferme la modale ou ouvre une autre fiche pendant l'envoi.
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
    toast.add({ title: 'Erreur photo', description: frError(e), color: 'error' })
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
    toast.add({ title: 'PDF indisponible', description: e?.message || 'Ouverture du PDF impossible.', color: 'error' })
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
    toast.add({ title: 'Transition impossible', description: frError(e), color: 'error' })
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
.reception-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}
.reception-kpis {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.reception-kpi {
  padding: 8px 14px;
  min-width: auto;
}
.reception-kpi-label {
  font-size: 10px;
  color: var(--content-3);
}
.reception-kpi-value {
  font-size: 18px;
  font-weight: 700;
}

.reception-loading {
  display: flex;
  justify-content: center;
  padding: 48px;
  color: var(--content-3);
}
.reception-error {
  padding: 24px;
  border-radius: 12px;
  background: var(--error-soft);
  border: 1px solid var(--error);
  color: var(--error-content);
  font-size: 13px;
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}
.reception-empty {
  padding: 24px;
  text-align: center;
  color: var(--content-3);
  font-size: 13px;
}

.reception-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.reception-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 14px;
  border-radius: 12px;
  border: 1px solid var(--border-2);
  background: var(--overlay-soft);
  flex-wrap: wrap;
}
.reception-card-heure {
  font-size: 18px;
  font-weight: 800;
  color: var(--accent-content);
  font-variant-numeric: tabular-nums;
  min-width: 58px;
}
.reception-card-infos {
  flex: 1;
  min-width: 180px;
}
.reception-card-client {
  font-weight: 600;
  color: var(--content-1);
  font-size: 14px;
}
.reception-card-vehicule {
  font-size: 12px;
  color: var(--content-3);
  margin-top: 2px;
}
.reception-card-plaque {
  display: inline-block;
  margin-left: 6px;
  padding: 1px 6px;
  border-radius: 4px;
  background: var(--overlay-hover);
  color: var(--content-2);
  font-weight: 600;
  letter-spacing: 0.5px;
}
.reception-card-type {
  font-size: 11px;
  color: var(--content-3);
  margin-top: 2px;
}
.reception-card-badges {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.edl-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
  white-space: nowrap;
}
.reception-card-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.reception-btn {
  min-height: 44px;
  padding: 10px 16px;
  font-size: 13px;
}

/* === Panneau check-in === */
.checkin-body {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.checkin-km-input {
  font-size: 18px;
  font-weight: 700;
  min-height: 48px;
  font-variant-numeric: tabular-nums;
}

.fuel-gauge {
  display: flex;
  gap: 6px;
}
.fuel-segment {
  flex: 1;
  min-height: 56px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 8px 4px;
  border-radius: 8px;
  border: 1px solid var(--border-2);
  background: var(--overlay-soft);
  color: var(--content-3);
  font-family: inherit;
  cursor: pointer;
  transition: all 0.15s ease;
}
.fuel-segment-bar {
  display: block;
  width: 100%;
  height: 8px;
  border-radius: 4px;
  background: var(--overlay-hover);
}
.fuel-segment.is-filled .fuel-segment-bar {
  background: var(--accent-soft);
}
.fuel-segment.is-selected {
  border-color: var(--accent-graphic);
  background: var(--accent-soft);
  color: var(--accent-content);
}
.fuel-segment.is-selected .fuel-segment-bar {
  background: var(--accent);
}
.fuel-segment-label {
  font-size: 12px;
  font-weight: 700;
}

.checkin-photos-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 8px;
}
.checkin-photos-count {
  font-size: 12px;
  font-weight: 700;
}
.checkin-photo-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 56px;
  border-radius: 10px;
  border: 1px dashed var(--accent);
  background: var(--accent-soft);
  color: var(--accent-content);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}
.checkin-photo-btn:hover {
  background: var(--accent-soft);
}
.checkin-photo-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(84px, 1fr));
  gap: 8px;
  margin-top: 10px;
}
.checkin-photo-thumb {
  width: 100%;
  aspect-ratio: 1;
  object-fit: cover;
  border-radius: 8px;
  border: 1px solid var(--border-2);
  cursor: zoom-in;
}
.checkin-photos-hint {
  margin-top: 8px;
  font-size: 11px;
  color: var(--content-3);
}

.checkin-alert {
  margin-top: 10px;
  padding: 10px 12px;
  border-radius: 8px;
  font-size: 13px;
}
.checkin-alert-error {
  background: var(--error-soft);
  border: 1px solid var(--error);
  color: var(--error-content);
}
.checkin-alert-ok {
  background: var(--success-soft);
  border: 1px solid var(--success);
  color: var(--success-content);
}

.checkin-signed {
  text-align: center;
  padding: 20px 12px;
  border-radius: 12px;
  background: var(--success-soft);
  border: 1px solid var(--success);
  display: flex;
  flex-direction: column;
  gap: 8px;
  align-items: center;
}
.checkin-signed-recap {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 8px;
  width: 100%;
  margin-top: 8px;
  padding: 12px;
  border-radius: 10px;
  background: var(--overlay-soft);
  font-size: 13px;
  color: var(--content-2);
  text-align: left;
}
.checkin-recap-label {
  color: var(--content-3);
}
.checkin-signed-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  justify-content: center;
  margin-top: 8px;
}

.checkin-footer {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  flex-wrap: wrap;
  width: 100%;
}
</style>
