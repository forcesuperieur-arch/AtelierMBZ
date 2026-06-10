const PUBLIC_PATHS = ['/login', '/forgot-password', '/reset-password', '/clauses', '/cgv', '/mentions-legales', '/politique-confidentialite']

export default defineNuxtRouteMiddleware(async (to) => {
  const auth = useAuthStore()
  if (!auth.isAuthenticated && !PUBLIC_PATHS.includes(to.path)) {
    await auth.fetchMe()
    if (!auth.isAuthenticated) {
      return navigateTo('/login')
    }
  }
})
