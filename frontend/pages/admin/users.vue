<template>
  <div>
    <AppPageHeader title="Utilisateurs" back-to="/admin">
      <template #badge>
        <AppStatusBadge v-if="pendingCount" variant="warning" size="sm">
          {{ pendingCount }} en attente SSO
        </AppStatusBadge>
      </template>
      <template #actions>
        <button class="topbar-new-btn" @click="resetForm(); showNew = true">+ Ajouter</button>
      </template>
    </AppPageHeader>

    <UCard>
      <UTable :data="users" :columns="columns" :loading="loading">
        <template #username-cell="{ row }">
          <span class="font-mono-blue">{{ row.original.username }}</span>
        </template>
        <template #role-cell="{ row }">
          <AppStatusBadge variant="info" size="sm">{{ roleLabel(row.original.role) }}</AppStatusBadge>
        </template>
        <template #auth_provider-cell="{ row }">
          <AppStatusBadge :variant="row.original.auth_provider === 'google' ? 'success' : 'info'" size="sm">
            {{ row.original.auth_provider === 'google' ? 'Google' : 'Local' }}
          </AppStatusBadge>
        </template>
        <template #access_status-cell="{ row }">
          <AppStatusBadge :variant="accessStatusVariant(row.original.access_status)" size="sm">
            {{ accessStatusLabel(row.original.access_status) }}
          </AppStatusBadge>
        </template>
        <template #role_metier-cell="{ row }">
          <div class="role-cell">
            <span class="role-label">{{ row.original.role_metier?.libelle || 'Auto / non défini' }}</span>
            <span class="role-code">{{ row.original.role_metier?.code || 'hérité du profil d’accès' }}</span>
          </div>
        </template>
        <template #is_active-cell="{ row }">
          <StatusBadge :status="row.original.is_active ? 'confirme' : 'annule'" />
        </template>
        <template #actions-cell="{ row }">
          <AppInlineActions>
            <button v-if="row.original.access_status === 'pending_validation'" class="btn-action-success" @click="openApproveModal(row.original)">✅ Valider</button>
            <button v-if="row.original.access_status === 'pending_validation'" class="btn-action-danger" @click="rejectPendingUser(row.original)">⛔ Refuser</button>
            <button class="btn-action-primary" @click="editUser(row.original)">✏ Modifier</button>
            <button class="btn-action-info" @click="toggleUserStatus(row.original)">
              {{ row.original.is_active ? '⏸ Désactiver' : '▶ Activer' }}
            </button>
            <button class="btn-action-danger" @click="deleteUser(row.original)">🗄 Archiver RGPD</button>
          </AppInlineActions>
        </template>
      </UTable>
    </UCard>

    <AppModal v-model:open="showNew" size="lg">
      <template #default>
        <UCard>
          <template #header><span class="modal-title">{{ editId ? 'Modifier' : 'Nouvel' }} utilisateur</span></template>
          <form @submit.prevent="saveUser" class="form-stack">
            <div class="form-grid-2">
              <UFormField label="Prénom"><UInput v-model="userForm.prenom" required /></UFormField>
              <UFormField label="Nom"><UInput v-model="userForm.nom" required /></UFormField>
              <UFormField label="Email"><UInput v-model="userForm.email" type="email" required /></UFormField>
              <UFormField label="Login / identifiant">
                <UInput v-model="userForm.username" :placeholder="buildUsername(userForm)" />
              </UFormField>
              <UFormField label="Profil d'accès">
                <select v-model="userForm.role" class="form-input form-select-dark" required>
                  <option v-for="role in roleOptions" :key="role.value" :value="role.value" class="form-select-dark">
                    {{ role.label }}
                  </option>
                </select>
              </UFormField>
              <UFormField label="Rôle métier">
                <select v-model="userForm.role_metier_id" class="form-input form-select-dark">
                  <option :value="null">Automatique selon le profil</option>
                  <option v-for="roleMetier in assignableRoleMetiers" :key="roleMetier.id" :value="roleMetier.id">
                    {{ roleMetier.libelle }}
                  </option>
                </select>
              </UFormField>
              <UFormField v-if="!editId" label="Mot de passe">
                <UInput v-model="userForm.password" type="password" required />
              </UFormField>
            </div>
            <div class="form-helper">
              Le login sera utilisé pour la connexion si besoin. S'il existe déjà, un suffixe automatique sera ajouté.
            </div>
            <div class="form-footer">
              <UButton label="Annuler" variant="outline" @click="showNew = false" />
              <UButton type="submit" :label="editId ? 'Modifier' : 'Créer'" :loading="saving" />
            </div>
          </form>
        </UCard>
      </template>
    </AppModal>

    <AppModal v-model:open="showApprove" size="lg">
      <template #default>
        <UCard>
          <template #header><span class="modal-title">Valider un compte SSO</span></template>
          <form @submit.prevent="approvePendingUser" class="form-stack">
            <UFormField label="Atelier">
              <select v-model="approveForm.atelier_id" class="form-input form-select-dark" required>
                <option :value="null" disabled>Sélectionner un atelier</option>
                <option v-for="atelier in ateliers" :key="atelier.id" :value="atelier.id">{{ atelier.nom }}</option>
              </select>
            </UFormField>
            <UFormField label="Rôle métier final">
              <select v-model="approveForm.role_metier_id" class="form-input form-select-dark" required>
                <option :value="null" disabled>Sélectionner un rôle métier</option>
                <option v-for="role in filteredRoleMetiers" :key="role.id" :value="role.id">{{ role.libelle }}</option>
              </select>
            </UFormField>
            <div class="form-helper">
              L’utilisateur sera activé après affectation de l’atelier et du rôle métier.
            </div>
            <div class="form-footer">
              <UButton label="Annuler" variant="outline" @click="showApprove = false" />
              <UButton type="submit" label="Valider le compte" :loading="approving" />
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
const loading = ref(true)
const users = ref<any[]>([])
const showNew = ref(false)
const showApprove = ref(false)
const saving = ref(false)
const approving = ref(false)
const editId = ref<number | null>(null)
const ateliers = ref<any[]>([])
const roleMetiers = ref<any[]>([])

