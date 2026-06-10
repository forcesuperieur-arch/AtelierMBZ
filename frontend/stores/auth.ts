import { defineStore } from 'pinia'

interface UserData {
  id: number
  email: string
  username: string
  nom?: string | null
  prenom?: string | null
  phoneNumber?: string | null
  role: string
  roles?: string[]
  atelier_id?: number | null
  atelier_nom?: string | null
  auth_provider?: string
  access_status?: string
  is_pending_validation?: boolean
  needs_atelier_assignment?: boolean
  role_permissions?: {
    sections_json: string[]
    permissions_json: string[]
  } | null
  role_metier?: {
    id: number
    code: string
    libelle: string
    base_role?: string
    permissions?: Array<{ module: string; action: string; scope: string }>
  } | null
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as UserData | null,
  }),

  getters: {
    isAuthenticated: (state) => !!state.user,
    fullName: (state) => state.user?.username ?? '',
    role: (state) => state.user?.role || '',
    atelierId: (state) => state.user?.atelier_id || null,
    atelierNom: (state) => state.user?.atelier_nom || '',
  },

  actions: {
    setUser(user: UserData) {
      this.user = user
    },
    clearUser() {
      this.user = null
    },
  },

  persist: true,
})
