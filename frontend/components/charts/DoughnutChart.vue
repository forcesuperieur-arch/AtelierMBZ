<template>
  <div style="position:relative;height:200px;width:100%;display:flex;align-items:center;justify-content:center;">
    <Doughnut :data="data" :options="mergedOptions" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Doughnut } from 'vue-chartjs'
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js'
import type { ChartData, ChartOptions } from 'chart.js'

ChartJS.register(ArcElement, Tooltip, Legend)

const props = defineProps<{
  data: ChartData<'doughnut'>
  options?: ChartOptions<'doughnut'>
}>()

const mergedOptions = computed<ChartOptions<'doughnut'>>(() => ({
  responsive: true,
  maintainAspectRatio: false,
  cutout: '65%',
  plugins: {
    legend: {
      position: 'right',
      labels: { color: '#9CA3AF', font: { size: 11 }, padding: 16, boxWidth: 12 },
    },
    tooltip: {
      backgroundColor: 'rgba(15, 23, 42, 0.95)',
      titleColor: '#E8E9ED',
      bodyColor: '#D1D5DB',
      borderColor: 'rgba(255,255,255,0.08)',
      borderWidth: 1,
      padding: 10,
      cornerRadius: 8,
      callbacks: {
        label: (ctx: any) => {
          const val = ctx.parsed
          const total = ctx.dataset.data.reduce((a: number, b: number) => a + b, 0)
          const pct = total ? Math.round((val / total) * 100) : 0
          return ` ${ctx.label}: ${val} (${pct}%)`
        },
      },
    },
  },
  ...props.options,
}))
</script>
