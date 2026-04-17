import { useAtelierStore } from '~/stores/atelier'

export default defineNuxtRouteMiddleware(async (to) => {
  const publicRoutes = ['/login', '/public/booking', '/public/suivi', '/public/companion']
  if (publicRoutes.some(r => to.path.startsWith(r))) return

  const { isAuthenticated, fetchMe } = useAuth()
  const api = useApi()
  const atelierStore = useAtelierStore()

  if (!isAuthenticated.value) {
    const fetchedUser = await fetchMe()
    if (!fetchedUser) return navigateTo('/login')
  }

  const roles = useAuth().user.value?.roles ?? []
  const currentRole = String(useAuth().user.value?.role || '')
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
