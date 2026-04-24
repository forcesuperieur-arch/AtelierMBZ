<template>
  <div class="planning-list">
    <div v-for="group in groupedRdvs" :key="group.date" class="planning-list-group">
      <div class="planning-list-date">
        <span class="planning-list-day">{{ group.dayLabel }}</span>
        <span class="planning-list-count">{{ group.items.length }} RDV</span>
      </div>

      <div
        v-for="rdv in group.items"
        :key="rdv.id"
        class="swipe-card"
        @touchstart.passive="onTouchStart($event, rdv)"
        @touchmove.passive="onTouchMove($event, rdv)"
        @touchend="onTouchEnd(rdv)"
      >
        <div
          class="swipe-actions"
          :style="{ opacity: swipeOpenId === rdv.id ? 1 : 0, transition: 'opacity 0.2s' }"
        >
          <button
            v-for="t in quickTransitions(rdv)"
            :key="t.name"
            class="swipe-action-btn"
            :style="{ background: t.bg }"
            @click.stop="$emit('action-transition', { rdv, name: t.name })"
          >
            {{ t.label }}
          </button>
        </div>

        <div
          class="planning-list-card"
          :class="[rdvTypeClass(rdv.type_intervention), { 'is-swiped': swipeOpenId === rdv.id }]"
          :style="swipeCardStyle(rdv)"
          @click.stop="$emit('select-rdv', rdv)"
        >
          <div class="planning-list-card-main">
            <div class="planning-list-time">
              <span class="planning-list-hour">{{ rdv.heure_debut?.slice(0, 5) }}</span>
              <span class="planning-list-duration">{{ formatDurationShort(rdv.temps_estime) }}</span>
            </div>
            <div class="planning-list-info">
              <div class="planning-list-title">
                {{ rdv.client_nom || 'Client inconnu' }}
                <span v-if="rdv.vehicule_info" class="planning-list-subtitle">· {{ rdv.vehicule_info }}</span>
              </div>
              <div class="planning-list-meta">
                <span class="planning-list-type">{{ rdv.type_intervention || '—' }}</span>
                <span
                  v-if="rdv.priorite || 'normale'"
                  class="priority-badge"
                  :class="`priority-badge-${rdv.priorite || 'normale'}`"
                >
                  {{ rdv.priorite || 'normale' }}
                </span>
              </div>
            </div>
          </div>

          <div class="planning-list-card-side">
            <span
              v-if="mecanicienFor(rdv)"
              class="meca-avatar"
              :style="{ background: mecanicienFor(rdv)?.couleur || '#8B5CF6' }"
              :title="mecanicienFor(rdv)?.label"
            >
              {{ mecanicienFor(rdv)?.initials }}
            </span>
            <span class="planning-list-status-dot" :class="`status-dot-${rdv.status || rdv.statut || 'en_attente'}`" />
          </div>
        </div>
      </div>

      <AppEmptyState
        v-if="group.items.length === 0"
        icon="📭"
        title="Aucun rendez-vous"
        description="Il n'y a pas de RDV prévu ce jour."
      />
    </div>

    <AppEmptyState
      v-if="!groupedRdvs.length"
      icon="📭"
      title="Aucun rendez-vous"
      description="Il n'y a pas de RDV dans la période sélectionnée."
      :action-label="canCreate ? 'Créer un RDV' : ''"
      @action="$emit('create-request')"
    />
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{
  rdvs?: any[]
  mecaniciens?: any[]
  canCreate?: boolean
  canDrag?: boolean
  canEdit?: boolean
}>()

const emit = defineEmits<{
  'select-rdv': [rdv: any]
  'action-transition': [payload: { rdv: any; name: string }]
  'create-request': []
}>()

const DAY_LABELS = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam']
const MONTH_LABELS = ['jan', 'fév', 'mar', 'avr', 'mai', 'jun', 'jul', 'aoû', 'sep', 'oct', 'nov', 'déc']

const swipeOpenId = ref<number | null>(null)
const touchStartX = ref(0)
const touchCurrentX = ref(0)

const groupedRdvs = computed(() => {
  const map = new Map<string, any[]>()
  const list = (props.rdvs || []).slice().sort((a: any, b: any) => {
    const d = String(a.date_rdv || '').localeCompare(String(b.date_rdv || ''))
    if (d !== 0) return d
    return String(a.heure_debut || '00:00').localeCompare(String(b.heure_debut || '00:00'))
  })
  for (const rdv of list) {
    const date = String(rdv.date_rdv || '')
    if (!map.has(date)) map.set(date, [])
    map.get(date)!.push(rdv)
  }
  return Array.from(map.entries()).map(([date, items]) => {
    const d = new Date(`${date}T00:00:00`)
    const today = new Date().toISOString().slice(0, 10)
    const isPast = date < today
    const isToday = date === today
    return {
      date,
      isPast,
      isToday,
      dayLabel: isToday ? `Aujourd'hui · ${d.getDate()} ${MONTH_LABELS[d.getMonth()]}` : `${DAY_LABELS[d.getDay()]} ${d.getDate()} ${MONTH_LABELS[d.getMonth()]}`,
      items,
    }
  })
})

