<template>
  <div>
    <div class="page-header">
      <div style="display:flex;align-items:center;gap:12px;">
        <NuxtLink to="/admin" style="color:#6B7280;text-decoration:none;font-size:18px;">◀</NuxtLink>
        <div class="page-title">Utilisateurs</div>
        <span v-if="pendingCount" class="status-badge" style="background:rgba(251,191,36,0.16);color:#FCD34D;">
          {{ pendingCount }} en attente SSO
        </span>
      </div>
      <button class="topbar-new-btn" @click="resetForm(); showNew = true">+ Ajouter</button>
    </div>

    <div style="display:flex;gap:12px;margin-bottom:12px;">
      <UInput
        v-model="searchQuery"
        placeholder="Rechercher par nom, login, email..."
        icon="i-heroicons-magnifying-glass"
        style="flex:1;max-width:400px;"
      />
    </div>
    <UCard>
      <UTable :data="filteredUsers" :columns="columns" :loading="loading">
        <template #username-cell="{ row }">
          <span style="font-family:monospace;font-size:12px;color:#93C5FD;">{{ row.original.username }}</span>
        </template>
        <template #role-cell="{ row }">
          <span class="status-badge" style="background:rgba(139,92,246,0.12);color:#C4B5FD;">{{ roleLabel(row.original.role) }}</span>
        </template>
        <template #auth_provider-cell="{ row }">
          <span class="status-badge" :style="row.original.auth_provider === 'google' ? 'background:rgba(34,197,94,0.14);color:#86EFAC;' : 'background:rgba(59,130,246,0.14);color:#93C5FD;'">
            {{ row.original.auth_provider === 'google' ? 'Google' : 'Local' }}
          </span>
        </template>
        <template #access_status-cell="{ row }">
          <span class="status-badge" :style="statusBadgeStyle(row.original.access_status)">
            {{ accessStatusLabel(row.original.access_status) }}
          </span>
        </template>
        <template #is_active-cell="{ row }">
          <StatusBadge :status="row.original.is_active ? 'confirme' : 'annule'" />
        </template>
        <template #actions-cell="{ row }">
          <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button v-if="row.original.access_status === 'pending_validation'" style="color:#86EFAC;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="openApproveModal(row.original)">✅ Valider</button>
            <button v-if="row.original.access_status === 'pending_validation'" style="color:#FCA5A5;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="rejectPendingUser(row.original)">⛔ Refuser</button>
            <button style="color:#FFD200;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="editUser(row.original)">✏ Modifier</button>
            <button style="color:#93C5FD;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="toggleUserStatus(row.original)">
              {{ row.original.is_active ? '⏸ Désactiver' : '▶ Activer' }}
            </button>
            <button style="color:#FCA5A5;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="deleteUser(row.original)">🗄 Archiver RGPD</button>
            <button style="color:#EF4444;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="hardDeleteUser(row.original)">🗑 Supprimer</button>
          </div>
        </template>
      </UTable>
    </UCard>

    <AppModal v-model:open="showNew" size="lg">
      <template #default>
        <UCard>
          <template #header><span style="font-size:15px;font-weight:700;color:#E8E9ED;">{{ editId ? 'Modifier' : 'Nouvel' }} utilisateur</span></template>
          <form @submit.prevent="saveUser" style="display:flex;flex-direction:column;gap:12px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <UFormField label="Prénom"><UInput v-model="userForm.prenom" required /></UFormField>
              <UFormField label="Nom"><UInput v-model="userForm.nom" required /></UFormField>
              <UFormField label="Email"><UInput v-model="userForm.email" type="email" required /></UFormField>
              <UFormField label="Login / identifiant">
                <UInput v-model="userForm.username" :placeholder="buildUsername(userForm)" />
              </UFormField>
              <UFormField label="Profil d'accès">
                <select
                  v-model="userForm.role"
                  class="form-input"
                  style="background:#171B24;color:#E8E9ED;"
                  required
                >
                  <option v-for="role in roleOptions" :key="role.value" :value="role.value" style="background:#171B24;color:#E8E9ED;">
                    {{ role.label }}
                  </option>
                </select>
              </UFormField>
              <UFormField label="Téléphone">
                <UInput v-model="userForm.phoneNumber" placeholder="+33612345678" />
              </UFormField>
              <UFormField v-if="!editId" label="Mot de passe">
                <UInput v-model="userForm.password" type="password" required />
              </UFormField>
            </div>
            <div style="font-size:12px;color:#9CA3AF;">
              Le login sera utilisé pour la connexion si besoin. S'il existe déjà, un suffixe automatique sera ajouté.
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;">
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
          <template #header><span style="font-size:15px;font-weight:700;color:#E8E9ED;">Valider un compte SSO</span></template>
          <form @submit.prevent="approvePendingUser" style="display:flex;flex-direction:column;gap:12px;">
            <UFormField label="Atelier">
              <select v-model="approveForm.atelier_id" class="form-input" style="background:#171B24;color:#E8E9ED;" required>
                <option :value="null" disabled>Sélectionner un atelier</option>
                <option v-for="atelier in ateliers" :key="atelier.id" :value="atelier.id">{{ atelier.nom }}</option>
              </select>
            </UFormField>
            <div style="font-size:12px;color:#9CA3AF;">
              L’utilisateur sera activé après affectation de l’atelier et du rôle métier.
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;">
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

