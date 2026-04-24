<template>
  <div>
    <NuxtLayout name="public">
      <div class="companion-container">
        <div v-if="loading" class="text-center p-10">
          <div class="text-4xl mb-3">⏳</div>
          <p class="text-muted">Chargement…</p>
        </div>

        <div v-else-if="error" class="text-center p-10">
          <div class="text-5xl mb-4">🚫</div>
          <h2 style="color:#FCA5A5;font-size:18px;margin-bottom:8px;">Lien invalide</h2>
          <p style="color:#9CA3AF;font-size:13px;">{{ error }}</p>
        </div>

        <div v-else-if="rdv">
          <!-- Header -->
          <div class="companion-header">
            <div class="text-3xl">🏍</div>
            <div>
              <h1 class="header-lg m-0">Réception PDA</h1>
              <p style="font-size:12px;color:#9CA3AF;margin:2px 0 0;">RDV #{{ rdv.id }} · {{ rdv.date_rdv }} à {{ rdv.heure_rdv }}</p>
            </div>
          </div>

          <!-- RDV Info Summary -->
          <div class="companion-card">
            <div class="info-grid text-md-value">
              <div><span class="text-subtle">Client :</span> <span class="text-value">{{ rdv.client?.prenom }} {{ rdv.client?.nom }}</span></div>
              <div><span class="text-subtle">Statut :</span> <span class="text-value">{{ rdv.statut }}</span></div>
              <div><span class="text-subtle">Véhicule :</span> <span class="text-value">{{ rdv.vehicule?.marque }} {{ rdv.vehicule?.modele }}</span></div>
              <div><span class="text-subtle">Plaque :</span> <span class="text-value">{{ rdv.vehicule?.plaque || '—' }}</span></div>
              <div class="col-span-full"><span class="text-subtle">Intervention :</span> <span class="text-value">{{ rdv.type_intervention }}</span></div>
            </div>
          </div>

          <!-- [C7] Restitution — signature client sur le rapport d'intervention -->
          <div v-if="rdv.statut === 'termine'" class="companion-card" style="border-color:rgba(16,185,129,0.4);margin-bottom:12px;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
              <span class="text-3xl">🏁</span>
              <div>
                <h2 class="m-0 header-md text-success">Intervention terminée</h2>
                <p class="mt-0 text-md-muted">Signez le rapport pour valider la restitution</p>
              </div>
            </div>

            <div v-if="restitutionDone" style="text-align:center;padding:16px;color:#6EE7B7;">
              Moto restituée — merci pour votre confiance !
            </div>

            <template v-else>
              <div v-if="restitutionData" class="text-md-value mb-3 leading-relaxed">
                <div v-if="restitutionData.rapport?.travaux_realises"><strong class="text-muted">Travaux réalisés :</strong> {{ restitutionData.rapport.travaux_realises }}</div>
                <div v-if="restitutionData.rapport?.kilometrage_restitution"><strong class="text-muted">Kilométrage restitution :</strong> {{ restitutionData.rapport.kilometrage_restitution }} km</div>
                <div v-if="restitutionData.rapport?.alertes?.length"><strong style="color:#FCA5A5;">Alertes :</strong> {{ restitutionData.rapport.alertes.join(', ') }}</div>
              </div>

              <div v-if="restitutionData?.rapport?.already_signed_by_client" style="color:#6EE7B7;text-align:center;padding:12px;">
                Vous avez déjà signé ce rapport.
              </div>

              <template v-else-if="restitutionData">
                <p class="text-md-muted mb-2">Signez ci-dessous pour confirmer la restitution :</p>
                <div class="sig-canvas-wrapper mb-2">
                  <canvas
                    ref="sigCanvasRestitution"
                    width="320"
                    height="130"
                    style="background:#fff;border-radius:8px;width:100%;touch-action:none;cursor:crosshair;"
                    @pointerdown="startDrawRestitution"
                    @pointermove="drawRestitution"
                    @pointerup="endDrawRestitution"
                    @pointerleave="endDrawRestitution"
                  ></canvas>
                </div>
                <div class="flex-wrap-gap">
                  <button class="companion-capture-btn flex-1" @click="clearRestitutionSignature">Effacer</button>
                  <button
                    class="companion-capture-btn"
                    style="flex:2;background:rgba(16,185,129,0.2);border-color:rgba(16,185,129,0.4);color:#6EE7B7;"
                    :disabled="restitutionSaving || !restitutionHasDrawn"
                    @click="submitSignatureRestitution"
                  >{{ restitutionSaving ? 'Envoi…' : 'Valider la restitution' }}</button>
                </div>
                <p v-if="restitutionError" style="color:#FCA5A5;font-size:12px;margin-top:8px;">{{ restitutionError }}</p>
              </template>

              <div v-else style="text-align:center;color:#9CA3AF;font-size:13px;">
                <button class="companion-capture-btn" @click="fetchRapportRestitution">Charger le rapport</button>
              </div>
            </template>
          </div>

          <!-- Status Pills -->
          <div class="companion-status-row">
            <div class="companion-pill" :class="{ done: rdv.photos_count > 0 }">
              📸 {{ rdv.photos_count }} photo{{ rdv.photos_count !== 1 ? 's' : '' }}
            </div>
            <div class="companion-pill" :class="{ done: carteGriseScanned }">
              🪪 CG {{ carteGriseScanned ? '✓' : '' }}
            </div>
            <div class="companion-pill" :class="{ done: checkupDone > 0 }">
              🔎 {{ checkupDone }}/{{ checkupItems.length }}
            </div>
            <div class="companion-pill" :class="{ done: rdv.has_signature }">
              ✍️ Signé {{ rdv.has_signature ? '✓' : '' }}
            </div>
          </div>

          <div v-if="statusError || statusMessage" class="companion-card" :style="statusError ? 'border-color:rgba(239,68,68,0.3);color:#FCA5A5;' : 'border-color:rgba(16,185,129,0.3);color:#6EE7B7;'">
            {{ statusError || statusMessage }}
          </div>

          <!-- Action Buttons (main menu) -->
          <div v-if="!activeSection" class="companion-actions">
            <button class="companion-action-btn" @click="activeSection = 'photos'">
              <span class="text-4xl">📸</span>
              <div>
                <span class="header-md">Photos véhicule</span>
                <span class="text-xs-muted block">État extérieur / intérieur</span>
              </div>
            </button>

            <button class="companion-action-btn" @click="activeSection = 'carte-grise'">
              <span class="text-4xl">🪪</span>
              <div>
                <span class="header-md">Scanner carte grise</span>
                <span class="text-xs-muted block">OCR FR / BE auto-remplissage</span>
              </div>
            </button>

            <button class="companion-action-btn" @click="activeSection = 'checkup'">
              <span class="text-4xl">🔎</span>
              <div>
                <span class="header-md">Checkup express</span>
                <span class="text-xs-muted block">{{ checkupDone }}/{{ checkupItems.length }} point{{ checkupDone > 1 ? 's' : '' }} vérifié{{ checkupDone > 1 ? 's' : '' }}</span>
              </div>
            </button>

            <button class="companion-action-btn" @click="activeSection = 'signature'">
              <span class="text-4xl">✍️</span>
              <div>
                <span class="header-md">Signature client</span>
                <span class="text-xs-muted block">{{ rdv.has_signature ? 'Déjà signé ✓' : 'Obligatoire' }}</span>
              </div>
            </button>
          </div>

          <!-- PHOTOS SECTION -->
          <div v-if="activeSection === 'photos'" class="companion-section">
            <div class="companion-section-header">
              <h2>📸 Photos du véhicule</h2>
              <button class="companion-close" @click="activeSection = null">✕</button>
            </div>

            <div class="flex-col-gap">
              <label class="companion-capture-btn">
                <input type="file" accept="image/*" capture="environment" multiple @change="onPhotosSelected" class="hidden" />
                <span class="text-2xl">📷</span>
                <span>{{ uploading ? 'Envoi en cours…' : 'Prendre une photo' }}</span>
              </label>

              <div v-if="rdv.photos?.length" class="companion-photo-grid">
                <div v-for="photo in rdv.photos" :key="photo.id" class="companion-photo-thumb">
                  <img :src="photoUrl(photo.url)" :alt="photo.description || 'Photo'" />
                </div>
              </div>
              <p v-else style="text-align:center;color:#6B7280;font-size:13px;padding:20px;">Aucune photo pour l'instant</p>
            </div>
          </div>

          <!-- CARTE GRISE SECTION -->
          <div v-if="activeSection === 'carte-grise'" class="companion-section">
            <div class="companion-section-header">
              <h2>🪪 Carte grise</h2>
              <button class="companion-close" @click="activeSection = null">✕</button>
            </div>

            <div class="flex-col-gap">
              <label class="companion-capture-btn">
                <input type="file" accept="image/*" capture="environment" @change="onCarteGriseSelected" class="hidden" />
                <span class="text-2xl">📷</span>
                <span>{{ ocrProcessing ? 'Analyse OCR en cours…' : 'Photographier la carte grise / certificat' }}</span>
              </label>

              <div v-if="ocrProcessing" class="text-center p-5">
                <div class="ocr-spinner"></div>
                <p style="color:#FBBF24;font-size:13px;margin-top:12px;">Extraction des informations…</p>
                <p style="color:#6B7280;font-size:11px;">Cela peut prendre quelques secondes</p>
              </div>

              <div v-if="ocrResult" class="companion-card" style="border-color:rgba(16,185,129,0.3);">
                <div class="text-lg-primary text-success mb-2">✓ Données extraites — vérifiez et corrigez si besoin</div>
                <div class="flex-col-gap-sm">
                  <div v-for="field in ocrFields" :key="field.key" class="ocr-field">
                    <label>{{ field.label }}</label>
                    <input v-model="ocrResult[field.key]" class="companion-input" />
                    <div
                      v-if="ocrComparisons[field.key]"
                      class="ocr-compare"
                      :class="`ocr-compare--${ocrComparisons[field.key]?.tone || 'neutral'}`"
                    >
                      <span>{{ ocrComparisons[field.key]?.message }}</span>
                      <button
                        v-if="ocrComparisons[field.key]?.canUseBase"
                        type="button"
                        class="ocr-compare-btn"
                        @click="useBaseValue(field.key)"
                      >
                        Reprendre la base
                      </button>
                    </div>
                  </div>
                </div>
                <button
                  class="companion-validate-btn mt-3"
                  :disabled="ocrSaving"
                  @click="applyOcrData"
                >
                  {{ ocrSaving ? 'Application…' : '✓ Appliquer au véhicule' }}
                </button>
              </div>
            </div>
          </div>

          <!-- CHECKUP SECTION -->
          <div v-if="activeSection === 'checkup'" class="companion-section">
            <div class="companion-section-header">
              <h2>🔎 Checkup express</h2>
              <button class="companion-close" @click="activeSection = null">✕</button>
            </div>

            <p class="text-md-muted mb-3">
              Touchez chaque point pour faire défiler : non vérifié → OK → NOK.
            </p>

            <div class="checkup-grid">
              <button
                v-for="item in checkupItems"
                :key="item.key"
                type="button"
                class="checkup-item"
                :class="checkup[item.key] || ''"
                @click="cycleCheckup(item.key)"
              >
                <span>{{ checkup[item.key] === 'ok' ? '✅' : checkup[item.key] === 'nok' ? '❌' : '⬜' }}</span>
                <span>{{ item.label }}</span>
              </button>
            </div>

            <div class="ocr-field mt-3">
              <label>Notes checkup</label>
              <textarea
                v-model="checkupNotes"
                class="companion-input"
                rows="4"
                placeholder="Usure, point à surveiller, remarque utile…"
              ></textarea>
            </div>

            <button
              class="companion-validate-btn mt-3"
              
              :disabled="checkupSaving"
              @click="saveCheckup"
            >
              {{ checkupSaving ? 'Enregistrement…' : '✓ Enregistrer le checkup' }}
            </button>
          </div>

          <!-- SIGNATURE SECTION -->
          <div v-if="activeSection === 'signature'" class="companion-section">
            <div class="companion-section-header">
              <h2>✍️ Signature du client</h2>
              <button class="companion-close" @click="activeSection = null">✕</button>
            </div>

            <div v-if="rdv.has_signature && !resignMode" class="text-center p-5">
              <div class="text-5xl mb-3">✅</div>
              <p style="color:#6EE7B7;font-size:15px;font-weight:600;">Signature déjà finalisée</p>
              <p class="text-md-muted">Le document est maintenant verrouillé côté atelier.</p>
            </div>

            <div v-else class="flex-col-gap">
              <p class="text-lg-muted text-center">
                Le client accepte les travaux décrits et confirme l'état du véhicule à la réception.
              </p>

              <div class="companion-clauses">
                <div class="companion-clauses__header">
                  <strong>Conditions et RGPD</strong>
                </div>

                <p v-if="clausesLoading" class="companion-clauses__meta">Chargement des clauses en cours…</p>
                <p v-else-if="clausesError" class="companion-clauses__meta companion-clauses__meta--error">{{ clausesError }}</p>

                <template v-else>
                  <label
                    v-for="clause in signatureClauses"
                    :key="clause.code"
                    class="companion-clause-item"
                  >
                    <input
                      :id="`clause-${clause.code}`"
                      v-model="acceptedClauses[clause.code]"
                      type="checkbox"
                    />
                    <div class="companion-clause-item__content">
                      <span class="companion-clause-item__title">{{ clause.libelle || humanizeClauseCode(clause.code) }}</span>
                      <p class="companion-clause-item__text">{{ clause.texte }}</p>
                    </div>
                  </label>

                  <p v-if="missingAcceptedClauseLabels.length" class="companion-clauses__meta companion-clauses__meta--warn">
                    Acceptation requise avant signature : {{ missingAcceptedClauseLabels.join(', ') }}
                  </p>
                </template>
              </div>

              <div class="sig-canvas-wrapper">
                <canvas
                  ref="sigCanvas"
                  width="600"
                  height="250"
                  @pointerdown="startDraw"
                  @pointermove="draw"
                  @pointerup="endDraw"
                  @pointerleave="endDraw"
                  style="width:100%;height:200px;border-radius:12px;background:rgba(255,255,255,0.95);touch-action:none;cursor:crosshair;"
                ></canvas>
              </div>

              <div class="flex-wrap-gap">
                <button class="companion-capture-btn flex-1" @click="clearSignature">
                  <span>↺ Effacer</span>
                </button>
                <button
                  class="companion-validate-btn"
                  style="flex:2;"
                  :disabled="sigSaving || !hasDrawn || clausesLoading || missingAcceptedClauseLabels.length > 0"
                  @click="submitSignature"
                >
                  {{ sigSaving ? 'Envoi…' : '✓ Valider la signature' }}
                </button>
              </div>
            </div>
          </div>

          <!-- All-done summary -->
          <div v-if="rdv.has_signature && rdv.photos_count > 0 && checkupDone > 0 && !activeSection" class="companion-card" style="border-color:rgba(16,185,129,0.3);text-align:center;margin-top:8px;">
            <div class="text-4xl mb-2">✅</div>
            <p style="color:#6EE7B7;font-weight:700;font-size:14px;">Réception prête à valider</p>
            <p class="text-md-muted">Le réceptionnaire peut maintenant valider la réception depuis le planning PC.</p>
          </div>
        </div>
      </div>
    </NuxtLayout>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: false })

