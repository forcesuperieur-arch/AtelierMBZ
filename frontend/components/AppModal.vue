<template>
  <Teleport to="body">
    <div v-if="open" class="app-modal-overlay" @click.self="open = false">
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
