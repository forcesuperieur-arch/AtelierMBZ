<template>
  <div>
    <AppPageHeader title="Rôles métier" back-to="/admin">
      <template #actions>
        <button class="topbar-new-btn" @click="showCreate = true">+ Nouveau rôle</button>
      </template>
    </AppPageHeader>

    <div v-if="loading" style="display:flex;justify-content:center;padding:48px;">
      <span style="color:#6B7280;">Chargement...</span>
    </div>

    <UCard v-else-if="!isSuperAdmin">
      <div style="padding:8px 0;color:#FCA5A5;font-weight:600;">Accès réservé au superadmin.</div>
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
        <button class="btn btn-primary empty-state-action" style="font-size:13px;" @click="showCreate = true">+ Créer le premier rôle</button>
      </div>
      <UTable v-else :data="roles" :columns="columns">
        <template #libelle-cell="{ row }">
          <div>
            <div style="font-weight:700;color:#E8E9ED;">{{ row.original.libelle }}</div>
            <div style="font-size:12px;color:#6B7280;">{{ row.original.code }}</div>
          </div>
        </template>

        <template #baseRole-cell="{ row }">
          <span class="status-badge" :style="row.original.baseRole === 'ROLE_ADMIN' ? 'background:rgba(252,211,77,0.12);color:#FCD34D;' : 'background:rgba(147,197,253,0.12);color:#93C5FD;'">
            {{ row.original.baseRole === 'ROLE_ADMIN' ? 'Admin' : 'User' }}
          </span>
        </template>

        <template #permissions-cell="{ row }">
          <div class="chip-list">
            <span v-for="p in row.original.permissions?.slice(0, 5)" :key="p.id" class="access-chip permission-chip">
              {{ p.module }}.{{ p.action }}
            </span>
            <span v-if="(row.original.permissions?.length ?? 0) > 5" style="font-size:11px;color:#6B7280;">
              +{{ row.original.permissions.length - 5 }}
            </span>
          </div>
        </template>

        <template #isSystemTemplate-cell="{ row }">
          <span
            :style="{
              display: 'inline-block', padding: '4px 10px', borderRadius: '999px',
              fontSize: '11px', fontWeight: '700',
              color: row.original.isSystemTemplate ? '#FCD34D' : '#9CA3AF',
              background: row.original.isSystemTemplate ? 'rgba(252,211,77,0.12)' : 'rgba(156,163,175,0.12)'
            }"
          >
            {{ row.original.isSystemTemplate ? 'Système' : 'Personnalisé' }}
          </span>
        </template>

        <template #isActive-cell="{ row }">
          <StatusBadge :status="row.original.isActive ? 'confirme' : 'annule'" />
        </template>

        <template #actions-cell="{ row }">
          <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button style="color:#FFD200;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="navigateTo(`/admin/roles-metier/${row.original.id}`)">✏ Modifier</button>
            <button
              v-if="!row.original.isSystemTemplate"
              style="color:#FCA5A5;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;"
              @click="deleteRole(row.original)"
            >✖ Supprimer</button>
          </div>
        </template>
      </UTable>
    </UCard>

    <!-- Quick create modal -->
    <AppModal v-model:open="showCreate" size="lg">
      <template #default>
        <UCard>
          <template #header><span style="font-size:15px;font-weight:700;color:#E8E9ED;">Nouveau rôle métier</span></template>
          <form @submit.prevent="createRole" style="display:flex;flex-direction:column;gap:12px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <UFormField label="Code"><UInput v-model="createForm.code" placeholder="ex: chef_atelier" required /></UFormField>
              <UFormField label="Libellé"><UInput v-model="createForm.libelle" placeholder="ex: Chef d'atelier" required /></UFormField>
              <UFormField label="Rôle de base">
                <USelect v-model="createForm.baseRole" :options="baseRoleOptions" required />
              </UFormField>
            </div>
            <UFormField label="Description">
              <UTextarea v-model="createForm.description" :rows="2" />
            </UFormField>
            <div style="display:flex;justify-content:flex-end;gap:8px;">
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
    roles.value = data['hydra:member'] ?? data
  } catch (e: any) {
    errorMessage.value = e?.message || 'Impossible de charger les rôles métier.'
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
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
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
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
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
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
