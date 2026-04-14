<template>
  <UBadge :color="color" :variant="variant" size="sm">
    {{ label }}
  </UBadge>
</template>

<script setup lang="ts">
const props = defineProps<{ status: string }>()

const statusMap: Record<string, { label: string; color: string; variant: string }> = {
  en_attente:  { label: 'En attente',  color: 'gray',    variant: 'subtle' },
  reserve:     { label: 'Réservé',     color: 'blue',    variant: 'subtle' },
  confirme:    { label: 'Confirmé',    color: 'info',    variant: 'subtle' },
  reception:   { label: 'Réception',   color: 'warning', variant: 'subtle' },
  en_cours:    { label: 'En cours',    color: 'orange',  variant: 'solid' },
  termine:     { label: 'Terminé',     color: 'success', variant: 'subtle' },
  restitue:    { label: 'Restitué',    color: 'primary', variant: 'subtle' },
  facture:     { label: 'Facturé',     color: 'violet',  variant: 'subtle' },
  paye:        { label: 'Payé',        color: 'success', variant: 'solid' },
  annule:      { label: 'Annulé',      color: 'error',   variant: 'subtle' },
}

const entry = computed(() => statusMap[props.status] || { label: props.status, color: 'gray', variant: 'subtle' })
const label = computed(() => entry.value.label)
const color = computed(() => entry.value.color)
const variant = computed(() => entry.value.variant)
</script>
