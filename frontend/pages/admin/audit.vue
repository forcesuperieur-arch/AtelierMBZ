<template>
  <div>
    <div class="page-header">
      <div style="display:flex;align-items:center;gap:12px;">
        <div class="page-title">Journal d'audit</div>
      </div>
    </div>

    <UCard style="margin-bottom:16px;">
      <template #header>
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
          <span style="font-size:15px;font-weight:700;color:var(--content-1);">Filtres</span>
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
          <span style="font-size:15px;font-weight:700;color:var(--content-1);">Entrées d'audit</span>
          <span v-if="total > 0" style="font-size:12px;color:var(--content-3);">{{ total }} résultat(s)</span>
        </div>
      </template>

      <div v-if="loading" style="padding:32px;text-align:center;color:var(--content-3);">Chargement…</div>
      <div v-else-if="!logs.length" style="padding:32px;text-align:center;color:var(--content-3);">Aucune entrée trouvée</div>
      <div v-else style="display:flex;flex-direction:column;gap:1px;">
        <div
          v-for="log in logs"
          :key="log.id"
          style="display:grid;grid-template-columns:140px 100px 1fr 150px;gap:12px;padding:10px 14px;border-bottom:1px solid var(--border-2);font-size:13px;align-items:center;"
        >
          <span style="color:var(--content-3);font-family:monospace;font-size:11px;">{{ formatDate(log.createdAt || log.created_at) }}</span>
          <span :style="actionStyle(log.action)">{{ actionLabel(log.action) }}</span>
          <span style="color:var(--content-1);">
            <strong>{{ log.entityType || log.entity_type || '' }}</strong>
            <span v-if="log.entityId || log.entity_id" style="color:var(--content-3);"> #{{ log.entityId || log.entity_id }}</span>
            <span v-if="log.description || log.details" style="color:var(--content-3);margin-left:8px;">— {{ log.description || log.details }}</span>
          </span>
          <span style="color:var(--content-3);">{{ log.userEmail || log.user_email || log.username || 'système' }}</span>
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
    create: 'background:var(--success-soft);color:var(--success-content);',
    update: 'background:var(--info-soft);color:var(--info-content);',
    delete: 'background:var(--error-soft);color:var(--error-content);',
    transition: 'background:var(--warning-soft);color:var(--warning-content);',
    login: 'background:var(--info-soft);color:var(--info-content);',
  }
  return (colors[a] || 'background:var(--overlay-hover);color:var(--content-3);') + 'padding:3px 10px;border-radius:6px;font-size:11px;font-weight:600;text-align:center;'
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
  background: var(--overlay-hover);
  border: 1px solid var(--border-1);
  color: var(--content-1);
  border-radius: 6px;
  padding: 8px 10px;
  font-size: 13px;
  width: 100%;
}
</style>
