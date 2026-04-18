<template>
  <div class="vo-nav">
    <NuxtLink
      v-for="item in items"
      :key="item.to"
      :to="item.to"
      class="vo-nav-link"
      :class="{ 'is-active': isActive(item.to) }"
    >
      <span>{{ item.icon }}</span>
      <span>{{ item.label }}</span>
    </NuxtLink>
  </div>
</template>

<script setup lang="ts">
const route = useRoute()

const items = [
  { to: '/vo', label: 'Dashboard', icon: '🏷️' },
  { to: '/vo/rachats', label: 'Rachats', icon: '🏍️' },
  { to: '/vo/depots', label: 'Dépôts', icon: '📋' },
  { to: '/vo/remises-en-etat', label: 'Remises en état', icon: '🛠️' },
  { to: '/vo/livre-police', label: 'Livre de Police', icon: '📕' },
  { to: '/vo/factures', label: 'Factures', icon: '💳' },
]

function isActive(path: string) {
  if (path === '/vo') return route.path === '/vo'
  return route.path === path || route.path.startsWith(`${path}/`)
}
</script>

<style scoped>
.vo-nav {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 18px;
}

.vo-nav-link {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  border-radius: 999px;
  text-decoration: none;
  color: #9ca3af;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.06);
  font-size: 13px;
  font-weight: 600;
  transition: all 0.2s ease;
}

.vo-nav-link:hover,
.vo-nav-link.is-active {
  color: #090b10;
  background: #f59e0b;
  border-color: rgba(245, 158, 11, 0.5);
}
</style>