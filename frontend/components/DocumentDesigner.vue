<template>
  <div class="designer-root">
    <!-- Toolbar -->
    <div class="designer-toolbar">
      <div class="toolbar-group">
        <label class="toolbar-label">
          <input v-model="showGrid" type="checkbox" />
          Grille
        </label>
        <label class="toolbar-label">
          <input v-model="snapToGrid" type="checkbox" />
          Magnet
        </label>
        <label class="toolbar-label">
          <input v-model="previewMode" type="checkbox" />
          Aperçu données
        </label>
      </div>
      <div class="toolbar-group">
        <button class="btn btn-ghost" style="font-size:12px;" @click="undo" :disabled="!canUndo">↩ Annuler</button>
        <button class="btn btn-ghost" style="font-size:12px;" @click="redo" :disabled="!canRedo">↪ Rétablir</button>
        <button class="btn btn-primary" style="font-size:12px;" @click="$emit('save', layoutJson)">💾 Enregistrer</button>
        <button class="btn btn-ghost" style="font-size:12px;" @click="previewPdf">👁️ PDF</button>
      </div>
    </div>

    <div class="designer-body">
      <!-- Left sidebar: tools -->
      <div class="designer-sidebar left">
        <div class="sidebar-title">Outils</div>
        <div class="tool-list">
          <button
            v-for="tool in tools"
            :key="tool.type"
            class="tool-btn"
            :class="{ active: selectedTool === tool.type }"
            @click="addElement(tool.type)"
          >
            <span class="tool-icon">{{ tool.icon }}</span>
            <span class="tool-label">{{ tool.label }}</span>
          </button>
        </div>
        <div class="sidebar-title" style="margin-top:16px;">Actions</div>
        <button class="tool-btn" style="color:#FCA5A5;" @click="deleteSelected" :disabled="!selectedId">
          <span class="tool-icon">🗑</span>
          <span class="tool-label">Supprimer</span>
        </button>
        <button class="tool-btn" @click="clearAll">
          <span class="tool-icon">✕</span>
          <span class="tool-label">Tout effacer</span>
        </button>
        <button class="tool-btn" @click="resetToDefault">
          <span class="tool-icon">↺</span>
          <span class="tool-label">Réinitialiser</span>
        </button>
      </div>

      <!-- Center: A4 canvas -->
      <div class="designer-canvas-wrapper" @click.self="selectedId = null">
        <div
          ref="a4Ref"
          class="designer-a4"
          :style="a4Style"
          @click.self="selectedId = null"
        >
          <!-- Grid -->
          <div v-if="showGrid" class="a4-grid" :style="gridStyle" />

          <!-- Elements -->
          <div
            v-for="el in elements"
            :key="el.id"
            class="designer-element"
            :class="{ selected: selectedId === el.id, preview: previewMode }"
            :style="elementStyle(el)"
            @mousedown.stop="startDrag($event, el)"
            @click.stop="selectElement(el)"
          >
            <!-- Content -->
            <div class="el-content" :style="contentStyle(el)">
              <template v-if="el.type === 'image'">
                <img v-if="el.content" :src="el.content" style="width:100%;height:100%;object-fit:contain;" />
                <span v-else style="color:#9CA3AF;font-size:10px;">[Image]</span>
              </template>
              <template v-else-if="el.type === 'line'">
                <div style="width:100%;height:0;border-top:1px solid currentColor;" />
              </template>
              <template v-else-if="el.type === 'rect'">
                <div style="width:100%;height:100%;border:1px solid currentColor;" />
              </template>
              <template v-else>
                {{ previewMode ? replaceVariables(el.content) : el.content }}
              </template>
            </div>

            <!-- Resize handles -->
            <div
              v-if="selectedId === el.id"
              class="resize-handle se"
              @mousedown.stop="startResize($event, el)"
            />
          </div>
        </div>
      </div>

      <!-- Right sidebar: properties + variables -->
      <div class="designer-sidebar right">
        <div v-if="selectedElement" class="props-panel">
          <div class="sidebar-title">Propriétés</div>

          <div class="prop-row">
            <label>Texte</label>
            <UTextarea v-model="selectedElement.content" :rows="3" size="xs" />
          </div>

          <div class="prop-row" style="display:flex;gap:8px;">
            <div style="flex:1;">
              <label>X (mm)</label>
              <UInput v-model.number="selectedElement.x" type="number" size="xs" />
            </div>
            <div style="flex:1;">
              <label>Y (mm)</label>
              <UInput v-model.number="selectedElement.y" type="number" size="xs" />
            </div>
          </div>

          <div class="prop-row" style="display:flex;gap:8px;">
            <div style="flex:1;">
              <label>Larg. (mm)</label>
              <UInput v-model.number="selectedElement.w" type="number" size="xs" />
            </div>
            <div style="flex:1;">
              <label>Haut. (mm)</label>
              <UInput v-model.number="selectedElement.h" type="number" size="xs" />
            </div>
          </div>

          <div class="prop-row" style="display:flex;gap:8px;">
            <div style="flex:1;">
              <label>Taille</label>
              <UInput v-model.number="selectedElement.style.fontSize" type="number" size="xs" />
            </div>
            <div style="flex:1;">
              <label>Couleur</label>
              <input v-model="selectedElement.style.color" type="color" style="width:100%;height:28px;border-radius:6px;border:none;" />
            </div>
          </div>

          <div class="prop-row" style="display:flex;gap:12px;">
            <label class="toggle-label">
              <input v-model="selectedElement.style.bold" type="checkbox" />
              Gras
            </label>
            <label class="toggle-label">
              <input v-model="selectedElement.style.italic" type="checkbox" />
              Italique
            </label>
          </div>

          <div class="prop-row">
            <label>Alignement</label>
            <div style="display:flex;gap:4px;">
              <button
                v-for="a in ['left','center','right']"
                :key="a"
                class="align-btn"
                :class="{ active: selectedElement.style.align === a }"
                @click="selectedElement.style.align = a"
              >
                {{ a === 'left' ? '⬅' : a === 'center' ? '↔' : '➡' }}
              </button>
            </div>
          </div>
        </div>

        <div v-else class="props-panel">
          <div class="sidebar-title">Propriétés</div>
          <p style="color:#6B7280;font-size:12px;">Sélectionnez un élément pour éditer ses propriétés.</p>
        </div>

        <div class="vars-panel">
          <div class="sidebar-title">Variables</div>
          <p style="color:#6B7280;font-size:11px;margin-bottom:8px;">Cliquez pour copier, puis collez dans le texte.</p>
          <div class="var-list">
            <button
              v-for="v in availableVariables"
              :key="v.key"
              class="var-chip"
              @click="copyVariable(v.key)"
            >
              {{ v.label }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'

export interface LayoutElement {
  id: string
  type: 'text' | 'variable' | 'image' | 'line' | 'rect'
  x: number
  y: number
  w: number
  h: number
  content: string
  style: {
    fontSize?: number
    bold?: boolean
    italic?: boolean
    color?: string
    align?: 'left' | 'center' | 'right'
    backgroundColor?: string
  }
}

interface LayoutJson {
  elements: LayoutElement[]
}

const props = defineProps<{
  modelValue: LayoutJson
  code: string
  sampleData?: Record<string, string>
  defaultLayout?: LayoutJson
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: LayoutJson): void
  (e: 'save', v: LayoutJson): void
  (e: 'preview-pdf', v: LayoutJson): void
}>()

