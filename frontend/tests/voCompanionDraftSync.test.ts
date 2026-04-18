import { describe, expect, it } from 'vitest'

import { adoptDraftEntity, syncDraftBoolean, syncDraftField, type DraftSyncMemory } from '../composables/voCompanionDraftSync'

describe('voCompanionDraftSync', () => {
  it('hydrates an empty field from the latest PDA payload', () => {
    const form = { nom: '' }
    const memory: DraftSyncMemory = {}

    syncDraftField(form, 'nom', 'Dupont', memory)

    expect(form.nom).toBe('Dupont')
    expect(memory.nom).toBe('Dupont')
  })

  it('does not overwrite a manual edit that diverged from the last synced value', () => {
    const form = { nom: 'Dupont' }
    const memory: DraftSyncMemory = { nom: 'Dupont' }

    form.nom = 'Durand'
    syncDraftField(form, 'nom', 'Martin', memory)

    expect(form.nom).toBe('Durand')
    expect(memory.nom).toBe('Martin')
  })

  it('updates booleans only while the field was untouched locally', () => {
    const form = { controleTechniqueOk: false }
    const memory: DraftSyncMemory = {}

    syncDraftBoolean(form, 'controleTechniqueOk', true, memory)
    expect(form.controleTechniqueOk).toBe(true)

    form.controleTechniqueOk = false
    syncDraftBoolean(form, 'controleTechniqueOk', true, memory)
    expect(form.controleTechniqueOk).toBe(false)
  })

  it('adopts the server entity only when there is no conflicting local selection', () => {
    expect(adoptDraftEntity(null, { id: 8, nom: 'Alice' })).toEqual({ id: 8, nom: 'Alice' })
    expect(adoptDraftEntity({ id: 8, nom: 'Alice' }, { id: 8, nom: 'Alice B.' })).toEqual({ id: 8, nom: 'Alice B.' })
    expect(adoptDraftEntity({ id: 4, nom: 'Bob' }, { id: 8, nom: 'Alice' })).toEqual({ id: 4, nom: 'Bob' })
  })
})