/**
 * Simple debounce composable.
 * Usage:
 *   const debouncedSearch = useDebounceFn((query: string) => { ... }, 300)
 *   debouncedSearch('hello')
 */
export function useDebounceFn<T extends (...args: any[]) => any>(fn: T, delay: number = 300): (...args: Parameters<T>) => void {
  let timer: ReturnType<typeof setTimeout> | null = null

  return (...args: Parameters<T>) => {
    if (timer) clearTimeout(timer)
    timer = setTimeout(() => fn(...args), delay)
  }
}
