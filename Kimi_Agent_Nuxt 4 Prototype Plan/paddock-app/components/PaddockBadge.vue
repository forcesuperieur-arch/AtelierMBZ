<template>
  <span
    :class="[
      'inline-flex items-center rounded-full font-medium transition-colors',
      size === 'sm' ? 'px-2 py-0.5 text-[11px]' : 'px-2.5 py-0.5 text-xs',
      variant === 'success' || color === 'green'
        ? 'bg-emerald-50 text-emerald-700'
        : variant === 'danger' || color === 'red'
        ? 'bg-red-50 text-red-700'
        : variant === 'warning' || color === 'orange'
        ? 'bg-orange-50 text-orange-700'
        : variant === 'info'
        ? 'bg-blue-50 text-blue-700'
        : 'bg-gray-100 text-text-secondary',
      pulse && 'animate-pulse',
    ]"
  >
    <slot />
  </span>
</template>

<script setup lang="ts">
interface Props {
  variant?: 'success' | 'warning' | 'danger' | 'gray' | 'info'
  color?: 'green' | 'red' | 'orange' | 'gray'
  size?: 'sm' | 'md'
  pulse?: boolean
}
withDefaults(defineProps<Props>(), { variant: undefined, color: 'gray', size: 'md', pulse: false })
</script>
