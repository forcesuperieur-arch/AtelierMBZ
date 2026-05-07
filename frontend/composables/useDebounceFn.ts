/**
 * Simple debounce composable.
 * Usage:
 *   const debouncedSearch = useDebounceFn((query: string) => { ... }, 300)
 *   debouncedSearch('hello')
 */
type DebouncedFn<T extends (...args: any[]) => any> = ((...args: Parameters<T>) => void) & { cancel: () => void }

export function useDebounceFn<T extends (...args: any[]) => any>(fn: T, delay: number = 300): DebouncedFn<T> {
  let timer: ReturnType<typeof setTimeout> | null = null

  const debounced = (...args: Parameters<T>) => {
    if (timer) clearTimeout(timer)
    timer = setTimeout(() => fn(...args), delay)
  }

  debounced.cancel = () => {
    if (timer) {
      clearTimeout(timer)
      timer = null
    }
  }

  return debounced
}
