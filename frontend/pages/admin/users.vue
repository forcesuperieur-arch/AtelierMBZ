<template>
  <div>
    <div class="page-header">
      <div style="display:flex;align-items:center;gap:12px;">
        <NuxtLink to="/admin" style="color:#6B7280;text-decoration:none;font-size:18px;">◀</NuxtLink>
        <div class="page-title">Utilisateurs</div>
      </div>
      <button class="topbar-new-btn" @click="resetForm(); showNew = true">+ Ajouter</button>
    </div>

    <UCard>
      <UTable :data="users" :columns="columns" :loading="loading">
        <template #role-cell="{ row }">
          <span class="status-badge" style="background:rgba(139,92,246,0.12);color:#C4B5FD;">{{ row.original.role }}</span>
        </template>
        <template #is_active-cell="{ row }">
          <StatusBadge :status="row.original.is_active ? 'confirme' : 'annule'" />
        </template>
        <template #actions-cell="{ row }">
          <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button style="color:#FFD200;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="editUser(row.original)">✏ Modifier</button>
            <button style="color:#93C5FD;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="toggleUserStatus(row.original)">
              {{ row.original.is_active ? '⏸ Désactiver' : '▶ Activer' }}
            </button>
            <button style="color:#FCA5A5;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="deleteUser(row.original)">✖ Supprimer</button>
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
              <UFormField label="Rôle">
                <USelect v-model="userForm.role" :options="roleOptions" required />
              </UFormField>
              <UFormField v-if="!editId" label="Mot de passe">
                <UInput v-model="userForm.password" type="password" required />
              </UFormField>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;">
              <UButton label="Annuler" variant="outline" @click="showNew = false" />
              <UButton type="submit" :label="editId ? 'Modifier' : 'Créer'" :loading="saving" />
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
const saving = ref(false)
const editId = ref<number | null>(null)

const userForm = reactive({ prenom: '', nom: '', email: '', role: 'receptionnaire', password: '' })

const roleOptions = [
  { value: 'admin', label: 'Administrateur' },
  { value: 'receptionnaire', label: 'Réceptionnaire' },
  { value: 'mecanicien', label: 'Mécanicien' },
  { value: 'comptable', label: 'Comptable' },
]

const columns = [
  { key: 'nom', label: 'Nom' },
  { key: 'prenom', label: 'Prénom' },
  { key: 'email', label: 'Email' },
  { key: 'role', label: 'Rôle' },
  { key: 'is_active', label: 'Actif' },
  { key: 'actions', label: '' },
]

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

function normalizeUser(u: any) {
  const derived = splitUsername(u.username || '')
  return {
    ...u,
    prenom: u.prenom ?? derived.prenom,
    nom: u.nom ?? derived.nom,
    is_active: u.is_active ?? u.isActive ?? 0,
  }
}

function buildUsername(source: { prenom?: string; nom?: string; email?: string } = userForm) {
  const prenom = normalizeNamePart(source.prenom || 'user')
  const nom = normalizeNamePart(source.nom || 'atelier')
  return [prenom, nom].filter(Boolean).join('.') || ((source.email || '').split('@')[0] || 'utilisateur')
}

function getUserActive(user: any) {
  return Number(user?.is_active ?? user?.isActive ?? 0) === 1
}

function resetForm() {
  editId.value = null
  Object.assign(userForm, { prenom: '', nom: '', email: '', role: 'receptionnaire', password: '' })
}

function editUser(u: any) {
  const user = normalizeUser(u)
  editId.value = user.id
  Object.assign(userForm, { prenom: user.prenom, nom: user.nom, email: user.email, role: user.role, password: '' })
  showNew.value = true
}

async function toggleUserStatus(u: any) {
  const user = normalizeUser(u)
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
  if (!confirm(`Supprimer l'utilisateur ${user.prenom} ${user.nom} ?`)) return

  try {
    await api.del(`/users/${user.id}`)
    toast.add({ title: 'Utilisateur supprimé', color: 'success' })
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
      username: buildUsername(userForm),
      email: userForm.email,
      role: userForm.role,
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

watch(showNew, (open) => {
  if (!open) resetForm()
})

onMounted(fetchUsers)
</script>
