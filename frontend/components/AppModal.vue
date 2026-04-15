<template>
  <Teleport to="body">
    <div v-if="open" class="app-modal-overlay" @click.self="open = false">
      <div :class="modalClass">
        <slot name="content">
          <slot />
        </slot>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
const open = defineModel<boolean>('open', { default: false })

const props = withDefaults(defineProps<{
  size?: 'sm' | 'md' | 'lg' | 'xl'
}>(), {
  size: 'md',
})

const modalClass = computed(() => {
  const classes = ['app-modal-card']
  if (props.size === 'xl') classes.push('app-modal-xl')
  else if (props.size === 'lg') classes.push('app-modal-lg')
  else if (props.size === 'sm') classes.push('app-modal-sm')
  return classes
})
</script>
