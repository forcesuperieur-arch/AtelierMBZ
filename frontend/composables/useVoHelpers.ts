export function useVoHelpers() {
  const api = useApi()
  const config = useRuntimeConfig()

  const apiBase = config.public.apiBase as string

  const documentLabels: Record<string, string> = {
    cerfa_cession_achat: 'CERFA achat',
    cerfa_cession_vente: 'CERFA vente',
    carte_grise: 'Carte grise',
    non_gage: 'Certificat non-gage',
    controle_technique: 'Contrôle technique',
    piece_identite: 'Pièce d’identité',
    contrat_depot_vente: 'Contrat dépôt-vente',
    facture_vo: 'Facture VO',
    pv_rachat: 'PV de rachat',
    notice_garantie: 'Notice de garantie',
    autre: 'Autre',
  }

  const purchaseStatusLabels: Record<string, string> = {
    brouillon: 'Brouillon',
    en_stock: 'En stock',
    en_vente: 'En vente',
    reserve: 'Réservé',
    vendu: 'Vendu',
  }

  const depotStatusLabels: Record<string, string> = {
    actif: 'Actif',
    vendu: 'Vendu',
    restitue: 'Restitué',
    expire: 'Expiré',
  }

  function formatPrice(value: string | number | null | undefined) {
    const numericValue = Number.parseFloat(String(value ?? 0))

    return new Intl.NumberFormat('fr-FR', {
      style: 'currency',
      currency: 'EUR',
    }).format(Number.isFinite(numericValue) ? numericValue : 0)
  }

  function formatDate(value: string | null | undefined) {
    if (!value) return '—'

    try {
      return new Date(value).toLocaleDateString('fr-FR')
    } catch {
      return value
    }
  }

  function extractCollection(data: any) {
    return data?.['hydra:member'] ?? data?.member ?? data?.items ?? (Array.isArray(data) ? data : [])
  }

  function normalizeText(value: unknown) {
    return String(value ?? '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .trim()
  }

  function sanitizeAlphaNum(value: string) {
    return normalizeText(value).toUpperCase().replace(/[^A-Z0-9]/g, '')
  }

  function looksLikeVin(value: string) {
    return sanitizeAlphaNum(value).length >= 11
  }

  function formatRegistrationOrVin(value: string) {
    const cleaned = sanitizeAlphaNum(value)
    if (!cleaned) return ''
    if (looksLikeVin(cleaned)) return cleaned
    if (/^[A-Z]{2}\d{3}[A-Z]{2}$/.test(cleaned)) {
      return `${cleaned.slice(0, 2)}-${cleaned.slice(2, 5)}-${cleaned.slice(5, 7)}`
    }
    return cleaned
  }

  async function findVehiculeByQuery(search: string) {
    const query = formatRegistrationOrVin(search)
    if (!query) return null

    try {
      return await api.get(`/vehicule/${encodeURIComponent(query)}`)
    } catch {
      const collection = await api.get(`/vehicules?plaque=${encodeURIComponent(query)}`).catch(() => null)
      return extractCollection(collection)[0] ?? null
    }
  }

  async function searchClients(term: string) {
    if (term.trim().length < 2) return []
    const data = await api.get(`/clients?search=${encodeURIComponent(term.trim())}`).catch(() => null)
    return extractCollection(data)
  }

  async function fetchExperts() {
    const data = await api.get('/users').catch(() => [])
    const users = extractCollection(data)

    return users.filter((user: any) => {
      const role = String(user?.role || '').toLowerCase()
      const roles = Array.isArray(user?.roles)
        ? user.roles.map((item: string) => String(item).toLowerCase())
        : []

      return role === 'admin'
        || role === 'vo_manager'
        || roles.includes('role_admin')
        || roles.includes('role_vo_manager')
    })
  }

  async function createQuickClient(payload: {
    prenom: string
    nom: string
    telephone: string
    email?: string
    adresse?: string
  }) {
    return await api.post('/clients', {
      ...payload,
      consentDate: new Date().toISOString(),
      consentSource: 'vo_workflow',
    })
  }

  async function createQuickVehicule(payload: Record<string, any>) {
    return await api.post('/vehicules', payload)
  }

  function documentLabel(type: string) {
    return documentLabels[type] || type
  }

  function buildVoDocumentUrl(document: { downloadPath?: string; filePath?: string }) {
    if (document.downloadPath) return document.downloadPath

    const filePath = String(document.filePath || '')
    if (!filePath) return '#'
    if (filePath.startsWith('/uploads/')) return filePath

    return `${apiBase}${filePath.startsWith('/') ? filePath : `/${filePath}`}`
  }

  function purchaseStatusLabel(status: string) {
    return purchaseStatusLabels[status] || status
  }

  function depotStatusLabel(status: string) {
    return depotStatusLabels[status] || status
  }

  return {
    apiBase,
    documentLabels,
    formatPrice,
    formatDate,
    extractCollection,
    normalizeText,
    formatRegistrationOrVin,
    findVehiculeByQuery,
    searchClients,
    fetchExperts,
    createQuickClient,
    createQuickVehicule,
    documentLabel,
    buildVoDocumentUrl,
    purchaseStatusLabel,
    depotStatusLabel,
  }
}