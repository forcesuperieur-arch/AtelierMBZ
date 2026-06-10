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

    <DemandeTravauxSuppDetailModal />

    <!-- Notification detail modal -->
    <div v-if="detailOpen" class="notif-detail-overlay" @click.self="closeDetail">
      <div class="notif-detail-card">
        <div class="notif-detail-header">
          <div class="notif-detail-icon" :class="severityClass(selectedNotif?.severity)">
            {{ severityIcon(selectedNotif?.severity) }}
          </div>
          <div class="notif-detail-title-wrap">
            <h3 class="notif-detail-title">{{ selectedNotif?.title }}</h3>
            <p class="notif-detail-date">{{ selectedNotif?.createdAt ? formatDate(selectedNotif.createdAt) : '' }}</p>
          </div>
          <button class="notif-detail-close" type="button" @click="closeDetail">✕</button>
        </div>

        <div class="notif-detail-body">
          <p class="notif-detail-message">{{ selectedNotif?.message }}</p>

          <!-- Dynamic detail for DemandeTravauxSupp -->
          <div v-if="selectedNotif?.relatedEntityType === 'DemandeTravauxSupp'" class="notif-detail-section">
            <div v-if="detailLoading" class="notif-detail-loading">Chargement des détails…</div>
            <template v-else-if="detailData">
              <div v-if="detailData.description" class="notif-detail-block">
                <span class="notif-detail-label">Commentaire mécanicien :</span>
                <p class="notif-detail-block-text">« {{ detailData.description }} »</p>
              </div>
              <div v-if="detailData.prestations?.length" class="notif-detail-block">
                <span class="notif-detail-label">Prestations demandées :</span>
                <ul class="notif-detail-list">
                  <li v-for="(p, i) in detailData.prestations" :key="i">
                    {{ p.designation }} — <strong>{{ formatEuro(p.prix_ttc) }}</strong> <span class="notif-detail-muted">({{ p.temps_minutes }} min)</span>
                  </li>
                </ul>
              </div>
              <div class="notif-detail-summary">
                <div class="notif-detail-summary-item">
                  <span class="notif-detail-label">Total TTC :</span>
                  <span class="notif-detail-value-highlight">{{ formatEuro(detailData.prix_estime) }}</span>
                </div>
                <div class="notif-detail-summary-item">
                  <span class="notif-detail-label">Temps estimé :</span>
                  <span class="notif-detail-value">{{ formatMinutes(detailData.temps_estime) }}</span>
                </div>
                <div v-if="detailData.urgence === 'urgent'" class="notif-detail-summary-item">
                  <span class="notif-detail-badge-urgent">URGENT</span>
                </div>
              </div>
            </template>
          </div>

          <div v-if="selectedNotif?.relatedEntityType" class="notif-detail-meta">
            <span class="notif-detail-label">Entité liée :</span>
            <span class="notif-detail-value">{{ selectedNotif.relatedEntityType }} #{{ selectedNotif.relatedEntityId }}</span>
          </div>
          <div v-if="selectedNotif?.type" class="notif-detail-meta">
            <span class="notif-detail-label">Type :</span>
            <span class="notif-detail-value">{{ selectedNotif.type }}</span>
          </div>
        </div>

        <div class="notif-detail-footer">
          <button class="notif-detail-btn-secondary" type="button" @click="closeDetail">Fermer</button>

          <!-- Contextual action for DemandeTravauxSupp pending validation -->
          <template v-if="selectedNotif?.relatedEntityType === 'DemandeTravauxSupp' && detailData && ['en_attente','en_attente_validation'].includes(detailData.statut)">
            <template v-if="detailCanalOpen">
              <button
                class="notif-detail-btn-primary"
                type="button"
                :disabled="detailActionLoading"
                @click="envoyerDemande('email')"
              >
                {{ detailActionLoading ? 'Envoi…' : '📧 Email' }}
              </button>
              <button
                class="notif-detail-btn-primary"
                type="button"
                :disabled="detailActionLoading"
                @click="envoyerDemande('sms')"
              >
                {{ detailActionLoading ? 'Envoi…' : '📱 SMS' }}
              </button>
              <button
                class="notif-detail-btn-secondary"
                type="button"
                :disabled="detailActionLoading"
                @click="detailCanalOpen = false"
              >
                Annuler
              </button>
            </template>
            <button
              v-else
              class="notif-detail-btn-primary"
              type="button"
              @click="detailCanalOpen = true"
            >
              📤 Envoyer au client
            </button>
          </template>

          <button v-if="selectedNotif?.actionUrl || selectedNotif?.relatedEntityType === 'DemandeTravauxSupp'" class="notif-detail-btn-primary" type="button" @click="navigateFromDetail">
            Voir le détail
          </button>
        </div>
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
const detailOpen = ref(false)
const selectedNotif = ref<any>(null)
const detailLoading = ref(false)
const detailData = ref<any>(null)
const detailCanalOpen = ref(false)
const detailActionLoading = ref(false)
const router = useRouter()
const toast = useToast()
const api = useApi()
const { notifications, unreadCount, fetchNotifications, markRead, markAllRead, acknowledge } = useNotifications()
const { open: openDemandeModal } = useDemandeTravauxSuppDetailModal()

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
    notif.acknowledgedAt ? Promise.resolve() : acknowledge(notif.id).catch(() => {}),
  ])

  selectedNotif.value = notif
  detailOpen.value = true
  detailData.value = null

  // Load dynamic detail for related entities
  if (notif.relatedEntityType === 'DemandeTravauxSupp' && notif.relatedEntityId) {
    detailLoading.value = true
    try {
      const data = await api.get(`/demandes-travaux-supp/${notif.relatedEntityId}`)
      detailData.value = data
    } catch (e) {
      console.warn('[Notification] Failed to load detail', e)
    } finally {
      detailLoading.value = false
    }
  }
}

