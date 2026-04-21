<template>
  <UCard>
    <template #header>
      <div class="vo-card-head">
        <div>
          <div class="vo-card-title">Dossier moto</div>
          <div class="vo-card-subtitle">Scan terrain, OCR local, validation manuelle et archivage au meme endroit.</div>
        </div>
        <span class="vo-doc-count">{{ sortedDocuments.length }} piece(s)</span>
      </div>
    </template>

    <div class="vo-dossier-stack">
      <div class="vo-flow-banner">
        <span class="vo-flow-pill">1. Capture</span>
        <span class="vo-flow-pill">2. OCR</span>
        <span class="vo-flow-pill">3. Validation</span>
        <span class="vo-flow-pill">4. Archive dossier</span>
      </div>

      <div class="vo-verdict-box" :class="saleVerdict.status === 'vendable' ? 'is-ok' : 'is-ko'">
        <strong>{{ saleVerdict.label }}</strong>
        <span>{{ saleVerdict.summary }}</span>
        <div v-if="saleVerdict.reasons.length" class="vo-verdict-list">
          <div v-for="reason in saleVerdict.reasons" :key="reason.code" class="vo-verdict-item">
            <span class="vo-verdict-tag" :class="`is-${reason.severity}`">{{ verdictSeverityLabel(reason.severity) }}</span>
            <span>{{ reason.message }}</span>
          </div>
        </div>
      </div>

      <div class="vo-sensitive-box">
        <strong>Identite: transcription uniquement</strong>
        <span>La piece d identite et le justificatif de domicile ne doivent pas etre archives ici. On retranscrit type, numero et date, puis on detruit le support.</span>
        <div v-if="identityChecklist.length" class="vo-sensitive-list">
          <span v-for="item in identityChecklist" :key="item.key" class="vo-sensitive-pill" :class="item.completed ? 'is-ok' : item.blocking ? 'is-ko' : 'is-warn'">
            {{ item.label }}: {{ item.completed ? 'OK' : item.blocking ? 'Bloquant' : 'A faire' }}
          </span>
        </div>
      </div>

      <div class="vo-scan-panel">
        <div>
          <div class="vo-panel-title">Carte grise et OCR assiste</div>
          <p class="vo-panel-text">
            La capture archive d'abord la piece dans le dossier, puis extrait les champs vehicule a confirmer avant ecriture.
          </p>
        </div>

        <label class="vo-scan-btn" :class="{ 'is-disabled': isBusy }">
          <input
            ref="scanInput"
            type="file"
            accept="image/*"
            capture="environment"
            hidden
            :disabled="isBusy"
            @change="onScanSelected"
          />
          <span class="vo-scan-kicker">OCR</span>
          <strong>{{ ocrProcessing ? 'Analyse en cours...' : 'Scanner la carte grise' }}</strong>
          <small>{{ ocrProcessing ? 'Lecture locale Tesseract.js' : 'Ouvre l appareil photo ou la galerie du PDA.' }}</small>
        </label>
      </div>

      <div v-if="ocrMessage" class="vo-ocr-summary" :class="`is-${ocrMessage.tone}`">
        <strong>{{ lastScanName ? `Capture : ${lastScanName}` : 'OCR dossier' }}</strong>
        <span>{{ ocrMessage.message }}</span>
      </div>

      <div v-if="ocrResult" class="vo-ocr-grid">
        <div v-for="field in ocrFields" :key="field.key" class="vo-ocr-field">
          <label>{{ field.label }}</label>
          <input v-model="ocrResult[field.key]" class="vo-input" />
          <div class="vo-compare" :class="`is-${ocrComparisons[field.key]?.tone || 'neutral'}`">
            <span>{{ ocrComparisons[field.key]?.message || 'En attente de comparaison.' }}</span>
            <button
              v-if="ocrComparisons[field.key]?.canUseBase"
              type="button"
              class="vo-compare-btn"
              @click="useBaseValue(field.key)"
            >
              Reprendre dossier
            </button>
          </div>
        </div>

        <div class="vo-inline-actions">
          <button class="topbar-new-btn" :disabled="ocrSaving" @click="applyOcrData">
            {{ ocrSaving ? 'Validation...' : 'Valider et mettre a jour le vehicule' }}
          </button>
        </div>
      </div>

      <div class="vo-upload-panel">
        <div class="vo-panel-title">Ajouter une autre piece</div>

        <div class="vo-form-grid">
          <label class="vo-field">
            <span>Type</span>
            <select v-model="uploadForm.type" class="vo-select">
              <option v-for="option in documentOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
          </label>

          <label class="vo-field">
            <span>Expiration</span>
            <input v-model="uploadForm.dateExpiration" type="date" class="vo-input" />
          </label>

          <label class="vo-field vo-field-full">
            <span>Fichier</span>
            <input ref="manualInput" type="file" class="vo-input" @change="onManualFileChange" />
          </label>
        </div>

        <div class="vo-inline-actions">
          <button class="topbar-new-btn" :disabled="uploading" @click="uploadDocument">
            {{ uploading ? 'Archivage...' : 'Ajouter le document au dossier' }}
          </button>
        </div>
      </div>

      <div v-if="sortedDocuments.length" class="vo-document-list">
        <a
          v-for="document in sortedDocuments"
          :key="document.id"
          :href="buildVoDocumentUrl(document)"
          target="_blank"
          rel="noopener"
          class="vo-document-item"
        >
          <div class="vo-document-top">
            <strong>{{ documentLabel(document.type) }}</strong>
            <span v-if="document.type === 'carte_grise'" class="vo-doc-chip">OCR</span>
          </div>
          <span>{{ document.originalFilename }}</span>
          <small>{{ formatDocumentMeta(document) }}</small>
        </a>
      </div>
      <div v-else class="vo-empty-box">Aucun document archive pour le moment.</div>
    </div>
  </UCard>
