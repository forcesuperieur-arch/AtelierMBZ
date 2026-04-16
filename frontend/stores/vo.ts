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
  originalFilename: string
  mimeType: string
  dateExpiration?: string
  retentionYears: number
  uploadedAt: string
}

interface VOStats {
  en_stock: number
  vendus: number
  depots_actifs: number
  alerts_count: number
}

export const useVoStore = defineStore('vo', {
  state: () => ({
    purchases: [] as VOPurchase[],
    depots: [] as VODepot[],
    factures: [] as VOFacture[],
    livrePolice: [] as LivrePoliceEntry[],
    documents: [] as VODocument[],
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

    async sellDepot(id: number, data: { buyerId: number; salePrice?: string; notes?: string }) {
      const api = useApi()
      return await api.post(`/vo/depots/${id}/sell`, data)
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
  },
})
