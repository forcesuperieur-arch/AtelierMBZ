<template>
  <div>
    <div class="page-header">
      <div style="display:flex;align-items:center;gap:12px;">
        <NuxtLink to="/admin" style="color:#6B7280;text-decoration:none;font-size:18px;">◀</NuxtLink>
        <div class="page-title">Gestion des rôles</div>
      </div>
      <button v-if="isSuperAdmin" class="topbar-new-btn" @click="resetForm(); showModal = true">+ Nouveau rôle</button>
    </div>

    <div v-if="loading" style="display:flex;justify-content:center;padding:48px;">
      <span style="color:#6B7280;">Chargement...</span>
    </div>

    <UCard v-else-if="!isSuperAdmin">
      <div style="padding:8px 0;color:#FCA5A5;font-weight:600;">Accès réservé au superadmin.</div>
    </UCard>

    <UCard v-else>
      <UTable :data="roles" :columns="columns">
        <template #label-cell="{ row }">
          <div>
            <div style="font-weight:700;color:#E8E9ED;">{{ row.original.label }}</div>
            <div style="font-size:12px;color:#6B7280;">{{ row.original.role }}</div>
          </div>
        </template>

        <template #sections-cell="{ row }">
          <div class="chip-list">
            <span v-for="section in getDisplayItems(row.original.sections_json, availableSections)" :key="`${row.original.role}-section-${section}`" class="access-chip section-chip">
              {{ section }}
            </span>
          </div>
        </template>

        <template #permissions-cell="{ row }">
          <div class="chip-list">
            <span v-for="perm in getDisplayItems(row.original.permissions_json, availablePermissions)" :key="`${row.original.role}-perm-${perm}`" class="access-chip permission-chip">
              {{ perm }}
            </span>
          </div>
        </template>

        <template #is_system-cell="{ row }">
          <span
            :style="{
              display: 'inline-block',
              padding: '4px 10px',
              borderRadius: '999px',
              fontSize: '11px',
              fontWeight: '700',
              color: row.original.is_system ? '#FCD34D' : '#9CA3AF',
              background: row.original.is_system ? 'rgba(252,211,77,0.12)' : 'rgba(156,163,175,0.12)'
            }"
          >
            {{ row.original.is_system ? 'Système' : 'Personnalisé' }}
          </span>
        </template>

        <template #actions-cell="{ row }">
          <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button style="color:#FFD200;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="editRole(row.original)">✏ Modifier</button>
            <button
              v-if="!row.original.is_system && row.original.role !== 'super_admin'"
              style="color:#FCA5A5;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;"
              @click="deleteRole(row.original)"
            >
              ✖ Supprimer
            </button>
          </div>
        </template>
      </UTable>
    </UCard>

    <AppModal v-model:open="showModal" size="xl">
      <template #content>
        <UCard>
          <template #header>
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
              <div>
                <div style="font-weight:700;">{{ editKey ? 'Modifier' : 'Nouveau' }} rôle</div>
                <div style="font-size:12px;color:#9CA3AF;">Active ou désactive simplement les accès avec les toggles ci-dessous.</div>
              </div>
              <button @click="showModal = false" style="background:none;border:none;color:#9CA3AF;font-size:18px;cursor:pointer;">✕</button>
            </div>
          </template>

          <form @submit.prevent="saveRole" style="display:flex;flex-direction:column;gap:16px;">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
              <div class="form-group">
                <label class="form-label">Clé du rôle</label>
                <input v-model="roleForm.role" class="form-input" :disabled="!!editKey" placeholder="Ex: chef_atelier" required />
              </div>
              <div class="form-group">
                <label class="form-label">Libellé</label>
                <input v-model="roleForm.label" class="form-input" placeholder="Ex: Chef d'atelier" required />
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Description</label>
              <textarea v-model="roleForm.description" class="form-input" rows="2" placeholder="Description du rôle"></textarea>
            </div>

            <div class="toggle-section">
              <div class="toggle-section-header">
                <div>
                  <div class="toggle-section-title">Sections visibles</div>
                  <div class="toggle-section-subtitle">Affichage des grandes zones de l'application.</div>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                  <button type="button" class="btn btn-ghost" @click="roleForm.sections = ['*']">Tout activer</button>
                  <button type="button" class="btn btn-ghost" @click="roleForm.sections = []">Tout couper</button>
                </div>
              </div>

              <div class="toggle-grid">
                <label v-for="section in availableSections" :key="section.key" class="toggle-item">
                  <input v-model="roleForm.sections" type="checkbox" :value="section.key" />
                  <div>
                    <div class="toggle-item-label">{{ section.label }}</div>
                    <div v-if="section.hint" class="toggle-item-hint">{{ section.hint }}</div>
                  </div>
                </label>
              </div>
            </div>

            <div class="toggle-section">
              <div class="toggle-section-header">
                <div>
                  <div class="toggle-section-title">Permissions métier</div>
                  <div class="toggle-section-subtitle">Actions autorisées pour ce rôle.</div>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                  <button type="button" class="btn btn-ghost" @click="roleForm.permissions = ['*']">Tout activer</button>
                  <button type="button" class="btn btn-ghost" @click="roleForm.permissions = []">Tout couper</button>
                </div>
              </div>

              <div class="toggle-grid">
                <label v-for="permission in availablePermissions" :key="permission.key" class="toggle-item">
                  <input v-model="roleForm.permissions" type="checkbox" :value="permission.key" />
                  <div>
                    <div class="toggle-item-label">{{ permission.label }}</div>
                    <div v-if="permission.hint" class="toggle-item-hint">{{ permission.hint }}</div>
                  </div>
                </label>
              </div>
            </div>

            <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#9CA3AF;">
              <input v-model="roleForm.is_system" type="checkbox" />
              Rôle système
            </label>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:4px;">
              <button type="button" class="btn btn-ghost" @click="showModal = false">Annuler</button>
              <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? 'Enregistrement…' : editKey ? 'Modifier' : 'Créer' }}</button>
            </div>
          </form>
        </UCard>
      </template>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
