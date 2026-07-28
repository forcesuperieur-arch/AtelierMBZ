<template>
  <div style="position:relative;height:220px;width:100%;">
    <Line :data="data" :options="mergedOptions" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Line } from 'vue-chartjs'
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler } from 'chart.js'
import type { ChartData, ChartOptions } from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler)

const props = defineProps<{
  data: ChartData<'line'>
  options?: ChartOptions<'line'>
}>()

const base: ChartOptions<'line'> = {
  responsive: true,
  maintainAspectRatio: false,
  interaction: { mode: 'index', intersect: false },
  plugins: {
    legend: { display: true, labels: { color: '#9CA3AF', font: { size: 11 } } },
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
      grid: { color: 'rgba(255,255,255,0.05)' },
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

/**
 * Fusion d'un niveau sur `plugins` et `scales` : un appelant qui personnalise
 * la légende ne doit pas perdre au passage le style des infobulles (ce que
 * faisait l'ancien `...props.options`, qui remplaçait le bloc entier).
 */
const mergedOptions = computed<ChartOptions<'line'>>(() => {
  const o = props.options ?? {}
  return {
    ...base,
    ...o,
    plugins: { ...base.plugins, ...(o.plugins ?? {}) },
    scales: { ...base.scales, ...(o.scales ?? {}) },
  } as ChartOptions<'line'>
})
</script>
