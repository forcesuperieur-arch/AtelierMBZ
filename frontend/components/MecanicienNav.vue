<template>
  <nav class="mecanicien-nav">
    <div class="mecanicien-nav-scroll">
      <button
        v-for="section in sections"
        :key="section.id"
        class="mecanicien-nav-item"
        :class="{ active: modelValue === section.id }"
        @click="$emit('update:modelValue', section.id)"
      >
        <span class="mecanicien-nav-label">{{ section.label }}</span>
        <span v-if="section.badge" class="mecanicien-nav-badge" aria-hidden="true">{{ section.badge }}</span>
      </button>
      <div class="mecanicien-nav-indicator" :style="indicatorStyle" />
    </div>
  </nav>
</template>

<script setup lang="ts">
import { computed } from 'vue'

export interface NavSection {
  id: string
  label: string
  badge?: string
}

const props = defineProps<{
  sections: NavSection[]
  modelValue: string
}>()

defineEmits<{
  'update:modelValue': [id: string]
}>()

const indicatorStyle = computed(() => {
  const index = props.sections.findIndex(s => s.id === props.modelValue)
  if (index === -1) return {}
  const count = props.sections.length
  return {
    left: `${(index / count) * 100}%`,
    width: `${(1 / count) * 100}%`,
  }
})
</script>

<style scoped>
.mecanicien-nav {
  position: sticky;
  top: 0;
  z-index: 30;
  background: var(--dark2, #11141B);
  border-bottom: 1px solid rgba(255,255,255,0.06);
  border-radius: 10px 10px 0 0;
  margin: -20px -20px 16px;
}
@media (max-width: 768px) {
  .mecanicien-nav {
    margin: -16px -16px 16px;
    border-radius: 0;
  }
}
.mecanicien-nav-scroll {
  display: flex;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  position: relative;
  scrollbar-width: none;
}
.mecanicien-nav-scroll::-webkit-scrollbar {
  display: none;
}
.mecanicien-nav-item {
  flex: 1 0 auto;
  min-width: 80px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 12px 14px;
  background: none;
  border: none;
  color: #9CA3AF;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  position: relative;
  transition: color 0.2s;
  min-height: 48px;
}
.mecanicien-nav-item.active {
  color: #FFD200;
}
.mecanicien-nav-badge {
  font-size: 12px;
  line-height: 1;
}
.mecanicien-nav-indicator {
  position: absolute;
  bottom: 0;
  height: 2px;
  background: #FFD200;
  border-radius: 2px 2px 0 0;
  transition: left 0.25s ease, width 0.25s ease;
}
</style>
