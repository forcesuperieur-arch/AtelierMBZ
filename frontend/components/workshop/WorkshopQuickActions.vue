<template>
  <UDropdownMenu :items="menuItems">
    <UButton
      color="neutral"
      variant="ghost"
      size="xs"
      icon="i-heroicons-ellipsis-vertical"
      class="workshop-action-btn"
    />
  </UDropdownMenu>
</template>

<script setup lang="ts">
interface Props {
  rdv?: any
  pont?: any
}

const props = defineProps<Props>()
const emit = defineEmits<{ (e: 'action', action: string, rdv?: any, pont?: any): void }>()

const menuItems = computed(() => [
  [
    { label: 'Démarrer intervention', icon: 'i-heroicons-play', onSelect: () => emit('action', 'start', props.rdv, props.pont) },
    { label: 'Mettre en pause', icon: 'i-heroicons-pause', onSelect: () => emit('action', 'pause', props.rdv, props.pont) },
  ],
  [
    { label: 'Demander pièces', icon: 'i-heroicons-wrench-screwdriver', onSelect: () => emit('action', 'pieces', props.rdv, props.pont) },
    { label: 'Envoyer devis', icon: 'i-heroicons-document-text', onSelect: () => emit('action', 'devis', props.rdv, props.pont) },
    { label: 'Contacter client', icon: 'i-heroicons-phone', onSelect: () => emit('action', 'contact', props.rdv, props.pont) },
    { label: 'Ajouter note', icon: 'i-heroicons-pencil-square', onSelect: () => emit('action', 'note', props.rdv, props.pont) },
  ],
  [
    { label: 'Marquer comme livré', icon: 'i-heroicons-check-circle', onSelect: () => emit('action', 'livre', props.rdv, props.pont) },
  ],
])
</script>

<style scoped>
:deep(.workshop-action-btn) {
  color: #9CA3AF;
}
:deep(.workshop-action-btn:hover) {
  color: #E8E9ED;
  background: rgba(255, 255, 255, 0.04);
}
</style>
