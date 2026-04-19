import { describe, expect, it } from 'vitest'

import { useCarteGriseOcr } from '../composables/useCarteGriseOcr'

describe('useCarteGriseOcr', () => {
  it('picks the first image file for OCR when multiple files are provided', () => {
    const { pickOcrImageFile } = useCarteGriseOcr()
    const pdf = new File(['dummy'], 'carte-grise.pdf', { type: 'application/pdf' })
    const image = new File(['dummy'], 'carte-grise.jpg', { type: 'image/jpeg' })

    const result = pickOcrImageFile([pdf, image])

    expect(result.file?.name).toBe('carte-grise.jpg')
    expect(result.warning).toBe('')
  })

  it('returns a clear warning when only a PDF is provided', () => {
    const { pickOcrImageFile } = useCarteGriseOcr()
    const pdf = new File(['dummy'], 'carte-grise.pdf', { type: 'application/pdf' })

    const result = pickOcrImageFile([pdf])

    expect(result.file).toBeNull()
    expect(result.warning).toContain('photo')
    expect(result.warning).toContain('PDF')
  })
})