const approveForm = reactive({
  user_id: null as number | null,
  atelier_id: null as number | null,
  role_metier_id: null as number | null,
})

const userForm = reactive({ prenom: '', nom: '', email: '', username: '', role: 'receptionnaire', role_metier_id: null as number | null, password: '' })

const defaultRoleOptions = [
  { value: 'admin', label: 'Administrateur' },
  { value: 'receptionnaire', label: 'Réceptionnaire' },
  { value: 'mecanicien', label: 'Mécanicien' },
  { value: 'vo_manager', label: 'VO Manager' },
  { value: 'comptable', label: 'Comptable' },
]
const roleOptions = ref(defaultRoleOptions)

const columns = [
  { key: 'nom', label: 'Nom' },
  { key: 'prenom', label: 'Prénom' },
  { key: 'username', label: 'Login' },
  { key: 'email', label: 'Email' },
  { key: 'auth_provider', label: 'Auth' },
  { key: 'access_status', label: 'Statut' },
  { key: 'role_metier', label: 'Rôle métier' },
  { key: 'role', label: 'Rôle' },
  { key: 'is_active', label: 'Actif' },
  { key: 'actions', label: '' },
]

const pendingCount = computed(() => users.value.filter((u: any) => u.access_status === 'pending_validation').length)

const filteredRoleMetiers = computed(() => {
  return roleMetiers.value.filter((role: any) => role.is_active !== false && (!role.atelier_id || Number(role.atelier_id) === Number(approveForm.atelier_id)))
})

const assignableRoleMetiers = computed(() => {
  return roleMetiers.value.filter((role: any) => role.is_active !== false)
})

