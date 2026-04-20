<template>
  <span class="status-badge" :style="badgeStyle">
    {{ label }}
  </span>
</template>

<script setup lang="ts">
const props = defineProps<{ status: string }>()

const statusMap: Record<string, { label: string; bg: string; color: string }> = {
  en_attente:  { label: 'En attente',  bg: 'rgba(107,114,128,0.12)', color: '#D1D5DB' },
  reserve:     { label: 'Réservé',     bg: 'rgba(245,158,11,0.12)',  color: '#FCD34D' },
  confirme:    { label: 'Confirmé',    bg: 'rgba(59,130,246,0.12)',  color: '#93C5FD' },
  reception:   { label: 'Réception',   bg: 'rgba(245,158,11,0.12)',  color: '#FCD34D' },
  en_cours:    { label: 'En cours',    bg: 'rgba(20,184,166,0.12)',  color: '#5EEAD4' },
  termine:     { label: 'Terminé',     bg: 'rgba(16,185,129,0.12)',  color: '#6EE7B7' },
  restitue:    { label: 'Restitué',    bg: 'rgba(16,185,129,0.12)',  color: '#6EE7B7' },
  facture:     { label: 'Facturé',     bg: 'rgba(139,92,246,0.12)',  color: '#C4B5FD' },
  paye:        { label: 'Payé',        bg: '#10B981',                color: '#ffffff' },
  annule:      { label: 'Annulé',      bg: 'rgba(239,68,68,0.12)',   color: '#FCA5A5' },
  a_regulariser:{ label: 'À régulariser', bg: 'rgba(239,68,68,0.12)', color: '#FCA5A5' },
}

const entry = computed(() => statusMap[props.status] || { label: props.status, bg: 'rgba(107,114,128,0.12)', color: '#D1D5DB' })
const label = computed(() => entry.value.label)
const badgeStyle = computed(() => ({
  background: entry.value.bg,
  color: entry.value.color,
}))
</script>

<style scoped>
.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.02em;
  white-space: nowrap;
}
</style>
