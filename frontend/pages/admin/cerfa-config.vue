<template>
  <div class="cerfa-config-page">
    <AppPageHeader
      title="Configuration CERFA"
      subtitle="Glissez-déposez les champs sur le formulaire. Sélectionnez un champ pour affiner ses propriétés."
    />

    <UCard class="mb-3">
      <div class="top-bar">
        <div class="cerfa-tabs">
          <button
            v-for="(libelle, ref) in cerfaRefs"
            :key="ref"
            :class="['cerfa-tab', { active: selectedRef === ref }]"
            @click="selectRef(ref as string)"
          >{{ libelle }}</button>
        </div>
        <div class="mode-toggle">
          <button :class="['mode-btn', { active: mode === 'visual' }]" @click="mode = 'visual'">Éditeur visuel</button>
          <button :class="['mode-btn', { active: mode === 'table' }]" @click="mode = 'table'">Tableau</button>
        </div>
      </div>
    </UCard>

    <div v-if="loading" class="cerfa-loading">Chargement…</div>

    <template v-else-if="selectedRef">

      <!-- MODE VISUEL -->
      <div v-if="mode === 'visual'" class="visual-layout">

        <!-- Gauche : canvas -->
        <div class="visual-main">
          <div class="visual-toolbar">
            <div v-if="bgPages.length > 1" class="page-selector">
              <button
                v-for="(_, pi) in bgPages"
                :key="pi"
                :class="['page-btn', { active: activePage === pi }]"
                @click="activePage = pi"
              >Page {{ pi + 1 }}</button>
            </div>
            <span class="hint-text">{{ fields.length }} champs — glisser pour repositionner</span>
            <template v-if="pendingFields.size > 0">
              <span class="pending-badge">{{ pendingFields.size }} modifié{{ pendingFields.size > 1 ? 's' : '' }}</span>
              <button class="btn-save-all" :disabled="savingAll" @click="saveAll">
                {{ savingAll ? 'Enregistrement…' : 'Tout enregistrer' }}
              </button>
            </template>
          </div>

          <!-- Canvas PDF -->
          <div
            class="cerfa-canvas-wrap"
            ref="canvasWrapRef"
            :class="{ 'canvas-dragging': !!dragging }"
            @mousemove="onMouseMove"
            @mouseleave="onCanvasLeave"
          >
            <img
              :src="bgPages[activePage]"
              class="cerfa-bg"
              draggable="false"
              @load="onImgLoad"
            />

            <div
              v-for="field in fields"
              :key="field.id"
              :class="[
                'fov', 'fov-' + field.field_type,
                {
                  'fov-sel':      selectedField && selectedField.id === field.id,
                  'fov-pending':  pendingFields.has(field.id),
                  'fov-drag':     dragging && dragging.id === field.id,
                  'fov-inactive': !field.is_active,
                },
              ]"
              :style="overlayStyle(field)"
              @mousedown.prevent.stop="startDrag($event, field)"
              :title="field.label + ' — x:' + field.x + ' y:' + field.y + ' w:' + field.width + ' fs:' + field.font_size"
            >
              <span class="fov-label">{{ field.label }}</span>
            </div>

            <div
              v-if="cursorInfo && !dragging"
              class="cursor-badge"
              :style="{ left: cursorInfo.left + 'px', top: cursorInfo.top + 'px' }"
            >x {{ cursorInfo.x }} mm — y {{ cursorInfo.y }} mm</div>

            <div v-if="dragging" class="drag-badge">
              {{ dragging.label }} — x {{ dragging.x }} y {{ dragging.y }}
            </div>
          </div>
        </div>

        <!-- Droite : panneau -->
        <div class="visual-panel">
          <template v-if="selectedField">
            <div class="panel-header">
              <div class="panel-title">{{ selectedField.label }}</div>
              <div class="panel-key">{{ selectedField.field_key }}</div>
              <span :class="['type-badge', 'type-' + selectedField.field_type]">{{ selectedField.field_type }}</span>
            </div>

            <div class="panel-fields">
              <div class="pf-row">
                <label>X (mm)</label>
                <input type="number" step="0.1" v-model="selectedField.x" @change="onPanelChange(selectedField, 'x')" class="panel-input" />
              </div>
              <div class="pf-row">
                <label>Y (mm)</label>
                <input type="number" step="0.1" v-model="selectedField.y" @change="onPanelChange(selectedField, 'y')" class="panel-input" />
              </div>
              <div class="pf-row">
                <label>Largeur (mm)</label>
                <input type="number" step="0.5" v-model="selectedField.width" @change="onPanelChange(selectedField, 'width')" class="panel-input" />
              </div>
              <div class="pf-row">
                <label>Police (pt)</label>
                <input type="number" step="0.5" min="5" max="14" v-model="selectedField.font_size" @change="onPanelChange(selectedField, 'font_size')" class="panel-input" />
              </div>
              <div class="pf-row pf-row--check">
                <label>Actif</label>
                <input
                  type="checkbox"
                  :checked="selectedField.is_active"
                  @change="onPanelChange(selectedField, 'is_active', ($event.target as HTMLInputElement).checked)"
                />
              </div>
            </div>

            <div v-if="pendingFields.has(selectedField.id)" class="panel-unsaved">Modifications non enregistrées</div>

            <div class="panel-actions">
              <button
                class="btn-save"
                :disabled="!pendingFields.has(selectedField.id) || savingId === selectedField.id"
                @click="saveField(selectedField)"
              >{{ savingId === selectedField.id ? '…' : 'Enregistrer' }}</button>
              <button
                class="btn-reset"
                :disabled="savingId === selectedField.id"
                @click="resetField(selectedField)"
              >↺ Reset</button>
            </div>
          </template>
          <div v-else class="panel-empty">Cliquez ou glissez un champ pour le sélectionner.</div>

          <div class="panel-sep" />

          <div class="panel-list">
            <div
              v-for="f in fields"
              :key="f.id"
              :class="['pli', { 'pli-sel': selectedField && selectedField.id === f.id, 'pli-pending': pendingFields.has(f.id), 'pli-inactive': !f.is_active }]"
              @click="selectedField = f"
            >
              <span :class="['pli-dot', 'dot-' + f.field_type]" />
              <span class="pli-label">{{ f.label }}</span>
              <span v-if="pendingFields.has(f.id)" class="pli-star">✦</span>
            </div>
          </div>
        </div>
      </div>

      <!-- MODE TABLEAU -->
      <UCard v-else>
        <div class="cerfa-toolbar">
          <UInput v-model="search" placeholder="Filtrer par label ou field_key…" class="cerfa-search" />
          <span class="cerfa-count">{{ filteredFields.length }} champs</span>
          <button v-if="pendingFields.size > 0" class="btn-save-all" :disabled="savingAll" @click="saveAll">
            Tout enregistrer ({{ pendingFields.size }})
          </button>
        </div>
        <div class="cerfa-table-wrap">
          <table class="cerfa-table">
            <thead>
              <tr>
                <th>Champ</th><th>Type</th><th>X (mm)</th><th>Y (mm)</th><th>Largeur</th><th>Police</th><th>Actif</th><th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="field in filteredFields" :key="field.id" :class="{ 'row-inactive': !field.is_active }">
                <td>
                  <div class="field-label">{{ field.label }}</div>
                  <div class="field-key">{{ field.field_key }}</div>
                </td>
                <td><span :class="['type-badge', 'type-' + field.field_type]">{{ field.field_type }}</span></td>
                <td><input type="number" step="0.1" class="coord-input" :value="field.x" @change="queueUpdate(field, 'x', ($event.target as HTMLInputElement).value)" /></td>
                <td><input type="number" step="0.1" class="coord-input" :value="field.y" @change="queueUpdate(field, 'y', ($event.target as HTMLInputElement).value)" /></td>
                <td><input type="number" step="0.5" class="coord-input" :value="field.width" @change="queueUpdate(field, 'width', ($event.target as HTMLInputElement).value)" /></td>
                <td><input type="number" step="0.5" min="5" max="14" class="coord-input coord-input--sm" :value="field.font_size" @change="queueUpdate(field, 'font_size', ($event.target as HTMLInputElement).value)" /></td>
                <td><input type="checkbox" :checked="field.is_active" @change="queueUpdate(field, 'is_active', ($event.target as HTMLInputElement).checked)" /></td>
                <td class="actions-cell">
                  <button v-if="pendingFields.has(field.id)" class="btn-save" :disabled="savingId === field.id" @click="saveField(field)">
                    {{ savingId === field.id ? '…' : 'Enregistrer' }}
                  </button>
                  <button class="btn-reset" :disabled="savingId === field.id" @click="resetField(field)">↺</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </UCard>

    </template>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ title: 'Configuration CERFA' })

