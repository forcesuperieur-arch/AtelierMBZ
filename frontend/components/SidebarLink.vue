<template>
  <NuxtLink
    v-if="visible"
    :to="to"
    :class="[
      'flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all duration-200 border',
      isActive
        ? 'bg-yellow-400/15 text-yellow-300 border-yellow-300/30 font-semibold shadow-[inset_0_0_0_1px_rgba(255,210,0,0.15)]'
        : 'text-gray-300 border-transparent hover:bg-white/5 hover:text-white hover:border-white/10'
    ]"
  >
    <UIcon :name="icon" class="text-lg flex-shrink-0" :class="isActive ? 'text-yellow-300' : 'text-gray-400'" />
    <span>{{ label }}</span>
  </NuxtLink>
</template>

<script setup lang="ts">
const props = defineProps<{
  to: string
  icon: string
  label: string
  section: string
}>()

const route = useRoute()
const auth = useAuth()

const isActive = computed(() => {
  if (props.to === '/') return route.path === '/'
  return route.path.startsWith(props.to)
})

const visible = computed(() => auth.hasSection(props.section))
</script>
