import { useAtelierStore } from '~/stores/atelier'

export default defineNuxtRouteMiddleware(async (to) => {
  const publicRoutes = ['/login', '/public/booking', '/public/suivi', '/public/companion', '/public/vo-companion', '/public/demande', '/restitution']
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

  /**
   * Un compte de rôle SRC atterrit sur le cockpit réseau, pas sur le Stat d'un
   * atelier (52a) : le cockpit est un étage à part. L'exception est la visite
   * en cours — quand il a « ouvert » un atelier depuis le cockpit, il a le
   * droit d'en voir le Stat, et le bandeau jaune lui rappelle d'où il vient.
   */
  const isServiceClient = roles.includes('ROLE_SERVICE_CLIENT')
  const visiteDepuisCockpit = Boolean(useCockpitOrigin().value)
  if (to.path === '/' && isServiceClient && !visiteDepuisCockpit) {
    return navigateTo('/cockpit')
  }

  if (to.path === '/' && !auth.hasStatsAccess()) {
    /**
     * Un SRC entré dans un atelier depuis le cockpit atterrit sur le PLANNING,
     * pas sur Stat — et c'est un écart assumé avec la maquette 52a.
     *
     * 52a montre le Stat de l'atelier visité, entrée de nav active à l'appui.
     * Le back le refuse : `assertStatsAccess()` (AnalyticsController et
     * StatistiquesController) n'ouvre le pilotage qu'au super admin, au
     * responsable d'atelier et au responsable de magasin. Ce garde est
     * antérieur à la création du rôle SRC : ce n'est pas un oubli, et le
     * contourner ouvrirait 19 points d'appel analytics à un profil de suivi
     * client. Mesuré : le SRC lit en revanche sans aucun refus les rendez-vous,
     * les ponts et la configuration — donc le planning s'affiche entier.
     */
    const fallbackPath = [
      ['planning', '/planning'],
      ['workshop', '/workshop'],
      ['rdv', '/rdv/new'],
      ['mecanicien', '/mecanicien'],
    ].find(([section]) => auth.hasSection(section))?.[1]
      || (isServiceClient ? (visiteDepuisCockpit ? '/planning' : '/cockpit') : '/login')

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
    ['/motos', 'motos'],
    ['/vo', 'vo'],
  ]

  const sectionLabels: Record<string, string> = {
    stock: 'Stock',
    facturation: 'Facturation',
    devis: 'Devis',
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
