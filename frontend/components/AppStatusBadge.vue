<template>
  <span class="status-badge" :class="[variantClass, sizeClass]">
    <slot />
  </span>
</template>

<script setup lang="ts">
interface Props {
  variant?: 'default' | 'success' | 'warning' | 'danger' | 'info'
  size?: 'sm' | 'md'
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'default',
  size: 'sm',
})

const variantClass = computed(() => ({
  'badge-default': props.variant === 'default',
  'badge-success': props.variant === 'success',
  'badge-warning': props.variant === 'warning',
  'badge-danger': props.variant === 'danger',
  'badge-info': props.variant === 'info',
}))

const sizeClass = computed(() => ({
  'badge-sm': props.size === 'sm',
  'badge-md': props.size === 'md',
}))
</script>

<style scoped>
.status-badge {
  display: inline-flex;
  align-items: center;
  border-radius: 9999px;
  font-weight: 600;
  white-space: nowrap;
}
.badge-sm {
  padding: 2px 10px;
  font-size: 12px;
}
.badge-md {
  padding: 4px 12px;
  font-size: 13px;
}
.badge-default {
  background: rgba(255, 255, 255, 0.08);
  color: #E8E9ED;
}
.badge-success {
  background: rgba(34, 197, 94, 0.12);
  color: #6EE7B7;
}
.badge-warning {
  background: rgba(245, 158, 11, 0.12);
  color: #FCD34D;
}
.badge-danger {
  background: rgba(239, 68, 68, 0.12);
  color: #FCA5A5;
}
.badge-info {
  background: rgba(59, 130, 246, 0.12);
  color: #93C5FD;
}
</style>
