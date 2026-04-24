<template>
  <div>
    <div class="planning-toolbar" style="margin-bottom: 18px;">
      <div class="planning-toolbar-group">
        <button class="toolbar-btn" @click="prevWeek">◀</button>
        <div style="display:flex;flex-direction:column;gap:2px;min-width:160px;">
          <strong style="color:#f8fafc;font-size:14px;line-height:1.1;">Semaine</strong>
          <span style="font-size:12px;color:#cbd5e1;">{{ formatDateRange }}</span>
        </div>
        <button class="toolbar-btn" @click="nextWeek">▶</button>
      </div>
      <button class="toolbar-btn-today" @click="goToday">Aujourd'hui</button>
    </div>

    <div v-if="!weekDays.length" class="planning-board" style="padding:18px;">
      <div style="font-size:13px;color:#9CA3AF;">Aucun jour d'ouverture n'est configuré pour l'atelier.</div>
    </div>

    <div v-else class="planning-board" style="position:relative;">
      <div style="overflow-x:auto;">
        <div :style="boardStyle">
          <div style="padding:10px;border-bottom:1px solid rgba(255,255,255,0.06);background:rgba(255,255,255,0.02);"></div>
          <div
            v-for="day in weekDays"
            :key="'h-' + day.date"
            :class="['planning-day-header', day.isToday ? 'is-today' : '']"
            style="border-bottom:1px solid rgba(255,255,255,0.06);border-left:1px solid rgba(255,255,255,0.04);"
          >
            <div class="planning-day-name">{{ day.label }}</div>
            <div class="planning-day-date">{{ day.dateNum }}</div>
            <div v-if="day.isToday" class="planning-today-label">AUJOURD'HUI</div>
            <div style="font-size:10px;color:#6B7280;margin-top:2px;">{{ countRdvsForDay(day.date) }} RDV</div>
          </div>

          <template v-for="slot in timeSlots" :key="slot.label">
            <div :style="timeLabelStyle">
              {{ slot.label }}
            </div>

            <div
              v-for="day in weekDays"
              :key="`${slot.label}-${day.date}`"
              :class="[
                'planning-cell',
                {
                  'is-clickable': canCreate && isSlotOpen(day.date, slot.minutes),
                  'is-unavailable': !isSlotOpen(day.date, slot.minutes),
                  'is-drop-target': isDropTarget(day.date, slot.minutes),
                },
              ]"
              :style="cellStyle(day, slot.minutes)"
              @click="handleCellClick(day.date, slot.minutes)"
              @dragover.prevent="handleDragOver(day.date, slot.minutes)"
              @drop.prevent="handleDrop(day.date, slot.minutes)"
            >
              <div
                v-if="isPauseSlot(day.date, slot.minutes)"
                style="position:absolute;inset:0;display:flex;align-items:center;justify-content:flex-end;padding-right:6px;font-size:9px;color:#6B7280;pointer-events:none;"
              >
                pause
              </div>

              <div
                v-for="rdv in getRdvsStartingAt(day.date, slot.minutes)"
                :key="rdv.id"
                :class="['rdv-block', rdvStatusClass(rdv.status), rdvTypeClass(rdv.type_intervention), { 'is-draggable': canDragRdv(rdv) }]"
                :draggable="canDragRdv(rdv)"
                :style="rdvStyle(rdv)"
                @click.stop="$emit('select-rdv', rdv)"
                @dragstart="onDragStart($event, rdv)"
                @dragend="onDragEnd"
              >
                <div class="rdv-block-inner">
                  <div style="font-size:10px;font-weight:800;letter-spacing:.05em;opacity:.9;">
                    {{ rdv.heure_debut?.slice(0, 5) }}
                  </div>
                  <div class="rdv-block-title">
                    {{ rdv.type_intervention }}
                  </div>
                  <div class="rdv-block-client">
                    {{ rdv.client_nom }}
                  </div>
                  <div v-if="rdv.source === 'web'" class="rdv-block-web">WEB</div>
                  <div v-if="rdv.priorite" class="rdv-block-priority">
                    <span class="priority-badge" :class="`priority-badge-${rdv.priorite}`">{{ rdv.priorite }}</span>
                  </div>
                  <div v-if="mecanicienFor(rdv)" class="rdv-block-meca">
                    <span
                      class="meca-avatar"
                      :style="{ background: mecanicienFor(rdv)?.couleur || '#8B5CF6' }"
                    >{{ mecanicienFor(rdv)?.initials }}</span>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </div>

        <div
          v-if="nowLineTop > 0"
          style="position:absolute;left:60px;right:0;z-index:10;pointer-events:none;"
          :style="{ top: nowLineTop + 'px' }"
        >
          <div style="display:flex;align-items:center;">
            <div style="width:8px;height:8px;border-radius:50%;background:#EF4444;"></div>
            <div style="flex:1;height:2px;background:#EF4444;opacity:0.7;"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { CSSProperties, StyleValue } from 'vue'
