<template>
  <span class="status-badge" :style="badgeStyle">
    {{ label }}
  </span>
</template>

<script setup lang="ts">
const props = defineProps<{ status: string }>()

const statusMap: Record<string, { label: string; bg: string; color: string }> = {
  en_attente:  { label: 'En attente',  bg: 'var(--surface-3)', color: 'var(--content-2)' },
  reserve:     { label: 'Réservé',     bg: 'var(--warning-soft)',  color: 'var(--warning-content)' },
  confirme:    { label: 'Confirmé',    bg: 'var(--info-soft)',  color: 'var(--info-content)' },
  reception:   { label: 'Réception',   bg: 'var(--warning-soft)',  color: 'var(--warning-content)' },
  en_cours:    { label: 'En cours',    bg: 'var(--success-soft)',  color: 'var(--success-content)' },
  termine:     { label: 'Terminé',     bg: 'var(--success-soft)',  color: 'var(--success-content)' },
  restitue:    { label: 'Restitué',    bg: 'var(--success-soft)',  color: 'var(--success-content)' },
  facture:     { label: 'Facturé',     bg: 'var(--info-soft)',  color: 'var(--info-content)' },
  // Seul statut posé sur un APLAT plein (et non un fond teinté) : son encre
  // suit la luminosité de l'aplat, pas celle du thème, d'où `--on-success`.
  paye:        { label: 'Payé',        bg: 'var(--success)',       color: 'var(--on-success)' },
  annule:      { label: 'Annulé',      bg: 'var(--error-soft)',   color: 'var(--error-content)' },
}

const entry = computed(() => statusMap[props.status] || { label: props.status, bg: 'var(--surface-3)', color: 'var(--content-2)' })
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
