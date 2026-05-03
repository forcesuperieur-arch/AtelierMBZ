<template>
  <div v-if="data.length" class="mini-chart">
    <div v-for="item in data" :key="item.label" class="mini-chart-item">
      <span class="text-xs-muted">{{ item.value }}</span>
      <div class="mini-chart-bar-track">
        <div
          class="mini-chart-bar-fill"
          :style="{ height: Math.max(10, Math.round(item.value / item.max * 100)) + '%' }"
        />
      </div>
      <span class="text-xs-subtle">{{ item.label }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  data: { label: string; value: number; max: number }[]
}>()
</script>

<style scoped>
.mini-chart {
  display: flex;
  align-items: flex-end;
  gap: 8px;
  min-height: 180px;
  overflow-x: auto;
  padding-top: 6px;
}
.mini-chart-item {
  min-width: 44px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
}
.mini-chart-bar-track {
  width: 24px;
  height: 120px;
  display: flex;
  align-items: flex-end;
}
.mini-chart-bar-fill {
  width: 100%;
  border-radius: 8px 8px 4px 4px;
  background: linear-gradient(180deg, #FFD200 0%, #F59E0B 100%);
}
</style>
