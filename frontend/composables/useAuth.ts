import { useAuthStore } from '~/stores/auth'
import { useAtelierStore } from '~/stores/atelier'

export function useAuth() {
  const store = useAuthStore()
  const api = useApi()

  async function login(email: string, password: string) {
    const data = await api.post('/auth/login', { email, password })
    store.setUser(data.user)
    return data
  }

  async function getGoogleLoginConfig(options?: { mode?: 'login' | 'request', email?: string, prenom?: string, nom?: string }) {
    const params = new URLSearchParams()
    if (options?.mode) params.set('mode', options.mode)
    if (options?.email) params.set('email', options.email)
    if (options?.prenom) params.set('prenom', options.prenom)
    if (options?.nom) params.set('nom', options.nom)

    const suffix = params.toString() ? `?${params.toString()}` : ''
    return await api.get(`/auth/google/url${suffix}`)
  }

  async function startGoogleLogin(options?: { mode?: 'login' | 'request', email?: string, prenom?: string, nom?: string }) {
    const data = await getGoogleLoginConfig(options)
    if (!data?.auth_url) {
      throw new Error(data?.error || 'Google SSO indisponible')
    }

    if (process.client) {
      window.location.href = data.auth_url
    }

    return data
  }

  async function exchangeGoogleCode(code: string, state = '') {
    const data = await api.post('/auth/google/exchange', { code, state })
    if (data?.user) {
      store.setUser(data.user)
    }
    return data
  }

  async function logout() {
    try {
      await api.post('/auth/logout')
    } finally {
      store.clearUser()
      useAtelierStore().clearModules()
      navigateTo('/login')
    }
  }

  async function fetchMe() {
    try {
      const data = await api.get('/auth/me')
      store.setUser(data)
      return data
    } catch {
      store.clearUser()
      useAtelierStore().clearModules()
      return null
    }
  }

  function hasSection(section: string): boolean {
    const perms = store.user?.role_permissions
    if (!perms) return false

    const sections = perms.sections_json
    const allowedByRole = sections.includes('*') || sections.includes(section)
    if (!allowedByRole) return false

    const atelierStore = useAtelierStore()
    return atelierStore.isModuleEnabled(section)
  }

  function hasPerm(perm: string): boolean {
    const perms = store.user?.role_permissions
    if (!perms) return false
    const permissions = perms.permissions_json
    if (permissions.includes('*')) return true
    return permissions.includes(perm)
  }

  function getAccessStatus(): string {
    return String(store.user?.access_status || 'active')
  }

  function isPendingValidation(): boolean {
    return Boolean(store.user?.is_pending_validation || getAccessStatus() === 'pending_validation')
  }

  function needsAtelierAssignment(): boolean {
    return Boolean(store.user?.needs_atelier_assignment)
  }

  return {
    user: computed(() => store.user),
    isAuthenticated: computed(() => store.isAuthenticated),
    login,
    getGoogleLoginConfig,
    startGoogleLogin,
    exchangeGoogleCode,
    logout,
    fetchMe,
    hasSection,
    hasPerm,
    getAccessStatus,
    isPendingValidation,
    needsAtelierAssignment,
  }
}
