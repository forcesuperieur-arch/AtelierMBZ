import { defineStore } from 'pinia'

export const useAppStore = defineStore('app', {
  state: () => ({
    sidebarOpen: true,
    loading: false,
    currentSection: 'dashboard',
  }),

  actions: {
    toggleSidebar() {
      this.sidebarOpen = !this.sidebarOpen
    },
    setSection(section: string) {
      this.currentSection = section
    },
    setLoading(val: boolean) {
      this.loading = val
    },
  },
})
