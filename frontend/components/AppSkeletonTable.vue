<template>
  <div class="skeleton-table">
    <div class="skeleton-row skeleton-header">
      <div
        v-for="c in cols"
        :key="'h-' + c"
        class="skeleton-shimmer skeleton-cell skeleton-cell-header"
      />
    </div>
    <div
      v-for="r in rows"
      :key="'r-' + r"
      class="skeleton-row"
    >
      <div
        v-for="c in cols"
        :key="'r-' + r + '-c-' + c"
        class="skeleton-shimmer skeleton-cell"
        :class="{ 'skeleton-cell-narrow': c > 1 }"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  rows?: number
  cols?: number
}>(), {
  rows: 5,
  cols: 4,
})
</script>

<style scoped>
.skeleton-table {
  background: var(--dark2);
  border: 1px solid var(--glass-border);
  border-radius: var(--radius-lg);
  overflow: hidden;
}
.skeleton-row {
  display: grid;
  grid-template-columns: repeat(var(--cols, 4), 1fr);
  gap: 16px;
  padding: 12px 14px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.04);
  align-items: center;
}
.skeleton-row:last-child {
  border-bottom: none;
}
.skeleton-header {
  background: rgba(255, 255, 255, 0.03);
  border-bottom: 1px solid var(--glass-border);
}
.skeleton-cell {
  height: 14px;
  width: 100%;
}
.skeleton-cell-header {
  height: 12px;
  width: 70%;
}
.skeleton-cell-narrow {
  width: 60%;
}
</style>
