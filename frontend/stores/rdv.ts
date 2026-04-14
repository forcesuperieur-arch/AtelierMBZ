import { defineStore } from 'pinia'

interface Rdv {
  id: number
  [key: string]: any
}

interface RdvFilters {
  date?: string
  status?: string
  mecanicien_id?: number
  pont_id?: number
  search?: string
}

/** Normalize API Platform nested response into flat fields expected by templates */
function normalizeRdv(r: any): Rdv {
  if (!r) return r
  return {
    ...r,
    // Flat aliases for nested objects
    status: r.statut ?? r.status,
    date_rdv: r.date_rdv,
    heure_debut: r.heure_rdv,
    type_intervention: r.type_intervention,
    client_nom: r.client ? `${r.client.prenom ?? ''} ${r.client.nom ?? ''}`.trim() : (r.client_nom ?? ''),
    client_telephone: r.client?.telephone ?? r.client_telephone,
    client_email: r.client?.email ?? r.client_email,
    vehicule_info: r.vehicule ? `${r.vehicule.marque ?? ''} ${r.vehicule.modele ?? ''}`.trim() : (r.vehicule_info ?? ''),
    vehicule_plaque: r.vehicule?.plaque ?? r.vehicule_plaque,
    pont_nom: r.pont?.nom ?? r.pont_nom,
    mecanicien_nom: r.mecanicien ? `${r.mecanicien.prenom ?? ''} ${r.mecanicien.nom ?? ''}`.trim() : (r.mecanicien_nom ?? ''),
    duree_estimee: r.temps_estime ?? r.duree_estimee,
    description_probleme: r.commentaire ?? r.description_probleme,
  }
}

export const useRdvStore = defineStore('rdv', {
  state: () => ({
    rdvs: [] as Rdv[],
    currentRdv: null as Rdv | null,
    filters: {} as RdvFilters,
    loading: false,
    planningDate: new Date().toISOString().slice(0, 10),
  }),

  actions: {
    async fetchRdvs(filters?: RdvFilters) {
      this.loading = true
      try {
        const api = useApi()
        const params = new URLSearchParams()
        const f = filters || this.filters
        if (f.date) params.set('dateRdv[after]', f.date)
        if (f.date) params.set('dateRdv[before]', f.date)
        if (f.status) params.set('statut', f.status)
        if (f.mecanicien_id) params.set('mecanicien.id', String(f.mecanicien_id))
        if (f.pont_id) params.set('pont.id', String(f.pont_id))
        const qs = params.toString()
        const raw = await api.get(`/rendez-vous${qs ? '?' + qs : ''}`)
        // API Platform may return hydra collection or plain array
        const items = Array.isArray(raw) ? raw : (raw['hydra:member'] ?? raw['member'] ?? [])
        this.rdvs = items.map(normalizeRdv)
      } finally {
        this.loading = false
      }
    },

    async fetchRdv(id: number) {
      const api = useApi()
      const raw = await api.get(`/rendez-vous/${id}`)
      this.currentRdv = normalizeRdv(raw)
    },

    async createRdv(data: any) {
      const api = useApi()
      const rdv = await api.post('/rendez-vous', data)
      const normalized = normalizeRdv(rdv)
      this.rdvs.unshift(normalized)
      return normalized
    },

    async updateRdv(id: number, data: any) {
      const api = useApi()
      const rdv = await api.put(`/rendez-vous/${id}`, data)
      const normalized = normalizeRdv(rdv)
      const idx = this.rdvs.findIndex(r => r.id === id)
      if (idx >= 0) this.rdvs[idx] = normalized
      if (this.currentRdv?.id === id) this.currentRdv = normalized
      return normalized
    },

    async transitionRdv(id: number, transition: string) {
      const api = useApi()
      const result = await api.post(`/rendez-vous/${id}/transition/${transition}`)
      // Transition endpoint returns { id, statut, transitions } — re-fetch to get full data
      await this.fetchRdv(id)
      return this.currentRdv
    },

    setFilters(f: RdvFilters) {
      this.filters = f
    },
  },
})