const api = useApi()
const toast = useToast()

interface CerfaField {
  id: number
  cerfa_ref: string
  field_key: string
  label: string
  x: string
  y: string
  width: string
  font_size: string
  field_type: string
  description: string | null
  is_active: boolean
}

// ─── State ───────────────────────────────────────────────────────────────────
const cerfaRefs     = ref<Record<string, string>>({})
const fields        = ref<CerfaField[]>([])
const selectedRef   = ref('')
const search        = ref('')
const loading       = ref(false)
const mode          = ref<'visual' | 'table'>('visual')
const pendingFields = ref<Map<number, Record<string, any>>>(new Map())
const savingId      = ref<number | null>(null)
const savingAll     = ref(false)

// Visual editor
const canvasWrapRef = ref<HTMLElement | null>(null)
const selectedField = ref<CerfaField | null>(null)
const activePage    = ref(0)
const dragging      = ref<CerfaField | null>(null)
const dragOrigin    = ref({ canvasPx: { x: 0, y: 0 }, fieldMm: { x: 0, y: 0 } })
const canvasRenderW = ref(680)
const cursorInfo    = ref<{ x: string; y: string; left: number; top: number } | null>(null)

const A4_W = 210
const A4_H = 297

const BG_PAGES: Record<string, string[]> = {
  cerfa_13751: ['/cerfa13751-1.png'],
  cerfa_13757: ['/cerfa13757-1.png'],
  cerfa_15776: ['/cerfa15776-1.png', '/cerfa15776-2.png'],
}

