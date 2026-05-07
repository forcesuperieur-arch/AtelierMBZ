import { defineStore } from 'pinia'

export interface Toast {
  id: number
  message: string
  type: 'success' | 'error' | 'warning' | 'info'
  createdAt: number
}

export const useAppStore = defineStore('app', () => {
  const user = ref<{ name: string; role: string } | null>(null)
  const is2FAEnabled = ref(true)
  const toasts = ref<Toast[]>([])
  let toastId = 0

  function setUser(u: { name: string; role: string }) {
    user.value = u
  }

  function logout() {
    user.value = null
  }

  function addToast(message: string, type: Toast['type'] = 'info') {
    const id = ++toastId
    const toast: Toast = {
      id,
      message,
      type,
      createdAt: Date.now(),
    }
    toasts.value.push(toast)

    // Auto-dismiss après 4s
    setTimeout(() => {
      removeToast(id)
    }, 4000)
  }

  function removeToast(id: number) {
    const index = toasts.value.findIndex((t) => t.id === id)
    if (index !== -1) {
      toasts.value.splice(index, 1)
    }
  }

  const visibleToasts = computed(() => {
    // Max 3 visibles (les plus récents)
    return toasts.value.slice(-3)
  })

  return {
    user,
    is2FAEnabled,
    toasts,
    visibleToasts,
    setUser,
    logout,
    addToast,
    removeToast,
  }
})
