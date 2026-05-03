import { defineStore } from 'pinia'
import { unwrapHydraOrEmpty } from '~/utils/hydra'

interface Facture {
  id: number
  numero_facture: string
  [key: string]: any
}

function normalizeFacture(f: any): Facture {
  if (!f) return f
  return {
    ...f,
    client_nom: f.client ? `${f.client.prenom ?? ''} ${f.client.nom ?? ''}`.trim() : (f.client_nom ?? ''),
    date_creation: f.created_at ?? f.date_creation,
  }
}

export const useBillingStore = defineStore('billing', {
  state: () => ({
    factures: [] as Facture[],
    currentFacture: null as Facture | null,
    loading: false,
  }),

  actions: {
    async fetchFactures(params?: Record<string, string>) {
      this.loading = true
      try {
        const api = useApi()
        const qs = params ? '?' + new URLSearchParams(params).toString() : ''
        const raw = await api.get(`/facturation${qs}`)
        this.factures = unwrapHydraOrEmpty(raw).map(normalizeFacture)
      } finally {
        this.loading = false
      }
    },

    async createFacture(rdvId: number) {
      const api = useApi()
      const facture = await api.post(`/facturation/rendez-vous/${rdvId}`)
      this.factures.unshift(facture)
      return facture
    },

    async addPayment(factureId: number, data: any) {
      const api = useApi()
      return api.post(`/facturation/${factureId}/paiement`, data)
    },

    async createAvoir(factureId: number, motif: string) {
      const api = useApi()
      return api.post(`/facturation/${factureId}/avoir`, { motif })
    },

    downloadPdf(factureId: number) {
      const { openPdf } = usePdfDownload()
      openPdf(`/facturation/${factureId}/pdf`)
    },
  },
})
