<template>
  <div>
    <div class="flex items-center gap-3 mb-6">
      <UButton icon="i-heroicons-arrow-left" variant="ghost" to="/admin" />
      <h1 class="text-2xl font-bold">Utilisateurs</h1>
      <UButton label="Ajouter" icon="i-heroicons-plus" size="sm" @click="showNew = true" class="ml-auto" />
    </div>

    <UCard>
      <UTable :data="users" :columns="columns" :loading="loading">
        <template #role-cell="{ row }">
          <UBadge variant="subtle">{{ row.original.role }}</UBadge>
        </template>
        <template #is_active-cell="{ row }">
          <UBadge :color="row.original.is_active ? 'success' : 'error'" variant="subtle">
            {{ row.original.is_active ? 'Actif' : 'Inactif' }}
          </UBadge>
        </template>
        <template #actions-cell="{ row }">
          <UButton size="xs" variant="ghost" icon="i-heroicons-pencil" @click="editUser(row.original)" />
        </template>
      </UTable>
    </UCard>

    <UModal v-model:open="showNew">
      <template #default>
        <UCard>
          <template #header><h2 class="font-semibold">{{ editId ? 'Modifier' : 'Nouvel' }} utilisateur</h2></template>
          <form @submit.prevent="saveUser" class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
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
            <div class="flex justify-end gap-2">
              <UButton label="Annuler" variant="outline" @click="showNew = false" />
              <UButton type="submit" :label="editId ? 'Modifier' : 'Créer'" :loading="saving" />
            </div>
          </form>
        </UCard>
      </template>
    </UModal>
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

function editUser(u: any) {
  editId.value = u.id
  Object.assign(userForm, { prenom: u.prenom, nom: u.nom, email: u.email, role: u.role, password: '' })
  showNew.value = true
}

async function saveUser() {
  saving.value = true
  try {
    if (editId.value) {
      await api.put(`/users/${editId.value}`, userForm)
    } else {
      await api.post('/users', userForm)
    }
    showNew.value = false
    editId.value = null
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
    users.value = data?.['hydra:member'] ?? data?.member ?? (Array.isArray(data) ? data : [])
  } finally {
    loading.value = false
  }
}

onMounted(fetchUsers)
</script>