const props = withDefaults(defineProps<{
  ponts?: Array<{ id: number; nom: string }>
  rdvs?: Array<any>
  horaires?: Array<any>
  mecaniciens?: Array<any>
  canCreate?: boolean
  canDrag?: boolean
  historicalStatuses?: string[]
}>(), {
  ponts: () => [],
  rdvs: () => [],
  horaires: () => [],
  mecaniciens: () => [],
  canCreate: false,
  canDrag: false,
  historicalStatuses: () => ['termine', 'restitue', 'facture', 'paye', 'annule'],
})

const emit = defineEmits<{
  'select-rdv': [rdv: any]
  'move-rdv': [payload: { id: number; date: string; time: string }]
  'create-at': [payload: { date: string; time: string }]
  'dates-changed': [range: { start: string; end: string }]
}>()

const currentDate = ref(new Date())
const draggingRdvId = ref<number | null>(null)
const dropTarget = ref<{ date: string; minutes: number } | null>(null)

const HEADER_HEIGHT = 72
const TIME_STEP_MINUTES = 15
const ROW_HEIGHT = 24
const DEFAULT_START_HOUR = 10
const DEFAULT_END_HOUR = 19
const DAY_LABELS = ['LUN', 'MAR', 'MER', 'JEU', 'VEN', 'SAM', 'DIM']
const MONTH_LABELS = ['jan', 'fév', 'mar', 'avr', 'mai', 'jun', 'jul', 'aoû', 'sep', 'oct', 'nov', 'déc']

const weekStart = computed(() => {
  const d = new Date(currentDate.value)
  const day = d.getDay()
  const diff = d.getDate() - day + (day === 0 ? -6 : 1)
  return new Date(d.setDate(diff))
})

watch(
  weekStart,
  (newStart) => {
    const startStr = newStart.toISOString().slice(0, 10)
    const end = new Date(newStart)
    end.setDate(end.getDate() + 6)
    const endStr = end.toISOString().slice(0, 10)
    emit('dates-changed', { start: startStr, end: endStr })
  },
  { immediate: true },
)

const horaireMap = computed(() => {
  const map = new Map<number, any>()
  for (const raw of props.horaires || []) {
    const idx = Number(raw.jour_semaine ?? raw.jourSemaine)
    if (Number.isFinite(idx)) map.set(idx, raw)
  }
  return map
})

const openDayIndexes = computed(() => {
  if (!horaireMap.value.size) return [1, 2, 3, 4, 5]
  return (Array.from(horaireMap.value.entries()) as Array<[number, any]>)
    .filter(([, horaire]: [number, any]) => Number(horaire.is_ouvert ?? horaire.isOuvert ?? 1) === 1)
    .map(([idx]: [number, any]) => idx)
    .sort((a: number, b: number) => a - b)
})

