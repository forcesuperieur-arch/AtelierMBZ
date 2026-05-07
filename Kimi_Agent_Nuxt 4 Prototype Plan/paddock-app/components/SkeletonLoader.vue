<template>
  <div class="w-full">
    <!-- Type: text -->
    <div v-if="type === 'text'" class="space-y-2 w-full">
      <div
        v-for="i in computedLines"
        :key="i"
        class="skeleton"
        :class="i === 1 ? 'h-4' : 'h-3'"
        :style="{ width: getTextWidth(i), animationDelay: `${i * 0.1}s` }"
      />
    </div>

    <!-- Type: card -->
    <div v-else-if="type === 'card'" class="bg-white rounded-xl border border-border-light shadow-card p-6 space-y-3">
      <div class="skeleton h-10 w-1/2 mb-4" />
      <div class="skeleton h-3 w-full" />
      <div class="skeleton h-3 w-5/6" />
      <div class="skeleton h-3 w-4/6" />
      <div class="skeleton h-10 w-full mt-4" />
    </div>

    <!-- Type: table -->
    <div v-else-if="type === 'table'" class="w-full space-y-2">
      <!-- Header row -->
      <div class="flex gap-2">
        <div
          v-for="c in computedColumns"
          :key="`h-${c}`"
          class="skeleton h-8 flex-1"
          :style="{ animationDelay: `${c * 0.05}s` }"
        />
      </div>
      <!-- Data rows -->
      <div
        v-for="r in computedRows"
        :key="`r-${r}`"
        class="flex gap-2"
      >
        <div
          v-for="c in computedColumns"
          :key="`r-${r}-c-${c}`"
          class="skeleton h-6 flex-1"
          :style="{ animationDelay: `${(r * computedColumns + c) * 0.03}s` }"
        />
      </div>
    </div>

    <!-- Type: kpi -->
    <div v-else-if="type === 'kpi'" class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div
        v-for="i in 4"
        :key="`kpi-${i}`"
        class="bg-white rounded-xl border border-border-light shadow-card p-5 space-y-3"
      >
        <div class="skeleton h-4 w-2/3" />
        <div class="skeleton h-8 w-1/2" />
        <div class="skeleton h-3 w-full" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
interface Props {
  type?: 'card' | 'table' | 'text' | 'kpi'
  lines?: number
  rows?: number
  columns?: number
}

const props = withDefaults(defineProps<Props>(), {
  type: 'text',
  lines: 3,
  rows: 4,
  columns: 4,
})

const computedLines = computed(() => Math.max(1, props.lines))
const computedRows = computed(() => Math.max(1, props.rows))
const computedColumns = computed(() => Math.max(1, props.columns))

function getTextWidth(index: number): string {
  const widths = ['100%', '85%', '70%', '60%', '50%', '45%', '40%']
  return widths[(index - 1) % widths.length]
}
</script>