// ========== STATE ==========
const elements = ref<LayoutElement[]>(JSON.parse(JSON.stringify(props.modelValue.elements ?? [])))
const selectedId = ref<string | null>(null)
const selectedTool = ref<string | null>(null)
const showGrid = ref(true)
const snapToGrid = ref(true)
const previewMode = ref(false)
const a4Ref = ref<HTMLElement | null>(null)

const SCALE = 3.81 // px per mm (800px / 210mm)
const GRID = 5 // mm

// History for undo/redo
const history = ref<LayoutElement[][]>([])
const historyIndex = ref(-1)

function pushHistory() {
  // Remove future history if we were not at the end
  if (historyIndex.value < history.value.length - 1) {
    history.value = history.value.slice(0, historyIndex.value + 1)
  }
  history.value.push(JSON.parse(JSON.stringify(elements.value)))
  historyIndex.value++
}

const canUndo = computed(() => historyIndex.value > 0)
const canRedo = computed(() => historyIndex.value < history.value.length - 1)

function undo() {
  if (!canUndo.value) return
  historyIndex.value--
  elements.value = JSON.parse(JSON.stringify(history.value[historyIndex.value]))
}

function redo() {
  if (!canRedo.value) return
  historyIndex.value++
  elements.value = JSON.parse(JSON.stringify(history.value[historyIndex.value]))
}

// Watch external modelValue changes (e.g. loaded from API)
let isUpdatingFromProps = false
watch(() => props.modelValue, (newVal) => {
  if (newVal?.elements && JSON.stringify(newVal.elements) !== JSON.stringify(elements.value)) {
    isUpdatingFromProps = true
    elements.value = JSON.parse(JSON.stringify(newVal.elements))
    nextTick(() => { isUpdatingFromProps = false })
  }
}, { deep: false })

