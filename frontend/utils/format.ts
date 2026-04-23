/**
 * Formate un montant en euros (fr-FR).
 */
export function formatEuro(value: number | string | null): string {
  const n = Number(value ?? 0)
  return n.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' })
}

/**
 * Formate un numéro de téléphone français.
 * Accepte les formats +33, 0033, 0x...
 */
export function formatPhone(phone: string | null): string {
  if (!phone) return '—'
  let cleaned = phone.replace(/\s/g, '')
  if (cleaned.startsWith('+33')) cleaned = '0' + cleaned.slice(3)
  if (cleaned.startsWith('0033')) cleaned = '0' + cleaned.slice(4)
  if (cleaned.length === 10 && cleaned.startsWith('0')) {
    return cleaned.replace(/(\d{2})(?=.)/g, '$1 ').trim()
  }
  return phone
}

/**
 * Formate une plaque d'immatriculation (SIV française).
 * Ex: AA123BB -> AA-123-BB
 */
export function formatPlaque(plaque: string | null): string {
  if (!plaque) return ''
  const cleaned = plaque.toUpperCase().replace(/[^A-Z0-9]/g, '')
  if (/^[A-Z]{2}\d{3}[A-Z]{2}$/.test(cleaned)) {
    return `${cleaned.slice(0, 2)}-${cleaned.slice(2, 5)}-${cleaned.slice(5, 7)}`
  }
  if (/^\d[A-Z]{3}\d{3}$/.test(cleaned)) {
    return `${cleaned.slice(0, 1)}-${cleaned.slice(1, 4)}-${cleaned.slice(4, 7)}`
  }
  if (/^[A-Z]{3}\d{3}$/.test(cleaned)) {
    return `${cleaned.slice(0, 3)}-${cleaned.slice(3, 6)}`
  }
  if (/^\d{3}[A-Z]{3}$/.test(cleaned)) {
    return `${cleaned.slice(0, 3)}-${cleaned.slice(3, 6)}`
  }
  return cleaned
}
