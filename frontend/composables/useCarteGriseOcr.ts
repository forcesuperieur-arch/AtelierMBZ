export type OcrFieldKey = 'plaque' | 'marque' | 'modele' | 'vin' | 'annee' | 'cylindree' | 'type_moto'

export type CarteGriseOcrResult = Record<OcrFieldKey, string>

export interface OcrComparison {
  tone: 'ok' | 'warn' | 'diff' | 'neutral'
  message: string
  canUseBase: boolean
}

export const ocrFields: Array<{ key: OcrFieldKey; label: string }> = [
  { key: 'plaque', label: 'Plaque (A)' },
  { key: 'marque', label: 'Marque (D.1)' },
  { key: 'modele', label: 'Modele (D.2)' },
  { key: 'vin', label: 'VIN (E)' },
  { key: 'annee', label: 'Mise en circulation (B)' },
  { key: 'cylindree', label: 'Cylindree (P.1)' },
  { key: 'type_moto', label: 'Type (J.1)' },
]

const knownBrands = [
  'YAMAHA',
  'HONDA',
  'KAWASAKI',
  'SUZUKI',
  'BMW',
  'DUCATI',
  'KTM',
  'HARLEY DAVIDSON',
  'HARLEY',
  'TRIUMPH',
  'APRILIA',
  'MV AGUSTA',
  'INDIAN',
  'HUSQVARNA',
  'BENELLI',
  'ROYAL ENFIELD',
  'MOTO GUZZI',
  'PIAGGIO',
  'VESPA',
  'KYMCO',
  'SYM',
  'PEUGEOT',
]

function stripAccents(value: string) {
  return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '')
}

function normalizeLoose(value: unknown): string {
  return stripAccents(String(value || ''))
    .toUpperCase()
    .replace(/[^A-Z0-9]+/g, ' ')
    .trim()
}

function toDigitLike(value: string): string {
  return value
    .replace(/[OQD]/g, '0')
    .replace(/[ILT]/g, '1')
    .replace(/Z/g, '2')
    .replace(/S/g, '5')
    .replace(/G/g, '6')
    .replace(/B/g, '8')
}

function toLetterLike(value: string): string {
  return value
    .replace(/0/g, 'O')
    .replace(/1/g, 'I')
    .replace(/2/g, 'Z')
    .replace(/5/g, 'S')
    .replace(/6/g, 'G')
    .replace(/8/g, 'B')
}

function normalizePlate(value: unknown): string {
  const cleaned = normalizeLoose(value).replace(/\s+/g, '')

  if (/^[A-Z]{2}\d{3}[A-Z]{2}$/.test(cleaned)) {
    const left = toLetterLike(cleaned.slice(0, 2))
    const middle = toDigitLike(cleaned.slice(2, 5))
    const right = toLetterLike(cleaned.slice(5, 7))
    return `${left}-${middle}-${right}`
  }

  if (/^\d[A-Z]{3}\d{3}$/.test(cleaned)) {
    return `${toDigitLike(cleaned.slice(0, 1))}-${toLetterLike(cleaned.slice(1, 4))}-${toDigitLike(cleaned.slice(4, 7))}`
  }

  if (/^[A-Z]{3}\d{3}$/.test(cleaned)) {
    return `${toLetterLike(cleaned.slice(0, 3))}-${toDigitLike(cleaned.slice(3, 6))}`
  }

  if (/^\d{3}[A-Z]{3}$/.test(cleaned)) {
    return `${toDigitLike(cleaned.slice(0, 3))}-${toLetterLike(cleaned.slice(3, 6))}`
  }

  return cleaned
}

function normalizeCylindree(value: unknown): string {
  return toDigitLike(normalizeLoose(value)).replace(/[^0-9]/g, '').slice(0, 4)
}

function normalizeVin(value: unknown): string {
  return toDigitLike(normalizeLoose(value)).replace(/[^A-Z0-9]/g, '').slice(0, 17)
}

function normalizeYear(value: unknown): string {
  const raw = String(value || '')
  const direct = raw.match(/(19\d{2}|20\d{2})/)
  if (direct) return direct[1]
  const compact = toDigitLike(normalizeLoose(raw)).replace(/[^0-9]/g, '')
  return compact.length >= 4 ? compact.slice(-4) : compact
}

