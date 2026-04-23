<template>
  <div>
    <div class="page-header">
      <div style="display:flex;align-items:center;gap:12px;">
        <NuxtLink to="/admin/roles-metier" style="color:#6B7280;text-decoration:none;font-size:18px;">◀</NuxtLink>
        <div class="page-title">{{ role?.libelle ?? 'Rôle métier' }}</div>
      </div>
      <div style="display:flex;gap:8px;">
        <UButton label="Enregistrer" :loading="saving" @click="saveRole" />
      </div>
    </div>

    <div v-if="loading" style="display:flex;justify-content:center;padding:48px;">
      <span style="color:#6B7280;">Chargement...</span>
    </div>

    <div v-else-if="!isSuperAdmin" style="padding:24px;color:#FCA5A5;font-weight:600;">
      Accès réservé au superadmin.
    </div>

    <div v-else-if="role" style="display:flex;flex-direction:column;gap:16px;">
      <!-- Basic info -->
      <UCard>
        <template #header><span style="font-weight:700;color:#E8E9ED;">Informations</span></template>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <UFormField label="Code">
            <UInput v-model="form.code" :disabled="role.isSystemTemplate" />
          </UFormField>
          <UFormField label="Libellé">
            <UInput v-model="form.libelle" />
          </UFormField>
          <UFormField label="Rôle de base">
            <USelect v-model="form.baseRole" :options="baseRoleOptions" />
          </UFormField>
          <UFormField label="Actif">
            <USelect v-model="form.isActive" :options="[{value:true,label:'Oui'},{value:false,label:'Non'}]" />
          </UFormField>
        </div>
        <UFormField label="Description" style="margin-top:12px;">
          <UTextarea v-model="form.description" :rows="2" />
        </UFormField>
      </UCard>

      <!-- Permission matrix -->
      <UCard>
        <template #header>
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <span style="font-weight:700;color:#E8E9ED;">Matrice de permissions</span>
            <div style="display:flex;gap:8px;">
              <button type="button" class="btn btn-ghost" @click="selectAll">Tout activer</button>
              <button type="button" class="btn btn-ghost" @click="deselectAll">Tout couper</button>
            </div>
          </div>
        </template>

        <div v-if="loadingModules" style="color:#6B7280;padding:12px;">Chargement des modules...</div>
        <div v-else>
          <table style="width:100%;border-collapse:collapse;">
            <thead>
              <tr style="border-bottom:1px solid #374151;">
                <th style="text-align:left;padding:8px 12px;color:#9CA3AF;font-size:12px;font-weight:600;">Module</th>
                <th v-for="action in allActions" :key="action" style="text-align:center;padding:8px 6px;color:#9CA3AF;font-size:12px;font-weight:600;">
                  {{ actionLabels[action] ?? action }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="mod in modules" :key="mod.code" style="border-bottom:1px solid #1F2937;">
                <td style="padding:8px 12px;color:#E8E9ED;font-weight:500;">
                  {{ mod.libelle }}
                  <div style="font-size:11px;color:#6B7280;">{{ mod.code }}</div>
                </td>
                <td v-for="action in allActions" :key="`${mod.code}-${action}`" style="text-align:center;padding:8px 6px;">
                  <input
                    v-if="mod.actions.includes(action)"
                    type="checkbox"
                    :checked="hasPermission(mod.code, action)"
                    @change="togglePermission(mod.code, action)"
                    style="accent-color:#FFD200;width:16px;height:16px;cursor:pointer;"
                  />
                  <span v-else style="color:#374151;">—</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </UCard>
    </div>
  </div>
</template>

<script setup lang="ts">
const route = useRoute()
const api = useApi()
const toast = useToast()
const { user } = useAuth()

const roleId = computed(() => route.params.id as string)

const loading = ref(true)
const loadingModules = ref(true)
const saving = ref(false)
const role = ref<any>(null)
const modules = ref<any[]>([])

const isSuperAdmin = computed(() => {
  const rolesList = user.value?.roles ?? []
  return user.value?.role === 'super_admin' || rolesList.includes('ROLE_SUPER_ADMIN')
})

const baseRoleOptions = [
  { value: 'ROLE_USER', label: 'Utilisateur (ROLE_USER)' },
  { value: 'ROLE_ADMIN', label: 'Administrateur (ROLE_ADMIN)' },
]

const allActions = ['view', 'create', 'edit', 'delete', 'export']
const actionLabels: Record<string, string> = {
  view: 'Voir',
  create: 'Créer',
  edit: 'Modifier',
  delete: 'Supprimer',
  export: 'Exporter',
}

const form = reactive({
  code: '',
  libelle: '',
  description: '',
  baseRole: 'ROLE_USER',
  isActive: true,
})

// Permission set: "module.action" strings
const permissionSet = ref(new Set<string>())

function hasPermission(module: string, action: string): boolean {
  return permissionSet.value.has(`${module}.${action}`)
}

function togglePermission(module: string, action: string) {
  const key = `${module}.${action}`
  if (permissionSet.value.has(key)) {
    permissionSet.value.delete(key)
  } else {
    permissionSet.value.add(key)
  }
}

function selectAll() {
  for (const mod of modules.value) {
    for (const action of mod.actions) {
      permissionSet.value.add(`${mod.code}.${action}`)
    }
  }
}

function deselectAll() {
  permissionSet.value.clear()
}

async function fetchRole() {
  loading.value = true
  try {
    const data = await api.get(`/roles-metier/${roleId.value}`)
    role.value = data
    form.code = data.code
    form.libelle = data.libelle
    form.description = data.description ?? ''
    form.baseRole = data.baseRole
    form.isActive = data.isActive

    permissionSet.value.clear()
    if (data.permissions) {
      for (const p of data.permissions) {
        if (p.granted !== false) {
          permissionSet.value.add(`${p.module}.${p.action}`)
        }
      }
    }
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
  } finally {
    loading.value = false
  }
}

async function fetchModules() {
  loadingModules.value = true
  try {
    const data = await api.get('/modules')
    modules.value = data['hydra:member'] ?? data
  } catch (e: unknown) {
    toast.add({ title: 'Erreur modules', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
  } finally {
    loadingModules.value = false
  }
}

async function saveRole() {
  saving.value = true
  try {
    // Build permissions array from permissionSet
    const permissions: any[] = []
    for (const key of permissionSet.value) {
      const [module, action] = key.split('.')
      permissions.push({ module, action, scope: 'atelier', granted: true })
    }

    await api.patch(`/roles-metier/${roleId.value}`, {
      ...form,
      permissions,
    })
    toast.add({ title: 'Rôle enregistré', color: 'success' })
    await fetchRole()
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  if (!isSuperAdmin.value) {
    navigateTo('/admin')
    return
  }
  fetchRole()
  fetchModules()
})
</script>
