<template>
  <div class="flex flex-wrap gap-2 mb-4">
    <button
      v-for="badge in badges"
      :key="badge.key"
      class="alert-badge"
      :class="{ active: modelValue === badge.key, pulse: badge.pulse && badge.count > 0 }"
      :style="badgeStyle(badge)"
      @click="$emit('update:modelValue', badge.key)"
    >
      <span class="alert-badge-dot" :style="dotStyle(badge)" />
      <span>{{ badge.label }}</span>
      <span class="alert-badge-count">{{ badge.count }}</span>
    </button>
  </div>
</template>

<script setup lang="ts">
interface BadgeDef {
  key: string
  label: string
  count: number
  severity: 'neutral' | 'info' | 'warning' | 'critical'
  pulse?: boolean
}

const props = defineProps<{
  modelValue: string
  counts: Record<string, number>
}>()

defineEmits<{ (e: 'update:modelValue', value: string): void }>()

const severityMeta: Record<string, { bg: string; border: string; color: string; dot: string }> = {
  neutral: { bg: 'rgba(255,255,255,0.04)', border: 'rgba(255,255,255,0.10)', color: '#D1D5DB', dot: '#9CA3AF' },
  info:    { bg: 'rgba(59,130,246,0.10)', border: 'rgba(59,130,246,0.25)', color: '#93C5FD', dot: '#3B82F6' },
  warning: { bg: 'rgba(245,158,11,0.08)', border: 'rgba(245,158,11,0.25)', color: '#FCD34D', dot: '#FFD200' },
  critical:{ bg: 'rgba(239,68,68,0.08)',  border: 'rgba(239,68,68,0.25)',  color: '#FCA5A5', dot: '#EF4444' },
}

const badges = computed<BadgeDef[]>(() => [
  { key: 'all',      label: 'Tous',                  count: props.counts.all      ?? 0, severity: 'neutral' },
  { key: 'retard',   label: 'En retard',             count: props.counts.retard   ?? 0, severity: 'critical', pulse: true },
  { key: 'pieces',   label: 'En attente pièces',     count: props.counts.pieces   ?? 0, severity: 'warning' },
  { key: 'no_show',  label: 'Client non joignable',  count: props.counts.no_show  ?? 0, severity: 'critical', pulse: true },
  { key: 'devis',    label: 'Devis en attente',      count: props.counts.devis    ?? 0, severity: 'info' },
  { key: 'essai',    label: 'Essai routier',         count: props.counts.essai    ?? 0, severity: 'info' },
  { key: 'photo',    label: 'Photo manquante',       count: props.counts.photo    ?? 0, severity: 'info' },
])

function badgeStyle(badge: BadgeDef) {
  const meta = severityMeta[badge.severity]
  const isActive = props.modelValue === badge.key
  return {
    background: isActive ? meta.bg : 'rgba(255,255,255,0.02)',
    borderColor: isActive ? meta.border : 'rgba(255,255,255,0.06)',
    color: isActive ? meta.color : '#9CA3AF',
  }
}

function dotStyle(badge: BadgeDef) {
  const meta = severityMeta[badge.severity]
  return { background: meta.dot }
}
</script>

<style scoped>
.alert-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 7px 14px;
  border-radius: 999px;
  border: 1px solid transparent;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  font-family: inherit;
  background: none;
}
.alert-badge:hover {
  transform: translateY(-1px);
  border-color: rgba(255,255,255,0.14);
}
.alert-badge.active {
  box-shadow: 0 0 0 3px rgba(255,255,255,0.04);
}
.alert-badge-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}
.alert-badge-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 20px;
  height: 20px;
  padding: 0 6px;
  border-radius: 999px;
  background: rgba(0,0,0,0.25);
  font-size: 11px;
  font-weight: 800;
}
@keyframes badge-pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(239,68,68,0.3); }
  50% { box-shadow: 0 0 0 6px rgba(239,68,68,0); }
}
.alert-badge.pulse .alert-badge-dot {
  animation: badge-pulse 2s infinite;
}
</style>
