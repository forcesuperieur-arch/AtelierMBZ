<template>
  <div class="meca-section" :class="{ 'is-open': modelValue }">
    <button type="button" class="meca-section-head" @click="$emit('update:modelValue', !modelValue)">
      <span class="meca-section-title"><AppIcon v-if="icon" :name="icon" /> {{ title }}</span>
      <span class="meca-section-right">
        <span v-if="badge" class="meca-section-badge">{{ badge }}</span>
        <AppIcon name="i-ri-arrow-down-s-line" class="meca-section-chevron" />
      </span>
    </button>
    <div v-show="modelValue" class="meca-section-body">
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  title: string
  icon?: string
  badge?: string
  modelValue: boolean
}>()
defineEmits<{ 'update:modelValue': [value: boolean] }>()
</script>

<style scoped>
.meca-section {
  border-top: 1px solid var(--border-2);
}

.meca-section-head {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  min-height: 52px;
  padding: 14px 4px;
  background: none;
  border: none;
  font-family: inherit;
  cursor: pointer;
  text-align: left;
}

.meca-section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 15px;
  font-weight: 700;
  color: var(--content-1);
}

.meca-section-right {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-shrink: 0;
}

.meca-section-badge {
  font-size: 12px;
  font-weight: 600;
  color: var(--content-3);
}

.meca-section-chevron {
  font-size: 20px;
  color: var(--content-3);
  transition: transform var(--dur-base) var(--ease);
}

.meca-section.is-open .meca-section-chevron {
  transform: rotate(180deg);
}

.meca-section-body {
  padding: 0 4px 18px;
}
</style>
