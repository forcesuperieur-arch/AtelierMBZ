<template>
  <Teleport to="body">
    <div v-if="open" ref="modalRef" class="app-modal-overlay" @click.self="open = false">
      <div :class="modalClass" @click.stop>
        <div v-if="$slots.header" class="app-modal-header">
          <slot name="header" />
          <button
            type="button"
            style="background:none;border:none;color:#9CA3AF;font-size:18px;cursor:pointer"
            aria-label="Fermer la modale"
            @click="open = false"
          >
            ✕
          </button>
        </div>

        <div class="app-modal-body">
          <slot name="content">
            <slot />
          </slot>
        </div>

        <div v-if="$slots.footer" class="app-modal-footer">
          <slot name="footer" />
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { onKeyStroke } from '@vueuse/core'
import { useFocusTrap } from '@vueuse/integrations/useFocusTrap'

const open = defineModel<boolean>('open', { default: false })

const props = withDefaults(defineProps<{
  size?: 'sm' | 'md' | 'lg' | 'xl'
}>(), {
  size: 'md',
})

const modalRef = ref<HTMLElement | null>(null)

const { activate, deactivate } = useFocusTrap(modalRef, {
  immediate: false,
  escapeDeactivates: false,
  clickOutsideDeactivates: false,
})

watch(open, (isOpen) => {
  if (isOpen) {
    nextTick(() => activate())
  } else {
    deactivate()
  }
})

onKeyStroke('Escape', (e) => {
  if (open.value) {
    e.preventDefault()
    open.value = false
  }
})

const modalClass = computed(() => {
  const classes = ['app-modal-card']
  if (props.size === 'xl') classes.push('app-modal-xl')
  else if (props.size === 'lg') classes.push('app-modal-lg')
  else if (props.size === 'sm') classes.push('app-modal-sm')
  return classes
})
</script>