</template>

<script setup lang="ts">
import type { CarteGriseOcrResult, OcrComparison, OcrFieldKey } from '~/composables/useCarteGriseOcr'
import { useVoStore } from '~/stores/vo'

interface VODossierDocument {
  id: number
  type: string
  filePath?: string
  downloadPath?: string
  originalFilename: string
  mimeType?: string
  uploadedAt?: string
  dateExpiration?: string | null
}

interface VODossierChecklistItem {
  key: string
  label: string
  completed: boolean
  blocking: boolean
}

interface VODossierSaleReason {
  code: string
  label: string
  message: string
  severity: 'critical' | 'high' | 'medium' | 'low'
  scope: string
}

interface VODossierSaleVerdict {
  status: 'vendable' | 'non_vendable'
  label: string
  summary: string
  reasons: VODossierSaleReason[]
}

const props = withDefaults(defineProps<{
  mode: 'purchase' | 'depot'
  dossierId: number
  vehicule?: Record<string, any> | null
  documents?: VODossierDocument[]
  missingDocuments?: string[]
  legalChecklist?: VODossierChecklistItem[]
  saleVerdict?: VODossierSaleVerdict | null
  reloadDetail?: () => Promise<void> | void
}>(), {
  vehicule: null,
  documents: () => [],
  missingDocuments: () => [],
  legalChecklist: () => [],
  saleVerdict: null,
  reloadDetail: undefined,
})

const voStore = useVoStore()
const toast = useToast()
const api = useApi()
const { documentLabel, documentLabels, buildVoDocumentUrl, formatDate } = useVoHelpers()
const {
  ocrFields,
  normalizeImage,
  compareOcrField,
  recognizeCarteGrise,
  summarizeOcrComparison,
  toVehicleUpdatePayload,
  createFallbackOcrResult,
  getVehiculeValue,
} = useCarteGriseOcr()

const documentTypesByMode = {
  purchase: [
    'cerfa_cession_achat',
    'cerfa_cession_vente',
    'carte_grise',
    'non_gage',
    'controle_technique',
    'pv_rachat',
    'da_siv',
    'recepisse_da',
    'mandat_immatriculation',
    'facture_vo',
    'notice_garantie',
    'autre',
  ],
  depot: [
    'contrat_depot_vente',
    'carte_grise',
    'controle_technique',
    'mandat_immatriculation',
    'notice_garantie',
    'autre',
  ],
} as const

const manualInput = ref<HTMLInputElement | null>(null)
const scanInput = ref<HTMLInputElement | null>(null)
const uploading = ref(false)
const ocrProcessing = ref(false)
const ocrSaving = ref(false)
const lastScanName = ref('')
const ocrResult = ref<CarteGriseOcrResult | null>(null)
const ocrMessage = ref<{ tone: 'success' | 'warning' | 'neutral'; message: string } | null>(null)

