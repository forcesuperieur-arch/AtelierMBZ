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

function toApiRelation(value: any, resource: string): string | null | undefined {
  if (value === undefined) return undefined
  if (value === null || value === '') return null

  if (typeof value === 'string') {
    if (value.startsWith('/api/')) return value
    const parsed = Number(value.split('/').pop())
    return Number.isFinite(parsed) ? `/api/${resource}/${parsed}` : null
  }

  if (typeof value === 'number' && Number.isFinite(value)) {
    return `/api/${resource}/${value}`
  }

  if (value && typeof value === 'object') {
    const iri = (value as any)['@id']
    if (typeof iri === 'string' && iri.startsWith('/api/')) return iri
    const parsed = Number((value as any).id)
    return Number.isFinite(parsed) ? `/api/${resource}/${parsed}` : null
  }

  return null
}

function buildUpdatePayload(base: any, data: any) {
  const heureValue = data.heure_rdv ?? data.heureRdv ?? data.heure_debut ?? base?.heure_rdv ?? base?.heureRdv ?? base?.heure_debut ?? '09:00'
  const payload: Record<string, any> = {
    date_rdv: data.date_rdv ?? data.dateRdv ?? base?.date_rdv ?? base?.dateRdv,
    heure_rdv: normalizeTime(heureValue),
    type_intervention: data.type_intervention ?? data.typeIntervention ?? base?.type_intervention ?? base?.typeIntervention ?? '',
    commentaire: data.commentaire ?? base?.commentaire ?? null,
    temps_estime: data.temps_estime ?? data.tempsEstime ?? base?.temps_estime ?? base?.tempsEstime ?? null,
    prix_estime: data.prix_estime ?? data.prixEstime ?? base?.prix_estime ?? base?.prixEstime ?? null,
    prix_final: data.prix_final ?? data.prixFinal ?? base?.prix_final ?? base?.prixFinal ?? null,
    kilometrage: data.kilometrage ?? base?.kilometrage ?? null,
    etat_vehicule: data.etat_vehicule ?? data.etatVehicule ?? base?.etat_vehicule ?? base?.etatVehicule ?? null,
    photos_etat: data.photos_etat ?? data.photosEtat ?? base?.photos_etat ?? base?.photosEtat ?? null,
    statut: data.statut ?? data.status ?? base?.statut ?? base?.status ?? 'en_attente',
  }

  const client = data.client !== undefined ? toApiRelation(data.client, 'clients') : toApiRelation(base?.client, 'clients')
  const vehicule = data.vehicule !== undefined ? toApiRelation(data.vehicule, 'vehicules') : toApiRelation(base?.vehicule, 'vehicules')
  const pont = data.pont !== undefined ? toApiRelation(data.pont, 'ponts') : ('pont_id' in data ? toApiRelation(data.pont_id, 'ponts') : toApiRelation(base?.pont ?? base?.pont_id, 'ponts'))
  const mecanicien = data.mecanicien !== undefined ? toApiRelation(data.mecanicien, 'mecaniciens') : ('mecanicien_id' in data ? toApiRelation(data.mecanicien_id, 'mecaniciens') : toApiRelation(base?.mecanicien ?? base?.mecanicien_id, 'mecaniciens'))

  if (client !== undefined) payload.client = client
  if (vehicule !== undefined) payload.vehicule = vehicule
  if (pont !== undefined) payload.pont = pont
  if (mecanicien !== undefined) payload.mecanicien = mecanicien

  return { ...payload, ...data }
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
        if (f.date) {
          const d = new Date(f.date)
          d.setDate(d.getDate() + 1)
          params.set('dateRdv[before]', d.toISOString().slice(0, 10))
        }
        if (f.status) params.set('statut', f.status)
        if (f.mecanicien_id) params.set('mecanicien.id', String(f.mecanicien_id))
        if (f.pont_id) params.set('pont.id', String(f.pont_id))
        // [SPRINT-4] I3 — Explicit pagination to avoid Hydra default 30-item cap
        params.set('itemsPerPage', '200')
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
      const atelierId = Number(data?.atelier_id ?? data?.atelierId ?? 0)
      const suffix = atelierId > 0 ? `?atelier_id=${atelierId}` : ''
      const rdv = await api.post(`/rendez-vous${suffix}`, data)
      const normalized = normalizeRdv(rdv)
      this.rdvs.unshift(normalized)
      return normalized
    },

    async updateRdv(id: number | string | Record<string, any>, data: any) {
      const api = useApi()
      const rdvId = resolveRdvId(id)

      let base = this.currentRdv?.id === rdvId
        ? this.currentRdv
        : this.rdvs.find(r => r.id === rdvId) ?? null

      if (!base) {
        const raw = await api.get(`/rendez-vous/${rdvId}`)
        base = normalizeRdv(raw)
      }

      const payload = buildUpdatePayload(base, data)
      const rdv = await api.put(`/rendez-vous/${rdvId}`, payload)
      const normalized = normalizeRdv(rdv)
      const idx = this.rdvs.findIndex(r => r.id === rdvId)
      if (idx >= 0) this.rdvs[idx] = normalized
      if (this.currentRdv?.id === rdvId) this.currentRdv = normalized
      return normalized
    },

    async transitionRdv(id: number | string | Record<string, any>, transition: string, body?: Record<string, any>) {
      const api = useApi()
      const rdvId = resolveRdvId(id)
      await api.post(`/rendez-vous/${rdvId}/transition/${transition}`, body ?? {})
      // Transition endpoint returns { id, statut, transitions } — re-fetch to get full data
      await this.fetchRdv(rdvId)
      return this.currentRdv
    },

    setFilters(f: RdvFilters) {
      this.filters = f
    },
  },
})
