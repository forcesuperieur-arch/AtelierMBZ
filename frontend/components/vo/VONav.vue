<template>
  <div class="vo-nav">
    <NuxtLink
      v-for="item in items"
      :key="item.to"
      :to="item.to"
      class="vo-nav-link"
      :class="{ 'is-active': isActive(item.to) }"
    >
      <span><AppIcon :name="item.icon" /></span>
      <span>{{ item.label }}</span>
    </NuxtLink>
  </div>
</template>

<script setup lang="ts">
const route = useRoute()

const items = [
  { to: '/vo', label: 'Stat', icon: 'i-ri-price-tag-3-line' },
  { to: '/vo/rachats', label: 'Rachats', icon: 'i-ri-motorbike-line' },
  { to: '/vo/depots', label: 'Dépôts', icon: 'i-ri-clipboard-line' },
  { to: '/vo/remises-en-etat', label: 'Remises en état', icon: 'i-ri-hammer-line' },
  { to: '/vo/livre-police', label: 'Livre de Police', icon: 'i-ri-book-line' },
  { to: '/vo/factures', label: 'Factures', icon: 'i-ri-bank-card-line' },
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
  color: var(--content-3);
  background: var(--overlay-soft);
  border: 1px solid var(--border-2);
  font-size: 13px;
  font-weight: 600;
  transition: all 0.2s ease;
}

.vo-nav-link:hover,
.vo-nav-link.is-active {
  color: var(--accent-ink);
  background: var(--warning);
  border-color: var(--warning);
}
</style>