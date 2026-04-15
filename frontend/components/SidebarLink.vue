<template>
  <NuxtLink
    v-if="visible"
    :to="to"
    :class="['nav-btn', isActive ? 'active' : '']"
  >
    <span class="nav-icon">{{ icon }}</span>
    <span class="nav-label">{{ label }}</span>
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

<style scoped>
.nav-btn {
  width: auto;
  height: 40px;
  border-radius: 6px;
  border: none;
  background: transparent;
  color: #6B7280;
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
  background: rgba(255,255,255,0.05);
  color: #D1D5DB;
}
.nav-btn.active {
  background: rgba(245,158,11,0.08);
  color: #FFD200;
}
.nav-btn.active::before {
  content: '';
  position: absolute;
  left: -8px;
  top: 8px;
  bottom: 8px;
  width: 3px;
  border-radius: 0 3px 3px 0;
  background: #FFD200;
}
.nav-btn:focus-visible {
  outline: 2px solid #FFD200;
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
</style>
