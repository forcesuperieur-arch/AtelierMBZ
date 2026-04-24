<template>
  <div v-if="visibleActions.length > 0" class="quick-actions-wrapper">
    <!-- Desktop: sticky bar centered -->
    <div class="quick-actions-desktop">
      <div class="quick-actions-bar">
        <template v-for="(action, idx) in visibleActions" :key="idx">
          <NuxtLink
            v-if="action.to"
            :to="action.to"
            class="quick-action-btn"
          >
            <UIcon :name="action.icon" class="quick-action-icon" />
            <span>{{ action.label }}</span>
          </NuxtLink>
          <button
            v-else
            class="quick-action-btn"
            @click="action.onClick"
          >
            <UIcon :name="action.icon" class="quick-action-icon" />
            <span>{{ action.label }}</span>
          </button>
        </template>
      </div>
    </div>

    <!-- Mobile: FAB with vertical menu -->
    <div class="quick-actions-mobile">
      <Transition name="fab-menu">
        <div v-if="isOpen" class="quick-actions-menu">
          <template v-for="(action, idx) in visibleActions" :key="idx">
            <NuxtLink
              v-if="action.to"
              :to="action.to"
              class="quick-action-btn-mobile"
              @click="isOpen = false"
            >
              <UIcon :name="action.icon" class="quick-action-icon" />
              <span>{{ action.label }}</span>
            </NuxtLink>
            <button
              v-else
              class="quick-action-btn-mobile"
              @click="onActionClick(action)"
            >
              <UIcon :name="action.icon" class="quick-action-icon" />
              <span>{{ action.label }}</span>
            </button>
          </template>
        </div>
      </Transition>
      <button
        class="quick-actions-fab"
        :class="{ 'is-open': isOpen }"
        aria-label="Actions rapides"
        @click="isOpen = !isOpen"
      >
        <UIcon
          :name="isOpen ? 'i-heroicons-x-mark' : 'i-heroicons-plus'"
          class="quick-action-icon-lg"
        />
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
interface QuickAction {
  icon: string
  label: string
  to?: string
  onClick?: () => void
  visible?: boolean
}

const props = defineProps<{
  actions: QuickAction[]
}>()

const isOpen = ref(false)

const visibleActions = computed(() =>
  props.actions.filter(a => a.visible !== false)
)

function onActionClick(action: QuickAction) {
  isOpen.value = false
  action.onClick?.()
}

// Close mobile menu on route change
const route = useRoute()
watch(() => route.path, () => { isOpen.value = false })

// Close mobile menu on outside click
onMounted(() => {
  const handler = (e: MouseEvent) => {
    const target = e.target as HTMLElement
    if (!target.closest('.quick-actions-mobile')) {
      isOpen.value = false
    }
  }
  document.addEventListener('click', handler)
  onBeforeUnmount(() => document.removeEventListener('click', handler))
})
</script>

<style scoped>
/* Desktop sticky bar */
.quick-actions-desktop {
  position: sticky;
  bottom: 16px;
  z-index: 35;
  display: flex;
  justify-content: center;
  padding: 0 16px;
  pointer-events: none;
}
.quick-actions-bar {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: rgba(23, 27, 36, 0.92);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-lg);
  pointer-events: auto;
}
.quick-action-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  border-radius: var(--radius-sm);
  background: var(--dark3);
  border: 1px solid rgba(255, 255, 255, 0.08);
  color: var(--text);
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  transition: all var(--transition);
  white-space: nowrap;
  font-family: inherit;
}
.quick-action-btn:hover {
  border-color: var(--orange);
  color: var(--orange);
  background: rgba(255, 210, 0, 0.06);
}
.quick-action-icon {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
}

/* Mobile FAB */
.quick-actions-mobile {
  display: none;
  position: fixed;
  bottom: 16px;
  right: 16px;
  z-index: 35;
  flex-direction: column;
  align-items: flex-end;
  gap: 10px;
}
.quick-actions-fab {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--orange), #D97706);
  color: var(--orange-ink);
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: var(--shadow-lg);
  transition: all var(--transition);
}
.quick-actions-fab:hover {
  transform: scale(1.05);
}
.quick-actions-fab.is-open {
  background: var(--dark3);
  color: var(--text);
  border: 1px solid rgba(255, 255, 255, 0.12);
}
.quick-action-icon-lg {
  width: 24px;
  height: 24px;
}
.quick-actions-menu {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 10px;
  background: rgba(23, 27, 36, 0.95);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-lg);
  min-width: 180px;
}
.quick-action-btn-mobile {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border-radius: var(--radius-sm);
  background: transparent;
  border: 1px solid transparent;
  color: var(--text);
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  transition: all var(--transition);
  text-align: left;
  font-family: inherit;
}
.quick-action-btn-mobile:hover {
  background: rgba(255, 255, 255, 0.04);
  border-color: rgba(255, 255, 255, 0.08);
  color: var(--orange);
}

/* Transitions */
.fab-menu-enter-active,
.fab-menu-leave-active {
  transition: all 0.2s ease;
}
.fab-menu-enter-from,
.fab-menu-leave-to {
  opacity: 0;
  transform: translateY(10px) scale(0.95);
}

/* Responsive toggle */
@media (max-width: 1023px) {
  .quick-actions-desktop {
    display: none;
  }
  .quick-actions-mobile {
    display: flex;
  }
}
</style>
