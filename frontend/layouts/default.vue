<template>
  <div class="app">
    <!-- Mobile sidebar overlay -->
    <div
      v-if="appStore.sidebarOpen"
      class="sidebar-overlay"
      @click="appStore.toggleSidebar()"
    />

    <!-- SIDEBAR -->
    <nav
      :class="['sidebar', appStore.sidebarOpen ? 'is-open' : '', isDesktop && isSidebarCollapsed ? 'is-collapsed' : '']"
      @mouseenter="handleSidebarEnter"
      @mouseleave="handleSidebarLeave"
      @focusin="handleSidebarEnter"
      @focusout="handleSidebarFocusOut"
    >
      <button class="sidebar-logo" @click="navigateTo('/')">
        <img v-if="atelierLogoUrl" :src="atelierLogoUrl" :alt="atelierName" class="sidebar-logo-image" />
        <span v-else class="sidebar-logo-fallback">{{ atelierInitial }}</span>
        <span class="sidebar-logo-text">{{ atelierName }}</span>
      </button>

      <template v-for="(group, gIdx) in menuGroups" :key="group.key">
        <div
          v-if="!(isDesktop && isSidebarCollapsed)"
          class="sidebar-group-label"
          :class="{ 'sidebar-group-label--first': gIdx === 0 }"
        >{{ group.label }}</div>
        <SidebarLink
          v-for="item in group.items"
          :key="item.to"
          :to="item.to"
          :icon="item.icon"
          :label="item.label"
          :section="item.section"
          :badge-count="item.to === '/planning' ? notifUnreadCount : undefined"
        />
      </template>

      <div class="sidebar-spacer" />

      <div v-if="auth.hasSection('mecanicien')" class="meca-avatar" @click="navigateTo('/mecanicien')">
        {{ auth.user.value?.prenom?.charAt(0) || 'U' }}
      </div>
      <button class="nav-btn nav-profile" @click="navigateTo('/profile')">
        <UIcon name="i-heroicons-user-circle" class="nav-icon-svg" />
        <span class="nav-label">Mon profil</span>
      </button>
      <button class="nav-btn nav-logout" @click="auth.logout()">
        <UIcon name="i-heroicons-arrow-right-on-rectangle" class="nav-icon-svg" />
        <span class="nav-label">Déconnexion</span>
      </button>
    </nav>

    <!-- MAIN -->
    <div class="main-area">
      <!-- TOPBAR -->
      <header class="topbar">
        <button class="topbar-menu-btn" @click="appStore.toggleSidebar()" aria-label="Menu">
          <UIcon name="i-heroicons-bars-3" />
        </button>
        <div class="topbar-brand">
          <img v-if="atelierLogoUrl" :src="atelierLogoUrl" :alt="atelierName" class="topbar-brand-logo" />
          <span v-else class="topbar-brand-fallback">{{ atelierInitial }}</span>
          <span class="topbar-brand-name">{{ atelierName }}</span>
        </div>
        <div class="topbar-center-brand" aria-hidden="true">
          <img :src="topbarLogoUrl" alt="Paddock" class="topbar-center-logo" />
        </div>
        <span class="topbar-title">{{ currentSection }}</span>
        <div class="topbar-spacer" />
        <div v-if="canSwitchAtelierContext" class="topbar-atelier-switch">
          <span style="font-size:11px;color:#FCD34D;font-weight:700;">{{ isSuperAdmin ? 'SA' : 'SC' }}</span>
          <select v-model="activeAtelierChoice" @change="onSwitchAtelier" style="background:#1a1d26;color:#E8E9ED;border:1px solid rgba(255,255,255,0.08);border-radius:6px;padding:4px 8px;font-size:12px;">
            <option v-for="a in ateliersList" :key="a.id" :value="a.id">{{ a.nom }}</option>
          </select>
        </div>
        <AppNotificationBell :atelier-id="currentNotificationAtelierId" />
        <div class="live-dot" />
        <span class="topbar-live">LIVE</span>
        <NuxtLink v-if="auth.hasSection('rdv')" to="/rdv/new" class="topbar-new-btn">+ Nouveau RDV</NuxtLink>
      </header>

      <!-- CONTENT -->
      <main class="content">
        <NotificationPopIn filter-type="demande_complementaire" />
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
const auth = useAuth()
const appStore = useAppStore()
const atelierStore = useAtelierStore()
const route = useRoute()
const { unreadCount: notifUnreadCount, fetchUnreadCount, fetchNotifications, connect: connectNotifs, disconnect: disconnectNotifs } = useNotifications()