const bgPages = computed(() => BG_PAGES[selectedRef.value] ?? [])
const scale   = computed(() => canvasRenderW.value / A4_W)

// ─── Données ─────────────────────────────────────────────────────────────────
async function loadAll() {
  loading.value = true
  try {
    const res = await api.get('/admin/cerfa-config')
    cerfaRefs.value = res.cerfa_refs ?? {}
    if (!selectedRef.value && Object.keys(cerfaRefs.value).length) {
      selectedRef.value = Object.keys(cerfaRefs.value)[0]
    }
    fields.value = (res.items ?? []).filter((f: CerfaField) => f.cerfa_ref === selectedRef.value)
  } catch (e: any) {
    toast.add({ title: 'Erreur chargement', description: e?.message, color: 'error' })
  } finally {
    loading.value = false
  }
}

async function selectRef(ref: string) {
  if (ref === selectedRef.value) return
  selectedRef.value  = ref
  activePage.value   = 0
  selectedField.value = null
  pendingFields.value = new Map()
  loading.value = true
  try {
    const res = await api.get('/admin/cerfa-config?cerfa_ref=' + ref)
    fields.value = res.items ?? []
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e?.message, color: 'error' })
  } finally {
    loading.value = false
  }
}

// ─── Pending / Save / Reset ───────────────────────────────────────────────────
function queueUpdate(field: CerfaField, key: string, value: any) {
  const patch = pendingFields.value.get(field.id) ?? {}
  patch[key] = value
  pendingFields.value.set(field.id, patch)
  ;(field as any)[key] = value
}

