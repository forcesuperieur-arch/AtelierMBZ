import { defineStore } from 'pinia'

interface UserData {
  id: number
  email: string
  username: string
  nom: string
  prenom: string
  role: string
  atelier_id: number
  atelier_nom: string
  role_permissions: {
    sections_json: string[]
    permissions_json: string[]
  }
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