const atelierName = computed(() => atelierStore.branding?.nom || 'Paddock')
const atelierLogoUrl = computed(() => atelierStore.branding?.logo_url || '/branding/paddock-logo-symbol.svg')
const topbarLogoUrl = computed(() => '/branding/paddock-logo-horizontal.svg')
const atelierInitial = computed(() => atelierName.value.trim().charAt(0).toUpperCase() || 'P')
const isDesktop = ref(false)
const isSidebarCollapsed = ref(false)
let notificationsConnectTimer: ReturnType<typeof setTimeout> | null = null

function syncSidebarMode() {
  if (!process.client) return
  isDesktop.value = window.innerWidth >= 1024
  isSidebarCollapsed.value = isDesktop.value
}

function handleSidebarEnter() {
  if (isDesktop.value) isSidebarCollapsed.value = false
}

function handleSidebarLeave() {
  if (isDesktop.value) isSidebarCollapsed.value = true
}

function handleSidebarFocusOut(event: FocusEvent) {
  if (!isDesktop.value) return

  const currentTarget = event.currentTarget as HTMLElement | null
  const nextTarget = event.relatedTarget as Node | null

  if (!currentTarget || !nextTarget || !currentTarget.contains(nextTarget)) {
    isSidebarCollapsed.value = true
  }
}

onMounted(() => {
  syncSidebarMode()
  if (process.client) {
    window.addEventListener('resize', syncSidebarMode)
  }
  // Keep first paint/navigation responsive, then start realtime notifications.
  fetchUnreadCount(currentNotificationAtelierId.value)
  fetchNotifications('unacknowledged', currentNotificationAtelierId.value)
  if (currentNotificationAtelierId.value) {
    notificationsConnectTimer = setTimeout(() => {
      connectNotifs(currentNotificationAtelierId.value as number)
    }, 1500)
  }
})

onBeforeUnmount(() => {
  if (process.client) {
    window.removeEventListener('resize', syncSidebarMode)
  }
  if (notificationsConnectTimer) {
    clearTimeout(notificationsConnectTimer)
    notificationsConnectTimer = null
  }
  disconnectNotifs()
})

const sectionNames: Record<string, string> = {
  '/': 'Stat',
  '/rdv': 'Rendez-vous',
  '/planning': 'Planning',
  '/clients': 'Clients',
  '/workshop': 'Atelier',
  '/ordres': 'Dossiers atelier',
  '/devis': 'Devis',
  '/facturation': 'Facturation',
  '/stock': 'Stock',
  '/motos': 'Catalogue',
  '/suivi': 'Suivi Live',
  '/tarifs': 'Tarifs',
  '/mecanicien': 'Espace Mécanicien',
  '/admin': 'Administration',
  '/vo': 'Véhicules d\'Occasion',
}

const currentSection = computed(() => {
  const path = route.path
  if (path === '/') return 'Stat'
  const base = '/' + path.split('/')[1]
  return sectionNames[base] || 'Paddock'
})

