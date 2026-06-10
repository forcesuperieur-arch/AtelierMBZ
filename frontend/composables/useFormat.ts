export function useFormat() {
  function formatDate(val: string | null | undefined): string {
    if (!val) return '—'
    const d = new Date(val)
    if (isNaN(d.getTime())) return val
    return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })
  }

  function formatTime(val: string | null | undefined): string {
    if (!val) return '—'
    // Already HH:mm format from custom controllers
    if (/^\d{2}:\d{2}$/.test(val)) return val
    const match = String(val).match(/(\d{2}):(\d{2})/)
    if (match) return `${match[1]}:${match[2]}`
    return val
  }

  function formatCurrency(val: number | null | undefined): string {
    if (val == null) return '—'
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(val)
  }

  return { formatDate, formatTime, formatCurrency }
}
