import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

import { useVoStore } from '../stores/vo'

describe('useVoStore', () => {
  const api = {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    del: vi.fn(),
    upload: vi.fn(),
  }

  beforeEach(() => {
    setActivePinia(createPinia())
    api.get.mockReset()
    api.post.mockReset()
    api.patch.mockReset()
    api.del.mockReset()
    api.upload.mockReset()
    vi.stubGlobal('useApi', () => api)
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('creates a depot draft and refreshes the depot list', async () => {
    api.post.mockResolvedValue({ id: 12, status: 'brouillon' })
    api.get.mockResolvedValue([{ id: 12, status: 'brouillon' }])

    const store = useVoStore()
    const result = await store.createDepot({ status: 'brouillon' })

    expect(api.post).toHaveBeenCalledWith('/vo/depots', { status: 'brouillon' })
    expect(api.get).toHaveBeenCalledWith('/vo/depots')
    expect(store.depots).toEqual([{ id: 12, status: 'brouillon' }])
    expect(result).toEqual({ id: 12, status: 'brouillon' })
  })

  it('patches a depot with the companion draft finalization flag', async () => {
    api.patch.mockResolvedValue({ id: 12, status: 'actif' })

    const store = useVoStore()
    const result = await store.updateDepot(12, { status: 'actif', finalizeCompanionDraft: true })

    expect(api.patch).toHaveBeenCalledWith('/vo/depots/12', { status: 'actif', finalizeCompanionDraft: true })
    expect(result).toEqual({ id: 12, status: 'actif' })
  })

  it('uses the refurbishment API endpoints expected by the VO card', async () => {
    api.get.mockResolvedValue({ items: [] })
    api.post.mockResolvedValue({ id: 44 })

    const store = useVoStore()

    await store.fetchApplicablePrestationsForRefurbishment(44)
    await store.createDepotRefurbishment(18)

    expect(api.get).toHaveBeenCalledWith('/vo/remises-en-etat/44/prestations-applicables')
    expect(api.post).toHaveBeenCalledWith('/vo/depots/18/remises-en-etat', {})
  })
})