type ToggleOption = {
  key: string
  label: string
  hint?: string
}

const api = useApi()
const toast = useToast()
const { user, fetchMe } = useAuth()

const availableSections: ToggleOption[] = [
  { key: '*', label: 'Toutes les sections', hint: 'Accès complet' },
  { key: 'dashboard', label: 'Stat' },
  { key: 'rdv', label: 'Rendez-vous' },
  { key: 'planning', label: 'Planning' },
  { key: 'workshop', label: 'Atelier / ponts' },
  { key: 'suivi', label: 'Suivi live' },
  { key: 'clients', label: 'Clients' },
  { key: 'or', label: 'Dossiers atelier' },
  { key: 'motos', label: 'Catalogue motos' },
  { key: 'devis', label: 'Devis' },
  { key: 'facturation', label: 'Facturation' },
  { key: 'stock', label: 'Stock' },
  { key: 'mecanicien', label: 'Espace mécanicien' },
  { key: 'absences', label: 'Absences' },
  { key: 'admin', label: 'Administration' },
  { key: 'tarifs', label: 'Tarifs' },
]

const availablePermissions: ToggleOption[] = [
  { key: '*', label: 'Toutes les permissions', hint: 'Accès complet' },
  { key: 'rdv.create', label: 'Créer un RDV' },
  { key: 'rdv.edit', label: 'Modifier un RDV' },
  { key: 'rdv.delete', label: 'Supprimer un RDV' },
  { key: 'client.create', label: 'Créer un client' },
  { key: 'client.edit', label: 'Modifier un client' },
  { key: 'client.delete', label: 'Supprimer un client' },
  { key: 'or.edit', label: 'Modifier un dossier atelier' },
  { key: 'facturation.create', label: 'Créer une facture' },
  { key: 'facturation.edit', label: 'Modifier une facture' },
  { key: 'stock.create', label: 'Créer un article stock' },
  { key: 'stock.edit', label: 'Modifier le stock' },
  { key: 'stock.delete', label: 'Supprimer du stock' },
  { key: 'admin.users', label: 'Gérer les utilisateurs' },
  { key: 'admin.config', label: 'Gérer la configuration' },
  { key: 'admin.roles', label: 'Gérer les rôles' },
]

const loading = ref(true)
const saving = ref(false)
const showModal = ref(false)
const editKey = ref<string | null>(null)
const roles = ref<any[]>([])

const roleForm = reactive({
  role: '',
  label: '',
  description: '',
  sections: [] as string[],
  permissions: [] as string[],
  is_system: false,
})

const isSuperAdmin = computed(() => {
  const rolesList = user.value?.roles ?? []
  return user.value?.role === 'super_admin' || rolesList.includes('ROLE_SUPER_ADMIN')
})

const columns = [
  { key: 'label', label: 'Rôle' },
  { key: 'description', label: 'Description' },
  { key: 'sections', label: 'Sections' },
  { key: 'permissions', label: 'Permissions' },
  { key: 'is_system', label: 'Type' },
  { key: 'actions', label: '' },
]

function parseList(value: any): string[] {
  if (Array.isArray(value)) return value.map(String).map(v => v.trim()).filter(Boolean)
  if (typeof value !== 'string') return []

  const trimmed = value.trim()
  if (!trimmed) return []

  try {
    const parsed = JSON.parse(trimmed)
    if (Array.isArray(parsed)) return parsed.map(String).map(v => v.trim()).filter(Boolean)
  } catch {}

  return trimmed
    .split(/[\n,;]+/)
    .map(v => v.trim())
    .filter(Boolean)
}