function mecanicienFor(rdv: any) {
  const id = rdv.mecanicien_id ?? rdv.mecanicien?.id
  if (!id) return null
  const m = (props.mecaniciens || []).find((x: any) => Number(x.id) === Number(id))
  if (!m) return null
  const label = `${m.prenom ?? ''} ${m.nom ?? ''}`.trim()
  const initials = `${m.prenom?.[0] ?? ''}${m.nom?.[0] ?? ''}`.toUpperCase()
  return { ...m, label, initials }
}

function rdvTypeClass(type?: string) {
  const t = String(type || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase()
  if (t.includes('entretien')) return 'rdv-type-entretien'
  if (t.includes('reparation')) return 'rdv-type-reparation'
  if (t.includes('diagnostic')) return 'rdv-type-diagnostic'
  if (t.includes('revision') || t.includes('vidange')) return 'rdv-type-revision'
  return 'rdv-type-default'
}

function formatDurationShort(minutes?: number) {
  const m = Number(minutes) || 0
  if (m < 60) return `${m} min`
  const h = Math.floor(m / 60)
  const rm = m % 60
  return rm ? `${h}h${rm}` : `${h}h`
}

function quickTransitions(rdv: any) {
  const status = rdv.status ?? rdv.statut
  const transitions: { name: string; label: string; bg: string }[] = []
  if (status === 'en_attente') {
    transitions.push({ name: 'reserver', label: 'Réserver', bg: 'rgba(245,158,11,0.8)' })
    transitions.push({ name: 'annuler', label: 'Refuser', bg: 'rgba(239,68,68,0.8)' })
  } else if (status === 'reserve') {
    transitions.push({ name: 'confirmer', label: 'Confirmer', bg: 'rgba(59,130,246,0.8)' })
    transitions.push({ name: 'annuler', label: 'Annuler', bg: 'rgba(239,68,68,0.8)' })
  } else if (status === 'confirme') {
    transitions.push({ name: 'reception', label: 'Réception', bg: 'rgba(255,210,0,0.8)' })
  } else if (status === 'reception') {
    transitions.push({ name: 'start_travail', label: 'Démarrer', bg: 'rgba(20,184,166,0.8)' })
  } else if (status === 'en_cours') {
    transitions.push({ name: 'terminer', label: 'Terminer', bg: 'rgba(16,185,129,0.8)' })
  }
  return transitions
}

function onTouchStart(e: TouchEvent, rdv: any) {
  touchStartX.value = e.touches[0].clientX
  touchCurrentX.value = touchStartX.value
  if (swipeOpenId.value && swipeOpenId.value !== rdv.id) {
    swipeOpenId.value = null
  }
}

function onTouchMove(e: TouchEvent, rdv: any) {
  touchCurrentX.value = e.touches[0].clientX
}

function onTouchEnd(rdv: any) {
  const diff = touchStartX.value - touchCurrentX.value
  if (diff > 50) {
    swipeOpenId.value = rdv.id
  } else if (diff < -50) {
    swipeOpenId.value = null
  }
}

function swipeCardStyle(rdv: any) {
  if (swipeOpenId.value !== rdv.id) return { transform: 'translateX(0px)', transition: 'transform 0.25s ease' }
  return { transform: 'translateX(-120px)', transition: 'transform 0.25s ease' }
}
</script>

<style scoped>
.planning-list {
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.planning-list-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.planning-list-date {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  padding: 0 4px;
}
.planning-list-day {
  font-size: 13px;
  font-weight: 700;
  color: #E8E9ED;
}
.planning-list-count {
  font-size: 11px;
  color: #6B7280;
}
.planning-list-card {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 12px 14px;
  border-radius: var(--radius);
  background: var(--dark2);
  border: 1px solid rgba(255,255,255,0.06);
  cursor: pointer;
  transition: background 0.15s, transform 0.15s;
}
.planning-list-card:hover {
  background: var(--dark3);
}
.planning-list-card-main {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  flex: 1;
  min-width: 0;
}
.planning-list-time {
  display: flex;
  flex-direction: column;
  align-items: center;
  min-width: 44px;
}
.planning-list-hour {
  font-size: 14px;
  font-weight: 800;
  color: #E8E9ED;
}
.planning-list-duration {
  font-size: 10px;
  font-weight: 600;
  color: #6B7280;
}
.planning-list-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}
.planning-list-title {
  font-size: 13px;
  font-weight: 700;
  color: #E8E9ED;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.planning-list-subtitle {
  font-weight: 500;
  color: #9CA3AF;
}
.planning-list-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.planning-list-type {
  font-size: 11px;
  font-weight: 600;
  color: #CBD5E1;
  background: rgba(255,255,255,0.04);
  padding: 2px 8px;
  border-radius: 6px;
}
.planning-list-card-side {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-shrink: 0;
}
.planning-list-status-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}
.status-dot-en_attente { background: #94A3B8; }
.status-dot-reserve    { background: #F59E0B; }
.status-dot-confirme   { background: #3B82F6; }
.status-dot-reception  { background: #F59E0B; }
.status-dot-en_cours   { background: #14B8A6; }
.status-dot-termine    { background: #10B981; }
.status-dot-restitue   { background: #10B981; }
.status-dot-facture    { background: #8B5CF6; }
.status-dot-paye       { background: #10B981; }
.status-dot-annule     { background: #EF4444; }
</style>