const uploadForm = reactive({
  type: 'carte_grise',
  dateExpiration: '',
  file: null as File | null,
})

const isBusy = computed(() => uploading.value || ocrProcessing.value || ocrSaving.value)
const documentOptions = computed(() => documentTypesByMode[props.mode].map((value) => ({ value, label: documentLabels[value] || value })))
const sortedDocuments = computed(() => {
  return [...props.documents].sort((left, right) => String(right.uploadedAt || '').localeCompare(String(left.uploadedAt || '')))
})
const identityChecklist = computed(() => props.legalChecklist.filter(item => ['seller_identity', 'deposant_identity', 'identity_storage'].includes(item.key)))
const saleVerdict = computed<VODossierSaleVerdict>(() => props.saleVerdict || {
  status: 'non_vendable',
  label: 'Verdict indisponible',
  summary: 'Le verdict de vente n a pas encore ete calcule pour ce dossier.',
  reasons: [],
})
const vehicleUpdatePath = computed(() => props.mode === 'purchase'
  ? `/vo/purchases/${props.dossierId}/vehicule`
  : `/vo/depots/${props.dossierId}/vehicule`)

const ocrComparisons = computed<Partial<Record<OcrFieldKey, OcrComparison>>>(() => {
  if (!ocrResult.value) return {}

  return ocrFields.reduce((acc, field) => {
    acc[field.key] = compareOcrField(field.key, ocrResult.value?.[field.key], getVehiculeValue(props.vehicule, field.key))
    return acc
  }, {} as Partial<Record<OcrFieldKey, OcrComparison>>)
})

watch(documentOptions, (options) => {
  if (!options.some(option => option.value === uploadForm.type)) {
    uploadForm.type = options[0]?.value || 'autre'
  }
}, { immediate: true })

function onManualFileChange(event: Event) {
  const target = event.target as HTMLInputElement
  uploadForm.file = target.files?.[0] ?? null
}

function formatDocumentMeta(document: VODossierDocument) {
  const chunks = [] as string[]

  if (document.uploadedAt) {
    chunks.push(`Archive le ${formatDate(document.uploadedAt)}`)
  }
  if (document.dateExpiration) {
    chunks.push(`Expire le ${formatDate(document.dateExpiration)}`)
  }

  return chunks.join(' • ') || 'Document archive'
}

function buildDocumentForm(file: File, type: string, dateExpiration = '') {
  const formData = new FormData()
  formData.append('file', file)
  formData.append('type', type)
  formData.append(props.mode === 'purchase' ? 'purchaseId' : 'depotId', String(props.dossierId))
  if (dateExpiration) {
    formData.append('dateExpiration', dateExpiration)
  }
  return formData
}

async function refreshDetail() {
  await props.reloadDetail?.()
}

function resetManualUpload() {
  uploadForm.file = null
  uploadForm.dateExpiration = ''
  if (manualInput.value) {
    manualInput.value.value = ''
  }
}

async function uploadDocument() {
  if (!uploadForm.file) {
    toast.add({ title: 'Erreur', description: 'Selectionnez un fichier a archiver', color: 'error' })
    return
  }

  uploading.value = true
  try {
    await voStore.uploadDocument(buildDocumentForm(uploadForm.file, uploadForm.type, uploadForm.dateExpiration))
    toast.add({ title: 'Document ajoute', color: 'success' })
    resetManualUpload()
    await refreshDetail()
  } catch (error: any) {
    toast.add({ title: 'Erreur', description: error.message, color: 'error' })
  } finally {
    uploading.value = false
  }
}

async function onScanSelected(event: Event) {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (!file) return

  ocrProcessing.value = true
  ocrResult.value = null
  ocrMessage.value = null
  lastScanName.value = file.name

  try {
    const preparedFile = await normalizeImage(file)
    await voStore.uploadDocument(buildDocumentForm(preparedFile, 'carte_grise'))
    await refreshDetail()

    try {
      const result = await recognizeCarteGrise(preparedFile, props.vehicule || {})
      ocrResult.value = result
      ocrMessage.value = summarizeOcrComparison(result, props.vehicule || {})
      toast.add({ title: 'Carte grise archivee', description: 'Lecture OCR preparee pour validation.', color: 'success' })
    } catch {
      ocrResult.value = createFallbackOcrResult(props.vehicule || {})
      ocrMessage.value = {
        tone: 'warning',
        message: 'La piece est archivee. L OCR n a pas abouti, vous pouvez corriger puis valider les champs manuellement.',
      }
      toast.add({ title: 'OCR incomplet', description: 'Le document est bien dans le dossier, validation manuelle requise.', color: 'warning' })
    }
  } catch (error: any) {
    toast.add({ title: 'Erreur', description: error.message, color: 'error' })
  } finally {
    ocrProcessing.value = false
    if (scanInput.value) {
      scanInput.value.value = ''
    }
    target.value = ''
  }
}

