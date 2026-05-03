<template>
  <div>
    <AppPageHeader title="Rôles métier" back-to="/admin">
      <template #actions>
        <button class="topbar-new-btn" @click="showCreate = true">+ Nouveau rôle</button>
      </template>
    </AppPageHeader>

    <div v-if="loading" class="loading-center">
      <span class="loading-text">Chargement...</span>
    </div>

    <UCard v-else-if="!isSuperAdmin">
      <div class="access-denied">Accès réservé au superadmin.</div>
    </UCard>

    <AppErrorState
      v-else-if="errorMessage && !roles.length"
      title="Rôles métier indisponibles"
      :description="errorMessage"
      @retry="fetchRoles"
    />

    <UCard v-else>
      <div v-if="!roles.length" class="empty-state">
        <div class="empty-state-icon">🎭</div>
        <div class="empty-state-title">Aucun rôle métier configuré</div>
        <div class="empty-state-sub">
          Créez votre premier rôle métier (chef d'atelier, comptable, etc.) pour affecter des permissions granulaires aux utilisateurs.
        </div>
        <button class="btn btn-primary empty-state-action btn-sm" @click="showCreate = true">+ Créer le premier rôle</button>
      </div>
      <UTable v-else :data="roles" :columns="columns">
        <template #libelle-cell="{ row }">
          <div>
            <div class="role-label">{{ row.original.libelle }}</div>
            <div class="role-key">{{ row.original.code }}</div>
          </div>
        </template>

        <template #baseRole-cell="{ row }">
          <AppStatusBadge :variant="row.original.baseRole === 'ROLE_ADMIN' ? 'warning' : 'info'" size="sm">
            {{ row.original.baseRole === 'ROLE_ADMIN' ? 'Admin' : 'User' }}
          </AppStatusBadge>
        </template>

        <template #permissions-cell="{ row }">
          <div class="chip-list">
            <span v-for="p in row.original.permissions?.slice(0, 5)" :key="p.id" class="access-chip permission-chip">
              {{ p.module }}.{{ p.action }}
            </span>
            <span v-if="(row.original.permissions?.length ?? 0) > 5" class="text-muted">
              +{{ row.original.permissions.length - 5 }}
            </span>
          </div>
        </template>

        <template #isSystemTemplate-cell="{ row }">
          <AppStatusBadge :variant="row.original.isSystemTemplate ? 'warning' : 'neutral'" size="sm">
            {{ row.original.isSystemTemplate ? 'Système' : 'Personnalisé' }}
          </AppStatusBadge>
        </template>

        <template #isActive-cell="{ row }">
          <StatusBadge :status="row.original.isActive ? 'confirme' : 'annule'" />
        </template>

        <template #actions-cell="{ row }">
          <AppInlineActions>
            <button class="btn-action-primary" @click="navigateTo(`/admin/roles-metier/${row.original.id}`)">✏ Modifier</button>
            <button
              v-if="!row.original.isSystemTemplate"
              class="btn-action-danger"
              @click="deleteRole(row.original)"
            >✖ Supprimer</button>
          </AppInlineActions>
        </template>
      </UTable>
    </UCard>

    <!-- Quick create modal -->
    <AppModal v-model:open="showCreate" size="lg">
      <template #default>
        <UCard>
          <template #header><span class="modal-title">Nouveau rôle métier</span></template>
          <form @submit.prevent="createRole" class="form-stack">
            <div class="form-grid-2">
              <UFormField label="Code"><UInput v-model="createForm.code" placeholder="ex: chef_atelier" required /></UFormField>
              <UFormField label="Libellé"><UInput v-model="createForm.libelle" placeholder="ex: Chef d'atelier" required /></UFormField>
              <UFormField label="Rôle de base">
                <USelect v-model="createForm.baseRole" :options="baseRoleOptions" required />
              </UFormField>
            </div>
            <UFormField label="Description">
              <UTextarea v-model="createForm.description" :rows="2" />
            </UFormField>
            <div class="form-footer">
              <UButton label="Annuler" variant="outline" @click="showCreate = false" />
              <UButton type="submit" label="Créer" :loading="saving" />
            </div>
          </form>
        </UCard>
      </template>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const toast = useToast()
const { user } = useAuth()

const loading = ref(true)
const saving = ref(false)
const showCreate = ref(false)
const roles = ref<any[]>([])
const errorMessage = ref('')

const isSuperAdmin = computed(() => {
  const rolesList = user.value?.roles ?? []
  return user.value?.role === 'super_admin' || rolesList.includes('ROLE_SUPER_ADMIN')
})

const baseRoleOptions = [
  { value: 'ROLE_USER', label: 'Utilisateur (ROLE_USER)' },
  { value: 'ROLE_ADMIN', label: 'Administrateur (ROLE_ADMIN)' },
]

const createForm = reactive({
  code: '',
  libelle: '',
  description: '',
  baseRole: 'ROLE_USER',
})

const columns = [
  { key: 'libelle', label: 'Rôle' },
  { key: 'baseRole', label: 'Rôle de base' },
  { key: 'permissions', label: 'Permissions' },
  { key: 'isSystemTemplate', label: 'Type' },
  { key: 'isActive', label: 'Actif' },
  { key: 'actions', label: '' },
]

async function fetchRoles() {
  loading.value = true
  errorMessage.value = ''
  try {
    const data = await api.get('/roles-metier')
    roles.value = unwrapHydraOrEmpty(data)
  } catch (e: unknown) {
    errorMessage.value = (e instanceof Error ? e.message : 'Erreur inconnue') || 'Impossible de charger les rôles métier.'
    toast.add({ title: 'Erreur', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
  } finally {
    loading.value = false
  }
}

async function createRole() {
  saving.value = true
  try {
    await api.post('/roles-metier', createForm)
    toast.add({ title: 'Rôle créé', color: 'success' })
    showCreate.value = false
    Object.assign(createForm, { code: '', libelle: '', description: '', baseRole: 'ROLE_USER' })
    await fetchRoles()
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
  } finally {
    saving.value = false
  }
}

async function deleteRole(role: any) {
  if (!confirm(`Supprimer le rôle "${role.libelle}" ?`)) return
  try {
    await api.del(`/roles-metier/${role.id}`)
    toast.add({ title: 'Rôle supprimé', color: 'success' })
    await fetchRoles()
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
  }
}

onMounted(() => {
  if (!isSuperAdmin.value) {
    navigateTo('/admin')
    return
  }
  fetchRoles()
})
</script>

<style scoped>
.loading-center { display:flex; justify-content:center; padding:48px; }
.loading-text { color:#6B7280; }
.access-denied { padding:8px 0; color:#FCA5A5; font-weight:600; }
.btn-sm { font-size:13px; }
.role-label { font-weight:700; color:#E8E9ED; }
.role-key { font-size:12px; color:#6B7280; }
.text-muted { font-size:11px; color:#6B7280; }
.btn-action-primary { color:#FFD200; font-size:12px; font-weight:600; background:none; border:none; cursor:pointer; }
.btn-action-danger { color:#FCA5A5; font-size:12px; font-weight:600; background:none; border:none; cursor:pointer; }
.modal-title { font-size:15px; font-weight:700; color:#E8E9ED; }
.form-stack { display:flex; flex-direction:column; gap:12px; }
.form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.form-footer { display:flex; justify-content:flex-end; gap:8px; }
.chip-list { display:flex; flex-wrap:wrap; gap:6px; }
.access-chip { display:inline-flex; align-items:center; padding:4px 8px; border-radius:999px; font-size:11px; font-weight:700; }
.permission-chip { color:#C4B5FD; background:rgba(139,92,246,0.12); }
</style>
