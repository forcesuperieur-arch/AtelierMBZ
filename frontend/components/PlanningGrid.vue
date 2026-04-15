<template>
  <div>
    <!-- Toolbar -->
    <div class="planning-toolbar" style="margin-bottom: 18px;">
      <div class="planning-toolbar-group">
        <button class="toolbar-btn" @click="prevWeek">◀</button>
        <div style="display:flex;flex-direction:column;gap:2px;min-width:140px;">
          <strong style="color:#f8fafc;font-size:14px;line-height:1.1;">Semaine</strong>
          <span style="font-size:12px;color:#cbd5e1;">{{ formatDateRange }}</span>
        </div>
        <button class="toolbar-btn" @click="nextWeek">▶</button>
      </div>
      <button class="toolbar-btn-today" @click="goToday">Aujourd'hui</button>
    </div>

    <!-- Planning board with time axis -->
    <div class="planning-board" style="position:relative;">
      <div style="overflow-x:auto;">
        <div style="display:grid;grid-template-columns:60px repeat(7, 1fr);min-width:900px;">
          <!-- Header row -->
          <div style="padding:10px;border-bottom:1px solid rgba(255,255,255,0.06);background:rgba(255,255,255,0.02);"></div>
          <div
            v-for="day in weekDays"
            :key="'h-'+day.date"
            :class="['planning-day-header', day.isToday ? 'is-today' : '']"
            style="border-bottom:1px solid rgba(255,255,255,0.06);border-left:1px solid rgba(255,255,255,0.04);"
          >
            <div class="planning-day-name">{{ day.label }}</div>
            <div class="planning-day-date">{{ day.dateNum }}</div>
            <div v-if="day.isToday" class="planning-today-label">AUJOURD'HUI</div>
            <div style="font-size:10px;color:#6B7280;margin-top:2px;">{{ countRdvsForDay(day.date) }} RDV</div>
          </div>

          <!-- Time rows -->
          <template v-for="slot in timeSlots" :key="slot.label">
            <!-- Time label -->
            <div :style="{ padding: '2px 8px 2px 4px', fontSize: '10px', fontWeight: '600', color: '#6B7280', textAlign: 'right', borderTop: '1px solid rgba(255,255,255,0.04)', height: `${ROW_HEIGHT}px`, display: 'flex', alignItems: 'flex-start', justifyContent: 'flex-end', paddingTop: '3px' }">
              {{ slot.label }}
            </div>
            <!-- Day cells -->
            <div
              v-for="day in weekDays"
              :key="`${slot.label}-${day.date}`"
              :style="[{ position: 'relative', borderTop: '1px solid rgba(255,255,255,0.04)', borderLeft: '1px solid rgba(255,255,255,0.04)', height: `${ROW_HEIGHT}px` }, day.isToday ? { background: 'rgba(255,210,0,0.02)' } : {}]"
            >
              <!-- RDV blocks positioned by time -->
              <div
                v-for="rdv in getRdvsStartingAt(day.date, slot.minutes)"
                :key="rdv.id"
                :class="['rdv-block', rdvStatusClass(rdv.status)]"
                style="position:absolute;left:2px;right:2px;z-index:2;overflow:hidden;"
                :style="{ top: '0px', height: rdvHeight(rdv) + 'px', minHeight: `${ROW_HEIGHT}px` }"
                @click="$emit('select-rdv', rdv)"
              >
                <div style="font-size:10px;font-weight:800;letter-spacing:.05em;opacity:.9;">
                  {{ rdv.heure_debut?.slice(0, 5) }}
                </div>
                <div style="font-size:11px;font-weight:700;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                  {{ rdv.type_intervention }}
                </div>
                <div style="font-size:10px;opacity:.8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                  {{ rdv.client_nom }}
                </div>
              </div>
            </div>
          </template>
        </div>

        <!-- Now line -->
        <div v-if="nowLineTop > 0" style="position:absolute;left:60px;right:0;z-index:10;pointer-events:none;" :style="{ top: nowLineTop + 'px' }">
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
const props = defineProps<{
  ponts: Array<{ id: number; nom: string }>
  rdvs: Array<any>
}>()

defineEmits<{
  'select-rdv': [rdv: any]
}>()

const currentDate = ref(new Date())
const HEADER_HEIGHT = 72 // approx header row height
const TIME_STEP_MINUTES = 15
const ROW_HEIGHT = 24
const START_HOUR = 8
const END_HOUR = 19

const weekStart = computed(() => {
  const d = new Date(currentDate.value)
  const day = d.getDay()
  const diff = d.getDate() - day + (day === 0 ? -6 : 1)
  return new Date(d.setDate(diff))
})

const weekDays = computed(() => {
  const days = []
  const labels = ['LUN', 'MAR', 'MER', 'JEU', 'VEN', 'SAM', 'DIM']
  const today = new Date().toISOString().slice(0, 10)
  for (let i = 0; i < 7; i++) {
    const d = new Date(weekStart.value)
    d.setDate(d.getDate() + i)
    const date = d.toISOString().slice(0, 10)
    days.push({
      label: labels[i],
      date,
      dateNum: d.getDate(),
      month: ['jan','fév','mar','avr','mai','jun','jul','aoû','sep','oct','nov','déc'][d.getMonth()],
      isToday: date === today,
    })
  }
  return days
})

const timeSlots = computed(() => {
  const slots: { label: string; minutes: number }[] = []
  for (let h = START_HOUR; h < END_HOUR; h++) {
    for (const m of [0, 15, 30, 45]) {
      slots.push({
        label: `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`,
        minutes: h * 60 + m,
      })
    }
  }
  return slots
})

const formatDateRange = computed(() => {
  const start = weekDays.value[0]
  const end = weekDays.value[weekDays.value.length - 1]
  if (!start || !end) return ''
  return `${start.dateNum} ${start.month} — ${end.dateNum} ${end.month}`
})

const nowLineTop = computed(() => {
  const now = new Date()
  const minutes = now.getHours() * 60 + now.getMinutes()
  if (minutes < START_HOUR * 60 || minutes > END_HOUR * 60) return -1
  const offsetMinutes = minutes - START_HOUR * 60
  return HEADER_HEIGHT + (offsetMinutes / TIME_STEP_MINUTES) * ROW_HEIGHT
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
  return props.rdvs.filter(r => r.date_rdv === date).length
}

function parseTime(t: string | undefined): number {
  if (!t) return START_HOUR * 60
  const parts = t.split(':')
  return Number(parts[0]) * 60 + Number(parts[1] || 0)
}

function getRdvsStartingAt(date: string, slotMinutes: number) {
  return props.rdvs.filter(r => {
    if (r.date_rdv !== date) return false
    const rdvMin = parseTime(r.heure_debut)
    return rdvMin >= slotMinutes && rdvMin < slotMinutes + TIME_STEP_MINUTES
  })
}

function rdvHeight(rdv: any): number {
  const duration = rdv.duree_estimee || rdv.temps_estime || 60
  return Math.max(20, (duration / TIME_STEP_MINUTES) * ROW_HEIGHT)
}

function rdvStatusClass(status: string): string {
  return `rdv-status-${status || 'en_attente'}`
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
</style>