const menuItems = computed(() => {
  const items = [
    { to: '/', icon: 'i-heroicons-chart-bar', label: 'Stat', section: 'dashboard', group: 'aujourdhui' },
    { to: '/planning', icon: 'i-heroicons-calendar-days', label: 'Planning', section: 'planning', group: 'aujourdhui' },
    { to: '/workshop', icon: 'i-heroicons-wrench-screwdriver', label: 'Ponts & Méca', section: 'workshop', group: 'aujourdhui' },
    { to: '/suivi', icon: 'i-heroicons-eye', label: 'Suivi Live', section: 'suivi', group: 'aujourdhui' },
    { to: '/ordres', icon: 'i-heroicons-clipboard-document-list', label: 'Dossiers atelier', section: 'or', group: 'dossiers' },
    { to: '/devis', icon: 'i-heroicons-document-text', label: 'Devis', section: 'devis', group: 'dossiers' },
    // TODO (2026-04-22) : modules en cours de réécriture — réactiver quand le flux sera vérifié end-to-end
    // { to: '/facturation', icon: '💳', label: 'Factures', section: 'facturation', group: 'dossiers' },
    // { to: '/stock', icon: '📦', label: 'Stock', section: 'stock', group: 'dossiers' },
    { to: '/vo', icon: 'i-heroicons-tag', label: 'VO', section: 'vo', group: 'dossiers' },
    { to: '/clients', icon: 'i-heroicons-user-group', label: 'Clients', section: 'clients', group: 'referentiels' },
    { to: '/motos', icon: 'i-heroicons-truck', label: 'Fiches moto', section: 'motos', group: 'referentiels' },
    { to: '/admin', icon: 'i-heroicons-cog-6-tooth', label: 'Administration', section: 'admin', group: 'systeme' },
  ]
  return items.filter(i => auth.hasSection(i.section) && (i.section !== 'dashboard' || auth.hasStatsAccess()))
})

const menuGroups = computed(() => {
  const groups: Array<{ key: string; label: string; items: typeof menuItems.value }> = [
    { key: 'aujourdhui', label: 'Aujourd\u2019hui', items: [] },
    { key: 'dossiers', label: 'Dossiers', items: [] },
    { key: 'referentiels', label: 'Référentiels', items: [] },
    { key: 'systeme', label: 'Système', items: [] },
  ]
  for (const item of menuItems.value) {
    const g = groups.find(x => x.key === item.group)
    if (g) g.items.push(item)
  }
  return groups.filter(g => g.items.length > 0)
})

const api = useApi()
const toast = useToast()
const activeAtelierCookie = useCookie<string | null>('active_atelier_id', { default: () => null })
const currentNotificationAtelierId = computed(() => {
  const cookieValue = Number(activeAtelierCookie.value ?? 0)
  if (Number.isFinite(cookieValue) && cookieValue > 0) return cookieValue

  const userValue = Number(auth.user.value?.atelier_id || 0)
  return Number.isFinite(userValue) && userValue > 0 ? userValue : null
})
const isSuperAdmin = computed(() => (auth.user.value?.roles || []).includes('ROLE_SUPER_ADMIN'))
const isServiceClient = computed(() => (auth.user.value?.roles || []).includes('ROLE_SERVICE_CLIENT'))
const canSwitchAtelierContext = computed(() => isSuperAdmin.value || isServiceClient.value)

function normalizeAtelierChoice(value: any): string {
  const normalized = String(value ?? '').trim()
  return normalized && normalized !== 'all' ? normalized : ''
}

const userDefaultAtelierChoice = computed(() => normalizeAtelierChoice(auth.user.value?.atelier_id))
const ateliersList = ref<any[]>([])
const activeAtelierChoice = ref<any>(normalizeAtelierChoice(activeAtelierCookie.value) || userDefaultAtelierChoice.value || '')

async function loadAteliers() {
  if (!canSwitchAtelierContext.value) return
  try {
    const res = await api.get('/auth/rdv-ateliers')
    ateliersList.value = Array.isArray(res) ? res : (res?.member || res?.['hydra:member'] || [])

    const validChoices = new Set(
      ateliersList.value
        .map((atelier: any) => normalizeAtelierChoice(atelier?.id))
        .filter(Boolean)
    )
    const requestedChoice = normalizeAtelierChoice(activeAtelierCookie.value)
    const fallbackChoice = userDefaultAtelierChoice.value || normalizeAtelierChoice(ateliersList.value[0]?.id)

    if ((!requestedChoice || !validChoices.has(requestedChoice)) && fallbackChoice) {
      activeAtelierChoice.value = fallbackChoice
      activeAtelierCookie.value = fallbackChoice
      return
    }

    activeAtelierChoice.value = requestedChoice || fallbackChoice
  } catch { ateliersList.value = [] }
}

