<template>
  <div>
    <NuxtLayout name="public">
      <div class="companion-container">
        <div v-if="loading" style="text-align:center;padding:40px;">
          <div style="font-size:32px;margin-bottom:12px;">⏳</div>
          <p style="color:#9CA3AF;">Chargement…</p>
        </div>

        <div v-else-if="error" style="text-align:center;padding:40px;">
          <div style="font-size:48px;margin-bottom:16px;">🚫</div>
          <h2 style="color:#FCA5A5;font-size:18px;margin-bottom:8px;">Lien invalide</h2>
          <p style="color:#9CA3AF;font-size:13px;">{{ error }}</p>
        </div>

        <div v-else-if="rdv">
          <!-- Header -->
          <div class="companion-header">
            <div style="font-size:28px;">🏍</div>
            <div>
              <h1 style="font-size:18px;font-weight:800;color:#E8E9ED;margin:0;">Réception PDA</h1>
              <p style="font-size:12px;color:#9CA3AF;margin:2px 0 0;">RDV #{{ rdv.id }} · {{ rdv.date_rdv }} à {{ rdv.heure_rdv }}</p>
            </div>
          </div>

          <!-- RDV Info Summary -->
          <div class="companion-card">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px;">
              <div><span style="color:#6B7280;">Client :</span> <span style="color:#D1D5DB;">{{ rdv.client?.prenom }} {{ rdv.client?.nom }}</span></div>
              <div><span style="color:#6B7280;">Tél :</span> <span style="color:#D1D5DB;">{{ rdv.client?.telephone || '—' }}</span></div>
              <div><span style="color:#6B7280;">Véhicule :</span> <span style="color:#D1D5DB;">{{ rdv.vehicule?.marque }} {{ rdv.vehicule?.modele }}</span></div>
              <div><span style="color:#6B7280;">Plaque :</span> <span style="color:#D1D5DB;">{{ rdv.vehicule?.plaque || '—' }}</span></div>
              <div style="grid-column:1/-1;"><span style="color:#6B7280;">Intervention :</span> <span style="color:#D1D5DB;">{{ rdv.type_intervention }}</span></div>
            </div>
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

          <!-- Action Buttons (main menu) -->
          <div v-if="!activeSection" class="companion-actions">
            <button class="companion-action-btn" @click="activeSection = 'photos'">
              <span style="font-size:36px;">📸</span>
              <div>
                <span style="font-size:14px;font-weight:700;color:#E8E9ED;">Photos véhicule</span>
                <span style="display:block;font-size:11px;color:#9CA3AF;">État extérieur / intérieur</span>
              </div>
            </button>

            <button class="companion-action-btn" @click="activeSection = 'carte-grise'">
              <span style="font-size:36px;">🪪</span>
              <div>
                <span style="font-size:14px;font-weight:700;color:#E8E9ED;">Scanner carte grise</span>
                <span style="display:block;font-size:11px;color:#9CA3AF;">OCR auto-remplissage</span>
              </div>
            </button>

            <button class="companion-action-btn" @click="activeSection = 'checkup'">
              <span style="font-size:36px;">🔎</span>
              <div>
                <span style="font-size:14px;font-weight:700;color:#E8E9ED;">Checkup express</span>
                <span style="display:block;font-size:11px;color:#9CA3AF;">{{ checkupDone }}/{{ checkupItems.length }} point{{ checkupDone > 1 ? 's' : '' }} vérifié{{ checkupDone > 1 ? 's' : '' }}</span>
              </div>
            </button>

            <button class="companion-action-btn" @click="activeSection = 'signature'">
              <span style="font-size:36px;">✍️</span>
              <div>
                <span style="font-size:14px;font-weight:700;color:#E8E9ED;">Signature client</span>
                <span style="display:block;font-size:11px;color:#9CA3AF;">{{ rdv.has_signature ? 'Déjà signé ✓' : 'Obligatoire' }}</span>
              </div>
            </button>
          </div>

          <!-- PHOTOS SECTION -->
          <div v-if="activeSection === 'photos'" class="companion-section">
            <div class="companion-section-header">
              <h2>📸 Photos du véhicule</h2>
              <button class="companion-close" @click="activeSection = null">✕</button>
            </div>

            <div style="display:flex;flex-direction:column;gap:12px;">
              <label class="companion-capture-btn">
                <input type="file" accept="image/*" capture="environment" multiple @change="onPhotosSelected" style="display:none;" />
                <span style="font-size:24px;">📷</span>
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

            <div style="display:flex;flex-direction:column;gap:12px;">
              <label class="companion-capture-btn">
                <input type="file" accept="image/*" capture="environment" @change="onCarteGriseSelected" style="display:none;" />
                <span style="font-size:24px;">📷</span>
                <span>{{ ocrProcessing ? 'Analyse OCR en cours…' : 'Photographier la carte grise' }}</span>
              </label>

              <div v-if="ocrProcessing" style="text-align:center;padding:20px;">
                <div class="ocr-spinner"></div>
                <p style="color:#FBBF24;font-size:13px;margin-top:12px;">Extraction des informations…</p>
                <p style="color:#6B7280;font-size:11px;">Cela peut prendre quelques secondes</p>
              </div>

              <div v-if="ocrResult" class="companion-card" style="border-color:rgba(16,185,129,0.3);">
                <div style="font-size:13px;font-weight:700;color:#6EE7B7;margin-bottom:10px;">✓ Données extraites — vérifiez et corrigez si besoin</div>
                <div style="display:flex;flex-direction:column;gap:8px;">
                  <div class="ocr-field">
                    <label>Plaque (A)</label>
                    <input v-model="ocrResult.plaque" class="companion-input" />
                  </div>
                  <div class="ocr-field">
                    <label>Marque (D.1)</label>
                    <input v-model="ocrResult.marque" class="companion-input" />
                  </div>
                  <div class="ocr-field">
                    <label>Modèle (D.2)</label>
                    <input v-model="ocrResult.modele" class="companion-input" />
                  </div>
                  <div class="ocr-field">
                    <label>Mise en circulation (B)</label>
                    <input v-model="ocrResult.annee" class="companion-input" />
                  </div>
                  <div class="ocr-field">
                    <label>Cylindrée (P.1)</label>
                    <input v-model="ocrResult.cylindree" class="companion-input" />
                  </div>
                  <div class="ocr-field">
                    <label>Type (J.1)</label>
                    <input v-model="ocrResult.type_moto" class="companion-input" />
                  </div>
                </div>
                <button
                  class="companion-validate-btn"
                  style="margin-top:12px;"
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

            <p style="font-size:12px;color:#9CA3AF;margin:0 0 12px;">
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

            <div class="ocr-field" style="margin-top:12px;">
              <label>Notes checkup</label>
              <textarea
                v-model="checkupNotes"
                class="companion-input"
                rows="4"
                placeholder="Usure, point à surveiller, remarque utile…"
              ></textarea>
            </div>

            <button
              class="companion-validate-btn"
              style="margin-top:12px;"
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

            <div v-if="rdv.has_signature && !resignMode" style="text-align:center;padding:20px;">
              <div style="font-size:48px;margin-bottom:12px;">✅</div>
              <p style="color:#6EE7B7;font-size:15px;font-weight:600;">Signature déjà enregistrée</p>
              <button class="companion-capture-btn" style="margin-top:16px;" @click="resignMode = true">
                <span>Refaire la signature</span>
              </button>
            </div>

            <div v-else style="display:flex;flex-direction:column;gap:12px;">
              <p style="font-size:13px;color:#9CA3AF;text-align:center;">
                Le client accepte les travaux décrits et confirme l'état du véhicule à la réception.
              </p>

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

              <div style="display:flex;gap:8px;">
                <button class="companion-capture-btn" style="flex:1;" @click="clearSignature">
                  <span>↺ Effacer</span>
                </button>
                <button
                  class="companion-validate-btn"
                  style="flex:2;"
                  :disabled="sigSaving || !hasDrawn"
                  @click="submitSignature"
                >
                  {{ sigSaving ? 'Envoi…' : '✓ Valider la signature' }}
                </button>
              </div>
            </div>
          </div>

          <!-- All-done summary -->
          <div v-if="rdv.has_signature && rdv.photos_count > 0 && !activeSection" class="companion-card" style="border-color:rgba(16,185,129,0.3);text-align:center;margin-top:8px;">
            <div style="font-size:32px;margin-bottom:8px;">✅</div>
            <p style="color:#6EE7B7;font-weight:700;font-size:14px;">Réception prête à valider</p>
            <p style="color:#9CA3AF;font-size:12px;">Le réceptionnaire peut maintenant valider la réception depuis le planning PC.</p>
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

