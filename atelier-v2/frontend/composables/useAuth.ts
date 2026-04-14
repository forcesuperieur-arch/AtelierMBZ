import { useAuthStore } from '~/stores/auth'

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
      return null
    }
  }

  function hasSection(section: string): boolean {
    const perms = store.user?.role_permissions
    if (!perms) return false
    const sections = perms.sections_json
    if (sections.includes('*')) return true
    return sections.includes(section)
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
