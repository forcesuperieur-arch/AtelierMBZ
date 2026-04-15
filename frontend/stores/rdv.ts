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

function normalizeDate(value: unknown): string {
  const raw = value ? String(value) : ''
  return raw ? raw.slice(0, 10) : ''
}

function normalizeTime(value: unknown): string {
  const raw = value ? String(value) : ''
  const match = raw.match(/(\d{2}):(\d{2})/)
  return match ? `${match[1]}:${match[2]}` : ''
}

/** Normalize API Platform nested response into flat fields expected by templates */
function resolveRdvId(value: unknown): number {
  if (typeof value === 'number' && Number.isFinite(value)) return value

  if (typeof value === 'string') {
    const parsed = Number(value.split('/').pop())
    if (Number.isFinite(parsed)) return parsed
  }

  if (value && typeof value === 'object') {
    const candidate = (value as any).id ?? (value as any)['@id']
    return resolveRdvId(candidate)
  }

  throw new Error(`Invalid RDV identifier: ${String(value)}`)
}

function normalizeRdv(r: any): Rdv {
  if (!r) return r
  return {
    ...r,
    // Flat aliases for nested objects
    status: r.statut ?? r.status,
    date_rdv: normalizeDate(r.date_rdv ?? r.dateRdv),
    heure_debut: normalizeTime(r.heure_rdv ?? r.heureRdv ?? r.heure_debut),
    type_intervention: r.type_intervention ?? r.typeIntervention,
    client_nom: r.client ? `${r.client.prenom ?? ''} ${r.client.nom ?? ''}`.trim() : (r.client_nom ?? ''),
    client_telephone: r.client?.telephone ?? r.client_telephone,
    client_email: r.client?.email ?? r.client_email,
    vehicule_info: r.vehicule ? `${r.vehicule.marque ?? ''} ${r.vehicule.modele ?? ''}`.trim() : (r.vehicule_info ?? ''),
    vehicule_plaque: r.vehicule?.plaque ?? r.vehicule_plaque,
    pont_nom: r.pont?.nom ?? r.pont_nom,
    mecanicien_nom: r.mecanicien ? `${r.mecanicien.prenom ?? ''} ${r.mecanicien.nom ?? ''}`.trim() : (r.mecanicien_nom ?? ''),
    temps_estime: r.temps_estime ?? r.tempsEstime ?? r.duree_estimee,
    duree_estimee: r.temps_estime ?? r.tempsEstime ?? r.duree_estimee,
    description_probleme: r.commentaire ?? r.description_probleme ?? r.descriptionProbleme,
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

    async fetchRdv(id: number | string | Record<string, any>) {
      const api = useApi()
      const rdvId = resolveRdvId(id)
      const raw = await api.get(`/rendez-vous/${rdvId}`)
      this.currentRdv = normalizeRdv(raw)
    },

    async createRdv(data: any) {
      const api = useApi()
      const rdv = await api.post('/rendez-vous', data)
      const normalized = normalizeRdv(rdv)
      this.rdvs.unshift(normalized)
      return normalized
    },

    async updateRdv(id: number | string | Record<string, any>, data: any) {
      const api = useApi()
      const rdvId = resolveRdvId(id)
      const rdv = await api.put(`/rendez-vous/${rdvId}`, data)
      const normalized = normalizeRdv(rdv)
      const idx = this.rdvs.findIndex(r => r.id === rdvId)
      if (idx >= 0) this.rdvs[idx] = normalized
      if (this.currentRdv?.id === rdvId) this.currentRdv = normalized
      return normalized
    },

    async transitionRdv(id: number | string | Record<string, any>, transition: string) {
      const api = useApi()
      const rdvId = resolveRdvId(id)
      await api.post(`/rendez-vous/${rdvId}/transition/${transition}`)
      // Transition endpoint returns { id, statut, transitions } — re-fetch to get full data
      await this.fetchRdv(rdvId)
      return this.currentRdv
    },

    setFilters(f: RdvFilters) {
      this.filters = f
    },
  },
})
