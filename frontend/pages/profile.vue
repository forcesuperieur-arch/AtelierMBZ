<template>
  <div>
    <AppPageHeader title="Mon profil" subtitle="Compte et contexte de connexion." />

    <AppEmptyState
      v-if="!user"
      icon="i-heroicons-user"
      title="Profil indisponible"
      description="Reconnectez-vous pour rafraîchir votre session."
    />

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <UCard>
        <template #header>
          <h3 class="text-sm font-bold text-text-primary">👤 Identité</h3>
        </template>
        <div class="profile-grid">
          <div><span class="lbl">Prénom</span><span class="val">{{ user.prenom || '—' }}</span></div>
          <div><span class="lbl">Nom</span><span class="val">{{ user.nom || '—' }}</span></div>
          <div><span class="lbl">Email</span><span class="val">{{ user.email }}</span></div>
          <div><span class="lbl">Identifiant</span><span class="val">{{ user.username }}</span></div>
          <div>
            <span class="lbl">Méthode de connexion</span>
            <span class="val">
              <AppStatusBadge :variant="user.auth_provider === 'google' ? 'success' : 'info'">
                {{ user.auth_provider === 'google' ? 'Google SSO' : (user.auth_provider || 'local') }}
              </AppStatusBadge>
            </span>
          </div>
        </div>
      </UCard>

      <UCard>
        <template #header>
          <h3 class="text-sm font-bold text-text-primary">🎭 Rôle &amp; permissions</h3>
        </template>
        <div class="profile-grid">
          <div><span class="lbl">Rôle métier</span><span class="val">{{ roleMetierLabel }}</span></div>
          <div><span class="lbl">Rôle de base</span><span class="val">{{ baseRoleLabel }}</span></div>
          <div v-if="extraRoles.length">
            <span class="lbl">Rôles techniques</span>
            <span class="val flex flex-wrap gap-1">
              <AppStatusBadge v-for="r in extraRoles" :key="r" variant="default" size="sm">{{ r }}</AppStatusBadge>
            </span>
          </div>
          <div v-if="user.role_metier?.permissions?.length">
            <span class="lbl">Permissions accordées</span>
            <span class="val text-xs text-gray-500">{{ user.role_metier.permissions.length }} entrée(s)</span>
          </div>
        </div>
      </UCard>

      <UCard>
        <template #header>
          <h3 class="text-sm font-bold text-text-primary">🏢 Atelier actif</h3>
        </template>
        <div v-if="!user.atelier_id && !isSuperAdmin" class="alert alert--warning m-0">
          <div class="alert-title">⚠️ Aucun atelier affecté</div>
          <div class="alert-body">Demandez à un administrateur de vous rattacher à un atelier pour accéder aux écrans opérationnels.</div>
        </div>
        <div v-else class="profile-grid">
          <div><span class="lbl">Nom</span><span class="val">{{ user.atelier_nom || (isSuperAdmin ? 'Tous (vue super-admin)' : '—') }}</span></div>
          <div v-if="user.atelier_id">
            <span class="lbl">ID atelier</span>
            <span class="val font-mono text-xs text-gray-500">#{{ user.atelier_id }}</span>
          </div>
          <div v-if="canSwitchAtelier">
            <span class="lbl">Changer d'atelier</span>
            <select :value="user.atelier_id ?? ''" class="form-input max-w-[280px]" @change="onSwitch(($event.target as HTMLSelectElement).value)">
              <option v-for="a in ateliersList" :key="a.id" :value="a.id">{{ a.nom }}</option>
            </select>
          </div>
        </div>
      </UCard>

      <UCard>
        <template #header>
          <h3 class="text-sm font-bold text-text-primary">⚙ Session</h3>
        </template>
        <div class="profile-grid">
          <div>
            <span class="lbl">Statut du compte</span>
            <span class="val">
              <AppStatusBadge :variant="user.access_status === 'active' ? 'success' : 'warning'">
                {{ user.access_status === 'active' ? 'Actif' : (user.access_status || 'inconnu') }}
              </AppStatusBadge>
            </span>
          </div>
        </div>
        <div class="mt-4 flex gap-2 flex-wrap">
          <UButton variant="ghost" icon="i-heroicons-arrow-path" @click="refreshMe">Rafraîchir</UButton>
          <UButton color="error" variant="soft" icon="i-heroicons-arrow-right-on-rectangle" @click="auth.logout()">Se déconnecter</UButton>
        </div>
      </UCard>
    </div>
  </div>
</template>

<script setup lang="ts">
const auth = useAuth()
const api = useApi()
const toast = useToast()
const authStore = useAuthStore()

const user = computed(() => authStore.user)

const isSuperAdmin = computed(() => {
  const roles = user.value?.roles ?? []
  return roles.includes('ROLE_SUPER_ADMIN') || String(user.value?.role || '').toLowerCase() === 'super_admin'
})

const isServiceClient = computed(() => {
  const roles = user.value?.roles ?? []
  return roles.includes('ROLE_SERVICE_CLIENT')
})

const canSwitchAtelier = computed(() => isSuperAdmin.value || isServiceClient.value)

const baseRoleLabels: Record<string, string> = {
  ROLE_SUPER_ADMIN: 'Super-administrateur',
  ROLE_ADMIN: 'Administrateur',
  ROLE_VO_MANAGER: 'Gestionnaire VO',
  ROLE_RECEPTIONNAIRE: 'Réceptionniste',
  ROLE_MECANICIEN: 'Mécanicien',
  ROLE_COMPTABLE: 'Comptable',
  ROLE_USER: 'Utilisateur',
}

const baseRoleLabel = computed(() => {
  const code = user.value?.role_metier?.base_role || user.value?.role || ''
  return baseRoleLabels[code] || code || '—'
})

const roleMetierLabel = computed(() => user.value?.role_metier?.libelle || '—')

const extraRoles = computed(() => {
  const list = user.value?.roles ?? []
  return list.filter((r) => r !== 'ROLE_USER')
})

const ateliersList = ref<Array<{ id: number | string; nom: string }>>([])

async function loadAteliers() {
  if (!canSwitchAtelier.value) return
  try {
    const data = await api.get('/auth/rdv-ateliers')
    ateliersList.value = Array.isArray(data) ? data : []
  } catch {
    ateliersList.value = []
  }
}

async function onSwitch(value: string) {
  const atelierId = value === '' ? null : Number(value)
  try {
    await api.post('/auth/switch-atelier', { atelier_id: atelierId })
    await auth.fetchMe()
    toast.add({ title: 'Atelier changé', color: 'success' })
  } catch (e: unknown) {
    toast.add({ title: 'Changement refusé', description: (e instanceof Error ? e.message : 'Erreur inconnue') || 'Atelier non autorisé.', color: 'error' })
  }
}

async function refreshMe() {
  await auth.fetchMe()
  toast.add({ title: 'Profil rafraîchi', color: 'success' })
}

onMounted(() => {
  loadAteliers()
})
</script>

<style scoped>
.profile-grid {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.profile-grid > div {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.lbl {
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--text-subtle);
  font-weight: 700;
}
.val {
  font-size: 14px;
  color: var(--text);
}
</style>
