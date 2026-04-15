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

  return {
    user: computed(() => store.user),
    isAuthenticated: computed(() => store.isAuthenticated),
    login,
    logout,
    fetchMe,
    hasSection,
    hasPerm,
  }
}
