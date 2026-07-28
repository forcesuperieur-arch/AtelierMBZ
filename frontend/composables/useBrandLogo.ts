/**
 * Chemins des logos Paddock selon le thème.
 *
 * Les fichiers de marque écrivent le mot-symbole en blanc cassé : posé sur le
 * thème clair il devient illisible. Les variantes `-light.svg` (générées par
 * `scripts/design/build-logos.mjs`) l'encrent en noir.
 *
 * Le logo « symbole » n'est qu'un tracé doré, sans texte : il tient sur les
 * deux fonds et n'a pas de variante.
 */
export function useBrandLogo() {
  const colorMode = useColorMode()
  const isDark = computed(() => colorMode.value !== 'light')

  const suffix = computed(() => (isDark.value ? '' : '-light'))

  return {
    isDark,
    horizontal: computed(() => `/branding/paddock-logo-horizontal${suffix.value}.svg`),
    stacked: computed(() => `/branding/paddock-logo-stacked${suffix.value}.svg`),
    symbol: computed(() => '/branding/paddock-logo-symbol.svg'),
  }
}
