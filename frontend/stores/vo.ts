import { defineStore } from 'pinia'

interface VOPurchase {
  id: number
  atelierId?: number
  vehicule?: any
  seller?: any
  expert?: any
  purchasePrice: string
  targetSalePrice: string
  repairEstimates?: Array<{ label: string; amount: string }>
  status: string
  purchaseDate?: string
  saleDate?: string
  notes?: string
  sellerIdType?: string
  sellerIdNumber?: string
  sellerIdDate?: string
  nonGageDate?: string
  controleTechniqueOk: boolean
  regimeTva: string
  margin?: string
  totalFre?: string
  missingDocuments?: string[]
  createdAt: string
  updatedAt: string
}

interface VODepot {
  id: number
  atelierId?: number
  vehicule?: any
  deposant?: any
  gestionnaire?: any
  prixVenteSouhaite: string
  commissionType: string
  commissionValeur: string
  dateDebut: string
  dateFin?: string
  dureeMandat: number
  status: string
  conditionsRestitution?: string
  assuranceInfo?: string
  notes?: string
  deposantIdType?: string
  deposantIdNumber?: string
  deposantIdDate?: string
  prixVenteEffectif?: string
  commissionAmount?: string
  deposantNet?: string
  mandatExpire?: boolean
  missingDocuments?: string[]
  createdAt: string
  updatedAt: string
}

interface VOFacture {
  id: number
  numeroFacture: string
  regimeTva: string
  totalHt: string
  totalTva: string
  totalTtc: string
  statut: string
  snapClientNom?: string
  snapClientPrenom?: string
  snapVehiculeMarque?: string
  snapVehiculeModele?: string
  immatriculation?: string
  dateCreation: string
}

interface LivrePoliceEntry {
  id: number
  numeroOrdre: number
  type: string
  dateAcquisition: string
  dateVente?: string
  descriptionBien: string
  immatriculation: string
  vendeurNom: string
  vendeurPrenom: string
  prixAchat: string
  prixVente?: string
  acheteurNom?: string
  acheteurPrenom?: string
}

interface VODocument {
  id: number
  type: string
  filePath: string
  downloadPath?: string
  originalFilename: string
  mimeType: string
  dateExpiration?: string
  retentionYears: number
  uploadedAt: string
}

interface VORemiseEnEtatLine {
  id: number
  prestation?: { id: number; code?: string; nom?: string } | null
  libelle: string
  quantity: number
  estimatedUnitHt: string
  estimatedTotalHt: string
  actualTotalHt?: string | null
  estimatedMinutes: number
  actualMinutes?: number | null
  status: string
  notes?: string | null
  sortOrder: number
  createdAt: string
  updatedAt: string
}

interface VORemiseEnEtatPiece {
  id: number
  libelle: string
  reference?: string | null
  quantity: number
  supplier?: string | null
  estimatedUnitCostHt: string
  estimatedTotalCostHt: string
  actualTotalCostHt?: string | null
  status: string
  notes?: string | null
  createdAt: string
  updatedAt: string
}

interface VORemiseEnEtatDocumentState {
  canSign: boolean
  signed: boolean
  signedAt?: string | null
  signedBy?: any
  signedHash?: string | null
  currentHash?: string | null
  outdatedSinceSignature: boolean
  livePdfUrl: string
  archivedDocument?: {
    id: number
    type: string
    originalFilename: string
    uploadedAt: string
    url?: string
    downloadPath?: string
  } | null
}

interface VORemiseEnEtat {
  id: number
  atelierId?: number
  sourceType: 'purchase' | 'depot'
  sourceId: number
  campaignIndex: number
  titre: string
  status: string
  priority: string
  diagnosticNotes?: string | null
  workshopNotes?: string | null
  businessNotes?: string | null
  requestedBy?: any
  validatedBy?: any
  requestedAt: string
  validatedAt?: string | null
  plannedFor?: string | null
  startedAt?: string | null
  completedAt?: string | null
  closedAt?: string | null
  createdAt: string
  updatedAt: string
  vehicle?: any
  document: VORemiseEnEtatDocumentState
  isClosed: boolean
  isBlockingSale: boolean
  pendingPiecesCount: number
  costSummary: {
    estimatedMoCost: string
    estimatedPartsCost: string
    estimatedTotalCost: string
    actualMoCost: string
    actualPartsCost: string
    actualTotalCost: string
    varianceTotal: string
  }
  lignes: VORemiseEnEtatLine[]
  pieces: VORemiseEnEtatPiece[]
}

