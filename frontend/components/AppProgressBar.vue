<template>
  <div class="progress-track" :class="[`h-${height}`]">
    <div
      class="progress-fill"
      :class="[`bg-${colorVariant}`]"
      :style="{ width: `${Math.min((value / max) * 100, 100)}%` }"
    />
  </div>
</template>

<script setup lang="ts">
const props = withDefaults(defineProps<{
  value: number
  max: number
  color?: string
  height?: string
}>(), {
  color: '#FFD200',
  height: '8px',
})

const colorVariant = computed(() => {
  const map: Record<string, string> = {
    '#FFD200': 'primary',
    '#14B8A6': 'teal',
    '#8B5CF6': 'violet',
    '#10B981': 'green',
    '#34D399': 'emerald',
    '#EF4444': 'red',
    '#6B7280': 'gray',
    '#FBBF24': 'amber',
    '#3B82F6': 'blue',
    '#F59E0B': 'orange',
    '#60A5FA': 'sky',
    '#A78BFA': 'purple',
    '#C4B5FD': 'lavender',
    '#5EEAD4': 'cyan',
    '#22C55E': 'success',
    '#86EFAC': 'light-green',
    '#93C5FD': 'light-blue',
    '#FCA5A5': 'light-red',
    '#FCD34D': 'light-amber',
  }
  return map[props.color] || 'primary'
})
</script>

<style scoped>
.progress-track {
  width: 100%;
  background: rgba(255, 255, 255, 0.06);
  border-radius: 999px;
  overflow: hidden;
}
.progress-fill {
  height: 100%;
  border-radius: 999px;
  transition: width 0.4s ease;
}
.h-4px { height: 4px; }
.h-6px { height: 6px; }
.h-7px { height: 7px; }
.h-8px { height: 8px; }
.bg-primary { background: #FFD200; }
.bg-teal { background: #14B8A6; }
.bg-violet { background: #8B5CF6; }
.bg-green { background: #10B981; }
.bg-emerald { background: #34D399; }
.bg-red { background: #EF4444; }
.bg-gray { background: #6B7280; }
.bg-amber { background: #FBBF24; }
.bg-blue { background: #3B82F6; }
.bg-orange { background: #F59E0B; }
.bg-sky { background: #60A5FA; }
.bg-purple { background: #A78BFA; }
.bg-lavender { background: #C4B5FD; }
.bg-cyan { background: #5EEAD4; }
.bg-success { background: #22C55E; }
.bg-light-green { background: #86EFAC; }
.bg-light-blue { background: #93C5FD; }
.bg-light-red { background: #FCA5A5; }
.bg-light-amber { background: #FCD34D; }
</style>
