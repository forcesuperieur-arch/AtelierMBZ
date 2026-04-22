<template>
  <div :class="['alert', `alert--${variant}`]" role="alert">
    <div class="alert-title">
      <span v-if="icon">{{ icon }}</span>
      <span>{{ title }}</span>
    </div>
    <div v-if="$slots.default || description" class="alert-body">
      <slot>{{ description }}</slot>
    </div>
  </div>
</template>

<script setup lang="ts">
type Variant = 'success' | 'warning' | 'danger' | 'info'

withDefaults(defineProps<{
  variant?: Variant
  title: string
  description?: string
  icon?: string
}>(), {
  variant: 'info',
})
</script>
