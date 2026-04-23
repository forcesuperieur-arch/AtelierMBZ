<template>
  <div class="public-card" style="max-width:640px;">
    <div class="public-card-header">
      <div style="font-size:32px;margin-bottom:8px;">🛠️</div>
      <h1 class="text-gradient" style="font-size:22px;font-weight:800;">Demande de travaux complémentaires</h1>
      <p v-if="demande" style="font-size:13px;color:#9CA3AF;margin-top:4px;">
        {{ demande.client_prenom }} — {{ demande.vehicule?.marque }} {{ demande.vehicule?.modele }}
        <template v-if="demande.vehicule?.plaque"> ({{ demande.vehicule.plaque }})</template>
      </p>
    </div>

    <div v-if="loading" style="text-align:center;color:#9CA3AF;padding:24px;">Chargement…</div>

    <div v-else-if="error" style="padding:16px;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);border-radius:12px;color:#FCA5A5;text-align:center;">
      {{ error }}
    </div>

    <template v-else-if="demande">
      <div v-if="demande.statut === 'accepte' || demande.statut === 'refuse'" style="padding:24px;text-align:center;border-radius:12px;"
        :style="demande.statut === 'accepte' ? 'background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.2);color:#6EE7B7;' : 'background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);color:#FCA5A5;'">
        <div style="font-size:40px;margin-bottom:12px;">{{ demande.statut === 'accepte' ? '✅' : '❌' }}</div>
        <div style="font-size:16px;font-weight:800;margin-bottom:6px;">
          {{ demande.statut === 'accepte' ? 'Travaux acceptés' : 'Travaux refusés' }}
        </div>
        <div style="font-size:13px;opacity:0.85;">Votre décision a bien été enregistrée. Nous vous remercions.</div>
      </div>

      <template v-else>
        <!-- urgence badge -->
        <div v-if="demande.urgence === 'urgent'" style="margin-bottom:14px;padding:10px 14px;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.25);border-radius:10px;color:#FCA5A5;font-size:13px;font-weight:700;">
          ⚠️ Intervention urgente — réponse rapide souhaitée
        </div>

        <div v-if="demande.description" style="margin-bottom:14px;padding:12px 14px;border-radius:10px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);color:#E8E9ED;font-size:13px;line-height:1.5;">
          {{ demande.description }}
        </div>

        <!-- prestations -->
        <div style="padding:14px;border:1px solid rgba(255,255,255,0.06);border-radius:12px;background:rgba(255,255,255,0.02);margin-bottom:14px;">
          <div style="font-size:13px;font-weight:800;color:#E8E9ED;margin-bottom:10px;">Prestations proposées</div>
          <div v-for="(p, i) in demande.prestations" :key="i" style="display:flex;justify-content:space-between;padding:8px 0;border-top:1px solid rgba(255,255,255,0.05);">
            <div>
              <div style="font-size:13px;color:#E8E9ED;font-weight:600;">{{ p.designation }}</div>
              <div style="font-size:11px;color:#6B7280;">{{ p.temps_minutes }} min</div>
            </div>
            <div style="font-size:13px;color:#FFD200;font-weight:700;">{{ formatEuro(p.prix_ttc) }}</div>
          </div>
          <div style="display:flex;justify-content:space-between;padding-top:12px;margin-top:8px;border-top:2px solid rgba(255,210,0,0.3);">
            <div style="font-size:14px;color:#E8E9ED;font-weight:800;">Total TTC</div>
            <div style="font-size:16px;color:#FFD200;font-weight:800;">{{ formatEuro(demande.prix_estime) }}</div>
          </div>
          <div style="font-size:11px;color:#6B7280;margin-top:4px;">Temps estimé : ~{{ formatDuration(demande.temps_estime) }}</div>
        </div>

        <!-- actions -->
        <div v-if="step === 'choice'" style="display:flex;flex-direction:column;gap:12px;">
          <button class="btn btn-primary" style="padding:14px;font-weight:800;" @click="step = 'sign'">
            ✅ J'accepte ces travaux
          </button>
          <button class="btn btn-ghost" style="padding:14px;color:#FCA5A5;border-color:rgba(239,68,68,0.25);" @click="refuse" :disabled="submitting">
            ❌ Je refuse ces travaux
          </button>
          <div style="font-size:11px;color:#6B7280;text-align:center;margin-top:4px;">
            En acceptant, vous signerez électroniquement un ordre de réparation complémentaire.
          </div>
        </div>

        <div v-else-if="step === 'sign'" style="display:flex;flex-direction:column;gap:12px;">
          <div style="font-size:13px;color:#E8E9ED;font-weight:700;">Signature électronique</div>
          <div style="border:1px solid rgba(255,255,255,0.08);border-radius:10px;background:#fff;overflow:hidden;">
            <canvas ref="sigCanvas" width="600" height="200" style="width:100%;height:200px;display:block;touch-action:none;" />
          </div>
          <div style="display:flex;gap:8px;">
            <button class="btn btn-ghost" style="flex:1;" @click="clearSig">Effacer</button>
            <button class="btn btn-ghost" style="flex:1;" @click="step = 'choice'">Retour</button>
          </div>
          <button class="btn btn-primary" style="padding:14px;font-weight:800;" :disabled="submitting || !hasSigned" @click="accept">
            {{ submitting ? 'Envoi…' : '✍️ Valider et accepter' }}
          </button>
        </div>
      </template>
    </template>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'public', auth: false })

