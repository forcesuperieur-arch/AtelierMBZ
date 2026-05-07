<template>
  <div class="page-header">
    <div class="page-header-main">
      <NuxtLink v-if="backTo" :to="backTo" class="page-header-back" :aria-label="backLabel">◀</NuxtLink>
      <div style="min-width:0;">
        <div class="page-title">
          <span>{{ title }}</span>
          <slot name="badge" />
        </div>
        <div v-if="subtitle || $slots.subtitle" class="page-sub">
          <slot name="subtitle">{{ subtitle }}</slot>
        </div>
      </div>
    </div>
    <div v-if="$slots.actions" class="page-header-actions">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  title: string
  subtitle?: string
  backTo?: string
  backLabel?: string
}>(), {
  backLabel: 'Retour',
})
</script>

<style scoped>
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 24px;
  flex-wrap: wrap;
}

.page-header-main {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
  flex: 1;
}
.page-title {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.page-header-back {
  color: var(--text-subtle);
  text-decoration: none;
  font-size: 18px;
  line-height: 1;
  padding: 4px;
  border-radius: 6px;
  transition: color var(--transition), background var(--transition);
}
.page-header-back:hover {
  color: var(--text);
  background: rgba(255,255,255,0.05);
}
.page-header-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
</style>