function closeDetail() {
  detailOpen.value = false
  selectedNotif.value = null
  detailData.value = null
}

function navigateFromDetail() {
  const url = selectedNotif.value?.actionUrl
  const isDemande = selectedNotif.value?.relatedEntityType === 'DemandeTravauxSupp'
  const demandeData = detailData.value

  if (isDemande && demandeData) {
    closeDetail()
    openDemandeModal(demandeData)
    return
  }

  closeDetail()
  open.value = false
  if (url) {
    router.push(url)
  }
}

function formatEuro(value?: string | number) {
  if (value === undefined || value === null) return '—'
  const n = typeof value === 'string' ? parseFloat(value) : value
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(n)
}

function formatMinutes(value?: number) {
  if (!value) return '—'
  const h = Math.floor(value / 60)
  const m = value % 60
  if (h > 0 && m > 0) return `${h}h ${m}min`
  if (h > 0) return `${h}h`
  return `${m}min`
}

async function envoyerDemande(canal: 'email' | 'sms') {
  const id = selectedNotif.value?.relatedEntityId
  if (!id) return
  detailActionLoading.value = true
  try {
    const res = await api.post(`/demandes-travaux-supp/${id}/envoyer`, { canal })
    if (res.envoye) {
      toast.add({ title: `Envoyé par ${canal === 'email' ? 'e-mail' : 'SMS'}`, description: `Destinataire : ${res.destinataire}`, color: 'success' })
    } else if (res.error) {
      toast.add({ title: 'Erreur d\'envoi', description: res.error, color: 'error' })
    } else {
      toast.add({ title: 'Lien prêt', description: `Lien : ${res.lien_client}`, color: 'warning' })
    }
    detailCanalOpen.value = false
    if (detailData.value) {
      detailData.value.statut = 'en_attente_decision_client'
    }
    // Refresh notification list
    await loadHistory()
  } catch (e: any) {
    toast.add({ title: e.data?.error || 'Erreur lors de l\'envoi', color: 'error' })
  } finally {
    detailActionLoading.value = false
  }
}

function severityIcon(value?: string) {
  if (value === 'critical') return '🔴'
  if (value === 'warning') return '🟠'
  return 'ℹ️'
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

/* Detail modal */
.notif-detail-overlay {
  position: fixed;
  inset: 0;
  z-index: 100;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
}

.notif-detail-card {
  width: min(420px, 92vw);
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  background: #11141B;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.5);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.notif-detail-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.notif-detail-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  flex-shrink: 0;
}

.notif-detail-icon.is-critical {
  background: rgba(239, 68, 68, 0.16);
}

.notif-detail-icon.is-warning {
  background: rgba(245, 158, 11, 0.16);
}

.notif-detail-icon.is-info {
  background: rgba(59, 130, 246, 0.16);
}

.notif-detail-title-wrap {
  flex: 1;
  min-width: 0;
}

.notif-detail-title {
  font-size: 15px;
  font-weight: 700;
  color: #E8E9ED;
  margin: 0;
  line-height: 1.3;
}

.notif-detail-date {
  font-size: 12px;
  color: #6B7280;
  margin: 2px 0 0;
}

.notif-detail-close {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: none;
  background: rgba(255, 255, 255, 0.05);
  color: #9CA3AF;
  font-size: 16px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.notif-detail-close:hover {
  background: rgba(255, 255, 255, 0.1);
  color: #E8E9ED;
}

.notif-detail-body {
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.notif-detail-message {
  font-size: 13px;
  color: #D1D5DB;
  line-height: 1.5;
  margin: 0;
}

.notif-detail-meta {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
}

.notif-detail-label {
  color: #6B7280;
}

.notif-detail-value {
  color: #9CA3AF;
  font-weight: 600;
}

.notif-detail-section {
  border-top: 1px solid rgba(255, 255, 255, 0.06);
  padding-top: 12px;
  margin-top: 4px;
}

.notif-detail-loading {
  font-size: 12px;
  color: #6B7280;
  font-style: italic;
}

.notif-detail-block {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-bottom: 10px;
}

.notif-detail-block-text {
  font-size: 12px;
  color: #D1D5DB;
  line-height: 1.4;
  margin: 0;
  font-style: italic;
}

.notif-detail-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.notif-detail-list li {
  font-size: 12px;
  color: #D1D5DB;
  padding: 4px 8px;
  background: rgba(255, 255, 255, 0.03);
  border-radius: 6px;
}

.notif-detail-muted {
  color: #6B7280;
  font-size: 11px;
}

.notif-detail-summary {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  padding: 10px 12px;
  background: rgba(255, 255, 255, 0.03);
  border-radius: 8px;
  margin-top: 4px;
}

.notif-detail-summary-item {
  display: flex;
  align-items: center;
  gap: 6px;
}

.notif-detail-value-highlight {
  color: #FFD200;
  font-weight: 700;
  font-size: 14px;
}

.notif-detail-badge-urgent {
  font-size: 10px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 999px;
  background: rgba(239, 68, 68, 0.16);
  color: #FCA5A5;
}

.notif-detail-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 12px 16px 16px;
}

.notif-detail-btn-secondary {
  padding: 8px 14px;
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  background: transparent;
  color: #9CA3AF;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.notif-detail-btn-secondary:hover {
  background: rgba(255, 255, 255, 0.05);
  color: #E8E9ED;
}

.notif-detail-btn-primary {
  padding: 8px 14px;
  border-radius: 8px;
  border: none;
  background: rgba(255, 210, 0, 0.85);
  color: #0B0E14;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}

.notif-detail-btn-primary:hover {
  background: #FFD200;
}
</style>
