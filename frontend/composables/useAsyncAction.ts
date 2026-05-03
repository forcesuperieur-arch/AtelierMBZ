import type { ToastOptions } from '#ui/types'

interface AsyncActionOptions {
  successToast?: Partial<ToastOptions>
  errorToast?: Partial<ToastOptions>
  suppressErrorToast?: boolean
}

/**
 * Encapsule le pattern répétitif : loading + try/catch + toast.
 * Remplace les ~292 blocs try/catch identiques du projet.
 *
 * Usage :
 * const { loading, error, execute } = useAsyncAction(async () => {
 *   await api.post('/endpoint', data)
 *   toast.add({ title: 'OK', color: 'green' })
 * })
 *
 * <UButton :loading="loading" @click="execute" />
 */
export function useAsyncAction<T>(
  fn: () => Promise<T>,
  options: AsyncActionOptions = {},
) {
  const loading = ref(false)
  const error = ref<string | null>(null)
  const toast = useToast()

  async function execute(): Promise<T | undefined> {
    loading.value = true
    error.value = null

    try {
      const result = await fn()

      if (options.successToast) {
        toast.add({
          color: 'green',
          ...options.successToast,
        } as ToastOptions)
      }

      return result
    }
    catch (e: unknown) {
      const msg = e instanceof Error ? e.message : 'Erreur inconnue'
      error.value = msg

      if (!options.suppressErrorToast) {
        toast.add({
          title: options.errorToast?.title ?? 'Erreur',
          description: (options.errorToast?.description as string) ?? msg,
          color: 'red',
          ...options.errorToast,
        } as ToastOptions)
      }

      return undefined
    }
    finally {
      loading.value = false
    }
  }

  return {
    loading: readonly(loading),
    error: readonly(error),
    execute,
  }
}
