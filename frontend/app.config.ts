/**
 * Palette des composants Nuxt UI.
 *
 * Les composants du framework (UButton, UInput, UCard, UTable, UFormField…)
 * ne lisent pas les variables de `tokens.css` : ils prennent leur accent dans
 * la palette Tailwind désignée ici. Sans cette configuration, `--ui-primary`
 * restait le VERT par défaut de Nuxt UI — ni Paddock, ni Motoblouz — alors que
 * l'application compte 375 de ces composants.
 *
 * Les échelles `mb*` sont déclarées dans `assets/css/main.css` (bloc @theme),
 * avec les valeurs du design system Motoblouz.
 */
export default defineAppConfig({
  ui: {
    colors: {
      primary: 'mbyellow',
      secondary: 'mbblue',
      success: 'mbgreen',
      info: 'mbblue',
      warning: 'mborange',
      error: 'mbred',
      neutral: 'mbgrey',
    },
  },
})
