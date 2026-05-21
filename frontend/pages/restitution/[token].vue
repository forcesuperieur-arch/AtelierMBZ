<template>
  <NuxtLayout name="public">
    <div class="restitution-container">
      <!-- Loading -->
      <div v-if="loading" style="text-align:center;padding:40px;">
        <div style="font-size:32px;margin-bottom:12px;">⏳</div>
        <p style="color:#9CA3AF;">Chargement…</p>
      </div>

      <!-- Error -->
      <div v-else-if="error" style="text-align:center;padding:40px;">
        <div style="font-size:48px;margin-bottom:16px;">🚫</div>
        <h2 style="color:#FCA5A5;font-size:18px;margin-bottom:8px;">Lien invalide</h2>
        <p style="color:#9CA3AF;font-size:13px;">{{ error }}</p>
      </div>

      <!-- Signed -->
      <div v-else-if="signed" style="text-align:center;padding:40px;">
        <div style="font-size:48px;margin-bottom:16px;">✅</div>
        <h2 style="color:#6EE7B7;font-size:18px;margin-bottom:8px;">Restitution signée</h2>
        <p style="color:#9CA3AF;font-size:13px;">Merci ! Vous pouvez récupérer votre véhicule.</p>
      </div>

      <!-- Restitution form -->
      <div v-else-if="data" class="restitution-card">
        <!-- Header -->
        <div class="restitution-header">
          <div style="font-size:28px;">🔑</div>
          <div>
            <h1 style="font-size:18px;font-weight:800;color:#E8E9ED;margin:0;">Restitution du véhicule</h1>
            <p style="font-size:12px;color:#9CA3AF;margin:2px 0 0;">
              RDV #{{ data.rdv.id }} · {{ data.rdv.date }} à {{ data.rdv.heure }}
            </p>
          </div>
        </div>

        <!-- Client & Véhicule -->
        <div class="restitution-section">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px;">
            <div><span style="color:#6B7280;">Client :</span> <span style="color:#D1D5DB;">{{ data.client?.prenom }} {{ data.client?.nom }}</span></div>
            <div><span style="color:#6B7280;">Tél :</span> <span style="color:#D1D5DB;">{{ data.client?.telephone || '—' }}</span></div>
            <div><span style="color:#6B7280;">Véhicule :</span> <span style="color:#D1D5DB;">{{ data.vehicule?.marque }} {{ data.vehicule?.modele }}</span></div>
            <div><span style="color:#6B7280;">Plaque :</span> <span style="color:#D1D5DB;">{{ data.vehicule?.plaque || '—' }}</span></div>
          </div>
        </div>

        <!-- Travaux réalisés -->
        <div class="restitution-section">
          <h3 style="font-size:13px;font-weight:700;color:#E8E9ED;margin:0 0 8px;">🔧 Travaux réalisés</h3>
          <div v-if="data.ordre?.travaux_realises" style="font-size:13px;color:#D1D5DB;white-space:pre-wrap;">{{ data.ordre.travaux_realises }}</div>
          <div v-else style="font-size:13px;color:#6B7280;font-style:italic;">Aucun détail renseigné</div>
        </div>

        <!-- Alertes -->
        <div v-if="data.ordre?.alertes" class="restitution-section" style="border-left:3px solid #EF4444;">
          <h3 style="font-size:13px;font-weight:700;color:#EF4444;margin:0 0 8px;">⚠️ Alertes</h3>
          <div style="font-size:13px;color:#D1D5DB;white-space:pre-wrap;">{{ data.ordre.alertes }}</div>
        </div>

        <!-- Recommandations -->
        <div v-if="data.ordre?.recommandations" class="restitution-section" style="border-left:3px solid #F59E0B;">
          <h3 style="font-size:13px;font-weight:700;color:#F59E0B;margin:0 0 8px;">💡 Recommandations</h3>
          <div style="font-size:13px;color:#D1D5DB;white-space:pre-wrap;">{{ data.ordre.recommandations }}</div>
        </div>

        <!-- Garantie -->
        <div v-if="data.ordre?.garantie" class="restitution-section" style="border-left:3px solid #10B981;">
          <h3 style="font-size:13px;font-weight:700;color:#10B981;margin:0 0 8px;">🛡️ Garantie</h3>
          <div style="font-size:13px;color:#D1D5DB;white-space:pre-wrap;">{{ data.ordre.garantie }}</div>
        </div>

        <!-- Kilométrage & prochaine révision -->
        <div class="restitution-section">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:13px;">
            <div v-if="data.ordre?.kilometrage_restitution">
              <span style="color:#6B7280;">Km restitution :</span>
              <span style="color:#D1D5DB;font-weight:700;"> {{ data.ordre.kilometrage_restitution }} km</span>
            </div>
            <div v-if="data.ordre?.prochaine_revision_km">
              <span style="color:#6B7280;">Prochaine révision :</span>
              <span style="color:#D1D5DB;font-weight:700;"> {{ data.ordre.prochaine_revision_km }} km</span>
            </div>
            <div v-if="data.ordre?.prochaine_revision_date" style="grid-column:1/-1;">
              <span style="color:#6B7280;">Date prochaine révision :</span>
              <span style="color:#D1D5DB;font-weight:700;"> {{ formatDate(data.ordre.prochaine_revision_date) }}</span>
            </div>
          </div>
        </div>

        <!-- Signature -->
        <div class="restitution-section">
          <h3 style="font-size:13px;font-weight:700;color:#E8E9ED;margin:0 0 8px;">✍️ Signature client</h3>
          <p style="font-size:12px;color:#9CA3AF;margin:0 0 12px;">
            En signant, je reconnais avoir pris connaissance des travaux effectués et récupéré mon véhicule.
          </p>
          <div class="sig-canvas-wrapper">
            <canvas
              ref="sigCanvas"
              width="600"
              height="200"
              @pointerdown="startDraw"
              @pointermove="draw"
              @pointerup="endDraw"
              @pointerleave="endDraw"
              style="width:100%;height:160px;border-radius:12px;background:rgba(255,255,255,0.95);touch-action:none;cursor:crosshair;"
            ></canvas>
          </div>
          <div style="display:flex;gap:8px;margin-top:12px;">
            <button class="restitution-btn-secondary" style="flex:1;" @click="clearSignature">
              ↺ Effacer
            </button>
            <button
              class="restitution-btn-primary"
              style="flex:2;"
              :disabled="sigSaving || !hasDrawn"
              @click="submitSignature"
            >
              {{ sigSaving ? 'Enregistrement…' : '✓ Valider la restitution' }}
            </button>
          </div>
          <p v-if="sigError" style="font-size:12px;color:#FCA5A5;text-align:center;margin-top:8px;">{{ sigError }}</p>
        </div>
      </div>
    </div>
  </NuxtLayout>
