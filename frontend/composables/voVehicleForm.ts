export interface VoVehicleFormState {
  plaque: string
  vin: string
  marque: string
  modele: string
  categorieId: string
  typeMoto: string
  cylindree: string
  annee: string
  mileage: string
  couleur: string
  datePremiereMiseEnCirculation: string
}

function toOptionalNumber(value: string) {
  return value ? Number(value) : null
}

/**
 * [SPRINT-5] I21 — Validation VIN selon ISO 3779 :
 * - 17 caractères exactement
 * - Pas de I, O, Q (interdits par la norme, ambiguïté visuelle)
 */
export function isValidVin(vin: string): boolean {
  if (!vin) return false
  const normalized = vin.toUpperCase().trim()
  if (normalized.length !== 17) return false
  if (/[IOQ]/.test(normalized)) return false
  if (!/^[A-HJ-NPR-Z0-9]{17}$/.test(normalized)) return false
  return true
}

export function vinErrorMessage(vin: string): string | null {
  if (!vin) return null
  const normalized = vin.toUpperCase().trim()
  if (normalized.length !== 17) return `Le VIN doit contenir exactement 17 caractères (actuellement : ${normalized.length})`
  if (/[IOQ]/.test(normalized)) return 'Le VIN ne peut pas contenir les lettres I, O ou Q (norme ISO 3779)'
  if (!/^[A-HJ-NPR-Z0-9]{17}$/.test(normalized)) return 'Le VIN contient des caractères non autorisés'
  return null
}

export function extractVehicleCategoryId(vehicle: any): string {
  const raw = vehicle?.categorie
  if (raw && typeof raw === 'object' && raw.id) return String(raw.id)
  if (typeof raw === 'string') {
    const match = raw.match(/\/(\d+)$/)
    if (match) return match[1]
  }
  return ''
}

export function applyVehicleToForm(vehicleForm: VoVehicleFormState, vehicle: any) {
  Object.assign(vehicleForm, {
    plaque: vehicle?.plaque || '',
    vin: vehicle?.vin || '',
    marque: vehicle?.marque || '',
    modele: vehicle?.modele || '',
    categorieId: extractVehicleCategoryId(vehicle),
    typeMoto: vehicle?.typeMoto || '',
    cylindree: vehicle?.cylindree || '',
    annee: vehicle?.annee ? String(vehicle.annee) : '',
    mileage: vehicle?.mileage ? String(vehicle.mileage) : '',
    couleur: vehicle?.couleur || '',
    datePremiereMiseEnCirculation: vehicle?.datePremiereMiseEnCirculation ? String(vehicle.datePremiereMiseEnCirculation).slice(0, 10) : '',
  })
}

export function buildVoVehiclePayload(vehicleForm: VoVehicleFormState, includeClientIri?: string) {
  const payload: Record<string, any> = {
    plaque: vehicleForm.plaque,
    vin: vehicleForm.vin || null,
    marque: vehicleForm.marque || null,
    modele: vehicleForm.modele || null,
    categorie: vehicleForm.categorieId ? `/api/motos/categories/${vehicleForm.categorieId}` : null,
    typeMoto: vehicleForm.typeMoto || null,
    cylindree: vehicleForm.cylindree || null,
    annee: toOptionalNumber(vehicleForm.annee),
    mileage: toOptionalNumber(vehicleForm.mileage),
    couleur: vehicleForm.couleur || null,
    datePremiereMiseEnCirculation: vehicleForm.datePremiereMiseEnCirculation || null,
  }

  if (includeClientIri) {
    payload.client = includeClientIri
  }

  return payload
}