<template>
  <div class="skeleton-kpi-grid">
    <div v-for="i in count" :key="i" class="skeleton-kpi-card">
      <div class="skeleton-shimmer skeleton-kpi-title" />
      <div class="skeleton-shimmer skeleton-kpi-value" />
      <div class="skeleton-shimmer skeleton-kpi-bar" />
    </div>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  count?: number
}>(), {
  count: 4,
})
</script>

<style scoped>
.skeleton-kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}
.skeleton-kpi-card {
  background: var(--dark2);
  border: 1px solid var(--glass-border);
  border-radius: var(--radius-lg);
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.skeleton-kpi-title {
  height: 14px;
  width: 50%;
}
.skeleton-kpi-value {
  height: 32px;
  width: 35%;
}
.skeleton-kpi-bar {
  height: 4px;
  width: 100%;
  border-radius: 2px;
  margin-top: 4px;
}
</style>