const route = useRoute()
const api = useApi()
const { formatDuration } = useFormat()
const token = String(route.params.token)

const loading = ref(true)
const error = ref('')
const demande = ref<any>(null)
const step = ref<'choice' | 'sign'>('choice')
const submitting = ref(false)
const sigCanvas = ref<HTMLCanvasElement | null>(null)
const hasSigned = ref(false)

async function load() {
  loading.value = true
  try {
    demande.value = await api.get(`/public/demandes-travaux-supp/${token}`)
  } catch (e: unknown) {
    error.value = (e instanceof Error ? e.message : 'Erreur inconnue') || 'Lien invalide ou expiré.'
  } finally {
    loading.value = false
  }
}

function initCanvas() {
  const canvas = sigCanvas.value
  if (!canvas) return
  const ctx = canvas.getContext('2d')
  if (!ctx) return
  ctx.fillStyle = '#ffffff'
  ctx.fillRect(0, 0, canvas.width, canvas.height)
  ctx.strokeStyle = '#111827'
  ctx.lineWidth = 2.2
  ctx.lineCap = 'round'
  ctx.lineJoin = 'round'

  let drawing = false
  const getPos = (e: PointerEvent) => {
    const rect = canvas.getBoundingClientRect()
    return {
      x: (e.clientX - rect.left) * (canvas.width / rect.width),
      y: (e.clientY - rect.top) * (canvas.height / rect.height),
    }
  }
  canvas.onpointerdown = (e) => {
    drawing = true
    canvas.setPointerCapture(e.pointerId)
    const { x, y } = getPos(e)
    ctx.beginPath()
    ctx.moveTo(x, y)
  }
  canvas.onpointermove = (e) => {
    if (!drawing) return
    const { x, y } = getPos(e)
    ctx.lineTo(x, y)
    ctx.stroke()
    hasSigned.value = true
  }
  canvas.onpointerup = () => { drawing = false }
  canvas.onpointerleave = () => { drawing = false }
}

function clearSig() {
  const canvas = sigCanvas.value
  if (!canvas) return
  const ctx = canvas.getContext('2d')
  if (!ctx) return
  ctx.fillStyle = '#ffffff'
  ctx.fillRect(0, 0, canvas.width, canvas.height)
  hasSigned.value = false
}

async function refuse() {
  if (!confirm('Confirmer le refus de ces travaux complémentaires ?')) return
  submitting.value = true
  try {
    await api.post(`/public/demandes-travaux-supp/${token}/decision`, { decision: 'refuse' })
    await load()
  } catch (e: unknown) {
    alert((e instanceof Error ? e.message : 'Erreur inconnue') || 'Erreur lors de l\'envoi')
  } finally {
    submitting.value = false
  }
}

async function accept() {
  const canvas = sigCanvas.value
  if (!canvas || !hasSigned.value) return
  const signature = canvas.toDataURL('image/png')
  submitting.value = true
  try {
    await api.post(`/public/demandes-travaux-supp/${token}/decision`, { decision: 'accepte', signature })
    await load()
  } catch (e: unknown) {
    alert((e instanceof Error ? e.message : 'Erreur inconnue') || 'Erreur lors de la signature')
  } finally {
    submitting.value = false
  }
}

function formatEuro(v: any): string {
  const n = Number(v) || 0
  return n.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' })
}

watch(step, (s) => {
  if (s === 'sign') nextTick(() => initCanvas())
})

onMounted(load)
</script>
