import { defineStore } from 'pinia'

interface PieceDetachee {
  id: number
  reference: string
  designation: string
  quantite_stock: number
  seuil_alerte: number
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
    loading: false,
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
      // Map frontend field names to API field names
      const { fournisseur, ...rest } = data
      const payload = { ...rest, nom: data.designation ?? data.nom, quantite_minimale: data.seuil_alerte ?? data.quantite_minimale }
      const piece = await api.post('/stock/pieces', payload)
      this.pieces.unshift(normalizePiece(piece))
      return piece
    },

    async updatePiece(id: number, data: any) {
      const api = useApi()
      const { fournisseur, ...rest } = data
      const payload = { ...rest, nom: data.designation ?? data.nom, quantite_minimale: data.seuil_alerte ?? data.quantite_minimale }
      const piece = await api.put(`/stock/pieces/${id}`, payload)
      const normalized = normalizePiece(piece)
      const idx = this.pieces.findIndex(p => p.id === id)
      if (idx >= 0) this.pieces[idx] = normalized
      return normalized
    },
  },
})
