<template>
  <UCard class="kpi-card">
    <div class="kpi-label">{{ label }}</div>
    <div class="kpi-value" :class="valueClass">{{ value }}</div>
  </UCard>
</template>

<script setup lang="ts">
interface Props {
  label: string
  value: string | number
  variant?: 'default' | 'danger' | 'warning' | 'success'
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'default',
})

const valueClass = computed(() => ({
  'text-text-primary': props.variant === 'default',
  'text-red-400': props.variant === 'danger',
  'text-amber-400': props.variant === 'warning',
  'text-emerald-400': props.variant === 'success',
}))
</script>

<style scoped>
.kpi-card {
  padding: 14px;
}
.kpi-label {
  font-size: 12px;
  color: #9CA3AF;
  margin-bottom: 4px;
}
.kpi-value {
  font-size: 22px;
  font-weight: 700;
}
</style>