function useBaseValue(key: OcrFieldKey) {
  if (!ocrResult.value) return
  ocrResult.value[key] = getVehiculeValue(props.vehicule, key)
}

async function applyOcrData() {
  if (!ocrResult.value) return

  ocrSaving.value = true
  try {
    await api.put(vehicleUpdatePath.value, toVehicleUpdatePayload(ocrResult.value))
    await refreshDetail()
    ocrMessage.value = {
      tone: 'success',
      message: 'Les informations vehicule ont ete appliquees au dossier. Vous pouvez rouvrir le scan si une nouvelle capture est necessaire.',
    }
    toast.add({ title: 'Vehicule mis a jour', color: 'success' })
  } catch (error: any) {
    toast.add({ title: 'Erreur', description: error.message, color: 'error' })
  } finally {
    ocrSaving.value = false
  }
}

function verdictSeverityLabel(severity: string) {
  switch (severity) {
    case 'critical': return 'Critique'
    case 'high': return 'Bloquant'
    case 'medium': return 'Atelier'
    default: return 'Info'
  }
}
</script>

<style scoped>
.vo-card-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.vo-card-title {
  color: #e8e9ed;
  font-weight: 700;
}

.vo-card-subtitle,
.vo-doc-count,
.vo-field span {
  color: #9ca3af;
  font-size: 12px;
}

.vo-dossier-stack,
.vo-document-list {
  display: grid;
  gap: 14px;
}

.vo-verdict-box,
.vo-verdict-item {
  display: grid;
  gap: 8px;
}

.vo-verdict-box {
  padding: 14px;
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(255, 255, 255, 0.03);
}

.vo-verdict-box.is-ok {
  background: rgba(34, 197, 94, 0.08);
  border-color: rgba(34, 197, 94, 0.22);
}

.vo-verdict-box.is-ko {
  background: rgba(239, 68, 68, 0.07);
  border-color: rgba(239, 68, 68, 0.2);
}

.vo-verdict-list {
  display: grid;
  gap: 8px;
}

.vo-verdict-item {
  grid-template-columns: auto 1fr;
  align-items: center;
  padding: 10px 12px;
  border-radius: 10px;
  background: rgba(15, 17, 23, 0.28);
}

