/**
 * Redirects legacy /rdv/:id routes to the RDV list.
 * The detail page was removed in favor of modals in planning.vue and rdv/index.vue.
 */
export default defineNuxtRouteMiddleware((to) => {
  // Match /rdv/<number> but not /rdv/new or other non-numeric paths
  if (to.path.match(/^\/rdv\/\d+$/)) {
    return navigateTo('/rdv', { redirectCode: 301 })
  }
})
