<template>
  <div class="min-h-screen bg-transparent text-gray-100">
    <!-- Mobile sidebar overlay -->
    <div
      v-if="appStore.sidebarOpen"
      class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 lg:hidden"
      @click="appStore.toggleSidebar()"
    />

    <!-- Sidebar -->
    <aside
      :class="[
        'fixed top-0 left-0 z-50 h-full w-64 bg-[#10141d]/95 border-r border-white/10 shadow-2xl transition-transform duration-200 backdrop-blur',
        appStore.sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
      ]"
    >
      <div class="flex items-center gap-3 h-16 px-4 border-b border-white/10">
        <div class="h-9 w-9 rounded-lg bg-gradient-to-br from-yellow-300 to-amber-500 text-black font-black grid place-items-center shadow">A</div>
        <span class="font-black text-lg tracking-tight text-white">Atelier Moto</span>
      </div>

      <nav class="p-3 space-y-1">
        <SidebarLink
          v-for="item in menuItems"
          :key="item.to"
          :to="item.to"
          :icon="item.icon"
          :label="item.label"
          :section="item.section"
        />
      </nav>

      <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-white/10 bg-black/20">
        <div class="flex items-center gap-3">
          <UAvatar :text="auth.user.value?.prenom?.charAt(0)" size="sm" />
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium truncate text-white">{{ auth.user.value?.prenom }} {{ auth.user.value?.nom }}</p>
            <p class="text-xs text-gray-400 truncate">{{ auth.user.value?.role }}</p>
          </div>
          <UButton icon="i-heroicons-arrow-right-on-rectangle" variant="ghost" size="xs" @click="auth.logout()" />
        </div>
      </div>
    </aside>

    <!-- Main content -->
    <div class="lg:pl-64">
      <!-- Top bar -->
      <header class="sticky top-0 z-30 bg-[#0d121a]/80 backdrop-blur border-b border-white/10 h-16 flex items-center px-4 gap-4">
        <UButton
          icon="i-heroicons-bars-3"
          variant="ghost"
          class="lg:hidden"
          @click="appStore.toggleSidebar()"
        />
        <div class="flex-1" />
        <span class="text-sm text-gray-300">{{ auth.user.value?.atelier_nom }}</span>
        <UColorModeButton />
      </header>

      <!-- Page content -->
      <main class="p-4 lg:p-6">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
const auth = useAuth()
const appStore = useAppStore()

const menuItems = computed(() => {
  const items = [
    { to: '/', icon: 'i-heroicons-home', label: 'Dashboard', section: 'dashboard' },
    { to: '/rdv', icon: 'i-heroicons-calendar-days', label: 'Rendez-vous', section: 'rdv' },
    { to: '/planning', icon: 'i-heroicons-table-cells', label: 'Planning', section: 'planning' },
    { to: '/clients', icon: 'i-heroicons-users', label: 'Clients', section: 'clients' },
    { to: '/workshop', icon: 'i-heroicons-wrench', label: 'Atelier', section: 'workshop' },
    { to: '/ordres', icon: 'i-heroicons-clipboard-document-list', label: 'Ordres', section: 'or' },
    { to: '/devis', icon: 'i-heroicons-document-text', label: 'Devis', section: 'devis' },
    { to: '/facturation', icon: 'i-heroicons-banknotes', label: 'Facturation', section: 'facturation' },
    { to: '/stock', icon: 'i-heroicons-cube', label: 'Stock', section: 'stock' },
    { to: '/motos', icon: 'i-heroicons-truck', label: 'Catalogue', section: 'motos' },
    { to: '/mecanicien', icon: 'i-heroicons-user-circle', label: 'Espace Méca', section: 'mecanicien' },
    { to: '/admin', icon: 'i-heroicons-cog-6-tooth', label: 'Admin', section: 'admin' },
  ]
  return items.filter(i => auth.hasSection(i.section))
})
</script>
