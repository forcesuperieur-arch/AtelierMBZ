/**
 * Résolution des tokens de couleur pour les graphiques.
 *
 * Chart.js peint dans un <canvas> : contrairement au DOM, le canvas ne sait pas
 * résoudre une propriété personnalisée CSS. Une série déclarée
 * `backgroundColor: 'var(--accent)'` sortirait donc sans couleur.
 *
 * Les pages continuent de déclarer des tokens — c'est ce qui permet aux
 * graphiques de suivre le thème — et ce composable les convertit en valeurs
 * calculées juste avant de les passer à Chart.js. La conversion est refaite à
 * chaque changement de thème, sinon un passage en clair laisserait les courbes
 * aux couleurs du thème sombre.
 */
import { computed, ref, onMounted, onBeforeUnmount } from 'vue'
import type { ComputedRef } from 'vue'

/** Incrémenté à chaque bascule de thème : sert de dépendance aux `computed`. */
const themeRevision = ref(0)
let observer: MutationObserver | null = null
let watchers = 0

/** Remplace récursivement toute chaîne `var(--token)` par sa valeur calculée. */
export function resolveTokens<T>(input: T): T {
  if (typeof window === 'undefined') return input
  const styles = getComputedStyle(document.documentElement)

  const resolveString = (value: string): string =>
    value.replace(/var\((--[\w-]+)(?:\s*,\s*([^)]+))?\)/g, (_match, name, fallback) => {
      const resolved = styles.getPropertyValue(name).trim()
      return resolved || (fallback ?? '').trim()
    })

  const walk = (value: unknown): unknown => {
    if (typeof value === 'string') return value.includes('var(--') ? resolveString(value) : value
    if (Array.isArray(value)) return value.map(walk)
    // On ne recopie que les objets simples : un objet de classe (une échelle
    // Chart.js déjà instanciée, par exemple) ne doit pas être reconstruit.
    if (value && typeof value === 'object' && (value.constructor === Object || value.constructor === undefined)) {
      const out: Record<string, unknown> = {}
      for (const [key, item] of Object.entries(value as Record<string, unknown>)) out[key] = walk(item)
      return out
    }
    return value
  }

  return walk(input) as T
}

/**
 * Renvoie une version de `source` dont les tokens sont résolus, recalculée à
 * chaque changement de thème.
 */
export function useThemedChart<T>(source: () => T): ComputedRef<T> {
  onMounted(() => {
    watchers++
    if (observer) return
    // Le thème se matérialise par l'attribut `data-theme` sur <html> (posé par
    // @nuxtjs/color-mode) : on observe l'attribut plutôt que d'écouter un
    // événement applicatif, ce qui couvre aussi la bascule « système ».
    observer = new MutationObserver(() => { themeRevision.value++ })
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme', 'class'] })
  })

  onBeforeUnmount(() => {
    watchers--
    if (watchers <= 0 && observer) {
      observer.disconnect()
      observer = null
    }
  })

  return computed(() => {
    // Dépendance explicite : sans cette lecture, le thème changerait sans que
    // le graphique soit recalculé.
    void themeRevision.value
    return resolveTokens(source())
  })
}

/** Réexporté pour les rares appels impératifs (création d'un dégradé canvas). */
export function useThemeRevision() {
  return themeRevision
}

/**
 * Applique une opacité à une couleur déjà résolue.
 *
 * Les graphiques remplissaient leur aire en concaténant un suffixe hexadécimal
 * (`couleur + '20'`), ce qui ne fonctionne que sur un `#RRGGBB`. Une fois les
 * tokens résolus, la valeur peut être un `rgb()` ou un `rgba()` : il faut donc
 * traiter les trois formes.
 */
export function withAlpha(color: string, alpha: number): string {
  const value = color.trim()

  const hex = value.match(/^#([0-9a-f]{6})$/i)
  if (hex) {
    const suffix = Math.round(Math.min(Math.max(alpha, 0), 1) * 255)
      .toString(16)
      .padStart(2, '0')
    return `#${hex[1]}${suffix}`
  }

  const rgb = value.match(/^rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)\s*(?:,\s*([\d.]+)\s*)?\)$/)
  if (rgb) {
    const existing = rgb[4] === undefined ? 1 : parseFloat(rgb[4])
    return `rgba(${rgb[1]}, ${rgb[2]}, ${rgb[3]}, ${(existing * alpha).toFixed(3)})`
  }

  // Forme non reconnue (mot-clé CSS, dégradé) : on la rend telle quelle plutôt
  // que de fabriquer une couleur invalide que Chart.js ignorerait en silence.
  return value
}
