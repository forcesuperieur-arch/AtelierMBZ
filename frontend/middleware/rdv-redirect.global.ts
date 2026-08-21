/**
 * Redirige ce qui reste des anciennes routes de rendez-vous.
 *
 * La liste `/rdv` a été supprimée par la refonte 2026 (8a) : le planning
 * montre les mêmes RDV avec leur pont et leur durée, et la file du jour est
 * dans Réception — une troisième vue du même objet coûtait trois fois la
 * maintenance. Il reste donc deux renvois à tenir, pour qu'aucun lien ancien
 * ni signet ne tombe dans le vide (règle 7).
 */
export default defineNuxtRouteMiddleware((to) => {
  // `/rdv/<nombre>` : la fiche de détail est un panneau du planning (36a).
  if (to.path.match(/^\/rdv\/\d+$/)) {
    return navigateTo('/planning', { redirectCode: 301 })
  }

  // `/rdv` nu : l'entrée « Prise de RDV » est désormais l'écran de saisie (6a).
  if (to.path === '/rdv' || to.path === '/rdv/') {
    return navigateTo('/rdv/new', { redirectCode: 301 })
  }
})
