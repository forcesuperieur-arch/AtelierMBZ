<template>
  <div ref="rootEl" class="notif-bell-wrap">
    <button class="notif-bell-btn" type="button" aria-label="Ouvrir l'historique des notifications" @click="toggleOpen">
      <span class="notif-bell-icon">🔔</span>
      <span v-if="unreadCount > 0" class="notif-bell-badge">{{ unreadLabel }}</span>
    </button>

    <div v-if="open" class="notif-panel">
      <div class="notif-panel-header">
        <div>
          <div class="notif-panel-title">Historique notifications</div>
          <div class="notif-panel-subtitle">{{ unreadCount }} non lue(s)</div>
        </div>
        <button class="notif-clear-btn" type="button" :disabled="loading" @click="markEverythingRead">Tout lire</button>
      </div>

      <div v-if="loading" class="notif-empty">Chargement…</div>

      <div v-else-if="!displayedNotifications.length" class="notif-empty">
        Aucune notification récente.
      </div>

      <div v-else class="notif-list">
        <button
          v-for="notif in displayedNotifications"
          :key="notif.id"
          type="button"
          class="notif-item"
          :class="{ 'is-unread': !notif.isRead }"
          @click="openNotification(notif)"
        >
          <div class="notif-item-top">
            <strong class="notif-item-title">{{ notif.title }}</strong>
            <span class="notif-item-date">{{ formatDate(notif.createdAt) }}</span>
          </div>
          <div class="notif-item-message">{{ notif.message }}</div>
          <div class="notif-item-meta">
            <span class="notif-pill" :class="severityClass(notif.severity)">{{ severityLabel(notif.severity) }}</span>
            <span v-if="notif.actionUrl" class="notif-link-hint">Ouvrir</span>
          </div>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{
  atelierId?: number | null
}>()

const rootEl = ref<HTMLElement | null>(null)
const open = ref(false)
const loading = ref(false)
const router = useRouter()
const toast = useToast()
const { notifications, unreadCount, fetchNotifications, markRead, markAllRead, acknowledge } = useNotifications()

const displayedNotifications = computed(() => notifications.value.slice(0, 12))
const unreadLabel = computed(() => unreadCount.value > 99 ? '99+' : String(unreadCount.value))

function formatDate(value?: string) {
  if (!value) return '—'
  return new Date(value).toLocaleString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function severityLabel(value?: string) {
  if (value === 'critical') return 'Critique'
  if (value === 'warning') return 'Attention'
  return 'Info'
}

function severityClass(value?: string) {
  if (value === 'critical') return 'is-critical'
  if (value === 'warning') return 'is-warning'
  return 'is-info'
}

async function loadHistory() {
  loading.value = true
  try {
    await fetchNotifications('all', props.atelierId ?? null)
  } finally {
    loading.value = false
  }
}

async function toggleOpen() {
  open.value = !open.value
  if (open.value) {
    await loadHistory()
  }
}

async function openNotification(notif: any) {
  await Promise.allSettled([
    notif.isRead ? Promise.resolve() : markRead(notif.id),
    notif.acknowledgedAt ? Promise.resolve() : acknowledge(notif.id),
  ])

  open.value = false

  if (notif.actionUrl) {
    await router.push(notif.actionUrl)
    return
  }

  toast.add({ title: 'Notification ouverte', color: 'success' })
}

async function markEverythingRead() {
  const pendingIds = displayedNotifications.value.map((notif) => notif.id)

  await markAllRead()
  await Promise.allSettled(pendingIds.map((id) => acknowledge(id)))
  await loadHistory()
  toast.add({ title: 'Notifications mises à jour', color: 'success' })
}

function handleClickOutside(event: MouseEvent) {
  const target = event.target as Node | null
  if (open.value && rootEl.value && target && !rootEl.value.contains(target)) {
    open.value = false
  }
}

onMounted(() => {
  if (process.client) {
    document.addEventListener('click', handleClickOutside)
  }
})

onBeforeUnmount(() => {
  if (process.client) {
    document.removeEventListener('click', handleClickOutside)
  }
})

watch(() => props.atelierId, async () => {
  if (open.value) {
    await loadHistory()
  }
})
</script>

<style scoped>
.notif-bell-wrap {
  position: relative;
}

.notif-bell-btn {
  position: relative;
  width: 40px;
  height: 40px;
  border-radius: 10px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(255, 255, 255, 0.03);
  color: #E8E9ED;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.notif-bell-btn:hover {
  background: rgba(255, 210, 0, 0.08);
  border-color: rgba(255, 210, 0, 0.25);
}

.notif-bell-icon {
  font-size: 17px;
}

.notif-bell-badge {
  position: absolute;
  top: -5px;
  right: -5px;
  min-width: 18px;
  height: 18px;
  border-radius: 999px;
  background: #EF4444;
  color: white;
  font-size: 10px;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 5px;
}

.notif-panel {
  position: absolute;
  right: 0;
  top: calc(100% + 10px);
  width: min(380px, 86vw);
  max-height: 70vh;
  overflow: hidden;
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  background: #11141B;
  box-shadow: 0 16px 50px rgba(0, 0, 0, 0.45);
  z-index: 80;
}

.notif-panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 14px 10px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.notif-panel-title {
  font-size: 14px;
  font-weight: 700;
  color: #E8E9ED;
}

.notif-panel-subtitle {
  font-size: 11px;
  color: #9CA3AF;
}

.notif-clear-btn {
  border: none;
  background: transparent;
  color: #FCD34D;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}

.notif-clear-btn:disabled {
  opacity: 0.5;
  cursor: default;
}

.notif-list {
  display: flex;
  flex-direction: column;
  max-height: calc(70vh - 58px);
  overflow-y: auto;
}

.notif-empty {
  padding: 20px 14px;
  font-size: 13px;
  color: #9CA3AF;
}

.notif-item {
  display: flex;
  flex-direction: column;
  gap: 8px;
  width: 100%;
  border: none;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  background: transparent;
  text-align: left;
  padding: 12px 14px;
  cursor: pointer;
}

.notif-item:hover {
  background: rgba(255, 255, 255, 0.03);
}

.notif-item.is-unread {
  background: rgba(255, 210, 0, 0.05);
}

.notif-item-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
}

.notif-item-title {
  color: #F9FAFB;
  font-size: 13px;
}

.notif-item-date {
  color: #9CA3AF;
  font-size: 11px;
  white-space: nowrap;
}

.notif-item-message {
  color: #D1D5DB;
  font-size: 12px;
  line-height: 1.4;
}

.notif-item-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.notif-pill {
  display: inline-flex;
  align-items: center;
  padding: 3px 8px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 700;
}

.notif-pill.is-critical {
  background: rgba(239, 68, 68, 0.16);
  color: #FCA5A5;
}

.notif-pill.is-warning {
  background: rgba(245, 158, 11, 0.16);
  color: #FCD34D;
}

.notif-pill.is-info {
  background: rgba(59, 130, 246, 0.14);
  color: #93C5FD;
}

.notif-link-hint {
  font-size: 11px;
  color: #9CA3AF;
}
</style>