const token = computed(() => String(route.query.token || ''))
const loading = ref(true)
const error = ref('')
const rdv = ref<any>(null)
const activeSection = ref<string | null>(null)
const uploading = ref(false)
const ocrProcessing = ref(false)
const ocrSaving = ref(false)
const ocrResult = ref<any>(null)
const carteGriseScanned = ref(false)
const sigSaving = ref(false)
const hasDrawn = ref(false)
const resignMode = ref(false)
const sigCanvas = ref<HTMLCanvasElement | null>(null)
const checkupSaving = ref(false)
const checkupNotes = ref('')

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

function loadSavedCheckup() {
  Object.keys(checkup).forEach((key) => { delete checkup[key] })
  const savedCheckup = rdv.value?.checkup ?? {}
  Object.entries(savedCheckup).forEach(([key, value]) => {
    if (value) checkup[key] = String(value)
  })
  checkupNotes.value = rdv.value?.checkup_notes ?? ''
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
    if (!res.ok) throw new Error('Lien invalide ou expiré')
    rdv.value = await res.json()
    loadSavedCheckup()
  } catch (e: any) {
    error.value = e.message || 'Erreur de chargement'
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
  try {
    for (const file of Array.from(files)) {
      const fd = new FormData()
      fd.append('photo', file)
      fd.append('description', 'Photo réception')
      await globalThis.fetch(`${apiBase}/companion/${token.value}/photo`, {
        method: 'POST',
        body: fd,
      })
    }
    await fetchRdv()
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
    // Upload as photo too
    const fd = new FormData()
    fd.append('photo', file)
    fd.append('description', 'Carte grise')
    await globalThis.fetch(`${apiBase}/companion/${token.value}/photo`, {
      method: 'POST',
      body: fd,
    })

    // Client-side OCR with Tesseract.js
    const { createWorker } = await import('tesseract.js')
    const worker = await createWorker('fra')
    const { data: { text } } = await worker.recognize(file)
    await worker.terminate()

    ocrResult.value = parseCarteGriseText(text)
    carteGriseScanned.value = true
    await fetchRdv()
  } catch (err) {
    console.warn('OCR error, pre-filling with vehicle data:', err)
    ocrResult.value = {
      plaque: rdv.value?.vehicule?.plaque || '',
      marque: rdv.value?.vehicule?.marque || '',
      modele: rdv.value?.vehicule?.modele || '',
      annee: rdv.value?.vehicule?.annee ? String(rdv.value.vehicule.annee) : '',
      cylindree: rdv.value?.vehicule?.cylindree || '',
      type_moto: rdv.value?.vehicule?.type_moto || '',
    }
    carteGriseScanned.value = true
  } finally {
    ocrProcessing.value = false
    input.value = ''
  }
}