function normalizeNamePart(value: string) {
  return (value || '')
    .normalize('NFD')
    .replace(/\p{Diacritic}/gu, '')
    .replace(/[^a-zA-Z0-9]+/g, '.')
    .replace(/(^\.|\.$)/g, '')
    .toLowerCase()
}

function splitUsername(username: string) {
  const cleaned = (username || '').replace(/[._-]+/g, ' ').trim()
  const [prenom = '', ...rest] = cleaned.split(/\s+/)
  return {
    prenom: prenom ? prenom.charAt(0).toUpperCase() + prenom.slice(1) : '',
    nom: rest.join(' ').toUpperCase(),
  }
}

function normalizeRoleValue(role: any) {
  if (role && typeof role === 'object') {
    if (role.value) return String(role.value).trim().toLowerCase()
    const matched = roleOptions.value.find((item: any) => item.label === role.label)
    return String(matched?.value || 'receptionnaire').trim().toLowerCase()
  }

  return String(role || 'receptionnaire').trim().toLowerCase()
}

function normalizeUser(u: any) {
  const derived = splitUsername(u.username || '')
  return {
    ...u,
    prenom: u.prenom ?? derived.prenom,
    nom: u.nom ?? derived.nom,
    username: u.username || '',
    role: normalizeRoleValue(u.role),
    is_active: u.is_active ?? u.isActive ?? 0,
    access_status: u.access_status || (Number(u.is_active ?? u.isActive ?? 0) === 1 ? 'active' : 'disabled'),
    auth_provider: u.auth_provider || 'local',
    role_metier: u.role_metier || null,
  }
}

function buildUsername(source: { prenom?: string; nom?: string; email?: string; username?: string } = userForm) {
  if ((source.username || '').trim()) return normalizeNamePart(source.username || '')
  const prenom = normalizeNamePart(source.prenom || 'user')
  const nom = normalizeNamePart(source.nom || 'atelier')
  return [prenom, nom].filter(Boolean).join('.') || ((source.email || '').split('@')[0] || 'utilisateur')
}

function ensureUniqueUsername(base: string) {
  const normalizedBase = normalizeNamePart(base || 'utilisateur') || 'utilisateur'
  const taken = new Set(users.value.filter((u: any) => u.id !== editId.value).map((u: any) => String(u.username || '').toLowerCase()))
  if (!taken.has(normalizedBase)) return normalizedBase
  let i = 2
  while (taken.has(`${normalizedBase}.${i}`)) i += 1
  return `${normalizedBase}.${i}`
}

function roleLabel(role: any) {
  const normalizedRole = normalizeRoleValue(role)
  return roleOptions.value.find((r: any) => r.value === normalizedRole)?.label || normalizedRole
}

function accessStatusLabel(status: string) {
  switch (status) {
    case 'pending_validation': return 'En attente'
    case 'disabled': return 'Désactivé'
    case 'archived': return 'Archivé RGPD'
    default: return 'Actif'
  }
}

function accessStatusVariant(status: string): 'warning' | 'error' | 'neutral' | 'success' {
  switch (status) {
    case 'pending_validation': return 'warning'
    case 'disabled': return 'error'
    case 'archived': return 'neutral'
    default: return 'success'
  }
}

function getUserActive(user: any) {
  return Number(user?.is_active ?? user?.isActive ?? 0) === 1
}

function mapRoleMetierCodeToLegacyRole(code?: string) {
  switch (code) {
    case 'responsable_atelier':
    case 'responsable_magasin':
      return 'admin'
    case 'receptionniste':
      return 'receptionnaire'
    case 'mecanicien':
      return 'mecanicien'
    case 'comptable':
      return 'comptable'
    case 'vo_manager':
      return 'vo_manager'
    case 'service_client':
      return 'service_client'
    default:
      return normalizeRoleValue(userForm.role)
  }
}

