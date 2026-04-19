<template>
  <div class="app-state app-state-loading" :class="{ compact }">
    <div class="spinner" aria-hidden="true"></div>
    <div class="app-state-title">{{ title }}</div>
    <div v-if="description" class="app-state-description">{{ description }}</div>
  </div>
</template>

<script setup lang="ts">
defineProps({
  title: { type: String, default: 'Chargement en cours' },
  description: { type: String, default: 'Les données sont en cours de récupération.' },
  compact: { type: Boolean, default: false },
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
  background: rgba(255,255,255,0.02);
  border: 1px solid rgba(255,255,255,0.06);
  text-align: center;
}

.app-state.compact {
  min-height: 120px;
}

.spinner {
  width: 34px;
  height: 34px;
  border-radius: 999px;
  border: 3px solid rgba(255,255,255,0.12);
  border-top-color: #FFD200;
  animation: spin 0.85s linear infinite;
}

.app-state-title {
  font-size: 15px;
  font-weight: 700;
  color: #E8E9ED;
}

.app-state-description {
  max-width: 440px;
  font-size: 13px;
  line-height: 1.45;
  color: #9CA3AF;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