</template>

<script setup lang="ts">
definePageMeta({ layout: false })

const route = useRoute()
const token = route.params.token as string

const config = useRuntimeConfig()
const baseURL = config.public.apiBase as string

const loading = ref(true)
const error = ref('')
const data = ref<any>(null)
const signed = ref(false)

const sigCanvas = ref<HTMLCanvasElement | null>(null)
const isDrawing = ref(false)
const hasDrawn = ref(false)
const sigSaving = ref(false)
const sigError = ref('')

function formatDate(d: string | null) {
  if (!d) return '—'
  const [y, m, day] = d.split('-')
  return `${day}/${m}/${y}`
}

// Load restitution data
onMounted(async () => {
  try {
    const res = await fetch(`${baseURL}/public/restitution/${token}`, {
      headers: { Accept: 'application/json' },
    })
    if (!res.ok) {
      const err = await res.json().catch(() => ({}))
      throw new Error(err.error || 'Lien invalide.')
    }
    data.value = await res.json()
  } catch (e: any) {
    error.value = e?.message || 'Erreur de chargement.'
  } finally {
    loading.value = false
  }
})

// Signature drawing
function getCtx() {
  const c = sigCanvas.value
  if (!c) return null
  return c.getContext('2d')
}

function getPos(e: PointerEvent) {
  const c = sigCanvas.value!
  const rect = c.getBoundingClientRect()
  return {
    x: (e.clientX - rect.left) * (c.width / rect.width),
    y: (e.clientY - rect.top) * (c.height / rect.height),
  }
}

function startDraw(e: PointerEvent) {
  isDrawing.value = true
  hasDrawn.value = true
  const ctx = getCtx()
  if (!ctx) return
  ctx.beginPath()
  const pos = getPos(e)
  ctx.moveTo(pos.x, pos.y)
}

function draw(e: PointerEvent) {
  if (!isDrawing.value) return
  const ctx = getCtx()
  if (!ctx) return
  const pos = getPos(e)
  ctx.lineTo(pos.x, pos.y)
  ctx.strokeStyle = '#111'
  ctx.lineWidth = 2
  ctx.lineCap = 'round'
  ctx.lineJoin = 'round'
  ctx.stroke()
}

function endDraw() {
  isDrawing.value = false
  const ctx = getCtx()
  if (ctx) ctx.beginPath()
}

function clearSignature() {
  const c = sigCanvas.value
  if (!c) return
  const ctx = c.getContext('2d')
  if (!ctx) return
  ctx.clearRect(0, 0, c.width, c.height)
  hasDrawn.value = false
}

async function submitSignature() {
  const c = sigCanvas.value
  if (!c) return
  const signature = c.toDataURL('image/png')
  sigSaving.value = true
  sigError.value = ''
  try {
    const res = await fetch(`${baseURL}/public/restitution/${token}/sign`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ signature }),
    })
    if (!res.ok) {
      const err = await res.json().catch(() => ({}))
      throw new Error(err.error || 'Erreur lors de la signature.')
    }
    signed.value = true
  } catch (e: any) {
    sigError.value = e?.message || 'Erreur lors de la signature.'
  } finally {
    sigSaving.value = false
  }
}
</script>

<style scoped>
.restitution-container {
  width: 100%;
  max-width: 520px;
  padding: 16px;
}
.restitution-card {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.restitution-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 16px;
}
.restitution-section {
  padding: 16px;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 16px;
}
.sig-canvas-wrapper {
  border: 2px dashed rgba(255, 255, 255, 0.2);
  border-radius: 14px;
  overflow: hidden;
}
.restitution-btn-primary {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 16px;
  border-radius: 12px;
  border: none;
  background: linear-gradient(135deg, #FFD200, #D97706);
  color: #111;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  transition: opacity 0.2s, transform 0.15s;
}
.restitution-btn-primary:hover:not(:disabled) {
  opacity: 0.92;
  transform: translateY(-1px);
}
.restitution-btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.restitution-btn-secondary {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 16px;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.15);
  background: rgba(255, 255, 255, 0.06);
  color: #D1D5DB;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}
.restitution-btn-secondary:hover {
  background: rgba(255, 255, 255, 0.1);
}
</style>
