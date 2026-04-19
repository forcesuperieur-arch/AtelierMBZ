<template>
  <div class="vo-public-shell">
    <div class="vo-public-card">
      <div class="vo-public-header">
        <div>
          <div class="vo-public-kicker">Parcours PDA VO</div>
          <h1>{{ headerTitle }}</h1>
          <p>Les documents à remplir sont générés automatiquement à partir des informations saisies puis validés par signature client.</p>
        </div>
        <div v-if="payload?.steps" class="vo-public-progress-pill">
          {{ payload.steps.completedCount }}/{{ payload.steps.totalCount }} étapes validées
        </div>
      </div>

      <div v-if="loading" class="vo-public-alert">
        Chargement du lien PDA...
      </div>

      <div v-else-if="errorMessage" class="vo-public-alert is-error">
        {{ errorMessage }}
      </div>

      <template v-else-if="payload">
        <div class="vo-public-summary-grid">
          <div class="vo-public-summary-box">
            <span>{{ roleLabel }}</span>
            <strong>{{ partyFullName }}</strong>
            <small>{{ payload.party?.telephone || 'Téléphone non renseigné' }}</small>
          </div>
          <div class="vo-public-summary-box">
            <span>Véhicule</span>
            <strong>{{ vehicleLine }}</strong>
            <small>{{ payload.vehicule?.plaque || payload.vehicule?.vin || 'À compléter' }}</small>
          </div>
          <div class="vo-public-summary-box">
            <span>Documents générés</span>
            <strong>{{ payload.dossier?.generatedDocuments?.length || 0 }}</strong>
            <small>{{ payload.mode === 'purchase' ? 'PV de rachat' : 'Contrat dépôt-vente' }}</small>
          </div>
        </div>

        <div class="vo-public-stepper">
          <button
            v-for="step in stepSequence"
            :key="step.key"
            type="button"
            class="vo-public-step-tab"
            :class="{ 'is-active': currentStep === step.key, 'is-done': step.completed }"
            @click="currentStep = step.key"
          >
            <strong>{{ step.index }}</strong>
            <span>{{ step.label }}</span>
          </button>
        </div>

        <div v-if="isSigned" class="vo-public-alert is-success">
          Signature déjà enregistrée. Le dossier est désormais verrouillé sur le PDA.
        </div>

        <section v-if="currentStep === 'seller'" class="vo-public-section">
          <div class="vo-public-section-head">
            <div>
              <h2>{{ roleLabel }}</h2>
              <p>Scanne la pièce d'identité et, si besoin, le justificatif de domicile. Les infos utiles sont retranscrites dans le dossier.</p>
            </div>
            <div class="vo-public-state" :class="payload.steps.seller.completed ? 'is-done' : 'is-pending'">
              {{ payload.steps.seller.completed ? 'Étape validée' : 'Étape à compléter' }}
            </div>
          </div>

          <div class="vo-public-form-grid">
            <label class="vo-public-field">
              <span>Type de pièce</span>
              <input v-model="sellerForm.idType" class="vo-public-input" placeholder="Carte nationale, passeport..." :disabled="isSigned">
            </label>
            <label class="vo-public-field">
              <span>Numéro de pièce</span>
              <input v-model="sellerForm.idNumber" class="vo-public-input" placeholder="Numéro relevé sur le document" :disabled="isSigned">
            </label>
            <label class="vo-public-field vo-public-field-full">
              <span>Date du document</span>
              <input v-model="sellerForm.idDate" type="date" class="vo-public-input" :disabled="isSigned">
            </label>
          </div>

          <div class="vo-public-upload-grid">
            <label class="vo-public-upload-box">
              <strong>Pièce d'identité</strong>
              <span>Ajoute une ou plusieurs photos ou un PDF du document.</span>
              <input type="file" multiple accept="image/*,.pdf" :disabled="isSigned" @change="onSellerFilesChange">
            </label>

            <label class="vo-public-upload-box">
              <strong>Justificatif de domicile</strong>
              <span>Facultatif selon le dossier. Photo ou PDF accepté.</span>
              <input type="file" multiple accept="image/*,.pdf" :disabled="isSigned" @change="onDomicileFilesChange">
            </label>
          </div>

          <div v-if="sellerFiles.length || domicileFiles.length" class="vo-public-file-list">
            <span v-for="file in [...sellerFiles, ...domicileFiles]" :key="file.name + file.size">{{ file.name }}</span>
          </div>

          <div class="vo-public-alert is-warning">
            Pièce d'identité et justificatif servent uniquement à la retranscription légale du dossier.
          </div>

          <div v-if="sellerDocuments.length" class="vo-public-doc-grid">
            <a v-for="document in sellerDocuments" :key="document.id" :href="buildVoDocumentUrl(document)" target="_blank" class="vo-public-doc-chip">
              {{ document.originalFilename || document.original_filename || documentLabel(document.type) }}
            </a>
          </div>

          <div class="vo-public-actions">
            <button type="button" class="vo-public-btn" :disabled="busy.seller || isSigned" @click="saveSellerStep">
              {{ busy.seller ? 'Enregistrement...' : 'Valider le vendeur' }}
            </button>
          </div>
        </section>

        <section v-else-if="currentStep === 'vehicle'" class="vo-public-section">
          <div class="vo-public-section-head">
            <div>
              <h2>Véhicule</h2>
              <p>Scanne la carte grise, laisse l'OCR préremplir les champs puis ajoute des photos du véhicule.</p>
            </div>
            <div class="vo-public-state" :class="payload.steps.vehicle.completed ? 'is-done' : 'is-pending'">
              {{ payload.steps.vehicle.completed ? 'Étape validée' : 'Étape à compléter' }}
            </div>
          </div>

          <div class="vo-public-upload-grid">
            <label class="vo-public-upload-box">
              <strong>Carte grise</strong>
              <span>Prends une photo depuis le PDA pour lancer l'OCR et préremplir le véhicule.</span>
              <input type="file" multiple accept="image/*" capture="environment" :disabled="isSigned" @change="onVehicleDocumentChange">
            </label>

            <label class="vo-public-upload-box">
              <strong>Photos du véhicule</strong>
              <span>Ajoute des vues générales ou des détails utiles au dossier.</span>
              <input type="file" multiple accept="image/*" capture="environment" :disabled="isSigned" @change="onVehiclePhotoChange">
            </label>
          </div>

          <div v-if="ocrNotice" class="vo-public-alert" :class="ocrNotice.tone === 'warning' ? 'is-warning' : 'is-success'">
            {{ ocrNotice.message }}
          </div>

          <div class="vo-public-form-grid">
            <label v-for="field in ocrFields" :key="field.key" class="vo-public-field">
              <span>{{ field.label }}</span>
              <input v-model="vehicleForm[field.key as OcrFieldKey]" class="vo-public-input" :disabled="isSigned">
              <small :class="`tone-${getVehicleComparison(field.key as OcrFieldKey).tone}`">
                {{ getVehicleComparison(field.key as OcrFieldKey).message }}
              </small>
            </label>
          </div>

          <div v-if="vehicleDocuments.length" class="vo-public-doc-grid">
            <a v-for="document in vehicleDocuments" :key="document.id" :href="buildVoDocumentUrl(document)" target="_blank" class="vo-public-doc-chip">
              {{ document.originalFilename || document.original_filename || documentLabel(document.type) }}
            </a>
          </div>

          <div class="vo-public-actions">
            <button type="button" class="vo-public-btn" :disabled="busy.vehicle || isSigned" @click="saveVehicleStep">
              {{ busy.vehicle ? 'Enregistrement...' : 'Valider le véhicule' }}
            </button>
          </div>
        </section>

        <section v-else-if="currentStep === 'documents'" class="vo-public-section">
          <div class="vo-public-section-head">
            <div>
              <h2>Documents</h2>
              <p>Ajoute les pièces restantes. Les documents obligatoires manquants sont listés ici.</p>
            </div>
            <div class="vo-public-state" :class="payload.steps.documents.completed ? 'is-done' : 'is-pending'">
              {{ payload.steps.documents.completed ? 'Étape validée' : 'Étape à compléter' }}
            </div>
          </div>

          <div class="vo-public-tags">
            <span
              v-for="type in payload.steps.documents.required || []"
              :key="type"
              class="vo-public-tag"
              :class="payload.steps.documents.missing?.includes(type) ? 'is-pending' : 'is-done'"
            >
              {{ documentLabel(type) }}
            </span>
          </div>

          <div class="vo-public-form-grid">
            <label class="vo-public-field vo-public-field-full">
              <span>Type de document</span>
              <select v-model="extraDocumentType" class="vo-public-input" :disabled="isSigned">
                <option value="">Choisir un type</option>
                <option v-for="type in documentTypeOptions" :key="type" :value="type">{{ documentLabel(type) }}</option>
              </select>
            </label>
          </div>

          <label class="vo-public-upload-box">
            <strong>Document à ajouter</strong>
            <span>Ajoute une photo ou un PDF correspondant au type choisi.</span>
            <input type="file" multiple accept="image/*,.pdf" :disabled="isSigned" @change="onExtraFilesChange">
          </label>

          <div v-if="extraFiles.length" class="vo-public-file-list">
            <span v-for="file in extraFiles" :key="file.name + file.size">{{ file.name }}</span>
          </div>

          <div v-if="extraDocuments.length" class="vo-public-doc-grid">
            <a v-for="document in extraDocuments" :key="document.id" :href="buildVoDocumentUrl(document)" target="_blank" class="vo-public-doc-chip">
              {{ document.originalFilename || document.original_filename || documentLabel(document.type) }}
            </a>
          </div>

          <div class="vo-public-actions">
            <button type="button" class="vo-public-btn" :disabled="busy.documents || isSigned" @click="saveDocumentsStep">
              {{ busy.documents ? 'Enregistrement...' : 'Valider les documents' }}
            </button>
          </div>
        </section>

        <section v-else class="vo-public-section">
          <div class="vo-public-section-head">
            <div>
              <h2>Signature client</h2>
              <p>La signature confirme les documents et finalise le dossier sur le PDA.</p>
            </div>
            <div class="vo-public-state" :class="payload.steps.signature.completed ? 'is-done' : 'is-pending'">
              {{ payload.steps.signature.completed ? 'Signature enregistrée' : 'Signature requise' }}
            </div>
          </div>

          <div v-if="!signatureUnlocked" class="vo-public-alert is-warning">
            Termine d'abord les étapes vendeur, véhicule et documents pour activer la signature.
          </div>

          <div class="vo-signature-box" :class="{ 'is-disabled': !signatureUnlocked }">
            <canvas
              ref="signatureCanvas"
              class="vo-signature-canvas"
              @pointerdown="startSignature"
              @pointermove="moveSignature"
              @pointerup="stopSignature"
              @pointerleave="stopSignature"
            />
          </div>

          <div class="vo-public-actions split">
            <button type="button" class="vo-public-btn is-secondary" :disabled="!signatureUnlocked" @click="clearSignature">Effacer</button>
            <button type="button" class="vo-public-btn" :disabled="busy.signature || !signatureUnlocked || !hasSignatureStroke" @click="saveSignatureStep">
              {{ busy.signature ? 'Enregistrement...' : 'Valider la signature' }}
            </button>
          </div>

          <div v-if="payload.steps.allComplete" class="vo-public-alert is-success">
            Dossier terminé. Les documents auto-générés sont maintenant confirmés avec la signature client.
          </div>
        </section>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useCarteGriseOcr } from '~/composables/useCarteGriseOcr'
