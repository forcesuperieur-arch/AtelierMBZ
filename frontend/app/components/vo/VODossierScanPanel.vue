<template>
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
      <UInput v-model="ocrResult[field.key]" />
      <div class="vo-compare" :class="`is-${ocrComparisons[field.key]?.tone || 'neutral'}`">
        <span>{{ ocrComparisons[field.key]?.message || 'En attente de comparaison.' }}</span>
        <UButton
          v-if="ocrComparisons[field.key]?.canUseBase"
          type="button"
           class="vo-compare-" color="primary">
          Reprendre dossier
        </UButton>
      </div>
    </div>

    <div class="vo-inline-actions">
      <UButton  class="topbar-new-" color="primary">
        {{ ocrSaving ? 'Validation...' : 'Valider et mettre a jour le vehicule' }}
      </UButton>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { CarteGriseOcrResult, OcrComparison, OcrFieldKey } from '~/composables/useCarteGriseOcr'
import { useVoStore } from '~/stores/vo'

const props = defineProps<{
  dossierId: number
  mode: 'purchase' | 'depot'
  vehicule?: Record<string, any> | null
  reloadDetail?: () => Promise<void> | void
}>()

const voStore = useVoStore()
const toast = useToast()
const api = useApi()
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

const scanInput = ref<HTMLInputElement | null>(null)
const ocrProcessing = ref(false)
const ocrSaving = ref(false)
const lastScanName = ref('')
const ocrResult = ref<CarteGriseOcrResult | null>(null)
const ocrMessage = ref<{ tone: 'success' | 'warning' | 'neutral'; message: string } | null>(null)

const isBusy = computed(() => ocrProcessing.value || ocrSaving.value)

const vehicleUpdatePath = computed(() =>
  props.mode === 'purchase'
    ? `/vo/purchases/${props.dossierId}/vehicule`
    : `/vo/depots/${props.dossierId}/vehicule`,
)

const ocrComparisons = computed<Partial<Record<OcrFieldKey, OcrComparison>>>(() => {
  if (!ocrResult.value) return {}

  return ocrFields.reduce((acc, field) => {
    acc[field.key] = compareOcrField(field.key, ocrResult.value?.[field.key], getVehiculeValue(props.vehicule, field.key))
    return acc
  }, {} as Partial<Record<OcrFieldKey, OcrComparison>>)
})

async function refreshDetail() {
  await props.reloadDetail?.()
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
</script>

<style scoped>
.vo-scan-panel {
  display: grid;
  grid-template-columns: minmax(0, 1.2fr) minmax(240px, 0.8fr);
  align-items: center;
  gap: 14px;
  padding: 14px;
  border-radius: 14px;
  border: 1px solid var(--warning-border);
  background: linear-gradient(135deg, var(--warning-bg), var(--bg-elevated));
}

.vo-panel-title {
  color: var(--text-primary);
  font-size: 13px;
  font-weight: 700;
}

.vo-panel-text {
  color: var(--text-secondary);
  font-size: 12px;
  line-height: 1.5;
}

.vo-scan-btn {
  display: grid;
  gap: 3px;
  padding: 14px;
  border-radius: 14px;
  border: 1px dashed var(--warning-border);
  background: var(--warning-bg);
  cursor: pointer;
  color: var(--text-primary);
  text-align: left;
}

.vo-scan-btn.is-disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.vo-scan-kicker {
  color: var(--warning);
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.vo-scan-btn strong {
  color: var(--text-primary);
  font-size: 14px;
}

.vo-scan-btn small {
  color: var(--text-secondary);
  font-size: 12px;
}

.vo-ocr-summary {
  display: grid;
  gap: 6px;
  padding: 14px;
  border-radius: 14px;
  border: 1px solid var(--border-default);
  background: var(--bg-elevated);
}

.vo-ocr-summary.is-success {
  background: var(--success-bg);
  border-color: var(--success-border);
}

.vo-ocr-summary.is-warning {
  background: var(--warning-bg);
  border-color: var(--warning-border);
}

.vo-ocr-summary.is-neutral {
  background: var(--info-bg);
  border-color: var(--info-border);
}

.vo-ocr-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.vo-ocr-field {
  display: grid;
  gap: 6px;
}

.vo-ocr-field label {
  color: var(--text-tertiary);
  font-size: 11px;
  font-weight: 700;
}

.vo-input {
  width: 100%;
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--bg-surface);
  border: 1px solid var(--border-default);
  color: var(--text-primary);
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
  background: var(--success-bg);
  border: 1px solid var(--success-border);
  color: var(--success);
}

.vo-compare.is-warn {
  background: var(--warning-bg);
  border: 1px solid var(--warning-border);
  color: var(--warning);
}

.vo-compare.is-diff {
  background: var(--danger-bg);
  border: 1px solid var(--danger-border);
  color: var(--danger-fg);
}

.vo-compare.is-neutral {
  background: var(--bg-elevated);
  border: 1px solid var(--border-default);
  color: var(--text-secondary);
}

.vo-compare-btn {
  border: 0;
  border-radius: 999px;
  padding: 6px 10px;
  background: var(--bg-elevated);
  color: var(--text-primary);
  font-size: 11px;
  font-weight: 700;
}

.vo-inline-actions {
  grid-column: 1 / -1;
  display: flex;
  justify-content: flex-end;
}

@media (max-width: 900px) {
  .vo-scan-panel,
  .vo-ocr-grid {
    grid-template-columns: 1fr;
  }
}
</style>