function onPanelChange(field: CerfaField, key: string, value?: any) {
  queueUpdate(field, key, value !== undefined ? value : (field as any)[key])
}

async function saveField(field: CerfaField) {
  const patch = pendingFields.value.get(field.id)
  if (!patch) return
  savingId.value = field.id
  try {
    const updated = await api.patch('/admin/cerfa-config/' + field.id, patch)
    Object.assign(field, updated)
    pendingFields.value.delete(field.id)
    if (selectedField.value && selectedField.value.id === field.id) Object.assign(selectedField.value, updated)
    toast.add({ title: 'Enregistré', color: 'success' })
  } catch (e: any) {
    toast.add({ title: 'Erreur sauvegarde', description: e?.message, color: 'error' })
  } finally {
    savingId.value = null
  }
}

async function saveAll() {
  savingAll.value = true
  let ok = 0
  for (const [id, patch] of [...pendingFields.value]) {
    const field = fields.value.find(f => f.id === id)
    if (!field) continue
    try {
      const updated = await api.patch('/admin/cerfa-config/' + id, patch)
      Object.assign(field, updated)
      pendingFields.value.delete(id)
      ok++
    } catch {}
  }
  savingAll.value = false
  toast.add({ title: ok + ' champ' + (ok > 1 ? 's' : '') + ' enregistré' + (ok > 1 ? 's' : ''), color: ok > 0 ? 'success' : 'warning' })
}

async function resetField(field: CerfaField) {
  savingId.value = field.id
  try {
    const updated = await api.post('/admin/cerfa-config/' + field.id + '/reset', {})
    Object.assign(field, updated)
    pendingFields.value.delete(field.id)
    if (selectedField.value && selectedField.value.id === field.id) Object.assign(selectedField.value, updated)
    toast.add({ title: 'Réinitialisé', color: 'success' })
  } catch (e: any) {
    toast.add({ title: 'Erreur reset', description: e?.message, color: 'error' })
  } finally {
    savingId.value = null
  }
}

// ─── Overlay style ────────────────────────────────────────────────────────────
function overlayStyle(field: CerfaField) {
  const s  = scale.value
  const x  = parseFloat(field.x)
  const y  = parseFloat(field.y)
  const w  = parseFloat(field.width)
  const fs = parseFloat(field.font_size)
  const h  = Math.max(fs * 0.42, 3.5)
  return {
    left:   (x * s) + 'px',
    top:    (y * s) + 'px',
    width:  (w * s) + 'px',
    height: (h * s) + 'px',
  }
}

// ─── Drag & Drop ──────────────────────────────────────────────────────────────
function startDrag(e: MouseEvent, field: CerfaField) {
  selectedField.value = field
  dragging.value = field
  const rect = canvasWrapRef.value!.getBoundingClientRect()
  canvasRenderW.value = rect.width
  dragOrigin.value = {
    canvasPx: { x: e.clientX - rect.left, y: e.clientY - rect.top },
    fieldMm:  { x: parseFloat(field.x),   y: parseFloat(field.y)   },
  }
}

function onMouseMove(e: MouseEvent) {
  if (!canvasWrapRef.value) return
  const rect = canvasWrapRef.value.getBoundingClientRect()
  const cx = e.clientX - rect.left
  const cy = e.clientY - rect.top
  const s  = rect.width / A4_W

  cursorInfo.value = {
    x:    (cx / s).toFixed(1),
    y:    (cy / s).toFixed(1),
    left: Math.min(cx + 10, rect.width - 130),
    top:  Math.max(cy - 26, 4),
  }

  if (!dragging.value) return

  const newX = Math.max(0, Math.min(A4_W - 1, dragOrigin.value.fieldMm.x + (cx - dragOrigin.value.canvasPx.x) / s))
  const newY = Math.max(0, Math.min(A4_H - 1, dragOrigin.value.fieldMm.y + (cy - dragOrigin.value.canvasPx.y) / s))
  dragging.value.x = newX.toFixed(1)
  dragging.value.y = newY.toFixed(1)
}

