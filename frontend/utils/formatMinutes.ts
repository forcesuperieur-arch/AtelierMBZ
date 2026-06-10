export function formatMinutes(value: number | string | undefined | null): string {
  const total = Math.max(0, Number(value ?? 0))
  const h = Math.floor(total / 60)
  const m = total % 60
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
}
