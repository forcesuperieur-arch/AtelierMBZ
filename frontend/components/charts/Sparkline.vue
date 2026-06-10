<template>
  <div :style="{ position: 'relative', height: height + 'px', width: '100%' }">
    <canvas ref="canvas"></canvas>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch, onUnmounted } from 'vue'
import {
  Chart as ChartJS,
  LineElement,
  PointElement,
  LinearScale,
  CategoryScale,
  type ChartData,
  type ChartOptions,
} from 'chart.js'
import 'chart.js/auto'

ChartJS.register(LineElement, PointElement, LinearScale, CategoryScale)

const props = defineProps<{
  data: number[]
  labels?: string[]
  color?: string
  fill?: boolean
  height?: number
}>()

const canvas = ref<HTMLCanvasElement | null>(null)
let chart: ChartJS | null = null
const height = props.height ?? 40

function buildData(): ChartData<'line'> {
  const labels = props.labels ?? props.data.map((_, i) => String(i))
  const c = props.color ?? '#FFD200'
  return {
    labels,
    datasets: [{
      data: props.data,
      borderColor: c,
      backgroundColor: props.fill ? c + '20' : 'transparent',
      borderWidth: 2,
      pointRadius: 0,
      pointHoverRadius: 3,
      fill: props.fill ?? false,
      tension: 0.4,
    }],
  }
}

const defaultOptions: ChartOptions<'line'> = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false }, tooltip: { enabled: false } },
  scales: {
    x: { display: false },
    y: { display: false, min: 0 },
  },
  animation: { duration: 800 },
}

function init() {
  if (!canvas.value) return
  if (chart) chart.destroy()
  chart = new ChartJS(canvas.value, {
    type: 'line',
    data: buildData(),
    options: defaultOptions,
  })
}

onMounted(init)
watch(() => [props.data, props.labels], () => {
  if (chart) {
    chart.data = buildData()
    chart.update()
  }
}, { deep: true })
onUnmounted(() => chart?.destroy())
</script>