function resetForm() {
  editId.value = null
  Object.assign(userForm, { prenom: '', nom: '', email: '', username: '', role: 'receptionnaire', role_metier_id: null, password: '' })
}

function editUser(u: any) {
  const user = normalizeUser(u)
  editId.value = user.id
  Object.assign(userForm, {
    prenom: user.prenom,
    nom: user.nom,
    email: user.email,
    username: user.username,
    role: normalizeRoleValue(user.role),
    role_metier_id: user.role_metier?.id ?? null,
    password: '',
  })
  showNew.value = true
}

function openApproveModal(u: any) {
  const user = normalizeUser(u)
  approveForm.user_id = user.id
  approveForm.atelier_id = user.atelier_id || null
  approveForm.role_metier_id = user.role_metier?.id || filteredRoleMetiers.value.find((role: any) => role.code === 'service_client')?.id || null
  showApprove.value = true
}

async function approvePendingUser() {
  if (!approveForm.user_id || !approveForm.atelier_id || !approveForm.role_metier_id) {
    toast.add({ title: 'Erreur', description: 'Atelier et rôle métier requis', color: 'error' })
    return
  }

  approving.value = true
  try {
    await api.post(`/admin/users/${approveForm.user_id}/approve`, {
      atelier_id: Number(approveForm.atelier_id),
      role_metier_id: Number(approveForm.role_metier_id),
    })
    showApprove.value = false
    toast.add({ title: 'Compte SSO validé', color: 'success' })
    await fetchUsers()
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: (e instanceof Error ? e.message : 'Erreur inconnue') || 'Validation impossible', color: 'error' })
  } finally {
    approving.value = false
  }
}

async function rejectPendingUser(u: any) {
  const user = normalizeUser(u)
  const reason = prompt(`Refuser le compte ${user.email} ?`, 'Compte refusé par un administrateur')
  if (reason === null) return

  try {
    await api.post(`/admin/users/${user.id}/reject`, { reason })
    toast.add({ title: 'Compte refusé', color: 'success' })
    await fetchUsers()
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: (e instanceof Error ? e.message : 'Erreur inconnue') || 'Refus impossible', color: 'error' })
  }
}

async function toggleUserStatus(u: any) {
  const user = normalizeUser(u)
  if (user.access_status === 'archived') {
    toast.add({ title: 'Compte archivé', description: 'Un compte archivé RGPD ne peut pas être réactivé.', color: 'warning' })
    return
  }

  const nextActive = getUserActive(user) ? 0 : 1

  try {
    await api.patch(`/users/${user.id}`, {
      username: user.username || buildUsername(user),
      email: user.email,
      role: user.role,
      is_active: nextActive,
      isActive: nextActive,
    })
    toast.add({ title: nextActive ? 'Utilisateur activé' : 'Utilisateur désactivé', color: 'success' })
    await fetchUsers()
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: (e instanceof Error ? e.message : 'Erreur inconnue') || 'Échec de mise à jour', color: 'error' })
  }
}

async function deleteUser(u: any) {
  const user = normalizeUser(u)
  if (!confirm(`Archiver le compte ${user.prenom} ${user.nom} et anonymiser ses données d’accès selon la règle RGPD ?`)) return

  try {
    await api.del(`/users/${user.id}`)
    toast.add({ title: 'Compte archivé', description: 'Les données d’accès ont été neutralisées.', color: 'success' })
    await fetchUsers()
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: (e instanceof Error ? e.message : 'Erreur inconnue') || 'Archivage impossible', color: 'error' })
  }
}