watch(elements, () => {
  if (isUpdatingFromProps) return
  emit('update:modelValue', { elements: elements.value })
}, { deep: true })

// Push initial state
if (history.value.length === 0) {
  history.value.push(JSON.parse(JSON.stringify(elements.value)))
  historyIndex.value = 0
}

// ========== TOOLS ==========
const tools = [
  { type: 'text', label: 'Texte', icon: 'T' },
  { type: 'variable', label: 'Variable', icon: '{x}' },
  { type: 'image', label: 'Image', icon: '🖼' },
  { type: 'line', label: 'Ligne', icon: '—' },
  { type: 'rect', label: 'Rectangle', icon: '□' },
]

function addElement(type: string) {
  selectedTool.value = type
  const id = 'el-' + Date.now()
  const el: LayoutElement = {
    id,
    type: type as any,
    x: 20,
    y: 20,
    w: type === 'line' ? 80 : type === 'rect' ? 40 : 60,
    h: type === 'line' ? 1 : type === 'image' ? 30 : 10,
    content: type === 'text' ? 'Nouveau texte' : type === 'variable' ? '{{variable}}' : type === 'image' ? '' : '',
    style: {
      fontSize: 11,
      color: '#1f2937',
      align: 'left',
    },
  }
  elements.value.push(el)
  selectedId.value = id
  pushHistory()
}

// ========== SELECTION ==========
const selectedElement = computed(() => {
  return elements.value.find(e => e.id === selectedId.value) ?? null
})

function selectElement(el: LayoutElement) {
  selectedId.value = el.id
}

function deleteSelected() {
  if (!selectedId.value) return
  elements.value = elements.value.filter(e => e.id !== selectedId.value)
  selectedId.value = null
  pushHistory()
}

function clearAll() {
  if (!confirm('Vider tous les éléments ?')) return
  elements.value = []
  selectedId.value = null
  pushHistory()
}

function resetToDefault() {
  if (!confirm('Réinitialiser au template par défaut ?')) return
  if (props.defaultLayout) {
    elements.value = JSON.parse(JSON.stringify(props.defaultLayout.elements))
  } else {
    elements.value = []
  }
  selectedId.value = null
  pushHistory()
}

// ========== DRAG ==========
let dragEl: LayoutElement | null = null
let dragStartX = 0
let dragStartY = 0
let elStartX = 0
let elStartY = 0

function startDrag(e: MouseEvent, el: LayoutElement) {
  dragEl = el
  dragStartX = e.clientX
  dragStartY = e.clientY
  elStartX = el.x
  elStartY = el.y
  selectedId.value = el.id
  window.addEventListener('mousemove', onDrag)
  window.addEventListener('mouseup', stopDrag)
}

function onDrag(e: MouseEvent) {
  if (!dragEl) return
  const dxPx = e.clientX - dragStartX
  const dyPx = e.clientY - dragStartY
  const dxMm = dxPx / SCALE
  const dyMm = dyPx / SCALE
  let nx = elStartX + dxMm
  let ny = elStartY + dyMm
  if (snapToGrid.value) {
    nx = Math.round(nx / GRID) * GRID
    ny = Math.round(ny / GRID) * GRID
  }
  dragEl.x = Math.max(0, Math.min(210 - dragEl.w, nx))
  dragEl.y = Math.max(0, Math.min(297 - dragEl.h, ny))
}

function stopDrag() {
  if (dragEl) {
    pushHistory()
  }
  dragEl = null
  window.removeEventListener('mousemove', onDrag)
  window.removeEventListener('mouseup', stopDrag)
}

// ========== RESIZE ==========
let resizeEl: LayoutElement | null = null
let resizeStartX = 0
let resizeStartY = 0
let elStartW = 0
let elStartH = 0

function startResize(e: MouseEvent, el: LayoutElement) {
  resizeEl = el
  resizeStartX = e.clientX
  resizeStartY = e.clientY
  elStartW = el.w
  elStartH = el.h
  window.addEventListener('mousemove', onResize)
  window.addEventListener('mouseup', stopResize)
}

function onResize(e: MouseEvent) {
  if (!resizeEl) return
  const dxPx = e.clientX - resizeStartX
  const dyPx = e.clientY - resizeStartY
  const dxMm = dxPx / SCALE
  const dyMm = dyPx / SCALE
  let nw = elStartW + dxMm
  let nh = elStartH + dyMm
  if (snapToGrid.value) {
    nw = Math.round(nw / GRID) * GRID
    nh = Math.round(nh / GRID) * GRID
  }
  resizeEl.w = Math.max(5, nw)
  resizeEl.h = Math.max(1, nh)
}