async function onSwitchAtelier() {
  try {
    const requestedAtelierId = normalizeAtelierChoice(activeAtelierChoice.value)
    if (!requestedAtelierId) {
      throw new Error('Aucun atelier sélectionné')
    }

    const res = await api.post('/auth/switch-atelier', { atelier_id: requestedAtelierId })
    activeAtelierCookie.value = String(res.active_atelier_id ?? requestedAtelierId)
    activeAtelierChoice.value = String(res.active_atelier_id ?? requestedAtelierId)
    toast.add({
      title: 'Atelier actif changé',
      description: res.atelier_nom || 'Contexte atelier mis à jour',
      color: 'success',
    })
    // Reload current page to refresh filtered data
    window.location.reload()
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e?.message, color: 'error' })
  }
}

watch(canSwitchAtelierContext, (enabled) => { if (enabled) loadAteliers() }, { immediate: true })
watch(currentNotificationAtelierId, (atelierId) => {
  fetchUnreadCount(atelierId)
  fetchNotifications('unacknowledged', atelierId)
  if (atelierId) {
    connectNotifs(atelierId)
  } else {
    disconnectNotifs()
  }
}, { immediate: false })
watch([activeAtelierCookie, userDefaultAtelierChoice, canSwitchAtelierContext], ([cookieValue, defaultValue, canSwitch]) => {
  if (!canSwitch) return
  activeAtelierChoice.value = normalizeAtelierChoice(cookieValue) || normalizeAtelierChoice(defaultValue) || normalizeAtelierChoice(ateliersList.value[0]?.id) || ''
}, { immediate: true })
</script>

<style scoped>
.app {
  display: flex;
  height: 100vh;
  min-height: 600px;
}

/* === Sidebar overlay (mobile) === */
.sidebar-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.6);
  backdrop-filter: blur(4px);
  z-index: 40;
}
@media (min-width: 1024px) {
  .sidebar-overlay { display: none; }
}

/* === SIDEBAR === */
.sidebar {
  width: 220px;
  background: linear-gradient(180deg, #111218 0%, #0E0F15 100%);
  border-right: 1px solid rgba(255,255,255,0.06);
  display: flex;
  flex-direction: column;
  padding: 16px 0;
  gap: 2px;
  z-index: 50;
  flex-shrink: 0;
  overflow-y: auto;
  overflow-x: hidden;
}
.sidebar::-webkit-scrollbar { width: 0; }

@media (min-width: 1024px) {
  .sidebar {
    transition: width 0.22s ease;
  }

  .sidebar.is-collapsed {
    width: 76px;
  }

  .sidebar.is-collapsed .sidebar-logo {
    justify-content: center;
    padding-left: 0;
    padding-right: 0;
    gap: 0;
  }

  .sidebar.is-collapsed .sidebar-logo-text,
  .sidebar.is-collapsed :deep(.nav-label),
  .sidebar.is-collapsed .nav-label {
    display: none;
  }

  .sidebar.is-collapsed :deep(.nav-btn),
  .sidebar.is-collapsed .nav-logout {
    justify-content: center;
    gap: 0;
    padding-left: 0;
    padding-right: 0;
  }

  .sidebar.is-collapsed .meca-avatar {
    margin-left: auto;
    margin-right: auto;
  }
}

@media (max-width: 1023px) {
  .sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100%;
    transform: translateX(-100%);
    transition: transform 0.2s;
  }
  .sidebar.is-open {
    transform: translateX(0);
  }
}

/* Logo */
.sidebar-logo {
  min-height: 58px;
  background: linear-gradient(135deg, #FFD200, #D97706);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 10px;
  margin: 0 12px 20px;
  padding: 9px 10px;
  font-size: 18px;
  font-weight: 800;
  color: #111;
  cursor: pointer;
  letter-spacing: 0.3px;
  box-shadow: 0 2px 12px rgba(245,158,11,0.2);
  transition: all 0.2s;
  border: none;
  font-family: inherit;
  text-align: left;
}
.sidebar-logo:hover {
  box-shadow: 0 4px 20px rgba(245,158,11,0.3);
  transform: translateY(-1px);
}
.sidebar-logo-image,
.topbar-brand-logo {
  background: rgba(255,255,255,0.92);
  border-radius: 8px;
  object-fit: contain;
  flex-shrink: 0;
}
.sidebar-logo-image {
  width: 38px;
  height: 38px;
  padding: 4px;
}
.sidebar-logo-fallback,
.topbar-brand-fallback {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  font-weight: 800;
  background: rgba(17,17,17,0.18);
  color: #111;
  flex-shrink: 0;
}
.sidebar-logo-fallback {
  width: 38px;
  height: 38px;
}
.sidebar-logo-text {
  font-size: 12px;
  font-weight: 800;
  line-height: 1.2;
}

.sidebar-spacer { flex: 1; }
.sidebar-group-label {
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--text-subtle);
  padding: 14px 16px 4px;
  font-weight: 700;
}
.sidebar-group-label--first { padding-top: 8px; }

