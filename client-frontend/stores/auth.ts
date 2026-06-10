import { defineStore } from 'pinia'

export const useAuthStore = defineStore('auth', () => {
  const accessToken = useState<string | null>('client_access_token', () => null)
  const client = useState<any | null>('client_user', () => null)

  const isAuthenticated = computed(() => !!accessToken.value || !!client.value)

  async function login(email: string, password: string): Promise<boolean> {
    try {
      const res = await $fetch('/api/client/login', {
        method: 'POST',
        body: { email, password },
        credentials: 'include',
        baseURL: '',
      })
      accessToken.value = res.access_token
      client.value = res.client
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
    accessToken.value = null
    client.value = null
    navigateTo('/login')
  }

  async function fetchMe() {
    try {
      const data = await $fetch('/api/client/me', {
        headers: accessToken.value ? { Authorization: `Bearer ${accessToken.value}` } : {},
        credentials: 'include',
        baseURL: '',
      })
      client.value = data
    } catch {
      accessToken.value = null
      client.value = null
    }
  }

  return {
    accessToken,
    client,
    isAuthenticated,
    login,
    logout,
    fetchMe,
  }
})
