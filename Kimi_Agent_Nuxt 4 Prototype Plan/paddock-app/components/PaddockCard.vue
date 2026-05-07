<template>
  <div
    :class="[
      'bg-white rounded-xl border border-border-light p-6',
      elevated ? 'shadow-card' : flat ? 'shadow-xs' : 'shadow-card',
    ]"
  >
    <div v-if="title" class="flex items-center justify-between mb-4">
      <h2 class="text-base font-bold text-text-primary">{{ title }}</h2>
      <slot name="header-extra" />
    </div>
    <slot />
  </div>
</template>

<script setup lang="ts">
interface Props {
  title?: string
  elevated?: boolean
  flat?: boolean
}
withDefaults(defineProps<Props>(), { title: undefined, elevated: false, flat: false })
</script>