.vo-verdict-tag {
  padding: 4px 8px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.vo-verdict-tag.is-critical,
.vo-verdict-tag.is-high {
  background: rgba(239, 68, 68, 0.14);
  color: #fca5a5;
}

.vo-verdict-tag.is-medium {
  background: rgba(245, 158, 11, 0.14);
  color: #fcd34d;
}

.vo-verdict-tag.is-low {
  background: rgba(59, 130, 246, 0.14);
  color: #bfdbfe;
}

.vo-flow-banner {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.vo-flow-pill {
  padding: 7px 12px;
  border-radius: 999px;
  background: rgba(59, 130, 246, 0.08);
  border: 1px solid rgba(59, 130, 246, 0.18);
  color: #bfdbfe;
  font-size: 11px;
  font-weight: 700;
}

.vo-warning-box,
.vo-ok-box,
.vo-sensitive-box,
.vo-scan-panel,
.vo-ocr-summary,
.vo-upload-panel,
.vo-empty-box {
  display: grid;
  gap: 6px;
  padding: 14px;
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.07);
  background: rgba(255, 255, 255, 0.03);
}

.vo-warning-box {
  background: rgba(239, 68, 68, 0.06);
  border-color: rgba(239, 68, 68, 0.18);
}

.vo-ok-box {
  background: rgba(34, 197, 94, 0.07);
  border-color: rgba(34, 197, 94, 0.18);
}

.vo-sensitive-box {
  background: rgba(245, 158, 11, 0.08);
  border-color: rgba(245, 158, 11, 0.18);
}

.vo-sensitive-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.vo-sensitive-pill {
  padding: 6px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
}

.vo-sensitive-pill.is-ok {
  background: rgba(34, 197, 94, 0.12);
  color: #86efac;
}

.vo-sensitive-pill.is-ko {
  background: rgba(239, 68, 68, 0.12);
  color: #fca5a5;
}

.vo-sensitive-pill.is-warn {
  background: rgba(245, 158, 11, 0.12);
  color: #fcd34d;
}

.vo-panel-title {
  color: #f9fafb;
  font-size: 13px;
  font-weight: 700;
}

.vo-panel-text,
.vo-empty-box {
  color: #cbd5e1;
  font-size: 12px;
  line-height: 1.5;
}

.vo-scan-panel {
  grid-template-columns: minmax(0, 1.2fr) minmax(240px, 0.8fr);
  align-items: center;
  gap: 14px;
  background: radial-gradient(circle at top left, rgba(245, 158, 11, 0.14), rgba(15, 23, 42, 0.4));
}

.vo-scan-btn {
  display: grid;
  gap: 3px;
  padding: 14px;
  border-radius: 14px;
  border: 1px dashed rgba(245, 158, 11, 0.45);
  background: rgba(245, 158, 11, 0.08);
  cursor: pointer;
  color: #fef3c7;
  text-align: left;
}

.vo-scan-btn.is-disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.vo-scan-kicker {
  color: #fcd34d;
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.vo-scan-btn strong {
  color: #f9fafb;
  font-size: 14px;
}

.vo-scan-btn small {
  color: #d1d5db;
  font-size: 12px;
}

.vo-ocr-summary.is-success {
  background: rgba(34, 197, 94, 0.08);
  border-color: rgba(34, 197, 94, 0.22);
}

.vo-ocr-summary.is-warning {
  background: rgba(245, 158, 11, 0.08);
  border-color: rgba(245, 158, 11, 0.22);
}

.vo-ocr-summary.is-neutral {
  background: rgba(59, 130, 246, 0.07);
  border-color: rgba(59, 130, 246, 0.18);
}

.vo-ocr-grid,
.vo-form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.vo-ocr-field,
.vo-field {
  display: grid;
  gap: 6px;
}

.vo-ocr-field label {
  color: #9ca3af;
  font-size: 11px;
  font-weight: 700;
}

.vo-field-full,
.vo-inline-actions {
  grid-column: 1 / -1;
}

.vo-input,
.vo-select {
  width: 100%;
  padding: 10px 12px;
  border-radius: 10px;
  background: #1a1a2e;
  border: 1px solid #374151;
  color: #e8e9ed;
}

.vo-compare {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 9px 10px;
  border-radius: 10px;
  font-size: 11px;
}

.vo-compare.is-ok {
  background: rgba(34, 197, 94, 0.08);
  border: 1px solid rgba(34, 197, 94, 0.18);
  color: #86efac;
}

.vo-compare.is-warn {
  background: rgba(245, 158, 11, 0.08);
  border: 1px solid rgba(245, 158, 11, 0.18);
  color: #fcd34d;
}

.vo-compare.is-diff {
  background: rgba(239, 68, 68, 0.08);
  border: 1px solid rgba(239, 68, 68, 0.18);
  color: #fca5a5;
}

.vo-compare.is-neutral {
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.08);
  color: #cbd5e1;
}

.vo-compare-btn {
  border: 0;
  border-radius: 999px;
  padding: 6px 10px;
  background: rgba(15, 23, 42, 0.45);
  color: #f9fafb;
  font-size: 11px;
  font-weight: 700;
}

.vo-inline-actions {
  display: flex;
  justify-content: flex-end;
}

.vo-document-list {
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.vo-document-item {
  display: grid;
  gap: 8px;
  padding: 14px;
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.07);
  background: rgba(255, 255, 255, 0.02);
  text-decoration: none;
}

.vo-document-item strong {
  color: #f9fafb;
}

.vo-document-item span,
.vo-document-item small {
  color: #cbd5e1;
}

.vo-document-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.vo-doc-chip {
  padding: 4px 8px;
  border-radius: 999px;
  background: rgba(59, 130, 246, 0.12);
  color: #bfdbfe;
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
}

@media (max-width: 900px) {
  .vo-scan-panel,
  .vo-ocr-grid,
  .vo-form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
