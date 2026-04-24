const PUBLIC_ROUTES = [
  '/login',
  '/public/booking',
  '/public/suivi',
  '/public/demande',
  '/public/mentions-legales',
  '/public/politique-confidentialite',
  '/companion/reception',
  '/companion/vo',
]

const AUTH_REFRESH_INTERVAL_MS = 5 * 60 * 1000
const AUTH_ACTIVITY_WINDOW_MS = 2 * 60 * 1000
const AUTH_HEARTBEAT_TICK_MS = 60 * 1000
const AUTH_ACTIVITY_THROTTLE_MS = 15 * 1000

function isPublicPath(path: string): boolean {
  return PUBLIC_ROUTES.some((route) => path.startsWith(route))
}

export default defineNuxtPlugin(() => {
  if (!process.client) {
    return
  }

  const auth = useAuth()
  const route = useRoute()
  const lastAuthRefreshAt = useState<number>('auth-last-refresh', () => 0)
  const lastActivityAt = useState<number>('auth-last-activity', () => Date.now())

  let heartbeatInterval: ReturnType<typeof setInterval> | null = null

  const markActivity = () => {
    const now = Date.now()
    if (now - lastActivityAt.value < AUTH_ACTIVITY_THROTTLE_MS) {
      return
    }

    lastActivityAt.value = now
  }

  const refreshSessionIfNeeded = async () => {
    if (!auth.isAuthenticated.value) {
      return
    }

    if (isPublicPath(route.path)) {
      return
    }

    if (document.hidden) {
      return
    }

    const now = Date.now()
    if (now - lastActivityAt.value > AUTH_ACTIVITY_WINDOW_MS) {
      return
    }

    if (now - lastAuthRefreshAt.value < AUTH_REFRESH_INTERVAL_MS) {
      return
    }

    const refreshed = await auth.silentRefresh()
    if (refreshed) {
      lastAuthRefreshAt.value = Date.now()
    }
  }

  const activityEvents: Array<keyof WindowEventMap> = ['pointerdown', 'keydown', 'scroll', 'focus', 'touchstart']
  activityEvents.forEach((eventName) => {
    window.addEventListener(eventName, markActivity, { passive: true })
  })

  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
      markActivity()
      void refreshSessionIfNeeded()
    }
  })

  heartbeatInterval = window.setInterval(() => {
    void refreshSessionIfNeeded()
  }, AUTH_HEARTBEAT_TICK_MS)

  window.addEventListener('beforeunload', () => {
    if (heartbeatInterval) {
      clearInterval(heartbeatInterval)
      heartbeatInterval = null
    }

    activityEvents.forEach((eventName) => {
      window.removeEventListener(eventName, markActivity)
    })
  }, { once: true })
})