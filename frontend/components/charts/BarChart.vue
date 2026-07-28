<template>
  <div style="position:relative;height:220px;width:100%;">
    <Bar :data="data" :options="mergedOptions" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Bar } from 'vue-chartjs'
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend } from 'chart.js'
import type { ChartData, ChartOptions } from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend)

const props = defineProps<{
  data: ChartData<'bar'>
  options?: ChartOptions<'bar'>
}>()

const base: ChartOptions<'bar'> = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      backgroundColor: 'rgba(15, 23, 42, 0.95)',
      titleColor: '#E8E9ED',
      bodyColor: '#D1D5DB',
      borderColor: 'rgba(255,255,255,0.08)',
      borderWidth: 1,
      padding: 10,
      cornerRadius: 8,
    },
  },
  scales: {
    x: {
      grid: { display: false },
      // #6B7280 fait 3,6:1 sur nos fonds : sous le minimum WCAG AA.
      ticks: { color: '#9CA3AF', font: { size: 10 } },
    },
    y: {
      grid: { color: 'rgba(255,255,255,0.05)' },
      ticks: { color: '#9CA3AF', font: { size: 10 } },
      beginAtZero: true,
    },
  },
}

/** Fusion d'un niveau : personnaliser la légende ne doit pas effacer l'infobulle. */
const mergedOptions = computed<ChartOptions<'bar'>>(() => {
  const o = props.options ?? {}
  return {
    ...base,
    ...o,
    plugins: { ...base.plugins, ...(o.plugins ?? {}) },
    scales: { ...base.scales, ...(o.scales ?? {}) },
  } as ChartOptions<'bar'>
})
</script>
