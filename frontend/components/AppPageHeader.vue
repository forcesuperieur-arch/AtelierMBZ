<template>
  <div class="page-header">
    <div style="flex:1;min-width:0;">
      <div class="page-title">{{ title }}</div>
      <div v-if="subtitle || $slots.subtitle" class="page-sub">
        <slot name="subtitle">{{ subtitle }}</slot>
      </div>
    </div>
    <div v-if="$slots.actions" class="page-header-actions">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  title: string
  subtitle?: string
}>()
</script>

<style scoped>
.page-header-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
</style>