const rdvDayIndexesInWeek = computed(() => {
  const start = new Date(weekStart.value)
  const end = new Date(weekStart.value)
  end.setDate(end.getDate() + 6)

  const indexes = new Set<number>()
  for (const rdv of props.rdvs || []) {
    const rawDate = String(rdv?.date_rdv || '')
    if (!rawDate) continue
    const date = new Date(`${rawDate}T00:00:00`)
    if (Number.isNaN(date.getTime()) || date < start || date > end) continue
    const jsDay = date.getDay()
    indexes.add(jsDay === 0 ? 6 : jsDay - 1)
  }

  return Array.from(indexes).sort((a, b) => a - b)
})

const displayedDayIndexes = computed(() => {
  const merged = new Set<number>([...openDayIndexes.value, ...rdvDayIndexesInWeek.value])
  return Array.from(merged).sort((a, b) => a - b)
})

const weekDays = computed(() => {
  const today = new Date().toISOString().slice(0, 10)
  return displayedDayIndexes.value.map((index: number) => {
    const d = new Date(weekStart.value)
    d.setDate(d.getDate() + index)
    const date = d.toISOString().slice(0, 10)
    return {
      label: DAY_LABELS[index],
      date,
      dateNum: d.getDate(),
      month: MONTH_LABELS[d.getMonth()],
      isToday: date === today,
    }
  })
})

const visibleWeekRdvRanges = computed(() => {
  const visibleDates = new Set(weekDays.value.map((day: any) => day.date))
  return (props.rdvs || [])
    .filter((rdv: any) => visibleDates.has(String(rdv?.date_rdv || '')))
    .map((rdv: any) => {
      const start = timeToMinutes(rdv?.heure_debut)
      const duration = Number(rdv?.duree_estimee || rdv?.temps_estime || 60)
      return { start, end: start + Math.max(15, duration) }
    })
    .filter((range: any) => Number.isFinite(range.start) && Number.isFinite(range.end))
})

const startMinutes = computed(() => {
  const values = (Array.from(horaireMap.value.values()) as any[])
    .filter((horaire: any) => Number(horaire.is_ouvert ?? horaire.isOuvert ?? 1) === 1)
    .map((horaire: any) => timeToMinutes(horaire.heure_ouverture ?? horaire.heureOuverture ?? `${String(DEFAULT_START_HOUR).padStart(2, '0')}:00`))
    .filter((value: number) => Number.isFinite(value))

  const rdvStarts = visibleWeekRdvRanges.value.map((range: any) => Math.max(0, range.start - (TIME_STEP_MINUTES * 2)))
  const minValue = [...values, ...rdvStarts]
  const resolved = minValue.length ? Math.min(...minValue) : DEFAULT_START_HOUR * 60
  return Math.floor(resolved / TIME_STEP_MINUTES) * TIME_STEP_MINUTES
})

const endMinutes = computed(() => {
  const values = (Array.from(horaireMap.value.values()) as any[])
    .filter((horaire: any) => Number(horaire.is_ouvert ?? horaire.isOuvert ?? 1) === 1)
    .map((horaire: any) => timeToMinutes(horaire.heure_fermeture ?? horaire.heureFermeture ?? `${String(DEFAULT_END_HOUR).padStart(2, '0')}:00`))
    .filter((value: number) => Number.isFinite(value))

  const rdvEnds = visibleWeekRdvRanges.value.map((range: any) => range.end + (TIME_STEP_MINUTES * 2))
  const maxValue = [...values, ...rdvEnds]
  const resolved = maxValue.length ? Math.max(...maxValue) : DEFAULT_END_HOUR * 60
  return Math.ceil(resolved / TIME_STEP_MINUTES) * TIME_STEP_MINUTES
})

const timeSlots = computed(() => {
  const slots: { label: string; minutes: number }[] = []
  for (let minutes = startMinutes.value; minutes < endMinutes.value; minutes += TIME_STEP_MINUTES) {
    slots.push({
      label: minutesToTime(minutes),
      minutes,
    })
  }
  return slots
})

