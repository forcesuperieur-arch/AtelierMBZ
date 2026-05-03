/**
 * Development-only logging utilities.
 * No-ops in production to keep the console clean.
 */

const isDev = typeof import.meta !== 'undefined' && import.meta.dev

export function devWarn(...args: unknown[]): void {
  if (isDev) {
    console.warn(...args)
  }
}

export function devLog(...args: unknown[]): void {
  if (isDev) {
    console.log(...args)
  }
}