async function saveUser() {
  saving.value = true
  try {
    const existing = editId.value ? users.value.find((u: any) => u.id === editId.value) : null
    const activeValue = existing ? (getUserActive(existing) ? 1 : 0) : 1
    const selectedRoleMetier = assignableRoleMetiers.value.find((role: any) => Number(role.id) === Number(userForm.role_metier_id)) || null
    const payload: any = {
      prenom: userForm.prenom.trim(),
      nom: userForm.nom.trim(),
      username: ensureUniqueUsername(buildUsername(userForm)),
      email: userForm.email.trim(),
      role: selectedRoleMetier ? mapRoleMetierCodeToLegacyRole(selectedRoleMetier.code) : normalizeRoleValue(userForm.role),
      is_active: activeValue,
      isActive: activeValue,
      roleMetier: selectedRoleMetier ? `/api/roles-metier/${selectedRoleMetier.id}` : null,
    }

    if (userForm.password.trim()) {
      payload.plain_password = userForm.password.trim()
      payload.plainPassword = userForm.password.trim()
    }

    if (editId.value) {
      await api.patch(`/users/${editId.value}`, payload)
    } else {
      if (!payload.plain_password) throw new Error('Le mot de passe est requis')
      await api.post('/users', payload)
    }

    showNew.value = false
    resetForm()
    await fetchUsers()
    toast.add({ title: 'Utilisateur sauvegardé', color: 'success' })
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
  } finally {
    saving.value = false
  }
}

async function fetchUsers() {
  loading.value = true
  try {
    const data = await api.get('/users')
    users.value = unwrapHydraOrEmpty(data).map((u: any) => normalizeUser(u))
  } finally {
    loading.value = false
  }
}

async function fetchAteliers() {
  try {
    const data = await api.get('/ateliers')
    ateliers.value = unwrapHydraOrEmpty(data)
  } catch {
    ateliers.value = []
  }
}

async function fetchRoleMetiers() {
  try {
    const data = await api.get('/roles-metier')
    roleMetiers.value = unwrapHydraOrEmpty(data)
  } catch {
    roleMetiers.value = []
  }
}

async function fetchRoleOptions() {
  try {
    const data = await api.get('/roles')
    const raw = unwrapHydraOrEmpty(data)
    const unique = new Map<string, { value: string; label: string }>()

    for (const option of defaultRoleOptions) {
      unique.set(option.value, option)
    }

    for (const role of raw) {
      const value = normalizeRoleValue(role.role || role.value)
      if (!value) continue

      const fallbackLabel = defaultRoleOptions.find((item) => item.value === value)?.label || value
      unique.set(value, {
        value,
        label: String(role.label || fallbackLabel),
      })
    }

    roleOptions.value = Array.from(unique.values())
  } catch {
    roleOptions.value = defaultRoleOptions
  }
}

watch(showNew, (open) => {
  if (!open) resetForm()
})

onMounted(async () => {
  await Promise.all([fetchUsers(), fetchRoleOptions(), fetchAteliers(), fetchRoleMetiers()])
})
</script>

<style scoped>
.font-mono-blue { font-family:monospace; font-size:12px; color:#93C5FD; }
.role-cell { display:flex; flex-direction:column; gap:4px; }
.role-label { color:#E8E9ED; font-size:12px; font-weight:600; }
.role-code { color:#9CA3AF; font-size:11px; }
.modal-title { font-size:15px; font-weight:700; color:#E8E9ED; }
.form-stack { display:flex; flex-direction:column; gap:12px; }
.form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.form-select-dark { background:#171B24; color:#E8E9ED; }
.form-helper { font-size:12px; color:#9CA3AF; }
.form-footer { display:flex; justify-content:flex-end; gap:8px; }
.btn-action-success { color:#86EFAC; font-size:12px; font-weight:600; background:none; border:none; cursor:pointer; }
.btn-action-danger { color:#FCA5A5; font-size:12px; font-weight:600; background:none; border:none; cursor:pointer; }
.btn-action-primary { color:#FFD200; font-size:12px; font-weight:600; background:none; border:none; cursor:pointer; }
.btn-action-info { color:#93C5FD; font-size:12px; font-weight:600; background:none; border:none; cursor:pointer; }
</style>
