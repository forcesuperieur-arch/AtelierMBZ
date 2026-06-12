<template>
  <div class="sig-overlay" role="dialog" aria-modal="true" aria-label="Signature" @click.self="$emit('close')">
    <div class="sig-modal">
      <h2 class="sig-title">{{ title }}</h2>
      <p class="sig-hint">Signez dans le cadre ci-dessous avec le doigt ou la souris.</p>

      <canvas
        ref="sigCanvas"
        class="sig-canvas"
        width="600"
        height="220"
        @pointerdown="startDraw"
        @pointermove="draw"
        @pointerup="endDraw"
        @pointerleave="endDraw"
      />

      <div v-if="error" class="sig-error">{{ error }}</div>

      <div class="sig-actions">
        <button class="btn-ghost" type="button" :disabled="saving" @click="$emit('close')">Annuler</button>
        <button class="btn-ghost" type="button" :disabled="saving || !hasDrawn" @click="clearSignature">Effacer</button>
        <button class="btn-primary" type="button" :disabled="saving || !hasDrawn" @click="confirm">
          {{ saving ? 'Envoi…' : confirmLabel }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  title?: string
  confirmLabel?: string
  saving?: boolean
  error?: string
}>(), {
  title: 'Votre signature',
  confirmLabel: 'Valider et signer',
  saving: false,
  error: '',
})

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'signed', dataUrl: string): void
}>()

const sigCanvas = ref<HTMLCanvasElement | null>(null)
const isDrawing = ref(false)
const hasDrawn = ref(false)

onMounted(() => {
  // Fond blanc : la signature est encre sombre sur blanc dans le PDF figé
  const ctx = sigCanvas.value?.getContext('2d')
  if (ctx && sigCanvas.value) {
    ctx.fillStyle = '#fff'
    ctx.fillRect(0, 0, sigCanvas.value.width, sigCanvas.value.height)
  }
})

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
  const ctx = sigCanvas.value?.getContext('2d')
  if (!ctx) return
  ctx.beginPath()
  const pos = getPos(e)
  ctx.moveTo(pos.x, pos.y)
}

function draw(e: PointerEvent) {
  if (!isDrawing.value) return
  const ctx = sigCanvas.value?.getContext('2d')
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
  const ctx = sigCanvas.value?.getContext('2d')
  if (ctx) ctx.beginPath()
}

function clearSignature() {
  const c = sigCanvas.value
  const ctx = c?.getContext('2d')
  if (!c || !ctx) return
  ctx.fillStyle = '#fff'
  ctx.fillRect(0, 0, c.width, c.height)
  hasDrawn.value = false
}

function confirm() {
  const c = sigCanvas.value
  if (!c || !hasDrawn.value) return
  emit('signed', c.toDataURL('image/png'))
}
</script>

<style scoped>
.sig-overlay {
  position: fixed;
  inset: 0;
  z-index: 100;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  background: rgba(0, 0, 0, 0.7);
}
.sig-modal {
  width: 100%;
  max-width: 640px;
  padding: 20px;
  border-radius: 12px;
  background: #15161D;
  border: 1px solid rgba(255, 255, 255, 0.08);
}
.sig-title {
  font-size: 17px;
  font-weight: 800;
  margin: 0 0 4px;
}
.sig-hint {
  font-size: 13px;
  color: #9CA3AF;
  margin: 0 0 12px;
}
.sig-canvas {
  width: 100%;
  height: auto;
  border-radius: 8px;
  border: 1px dashed rgba(255, 255, 255, 0.25);
  background: #fff;
  /* Indispensable : sans ça le navigateur scrolle au lieu de dessiner */
  touch-action: none;
  cursor: crosshair;
}
.sig-error {
  margin-top: 8px;
  font-size: 13px;
  color: #FCA5A5;
}
.sig-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 14px;
  flex-wrap: wrap;
}
</style>