const route = useRoute()
const config = useRuntimeConfig()
const apiBase = config.public.apiBase as string

const token = computed(() => String(route.params.token || ''))
const loading = ref(true)
const error = ref('')
const rdv = ref<any>(null)
const activeSection = ref<string | null>(null)
const uploading = ref(false)
const ocrProcessing = ref(false)
const ocrSaving = ref(false)
const ocrResult = ref<any>(null)
const carteGriseScanned = ref(false)
const statusMessage = ref('')
const statusError = ref('')
const sigSaving = ref(false)
const hasDrawn = ref(false)
const resignMode = ref(false)
const sigCanvas = ref<HTMLCanvasElement | null>(null)
const checkupSaving = ref(false)
const checkupNotes = ref('')
const clausesLoading = ref(false)
const clausesError = ref('')

type SignatureClause = {
  code: string
  libelle: string
  texte: string
  version: number | null
}

const signatureClauses = ref<SignatureClause[]>([])
const acceptedClauses = reactive<Record<string, boolean>>({})

type OcrFieldKey = 'plaque' | 'marque' | 'modele' | 'type_variante' | 'denomination_commerciale' | 'vin' | 'annee' | 'cylindree' | 'type_moto'

const ocrFields: Array<{ key: OcrFieldKey; label: string }> = [
  { key: 'plaque', label: 'Plaque (A)' },
  { key: 'marque', label: 'Marque (D.1)' },
  { key: 'modele', label: 'Modèle (D.2)' },
  { key: 'type_variante', label: 'Type / Variante / Version (D.2.1)' },
  { key: 'denomination_commerciale', label: 'Dénomination commerciale (D.3)' },
  { key: 'vin', label: 'VIN (E)' },
  { key: 'annee', label: 'Mise en circulation (B)' },
  { key: 'cylindree', label: 'Cylindrée (P.1)' },
  { key: 'type_moto', label: 'Type (J.1)' },
]