const approveForm = reactive({
  user_id: null as number | null,
  atelier_id: null as number | null,
})

const searchQuery = ref('')

const userForm = reactive({ prenom: '', nom: '', email: '', username: '', role: 'receptionnaire', phoneNumber: '', password: '' })

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
  { key: 'role', label: 'Rôle' },
  { key: 'phoneNumber', label: 'Téléphone' },
  { key: 'is_active', label: 'Actif' },
  { key: 'actions', label: '' },
]

const pendingCount = computed(() => users.value.filter((u: any) => u.access_status === 'pending_validation').length)

const filteredUsers = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return users.value
  return users.value.filter((u: any) => {
    const text = `${u.prenom || ''} ${u.nom || ''} ${u.username || ''} ${u.email || ''}`.toLowerCase()
    return text.includes(q)
  })
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

function statusBadgeStyle(status: string) {
  switch (status) {
    case 'pending_validation': return 'background:rgba(251,191,36,0.16);color:#FCD34D;'
    case 'disabled': return 'background:rgba(239,68,68,0.16);color:#FCA5A5;'
    case 'archived': return 'background:rgba(107,114,128,0.18);color:#D1D5DB;'
    default: return 'background:rgba(34,197,94,0.14);color:#86EFAC;'
  }
}

function getUserActive(user: any) {
  return Number(user?.is_active ?? user?.isActive ?? 0) === 1
}

function resetForm() {
  editId.value = null
  Object.assign(userForm, { prenom: '', nom: '', email: '', username: '', role: 'receptionnaire', phoneNumber: '', password: '' })
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
    phoneNumber: user.phoneNumber || '',
    password: '',
  })
  showNew.value = true
}

function openApproveModal(u: any) {
  const user = normalizeUser(u)
  approveForm.user_id = user.id
  approveForm.atelier_id = user.atelier_id || null
  showApprove.value = true
}

async function approvePendingUser() {
  if (!approveForm.user_id || !approveForm.atelier_id) {
    toast.add({ title: 'Erreur', description: 'Atelier requis', color: 'error' })
    return
  }

  approving.value = true
  try {
    await api.post(`/admin/users/${approveForm.user_id}/approve`, {
      atelier_id: Number(approveForm.atelier_id),
    })
    showApprove.value = false
    toast.add({ title: 'Compte SSO validé', color: 'success' })
    await fetchUsers()
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e?.message || 'Validation impossible', color: 'error' })
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
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e?.message || 'Refus impossible', color: 'error' })
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
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e?.message || 'Échec de mise à jour', color: 'error' })
  }
}

async function deleteUser(u: any) {
  const user = normalizeUser(u)
  if (!confirm(`Archiver le compte ${user.prenom} ${user.nom} et anonymiser ses données d’accès selon la règle RGPD ?`)) return

  try {
    await api.del(`/users/${user.id}`)
    toast.add({ title: 'Compte archivé', description: 'Les données d’accès ont été neutralisées.', color: 'success' })
    await fetchUsers()
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e?.message || 'Archivage impossible', color: 'error' })
  }
}


async function hardDeleteUser(u: any) {
  const user = normalizeUser(u)
  if (!confirm(`SUPPRIMER DÉFINITIVEMENT le compte ${user.prenom} ${user.nom} (${user.email}) ?\n\nCette action est irréversible.`)) return

  try {
    await api.del(`/admin/users/${user.id}/hard`)
    toast.add({ title: 'Compte supprimé', description: 'L\'utilisateur a été définitivement supprimé.', color: 'success' })
    await fetchUsers()
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e?.message || 'Suppression impossible', color: 'error' })
  }
}

async function saveUser() {
  saving.value = true
  try {
    const existing = editId.value ? users.value.find((u: any) => u.id === editId.value) : null
    const activeValue = existing ? (getUserActive(existing) ? 1 : 0) : 1
    const payload: any = {
      prenom: userForm.prenom.trim(),
      nom: userForm.nom.trim(),
      username: ensureUniqueUsername(buildUsername(userForm)),
      email: userForm.email.trim(),
      role: normalizeRoleValue(userForm.role),
      phoneNumber: userForm.phoneNumber.trim() || null,
      is_active: activeValue,
      isActive: activeValue,
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
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  } finally {
    saving.value = false
  }
}

async function fetchUsers() {
  loading.value = true
  try {
    const data = await api.get('/users')
    const raw = data?.['hydra:member'] ?? data?.member ?? (Array.isArray(data) ? data : [])
    users.value = raw.map((u: any) => normalizeUser(u))
  } finally {
    loading.value = false
  }
}

async function fetchAteliers() {
  try {
    const data = await api.get('/ateliers')
    const raw = data?.['hydra:member'] ?? data?.member ?? (Array.isArray(data) ? data : [])
    ateliers.value = raw
  } catch {
    ateliers.value = []
  }
}

async function fetchRoleOptions() {
  try {
    const data = await api.get('/roles')
    const raw = data?.['hydra:member'] ?? data?.member ?? (Array.isArray(data) ? data : [])
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
  await Promise.all([fetchUsers(), fetchRoleOptions(), fetchAteliers()])
})
</script>