function onCanvasLeave() {
  cursorInfo.value = null
}

function onDocMouseUp() {
  if (!dragging.value) return
  const field = dragging.value
  dragging.value = null
  const patch = pendingFields.value.get(field.id) ?? {}
  patch.x = field.x
  patch.y = field.y
  pendingFields.value.set(field.id, patch)
}

function onImgLoad() {
  if (canvasWrapRef.value) {
    canvasRenderW.value = canvasWrapRef.value.getBoundingClientRect().width || 680
  }
}

const filteredFields = computed(() => {
  if (!search.value) return fields.value
  const q = search.value.toLowerCase()
  return fields.value.filter(f =>
    f.label.toLowerCase().includes(q) || f.field_key.toLowerCase().includes(q)
  )
})

onMounted(() => {
  loadAll()
  document.addEventListener('mouseup', onDocMouseUp)
})
onUnmounted(() => {
  document.removeEventListener('mouseup', onDocMouseUp)
})
</script>

<style scoped>
.cerfa-config-page { padding-bottom: 40px; }

.top-bar {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.cerfa-tabs {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  flex: 1;
}

.cerfa-tab {
  padding: 5px 13px;
  border-radius: 6px;
  border: 1px solid #374151;
  background: #111827;
  color: #9ca3af;
  font-size: 12px;
  cursor: pointer;
  transition: all 0.15s;
  white-space: nowrap;
}
.cerfa-tab:hover { background: #1f2937; color: #e5e7eb; }
.cerfa-tab.active { background: #1d4ed8; border-color: #1d4ed8; color: #fff; font-weight: 600; }

.mode-toggle {
  display: flex;
  border: 1px solid #374151;
  border-radius: 6px;
  overflow: hidden;
}
.mode-btn {
  padding: 5px 14px;
  font-size: 12px;
  background: #111827;
  color: #6b7280;
  border: none;
  cursor: pointer;
  transition: all 0.15s;
}
.mode-btn:hover { background: #1f2937; color: #e5e7eb; }
.mode-btn.active { background: #1d4ed8; color: #fff; font-weight: 600; }

.cerfa-loading {
  text-align: center; color: #6b7280; padding: 60px; font-size: 13px;
}

/* ─── Visual layout ──────────────────────────────────────────────────────── */
.visual-layout { display: flex; gap: 16px; align-items: flex-start; }

.visual-main { flex: 1 1 0; min-width: 0; }

.visual-toolbar {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 8px;
  flex-wrap: wrap;
}

.page-selector { display: flex; gap: 4px; }
.page-btn {
  padding: 3px 10px; font-size: 11px;
  border-radius: 4px; border: 1px solid #374151;
  background: #1f2937; color: #9ca3af; cursor: pointer;
}
.page-btn.active { background: #1d4ed8; border-color: #1d4ed8; color: #fff; }

.hint-text { font-size: 11px; color: #4b5563; flex: 1; }

.pending-badge {
  font-size: 11px; background: #92400e; color: #fbbf24;
  padding: 2px 8px; border-radius: 10px; font-weight: 600;
}

.btn-save-all {
  background: #1d4ed8; color: #fff; border: none;
  border-radius: 5px; padding: 4px 14px; font-size: 12px;
  cursor: pointer; font-weight: 600;
}
.btn-save-all:hover:not(:disabled) { background: #1e40af; }
.btn-save-all:disabled { opacity: 0.5; cursor: default; }

/* ─── Canvas ─────────────────────────────────────────────────────────────── */
.cerfa-canvas-wrap {
  position: relative;
  display: block;
  border: 1px solid #374151;
  border-radius: 6px;
  overflow: hidden;
  cursor: crosshair;
  user-select: none;
}
.cerfa-canvas-wrap.canvas-dragging { cursor: grabbing; }

.cerfa-bg {
  width: 100%; height: auto; display: block; pointer-events: none;
}

/* ─── Field overlays ─────────────────────────────────────────────────────── */
.fov {
  position: absolute;
  border: 1px solid transparent;
  border-radius: 2px;
  cursor: grab;
  display: flex;
  align-items: center;
  overflow: hidden;
  transition: border-color 0.1s, background-color 0.1s;
}
.fov:hover { z-index: 10; border-color: rgba(255,255,255,0.5) !important; }
.fov:active { cursor: grabbing; }

.fov-label {
  font-size: 8px; font-weight: 600; padding: 0 2px;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  pointer-events: none; line-height: 1;
}

.fov-text     { background: rgba(30,64,175,0.28);  border-color: rgba(96,165,250,0.5); }
.fov-text     .fov-label { color: #93c5fd; }
.fov-boxed    { background: rgba(20,83,45,0.28);   border-color: rgba(74,222,128,0.5); }
.fov-boxed    .fov-label { color: #86efac; }
.fov-date     { background: rgba(120,53,15,0.28);  border-color: rgba(251,191,36,0.5); }
.fov-date     .fov-label { color: #fcd34d; }
.fov-checkbox { background: rgba(76,29,149,0.28);  border-color: rgba(196,181,253,0.5); }
.fov-checkbox .fov-label { color: #c4b5fd; }

.fov-sel {
  z-index: 20;
  border-width: 2px !important;
  border-color: #3b82f6 !important;
  background: rgba(59,130,246,0.25) !important;
  box-shadow: 0 0 0 1px #3b82f6;
}
.fov-sel .fov-label { color: #fff; }

.fov-pending { border-style: dashed; }

.fov-drag {
  z-index: 30; opacity: 0.85;
  box-shadow: 0 2px 12px rgba(0,0,0,0.5);
}

.fov-inactive { opacity: 0.18; pointer-events: none; }

/* ─── Badges ─────────────────────────────────────────────────────────────── */
.cursor-badge {
  position: absolute; background: rgba(0,0,0,0.75); color: #d1d5db;
  font-size: 10px; padding: 2px 7px; border-radius: 4px;
  pointer-events: none; white-space: nowrap; z-index: 50;
}

.drag-badge {
  position: absolute; top: 8px; left: 50%; transform: translateX(-50%);
  background: rgba(29,78,216,0.9); color: #fff;
  font-size: 11px; font-weight: 600; padding: 3px 12px;
  border-radius: 12px; pointer-events: none; z-index: 50; white-space: nowrap;
}

/* ─── Panneau latéral ────────────────────────────────────────────────────── */
.visual-panel {
  width: 272px; min-width: 272px; flex-shrink: 0;
  position: sticky; top: 72px;
  max-height: calc(100vh - 90px); overflow-y: auto;
  background: #0f172a;
  border: 1px solid #1f2937;
  border-radius: 8px;
}

.panel-header {
  padding: 14px 14px 10px;
  border-bottom: 1px solid #1f2937;
}
.panel-title { font-size: 13px; font-weight: 600; color: #e5e7eb; }
.panel-key   { font-family: monospace; font-size: 10px; color: #6b7280; margin-top: 2px; }

.panel-fields { padding: 12px 14px; display: flex; flex-direction: column; gap: 9px; }

.pf-row {
  display: flex; align-items: center; justify-content: space-between; gap: 8px;
}
.pf-row label { font-size: 11px; color: #9ca3af; white-space: nowrap; }
.pf-row--check { margin-top: 2px; }

.panel-input {
  width: 86px; background: #1f2937; border: 1px solid #374151;
  border-radius: 4px; color: #e5e7eb; padding: 3px 7px;
  font-size: 12px; text-align: right;
}
.panel-input:focus { outline: none; border-color: #1d4ed8; }

.panel-unsaved {
  margin: 0 14px 8px;
  font-size: 10px; color: #f59e0b;
  background: rgba(245,158,11,0.1); padding: 3px 8px;
  border-radius: 4px; border: 1px solid rgba(245,158,11,0.3);
}

.panel-actions {
  display: flex; gap: 8px; padding: 0 14px 14px;
}

.panel-empty {
  padding: 24px 14px; font-size: 12px; color: #4b5563;
  text-align: center; line-height: 1.6;
}

.panel-sep { height: 1px; background: #1f2937; }

.panel-list { padding: 6px 0; }

.pli {
  display: flex; align-items: center; gap: 7px;
  padding: 4px 14px; cursor: pointer;
  font-size: 11px; color: #9ca3af; transition: background 0.1s;
}
.pli:hover { background: #111827; color: #e5e7eb; }
.pli-sel { background: #1e3a5f !important; color: #93c5fd !important; }
.pli-pending .pli-label { font-style: italic; }
.pli-inactive { opacity: 0.35; }

.pli-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
.dot-text     { background: #60a5fa; }
.dot-boxed    { background: #4ade80; }
.dot-date     { background: #fbbf24; }
.dot-checkbox { background: #a78bfa; }

.pli-label { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pli-star  { color: #f59e0b; font-size: 9px; }

/* ─── Mode tableau ──────────────────────────────────────────────────────── */
.cerfa-toolbar {
  display: flex; align-items: center; gap: 12px;
  margin-bottom: 16px; flex-wrap: wrap;
}
.cerfa-search { flex: 1; max-width: 320px; }
.cerfa-count  { font-size: 12px; color: #6b7280; }

.cerfa-table-wrap { overflow-x: auto; }

.cerfa-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.cerfa-table th {
  text-align: left; padding: 6px 10px; color: #6b7280;
  font-weight: 600; font-size: 11px; text-transform: uppercase;
  border-bottom: 1px solid #1f2937;
}
.cerfa-table td { padding: 6px 10px; border-bottom: 1px solid #111827; vertical-align: middle; }
.cerfa-table tr:hover td { background: #0f172a; }
.row-inactive td { opacity: 0.4; }

.field-label { font-weight: 500; color: #e5e7eb; }
.field-key   { font-family: monospace; font-size: 10px; color: #6b7280; margin-top: 2px; }

.type-badge {
  padding: 2px 6px; border-radius: 4px;
  font-size: 10px; font-weight: 600; text-transform: uppercase;
}
.type-text     { background: #1f2937; color: #9ca3af; }
.type-boxed    { background: #1e3a5f; color: #60a5fa; }
.type-date     { background: #14532d; color: #4ade80; }
.type-checkbox { background: #4c1d95; color: #c4b5fd; }

.coord-input {
  width: 68px; background: #1f2937; border: 1px solid #374151;
  border-radius: 4px; color: #e5e7eb; padding: 3px 6px;
  font-size: 12px; text-align: right;
}
.coord-input--sm { width: 50px; }
.coord-input:focus { outline: none; border-color: #1d4ed8; }

.actions-cell { display: flex; gap: 6px; align-items: center; }

.btn-save {
  background: #1d4ed8; color: #fff; border: none;
  border-radius: 5px; padding: 3px 10px; font-size: 11px; cursor: pointer;
}
.btn-save:hover:not(:disabled) { background: #1e40af; }
.btn-save:disabled { opacity: 0.5; cursor: default; }

.btn-reset {
  background: none; border: 1px solid #374151; color: #9ca3af;
  border-radius: 5px; padding: 3px 8px; font-size: 12px; cursor: pointer;
}
.btn-reset:hover:not(:disabled) { border-color: #6b7280; color: #e5e7eb; }
.btn-reset:disabled { opacity: 0.4; cursor: default; }
</style>
