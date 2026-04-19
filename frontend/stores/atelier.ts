import { defineStore } from 'pinia'

export const DEFAULT_FEATURE_MODULES: Record<string, boolean> = {
  dashboard: true,
  rdv: true,
  rdv_siege: false,
  planning: true,
  workshop: true,
  suivi: true,
  clients: true,
  or: true,
  motos: true,
  devis: true,
  facturation: true,
  stock: true,
  mecanicien: true,
  absences: true,
  admin: true,
  tarifs: true,
  vo: false,
}

export const DEFAULT_ATELIER_BRANDING = {
  nom: 'Paddock',
  logo_url: '/branding/paddock-logo-symbol.svg',
  adresse: '',
  cp: '',
  ville: '',
  telephone: '',
  email: '',
  siret: '',
  tva_intracom: '',
}

export function normalizeFeatureModules(value?: Record<string, any> | null) {
  const normalized = { ...DEFAULT_FEATURE_MODULES }

  if (!value || typeof value !== 'object') {
    return normalized
  }

  for (const [key, enabled] of Object.entries(value)) {
    normalized[key] = !(enabled === false || enabled === 0 || enabled === '0' || enabled === 'false')
  }

  return normalized
}

export function normalizeAtelierBranding(value?: Record<string, any> | null) {
  const branding = { ...DEFAULT_ATELIER_BRANDING }

  if (!value || typeof value !== 'object') {
    return branding
  }

  for (const key of Object.keys(branding)) {
    const rawValue = value[key]
    branding[key as keyof typeof branding] = rawValue == null ? '' : String(rawValue)
  }

  if (!branding.nom) {
    branding.nom = DEFAULT_ATELIER_BRANDING.nom
  }

  if (!branding.logo_url) {
    branding.logo_url = DEFAULT_ATELIER_BRANDING.logo_url
  }

  return branding
}

export const useAtelierStore = defineStore('atelier', {
  state: () => ({
    modules: { ...DEFAULT_FEATURE_MODULES } as Record<string, boolean>,
    branding: { ...DEFAULT_ATELIER_BRANDING },
    loaded: false,
  }),

  getters: {
    isModuleEnabled: (state) => (key: string) => {
      if (!(key in state.modules)) return true
      return state.modules[key] !== false
    },
  },

  actions: {
    setModules(modules?: Record<string, any> | null) {
      this.modules = normalizeFeatureModules(modules)
      this.loaded = true
    },
    setBranding(branding?: Record<string, any> | null) {
      this.branding = normalizeAtelierBranding(branding)
    },
    setConfig(config?: Record<string, any> | null) {
      this.setModules(config?.feature_modules ?? config?.featureModules)
      this.setBranding(config?.atelier ?? null)
    },
    clearModules() {
      this.modules = { ...DEFAULT_FEATURE_MODULES }
      this.branding = { ...DEFAULT_ATELIER_BRANDING }
      this.loaded = false
    },
  },

  persist: true,
})