function levenshtein(a: string, b: string): number {
  if (a === b) return 0
  if (!a.length) return b.length
  if (!b.length) return a.length

  const matrix = Array.from({ length: b.length + 1 }, (_, i) => [i])
  for (let j = 0; j <= a.length; j += 1) matrix[0][j] = j

  for (let i = 1; i <= b.length; i += 1) {
    for (let j = 1; j <= a.length; j += 1) {
      const cost = a[j - 1] === b[i - 1] ? 0 : 1
      matrix[i][j] = Math.min(
        matrix[i - 1][j] + 1,
        matrix[i][j - 1] + 1,
        matrix[i - 1][j - 1] + cost,
      )
    }
  }

  return matrix[b.length][a.length]
}

function similarityScore(a: string, b: string): number {
  if (!a || !b) return 0
  const distance = levenshtein(a, b)
  return 1 - distance / Math.max(a.length, b.length, 1)
}

function getFieldNormalizer(key: OcrFieldKey) {
  if (key === 'plaque') return normalizePlate
  if (key === 'vin') return normalizeVin
  if (key === 'cylindree') return normalizeCylindree
  if (key === 'annee') return normalizeYear
  return normalizeLoose
}

function selectBestCandidate(rawValue: string, candidates: string[]): string {
  const cleanedRaw = normalizeLoose(rawValue)
  if (!cleanedRaw) return rawValue

  let best = rawValue
  let bestScore = 0

  for (const candidate of candidates) {
    const score = similarityScore(cleanedRaw, normalizeLoose(candidate))
    if (score > bestScore) {
      best = candidate
      bestScore = score
    }
  }

  return bestScore >= 0.58 ? best : rawValue
}

function formatBrand(value: string): string {
  return value
    .toLowerCase()
    .replace(/(^|\s)([a-z])/g, (_, prefix, char) => `${prefix}${char.toUpperCase()}`)
}

function getVehiculeValue(vehicule: Record<string, any> | null | undefined, key: OcrFieldKey): string {
  if (!vehicule) return ''

  if (key === 'type_moto') {
    return String(vehicule.type_moto || vehicule.typeMoto || '')
  }

  return String(vehicule[key] || '')
}

function createFallbackOcrResult(baseVehicule: Record<string, any> | null | undefined = {}): CarteGriseOcrResult {
  return {
    plaque: getVehiculeValue(baseVehicule, 'plaque'),
    marque: getVehiculeValue(baseVehicule, 'marque'),
    modele: getVehiculeValue(baseVehicule, 'modele'),
    vin: getVehiculeValue(baseVehicule, 'vin'),
    annee: normalizeYear(getVehiculeValue(baseVehicule, 'annee')),
    cylindree: getVehiculeValue(baseVehicule, 'cylindree'),
    type_moto: getVehiculeValue(baseVehicule, 'type_moto'),
  }
}