function stopResize() {
  if (resizeEl) {
    pushHistory()
  }
  resizeEl = null
  window.removeEventListener('mousemove', onResize)
  window.removeEventListener('mouseup', stopResize)
}

// ========== STYLES ==========
const a4Style = computed(() => ({
  width: `${210 * SCALE}px`,
  height: `${297 * SCALE}px`,
}))

const gridStyle = computed(() => ({
  backgroundImage: `repeating-linear-gradient(0deg, transparent, transparent ${SCALE * GRID - 1}px, rgba(255,255,255,0.04) ${SCALE * GRID - 1}px, rgba(255,255,255,0.04) ${SCALE * GRID}px), repeating-linear-gradient(90deg, transparent, transparent ${SCALE * GRID - 1}px, rgba(255,255,255,0.04) ${SCALE * GRID - 1}px, rgba(255,255,255,0.04) ${SCALE * GRID}px)`,
  backgroundSize: '100% 100%',
}))

function elementStyle(el: LayoutElement) {
  return {
    left: `${el.x * SCALE}px`,
    top: `${el.y * SCALE}px`,
    width: `${el.w * SCALE}px`,
    height: `${el.h * SCALE}px`,
    fontSize: `${(el.style.fontSize ?? 11) * SCALE / 3.81}px`,
    color: el.style.color ?? '#1f2937',
    fontWeight: el.style.bold ? 'bold' : 'normal',
    fontStyle: el.style.italic ? 'italic' : 'normal',
    textAlign: el.style.align ?? 'left',
    backgroundColor: el.style.backgroundColor ?? 'transparent',
  }
}

function contentStyle(el: LayoutElement) {
  return {
    width: '100%',
    height: '100%',
    overflow: 'hidden',
    wordWrap: 'break-word',
    display: 'flex',
    alignItems: el.type === 'line' || el.type === 'rect' ? 'center' : 'flex-start',
    justifyContent: el.style.align === 'center' ? 'center' : el.style.align === 'right' ? 'flex-end' : 'flex-start',
  }
}

// ========== VARIABLES ==========
const variableCatalog: Record<string, { key: string; label: string }[]> = {
  facture: [
    { key: 'numero_facture', label: 'N° facture' },
    { key: 'date_facture', label: 'Date' },
    { key: 'client_nom', label: 'Client nom' },
    { key: 'client_prenom', label: 'Client prénom' },
    { key: 'client_adresse', label: 'Client adresse' },
    { key: 'vehicule_marque', label: 'Marque' },
    { key: 'vehicule_modele', label: 'Modèle' },
    { key: 'vehicule_plaque', label: 'Plaque' },
    { key: 'total_ht', label: 'Total HT' },
    { key: 'total_tva', label: 'Total TVA' },
    { key: 'total_ttc', label: 'Total TTC' },
    { key: 'atelier_nom', label: 'Atelier' },
  ],
  ordre_reparation: [
    { key: 'or_numero', label: 'N° OR' },
    { key: 'date_or', label: 'Date OR' },
    { key: 'client_nom', label: 'Client nom' },
    { key: 'client_prenom', label: 'Client prénom' },
    { key: 'client_telephone', label: 'Téléphone' },
    { key: 'vehicule_marque', label: 'Marque' },
    { key: 'vehicule_modele', label: 'Modèle' },
    { key: 'vehicule_plaque', label: 'Plaque' },
    { key: 'kilometrage', label: 'Kilométrage' },
    { key: 'travaux', label: 'Travaux' },
    { key: 'atelier_nom', label: 'Atelier' },
  ],
  devis: [
    { key: 'numero_devis', label: 'N° devis' },
    { key: 'date_devis', label: 'Date' },
    { key: 'client_nom', label: 'Client nom' },
    { key: 'total_ht', label: 'Total HT' },
    { key: 'total_ttc', label: 'Total TTC' },
    { key: 'atelier_nom', label: 'Atelier' },
  ],
  default: [
    { key: 'client_nom', label: 'Client nom' },
    { key: 'client_prenom', label: 'Client prénom' },
    { key: 'date', label: 'Date' },
    { key: 'atelier_nom', label: 'Atelier' },
  ],
}

const availableVariables = computed(() => {
  return variableCatalog[props.code] ?? variableCatalog.default
})