const knownBrands = ['YAMAHA', 'HONDA', 'KAWASAKI', 'SUZUKI', 'BMW', 'DUCATI', 'KTM', 'HARLEY DAVIDSON', 'HARLEY', 'TRIUMPH', 'APRILIA', 'MV AGUSTA', 'INDIAN', 'HUSQVARNA', 'BENELLI', 'ROYAL ENFIELD', 'MOTO GUZZI', 'PIAGGIO', 'VESPA', 'KYMCO', 'SYM', 'PEUGEOT']

const checkupItems = [
  { key: 'pneus', label: 'Pneus' },
  { key: 'freins', label: 'Freins' },
  { key: 'huile', label: 'Huile' },
  { key: 'eclairage', label: 'Éclairage' },
  { key: 'batterie', label: 'Batterie' },
  { key: 'chaine', label: 'Chaîne' },
  { key: 'liquides', label: 'Liquides' },
  { key: 'suspension', label: 'Suspension' },
  { key: 'cablerie', label: 'Câblerie' },
  { key: 'general', label: 'État général' },
]
const checkup = reactive<Record<string, string>>({})
const checkupDone = computed(() => Object.values(checkup).filter(v => v === 'ok' || v === 'nok').length)

let drawing = false
let lastX = 0
let lastY = 0

function setFeedback(message = '', isError = false) {
  statusError.value = isError ? message : ''
  statusMessage.value = isError ? '' : message
}

function humanizeClauseCode(code: string) {
  const map: Record<string, string> = {
    cgv: 'Conditions generales',
    garantie: 'Garantie',
    rgpd: 'Protection des donnees (RGPD)',
  }
  return map[code] || code.toUpperCase()
}

function resetAcceptedClauses() {
  Object.keys(acceptedClauses).forEach((key) => {
    delete acceptedClauses[key]
  })
}

async function loadSignatureClauses(force = false) {
  if (!token.value) return
  if (!force && signatureClauses.value.length > 0) return

  clausesLoading.value = true
  clausesError.value = ''
  try {
    const res = await globalThis.fetch(`${apiBase}/companion/${token.value}/clauses`)
    await ensureOk(res, 'Impossible de charger les clauses de signature')
    const payload = await res.json()
    signatureClauses.value = Array.isArray(payload) ? payload : []

    resetAcceptedClauses()
    signatureClauses.value.forEach((clause) => {
      acceptedClauses[clause.code] = false
    })
  } catch (e: unknown) {
    signatureClauses.value = []
    clausesError.value = (e instanceof Error ? e.message : 'Erreur inconnue') || 'Impossible de charger les clauses de signature'
  } finally {
    clausesLoading.value = false
  }
}

const missingAcceptedClauseLabels = computed(() => signatureClauses.value
  .filter((clause) => !acceptedClauses[clause.code])
  .map((clause) => clause.libelle || humanizeClauseCode(clause.code)))

