<template>
  <UCard class="relative overflow-hidden">
    <div class="flex items-start justify-between">
      <div>
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ title }}</p>
        <p class="text-2xl font-bold mt-1">{{ formattedValue }}</p>
        <p v-if="subtitle" class="text-xs text-gray-400 mt-1">{{ subtitle }}</p>
      </div>
      <div :class="['p-2 rounded-lg', bgColor]">
        <UIcon :name="icon" class="text-xl" :class="iconColor" />
      </div>
    </div>
    <div v-if="trend !== undefined" class="mt-2 flex items-center gap-1 text-xs">
      <UIcon
        :name="trend >= 0 ? 'i-heroicons-arrow-trending-up' : 'i-heroicons-arrow-trending-down'"
        :class="trend >= 0 ? 'text-green-500' : 'text-red-500'"
      />
      <span :class="trend >= 0 ? 'text-green-500' : 'text-red-500'">
        {{ Math.abs(trend) }}%
      </span>
      <span class="text-gray-400">vs mois dernier</span>
    </div>
  </UCard>
</template>

<script setup lang="ts">
const props = defineProps<{
  title: string
  value: number | string
  icon: string
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

const bgColor = computed(() => {
  const c = props.color || 'primary'
  return `bg-${c}-50 dark:bg-${c}-950`
})

const iconColor = computed(() => {
  const c = props.color || 'primary'
  return `text-${c}-500`
})
</script>