/* Mechanic avatar */
.meca-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: #8B5CF6;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  border: 2px solid transparent;
  transition: all 0.2s;
  color: white;
  margin-left: 16px;
  margin-bottom: 4px;
}
.meca-avatar:hover { border-color: #8B5CF6; }

/* Logout button */
.nav-logout {
  height: 40px;
  border-radius: 6px;
  border: none;
  background: transparent;
  color: #4B5563;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 15px;
  padding: 0 16px;
  margin: 0 8px;
  margin-top: 8px;
  font-family: inherit;
  transition: all 0.2s;
}
.nav-logout span:first-child { font-size: 14px; width: 24px; text-align: center; }
.nav-label { font-size: 13px; font-weight: 500; }
.nav-logout:hover { color: #9CA3AF; background: rgba(255,255,255,0.04); }

/* === MAIN AREA === */
.main-area {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

/* === TOPBAR === */
.topbar {
  position: relative;
  height: 56px;
  background: #11141B;
  border-bottom: 1px solid rgba(255,255,255,0.06);
  display: flex;
  align-items: center;
  padding: 0 24px;
  gap: 16px;
  flex-shrink: 0;
}
.topbar-menu-btn {
  display: none;
  background: none;
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 6px;
  color: #9CA3AF;
  cursor: pointer;
  padding: 6px 10px;
  font-size: 16px;
}
@media (max-width: 1023px) {
  .topbar-menu-btn { display: block; }
}
.topbar-brand {
  display: flex;
  align-items: center;
  gap: 8px;
  padding-right: 12px;
  margin-right: 2px;
  border-right: 1px solid rgba(255,255,255,0.08);
  position: relative;
  z-index: 1;
}
.topbar-brand-logo {
  width: 30px;
  height: 30px;
  padding: 3px;
}
.topbar-center-brand {
  position: absolute;
  left: 50%;
  top: 50%;
  transform: translate(-50%, -50%);
  display: flex;
  align-items: center;
  justify-content: center;
  pointer-events: none;
}
.topbar-center-logo {
  width: 124px;
  height: 28px;
  object-fit: contain;
  background: transparent;
}
.topbar-brand-fallback {
  width: 30px;
  height: 30px;
  font-size: 12px;
}
.topbar-brand-name {
  max-width: 160px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 12px;
  font-weight: 700;
  color: #E8E9ED;
}

@media (max-width: 900px) {
  .topbar-brand-name {
    display: none;
  }

  .topbar-center-logo {
    width: 108px;
  }
}

@media (max-width: 640px) {
  .topbar-center-brand {
    display: none;
  }
}
.topbar-title {
  font-size: 16px;
  font-weight: 600;
  color: #E8E9ED;
  letter-spacing: -0.2px;
}
.topbar-spacer { flex: 1; }
.topbar-live {
  font-size: 12px;
  color: #6B7280;
}
.topbar-new-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 9px 16px;
  border-radius: 6px;
  background: linear-gradient(135deg, #FFD200, #D97706);
  color: #111;
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
  transition: all 0.2s;
  box-shadow: 0 1px 3px rgba(245,158,11,0.2);
}
.topbar-new-btn:hover {
  background: linear-gradient(135deg, #FBBF24, #FFD200);
  box-shadow: 0 2px 8px rgba(245,158,11,0.3);
  transform: translateY(-1px);
}

/* === CONTENT === */
.content {
  flex: 1;
  overflow-y: auto;
  padding: 24px;
  scroll-behavior: smooth;
}
@media (max-width: 768px) {
  .content { padding: 16px; }
}
</style>
