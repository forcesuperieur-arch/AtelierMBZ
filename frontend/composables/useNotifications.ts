interface NotificationItem {
  id: number
  type: string
  severity: string
  title: string
  message: string
  actionUrl?: string
  relatedEntityType?: string
  relatedEntityId?: number
  targetRoles: string[]
  isRead: boolean
  readAt?: string
  acknowledgedAt?: string
  priority: string
  createdAt: string
  expiresAt?: string
}

const notifications = ref<NotificationItem[]>([])
const unreadCount = ref(0)
let eventSource: EventSource | null = null
let pollInterval: ReturnType<typeof setInterval> | null = null

export const useNotifications = () => {
  const { get, post } = useApi()
  const config = useRuntimeConfig()

  const fetchNotifications = async (status = 'unread') => {
    try {
      const data = await get<{ items: NotificationItem[] }>(`/notifications?status=${status}&limit=50`)
      notifications.value = data.items || []
    } catch (e) {
      console.warn('[Notifications] Failed to fetch', e)
    }
  }

  const fetchUnreadCount = async () => {
    try {
      const data = await get<{ count: number }>('/notifications/unread-count')
      unreadCount.value = data.count
    } catch (e) {
      console.warn('[Notifications] Failed to fetch count', e)
    }
  }

  const handleIncoming = (notif: NotificationItem) => {
    // Prepend to list, avoid duplicates
    const idx = notifications.value.findIndex(n => n.id === notif.id)
    if (idx === -1) {
      notifications.value.unshift(notif)
      unreadCount.value++
    }

    // Play sound for critical/warning
    if (notif.severity === 'critical' || notif.severity === 'warning') {
      playNotificationSound()
    }
  }

  const connect = (atelierId: number) => {
    disconnect()

    const mercureUrl = config.public.mercureUrl as string
    const topic = encodeURIComponent(`atelier/${atelierId}/notifications`)
    const url = `${mercureUrl}?topic=${topic}`

    try {
      eventSource = new EventSource(url, { withCredentials: true })

      eventSource.onmessage = (event) => {
        try {
          const notif = JSON.parse(event.data)
          handleIncoming(notif)
        } catch (e) {
          console.warn('[Mercure] Failed to parse notification', e)
        }
      }

      eventSource.onerror = () => {
        console.warn('[Mercure] Connection error, falling back to polling')
        disconnect()
        startPolling()
      }
    } catch {
      // Mercure not available — use polling fallback
      startPolling()
    }
  }

  const disconnect = () => {
    if (eventSource) {
      eventSource.close()
      eventSource = null
    }
    if (pollInterval) {
      clearInterval(pollInterval)
      pollInterval = null
    }
  }

  const startPolling = () => {
    if (pollInterval) return
    pollInterval = setInterval(() => {
      fetchUnreadCount()
    }, 15000) // Poll every 15s as fallback
  }

  const acknowledge = async (id: number) => {
    try {
      await post(`/notifications/${id}/acknowledge`)
      const notif = notifications.value.find(n => n.id === id)
      if (notif) {
        notif.acknowledgedAt = new Date().toISOString()
      }
    } catch (e: any) {
      if (e.status === 409) {
        // Already acknowledged — refresh
        await fetchNotifications()
      }
      throw e
    }
  }

  const markRead = async (id: number) => {
    try {
      await post(`/notifications/${id}/mark-read`)
      const notif = notifications.value.find(n => n.id === id)
      if (notif && !notif.isRead) {
        notif.isRead = true
        notif.readAt = new Date().toISOString()
        unreadCount.value = Math.max(0, unreadCount.value - 1)
      }
    } catch (e) {
      console.warn('[Notifications] Failed to mark read', e)
    }
  }

  const markAllRead = async () => {
    const unread = notifications.value.filter(n => !n.isRead)
    await Promise.allSettled(unread.map(n => markRead(n.id)))
  }

  const playNotificationSound = () => {
    try {
      const ctx = new AudioContext()
      const osc = ctx.createOscillator()
      const gain = ctx.createGain()
      osc.connect(gain)
      gain.connect(ctx.destination)
      osc.frequency.value = 800
      gain.gain.value = 0.1
      osc.start()
      osc.stop(ctx.currentTime + 0.15)
    } catch {
      // Audio not available
    }
  }

  return {
    notifications: readonly(notifications),
    unreadCount: readonly(unreadCount),
    fetchNotifications,
    fetchUnreadCount,
    connect,
    disconnect,
    acknowledge,
    markRead,
    markAllRead,
  }
}