import type { OcrFieldKey } from '~/composables/useCarteGriseOcr'

definePageMeta({
  layout: false,
  title: 'Parcours PDA VO',
})

type StepKey = 'seller' | 'vehicle' | 'documents' | 'signature'

const route = useRoute()
const toast = useToast()
const { apiBase, documentLabel, buildVoDocumentUrl } = useVoHelpers()
const {
  ocrFields,
  normalizeImage,
  compareOcrField,
  pickOcrImageFile,
  recognizeCarteGrise,
  summarizeOcrComparison,
  toVehicleUpdatePayload,
  createFallbackOcrResult,
} = useCarteGriseOcr()

const loading = ref(true)
const errorMessage = ref('')
const payload = ref<any | null>(null)
const currentStep = ref<StepKey>('seller')
const ocrNotice = ref<{ tone: 'warning' | 'success' | 'neutral'; message: string } | null>(null)
const busy = reactive({ seller: false, vehicle: false, documents: false, signature: false })

const sellerFiles = ref<File[]>([])
const domicileFiles = ref<File[]>([])
const vehicleDocumentFiles = ref<File[]>([])
const vehiclePhotoFiles = ref<File[]>([])
const extraFiles = ref<File[]>([])
const extraDocumentType = ref('')

const sellerForm = reactive({
  idType: '',
  idNumber: '',
  idDate: '',
})