function loadSavedCheckup() {
  Object.keys(checkup).forEach((key) => { delete checkup[key] })
  const savedCheckup = rdv.value?.checkup ?? {}
  Object.entries(savedCheckup).forEach(([key, value]) => {
    if (value) checkup[key] = String(value)
  })
  checkupNotes.value = rdv.value?.checkup_notes ?? ''
  carteGriseScanned.value = Boolean(
    rdv.value?.vehicule?.vin
    || rdv.value?.vehicule?.annee
    || rdv.value?.vehicule?.cylindree
    || rdv.value?.vehicule?.type_moto,
  )
}

async function readApiError(res: Response, fallback: string) {
  try {
    const payload = await res.json()
    return payload?.error || payload?.message || fallback
  } catch {
    return fallback
  }
}

async function ensureOk(res: Response, fallback: string) {
  if (!res.ok) {
    throw new Error(await readApiError(res, fallback))
  }
}

async function normalizeImage(file: File): Promise<File> {
  if (!file.type.startsWith('image/')) return file
  if (/image\/(jpeg|png|webp)/i.test(file.type) && file.size <= 6 * 1024 * 1024) return file

  try {
    const objectUrl = URL.createObjectURL(file)
    const img = await new Promise<HTMLImageElement>((resolve, reject) => {
      const image = new Image()
      image.onload = () => resolve(image)
      image.onerror = reject
      image.src = objectUrl
    })

    const maxSize = 2200
    const ratio = Math.min(1, maxSize / Math.max(img.width, img.height))
    const canvas = document.createElement('canvas')
    canvas.width = Math.max(1, Math.round(img.width * ratio))
    canvas.height = Math.max(1, Math.round(img.height * ratio))

    const ctx = canvas.getContext('2d')
    if (!ctx) throw new Error('Canvas indisponible')
    ctx.drawImage(img, 0, 0, canvas.width, canvas.height)

    const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.86))
    URL.revokeObjectURL(objectUrl)

    if (!blob) return file

    const safeName = file.name.replace(/\.[^.]+$/, '') || 'photo'
    return new File([blob], `${safeName}.jpg`, { type: 'image/jpeg' })
  } catch {
    return file
  }
}

function stripAccents(value: string) {
  return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '')
}

function normalizeLoose(value: unknown): string {
  return stripAccents(String(value || ''))
    .toUpperCase()
    .replace(/[^A-Z0-9]+/g, ' ')
    .trim()
}

function toDigitLike(value: string): string {
  return value
    .replace(/[OQD]/g, '0')
    .replace(/[ILT]/g, '1')
    .replace(/Z/g, '2')
    .replace(/S/g, '5')
    .replace(/G/g, '6')
    .replace(/B/g, '8')
}

function toLetterLike(value: string): string {
  return value
    .replace(/0/g, 'O')
    .replace(/1/g, 'I')
    .replace(/2/g, 'Z')
    .replace(/5/g, 'S')
    .replace(/6/g, 'G')
    .replace(/8/g, 'B')
}

function normalizePlate(value: unknown): string {
  const cleaned = normalizeLoose(value).replace(/\s+/g, '')

  if (/^[A-Z]{2}\d{3}[A-Z]{2}$/.test(cleaned)) {
    const left = toLetterLike(cleaned.slice(0, 2))
    const middle = toDigitLike(cleaned.slice(2, 5))
    const right = toLetterLike(cleaned.slice(5, 7))
    return `${left}-${middle}-${right}`
  }

  if (/^\d[A-Z]{3}\d{3}$/.test(cleaned)) {
    return `${toDigitLike(cleaned.slice(0, 1))}-${toLetterLike(cleaned.slice(1, 4))}-${toDigitLike(cleaned.slice(4, 7))}`
  }

  if (/^[A-Z]{3}\d{3}$/.test(cleaned)) {
    return `${toLetterLike(cleaned.slice(0, 3))}-${toDigitLike(cleaned.slice(3, 6))}`
  }

  if (/^\d{3}[A-Z]{3}$/.test(cleaned)) {
    return `${toDigitLike(cleaned.slice(0, 3))}-${toLetterLike(cleaned.slice(3, 6))}`
  }

  return cleaned
}

function normalizeCylindree(value: unknown): string {
  return toDigitLike(normalizeLoose(value)).replace(/[^0-9]/g, '').slice(0, 4)
}

function normalizeVin(value: unknown): string {
  return toDigitLike(normalizeLoose(value)).replace(/[^A-Z0-9]/g, '').slice(0, 17)
}

function normalizeYear(value: unknown): string {
  const raw = String(value || '')
  const direct = raw.match(/(19\d{2}|20\d{2})/)
  if (direct) return direct[1]
  const compact = toDigitLike(normalizeLoose(raw)).replace(/[^0-9]/g, '')
  return compact.length >= 4 ? compact.slice(-4) : compact
}

function levenshtein(a: string, b: string): number {
  if (a === b) return 0
  if (!a.length) return b.length
  if (!b.length) return a.length

  const matrix = Array.from({ length: b.length + 1 }, (_, i) => [i])
  for (let j = 0; j <= a.length; j += 1) matrix[0][j] = j

  for (let i = 1; i <= b.length; i += 1) {
    for (let j = 1; j <= a.length; j += 1) {
      const cost = a[j - 1] === b[i - 1] ? 0 : 1
      matrix[i][j] = Math.min(
        matrix[i - 1][j] + 1,
        matrix[i][j - 1] + 1,
        matrix[i - 1][j - 1] + cost,
      )
    }
  }

  return matrix[b.length][a.length]
}

function similarityScore(a: string, b: string): number {
  if (!a || !b) return 0
  const distance = levenshtein(a, b)
  return 1 - distance / Math.max(a.length, b.length, 1)
}

function getFieldNormalizer(key: OcrFieldKey) {
  if (key === 'plaque') return normalizePlate
  if (key === 'vin') return normalizeVin
  if (key === 'cylindree') return normalizeCylindree
  if (key === 'annee') return normalizeYear
  return normalizeLoose
}

function selectBestCandidate(rawValue: string, candidates: string[]): string {
  const cleanedRaw = normalizeLoose(rawValue)
  if (!cleanedRaw) return rawValue

  let best = rawValue
  let bestScore = 0

  for (const candidate of candidates) {
    const score = similarityScore(cleanedRaw, normalizeLoose(candidate))
    if (score > bestScore) {
      best = candidate
      bestScore = score
    }
  }

  return bestScore >= 0.58 ? best : rawValue
}

