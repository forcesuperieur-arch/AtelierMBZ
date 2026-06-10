import { defineStore } from 'pinia'

/**
 * Session client. La source de vérité est le cookie HttpOnly géré par le
 * backend — le store ne garde que le profil hydraté via /api/client/me.
 * `hydrated` indique qu'une tentative de réhydratation a eu lieu (boot/F5).
 */
export const useAuthStore = defineStore('auth', () => {
  const client = useState<any | null>('client_user', () => null)
  const hydrated = useState<boolean>('client_auth_hydrated', () => false)

  const isAuthenticated = computed(() => !!client.value)

  async function login(email: string, password: string): Promise<boolean> {
    try {
      const res: any = await $fetch('/api/client/login', {
        method: 'POST',
        body: { email, password },
        credentials: 'include',
        baseURL: '',
      })
      client.value = res.client
      hydrated.value = true
      return true
    } catch (e: any) {
      console.error('[Auth] Login failed', e)
      return false
    }
  }

  async function logout() {
    try {
      await $fetch('/api/client/logout', { method: 'POST', credentials: 'include', baseURL: '' })
    } catch {}
    clearSession()
    navigateTo('/login')
  }

  function clearSession() {
    client.value = null
  }

  /** Réhydrate la session depuis le cookie (avec refresh silencieux sur 401). */
  async function fetchMe(): Promise<boolean> {
    const { apiFetch } = useClientApi()
    try {
      client.value = await apiFetch('/api/client/me')
      return true
    } catch {
      client.value = null
      return false
    } finally {
      hydrated.value = true
    }
  }

  return {
    client,
    hydrated,
    isAuthenticated,
    login,
    logout,
    clearSession,
    fetchMe,
  }
})