const vehicleForm = reactive<Record<OcrFieldKey, string>>({
  plaque: '',
  marque: '',
  modele: '',
  vin: '',
  annee: '',
  cylindree: '',
  type_moto: '',
})

const signatureCanvas = ref<HTMLCanvasElement | null>(null)
const isDrawing = ref(false)
const hasSignatureStroke = ref(false)
const lastPoint = reactive({ x: 0, y: 0 })

const token = computed(() => String(route.query.token || '').trim())

const roleLabel = computed(() => {
  if (payload.value?.partyRole === 'deposant') return 'Déposant'
  return 'Vendeur'
})

const headerTitle = computed(() => {
  return payload.value?.mode === 'depot'
    ? 'Mandat dépôt-vente à finaliser'
    : 'Rachat VO à finaliser'
})

const partyFullName = computed(() => {
  return [payload.value?.party?.prenom, payload.value?.party?.nom].filter(Boolean).join(' ') || 'Client'
})

const vehicleLine = computed(() => {
  const marque = payload.value?.vehicule?.marque || vehicleForm.marque || 'Moto'
  const modele = payload.value?.vehicule?.modele || vehicleForm.modele || ''
  return `${marque} ${modele}`.trim()
})

const uploadedDocuments = computed(() => payload.value?.documents || [])

