/**
 * Dates en heure LOCALE de l'atelier.
 * `new Date().toISOString().slice(0, 10)` donne la date UTC : entre minuit et
 * 2h (heure de Paris), c'est encore « hier » — le planning et la liste
 * mécanicien chargeaient la mauvaise journée.
 */
export function toLocalISODate(d: Date): string {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

export function todayLocalISO(): string {
  return toLocalISODate(new Date())
}
