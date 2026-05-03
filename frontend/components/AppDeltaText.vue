<template>
  <span :class="variantClass"><slot /></span>
</template>

<script setup lang="ts">
const props = defineProps<{
  delta?: number | string
}>()

const variantClass = computed(() => {
  const val = Number(props.delta ?? 0)
  return val >= 0 ? 'delta-positive' : 'delta-negative'
})
</script>

<style scoped>
.delta-positive { color: #34D399; }
.delta-negative { color: #FCA5A5; }
</style>