const formatDateRange = computed(() => {
  const start = weekDays.value[0]
  const end = weekDays.value[weekDays.value.length - 1]
  if (!start || !end) return ''
  return `${start.dateNum} ${start.month} — ${end.dateNum} ${end.month}`
})

const boardStyle = computed(() => ({
  display: 'grid',
  gridTemplateColumns: `60px repeat(${Math.max(weekDays.value.length, 1)}, minmax(140px, 1fr))`,
  minWidth: `${60 + Math.max(weekDays.value.length, 1) * 140}px`,
}))

const timeLabelStyle = computed((): CSSProperties => ({
  padding: '2px 8px 2px 4px',
  fontSize: '10px',
  fontWeight: '600',
  color: '#6B7280',
  textAlign: 'right',
  borderTop: '1px solid rgba(255,255,255,0.04)',
  height: `${ROW_HEIGHT}px`,
  display: 'flex',
  alignItems: 'flex-start',
  justifyContent: 'flex-end',
  paddingTop: '3px',
}))

const nowLineTop = computed(() => {
  if (!weekDays.value.some((day: any) => day.isToday)) return -1
  const now = new Date()
  const minutes = now.getHours() * 60 + now.getMinutes()
  if (minutes < startMinutes.value || minutes > endMinutes.value) return -1
  const offsetMinutes = minutes - startMinutes.value
  return HEADER_HEIGHT + (offsetMinutes / TIME_STEP_MINUTES) * ROW_HEIGHT
})

const dayLayouts = computed(() => {
  const layouts: Record<string, any[]> = {}

  for (const day of weekDays.value) {
    const dayEvents = (props.rdvs || [])
      .filter((rdv: any) => rdv.date_rdv === day.date)
      .map((rdv: any) => {
        const start = parseTime(rdv.heure_debut)
        const duration = Number(rdv.duree_estimee || rdv.temps_estime || 60)
        return {
          ...rdv,
          start,
          end: start + duration,
        }
      })
      .sort((a: any, b: any) => a.start - b.start || a.end - b.end)

    const activeColumns: Array<{ end: number; column: number }> = []
    const placed = dayEvents.map((rdv: any) => {
      for (let i = activeColumns.length - 1; i >= 0; i--) {
        if (activeColumns[i].end <= rdv.start) activeColumns.splice(i, 1)
      }

      const used = new Set(activeColumns.map((item) => item.column))
      let column = 0
      while (used.has(column)) column += 1

      activeColumns.push({ end: rdv.end, column })
      return { ...rdv, column }
    })

    layouts[day.date] = placed.map((rdv: any) => {
      const overlaps = placed.filter((other: any) => rdv.start < other.end && other.start < rdv.end)
      const overlapCount = Math.max(1, ...overlaps.map((item: any) => item.column + 1))
      return {
        ...rdv,
        overlapCount,
      }
    })
  }

  return layouts
})

function prevWeek() {
  const d = new Date(currentDate.value)
  d.setDate(d.getDate() - 7)
  currentDate.value = d
}

function nextWeek() {
  const d = new Date(currentDate.value)
  d.setDate(d.getDate() + 7)
  currentDate.value = d
}

function goToday() {
  currentDate.value = new Date()
}

function countRdvsForDay(date: string) {
  return (props.rdvs || []).filter((rdv: any) => rdv.date_rdv === date).length
}

function timeToMinutes(time: string | undefined) {
  const raw = String(time || '00:00')
  const [hours, minutes] = raw.split(':').map(Number)
  return (hours || 0) * 60 + (minutes || 0)
}

function minutesToTime(minutes: number) {
  return `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`
}

function parseTime(time: string | undefined): number {
  return timeToMinutes(time)
}

function getDayHoraire(date: string) {
  const jsDay = new Date(`${date}T00:00:00`).getDay()
  const idx = jsDay === 0 ? 6 : jsDay - 1
  return horaireMap.value.get(idx) || null
}

