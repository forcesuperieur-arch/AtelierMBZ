<template>
  <button
    :class="[
      'inline-flex items-center justify-center rounded-lg font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-accent/20',
      size === 'sm'
        ? 'px-3 py-1.5 text-xs'
        : size === 'lg'
        ? 'px-6 py-3 text-base'
        : 'px-4 py-2.5 text-sm',
      variant === 'primary'
        ? 'bg-accent text-white hover:bg-accent-hover'
        : variant === 'secondary'
        ? 'bg-white text-text-primary border border-border-light hover:bg-body-gray'
        : 'text-text-secondary hover:text-text-primary',
    ]"
  >
    <slot />
  </button>
</template>

<script setup lang="ts">
interface Props {
  variant?: 'primary' | 'secondary' | 'ghost'
  size?: 'sm' | 'md' | 'lg'
}
withDefaults(defineProps<Props>(), { variant: 'primary', size: 'md' })
</script>
