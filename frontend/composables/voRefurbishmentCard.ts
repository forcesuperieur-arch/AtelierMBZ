export function selectRefurbishmentCampaignId(
  campaigns: Array<Record<string, any>>,
  activeCampaignId?: number | null,
  currentSelectedId?: number | null,
) {
  if (!campaigns.length) return null

  if (currentSelectedId && campaigns.some(campaign => campaign.id === currentSelectedId)) {
    return currentSelectedId
  }

  return activeCampaignId || campaigns[0]?.id || null
}

export function buildRefurbishmentLineForms(lines: Array<Record<string, any>> = []) {
  return Object.fromEntries(lines.map((line: any) => [line.id, {
    quantity: String(line.quantity ?? 1),
    status: line.status || 'proposee',
    actualTotalHt: line.actualTotalHt || '',
    actualMinutes: line.actualMinutes != null ? String(line.actualMinutes) : '',
    notes: line.notes || '',
  }]))
}

export function buildRefurbishmentPieceForms(pieces: Array<Record<string, any>> = []) {
  return Object.fromEntries(pieces.map((piece: any) => [piece.id, {
    reference: piece.reference || '',
    quantity: String(piece.quantity ?? 1),
    supplier: piece.supplier || '',
    status: piece.status || 'a_commander',
    actualTotalCostHt: piece.actualTotalCostHt || '',
    notes: piece.notes || '',
  }]))
}

export function toRefurbishmentDateTimeLocal(value?: string | null) {
  if (!value) return ''

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''

  const pad = (part: number) => String(part).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}