function compareOcrField(key: OcrFieldKey, currentValue: unknown, baseValue: unknown) {
  const current = String(currentValue || '')
  const base = String(baseValue || '')
  if (!base) {
    return { tone: 'neutral', message: 'Aucune valeur atelier existante', canUseBase: false }
  }

  const normalizer = getFieldNormalizer(key)
  const normalizedCurrent = normalizer(current)
  const normalizedBase = normalizer(base)

  if (!normalizedCurrent) {
    return { tone: 'warn', message: `Base atelier : ${base}`, canUseBase: true }
  }

  if (normalizedCurrent === normalizedBase) {
    return { tone: 'ok', message: `Conforme à la base atelier : ${base}`, canUseBase: false }
  }

  const score = similarityScore(normalizedCurrent, normalizedBase)
  if (score >= 0.72) {
    return { tone: 'warn', message: `Lecture proche de la base atelier : ${base}`, canUseBase: true }
  }

  return { tone: 'diff', message: `Écart détecté avec la base atelier : ${base}`, canUseBase: true }
}

const ocrComparisons = computed<Partial<Record<OcrFieldKey, { tone: string; message: string; canUseBase: boolean }>>>(() => {
  if (!ocrResult.value) return {}

  return ocrFields.reduce((acc, field) => {
    acc[field.key] = compareOcrField(field.key, ocrResult.value?.[field.key], rdv.value?.vehicule?.[field.key])
    return acc
  }, {} as Partial<Record<OcrFieldKey, { tone: string; message: string; canUseBase: boolean }>>)
})

function useBaseValue(key: OcrFieldKey) {
  if (!ocrResult.value) return
  ocrResult.value[key] = String(rdv.value?.vehicule?.[key] || '')
}

function summarizeOcrComparison(result: Record<string, string>) {
  const diffCount = ocrFields.filter((field) => compareOcrField(field.key, result[field.key], rdv.value?.vehicule?.[field.key]).tone === 'diff').length

  if (diffCount > 0) {
    setFeedback(`OCR terminé : ${diffCount} champ(s) diffèrent de la base atelier. Vérifiez avant validation.`, true)
    return
  }

  setFeedback('OCR cohérent avec la base atelier. Vérifiez puis appliquez.')
}

function cycleCheckup(key: string) {
  if (!checkup[key]) checkup[key] = 'ok'
  else if (checkup[key] === 'ok') checkup[key] = 'nok'
  else checkup[key] = ''
}

async function fetchRdv() {
  if (!token.value) {
    error.value = 'Aucun token fourni dans l\'URL'
    loading.value = false
    return
  }
  try {
    const res = await globalThis.fetch(`${apiBase}/companion/${token.value}`)
    if (!res.ok) throw new Error(await readApiError(res, 'Lien invalide ou expiré'))
    rdv.value = await res.json()
    loadSavedCheckup()
  } catch (e: unknown) {
    error.value = (e instanceof Error ? e.message : 'Erreur inconnue') || 'Erreur de chargement'
  } finally {
    loading.value = false
  }
}

function photoUrl(url: string) {
  if (!url) return ''
  if (url.startsWith('http')) return url
  return `${apiBase}${url.startsWith('/api') ? url.replace('/api', '') : url}`
}

// --- Photos ---
async function onPhotosSelected(e: Event) {
  const input = e.target as HTMLInputElement
  const files = input.files
  if (!files?.length) return

  uploading.value = true
  setFeedback('')
  try {
    for (const file of Array.from(files)) {
      const preparedFile = await normalizeImage(file)
      const fd = new FormData()
      fd.append('photo', preparedFile)
      fd.append('description', 'Photo réception')
      const res = await globalThis.fetch(`${apiBase}/companion/${token.value}/photo`, {
        method: 'POST',
        body: fd,
      })
      await ensureOk(res, 'Impossible d’envoyer la photo')
    }
    await fetchRdv()
    setFeedback('Photo(s) enregistrée(s).')
  } catch (e: unknown) {
    setFeedback((e instanceof Error ? e.message : 'Erreur inconnue') || 'Impossible d’envoyer la photo', true)
  } finally {
    uploading.value = false
    input.value = ''
  }
}

// --- Carte Grise OCR ---
async function onCarteGriseSelected(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  ocrProcessing.value = true
  ocrResult.value = null

  try {
    setFeedback('')
    // OCR local uniquement: on retranscrit les champs sans persister l'image de carte grise.
    const { createWorker } = await import('tesseract.js')
    const worker = await createWorker('fra+nld+eng')
    const { data: { text } } = await worker.recognize(file)
    await worker.terminate()

    ocrResult.value = parseCarteGriseText(text)
    carteGriseScanned.value = true
    await fetchRdv()
    summarizeOcrComparison(ocrResult.value)
  } catch (err) {
    ocrResult.value = {
      plaque: rdv.value?.vehicule?.plaque || '',
      marque: rdv.value?.vehicule?.marque || '',
      modele: rdv.value?.vehicule?.modele || '',
      type_variante: rdv.value?.vehicule?.type_variante || rdv.value?.vehicule?.typeVariante || '',
      denomination_commerciale: rdv.value?.vehicule?.denomination_commerciale || rdv.value?.vehicule?.denominationCommerciale || '',
      vin: rdv.value?.vehicule?.vin || '',
      annee: rdv.value?.vehicule?.annee ? String(rdv.value.vehicule.annee) : '',
      cylindree: rdv.value?.vehicule?.cylindree || '',
      type_moto: rdv.value?.vehicule?.type_moto || '',
    }
    carteGriseScanned.value = true
    setFeedback('OCR indisponible sur cette image. Corrigez les champs manuellement puis appliquez au véhicule.', true)
  } finally {
    ocrProcessing.value = false
    input.value = ''
  }
}

