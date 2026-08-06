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

      <SidebarLink
        v-for="item in menuItems"
        :key="item.to"
        :to="item.to"
        :icon="item.icon"
        :label="item.label"
        :section="item.section"
        :badge-count="item.to === '/planning' ? notifUnreadCount : undefined"
      />

      <div class="sidebar-spacer" />

      <div v-if="auth.hasSection('mecanicien')" class="meca-avatar" @click="navigateTo('/mecanicien')">
        {{ auth.user.value?.prenom?.charAt(0) || 'U' }}
      </div>
      <button class="nav-btn nav-logout" @click="auth.logout()">
        <span class="nav-icon"><AppIcon name="i-ri-shut-down-line" /></span>
        <span class="nav-label">Déconnexion</span>
      </button>
    </nav>

    <!-- MAIN -->
    <div class="main-area">
      <!-- TOPBAR -->
      <header class="topbar">
        <button class="topbar-menu-btn" aria-label="Afficher ou masquer le menu" @click="appStore.toggleSidebar()"><AppIcon name="i-ri-menu-line" /></button>
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
          <span class="atelier-switch-badge" :title="isSuperAdmin ? 'Super admin — vue multi-ateliers' : 'Superviseur de comptes'">{{ isSuperAdmin ? 'SA' : 'SC' }}</span>
          <select v-model="activeAtelierChoice" class="atelier-switch-select" @change="onSwitchAtelier">
            <option v-for="a in ateliersList" :key="a.id" :value="a.id">{{ a.nom }}</option>
          </select>
        </div>
        <AppThemeToggle />
        <AppNotificationBell :atelier-id="currentNotificationAtelierId" />
        <div class="live-dot" />
        <span class="topbar-live">LIVE</span>
        <NuxtLink v-if="auth.hasSection('rdv')" to="/rdv/new" class="btn btn-primary">+ Nouveau RDV</NuxtLink>
      </header>

      <!-- CONTENT -->
      <main class="content">
        <NotificationPopIn filter-type="demande_complementaire" />
        <slot />
      </main>
      <UToaster />
    </div>
  </div>
  <RdvDetailModal />
</template>

<script setup lang="ts">
const auth = useAuth()
const appStore = useAppStore()
const atelierStore = useAtelierStore()
const route = useRoute()
const notifStore = useNotificationsStore()
const { unreadCount: notifUnreadCount } = storeToRefs(notifStore)
const { fetchUnreadCount, fetchNotifications, connect: connectNotifs, disconnect: disconnectNotifs } = notifStore

const atelierName = computed(() => atelierStore.branding?.nom || 'Paddock')
const atelierLogoUrl = computed(() => atelierStore.branding?.logo_url || '/branding/paddock-logo-symbol.svg')
const brandLogo = useBrandLogo()
// Variante encrée en thème clair : le mot-symbole d'origine est blanc cassé.
const topbarLogoUrl = brandLogo.horizontal
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
  '/reception': 'Réception',
  '/clients': 'Clients',
  '/workshop': 'Atelier',
  '/devis': 'Devis',
  '/facturation': 'Facturation',
  '/stock': 'Stock',
  '/motos': 'Catalogue',
  '/suivi': 'Suivi Live',
  '/tarifs': 'Tarifs',
  '/mecanicien': 'Espace Mécanicien',
  '/admin': 'Administration',
  '/vo': 'Véhicules d\'Occasion',
  '/demandes-travaux-supp': 'Travaux complémentaires',
}

const currentSection = computed(() => {
  const path = route.path
  if (path === '/') return 'Stat'
  const base = '/' + path.split('/')[1]
  return sectionNames[base] || 'Paddock'
})

const menuItems = computed(() => {
  const items = [
    { to: '/', icon: 'i-ri-bar-chart-2-line', label: 'Stat', section: 'dashboard' },
    { to: '/cockpit', icon: 'i-ri-radar-line', label: 'Cockpit SRC', section: 'cockpit' },
    { to: '/rdv', icon: 'i-ri-calendar-line', label: 'Prise de RDV', section: 'rdv' },
    { to: '/planning', icon: 'i-ri-calendar-2-line', label: 'Planning', section: 'planning' },
    { to: '/reception', icon: 'i-ri-inbox-line', label: 'Réception', section: 'planning' },
    { to: '/en-atelier', icon: 'i-ri-hourglass-line', label: 'En atelier', section: 'planning' },
    { to: '/workshop', icon: 'i-ri-tools-line', label: 'Ponts & Méca', section: 'workshop' },
    { to: '/demandes-travaux-supp', icon: 'i-ri-hammer-line', label: 'Travaux complémentaires', section: 'workshop' },
    { to: '/suivi', icon: 'i-ri-eye-line', label: 'Suivi Live', section: 'suivi' },
    { to: '/clients', icon: 'i-ri-group-line', label: 'Clients', section: 'clients' },
    { to: '/motos', icon: 'i-ri-motorbike-line', label: 'Fiches moto', section: 'motos' },
    { to: '/devis', icon: 'i-ri-draft-line', label: 'Devis', section: 'devis' },
    { to: '/facturation', icon: 'i-ri-bank-card-line', label: 'Factures', section: 'facturation' },
    { to: '/stock', icon: 'i-ri-archive-line', label: 'Stock', section: 'stock' },
    { to: '/vo', icon: 'i-ri-price-tag-3-line', label: 'VO', section: 'vo' },
    { to: '/admin', icon: 'i-ri-settings-3-line', label: 'Administration', section: 'admin' },
  ]
  return items.filter(i => auth.hasSection(i.section) && (i.section !== 'dashboard' || auth.hasStatsAccess()))
})