const sellerDocuments = computed(() => uploadedDocuments.value.filter((document: any) => ['piece_identite', 'justificatif_domicile'].includes(document.type)))

const vehicleDocuments = computed(() => uploadedDocuments.value.filter((document: any) => ['carte_grise', 'photo_vehicule'].includes(document.type)))

const extraDocuments = computed(() => uploadedDocuments.value.filter((document: any) => !['piece_identite', 'justificatif_domicile', 'carte_grise', 'photo_vehicule', 'signature_client'].includes(document.type)))

const documentTypeOptions = computed(() => {
  const required = payload.value?.steps?.documents?.required || []
  const additional = payload.value?.steps?.additionalDocumentOptions || []
  return [...new Set([...required, ...additional])]
})

const signatureUnlocked = computed(() => {
  const steps = payload.value?.steps
  return !isSigned.value && !!(steps?.seller?.completed && steps?.vehicle?.completed && steps?.documents?.completed)
})

const isSigned = computed(() => !!payload.value?.steps?.signature?.completed)

const stepSequence = computed(() => {
  const steps = payload.value?.steps || {}
  return [
    { key: 'seller' as const, label: steps.seller?.label || 'Vendeur', completed: !!steps.seller?.completed, index: '01' },
    { key: 'vehicle' as const, label: steps.vehicle?.label || 'Vehicule', completed: !!steps.vehicle?.completed, index: '02' },
    { key: 'documents' as const, label: steps.documents?.label || 'Documents', completed: !!steps.documents?.completed, index: '03' },
    { key: 'signature' as const, label: steps.signature?.label || 'Signature', completed: !!steps.signature?.completed, index: '04' },
  ]
})

watch(token, () => {
  loadPayload()
}, { immediate: true })

onMounted(() => {
  nextTick(() => resizeSignatureCanvas())
  if (import.meta.client) {
    window.addEventListener('resize', resizeSignatureCanvas)
  }
})

onBeforeUnmount(() => {
  if (import.meta.client) {
    window.removeEventListener('resize', resizeSignatureCanvas)
  }
})

async function loadPayload() {
  if (!token.value) {
    errorMessage.value = 'Lien PDA incomplet.'
    loading.value = false
    return
  }

  loading.value = true
  errorMessage.value = ''

  try {
    const result = await $fetch(`${apiBase}/public/vo-companion/${token.value}`)
    applyPayload(result)
  } catch (error: any) {
    errorMessage.value = error?.data?.error || error?.message || 'Impossible de charger le parcours PDA.'
  } finally {
    loading.value = false
  }
}

