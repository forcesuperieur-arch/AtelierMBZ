export default defineNuxtRouteMiddleware(async (to) => {
  const publicRoutes = ['/login', '/public/booking', '/public/suivi']
  if (publicRoutes.some(r => to.path.startsWith(r))) return

  const { isAuthenticated, fetchMe } = useAuth()

  if (!isAuthenticated.value) {
    const user = await fetchMe()
    if (!user) return navigateTo('/login')
  }
})
