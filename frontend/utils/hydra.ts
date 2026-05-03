/**
 * Extraction unifiée des réponses API Platform / Hydra.
 * Remplace les dizaines de duplications `raw['hydra:member'] ?? raw['member'] ?? []`.
 */

export function unwrapHydra<T>(response: unknown): T[] {
  if (Array.isArray(response)) {
    return response as T[]
  }
  if (response && typeof response === 'object') {
    const obj = response as Record<string, unknown>
    return (obj['hydra:member'] ?? obj['member'] ?? []) as T[]
  }
  return []
}

export function unwrapHydraOrEmpty<T>(response: unknown): T[] {
  if (!response) return []
  return unwrapHydra<T>(response)
}

export function unwrapHydraPaginated<T>(response: unknown): {
  items: T[]
  totalItems: number
} {
  if (!response || typeof response !== 'object') {
    return { items: [], totalItems: 0 }
  }
  const obj = response as Record<string, unknown>
  return {
    items: unwrapHydra<T>(response),
    totalItems: (obj['hydra:totalItems'] ?? obj['totalItems'] ?? 0) as number,
  }
}
