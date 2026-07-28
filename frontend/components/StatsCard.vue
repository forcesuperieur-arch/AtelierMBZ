<template>
  <div class="stat-card">
    <div class="stat-label">{{ title }}</div>
    <div class="stat-value" :style="{ color: valueColor }">{{ formattedValue }}</div>
    <div v-if="trend !== undefined" class="stat-delta" :style="{ color: trend >= 0 ? 'var(--success-content)' : 'var(--error-content)' }">
      {{ trend >= 0 ? '▲' : '▼' }} {{ Math.abs(trend) }}% vs mois dernier
    </div>
    <div v-if="subtitle" class="stat-delta" style="color: var(--content-3);">{{ subtitle }}</div>
    <div class="stat-bar">
      <div class="stat-bar-fill" :style="{ width: barWidth, background: barColor }" />
    </div>
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{
  title: string
  value: number | string
  icon?: string
  color?: string
  subtitle?: string
  trend?: number
  currency?: boolean
}>()

const formattedValue = computed(() => {
  if (props.currency && typeof props.value === 'number') {
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(props.value)
  }
  return String(props.value)
})

const colorMap: Record<string, string> = {
  primary: 'var(--accent)',
  blue: 'var(--info)',
  success: 'var(--success)',
  warning: 'var(--warning)',
  error: 'var(--error)',
  purple: 'var(--info)',
  teal: 'var(--success)',
  orange: 'var(--warning)',
}

const valueColor = computed(() => {
  return 'var(--content-1)'
})

const barColor = computed(() => {
  return colorMap[props.color || 'primary'] || 'var(--accent)'
})

const barWidth = computed(() => {
  const v = typeof props.value === 'number' ? props.value : 0
  const max = v > 100 ? v * 1.5 : 100
  return Math.min(100, (v / max) * 100) + '%'
})
</script>