function isPauseSlot(date: string, minutes: number) {
  const horaire = getDayHoraire(date)
  if (!horaire) return false
  const pauseStart = horaire.pause_debut ?? horaire.pauseDebut
  const pauseEnd = horaire.pause_fin ?? horaire.pauseFin
  if (!pauseStart || !pauseEnd) return false
  return minutes >= timeToMinutes(pauseStart) && minutes < timeToMinutes(pauseEnd)
}

function isSlotOpen(date: string, minutes: number) {
  const horaire = getDayHoraire(date)
  if (!horaire) return true
  if (Number(horaire.is_ouvert ?? horaire.isOuvert ?? 1) !== 1) return false

  const open = timeToMinutes(horaire.heure_ouverture ?? horaire.heureOuverture ?? '08:00')
  const close = timeToMinutes(horaire.heure_fermeture ?? horaire.heureFermeture ?? '18:00')
  if (minutes < open || minutes >= close) return false
  if (isPauseSlot(date, minutes)) return false
  return true
}

function cellStyle(day: { isToday: boolean; date: string }, minutes: number): StyleValue {
  return [
    {
      position: 'relative',
      borderTop: '1px solid rgba(255,255,255,0.04)',
      borderLeft: '1px solid rgba(255,255,255,0.04)',
      height: `${ROW_HEIGHT}px`,
      cursor: props.canCreate && isSlotOpen(day.date, minutes) ? 'pointer' : 'default',
    },
    day.isToday ? { background: 'rgba(255,210,0,0.02)' } : {},
    !isSlotOpen(day.date, minutes) ? { background: 'rgba(255,255,255,0.015)' } : {},
    isPauseSlot(day.date, minutes) ? { background: 'rgba(99,102,241,0.06)' } : {},
  ]
}

function getRdvsStartingAt(date: string, slotMinutes: number) {
  return (dayLayouts.value[date] || []).filter((rdv: any) => rdv.start >= slotMinutes && rdv.start < slotMinutes + TIME_STEP_MINUTES)
}

function rdvHeight(rdv: any): number {
  const duration = Number(rdv.duree_estimee || rdv.temps_estime || 60)
  return Math.max(20, (duration / TIME_STEP_MINUTES) * ROW_HEIGHT)
}

function rdvStyle(rdv: any): CSSProperties {
  const overlapCount = Math.max(1, Number(rdv.overlapCount || 1))
  const width = 100 / overlapCount
  return {
    position: 'absolute',
    top: '0px',
    left: `calc(${rdv.column * width}% + 2px)`,
    width: `calc(${width}% - 4px)`,
    height: `${rdvHeight(rdv)}px`,
    minHeight: `${ROW_HEIGHT}px`,
    zIndex: 2,
    overflow: 'hidden',
    opacity: isHistoricalStatus(rdv.status) ? 0.72 : 1,
    cursor: canDragRdv(rdv) ? 'grab' : 'pointer',
  }
}

function rdvStatusClass(status: string) {
  return `rdv-status-${status || 'en_attente'}`
}