interface VOStockItem {
  id: number
  source: 'purchase' | 'depot'
  status: string
  plaque?: string
  marque?: string
  modele?: string
  annee?: number
  km?: number
  couleur?: string
  prix_achat?: string
  prix_vente?: string
  marge?: string
  total_fre?: string
  regime_tva?: string
  jours_stock?: number | null
  commission_ht?: string
  commission_ttc?: string
  deposant_net?: string
  jours_restants?: number
  mandat_expire?: boolean
  missing_docs?: string[]
  can_sell?: boolean
  created_at: string
}

interface VOStats {
  en_stock: number
  vendus: number
  depots_actifs: number
  alerts_count: number
  stock_total?: number
  stock_items?: VOStockItem[]
  mandats_expirant_7j?: number
}

export const useVoStore = defineStore('vo', {
  state: () => ({
    purchases: [] as VOPurchase[],
    depots: [] as VODepot[],
    factures: [] as VOFacture[],
    livrePolice: [] as LivrePoliceEntry[],
    documents: [] as VODocument[],
    stock: [] as VOStockItem[],
    refurbishmentQueue: [] as VORemiseEnEtat[],
    stats: null as VOStats | null,
    alerts: [] as any[],
    loading: false,
  }),

  actions: {
    // ── Purchases ──

    async fetchPurchases() {
      this.loading = true
      try {
        const api = useApi()
        this.purchases = await api.get('/vo/purchases')
      } finally {
        this.loading = false
      }
    },

    async fetchPurchase(id: number): Promise<VOPurchase> {
      const api = useApi()
      return await api.get(`/vo/purchases/${id}`)
    },

    async fetchPurchaseFull(id: number): Promise<VOPurchase & Record<string, any>> {
      const api = useApi()
      return await api.get(`/vo/purchases/${id}/full`)
    },

    async createPurchase(data: Record<string, any>): Promise<VOPurchase> {
      const api = useApi()
      const result = await api.post('/vo/purchases', data)
      await this.fetchPurchases()
      return result
    },

    async updatePurchase(id: number, data: Record<string, any>) {
      const api = useApi()
      const result = await api.patch(`/vo/purchases/${id}`, data)
      const idx = this.purchases.findIndex(p => p.id === id)
      if (idx !== -1) this.purchases[idx] = result
      return result
    },

    async confirmPurchase(id: number) {
      const api = useApi()
      return await api.post(`/vo/purchases/${id}/confirm`)
    },

    async sellPurchase(id: number, data: { buyerId: number; salePrice?: string; notes?: string }) {
      const api = useApi()
      return await api.post(`/vo/purchases/${id}/sell`, data)
    },

    // ── Dépôts-vente ──

    async fetchDepots() {
      this.loading = true
      try {
        const api = useApi()
        this.depots = await api.get('/vo/depots')
      } finally {
        this.loading = false
      }
    },

    async fetchDepot(id: number): Promise<VODepot> {
      const api = useApi()
      return await api.get(`/vo/depots/${id}`)
    },

    async fetchDepotFull(id: number): Promise<VODepot & Record<string, any>> {
      const api = useApi()
      return await api.get(`/vo/depots/${id}/full`)
    },

    async createDepot(data: Record<string, any>): Promise<VODepot> {
      const api = useApi()
      const result = await api.post('/vo/depots', data)
      await this.fetchDepots()
      return result
    },

    async updateDepot(id: number, data: Record<string, any>) {
      const api = useApi()
      return await api.patch(`/vo/depots/${id}`, data)
    },

    async restituerDepot(id: number, data?: { notes?: string }) {
      const api = useApi()
      return await api.post(`/vo/depots/${id}/restituer`, data ?? {})
    },

    async prolongerMandat(id: number, data: { dureeSupplementaire: number }) {
      const api = useApi()
      return await api.patch(`/vo/depots/${id}`, { dureeMandat: data.dureeSupplementaire })
    },

    async sellDepot(id: number, data: { buyerId: number; salePrice?: string; notes?: string }) {
      const api = useApi()
      return await api.post(`/vo/depots/${id}/sell`, data)
    },

    // ── Remises en état VO ──

    async fetchPurchaseRefurbishments(id: number): Promise<{ items: VORemiseEnEtat[]; activeCampaignId?: number | null; canCreate?: boolean }> {
      const api = useApi()
      return await api.get(`/vo/purchases/${id}/remises-en-etat`)
    },

    async createPurchaseRefurbishment(id: number, data: Record<string, any> = {}): Promise<VORemiseEnEtat> {
      const api = useApi()
      return await api.post(`/vo/purchases/${id}/remises-en-etat`, data)
    },

    async fetchDepotRefurbishments(id: number): Promise<{ items: VORemiseEnEtat[]; activeCampaignId?: number | null; canCreate?: boolean }> {
      const api = useApi()
      return await api.get(`/vo/depots/${id}/remises-en-etat`)
    },

    async createDepotRefurbishment(id: number, data: Record<string, any> = {}): Promise<VORemiseEnEtat> {
      const api = useApi()
      return await api.post(`/vo/depots/${id}/remises-en-etat`, data)
    },

    async fetchRefurbishment(id: number): Promise<VORemiseEnEtat> {
      const api = useApi()
      return await api.get(`/vo/remises-en-etat/${id}`)
    },

    async updateRefurbishment(id: number, data: Record<string, any>): Promise<VORemiseEnEtat> {
      const api = useApi()
      return await api.patch(`/vo/remises-en-etat/${id}`, data)
    },

    async signRefurbishmentDocument(id: number, signature: string): Promise<VORemiseEnEtat> {
      const api = useApi()
      return await api.post(`/vo/remises-en-etat/${id}/sign`, { signature })
    },

    async fetchApplicablePrestationsForRefurbishment(id: number): Promise<{ items: Array<Record<string, any>> }> {
      const api = useApi()
      return await api.get(`/vo/remises-en-etat/${id}/prestations-applicables`)
    },

    async addRefurbishmentLine(id: number, data: Record<string, any>): Promise<VORemiseEnEtat> {
      const api = useApi()
      return await api.post(`/vo/remises-en-etat/${id}/lignes`, data)
    },

    async updateRefurbishmentLine(id: number, data: Record<string, any>): Promise<VORemiseEnEtat> {
      const api = useApi()
      return await api.patch(`/vo/remises-en-etat/lignes/${id}`, data)
    },

    async deleteRefurbishmentLine(id: number): Promise<VORemiseEnEtat> {
      const api = useApi()
      return await api.del(`/vo/remises-en-etat/lignes/${id}`)
    },

    async addRefurbishmentPiece(id: number, data: Record<string, any>): Promise<VORemiseEnEtat> {
      const api = useApi()
      return await api.post(`/vo/remises-en-etat/${id}/pieces`, data)
    },

    async updateRefurbishmentPiece(id: number, data: Record<string, any>): Promise<VORemiseEnEtat> {
      const api = useApi()
      return await api.patch(`/vo/remises-en-etat/pieces/${id}`, data)
    },

    async deleteRefurbishmentPiece(id: number): Promise<VORemiseEnEtat> {
      const api = useApi()
      return await api.del(`/vo/remises-en-etat/pieces/${id}`)
    },

    async fetchRefurbishmentQueue() {
      const api = useApi()
      const payload = await api.get('/vo/remises-en-etat/queue')
      this.refurbishmentQueue = payload.items ?? []
      return payload
    },

    // ── Factures ──

    async fetchFactures() {
      const api = useApi()
      this.factures = await api.get('/vo/factures')
    },

    // ── Livre de Police ──

    async fetchLivrePolice() {
      const api = useApi()
      this.livrePolice = await api.get('/vo/livre-police')
    },

    // ── Documents ──

    async fetchDocuments(params: { purchaseId?: number; depotId?: number } = {}) {
      const api = useApi()
      const qs = new URLSearchParams()
      if (params.purchaseId) qs.set('purchaseId', String(params.purchaseId))
      if (params.depotId) qs.set('depotId', String(params.depotId))
      const q = qs.toString()
      this.documents = await api.get(`/vo/documents${q ? '?' + q : ''}`)
    },

    async uploadDocument(formData: FormData) {
      const api = useApi()
      return await api.upload('/vo/documents/upload', formData)
    },

    async fetchAlerts() {
      const api = useApi()
      this.alerts = await api.get('/vo/documents/alerts')
    },

    async fetchStock(query?: string, limit?: number) {
      const api = useApi()
      const qs = new URLSearchParams()
      if (query) qs.set('q', query)
      if (typeof limit === 'number' && limit > 0) qs.set('limit', String(limit))

      const payload = await api.get(`/vo/stock${qs.toString() ? `?${qs.toString()}` : ''}`)
      this.stock = payload.items ?? []
      return payload
    },

    // ── Stats ──

    async fetchStats() {
      const api = useApi()
      this.stats = await api.get('/vo/stats')
    },

    // ── Margin calculation ──

    async calculateMargin(data: { regime: string; purchasePrice: string; salePrice: string }) {
      const api = useApi()
      return await api.post('/vo/margin/calculate', data)
    },

    async simulateMargin(data: {
      purchasePrice: string
      salePrice: string
      regime: string
      freItems: Array<{ label: string; amount: string }>
    }) {
      const api = useApi()
      return await api.post('/vo/margin/simulate', data)
    },
  },
})
