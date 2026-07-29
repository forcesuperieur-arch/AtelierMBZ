/**
 * Bascule clair / sombre du portail client.
 *
 * Le front staff s'appuie sur @nuxtjs/color-mode (fourni par @nuxt/ui). Le
 * portail client n'embarque pas ce module et en ajouter un imposerait de
 * reconstruire l'image Docker : on reproduit donc le strict nécessaire à la
 * main, avec la MÊME clé de stockage et les mêmes valeurs, pour que les deux
 * applications se comportent pareil.
 *
 * L'application initiale de l'attribut est faite par un script en ligne dans
 * `nuxt.config.ts`, avant la première peinture : sans lui, la page s'afficherait
 * en clair (valeur par défaut de `:root`) le temps que le bundle démarre.
 */
export type ThemePreference = 'system' | 'light' | 'dark'

const STORAGE_KEY = 'paddock-theme'

function readStoredPreference(): ThemePreference {
  if (typeof window === 'undefined') return 'system'
  try {
    const stored = window.localStorage.getItem(STORAGE_KEY)
    return stored === 'light' || stored === 'dark' || stored === 'system' ? stored : 'system'
  } catch {
    // Navigation privée ou stockage refusé : la préférence système fait office.
    return 'system'
  }
}

export function useTheme() {
  const preference = useState<ThemePreference>('paddock-theme-preference', readStoredPreference)
  // Le repli est le thème sombre, comme côté staff (`fallback: 'dark'`).
  const prefersLight = useState<boolean>('paddock-theme-system-light', () => false)

  const isDark = computed(() =>
    preference.value === 'system' ? !prefersLight.value : preference.value === 'dark',
  )

  function apply() {
    if (typeof document === 'undefined') return
    const root = document.documentElement
    const value = isDark.value ? 'dark' : 'light'
    root.setAttribute('data-theme', value)
    // La classe est posée en plus de l'attribut : elle sert de point d'accroche
    // aux styles écrits pour le staff, où @nuxt/ui la pose de son côté.
    root.classList.toggle('dark', isDark.value)
    root.classList.toggle('light', !isDark.value)
  }

  onMounted(() => {
    if (!window.matchMedia) {
      apply()
      return
    }
    const query = window.matchMedia('(prefers-color-scheme: light)')
    prefersLight.value = query.matches
    const onChange = (event: MediaQueryListEvent) => { prefersLight.value = event.matches }
    query.addEventListener('change', onChange)
    onBeforeUnmount(() => query.removeEventListener('change', onChange))
    apply()
  })

  watch(isDark, apply)

  function setPreference(next: ThemePreference) {
    preference.value = next
    try {
      window.localStorage.setItem(STORAGE_KEY, next)
    } catch {
      // Le thème reste appliqué pour la session même sans persistance.
    }
  }

  /** Rend la préférence explicite : un premier clic depuis « système » fige le contraire. */
  function toggle() {
    setPreference(isDark.value ? 'light' : 'dark')
  }

  return { preference, isDark, toggle, setPreference }
}