function applyPayload(nextPayload: any) {
  payload.value = nextPayload

  sellerForm.idType = nextPayload?.party?.idType || ''
  sellerForm.idNumber = nextPayload?.party?.idNumber || ''
  sellerForm.idDate = nextPayload?.party?.idDate || ''

  const fallback = createFallbackOcrResult(nextPayload?.vehicule || {})
  vehicleForm.plaque = String(nextPayload?.vehicule?.plaque || fallback.plaque || '')
  vehicleForm.marque = String(nextPayload?.vehicule?.marque || fallback.marque || '')
  vehicleForm.modele = String(nextPayload?.vehicule?.modele || fallback.modele || '')
  vehicleForm.vin = String(nextPayload?.vehicule?.vin || fallback.vin || '')
  vehicleForm.annee = String(nextPayload?.vehicule?.annee || fallback.annee || '')
  vehicleForm.cylindree = String(nextPayload?.vehicule?.cylindree || fallback.cylindree || '')
  vehicleForm.type_moto = String(nextPayload?.vehicule?.typeMoto || nextPayload?.vehicule?.type_moto || fallback.type_moto || '')

  currentStep.value = getFirstOpenStep(nextPayload?.steps)
  nextTick(() => resizeSignatureCanvas())
}

function getFirstOpenStep(steps: any): StepKey {
  if (!steps?.seller?.completed) return 'seller'
  if (!steps?.vehicle?.completed) return 'vehicle'
  if (!steps?.documents?.completed) return 'documents'
  return 'signature'
}

function onSellerFilesChange(event: Event) {
  sellerFiles.value = extractFiles(event)
}

function onDomicileFilesChange(event: Event) {
  domicileFiles.value = extractFiles(event)
}

async function onVehicleDocumentChange(event: Event) {
  vehicleDocumentFiles.value = extractFiles(event)
  ocrNotice.value = null

  const { file: ocrFile, warning } = pickOcrImageFile(vehicleDocumentFiles.value)
  if (warning) {
    ocrNotice.value = { tone: 'warning', message: warning }
  }
  if (!ocrFile) return

  try {
    const normalized = await normalizeImage(ocrFile)
    const ocrResult = await recognizeCarteGrise(normalized, payload.value?.vehicule || {})
    Object.assign(vehicleForm, ocrResult)
    ocrNotice.value = summarizeOcrComparison(ocrResult, payload.value?.vehicule || {})
  } catch {
    ocrNotice.value = {
      tone: 'warning',
      message: 'Lecture OCR impossible. Tu peux saisir les informations véhicule manuellement.',
    }
  }
}

function onVehiclePhotoChange(event: Event) {
  vehiclePhotoFiles.value = extractFiles(event)
}

function onExtraFilesChange(event: Event) {
  extraFiles.value = extractFiles(event)
}

function getVehicleFieldValue(key: OcrFieldKey) {
  return vehicleForm[key]
}

function getVehicleBaseValue(key: OcrFieldKey) {
  if (key === 'type_moto') {
    return payload.value?.vehicule?.typeMoto ?? payload.value?.vehicule?.type_moto
  }

  return payload.value?.vehicule?.[key]
}

function getVehicleComparison(key: OcrFieldKey) {
  return compareOcrField(key, getVehicleFieldValue(key), getVehicleBaseValue(key))
}

async function saveSellerStep() {
  if (!payload.value) return

  busy.seller = true
  try {
    const formData = new FormData()
    formData.append('idType', sellerForm.idType)
    formData.append('idNumber', sellerForm.idNumber)
    if (sellerForm.idDate) formData.append('idDate', sellerForm.idDate)
    sellerFiles.value.forEach(file => formData.append('files[]', file))

    let result = await $fetch(`${apiBase}/public/vo-companion/${token.value}/seller`, {
      method: 'POST',
      body: formData,
    })

    if (domicileFiles.value.length) {
      const domicileData = new FormData()
      domicileData.append('type', 'justificatif_domicile')
      domicileFiles.value.forEach(file => domicileData.append('files[]', file))

      result = await $fetch(`${apiBase}/public/vo-companion/${token.value}/document`, {
        method: 'POST',
        body: domicileData,
      })
    }

    sellerFiles.value = []
    domicileFiles.value = []
    applyPayload(result)
    toast.add({ title: `${roleLabel.value} validé`, color: 'success' })
  } catch (error: any) {
    toast.add({ title: 'Erreur', description: error?.data?.error || error.message, color: 'error' })
  } finally {
    busy.seller = false
  }
}

