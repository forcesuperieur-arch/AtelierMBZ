import { useAtelierStore } from '~/stores/atelier'

export default defineNuxtRouteMiddleware(async (to) => {
  const publicRoutes = ['/login', '/public/booking', '/public/suivi', '/public/companion', '/public/vo-companion']
  if (publicRoutes.some(r => to.path.startsWith(r))) return

  const auth = useAuth()
  const { isAuthenticated, fetchMe } = auth
  const api = useApi()
  const atelierStore = useAtelierStore()
  const authBootstrapDone = useState<boolean>('auth-bootstrap-done', () => false)

  const shouldRefreshAuthContext = !authBootstrapDone.value || !auth.user.value?.role_permissions
  if (!isAuthenticated.value || shouldRefreshAuthContext) {
    const fetchedUser = await fetchMe()
    authBootstrapDone.value = Boolean(fetchedUser)
    if (!fetchedUser) return navigateTo('/login')
  }

  const roles = auth.user.value?.roles ?? []
  const currentRole = String(auth.user.value?.role || '')
  const isAdmin = currentRole === 'admin' || currentRole === 'super_admin' || roles.includes('ROLE_ADMIN') || roles.includes('ROLE_SUPER_ADMIN')

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
