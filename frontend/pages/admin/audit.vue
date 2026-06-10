<template>
  <div>
    <div class="page-header">
      <div style="display:flex;align-items:center;gap:12px;">
        <NuxtLink to="/admin" style="color:#6B7280;text-decoration:none;font-size:18px;">◀</NuxtLink>
        <div class="page-title">Journal d'audit</div>
      </div>
    </div>

    <UCard style="margin-bottom:16px;">
      <template #header>
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
          <span style="font-size:15px;font-weight:700;color:#E8E9ED;">Filtres</span>
          <UInput
            v-model="filters.search"
            placeholder="Rechercher un utilisateur, une entité, une action..."
            icon="i-heroicons-magnifying-glass"
            style="min-width:280px;"
          />
        </div>
      </template>

      <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
        <UFormField label="Date début">
          <input v-model="filters.dateFrom" type="date" class="form-input" />
        </UFormField>
        <UFormField label="Date fin">
          <input v-model="filters.dateTo" type="date" class="form-input" />
        </UFormField>
        <UFormField label="Type">
          <select v-model="filters.action" class="form-input">
            <option value="">Toutes</option>
            <option value="create">Création</option>
            <option value="update">Modification</option>
            <option value="delete">Suppression</option>
            <option value="transition">Transition</option>
            <option value="login">Connexion</option>
          </select>
        </UFormField>
        <button class="btn btn-primary" style="font-size:13px;" @click="page = 1; fetchLogs()">Filtrer</button>
      </div>
    </UCard>

    <UCard>
      <template #header>
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <span style="font-size:15px;font-weight:700;color:#E8E9ED;">Entrées d'audit</span>
          <span v-if="total > 0" style="font-size:12px;color:#9CA3AF;">{{ total }} résultat(s)</span>
        </div>
      </template>

      <div v-if="loading" style="padding:32px;text-align:center;color:#6B7280;">Chargement…</div>
      <div v-else-if="!logs.length" style="padding:32px;text-align:center;color:#9CA3AF;">Aucune entrée trouvée</div>
      <div v-else style="display:flex;flex-direction:column;gap:1px;">
        <div
          v-for="log in logs"
          :key="log.id"
          style="display:grid;grid-template-columns:140px 100px 1fr 150px;gap:12px;padding:10px 14px;border-bottom:1px solid rgba(255,255,255,0.04);font-size:13px;align-items:center;"
        >
          <span style="color:#6B7280;font-family:monospace;font-size:11px;">{{ formatDate(log.createdAt || log.created_at) }}</span>
          <span :style="actionStyle(log.action)">{{ actionLabel(log.action) }}</span>
          <span style="color:#E8E9ED;">
            <strong>{{ log.entityType || log.entity_type || '' }}</strong>
            <span v-if="log.entityId || log.entity_id" style="color:#6B7280;"> #{{ log.entityId || log.entity_id }}</span>
            <span v-if="log.description || log.details" style="color:#9CA3AF;margin-left:8px;">— {{ log.description || log.details }}</span>
          </span>
          <span style="color:#9CA3AF;">{{ log.userEmail || log.user_email || log.username || 'système' }}</span>
        </div>
      </div>
    </UCard>

    <div v-if="totalPages > 1" style="display:flex;justify-content:center;gap:6px;margin-top:16px;">
      <button
        v-for="p in totalPages"
        :key="p"
        class="btn"
        :class="page === p ? 'btn-primary' : 'btn-ghost'"
        style="min-width:36px;padding:6px 10px;font-size:12px;"
        @click="page = p; fetchLogs()"
      >{{ p }}</button>
    </div>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const loading = ref(true)
const logs = ref<any[]>([])
const page = ref(1)
const totalPages = ref(1)
const total = ref(0)

const filters = reactive({
  dateFrom: '',
  dateTo: '',
  action: '',
  search: '',
})

watch(() => filters.search, () => {
  page.value = 1
  fetchLogs()
})

function formatDate(d: string) {
  if (!d) return ''
  try { return new Date(d).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) } catch { return d }
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
    params.set('limit', '50')
    if (filters.dateFrom) params.set('dateFrom', filters.dateFrom)
    if (filters.dateTo) params.set('dateTo', filters.dateTo)
    if (filters.action) params.set('action', filters.action)
    if (filters.search.trim()) params.set('search', filters.search.trim())
    const data = await api.get(`/admin/audit-logs?${params}`)
    logs.value = data.items || []
    total.value = data.total || 0
    totalPages.value = data.totalPages || 1
  } catch (e: any) {
    logs.value = []
    total.value = 0
    totalPages.value = 1
  } finally {
    loading.value = false
  }
}

onMounted(fetchLogs)
</script>

<style scoped>
.form-input {
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.1);
  color: #E8E9ED;
  border-radius: 6px;
  padding: 8px 10px;
  font-size: 13px;
  width: 100%;
}
</style>