export function useCarteGriseOcr() {
  async function normalizeImage(file: File): Promise<File> {
    if (!file.type.startsWith('image/')) return file
    if (/image\/(jpeg|png|webp)/i.test(file.type) && file.size <= 6 * 1024 * 1024) return file

    try {
      const objectUrl = URL.createObjectURL(file)
      const img = await new Promise<HTMLImageElement>((resolve, reject) => {
        const image = new Image()
        image.onload = () => resolve(image)
        image.onerror = reject
        image.src = objectUrl
      })

      const maxSize = 2200
      const ratio = Math.min(1, maxSize / Math.max(img.width, img.height))
      const canvas = document.createElement('canvas')
      canvas.width = Math.max(1, Math.round(img.width * ratio))
      canvas.height = Math.max(1, Math.round(img.height * ratio))

      const ctx = canvas.getContext('2d')
      if (!ctx) throw new Error('Canvas indisponible')
      ctx.drawImage(img, 0, 0, canvas.width, canvas.height)

      const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.86))
      URL.revokeObjectURL(objectUrl)

      if (!blob) return file

      const safeName = file.name.replace(/\.[^.]+$/, '') || 'photo'
      return new File([blob], `${safeName}.jpg`, { type: 'image/jpeg' })
    } catch {
      return file
    }
  }

  function compareOcrField(key: OcrFieldKey, currentValue: unknown, baseValue: unknown): OcrComparison {
    const current = String(currentValue || '')
    const base = String(baseValue || '')
    if (!base) {
      return { tone: 'neutral', message: 'Aucune valeur dossier existante', canUseBase: false }
    }

    const normalizer = getFieldNormalizer(key)
    const normalizedCurrent = normalizer(current)
    const normalizedBase = normalizer(base)

    if (!normalizedCurrent) {
      return { tone: 'warn', message: `Base dossier : ${base}`, canUseBase: true }
    }

    if (normalizedCurrent === normalizedBase) {
      return { tone: 'ok', message: `Conforme au dossier : ${base}`, canUseBase: false }
    }

    const score = similarityScore(normalizedCurrent, normalizedBase)
    if (score >= 0.72) {
      return { tone: 'warn', message: `Lecture proche de la base dossier : ${base}`, canUseBase: true }
    }

    return { tone: 'diff', message: `Ecart detecte avec le dossier : ${base}`, canUseBase: true }
  }

  function parseCarteGriseText(text: string, baseVehicule: Record<string, any> | null | undefined = {}): CarteGriseOcrResult {
    const lines = text.split('\n').map(line => line.trim()).filter(Boolean)
    const joined = lines.join(' ')

    const extractFirst = (...patterns: RegExp[]) => {
      const haystacks = [...lines, joined]
      for (const source of haystacks) {
        for (const pattern of patterns) {
          const match = source.match(pattern)
          if (match?.[1]) return match[1].trim()
        }
      }
      return ''
    }

    const plaqueCandidate = extractFirst(
      /(?:\bA\b\s*[:\-]?)\s*([A-Z0-9\s-]{6,12})/i,
      /(?:plaque|immatriculation|kenteken)[\s:.-]*([A-Z0-9-]{6,12})/i,
      /\b([A-Z]{2}[\s-]?\d{3}[\s-]?[A-Z]{2})\b/i,
      /\b(\d[\s-]?[A-Z]{3}[\s-]?\d{3})\b/i,
      /\b([A-Z]{3}[\s-]?\d{3})\b/i,
      /\b(\d{3}[\s-]?[A-Z]{3})\b/i,
    )

    let marqueCandidate = extractFirst(
      /(?:\bD[.\s]?1\b\s*[:\-]?)\s*([A-Z][A-Z0-9\s-]{1,24})/i,
      /(?:marque|merk)[\s:.-]*([A-Z][A-Z0-9\s-]{1,24})/i,
    )
    if (!marqueCandidate) {
      const bestBrand = selectBestCandidate(joined, [...knownBrands, getVehiculeValue(baseVehicule, 'marque')].filter(Boolean))
      if (bestBrand && normalizeLoose(bestBrand) !== normalizeLoose(joined)) {
        marqueCandidate = bestBrand
      }
    }

    const modeleCandidate = extractFirst(
      /(?:\bD[.\s]?2(?:[.\s]?1)?\b\s*[:\-]?)\s*([A-Z0-9\s/-]{2,30})/i,
      /(?:\bD[.\s]?3\b\s*[:\-]?)\s*([A-Z0-9\s/-]{2,30})/i,
      /(?:modele|model|commerciale?|handelsbenaming)[\s:.-]*([A-Z0-9\s/-]{2,30})/i,
    )
    const vinCandidate = extractFirst(
      /(?:\bE\b\s*[:\-]?)\s*([A-HJ-NPR-Z0-9]{11,17})/i,
      /(?:chassis(?:nummer)?|num[ée]ro de ch[âa]ssis|n[°o]\s*de\s*s[ée]rie)[\s:.-]*([A-HJ-NPR-Z0-9]{11,17})/i,
      /\b([A-HJ-NPR-Z0-9]{17})\b/i,
    )
    const yearCandidate = extractFirst(
      /(?:\bB\b\s*[:\-]?)\s*(\d{2}[/.\-]\d{2}[/.\-]\d{4}|\d{4})/i,
      /(?:\bI\b\s*[:\-]?)\s*(\d{2}[/.\-]\d{2}[/.\-]\d{4}|\d{4})/i,
      /(?:premi[èe]re immatriculation|eerste inschrijving)[\s:.-]*(\d{2}[/.\-]\d{2}[/.\-]\d{4}|\d{4})/i,
      /\b(19\d{2}|20\d{2})\b/,
    )
    const cylCandidate = extractFirst(
      /(?:\bP[.\s]?1\b\s*[:\-]?)\s*([0-9OQDISBZ]{2,4})/i,
      /([0-9OQDISBZ]{2,4})\s*(?:cm[³3]|cc|CM3)/i,
    )
    const typeCandidate = extractFirst(
      /(?:\bJ[.\s]?1\b\s*[:\-]?)\s*([A-Z0-9]{2,8})/i,
      /(?:genre|carrosserie|voertuigtype)[\s:.-]*([A-Z0-9]{2,8})/i,
      /\b(MTL|MTT1|MTT2|CL|QM|TM|L3E|L1E)\b/i,
    )

    const resolved: CarteGriseOcrResult = {
      plaque: normalizePlate(plaqueCandidate || getVehiculeValue(baseVehicule, 'plaque')),
      marque: marqueCandidate
        ? formatBrand(selectBestCandidate(marqueCandidate, [...knownBrands, getVehiculeValue(baseVehicule, 'marque')].filter(Boolean)))
        : getVehiculeValue(baseVehicule, 'marque'),
      modele: String(modeleCandidate || getVehiculeValue(baseVehicule, 'modele')).trim(),
      vin: normalizeVin(vinCandidate || getVehiculeValue(baseVehicule, 'vin')),
      annee: normalizeYear(yearCandidate || getVehiculeValue(baseVehicule, 'annee')),
      cylindree: normalizeCylindree(cylCandidate || getVehiculeValue(baseVehicule, 'cylindree')),
      type_moto: normalizeLoose(typeCandidate || getVehiculeValue(baseVehicule, 'type_moto')),
    }

    ocrFields.forEach((field) => {
      const baseValue = getVehiculeValue(baseVehicule, field.key)
      const comparison = compareOcrField(field.key, resolved[field.key], baseValue)
      if (comparison.tone === 'warn' && baseValue) {
        resolved[field.key] = String(baseValue)
      }
    })

    return resolved
  }

  async function recognizeCarteGrise(file: File, baseVehicule: Record<string, any> | null | undefined = {}): Promise<CarteGriseOcrResult> {
    const { createWorker } = await import('tesseract.js')
    const worker = await createWorker('fra+nld+eng')

    try {
      const { data: { text } } = await worker.recognize(file)
      return parseCarteGriseText(text, baseVehicule)
    } finally {
      await worker.terminate()
    }
  }

  function summarizeOcrComparison(result: Partial<CarteGriseOcrResult>, baseVehicule: Record<string, any> | null | undefined = {}) {
    const tones = ocrFields.map((field) => compareOcrField(field.key, result[field.key], getVehiculeValue(baseVehicule, field.key)).tone)
    const diffCount = tones.filter(tone => tone === 'diff').length
    const warnCount = tones.filter(tone => tone === 'warn').length

    if (diffCount > 0) {
      return {
        tone: 'warning' as const,
        message: `OCR termine : ${diffCount} champ(s) divergent du dossier. Validation manuelle recommandee.`,
      }
    }

    if (warnCount > 0) {
      return {
        tone: 'neutral' as const,
        message: 'OCR termine. Quelques champs ont ete rapproches de la base dossier, verifiez avant validation.',
      }
    }

    return {
      tone: 'success' as const,
      message: 'OCR coherent avec le dossier. Vous pouvez valider les informations.',
    }
  }

  function toVehicleUpdatePayload(result: Partial<CarteGriseOcrResult>) {
    return {
      plaque: result.plaque || undefined,
      marque: result.marque || undefined,
      modele: result.modele || undefined,
      vin: result.vin || undefined,
      annee: result.annee ? Number.parseInt(result.annee, 10) || undefined : undefined,
      cylindree: result.cylindree || undefined,
      type_moto: result.type_moto || undefined,
    }
  }

  return {
    ocrFields,
    normalizeImage,
    compareOcrField,
    parseCarteGriseText,
    recognizeCarteGrise,
    summarizeOcrComparison,
    toVehicleUpdatePayload,
    createFallbackOcrResult,
    getVehiculeValue,
  }
}
