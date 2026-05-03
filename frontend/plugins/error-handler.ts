export default defineNuxtPlugin((nuxtApp) => {
  nuxtApp.vueApp.config.errorHandler = (error, instance, info) => {
    // Log to console in development
    if (process.dev) {
      // eslint-disable-next-line no-console
      console.error('[Vue Error]', error, info)
    }

    // Send to any monitoring service here
    // e.g. Sentry.captureException(error)

    // Show a toast if toast is available (best-effort)
    try {
      const toast = useToast()
      toast.add({
        title: 'Erreur inattendue',
        description: error instanceof Error ? error.message : 'Une erreur est survenue.',
        color: 'error',
      })
    } catch {
      // Toast may not be available during SSR or early init
    }
  }
})
