/**
 * Regex patterns and validators for common French format fields.
 */

/** French phone: 10 digits starting with 0, or +33 followed by 9 digits */
const PHONE_RE = /^(?:(?:\+33|0033)\s?[1-9](?:[\s.-]?\d{2}){4}|0[1-9](?:[\s.-]?\d{2}){4})$/

/** Basic email format */
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/

/** French registration plate: AA-123-AA (with or without dashes/spaces) */
const PLATE_RE = /^[A-Z]{2}[\s-]?\d{3}[\s-]?[A-Z]{2}$/i

export function useValidation() {
  function isValidPhone(value: string): boolean {
    return PHONE_RE.test(value.trim())
  }

  function isValidEmail(value: string): boolean {
    return EMAIL_RE.test(value.trim())
  }

  function isValidPlate(value: string): boolean {
    return PLATE_RE.test(value.trim())
  }

  /**
   * Validate fields and return error messages array.
   * Empty array = all valid.
   */
  function validateClientFields(fields: { telephone?: string; email?: string; plaque?: string }): string[] {
    const errors: string[] = []
    if (fields.telephone && !isValidPhone(fields.telephone)) {
      errors.push('Téléphone invalide (format attendu : 06 12 34 56 78 ou +33 6 12 34 56 78)')
    }
    if (fields.email && !isValidEmail(fields.email)) {
      errors.push('Email invalide')
    }
    if (fields.plaque && !isValidPlate(fields.plaque)) {
      errors.push('Plaque invalide (format attendu : AA-123-AA)')
    }
    return errors
  }

  return { isValidPhone, isValidEmail, isValidPlate, validateClientFields }
}
