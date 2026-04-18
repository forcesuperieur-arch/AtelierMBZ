export type DraftSyncMemory = Record<string, string>

function normalizeDraftValue(value: unknown): string {
  if (value === null || value === undefined) return ''
  return String(value).trim()
}

export function syncDraftField<T extends Record<string, any>>(
  target: T,
  key: keyof T & string,
  nextValue: unknown,
  memory: DraftSyncMemory,
) {
  const normalized = normalizeDraftValue(nextValue)
  if (!normalized) return

  const current = normalizeDraftValue(target[key])
  const previous = memory[key] ?? ''

  if (!current || current === previous) {
    target[key] = normalized as T[keyof T]
  }

  memory[key] = normalized
}

export function syncDraftBoolean<T extends Record<string, any>>(
  target: T,
  key: keyof T & string,
  nextValue: unknown,
  memory: DraftSyncMemory,
) {
  const normalized = nextValue ? '1' : '0'
  const previous = memory[key]
  const current = target[key] ? '1' : '0'

  if (previous === undefined ? !target[key] : current === previous) {
    target[key] = Boolean(nextValue) as T[keyof T]
  }

  memory[key] = normalized
}

export function adoptDraftEntity<T extends { id?: number | string } | null>(current: T, next: T): T {
  if (!next?.id) return current
  if (!current?.id || current.id === next.id) return next
  return current
}