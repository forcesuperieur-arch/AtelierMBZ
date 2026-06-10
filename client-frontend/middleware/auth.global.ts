export default defineNuxtRouteMiddleware(async (to) => {
  const auth = useAuthStore()

  // Réhydratation unique au boot / F5 : le cookie HttpOnly porte la session,
  // fetchMe la restaure (avec refresh silencieux si l'access token a expiré).
  if (!auth.hydrated && !auth.isAuthenticated) {
    await auth.fetchMe()
  }

  if (!auth.isAuthenticated && !CLIENT_PUBLIC_PATHS.includes(to.path)) {
    return navigateTo('/login')
  }

  if (auth.isAuthenticated && to.path === '/login') {
    return navigateTo('/')
  }
})
