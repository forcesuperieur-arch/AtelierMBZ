<template>
  <div class="bg-white rounded-xl border border-border-light shadow-card p-6 space-y-3">
    <div
      v-for="i in computedLines"
      :key="i"
      class="h-4 rounded-md bg-body-gray animate-shimmer"
      :style="shimmerStyle"
      :class="i === 1 ? 'w-3/4' : i === computedLines ? 'w-1/2' : 'w-full'"
    />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  lines?: number
}

const props = withDefaults(defineProps<Props>(), { lines: 4 })

const computedLines = computed(() => Math.max(1, props.lines))

const shimmerStyle = {
  backgroundImage: 'linear-gradient(90deg, #e4e3de 25%, #f0efe9 50%, #e4e3de 75%)',
  backgroundSize: '200% 100%',
}
</script>