async function saveVehicleStep() {
  if (!payload.value) return

  busy.vehicle = true
  try {
    if (vehicleDocumentFiles.value.length) {
      const vehicleDocumentData = new FormData()
      vehicleDocumentFiles.value.forEach(file => vehicleDocumentData.append('files[]', file))
      await $fetch(`${apiBase}/public/vo-companion/${token.value}/vehicle-document`, {
        method: 'POST',
        body: vehicleDocumentData,
      })
    }

    if (vehiclePhotoFiles.value.length) {
      const vehiclePhotoData = new FormData()
      vehiclePhotoFiles.value.forEach(file => vehiclePhotoData.append('files[]', file))
      await $fetch(`${apiBase}/public/vo-companion/${token.value}/vehicle-photo`, {
        method: 'POST',
        body: vehiclePhotoData,
      })
    }

    const result = await $fetch(`${apiBase}/public/vo-companion/${token.value}/vehicle-data`, {
      method: 'PUT',
      body: toVehicleUpdatePayload(vehicleForm),
    })

    vehicleDocumentFiles.value = []
    vehiclePhotoFiles.value = []
    applyPayload(result)
    toast.add({ title: 'Véhicule validé', color: 'success' })
  } catch (error: any) {
    toast.add({ title: 'Erreur', description: error?.data?.error || error.message, color: 'error' })
  } finally {
    busy.vehicle = false
  }
}

async function saveDocumentsStep() {
  if (!payload.value) return
  if (!extraDocumentType.value) {
    toast.add({ title: 'Erreur', description: 'Choisis un type de document', color: 'error' })
    return
  }
  if (!extraFiles.value.length) {
    toast.add({ title: 'Erreur', description: 'Ajoute au moins un document', color: 'error' })
    return
  }

  busy.documents = true
  try {
    const formData = new FormData()
    formData.append('type', extraDocumentType.value)
    extraFiles.value.forEach(file => formData.append('files[]', file))

    const result = await $fetch(`${apiBase}/public/vo-companion/${token.value}/document`, {
      method: 'POST',
      body: formData,
    })

    extraFiles.value = []
    extraDocumentType.value = ''
    applyPayload(result)
    toast.add({ title: 'Documents validés', color: 'success' })
  } catch (error: any) {
    toast.add({ title: 'Erreur', description: error?.data?.error || error.message, color: 'error' })
  } finally {
    busy.documents = false
  }
}

async function saveSignatureStep() {
  const canvas = signatureCanvas.value
  if (!canvas) return

  busy.signature = true
  try {
    const result = await $fetch(`${apiBase}/public/vo-companion/${token.value}/signature`, {
      method: 'POST',
      body: { signature: canvas.toDataURL('image/png') },
    })

    applyPayload(result)
    clearSignature()
    toast.add({ title: 'Signature enregistrée', color: 'success' })
  } catch (error: any) {
    toast.add({ title: 'Erreur', description: error?.data?.error || error.message, color: 'error' })
  } finally {
    busy.signature = false
  }
}

function extractFiles(event: Event): File[] {
  return Array.from((event.target as HTMLInputElement)?.files || [])
}

function resizeSignatureCanvas() {
  const canvas = signatureCanvas.value
  if (!canvas) return

  const rect = canvas.getBoundingClientRect()
  if (!rect.width || !rect.height) return

  const ratio = import.meta.client ? window.devicePixelRatio || 1 : 1
  canvas.width = Math.round(rect.width * ratio)
  canvas.height = Math.round(rect.height * ratio)

  const ctx = canvas.getContext('2d')
  if (!ctx) return

  ctx.setTransform(ratio, 0, 0, ratio, 0, 0)
  ctx.fillStyle = '#ffffff'
  ctx.fillRect(0, 0, rect.width, rect.height)
  ctx.lineCap = 'round'
  ctx.lineJoin = 'round'
  ctx.lineWidth = 2.5
  ctx.strokeStyle = '#111827'
  hasSignatureStroke.value = false
}

