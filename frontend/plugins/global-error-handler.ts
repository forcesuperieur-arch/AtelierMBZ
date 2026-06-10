export default defineNuxtPlugin((nuxtApp) => {
  nuxtApp.hook('vue:error', (err) => {
    const error = err instanceof Error ? err : new Error(String(err))

    // Ignore certain known non-critical errors
    if (
      /ResizeObserver loop limit exceeded/i.test(error.message) ||
      /Loading chunk \d+ failed/i.test(error.message) ||
      /cancelled/i.test(error.message)
    ) {
      return
    }

    // Log to console
    if (typeof console !== 'undefined') {
      console.error('[GlobalError]', error)
    }

    // Show toast notification on client side
    if (process.client) {
      try {
        const toast = useToast()
        const message = error.message || 'Une erreur inattendue est survenue'
        toast.add({
          title: 'Erreur',
          description: message.slice(0, 200),
          color: 'error',
          duration: 5000,
        })
      } catch {
        // Toast may not be available during SSR or early init
      }
    }
  })
})
