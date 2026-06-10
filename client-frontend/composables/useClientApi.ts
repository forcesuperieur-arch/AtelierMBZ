/**
 * Client HTTP de l'espace client.
 * L'auth repose sur les cookies HttpOnly (client_access_token / client_refresh_token) :
 * sur un 401, on tente un refresh silencieux puis on rejoue la requête une fois.
 * Si le refresh échoue, la session est purgée ; on ne renvoie au login que
 * depuis une page protégée.
 */
export const CLIENT_PUBLIC_PATHS = [
  '/login',
  '/forgot-password',
  '/reset-password',
  '/clauses',
  '/cgv',
  '/mentions-legales',
  '/politique-confidentialite',
]

let refreshPromise: Promise<boolean> | null = null

async function refreshSession(): Promise<boolean> {
  if (!refreshPromise) {
    refreshPromise = $fetch('/api/client/refresh', {
      method: 'POST',
      credentials: 'include',
      baseURL: '',
    })
      .then(() => true)
      .catch(() => false)
      .finally(() => {
        // libère le verrou après coup pour les prochains 401
        setTimeout(() => { refreshPromise = null }, 0)
      })
  }
  return refreshPromise
}

export function useClientApi() {
  async function apiFetch<T = any>(path: string, options: Record<string, any> = {}): Promise<T> {
    const doFetch = () => $fetch<T>(path, {
      credentials: 'include',
      baseURL: '',
      ...options,
    })

    try {
      return await doFetch()
    } catch (error: any) {
      if (error?.response?.status !== 401 && error?.status !== 401) {
        throw error
      }
      const refreshed = await refreshSession()
      if (!refreshed) {
        const auth = useAuthStore()
        auth.clearSession()
        const path = useRoute().path
        if (!CLIENT_PUBLIC_PATHS.includes(path)) {
          await navigateTo('/login')
        }
        throw error
      }
      return await doFetch()
    }
  }

  return { apiFetch }
}