function getSignaturePoint(event: PointerEvent) {
  const canvas = signatureCanvas.value
  if (!canvas) return { x: 0, y: 0 }

  const rect = canvas.getBoundingClientRect()
  return {
    x: event.clientX - rect.left,
    y: event.clientY - rect.top,
  }
}

function startSignature(event: PointerEvent) {
  if (!signatureUnlocked.value) return

  const canvas = signatureCanvas.value
  const ctx = canvas?.getContext('2d')
  if (!canvas || !ctx) return

  const point = getSignaturePoint(event)
  lastPoint.x = point.x
  lastPoint.y = point.y
  isDrawing.value = true
  hasSignatureStroke.value = true
  ctx.beginPath()
  ctx.moveTo(point.x, point.y)
}

function moveSignature(event: PointerEvent) {
  if (!isDrawing.value) return

  const canvas = signatureCanvas.value
  const ctx = canvas?.getContext('2d')
  if (!canvas || !ctx) return

  const point = getSignaturePoint(event)
  ctx.lineTo(point.x, point.y)
  ctx.stroke()
  lastPoint.x = point.x
  lastPoint.y = point.y
}

function stopSignature() {
  isDrawing.value = false
}

function clearSignature() {
  resizeSignatureCanvas()
}
</script>

<style scoped>
.vo-public-shell {
  min-height: 100vh;
  padding: 28px 16px;
  background:
    radial-gradient(circle at top left, rgba(251, 191, 36, 0.14), transparent 28%),
    radial-gradient(circle at top right, rgba(59, 130, 246, 0.12), transparent 24%),
    linear-gradient(180deg, #0f172a 0%, #111827 55%, #0b1120 100%);
}

.vo-public-card {
  width: min(100%, 980px);
  margin: 0 auto;
  padding: 24px;
  border-radius: 28px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(15, 23, 42, 0.82);
  backdrop-filter: blur(18px);
  box-shadow: 0 24px 80px rgba(0, 0, 0, 0.35);
}

.vo-public-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 22px;
}

.vo-public-header h1 {
  margin: 8px 0 6px;
  color: #f8fafc;
  font-size: clamp(28px, 5vw, 42px);
  line-height: 1.05;
}

.vo-public-header p,
.vo-public-summary-box span,
.vo-public-summary-box small,
.vo-public-field span,
.vo-public-upload-box span,
.vo-public-alert,
.vo-public-tag,
.vo-public-doc-chip,
.vo-public-state {
  color: #cbd5e1;
}

.vo-public-kicker {
  color: #fbbf24;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.16em;
  font-weight: 700;
}

.vo-public-progress-pill {
  padding: 10px 14px;
  border-radius: 999px;
  background: rgba(248, 250, 252, 0.08);
  color: #f8fafc;
  white-space: nowrap;
}

.vo-public-summary-grid,
.vo-public-upload-grid,
.vo-public-form-grid,
.vo-public-doc-grid,
.vo-public-stepper {
  display: grid;
  gap: 12px;
}

.vo-public-summary-grid {
  grid-template-columns: repeat(3, minmax(0, 1fr));
  margin-bottom: 20px;
}

.vo-public-summary-box,
.vo-public-section,
.vo-public-upload-box,
.vo-public-alert,
.vo-signature-box {
  border-radius: 20px;
  border: 1px solid rgba(255, 255, 255, 0.09);
  background: rgba(255, 255, 255, 0.04);
}

.vo-public-summary-box {
  padding: 14px;
  display: grid;
  gap: 4px;
}

.vo-public-summary-box strong,
.vo-public-section h2,
.vo-public-upload-box strong,
.vo-public-step-tab span {
  color: #f8fafc;
}

.vo-public-stepper {
  grid-template-columns: repeat(4, minmax(0, 1fr));
  margin-bottom: 20px;
}