function parseCarteGriseText(text: string): Record<string, string> {
  const lines = text.split('\n').map(l => l.trim()).filter(Boolean)
  const joined = lines.join(' ')
  const compact = joined
    .replace(/[|]/g, 'I')
    .replace(/\u2013|\u2014/g, '-')
    .replace(/\s+/g, ' ')
    .trim()
  const base = rdv.value?.vehicule || {}

  const extractFirst = (...patterns: RegExp[]) => {
    const haystacks = [...lines, joined]
    for (const source of haystacks) {
      for (const pattern of patterns) {
        const match = source.match(pattern)
        if (match?.[1]) return match[1].trim()
      }
    }
    return ''
  }

  const plaqueCandidate = extractFirst(
    /(?:\bA\b\s*[:\-]?)\s*([A-Z0-9\s-]{6,12})/i,
    /(?:plaque|immatriculation|kenteken)[\s:.-]*([A-Z0-9-]{6,12})/i,
    /\b([A-Z]{2}[\s-]?\d{3}[\s-]?[A-Z]{2})\b/i,
    /\b(\d[\s-]?[A-Z]{3}[\s-]?\d{3})\b/i,
    /\b([A-Z]{3}[\s-]?\d{3})\b/i,
    /\b(\d{3}[\s-]?[A-Z]{3})\b/i,
  )

  let marqueCandidate = extractFirst(
    /(?:\bD[.\s]?1\b\s*[:\-]?)\s*([A-Z][A-Z0-9\s-]{1,24})/i,
    /(?:marque|merk)[\s:.-]*([A-Z][A-Z0-9\s-]{1,24})/i,
  )
  if (!marqueCandidate) {
    const bestBrand = selectBestCandidate(joined, [...knownBrands, String(base?.marque || '')].filter(Boolean))
    if (bestBrand && normalizeLoose(bestBrand) !== normalizeLoose(joined)) {
      marqueCandidate = bestBrand
    }
  }

  const modeleCandidate = extractFirst(
    /(?:\bD[.\s]?2\b\s*[:\-]?)\s*([A-Z0-9\s/-]{2,30})/i,
    /(?:modele|model)[\s:.-]*([A-Z0-9\s/-]{2,30})/i,
  )
  let typeVarianteCandidate = extractFirst(
    /(?:\bD[.\s]?2[.\s]?1\b\s*[:\-]?)\s*([A-Z0-9\s/-]{2,35})/i,
  )
  let denominationCandidate = extractFirst(
    /(?:\bD[.\s]?3\b\s*[:\-]?)\s*([A-Z0-9\s/-]{2,35})/i,
    /(?:denomination commerciale|commerciale?|handelsbenaming)[\s:.-]*([A-Z0-9\s/-]{2,35})/i,
  )
  if (!typeVarianteCandidate) {
    const d21Line = compact.match(/(?:^|\s)D\s*[.,]?\s*2\s*[.,]?\s*1\s*[:\-]?\s*([A-Z0-9\s/-]{2,35})(?:\s|$)/i)
    if (d21Line?.[1]) {
      typeVarianteCandidate = d21Line[1]
    }
  }
  if (!denominationCandidate) {
    const d3Line = compact.match(/(?:^|\s)D\s*[.,]?\s*3\s*[:\-]?\s*([A-Z0-9\s/-]{2,35})(?:\s|$)/i)
    if (d3Line?.[1]) {
      denominationCandidate = d3Line[1]
    }
  }
  let vinCandidate = extractFirst(
    /(?:\bE\b\s*[:\-]?)\s*([A-HJ-NPR-Z0-9]{11,17})/i,
    /(?:chassis(?:nummer)?|num[ée]ro de ch[âa]ssis|n[°o]\s*de\s*s[ée]rie)[\s:.-]*([A-HJ-NPR-Z0-9]{11,17})/i,
    /\b([A-HJ-NPR-Z0-9]{17})\b/i,
  )
  if (!vinCandidate) {
    const vinMatch = compact.match(/(?:^|\s)E\s*[:\-]?\s*([A-HJ-NPR-Z0-9\s]{14,22})(?:\s|$)/i)
    if (vinMatch?.[1]) {
      vinCandidate = vinMatch[1].replace(/\s+/g, '')
    }
  }

  let yearCandidate = extractFirst(
    /(?:\bB\b\s*[:\-]?)\s*(\d{2}[/.\-]\d{2}[/.\-]\d{4}|\d{4})/i,
    /(?:\bI\b\s*[:\-]?)\s*(\d{2}[/.\-]\d{2}[/.\-]\d{4}|\d{4})/i,
    /(?:premi[èe]re immatriculation|eerste inschrijving)[\s:.-]*(\d{2}[/.\-]\d{2}[/.\-]\d{4}|\d{4})/i,
    /\b(19\d{2}|20\d{2})\b/,
  )
  if (!yearCandidate) {
    const bLine = compact.match(/(?:^|\s)B\s*[:\-]?\s*(\d{2}[/.\-]\d{2}[/.\-]\d{4})/i)
    if (bLine?.[1]) {
      yearCandidate = bLine[1]
    }
  }

  let cylCandidate = extractFirst(
    /(?:\bP[.\s]?1\b\s*[:\-]?)\s*([0-9OQDISBZ]{2,4})/i,
    /([0-9OQDISBZ]{2,4})\s*(?:cm[³3]|cc|CM3)/i,
  )
  if (!cylCandidate) {
    const pLine = compact.match(/(?:^|\s)P\s*[.,]?\s*1\s*[:\-]?\s*([0-9OQDISBZ]{2,5})/i)
    if (pLine?.[1]) {
      cylCandidate = pLine[1]
    }
  }

  let typeCandidate = extractFirst(
    /(?:\bJ[.\s]?1\b\s*[:\-]?)\s*([A-Z0-9]{2,8})/i,
    /(?:genre|carrosserie|voertuigtype)[\s:.-]*([A-Z0-9]{2,8})/i,
    /\b(MTL|MTT1|MTT2|CL|QM|TM|L3E|L1E)\b/i,
  )
  if (!typeCandidate) {
    const jLine = compact.match(/(?:^|\s)J\s*[.,]?\s*1\s*[:\-]?\s*([A-Z0-9]{2,8})/i)
    if (jLine?.[1]) {
      typeCandidate = jLine[1]
    }
  }

  const initial: Record<OcrFieldKey, string> = {
    plaque: normalizePlate(plaqueCandidate || base?.plaque || ''),
    marque: marqueCandidate ? selectBestCandidate(marqueCandidate, [...knownBrands, String(base?.marque || '')].filter(Boolean)) : String(base?.marque || ''),
    modele: String(modeleCandidate || base?.modele || '').trim(),
    type_variante: String(typeVarianteCandidate || base?.type_variante || base?.typeVariante || '').trim(),
    denomination_commerciale: String(denominationCandidate || base?.denomination_commerciale || base?.denominationCommerciale || '').trim(),
    vin: normalizeVin(vinCandidate || base?.vin || ''),
    annee: normalizeYear(yearCandidate || base?.annee || ''),
    cylindree: normalizeCylindree(cylCandidate || base?.cylindree || ''),
    type_moto: normalizeLoose(typeCandidate || base?.type_moto || ''),
  }

  const resolved = { ...initial }

  ocrFields.forEach((field) => {
    const baseValue = String(base?.[field.key] || '')
    const comparison = compareOcrField(field.key, resolved[field.key], baseValue)
    if (comparison.tone === 'warn' && baseValue) {
      resolved[field.key] = baseValue
    }
  })

  if (resolved.marque) {
    resolved.marque = resolved.marque
      .toLowerCase()
      .replace(/(^|\s)([a-z])/g, (_, prefix, char) => `${prefix}${char.toUpperCase()}`)
  }

  return resolved
}

async function applyOcrData() {
  if (!ocrResult.value) return
  ocrSaving.value = true
  try {
    setFeedback('')
    const res = await globalThis.fetch(`${apiBase}/companion/${token.value}/vehicule`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(ocrResult.value),
    })
    await ensureOk(res, 'Impossible de mettre à jour le véhicule')
    await fetchRdv()
    activeSection.value = null
    setFeedback('Les informations véhicule ont été mises à jour.')
  } catch (e: unknown) {
    setFeedback((e instanceof Error ? e.message : 'Erreur inconnue') || 'Impossible de mettre à jour le véhicule', true)
  } finally {
    ocrSaving.value = false
  }
}

