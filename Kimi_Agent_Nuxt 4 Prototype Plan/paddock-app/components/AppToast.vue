<template>
  <Teleport to="body">
    <div class="fixed bottom-6 right-6 z-toast flex flex-col gap-2 pointer-events-none">
      <TransitionGroup name="toast">
        <div
          v-for="toast in visibleToasts"
          :key="toast.id"
          class="pointer-events-auto flex items-center gap-3 px-4 py-3 rounded-xl shadow-card text-sm font-medium min-w-[280px] max-w-[400px]"
          :class="typeClasses[toast.type]"
        >
          <span class="text-base">{{ typeIcons[toast.type] }}</span>
          <span class="flex-1">{{ toast.message }}</span>
          <button
            class="inline-flex items-center justify-center w-6 h-6 rounded-md hover:bg-black/10 transition-colors"
            @click="removeToast(toast.id)"
          >
            ✕
          </button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { storeToRefs } from 'pinia'

const appStore = useAppStore()
const { visibleToasts } = storeToRefs(appStore)
const { removeToast } = appStore

const typeClasses: Record<string, string> = {
  success: 'bg-success text-white',
  error: 'bg-danger text-white',
  warning: 'bg-warning text-white',
  info: 'bg-info text-white',
}

const typeIcons: Record<string, string> = {
  success: '✅',
  error: '❌',
  warning: '⚠️',
  info: 'ℹ️',
}
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease-out;
}
.toast-enter-from {
  transform: translateY(100%);
  opacity: 0;
}
.toast-leave-to {
  transform: translateY(-20%);
  opacity: 0;
}
</style>
