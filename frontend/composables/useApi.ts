import type { UseFetchOptions } from 'nuxt/app'

export function useApi() {
  const config = useRuntimeConfig()
  const baseURL = config.public.apiBase as string

  function buildHeaders(opts: RequestInit): Record<string, string> {
    const isFormData = opts.body instanceof FormData
    const defaults: Record<string, string> = { Accept: 'application/json' }
    if (!isFormData) defaults['Content-Type'] = 'application/json'
    return { ...defaults, ...opts.headers as Record<string, string> }
  }

  async function $fetch<T = any>(path: string, opts: RequestInit = {}): Promise<T> {
    const url = `${baseURL}${path}`
    const res = await globalThis.fetch(url, {
      credentials: 'include',
      headers: buildHeaders(opts),
      ...opts,
    })

    if (res.status === 401) {
      // Try refresh
      const refreshed = await refreshToken()
      if (refreshed) {
        const retry = await globalThis.fetch(url, {
          credentials: 'include',
          headers: buildHeaders(opts),
          ...opts,
        })
        if (!retry.ok) throw createApiError(retry)
        return retry.status === 204 ? (null as T) : retry.json()
      }
      navigateTo('/login')
      throw new Error('Session expired')
    }

    if (!res.ok) throw createApiError(res)
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

  function createApiError(res: Response) {
    const err = new Error(`API Error ${res.status}`) as Error & { status: number }
    err.status = res.status
    return err
  }

  const get = <T = any>(path: string) => $fetch<T>(path)

  const post = <T = any>(path: string, body?: any) =>
    $fetch<T>(path, { method: 'POST', body: body ? JSON.stringify(body) : undefined })

  const put = <T = any>(path: string, body?: any) =>
    $fetch<T>(path, { method: 'PUT', body: body ? JSON.stringify(body) : undefined })

  const patch = <T = any>(path: string, body?: any) =>
    $fetch<T>(path, { method: 'PATCH', body: body ? JSON.stringify(body) : undefined })

  const del = <T = any>(path: string) =>
    $fetch<T>(path, { method: 'DELETE' })

  const upload = <T = any>(path: string, formData: FormData) =>
    $fetch<T>(path, {
      method: 'POST',
      body: formData as any,
    })

  return { get, post, put, patch, del, upload }
}
