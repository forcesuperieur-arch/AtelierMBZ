<template>
  <div>
    <AppPageHeader title="Journal d'audit" back-to="/admin" />

    <!-- Filters -->
    <UCard style="margin-bottom:16px;">
      <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
        <div class="form-group">
          <label class="form-label">Date début</label>
          <input v-model="filters.dateFrom" type="date" class="form-input" />
        </div>
        <div class="form-group">
          <label class="form-label">Date fin</label>
          <input v-model="filters.dateTo" type="date" class="form-input" />
        </div>
        <div class="form-group">
          <label class="form-label">Type</label>
          <select v-model="filters.action" class="form-input">
            <option value="">Toutes</option>
            <option value="create">Création</option>
            <option value="update">Modification</option>
            <option value="delete">Suppression</option>
            <option value="transition">Transition</option>
            <option value="login">Connexion</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Recherche</label>
          <input v-model="filters.search" class="form-input" placeholder="Utilisateur, entité…" />
        </div>
        <button class="btn btn-primary" style="font-size:13px;" @click="fetchLogs">Filtrer</button>
      </div>
    </UCard>

    <UCard>
      <div v-if="loading" style="padding:32px;text-align:center;color:var(--text-subtle);">Chargement…</div>
      <div v-else-if="!logs.length" class="empty-state">
        <div class="empty-state-icon">📜</div>
        <div class="empty-state-title">Aucune entrée dans le journal</div>
        <div class="empty-state-sub">
          Aucune action sensible tracée sur la période sélectionnée. Affinez les filtres ou élargissez les dates.
        </div>
      </div>
      <div v-else style="display:flex;flex-direction:column;gap:1px;">
        <div v-for="log in filteredLogs" :key="log.id" style="display:grid;grid-template-columns:140px 100px 1fr 150px;gap:12px;padding:10px 14px;border-bottom:1px solid rgba(255,255,255,0.04);font-size:13px;align-items:center;">
          <span style="color:#6B7280;font-family:monospace;font-size:11px;">{{ formatDate(log.created_at || log.createdAt) }}</span>
          <span :style="actionStyle(log.action)">{{ actionLabel(log.action) }}</span>
          <span style="color:#E8E9ED;">
            <strong>{{ log.entity_type || log.entityType || '' }}</strong>
            <span v-if="log.entity_id || log.entityId" style="color:#6B7280;"> #{{ log.entity_id || log.entityId }}</span>
            <span v-if="log.description" style="color:#9CA3AF;margin-left:8px;">— {{ log.description }}</span>
          </span>
          <span style="color:#9CA3AF;">{{ log.user_email || log.userEmail || 'système' }}</span>
        </div>
      </div>
    </UCard>

    <!-- Pagination -->
    <div v-if="totalPages > 1" style="display:flex;justify-content:center;gap:6px;margin-top:16px;">
      <button v-for="p in totalPages" :key="p" class="btn" :class="page === p ? 'btn-primary' : 'btn-ghost'" style="min-width:36px;padding:6px 10px;font-size:12px;" @click="page = p; fetchLogs()">{{ p }}</button>
    </div>
  </div>
</template>

<script setup lang="ts">
const auth = useAuth()
const api = useApi()
const toast = useToast()
const loading = ref(true)
const logs = ref<any[]>([])
const page = ref(1)
const totalPages = ref(1)

const filters = reactive({
  dateFrom: '',
  dateTo: '',
  action: '',
  search: '',
})

const filteredLogs = computed(() => {
  if (!filters.search) return logs.value
  const s = filters.search.toLowerCase()
  return logs.value.filter(l =>
    (l.user_email || l.userEmail || '').toLowerCase().includes(s) ||
    (l.entity_type || l.entityType || '').toLowerCase().includes(s) ||
    (l.description || '').toLowerCase().includes(s)
  )
})

function formatDate(d: string) {
  if (!d) return ''
  try { return new Date(d).toLocaleString('fr-FR') } catch { return d }
}

function actionLabel(a: string) {
  const map: Record<string, string> = {
    create: 'Création', update: 'Modification', delete: 'Suppression',
    transition: 'Transition', login: 'Connexion', logout: 'Déconnexion',
  }
  return map[a] || a
}

function actionStyle(a: string) {
  const colors: Record<string, string> = {
    create: 'background:rgba(16,185,129,0.12);color:#6EE7B7;',
    update: 'background:rgba(59,130,246,0.12);color:#93C5FD;',
    delete: 'background:rgba(239,68,68,0.12);color:#FCA5A5;',
    transition: 'background:rgba(245,158,11,0.12);color:#FCD34D;',
    login: 'background:rgba(139,92,246,0.12);color:#C4B5FD;',
  }
  return (colors[a] || 'background:rgba(255,255,255,0.06);color:#9CA3AF;') + 'padding:3px 10px;border-radius:6px;font-size:11px;font-weight:600;text-align:center;'
}

async function fetchLogs() {
  loading.value = true
  try {
    const params = new URLSearchParams()
    params.set('page', String(page.value))
    params.set('itemsPerPage', '50')
    if (filters.dateFrom) params.set('createdAt[after]', filters.dateFrom)
    if (filters.dateTo) params.set('createdAt[before]', filters.dateTo)
    if (filters.action) params.set('action', filters.action)
    const data = await api.get(`/audit-logs?${params}`)
    const raw = data?.['hydra:member'] ?? data?.member ?? (Array.isArray(data) ? data : [])
    logs.value = raw
    const total = data?.['hydra:totalItems'] ?? data?.totalItems ?? raw.length
    totalPages.value = Math.max(1, Math.ceil(total / 50))
  } catch (e: any) {
    logs.value = []
    toast.add({ title: 'Erreur de chargement', description: e?.message || 'Impossible de récupérer le journal d\'audit.', color: 'error' })
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  if (!auth.canAccessAuditLogs()) {
    await navigateTo('/admin')
    return
  }

  await fetchLogs()
})
</script>
