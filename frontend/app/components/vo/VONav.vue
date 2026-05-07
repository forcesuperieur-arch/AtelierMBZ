<template>
  <div class="vo-nav">
    <NuxtLink
      v-for="item in items"
      :key="item.to"
      :to="item.to"
      class="vo-nav-link"
      :class="{ 'is-active': isActive(item.to) }"
    >
      <UIcon :name="item.icon" class="w-4 h-4" />
      <span>{{ item.label }}</span>
    </NuxtLink>
  </div>
</template>

<script setup lang="ts">
const route = useRoute()

const items = [
  { to: '/vo', label: 'Stat', icon: 'i-heroicons-tag' },
  { to: '/vo/rachats', label: 'Rachats', icon: 'i-heroicons-shopping-bag' },
  { to: '/vo/depots', label: 'Dépôts', icon: 'i-heroicons-clipboard-document-list' },
  { to: '/vo/remises-en-etat', label: 'Remises en état', icon: 'i-heroicons-wrench-screwdriver' },
  { to: '/vo/livre-police', label: 'Livre de Police', icon: 'i-heroicons-book-open' },
  { to: '/vo/factures', label: 'Factures', icon: 'i-heroicons-credit-card' },
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
  color: var(--text-secondary);
  background: var(--bg-surface);
  border: 1px solid var(--border-default);
  font-size: 13px;
  font-weight: 600;
  transition: all 0.2s ease;
}

.vo-nav-link:hover,
.vo-nav-link.is-active {
  color: #fff;
  background: var(--accent);
  border-color: var(--accent);
}
</style>