async function saveCheckup() {
  checkupSaving.value = true
  setFeedback('')
  try {
    const res = await globalThis.fetch(`${apiBase}/companion/${token.value}/reception-data`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        checkup: { ...checkup },
        checkup_notes: checkupNotes.value,
      }),
    })

    await ensureOk(res, 'Erreur enregistrement checkup')

    await fetchRdv()
    activeSection.value = null
    setFeedback('Checkup enregistré.')
  } catch (e: unknown) {
    setFeedback((e instanceof Error ? e.message : 'Erreur inconnue') || 'Erreur enregistrement checkup', true)
  } finally {
    checkupSaving.value = false
  }
}

// --- Signature ---
function getCanvasPos(e: PointerEvent) {
  const canvas = sigCanvas.value!
  const rect = canvas.getBoundingClientRect()
  return {
    x: (e.clientX - rect.left) * (canvas.width / rect.width),
    y: (e.clientY - rect.top) * (canvas.height / rect.height),
  }
}

function startDraw(e: PointerEvent) {
  drawing = true
  hasDrawn.value = true
  const pos = getCanvasPos(e)
  lastX = pos.x
  lastY = pos.y
}

function draw(e: PointerEvent) {
  if (!drawing) return
  const canvas = sigCanvas.value!
  const ctx = canvas.getContext('2d')!
  const pos = getCanvasPos(e)
  ctx.beginPath()
  ctx.moveTo(lastX, lastY)
  ctx.lineTo(pos.x, pos.y)
  ctx.strokeStyle = '#1a1a2e'
  ctx.lineWidth = 3
  ctx.lineCap = 'round'
  ctx.lineJoin = 'round'
  ctx.stroke()
  lastX = pos.x
  lastY = pos.y
}

function endDraw() {
  drawing = false
}

function clearSignature() {
  const canvas = sigCanvas.value
  if (!canvas) return
  const ctx = canvas.getContext('2d')!
  ctx.clearRect(0, 0, canvas.width, canvas.height)
  hasDrawn.value = false
}

async function submitSignature() {
  if (!sigCanvas.value || !hasDrawn.value) return

  if (missingAcceptedClauseLabels.value.length > 0) {
    setFeedback('Vous devez accepter toutes les clauses avant de signer.', true)
    return
  }

  sigSaving.value = true
  setFeedback('')
  try {
    const dataUrl = sigCanvas.value.toDataURL('image/png')
    const res = await globalThis.fetch(`${apiBase}/companion/${token.value}/signature`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        signature: dataUrl,
        clausesAcceptees: signatureClauses.value
          .filter((clause) => acceptedClauses[clause.code])
          .map((clause) => clause.code),
      }),
    })
    await ensureOk(res, 'Erreur signature')
    rdv.value.has_signature = true
    resignMode.value = false
    activeSection.value = null
    clearSignature()
    await fetchRdv()
    setFeedback('Signature client finalisée.')
  } catch (e: unknown) {
    setFeedback((e instanceof Error ? e.message : 'Erreur inconnue') || 'Erreur signature', true)
  } finally {
    sigSaving.value = false
  }
}

watch(activeSection, async (value) => {
  if (value === 'signature') {
    await loadSignatureClauses()
  }
})

// --- [C7] Restitution canvas + signature ---
const restitutionData = ref<any>(null)
const restitutionDone = ref(false)
const restitutionSaving = ref(false)
const restitutionHasDrawn = ref(false)
const restitutionError = ref('')
const sigCanvasRestitution = ref<HTMLCanvasElement | null>(null)
let restitutionDrawing = false
let restitutionLastX = 0
let restitutionLastY = 0

async function fetchRapportRestitution() {
  try {
    const res = await globalThis.fetch(`${apiBase}/companion/${token.value}/rapport-restitution`)
    if (!res.ok) throw new Error(await readApiError(res, 'Erreur chargement rapport'))
    restitutionData.value = await res.json()
  } catch (e: unknown) {
    restitutionError.value = (e instanceof Error ? e.message : 'Erreur inconnue') || 'Erreur chargement rapport de restitution'
  }
}

function startDrawRestitution(e: PointerEvent) {
  const canvas = sigCanvasRestitution.value
  if (!canvas) return
  canvas.setPointerCapture(e.pointerId)
  restitutionDrawing = true
  restitutionHasDrawn.value = true
  const rect = canvas.getBoundingClientRect()
  restitutionLastX = (e.clientX - rect.left) * (canvas.width / rect.width)
  restitutionLastY = (e.clientY - rect.top) * (canvas.height / rect.height)
}

function drawRestitution(e: PointerEvent) {
  if (!restitutionDrawing) return
  const canvas = sigCanvasRestitution.value
  if (!canvas) return
  const ctx = canvas.getContext('2d')!
  const rect = canvas.getBoundingClientRect()
  const x = (e.clientX - rect.left) * (canvas.width / rect.width)
  const y = (e.clientY - rect.top) * (canvas.height / rect.height)
  ctx.beginPath()
  ctx.moveTo(restitutionLastX, restitutionLastY)
  ctx.lineTo(x, y)
  ctx.strokeStyle = '#1a1a2e'
  ctx.lineWidth = 3
  ctx.lineCap = 'round'
  ctx.lineJoin = 'round'
  ctx.stroke()
  restitutionLastX = x
  restitutionLastY = y
}

function endDrawRestitution() {
  restitutionDrawing = false
}

function clearRestitutionSignature() {
  const canvas = sigCanvasRestitution.value
  if (!canvas) return
  canvas.getContext('2d')!.clearRect(0, 0, canvas.width, canvas.height)
  restitutionHasDrawn.value = false
}

async function submitSignatureRestitution() {
  if (!sigCanvasRestitution.value || !restitutionHasDrawn.value) return
  restitutionSaving.value = true
  restitutionError.value = ''
  try {
    const dataUrl = sigCanvasRestitution.value.toDataURL('image/png')
    const res = await globalThis.fetch(`${apiBase}/companion/${token.value}/signature-restitution`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ signature: dataUrl }),
    })
    await ensureOk(res, 'Erreur signature restitution')
    restitutionDone.value = true
    await fetchRdv()
  } catch (e: unknown) {
    restitutionError.value = (e instanceof Error ? e.message : 'Erreur inconnue') || 'Erreur lors de la signature de restitution'
  } finally {
    restitutionSaving.value = false
  }
}

// --- Polling status updates ---
let pollInterval: ReturnType<typeof setInterval>

