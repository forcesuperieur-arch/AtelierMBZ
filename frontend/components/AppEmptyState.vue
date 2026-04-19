<template>
  <div class="app-state app-state-empty">
    <div class="app-state-icon" aria-hidden="true">{{ icon }}</div>
    <div class="app-state-title">{{ title }}</div>
    <div v-if="description" class="app-state-description">{{ description }}</div>
    <button v-if="actionLabel" class="btn btn-ghost" @click="$emit('action')">{{ actionLabel }}</button>
  </div>
</template>

<script setup lang="ts">
defineEmits(['action'])

defineProps({
  icon: { type: String, default: '📭' },
  title: { type: String, default: 'Aucune donnée disponible' },
  description: { type: String, default: '' },
  actionLabel: { type: String, default: '' },
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
  border: 1px dashed rgba(255,255,255,0.08);
  text-align: center;
}

.app-state-icon {
  font-size: 30px;
}

.app-state-title {
  font-size: 15px;
  font-weight: 700;
  color: #E8E9ED;
}

.app-state-description {
  max-width: 460px;
  font-size: 13px;
  line-height: 1.45;
  color: #9CA3AF;
}
</style>
