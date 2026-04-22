import { useAtelierStore } from '~/stores/atelier'

export default defineNuxtRouteMiddleware(async (to) => {

  const publicRoutes = [
    '/login',
    '/public/booking',
    '/public/suivi',
    '/public/demande',
    '/public/mentions-legales',
    '/public/politique-confidentialite',
    '/companion/reception',
    '/companion/vo',
  ]
  if (publicRoutes.some(r => to.path.startsWith(r))) return

  const auth = useAuth()
  const { isAuthenticated, fetchMe } = auth
  const api = useApi()
  const atelierStore = useAtelierStore()
  const authBootstrapDone = useState<boolean>('auth-bootstrap-done', () => false)
  // [SPRINT-4] I19 — Track last refresh timestamp for periodic re-auth
  const lastAuthRefreshAt = useState<number>('auth-last-refresh', () => 0)
  const AUTH_REFRESH_INTERVAL_MS = 5 * 60 * 1000 // 5 minutes

  const isStale = Date.now() - lastAuthRefreshAt.value > AUTH_REFRESH_INTERVAL_MS
  const shouldRefreshAuthContext = !authBootstrapDone.value || !auth.user.value?.role_permissions || isStale
  if (!isAuthenticated.value || shouldRefreshAuthContext) {
    const fetchedUser = await fetchMe()
    if (fetchedUser) {
      authBootstrapDone.value = true
      lastAuthRefreshAt.value = Date.now()
    } else {
      authBootstrapDone.value = false
      return navigateTo('/login')
    }
  }

  const roles = auth.user.value?.roles ?? []
  const currentRole = String(auth.user.value?.role || '')
  const isAdmin = currentRole === 'admin' || currentRole === 'super_admin' || roles.includes('ROLE_ADMIN') || roles.includes('ROLE_SUPER_ADMIN')
  const isSuperAdmin = currentRole === 'super_admin' || roles.includes('ROLE_SUPER_ADMIN')

  if (to.path.startsWith('/admin') && !isAdmin) {
    if (process.client) {
      useToast().add({
        title: 'Accès refusé',
        description: 'Cette zone est réservée à l’administration.',
        color: 'error',
      })
    }
    return navigateTo('/')
  }

  if (to.path.startsWith('/admin/audit') && !isSuperAdmin) {
    if (process.client) {
      useToast().add({
        title: 'Accès refusé',
        description: 'Le journal d\'audit global est réservé au super-admin.',
        color: 'error',
      })
    }
    return navigateTo('/admin')
  }

  if (to.path === '/' && !auth.hasStatsAccess()) {
    const fallbackPath = [
      ['planning', '/planning'],
      ['workshop', '/workshop'],
      ['rdv', '/rdv'],
      ['mecanicien', '/mecanicien'],
      ['suivi', '/suivi'],
    ].find(([section]) => auth.hasSection(section))?.[1] || '/login'

    if (process.client) {
      useToast().add({
        title: 'Accès restreint',
        description: 'La page Stat est réservée au responsable atelier et aux profils supérieurs.',
        color: 'warning',
      })
    }

    return navigateTo(fallbackPath)
  }

  const missingBranding = !atelierStore.branding?.logo_url

  if (!atelierStore.loaded || missingBranding) {
    try {
      const config = await api.get('/config')
      atelierStore.setConfig(config)
    } catch {
      atelierStore.setConfig()
    }
  }

  const sectionByPrefix: Array<[string, string]> = [
    ['/stock', 'stock'],
    ['/facturation', 'facturation'],
    ['/devis', 'devis'],
    ['/suivi', 'suivi'],
    ['/motos', 'motos'],
    ['/vo', 'vo'],
  ]

  const sectionLabels: Record<string, string> = {
    stock: 'Stock',
    facturation: 'Facturation',
    devis: 'Devis',
    suivi: 'Suivi live',
    motos: 'Catalogue motos',
    vo: 'Véhicules d\'Occasion',
  }

  const blockedSection = sectionByPrefix.find(([prefix]) => to.path.startsWith(prefix))?.[1]
  if (blockedSection && !atelierStore.isModuleEnabled(blockedSection)) {
    if (process.client) {
      useToast().add({
        title: `${sectionLabels[blockedSection] || 'Ce module'} est désactivé`,
        description: 'Réactive-le dans la configuration atelier pour rouvrir cet écran.',
        color: 'warning',
      })
    }
    return navigateTo({ path: '/', query: { moduleDisabled: blockedSection } })
  }
})