function cleanSelections(value: any): string[] {
  const items = Array.from(new Set(parseList(value)))
  return items.includes('*') ? ['*'] : items
}

function normalizeRole(r: any) {
  return {
    ...r,
    sections_json: cleanSelections(r.sections_json ?? r.sectionsJson),
    permissions_json: cleanSelections(r.permissions_json ?? r.permissionsJson),
    is_system: Number(r.is_system ?? r.isSystem ?? 0) === 1,
  }
}

function resetForm() {
  editKey.value = null
  Object.assign(roleForm, {
    role: '',
    label: '',
    description: '',
    sections: [],
    permissions: [],
    is_system: false,
  })
}

function editRole(role: any) {
  const current = normalizeRole(role)
  editKey.value = current.role
  Object.assign(roleForm, {
    role: current.role,
    label: current.label ?? '',
    description: current.description ?? '',
    sections: [...current.sections_json],
    permissions: [...current.permissions_json],
    is_system: current.is_system,
  })
  showModal.value = true
}

function getDisplayItems(values: string[], source: ToggleOption[]): string[] {
  if (values.includes('*')) return ['Tous']

  const labels = values.map((value) => source.find(item => item.key === value)?.label || value)
  return labels.length ? labels : ['Aucun']
}

function buildPayload() {
  return {
    role: roleForm.role.trim(),
    label: roleForm.label.trim(),
    description: roleForm.description.trim() || null,
    sections_json: JSON.stringify(cleanSelections(roleForm.sections)),
    permissions_json: JSON.stringify(cleanSelections(roleForm.permissions)),
    is_system: roleForm.is_system ? 1 : 0,
  }
}

async function fetchRoles() {
  const data = await api.get('/roles')
  const raw = data?.['hydra:member'] ?? data?.member ?? (Array.isArray(data) ? data : [])
  roles.value = raw
    .map((r: any) => normalizeRole(r))
    .sort((a: any, b: any) => String(a.label ?? '').localeCompare(String(b.label ?? '')))
}

async function saveRole() {
  saving.value = true
  try {
    const payload = buildPayload()
    if (!payload.role) throw new Error('La clé du rôle est requise')
    if (!payload.label) throw new Error('Le libellé est requis')

    if (editKey.value) {
      await api.patch(`/roles/${encodeURIComponent(editKey.value)}`, payload)
      toast.add({ title: 'Rôle modifié', color: 'success' })
    } else {
      await api.post('/roles', payload)
      toast.add({ title: 'Rôle créé', color: 'success' })
    }

    showModal.value = false
    resetForm()
    await fetchRoles()
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e?.message || 'Échec de sauvegarde', color: 'error' })
  } finally {
    saving.value = false
  }
}

async function deleteRole(role: any) {
  if (!confirm(`Supprimer le rôle ${role.label} ?`)) return

  try {
    await api.del(`/roles/${encodeURIComponent(role.role)}`)
    toast.add({ title: 'Rôle supprimé', color: 'success' })
    await fetchRoles()
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e?.message || 'Suppression impossible', color: 'error' })
  }
}

watch(showModal, (open) => {
  if (!open) resetForm()
})

onMounted(async () => {
  try {
    if (!user.value) await fetchMe()
    if (isSuperAdmin.value) {
      await fetchRoles()
    } else {
      toast.add({ title: 'Accès refusé', description: 'Page réservée au superadmin', color: 'warning' })
      navigateTo('/admin')
    }
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.chip-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.access-chip {
  display: inline-flex;
  align-items: center;
  padding: 4px 8px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
}

.section-chip {
  color: #93C5FD;
  background: rgba(59, 130, 246, 0.12);
}

.permission-chip {
  color: #C4B5FD;
  background: rgba(139, 92, 246, 0.12);
}

.toggle-section {
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 12px;
  padding: 12px;
  background: rgba(17, 24, 39, 0.45);
}

.toggle-section-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  margin-bottom: 12px;
  flex-wrap: wrap;
}

.toggle-section-title {
  font-weight: 700;
  color: #E8E9ED;
}

.toggle-section-subtitle {
  font-size: 12px;
  color: #9CA3AF;
}

.toggle-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 8px;
}

.toggle-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid rgba(255,255,255,0.08);
  background: rgba(255,255,255,0.02);
  cursor: pointer;
}

.toggle-item input {
  margin-top: 2px;
}

.toggle-item-label {
  color: #E8E9ED;
  font-size: 13px;
  font-weight: 600;
}

.toggle-item-hint {
  color: #9CA3AF;
  font-size: 11px;
}
</style>