onMounted(async () => {
  await fetchRdv()
  if (rdv.value?.statut === 'termine') {
    await fetchRapportRestitution()
  }
  pollInterval = setInterval(async () => {
    if (!token.value || activeSection.value) return
    try {
      const res = await globalThis.fetch(`${apiBase}/companion/${token.value}/status`)
      if (res.ok) {
        const status = await res.json()
        if (rdv.value) {
          rdv.value.photos_count = status.photos_count
          rdv.value.has_signature = status.has_signature
          rdv.value.checkup_done = status.checkup_done || 0
        }
      }
    } catch {
      // Polling silencieux — erreur réseau non bloquante
    }
  }, 5000)
})

onUnmounted(() => {
  clearInterval(pollInterval)
})
</script>

<style scoped>
.companion-container {
  max-width: 480px;
  margin: 0 auto;
  padding: 16px;
  min-height: 100vh;
}
.companion-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
  padding: 16px;
  border-radius: 14px;
  background: linear-gradient(135deg, rgba(255,210,0,0.08), rgba(245,158,11,0.04));
  border: 1px solid rgba(255,210,0,0.15);
}
.companion-card {
  padding: 14px;
  border-radius: 12px;
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.08);
  margin-bottom: 12px;
}
.companion-status-row {
  display: flex;
  gap: 8px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}
.companion-pill {
  flex: 1;
  min-width: 80px;
  padding: 8px 10px;
  border-radius: 10px;
  font-size: 11px;
  font-weight: 600;
  text-align: center;
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.08);
  color: #6B7280;
}
.companion-pill.done {
  background: rgba(16,185,129,0.08);
  border-color: rgba(16,185,129,0.25);
  color: #6EE7B7;
}
.companion-actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 16px;
}
.companion-action-btn {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 20px 18px;
  border-radius: 14px;
  border: 1px solid rgba(255,255,255,0.08);
  background: rgba(255,255,255,0.03);
  cursor: pointer;
  text-align: left;
  transition: all 0.15s;
  color: #E8E9ED;
}
.companion-action-btn:active {
  transform: scale(0.98);
  background: rgba(255,255,255,0.06);
}
.companion-section {
  margin-top: 8px;
  padding: 16px;
  border-radius: 14px;
  background: rgba(255,255,255,0.02);
  border: 1px solid rgba(255,255,255,0.08);
}
.ocr-compare {
  margin-top: 6px;
  padding: 8px 10px;
  border-radius: 10px;
  font-size: 11px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.ocr-compare--ok {
  background: rgba(16,185,129,0.08);
  color: #6EE7B7;
  border: 1px solid rgba(16,185,129,0.2);
}
.ocr-compare--warn {
  background: rgba(245,158,11,0.08);
  color: #FCD34D;
  border: 1px solid rgba(245,158,11,0.2);
}
.ocr-compare--diff {
  background: rgba(239,68,68,0.08);
  color: #FCA5A5;
  border: 1px solid rgba(239,68,68,0.2);
}
.ocr-compare--neutral {
  background: rgba(255,255,255,0.04);
  color: #9CA3AF;
  border: 1px solid rgba(255,255,255,0.08);
}
.ocr-compare-btn {
  border: 0;
  border-radius: 8px;
  padding: 6px 8px;
  font-size: 11px;
  font-weight: 700;
  background: rgba(255,255,255,0.08);
  color: #E8E9ED;
}
.companion-section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}
.companion-section-header h2 {
  font-size: 16px;
  font-weight: 700;
  color: #E8E9ED;
  margin: 0;
}
.companion-close {
  background: none;
  border: none;
  color: #9CA3AF;
  font-size: 20px;
  cursor: pointer;
  padding: 4px 8px;
}
.companion-capture-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 16px;
  border-radius: 12px;
  border: 2px dashed rgba(255,210,0,0.25);
  background: rgba(255,210,0,0.04);
  color: #FFD200;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}
.companion-capture-btn:active {
  background: rgba(255,210,0,0.1);
}
.companion-photo-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}
.companion-photo-thumb {
  aspect-ratio: 1;
  border-radius: 10px;
  overflow: hidden;
  background: rgba(255,255,255,0.05);
}
.companion-photo-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.companion-input {
  width: 100%;
  padding: 8px 10px;
  border-radius: 8px;
  border: 1px solid rgba(255,255,255,0.12);
  background: rgba(255,255,255,0.04);
  color: #E8E9ED;
  font-size: 13px;
  outline: none;
}
.companion-input:focus {
  border-color: rgba(255,210,0,0.4);
}
.ocr-field {
  display: flex;
  flex-direction: column;
  gap: 3px;
}
.ocr-field label {
  font-size: 11px;
  color: #6B7280;
  font-weight: 600;
}
.checkup-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}
.checkup-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 10px;
  border-radius: 10px;
  border: 1px solid rgba(255,255,255,0.08);
  background: rgba(255,255,255,0.03);
  color: #E8E9ED;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}
.checkup-item.ok {
  background: rgba(16,185,129,0.08);
  border-color: rgba(16,185,129,0.25);
  color: #6EE7B7;
}
.checkup-item.nok {
  background: rgba(239,68,68,0.08);
  border-color: rgba(239,68,68,0.25);
  color: #FCA5A5;
}
.companion-validate-btn {
  width: 100%;
  padding: 14px;
  border-radius: 12px;
  border: none;
  background: linear-gradient(135deg, #FFD200, #D97706);
  color: #111;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s;
}
.companion-validate-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.companion-validate-btn:active:not(:disabled) {
  transform: scale(0.98);
}
.sig-canvas-wrapper {
  border-radius: 14px;
  overflow: hidden;
  border: 2px solid rgba(255,255,255,0.12);
}
.companion-clauses {
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 12px;
  padding: 12px;
  background: rgba(255,255,255,0.03);
}
.companion-clauses__header {
  margin-bottom: 8px;
  color: #E8E9ED;
  font-size: 13px;
}
.companion-clauses__meta {
  font-size: 12px;
  color: #9CA3AF;
  margin: 0;
}
.companion-clauses__meta--warn {
  color: #FCD34D;
  margin-top: 8px;
}
.companion-clauses__meta--error {
  color: #FCA5A5;
}
.companion-clause-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 0;
  border-top: 1px solid rgba(255,255,255,0.08);
}
.companion-clause-item:first-of-type {
  border-top: none;
  padding-top: 0;
}
.companion-clause-item__content {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.companion-clause-item__title {
  font-size: 12px;
  color: #E8E9ED;
  font-weight: 700;
}
.companion-clause-item__text {
  margin: 0;
  font-size: 12px;
  color: #C9CED8;
  line-height: 1.4;
  white-space: pre-wrap;
}
.ocr-spinner {
  width: 40px;
  height: 40px;
  margin: 0 auto;
  border: 3px solid rgba(255,210,0,0.15);
  border-top-color: #FFD200;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
