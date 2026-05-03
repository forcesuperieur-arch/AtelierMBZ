<template>
  <button
    type="button"
    class="action-link"
    :class="[variantClass]"
    @click="$emit('click')"
  >
    <slot />
  </button>
</template>

<script setup lang="ts">
interface Props {
  variant?: 'primary' | 'secondary' | 'muted'
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'primary',
})

const variantClass = computed(() => ({
  'action-primary': props.variant === 'primary',
  'action-secondary': props.variant === 'secondary',
  'action-muted': props.variant === 'muted',
}))

defineEmits<{
  click: []
}>()
</script>

<style scoped>
.action-link {
  font-size: 12px;
  font-weight: 600;
  background: none;
  border: none;
  cursor: pointer;
  padding: 2px 4px;
  border-radius: 4px;
  transition: background 150ms ease;
}
.action-link:hover {
  background: rgba(255, 255, 255, 0.05);
}
.action-primary {
  color: #FFD200;
}
.action-secondary {
  color: #93C5FD;
}
.action-muted {
  color: #9CA3AF;
}
</style>
