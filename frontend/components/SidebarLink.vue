<template>
  <NuxtLink
    v-if="visible"
    :to="to"
    :class="['nav-btn', isActive ? 'active' : '']"
  >
    <span class="nav-icon">{{ icon }}</span>
    <span class="nav-label">{{ label }}</span>
    <span v-if="badgeCount && badgeCount > 0" class="nav-badge">{{ badgeCount > 99 ? '99+' : badgeCount }}</span>
  </NuxtLink>
</template>

<script setup lang="ts">
const props = defineProps<{
  to: string
  icon: string
  label: string
  section: string
  badgeCount?: number
}>()

const route = useRoute()
const auth = useAuth()

const isActive = computed(() => {
  if (props.to === '/') return route.path === '/'
  return route.path.startsWith(props.to)
})

const visible = computed(() => auth.hasSection(props.section))
</script>

<style scoped>
.nav-btn {
  width: auto;
  height: 40px;
  border-radius: 6px;
  border: none;
  background: transparent;
  color: var(--content-3);
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 12px;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  font-size: 15px;
  position: relative;
  padding: 0 16px;
  margin: 0 8px;
  font-family: inherit;
  text-decoration: none;
}
.nav-btn:hover {
  background: var(--overlay-hover);
  color: var(--content-2);
}
.nav-btn.active {
  background: var(--warning-soft);
  color: var(--accent-content);
}
.nav-btn.active::before {
  content: '';
  position: absolute;
  left: -8px;
  top: 8px;
  bottom: 8px;
  width: 3px;
  border-radius: 0 3px 3px 0;
  background: var(--accent);
}
.nav-btn:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 2px;
}
.nav-icon {
  font-size: 18px;
  flex-shrink: 0;
  width: 24px;
  text-align: center;
}
.nav-label {
  font-size: 13px;
  font-weight: 500;
  white-space: nowrap;
}
.nav-badge {
  margin-left: auto;
  background: var(--error);
  color: var(--on-error);
  font-size: 11px;
  font-weight: 700;
  min-width: 20px;
  height: 20px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 6px;
  line-height: 1;
}
</style>