function parseCarteGriseText(text: string): Record<string, string> {
  const lines = text.split('\n').map(l => l.trim()).filter(Boolean)
  const joined = lines.join(' ')

  const result: Record<string, string> = {
    plaque: rdv.value?.vehicule?.plaque || '',
    marque: rdv.value?.vehicule?.marque || '',
    modele: rdv.value?.vehicule?.modele || '',
    annee: rdv.value?.vehicule?.annee ? String(rdv.value.vehicule.annee) : '',
    cylindree: rdv.value?.vehicule?.cylindree || '',
    type_moto: rdv.value?.vehicule?.type_moto || '',
  }

  // Plaque: format AA-123-BB or AA 123 BB
  const plaqueMatch = joined.match(/\b([A-Z]{2}[\s-]?\d{3}[\s-]?[A-Z]{2})\b/i)
  if (plaqueMatch) result.plaque = plaqueMatch[1].replace(/\s/g, '-').toUpperCase()

  // Marque: common motorcycle brands
  const brands = ['YAMAHA', 'HONDA', 'KAWASAKI', 'SUZUKI', 'BMW', 'DUCATI', 'KTM', 'HARLEY', 'TRIUMPH', 'APRILIA', 'MV AGUSTA', 'INDIAN', 'HUSQVARNA', 'BENELLI', 'ROYAL ENFIELD', 'MOTO GUZZI', 'PIAGGIO', 'VESPA', 'KYMCO', 'SYM', 'PEUGEOT']
  for (const brand of brands) {
    if (joined.toUpperCase().includes(brand)) {
      result.marque = brand.charAt(0) + brand.slice(1).toLowerCase()
      break
    }
  }

  // Year
  const yearMatch = joined.match(/\b(19[9]\d|20[0-3]\d)\b/)
  if (yearMatch) result.annee = yearMatch[1]

  // Cylindrée (P.1)
  const cylMatch = joined.match(/(\d{2,4})\s*(?:cm[³3]|cc|CM3)/i)
  if (cylMatch) result.cylindree = cylMatch[1]

  // Type: MTL, MTT1, MTT2, CL, QM, TM
  const typeMatch = joined.match(/\b(MTL|MTT1|MTT2|CL|QM|TM)\b/i)
  if (typeMatch) result.type_moto = typeMatch[1].toUpperCase()

  return result
}

async function applyOcrData() {
  if (!ocrResult.value) return
  ocrSaving.value = true
  try {
    await globalThis.fetch(`${apiBase}/companion/${token.value}/vehicule`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(ocrResult.value),
    })
    await fetchRdv()
    activeSection.value = null
  } finally {
    ocrSaving.value = false
  }
}

async function saveCheckup() {
  checkupSaving.value = true
  try {
    const res = await globalThis.fetch(`${apiBase}/companion/${token.value}/reception-data`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        checkup: { ...checkup },
        checkup_notes: checkupNotes.value,
      }),
    })

    if (!res.ok) {
      throw new Error('Erreur enregistrement checkup')
    }

    await fetchRdv()
    activeSection.value = null
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
  sigSaving.value = true
  try {
    const dataUrl = sigCanvas.value.toDataURL('image/png')
    const res = await globalThis.fetch(`${apiBase}/companion/${token.value}/signature`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ signature: dataUrl }),
    })
    if (!res.ok) {
      const err = await res.json().catch(() => ({}))
      throw new Error(err.error || 'Erreur signature')
    }
    rdv.value.has_signature = true
    resignMode.value = false
    activeSection.value = null
    await fetchRdv()
  } finally {
    sigSaving.value = false
  }
}

// --- Polling status updates ---
let pollInterval: ReturnType<typeof setInterval>

onMounted(async () => {
  await fetchRdv()
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
    } catch {}
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
