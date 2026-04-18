import { describe, expect, it } from 'vitest'

import {
  buildRefurbishmentLineForms,
  buildRefurbishmentPieceForms,
  selectRefurbishmentCampaignId,
  toRefurbishmentDateTimeLocal,
} from '../composables/voRefurbishmentCard'

describe('voRefurbishmentCard helpers', () => {
  it('keeps the selected campaign when it still exists', () => {
    expect(selectRefurbishmentCampaignId([{ id: 1 }, { id: 2 }], 2, 1)).toBe(1)
  })

  it('falls back to the active campaign or first campaign', () => {
    expect(selectRefurbishmentCampaignId([{ id: 1 }, { id: 2 }], 2, 9)).toBe(2)
    expect(selectRefurbishmentCampaignId([{ id: 1 }, { id: 2 }], null, null)).toBe(1)
    expect(selectRefurbishmentCampaignId([], null, null)).toBeNull()
  })

  it('builds editable line and piece forms from campaign payloads', () => {
    expect(buildRefurbishmentLineForms([
      { id: 5, quantity: 2, status: 'validee', actualTotalHt: '120.00', actualMinutes: 45, notes: 'OK' },
    ])).toEqual({
      5: {
        quantity: '2',
        status: 'validee',
        actualTotalHt: '120.00',
        actualMinutes: '45',
        notes: 'OK',
      },
    })

    expect(buildRefurbishmentPieceForms([
      { id: 7, reference: 'REF-1', quantity: 3, supplier: 'Honda', status: 'commandee', actualTotalCostHt: '86.00', notes: 'Attente' },
    ])).toEqual({
      7: {
        reference: 'REF-1',
        quantity: '3',
        supplier: 'Honda',
        status: 'commandee',
        actualTotalCostHt: '86.00',
        notes: 'Attente',
      },
    })
  })

  it('formats ISO strings for datetime-local inputs', () => {
    expect(toRefurbishmentDateTimeLocal('2026-04-18T09:30:00')).toBe('2026-04-18T09:30')
    expect(toRefurbishmentDateTimeLocal('')).toBe('')
  })
})