function copyVariable(key: string) {
  const text = `{{${key}}}`
  navigator.clipboard.writeText(text).catch(() => {})
  if (selectedElement.value) {
    selectedElement.value.content += text
  }
}

function replaceVariables(content: string): string {
  const data = props.sampleData ?? {}
  return content.replace(/\{\{(\w+)\}\}/g, (_, key) => data[key] ?? `{{${key}}}`)
}

function previewPdf() {
  emit('preview-pdf', { elements: elements.value })
}

const layoutJson = computed(() => ({ elements: elements.value }))
</script>

<style scoped>
.designer-root {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 120px);
  gap: 12px;
}
.designer-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 12px;
  background: rgba(255,255,255,0.03);
  border-radius: 10px;
  border: 1px solid rgba(255,255,255,0.06);
}
.toolbar-group {
  display: flex;
  gap: 12px;
  align-items: center;
}
.toolbar-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #D1D5DB;
  cursor: pointer;
}
.designer-body {
  display: flex;
  flex: 1;
  gap: 12px;
  overflow: hidden;
}
.designer-sidebar {
  width: 200px;
  flex-shrink: 0;
  background: rgba(255,255,255,0.02);
  border-radius: 10px;
  border: 1px solid rgba(255,255,255,0.06);
  padding: 12px;
  overflow-y: auto;
}
.designer-sidebar.left {
  width: 160px;
}
.designer-sidebar.right {
  width: 220px;
}
.sidebar-title {
  font-size: 12px;
  font-weight: 700;
  color: #E8E9ED;
  margin-bottom: 10px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.tool-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.tool-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  border-radius: 8px;
  background: transparent;
  border: 1px solid transparent;
  color: #D1D5DB;
  font-size: 12px;
  cursor: pointer;
  transition: all 0.15s;
}
.tool-btn:hover {
  background: rgba(255,255,255,0.06);
  border-color: rgba(255,255,255,0.1);
}
.tool-btn.active {
  background: rgba(251,191,36,0.12);
  border-color: rgba(251,191,36,0.3);
  color: #FCD34D;
}
.tool-icon {
  font-size: 14px;
  width: 20px;
  text-align: center;
}
.designer-canvas-wrapper {
  flex: 1;
  display: flex;
  justify-content: center;
  align-items: flex-start;
  overflow: auto;
  padding: 20px;
  background: rgba(0,0,0,0.2);
  border-radius: 10px;
}
.designer-a4 {
  position: relative;
  background: white;
  box-shadow: 0 4px 24px rgba(0,0,0,0.3);
  flex-shrink: 0;
  overflow: hidden;
}
.a4-grid {
  position: absolute;
  inset: 0;
  pointer-events: none;
  z-index: 1;
}
.designer-element {
  position: absolute;
  z-index: 2;
  cursor: grab;
  border: 1px dashed transparent;
  user-select: none;
}
.designer-element:hover {
  border-color: rgba(251,191,36,0.3);
}
.designer-element.selected {
  border: 1px solid #FBBF24;
  z-index: 10;
}
.designer-element.preview {
  cursor: default;
}
.el-content {
  font-family: DejaVu Sans, sans-serif;
  line-height: 1.3;
  color: #1f2937;
}
.resize-handle {
  position: absolute;
  width: 8px;
  height: 8px;
  background: #FBBF24;
  border-radius: 50%;
  z-index: 20;
  cursor: nwse-resize;
}
.resize-handle.se {
  right: -4px;
  bottom: -4px;
}
.props-panel {
  margin-bottom: 16px;
}
.prop-row {
  margin-bottom: 10px;
}
.prop-row label {
  display: block;
  font-size: 11px;
  color: #9CA3AF;
  margin-bottom: 4px;
}
.toggle-label {
  display: flex !important;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #D1D5DB;
  cursor: pointer;
}
.align-btn {
  flex: 1;
  padding: 4px;
  border-radius: 6px;
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.06);
  color: #D1D5DB;
  font-size: 12px;
  cursor: pointer;
}
.align-btn.active {
  background: rgba(251,191,36,0.2);
  border-color: rgba(251,191,36,0.4);
  color: #FCD34D;
}
.var-list {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}
.var-chip {
  font-size: 10px;
  padding: 3px 8px;
  border-radius: 999px;
  background: rgba(96,165,250,0.12);
  color: #93C5FD;
  border: 1px solid rgba(96,165,250,0.2);
  cursor: pointer;
  white-space: nowrap;
}
.var-chip:hover {
  background: rgba(96,165,250,0.25);
}
</style>