function rdvTypeClass(type?: string) {
  const t = String(type || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase()
  if (t.includes('entretien')) return 'rdv-type-entretien'
  if (t.includes('reparation')) return 'rdv-type-reparation'
  if (t.includes('diagnostic')) return 'rdv-type-diagnostic'
  if (t.includes('revision') || t.includes('vidange')) return 'rdv-type-revision'
  return 'rdv-type-default'
}

function mecanicienFor(rdv: any) {
  const id = rdv.mecanicien_id ?? rdv.mecanicien?.id
  if (!id) return null
  const m = (props.mecaniciens || []).find((x: any) => Number(x.id) === Number(id))
  if (!m) return null
  const label = `${m.prenom ?? ''} ${m.nom ?? ''}`.trim()
  const initials = `${m.prenom?.[0] ?? ''}${m.nom?.[0] ?? ''}`.toUpperCase()
  return { ...m, label, initials }
}

function formatDurationShort(minutes?: number) {
  const m = Number(minutes) || 0
  if (m < 60) return `${m} min`
  const h = Math.floor(m / 60)
  const rm = m % 60
  return rm ? `${h}h${rm}` : `${h}h`
}

function isHistoricalStatus(status: string) {
  return (props.historicalStatuses || []).includes(status)
}

function canDragRdv(rdv: any) {
  return !!props.canDrag && !isHistoricalStatus(rdv.status)
}

function handleCellClick(date: string, minutes: number) {
  if (!props.canCreate || !isSlotOpen(date, minutes)) return
  emit('create-at', { date, time: minutesToTime(minutes) })
}

function onDragStart(event: DragEvent, rdv: any) {
  if (!canDragRdv(rdv)) {
    event.preventDefault()
    return
  }
  draggingRdvId.value = Number(rdv.id)
  event.dataTransfer?.setData('text/plain', String(rdv.id))
  event.dataTransfer?.setData('application/json', JSON.stringify({ id: rdv.id }))
  if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move'
}

function handleDragOver(date: string, minutes: number) {
  if (!draggingRdvId.value || !isSlotOpen(date, minutes)) return
  dropTarget.value = { date, minutes }
}

function handleDrop(date: string, minutes: number) {
  if (!draggingRdvId.value || !isSlotOpen(date, minutes)) return
  emit('move-rdv', {
    id: draggingRdvId.value,
    date,
    time: minutesToTime(minutes),
  })
  draggingRdvId.value = null
  dropTarget.value = null
}

function isDropTarget(date: string, minutes: number) {
  return !!dropTarget.value && dropTarget.value.date === date && dropTarget.value.minutes === minutes
}

function onDragEnd() {
  draggingRdvId.value = null
  dropTarget.value = null
}
</script>

<style scoped>
.toolbar-btn {
  background: transparent;
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 6px;
  color: #9CA3AF;
  cursor: pointer;
  padding: 6px 12px;
  font-size: 14px;
  font-family: inherit;
  transition: all 0.2s;
}
.toolbar-btn:hover {
  background: rgba(255,255,255,0.04);
  color: #E8E9ED;
}
.toolbar-btn-today {
  background: transparent;
  border: 1px solid rgba(255,210,0,0.3);
  border-radius: 6px;
  color: #FFD200;
  cursor: pointer;
  padding: 8px 14px;
  font-size: 12px;
  font-weight: 700;
  font-family: inherit;
  transition: all 0.2s;
}
.toolbar-btn-today:hover {
  background: rgba(255,210,0,0.08);
}
.planning-cell.is-clickable:hover {
  background: rgba(255,210,0,0.05) !important;
}
.planning-cell.is-drop-target {
  box-shadow: inset 0 0 0 1px rgba(255,210,0,0.45);
  background: rgba(255,210,0,0.08) !important;
}
.rdv-block.is-draggable:active {
  cursor: grabbing;
}
.rdv-block-inner {
  width: 100%;
  height: 100%;
  overflow: hidden;
}
.rdv-block-title {
  font-size: 11px;
  font-weight: 700;
  line-height: 1.2;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.rdv-block-client {
  font-size: 10px;
  opacity: 0.8;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.rdv-block-web {
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.05em;
  color: #93C5FD;
  opacity: 0.9;
}
.rdv-block-priority {
  margin-top: 2px;
}
.rdv-block-meca {
  margin-top: 2px;
}
.rdv-tooltip {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 180px;
}
.rdv-tooltip-header {
  font-size: 13px;
  font-weight: 700;
  color: #E8E9ED;
  margin-bottom: 2px;
}
.rdv-tooltip-row {
  font-size: 11px;
  color: #CBD5E1;
}
.rdv-tooltip-label {
  color: #6B7280;
  font-weight: 600;
  margin-right: 4px;
}
</style>
