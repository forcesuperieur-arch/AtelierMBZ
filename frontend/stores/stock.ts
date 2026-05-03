import { defineStore } from 'pinia'

interface PieceDetachee {
  id: number
  reference: string
  designation: string
  quantite_stock: number
  seuil_alerte: number
  prix_achat_ht?: number
  prix_vente_ht?: number
  [key: string]: any
}

interface Fournisseur {
  id: number
  nom: string
  contact?: string
  telephone?: string
  email?: string
  delai_livraison_jours?: number
  [key: string]: any
}

interface LigneCommande {
  piece_id: number
  quantite: number
  prix_unitaire_ht: number
  piece?: PieceDetachee
}

interface CommandeFournisseur {
  id: number
  numero_commande: string
  statut: string
  date_commande: string
  date_prevue_livraison?: string
  date_reception?: string
  total_ht: number
  total_ttc: number
  fournisseur?: Fournisseur
  lignes?: any[]
  notes?: string
  [key: string]: any
}

interface MouvementStock {
  id: number
  type: string
  quantite: number
  prix_unitaire_ht?: number
  motif?: string
  created_at: string
  piece?: PieceDetachee
  [key: string]: any
}

function normalizePiece(p: any): PieceDetachee {
  if (!p) return p
  return {
    ...p,
    designation: p.nom ?? p.designation,
    seuil_alerte: p.quantite_minimale ?? p.seuil_alerte,
  }
}

export const useStockStore = defineStore('stock', {
  state: () => ({
    pieces: [] as PieceDetachee[],
    fournisseurs: [] as Fournisseur[],
    commandes: [] as CommandeFournisseur[],
    mouvements: [] as MouvementStock[],
    loading: false,
    loadingCommandes: false,
    loadingMouvements: false,
  }),

  getters: {
    alertes: (state) => state.pieces.filter(p => p.quantite_stock <= p.seuil_alerte),
  },

  actions: {
    async fetchPieces(search?: string) {
      this.loading = true
      try {
        const api = useApi()
        const qs = search ? `?nom=${encodeURIComponent(search)}` : ''
        const raw = await api.get(`/stock/pieces${qs}`)
        const items = Array.isArray(raw) ? raw : (raw['hydra:member'] ?? raw['member'] ?? [])
        this.pieces = items.map(normalizePiece)
      } finally {
        this.loading = false
      }
    },

    async createPiece(data: any) {
      const api = useApi()
      const payload = { ...data, nom: data.designation ?? data.nom, quantite_minimale: data.seuil_alerte ?? data.quantite_minimale }
      const piece = await api.post('/stock/pieces', payload)
      this.pieces.unshift(normalizePiece(piece))
      return piece
    },

    async updatePiece(id: number, data: any) {
      const api = useApi()
      const payload = { ...data, nom: data.designation ?? data.nom, quantite_minimale: data.seuil_alerte ?? data.quantite_minimale }
      const piece = await api.put(`/stock/pieces/${id}`, payload)
      const normalized = normalizePiece(piece)
      const idx = this.pieces.findIndex(p => p.id === id)
      if (idx >= 0) this.pieces[idx] = normalized
      return normalized
    },

    async fetchFournisseurs() {
      const api = useApi()
      const raw = await api.get('/stock/fournisseurs')
      this.fournisseurs = Array.isArray(raw) ? raw : (raw['hydra:member'] ?? raw['member'] ?? [])
    },

    async createFournisseur(data: any) {
      const api = useApi()
      const f = await api.post('/stock/fournisseurs', data)
      this.fournisseurs.push(f)
      return f
    },

    async fetchCommandes(statut?: string) {
      this.loadingCommandes = true
      try {
        const api = useApi()
        const qs = statut ? `?statut=${encodeURIComponent(statut)}` : ''
        const raw = await api.get(`/stock/commandes${qs}`)
        this.commandes = raw?.member ?? raw?.['hydra:member'] ?? raw ?? []
      } finally {
        this.loadingCommandes = false
      }
    },

    async createCommande(data: any) {
      const api = useApi()
      const cmd = await api.post('/stock/commandes', data)
      this.commandes.unshift(cmd)
      return cmd
    },

    async receiveCommande(id: number, lignes: any[]) {
      const api = useApi()
      await api.post(`/stock/commandes/${id}/recevoir`, { lignes })
      await this.fetchCommandes()
      await this.fetchPieces()
    },

    async fetchMouvements(pieceId?: number) {
      this.loadingMouvements = true
      try {
        const api = useApi()
        const qs = pieceId ? `?piece_id=${pieceId}` : ''
        const raw = await api.get(`/stock/mouvements${qs}`)
        this.mouvements = raw?.member ?? raw?.['hydra:member'] ?? raw ?? []
      } finally {
        this.loadingMouvements = false
      }
    },

    async createMouvement(data: any) {
      const api = useApi()
      await api.post('/stock/mouvements', data)
      await this.fetchMouvements()
      await this.fetchPieces()
    },

    async togglePiece(id: number) {
      const api = useApi()
      const res = await api.post(`/stock/pieces/${id}/toggle`)
      const idx = this.pieces.findIndex(p => p.id === id)
      if (idx >= 0) {
        this.pieces[idx] = { ...this.pieces[idx], is_active: res.is_active }
      }
      return res
    },

    async fetchInventaire() {
      const api = useApi()
      return await api.get('/stock/inventaire')
    },
  },
})
