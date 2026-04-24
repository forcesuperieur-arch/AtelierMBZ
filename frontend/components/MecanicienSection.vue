<template>
  <div :id="`section-${sectionKey}`" class="mecanicien-section" :class="{ expanded: isOpen }">
    <button
      type="button"
      class="mecanicien-section-header"
      :aria-expanded="isOpen"
      @click="toggle"
    >
      <div class="flex items-center gap-2">
        <span v-if="icon" class="text-base">{{ icon }}</span>
        <span class="font-semibold text-sm" style="color:#E8E9ED">{{ title }}</span>
        <span v-if="badge" class="mecanicien-section-badge">{{ badge }}</span>
      </div>
      <UIcon
        name="i-heroicons-chevron-down"
        class="w-5 h-5 text-gray-500 transition-transform duration-200 shrink-0"
        :class="{ 'rotate-180': isOpen }"
      />
    </button>
    <Transition
      enter-active-class="transition-all duration-200 ease-out"
      enter-from-class="max-h-0 opacity-0"
      enter-to-class="max-h-[2000px] opacity-100"
      leave-active-class="transition-all duration-200 ease-in"
      leave-from-class="max-h-[2000px] opacity-100"
      leave-to-class="max-h-0 opacity-0"
    >
      <div v-show="isOpen" class="mecanicien-section-body">
        <slot />
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'

const props = defineProps<{
  title: string
  sectionKey: string
  icon?: string
  badge?: string
  defaultOpen?: boolean
}>()

const STORAGE_KEY = 'mecanicien_sections_state'

const isOpen = ref(props.defaultOpen ?? false)

onMounted(() => {
  try {
    const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}')
    if (props.sectionKey in saved) {
      isOpen.value = saved[props.sectionKey]
    } else {
      isOpen.value = props.defaultOpen ?? false
    }
  } catch {
    isOpen.value = props.defaultOpen ?? false
  }
})

watch(isOpen, (val) => {
  try {
    const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}')
    saved[props.sectionKey] = val
    localStorage.setItem(STORAGE_KEY, JSON.stringify(saved))
  } catch { /* ignore */ }
})

function toggle() {
  isOpen.value = !isOpen.value
}
</script>

<style scoped>
.mecanicien-section {
  border-top: 1px solid rgba(255,255,255,0.06);
}
.mecanicien-section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  padding: 14px 0;
  background: none;
  border: none;
  color: inherit;
  cursor: pointer;
  min-height: 48px;
}
.mecanicien-section-badge {
  font-size: 11px;
  padding: 2px 8px;
  border-radius: 999px;
  background: rgba(255,210,0,0.12);
  color: #FFD200;
  font-weight: 600;
}
.mecanicien-section-body {
  overflow: hidden;
  padding-bottom: 16px;
}
</style>
