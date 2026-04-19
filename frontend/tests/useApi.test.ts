import { beforeEach, describe, expect, it, vi } from 'vitest'

import { useApi } from '../composables/useApi'

describe('useApi auth handling', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
    vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: '/api' } }))
    vi.stubGlobal('navigateTo', vi.fn())
  })

  it('does not try refresh for auth endpoints like google url', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: false,
      status: 401,
      text: async () => JSON.stringify({ error: 'Unauthorized' }),
    })

    vi.stubGlobal('fetch', fetchMock)

    const api = useApi()

    await expect(api.get('/auth/google/url')).rejects.toThrow('Unauthorized')
    expect(fetchMock).toHaveBeenCalledTimes(1)
    expect(fetchMock.mock.calls[0]?.[0]).toBe('/api/auth/google/url')
  })

  it('retries once after refresh for protected endpoints', async () => {
    const fetchMock = vi.fn()
      .mockResolvedValueOnce({
        ok: false,
        status: 401,
        text: async () => 'Unauthorized',
      })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        text: async () => '',
      })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({ ok: true }),
      })

    vi.stubGlobal('fetch', fetchMock)

    const api = useApi()
    const result = await api.get('/ponts/status')

    expect(result).toEqual({ ok: true })
    expect(fetchMock).toHaveBeenCalledTimes(3)
    expect(fetchMock.mock.calls[1]?.[0]).toBe('/api/auth/refresh')
  })

  it('does not log an error for an unknown vehicle plate lookup during RDV intake', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: false,
      status: 404,
      text: async () => JSON.stringify({ error: 'Vehicle not found' }),
    })

    const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => undefined)
    vi.stubGlobal('fetch', fetchMock)

    const api = useApi()

    await expect(api.get('/vehicule/ZZ-999-AA')).rejects.toThrow('Vehicle not found')
    expect(consoleSpy).not.toHaveBeenCalled()
  })
})
