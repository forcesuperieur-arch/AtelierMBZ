<template>
  <div style="position:relative;height:220px;width:100%;">
    <Line :data="themedData" :options="themedOptions" />
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
    legend: { display: true, labels: { color: 'var(--content-3)', font: { size: 11 } } },
    tooltip: {
      backgroundColor: 'var(--surface-1)',
      titleColor: 'var(--content-1)',
      bodyColor: 'var(--content-2)',
      borderColor: 'var(--border-2)',
      borderWidth: 1,
      padding: 10,
      cornerRadius: 8,
    },
  },
  scales: {
    x: {
      grid: { color: 'var(--viz-grid)' },
      // var(--content-3) fait 3,6:1 sur nos fonds : sous le minimum WCAG AA.
      ticks: { color: 'var(--content-3)', font: { size: 10 } },
    },
    y: {
      grid: { color: 'var(--viz-grid)' },
      ticks: { color: 'var(--content-3)', font: { size: 10 } },
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
/* Chart.js peint dans un <canvas>, qui ne résout pas les propriétés CSS
   personnalisées : on convertit les tokens en valeurs calculées, et on
   recalcule à chaque bascule de thème. */
const themedData = useThemedChart(() => props.data)
const themedOptions = useThemedChart(() => mergedOptions.value)
</script>
