<template>
  <div class="text-center animate-fade-in" :class="containerClasses">
    <div class="mb-4 opacity-30 grayscale select-none leading-none" :class="iconClasses">
      {{ icon }}
    </div>
    <h3 class="font-extrabold text-text-secondary mb-2" :class="titleClasses">
      {{ title }}
    </h3>
    <p class="text-sm text-text-tertiary mb-5 max-w-sm mx-auto">
      {{ description }}
    </p>
    <NuxtLink v-if="actionTo && actionLabel" :to="actionTo">
      <PaddockButton variant="primary">{{ actionLabel }}</PaddockButton>
    </NuxtLink>
    <PaddockButton v-else-if="actionLabel" variant="primary" @click="$emit('action')">
      {{ actionLabel }}
    </PaddockButton>
  </div>
</template>

<script setup lang="ts">
interface Props {
  icon?: string
  title?: string
  description?: string
  actionLabel?: string
  actionTo?: string
  size?: 'sm' | 'md' | 'lg'
}

const props = withDefaults(defineProps<Props>(), {
  icon: '📭',
  title: 'Aucun élément',
  description: 'Commencez par ajouter un élément.',
  size: 'md',
})

defineEmits<{
  (e: 'action'): void
}>()

const containerClasses = computed(() => {
  switch (props.size) {
    case 'sm': return 'py-8'
    case 'lg': return 'py-16 lg:py-20'
    default: return 'py-12 lg:py-20'
  }
})

const iconClasses = computed(() => {
  switch (props.size) {
    case 'sm': return 'text-4xl'
    case 'lg': return 'text-8xl'
    default: return 'text-6xl'
  }
})

const titleClasses = computed(() => {
  switch (props.size) {
    case 'sm': return 'text-sm'
    case 'lg': return 'text-2xl'
    default: return 'text-lg'
  }
})
</script>
