/**
 * Formatage propre à la page Stat : durées en langage d'atelier, euros sans
 * centimes, et surtout un delta qui ne ment pas.
 *
 * L'ancienne page affichait « 0 % vs période préc. » même quand aucune
 * comparaison n'était calculable : un chiffre faux est pire qu'un tiret.
 */
export function useDashboardFormat() {
  /** « 1 h 30 », « 45 min », « 12 h » — jamais « 90 » tout court. */
  function formatMinutes(value: number | string | null | undefined): string {
    const total = Math.round(Number(value ?? 0))
    if (!Number.isFinite(total) || total <= 0) return '0 min'
    const heures = Math.floor(total / 60)
    const minutes = total % 60
    if (heures === 0) return `${minutes} min`
    if (minutes === 0) return `${heures} h`
    return `${heures} h ${String(minutes).padStart(2, '0')}`
  }

  function formatEuro(value: number | string | null | undefined): string {
    const n = Number(value ?? 0)
    if (!Number.isFinite(n)) return '0 €'
    return new Intl.NumberFormat('fr-FR', {
      style: 'currency', currency: 'EUR', maximumFractionDigits: 0,
    }).format(n)
  }

  function formatNombre(value: number | string | null | undefined): string {
    const n = Number(value ?? 0)
    return Number.isFinite(n) ? new Intl.NumberFormat('fr-FR').format(Math.round(n)) : '0'
  }

  /** Pourcentage à la française : virgule décimale, et pas de décimale inutile. */
  function formatPourcent(value: number | string | null | undefined, decimales = 1): string {
    const n = Number(value ?? 0)
    if (!Number.isFinite(n)) return '0 %'
    const arrondi = Math.abs(n) >= 100 ? Math.round(n) : Number(n.toFixed(decimales))
    return `${String(arrondi).replace('.', ',')} %`
  }

  function formatJourCourt(value: string | null | undefined): string {
    if (!value) return ''
    const d = new Date(value)
    if (isNaN(d.getTime())) return String(value)
    return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' })
  }

  /**
   * Delta d'une métrique de comparaison de période.
   * Renvoie `null` si la période précédente n'a rien à comparer — l'appelant
   * affiche alors un repère factuel plutôt qu'un faux 0 %.
   */
  function delta(metric: any): { pct: number, signe: string, sens: 'hausse' | 'baisse' | 'stable' } | null {
    if (!metric) return null
    const precedent = Number(metric.previous ?? metric.prev ?? 0)
    const courant = Number(metric.current ?? 0)
    if (!Number.isFinite(precedent) || precedent === 0) return null
    // L'API renvoie `delta_percent` (AnalyticsController::compareMetric).
    // L'ancienne page lisait `delta_pct`, qui n'existe pas : d'où le « 0 % vs
    // période préc. » affiché partout quels que soient les chiffres réels.
    const brut = metric.delta_percent ?? metric.delta_pct
    const pct = Number(brut ?? Math.round((courant - precedent) / precedent * 100))
    return {
      // Un delta se lit d'un coup d'œil : l'entier suffit, la décimale parasite.
      pct: Math.round(Math.abs(pct)),
      signe: pct > 0 ? '+' : (pct < 0 ? '−' : ''),
      sens: pct > 0 ? 'hausse' : (pct < 0 ? 'baisse' : 'stable'),
    }
  }

  return { formatMinutes, formatEuro, formatNombre, formatPourcent, formatJourCourt, delta }
}
