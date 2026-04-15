<template>
  <div class="stat-card">
    <div class="stat-label">{{ title }}</div>
    <div class="stat-value" :style="{ color: valueColor }">{{ formattedValue }}</div>
    <div v-if="trend !== undefined" class="stat-delta" :style="{ color: trend >= 0 ? '#10B981' : '#EF4444' }">
      {{ trend >= 0 ? '▲' : '▼' }} {{ Math.abs(trend) }}% vs mois dernier
    </div>
    <div v-if="subtitle" class="stat-delta" style="color: #6B7280;">{{ subtitle }}</div>
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
  primary: '#FFD200',
  blue: '#3B82F6',
  success: '#10B981',
  warning: '#F59E0B',
  error: '#EF4444',
  purple: '#8B5CF6',
  teal: '#14B8A6',
  orange: '#F97316',
}

const valueColor = computed(() => {
  return '#F0F1F5'
})

const barColor = computed(() => {
  return colorMap[props.color || 'primary'] || '#FFD200'
})

const barWidth = computed(() => {
  const v = typeof props.value === 'number' ? props.value : 0
  const max = v > 100 ? v * 1.5 : 100
  return Math.min(100, (v / max) * 100) + '%'
})
</script>
