import type { UseFetchOptions } from 'nuxt/app'

// Singleton pour sérialiser les refresh de token : si un refresh est en cours,
// les autres requêtes attendent le même résultat au lieu de re-tenter en parallèle.
let _refreshPromise: Promise<boolean> | null = null

export function useApi() {
  const config = useRuntimeConfig()
  const baseURL = config.public.apiBase as string

  function normalizePath(path: string): string {
    if (!path) return ''
    if (/^https?:\/\//i.test(path)) return path
    if (baseURL && path.startsWith(`${baseURL}/`)) {
      return path.slice(baseURL.length)
    }
    return path.startsWith('/') ? path : `/${path}`
  }

  function buildHeaders(opts: RequestInit): Record<string, string> {
    const isFormData = opts.body instanceof FormData
    const defaults: Record<string, string> = { Accept: 'application/json' }
    if (!isFormData) defaults['Content-Type'] = 'application/json'
    return { ...defaults, ...opts.headers as Record<string, string> }
  }

  function previewBody(body: unknown): string | null {
    if (!body) return null
    if (typeof body === 'string') return body.slice(0, 500)
    if (body instanceof FormData) return '[FormData]'
    try {
      return JSON.stringify(body).slice(0, 500)
    } catch {
      return String(body).slice(0, 500)
    }
  }

  function logApiIssue(level: 'warn' | 'error', message: string, payload: Record<string, unknown>) {
    if (typeof console === 'undefined') return
    console[level](`[API] ${message}`, payload)
  }

  async function $fetch<T = any>(path: string, opts: RequestInit = {}): Promise<T> {
    const method = String(opts.method ?? 'GET').toUpperCase()
    const normalizedPath = normalizePath(path)
    const url = /^https?:\/\//i.test(normalizedPath) ? normalizedPath : `${baseURL}${normalizedPath}`

    if (url.includes('[object Object]')) {
      logApiIssue('error', 'Invalid object interpolated into API path', {
        method,
        path,
        body: previewBody(opts.body),
      })
      throw new Error('Invalid API path: object used instead of identifier')
    }

    let res: Response
    try {
      res = await globalThis.fetch(url, {
        credentials: 'include',
        headers: buildHeaders(opts),
        ...opts,
      })
    } catch (error: any) {
      const isLocalCertIssue = /cert|certificate|failed to fetch|networkerror/i.test(String(error?.message || ''))
        && /localhost/.test(url)

      throw new Error(
        isLocalCertIssue
          ? 'Connexion API impossible : certificat local non approuvé sur https://localhost.'
          : 'Connexion API impossible. Vérifiez que le serveur répond bien.'
      )
    }

    const isAuthEndpoint = /\/auth(\/|$)/.test(normalizedPath)

    if (res.status === 401 && !isAuthEndpoint) {
      if (!_refreshPromise) {
        _refreshPromise = refreshToken().finally(() => { _refreshPromise = null })
      }
      const refreshed = await _refreshPromise
      if (refreshed) {
        const retry = await globalThis.fetch(url, {
          credentials: 'include',
          headers: buildHeaders(opts),
          ...opts,
        })
        if (!retry.ok) {
          const retryText = await retry.text().catch(() => '')
          logApiIssue('error', 'Retry after refresh failed', {
            method,
            path,
            status: retry.status,
            response: retryText.slice(0, 500),
          })
          throw createApiError(retry, retryText)
        }
        return retry.status === 204 ? (null as T) : retry.json()
      }
      // [LOT-0] Distingue inactivité 30min des autres causes 401 pour message UX
      const refreshErrText = await res.text().catch(() => '')
      const isInactivity = /inactivity|inactiv/i.test(refreshErrText)
      navigateTo(isInactivity ? '/login?expired=inactivity' : '/login')
      throw new Error('Session expired')
    }

    if (!res.ok) {
      const errorText = await res.text().catch(() => '')
      const isExpectedVehicleMiss = method === 'GET' && res.status === 404 && /^\/vehicule\//.test(normalizedPath)

      if (!(isAuthEndpoint && res.status === 401) && !isExpectedVehicleMiss) {
        logApiIssue('error', 'Request failed', {
          method,
          path,
          status: res.status,
          body: previewBody(opts.body),
          response: errorText.slice(0, 500),
        })
      }
      throw createApiError(res, errorText)
    }

    return res.status === 204 ? (null as T) : res.json()
  }

  async function refreshToken(): Promise<boolean> {
    try {
      const res = await globalThis.fetch(`${baseURL}/auth/refresh`, {
        method: 'POST',
        credentials: 'include',
      })
      return res.ok
    } catch {
      return false
    }
  }

  function createApiError(res: Response, details = '') {
    let message = `API Error ${res.status}`
    let parsed: any = null

    if (details) {
      try {
        parsed = JSON.parse(details)
        message = parsed?.message || parsed?.error || parsed?.detail || message
      } catch {
        if (details.trim()) message = details.slice(0, 200)
      }
    }

    if ((!parsed?.message && !parsed?.error && !parsed?.detail) && res.status === 403) {
      message = 'Accès refusé. Vous n\'avez pas les permissions nécessaires pour cette action.'
    } else if (res.status === 429) {
      message = 'Trop de requêtes. Attendez quelques secondes avant de réessayer.'
    }

    const err = new Error(message) as Error & { status: number; details?: string; data?: any }
    err.status = res.status
    err.details = details
    err.data = parsed
    return err
  }

  const apiFetch = <T = any>(path: string, opts: RequestInit = {}) =>
    $fetch<T>(path, {
      ...opts,
      body: opts.body && !(opts.body instanceof FormData) && typeof opts.body !== 'string'
        ? JSON.stringify(opts.body)
        : opts.body,
    })

  const get = <T = any>(path: string) => $fetch<T>(path)

  const post = <T = any>(path: string, body?: any) =>
    $fetch<T>(path, {
      method: 'POST',
      body: body instanceof FormData || typeof body === 'string'
        ? body
        : (body ? JSON.stringify(body) : undefined),
    })

  const put = <T = any>(path: string, body?: any) =>
    $fetch<T>(path, {
      method: 'PUT',
      body: body instanceof FormData || typeof body === 'string'
        ? body
        : (body ? JSON.stringify(body) : undefined),
    })

  const patch = <T = any>(path: string, body?: any) =>
    $fetch<T>(path, {
      method: 'PATCH',
      body: body instanceof FormData || typeof body === 'string'
        ? body
        : (body ? JSON.stringify(body) : undefined),
      headers: body instanceof FormData ? undefined : { 'Content-Type': 'application/merge-patch+json' },
    })

  const del = <T = any>(path: string) =>
    $fetch<T>(path, { method: 'DELETE' })

  const upload = <T = any>(path: string, formData: FormData) =>
    $fetch<T>(path, {
      method: 'POST',
      body: formData as any,
    })

  return { apiFetch, get, post, put, patch, del, upload }
}
