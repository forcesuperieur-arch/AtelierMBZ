/**
 * Formate une date en format court français (JJ/MM/AAAA).
 */
export function formatDateShort(date: string | Date | null): string {
  if (!date) return '—'
  const d = typeof date === 'string' ? new Date(date) : date
  if (isNaN(d.getTime())) return '—'
  return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

/**
 * Formate une date avec heure en format court français.
 */
export function formatDateTime(date: string | Date | null): string {
  if (!date) return 'Date inconnue'
  const d = typeof date === 'string' ? new Date(date) : date
  if (isNaN(d.getTime())) return String(date)
  return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'short', timeStyle: 'short' }).format(d)
}

/**
 * Formate une durée en minutes en HH:MM.
 */
export function formatDuration(minutes: number): string {
  const total = Math.max(0, Math.round(Number(minutes ?? 0)))
  const h = Math.floor(total / 60)
  const m = total % 60
  return `${h}:${String(m).padStart(2, '0')}`
}