.vo-public-step-tab {
  appearance: none;
  border: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(255, 255, 255, 0.03);
  padding: 14px;
  border-radius: 18px;
  display: grid;
  gap: 6px;
  justify-items: start;
  cursor: pointer;
}

.vo-public-step-tab strong {
  color: #fbbf24;
  font-size: 11px;
  letter-spacing: 0.12em;
}

.vo-public-step-tab.is-active {
  border-color: rgba(251, 191, 36, 0.35);
  background: rgba(251, 191, 36, 0.08);
}

.vo-public-step-tab.is-done {
  border-color: rgba(34, 197, 94, 0.35);
}

.vo-public-section {
  padding: 18px;
  display: grid;
  gap: 16px;
}

.vo-public-section-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.vo-public-section-head h2 {
  margin: 0 0 6px;
}

.vo-public-state {
  padding: 8px 12px;
  border-radius: 999px;
  white-space: nowrap;
  font-size: 12px;
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.vo-public-state.is-done {
  color: #bbf7d0;
  background: rgba(34, 197, 94, 0.12);
}

.vo-public-state.is-pending {
  color: #fde68a;
  background: rgba(245, 158, 11, 0.12);
}

.vo-public-form-grid {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.vo-public-field,
.vo-public-upload-box {
  display: grid;
  gap: 8px;
}

.vo-public-field-full {
  grid-column: 1 / -1;
}

.vo-public-input {
  width: 100%;
  min-height: 46px;
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(15, 23, 42, 0.82);
  color: #f8fafc;
  padding: 12px 14px;
}

.vo-public-upload-grid {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.vo-public-upload-box {
  padding: 16px;
}

.vo-public-upload-box input {
  color: #f8fafc;
}

.vo-public-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.vo-public-actions.split {
  justify-content: space-between;
}

.vo-public-btn {
  appearance: none;
  border: 0;
  border-radius: 14px;
  padding: 12px 18px;
  background: linear-gradient(135deg, #f59e0b, #fb7185);
  color: #111827;
  font-weight: 800;
  cursor: pointer;
}

.vo-public-btn.is-secondary {
  background: rgba(255, 255, 255, 0.1);
  color: #f8fafc;
}

.vo-public-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.vo-public-alert {
  padding: 14px 16px;
}

.vo-public-alert.is-error {
  border-color: rgba(239, 68, 68, 0.35);
  background: rgba(239, 68, 68, 0.12);
}

.vo-public-alert.is-warning {
  border-color: rgba(245, 158, 11, 0.35);
  background: rgba(245, 158, 11, 0.12);
}

.vo-public-alert.is-success {
  border-color: rgba(34, 197, 94, 0.35);
  background: rgba(34, 197, 94, 0.12);
}

.vo-public-tags,
.vo-public-file-list,
.vo-public-doc-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.vo-public-tag,
.vo-public-file-list span,
.vo-public-doc-chip {
  padding: 10px 12px;
  border-radius: 999px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(255, 255, 255, 0.05);
  font-size: 12px;
  text-decoration: none;
}

.vo-public-tag.is-done {
  border-color: rgba(34, 197, 94, 0.35);
}

.vo-public-tag.is-pending {
  border-color: rgba(245, 158, 11, 0.35);
}

.vo-signature-box {
  padding: 14px;
}

.vo-signature-box.is-disabled {
  opacity: 0.55;
}

.vo-signature-canvas {
  display: block;
  width: 100%;
  height: 220px;
  border-radius: 16px;
  background: white;
  touch-action: none;
}

.tone-ok {
  color: #86efac;
}

.tone-warn {
  color: #fde68a;
}

.tone-diff {
  color: #fca5a5;
}

.tone-neutral {
  color: #94a3b8;
}

@media (max-width: 860px) {
  .vo-public-card {
    padding: 18px;
  }

  .vo-public-header,
  .vo-public-section-head {
    flex-direction: column;
  }

  .vo-public-summary-grid,
  .vo-public-stepper,
  .vo-public-upload-grid,
  .vo-public-form-grid {
    grid-template-columns: 1fr;
  }

  .vo-public-actions,
  .vo-public-actions.split {
    flex-direction: column;
    justify-content: stretch;
  }
}
</style>