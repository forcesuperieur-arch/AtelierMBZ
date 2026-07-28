/**
 * Chemin du logo Paddock pour le portail client.
 *
 * Deux points à respecter :
 *
 * 1. Le portail est servi sous `/client` (`app.baseURL`). Un chemin écrit
 *    `/branding/…` ne pointe PAS sur ses propres fichiers mais sur la racine du
 *    domaine : il ne fonctionnait que parce que l'application staff sert un
 *    fichier du même nom derrière Caddy. On préfixe donc par la base réelle.
 *
 * 2. Le mot-symbole des fichiers de marque est en blanc cassé (« Place on dark
 *    background ») : sur le thème clair il faut la variante `-light.svg`,
 *    produite par `scripts/design/build-logos.mjs`.
 */
export function useBrandLogo() {
  const { isDark } = useTheme()
  const base = useRuntimeConfig().app.baseURL.replace(/\/$/, '')

  const stacked = computed(
    () => `${base}/branding/paddock-logo-stacked${isDark.value ? '' : '-light'}.svg`,
  )

  return { isDark, stacked }
}