const api = useApi()
const toast = useToast()
const activeAtelierCookie = useCookie<string | null>('active_atelier_id', { default: () => null })
const currentNotificationAtelierId = computed(() => {
  const cookieValue = Number(activeAtelierCookie.value ?? 0)
  if (Number.isFinite(cookieValue) && cookieValue > 0) return cookieValue

  const userValue = Number(auth.user.value?.atelierId || auth.user.value?.atelier_id || 0)
  return Number.isFinite(userValue) && userValue > 0 ? userValue : null
})
const isSuperAdmin = computed(() => (auth.user.value?.roles || []).includes('ROLE_SUPER_ADMIN'))
const isServiceClient = computed(() => (auth.user.value?.roles || []).includes('ROLE_SERVICE_CLIENT'))
const canSwitchAtelierContext = computed(() => isSuperAdmin.value || isServiceClient.value)

function normalizeAtelierChoice(value: any): string {
  const normalized = String(value ?? '').trim()
  return normalized && normalized !== 'all' ? normalized : ''
}

const userDefaultAtelierChoice = computed(() => normalizeAtelierChoice(auth.user.value?.atelierId || auth.user.value?.atelier_id))
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

/* === Sidebar overlay (mobile) ===
   Voile opaque SANS flou : c'est ce que demande le design system pour un
   arrière-plan de surcouche. `--scrim` porte déjà l'opacité prescrite. Ce
   `backdrop-filter` était le dernier survivant des onze de l'application. */
.sidebar-overlay {
  position: fixed;
  inset: 0;
  background: var(--scrim);
  z-index: 40;
}
@media (min-width: 1024px) {
  .sidebar-overlay { display: none; }
}

/* === SIDEBAR === */
.sidebar {
  width: 220px;
  background: var(--surface-1);
  border-right: 1px solid var(--border-2);
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
  background: var(--accent);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 10px;
  margin: 0 12px 20px;
  padding: 9px 10px;
  font-size: 18px;
  font-weight: 800;
  color: var(--accent-ink);
  cursor: pointer;
  letter-spacing: 0.3px;
  box-shadow: 0 2px 12px rgba(217,101,0,0.2);
  transition: all 0.2s;
  border: none;
  font-family: inherit;
  text-align: left;
}
.sidebar-logo:hover {
  box-shadow: 0 4px 20px rgba(217,101,0,0.3);
  transform: translateY(-1px);
}
.sidebar-logo-image,
.topbar-brand-logo {
  background: var(--surface-2);
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
  background: var(--surface-3);
  color: var(--accent-ink);
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

/* Mechanic avatar */
.meca-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--info);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  border: 2px solid transparent;
  transition: all 0.2s;
  color: var(--on-info);
  margin-left: 16px;
  margin-bottom: 4px;
}
.meca-avatar:hover { border-color: var(--info); }

/* Logout button */
.nav-logout {
  height: 40px;
  border-radius: 6px;
  border: none;
  background: transparent;
  color: var(--content-disabled);
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
.nav-logout:hover { color: var(--content-3); background: var(--overlay-soft); }

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
  background: var(--surface-1);
  border-bottom: 1px solid var(--border-2);
  display: flex;
  align-items: center;
  padding: 0 24px;
  gap: 16px;
  flex-shrink: 0;
}
.topbar-menu-btn {
  display: none;
  background: none;
  border: 1px solid var(--border-1);
  border-radius: 6px;
  color: var(--content-3);
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
  border-right: 1px solid var(--border-2);
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
  color: var(--content-1);
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
  color: var(--content-1);
  letter-spacing: -0.2px;
}
.topbar-spacer { flex: 1; }
.topbar-live {
  font-size: 12px;
  color: var(--content-3);
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
