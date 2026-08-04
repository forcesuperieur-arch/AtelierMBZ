<template>
  <div class="checkpoint-grid">
    <button
      v-for="item in items"
      :key="item.key"
      type="button"
      class="checkpoint-btn"
      :class="values[item.key] === 'ok' ? 'is-ok' : values[item.key] === 'nok' ? 'is-nok' : ''"
      :disabled="disabled"
      @click="$emit('toggle', item.key)"
    >
      <AppIcon :name="values[item.key] === 'ok' ? 'i-ri-checkbox-circle-line' : values[item.key] === 'nok' ? 'i-ri-close-circle-line' : 'i-ri-checkbox-blank-line'" />
      <span>{{ item.label }}</span>
    </button>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  items: { key: string; label: string }[]
  values: Record<string, string>
  disabled?: boolean
}>()
defineEmits<{ toggle: [key: string] }>()
</script>

<style scoped>
.checkpoint-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: 10px;
}

.checkpoint-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 48px;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--overlay-hover);
  background: var(--overlay-soft);
  color: var(--content-2);
  font-family: inherit;
  font-size: 14px;
  font-weight: 600;
  text-align: left;
  cursor: pointer;
  transition: background var(--dur-fast) var(--ease), border-color var(--dur-fast) var(--ease);
}

.checkpoint-btn :deep(svg),
.checkpoint-btn .app-icon {
  font-size: 18px;
  flex-shrink: 0;
}

.checkpoint-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.checkpoint-btn.is-ok {
  background: var(--success-soft);
  border-color: var(--success);
  color: var(--success-content);
}

.checkpoint-btn.is-nok {
  background: var(--error-soft);
  border-color: var(--error);
  color: var(--error-content);
}
</style>
