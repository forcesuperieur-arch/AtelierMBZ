<template>
  <div class="app-state app-state-error">
    <div class="app-state-icon" aria-hidden="true">⚠️</div>
    <div class="app-state-title">{{ title }}</div>
    <div class="app-state-description">{{ description }}</div>
    <button class="btn btn-primary" @click="$emit('retry')">{{ actionLabel }}</button>
  </div>
</template>

<script setup lang="ts">
defineEmits(['retry'])

defineProps({
  title: { type: String, default: 'Chargement impossible' },
  description: { type: String, default: 'Une erreur est survenue pendant le chargement. Réessaie.' },
  actionLabel: { type: String, default: 'Réessayer' },
})
</script>

<style scoped>
.app-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  min-height: 220px;
  padding: 28px;
  border-radius: 16px;
  background: rgba(239,68,68,0.05);
  border: 1px solid rgba(239,68,68,0.18);
  text-align: center;
}

.app-state-icon {
  font-size: 28px;
}

.app-state-title {
  font-size: 15px;
  font-weight: 700;
  color: #FECACA;
}

.app-state-description {
  max-width: 460px;
  font-size: 13px;
  line-height: 1.45;
  color: #FCA5A5;
}
</style>
