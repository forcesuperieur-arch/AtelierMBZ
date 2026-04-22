<template>
  <div>
    <AppPageHeader title="Mon profil" subtitle="Compte et contexte de connexion." />

    <div v-if="!user" class="empty-state">
      <div class="empty-state-icon">👤</div>
      <div class="empty-state-title">Profil indisponible</div>
      <div class="empty-state-sub">Reconnectez-vous pour rafraîchir votre session.</div>
    </div>

    <div v-else style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;">
      <UCard>
        <template #header>
          <span style="font-size:14px;font-weight:700;color:var(--text-strong);">👤 Identité</span>
        </template>
        <div class="profile-grid">
          <div><span class="lbl">Prénom</span><span class="val">{{ user.prenom || '—' }}</span></div>
          <div><span class="lbl">Nom</span><span class="val">{{ user.nom || '—' }}</span></div>
          <div><span class="lbl">Email</span><span class="val">{{ user.email }}</span></div>
          <div><span class="lbl">Identifiant</span><span class="val">{{ user.username }}</span></div>
          <div>
            <span class="lbl">Méthode de connexion</span>
            <span class="val">
              <span v-if="user.auth_provider === 'google'" class="status-badge" style="background:rgba(34,197,94,0.14);color:#86EFAC;">Google SSO</span>
              <span v-else class="status-badge" style="background:rgba(59,130,246,0.14);color:#93C5FD;">{{ user.auth_provider || 'local' }}</span>
            </span>
          </div>
        </div>
      </UCard>

      <UCard>
        <template #header>
          <span style="font-size:14px;font-weight:700;color:var(--text-strong);">🎭 Rôle &amp; permissions</span>
        </template>
        <div class="profile-grid">
          <div>
            <span class="lbl">Rôle métier</span>
            <span class="val">{{ roleMetierLabel }}</span>
          </div>
          <div>
            <span class="lbl">Rôle de base</span>
            <span class="val">{{ baseRoleLabel }}</span>
          </div>
          <div v-if="extraRoles.length">
            <span class="lbl">Rôles techniques</span>
            <span class="val" style="display:flex;flex-wrap:wrap;gap:4px;">
              <span v-for="r in extraRoles" :key="r" class="status-badge" style="background:rgba(255,255,255,0.06);color:var(--text-muted);font-size:10px;">{{ r }}</span>
            </span>
          </div>
          <div v-if="user.role_metier?.permissions?.length">
            <span class="lbl">Permissions accordées</span>
            <span class="val" style="font-size:12px;color:var(--text-muted);">{{ user.role_metier.permissions.length }} entrée(s)</span>
          </div>
        </div>
      </UCard>

      <UCard>
        <template #header>
          <span style="font-size:14px;font-weight:700;color:var(--text-strong);">🏢 Atelier actif</span>
        </template>
        <div v-if="!user.atelier_id && !isSuperAdmin" class="alert alert--warning" style="margin:0;">
          <div class="alert-title">⚠️ Aucun atelier affecté</div>
          <div class="alert-body">Demandez à un administrateur de vous rattacher à un atelier pour accéder aux écrans opérationnels.</div>
        </div>
        <div v-else class="profile-grid">
          <div>
            <span class="lbl">Nom</span>
            <span class="val">{{ user.atelier_nom || (isSuperAdmin ? 'Tous (vue super-admin)' : '—') }}</span>
          </div>
          <div v-if="user.atelier_id">
            <span class="lbl">ID atelier</span>
            <span class="val" style="font-family:monospace;font-size:12px;color:var(--text-muted);">#{{ user.atelier_id }}</span>
          </div>
          <div v-if="canSwitchAtelier">
            <span class="lbl">Changer d'atelier</span>
            <select :value="user.atelier_id ?? ''" class="form-input" style="max-width:280px;" @change="onSwitch(($event.target as HTMLSelectElement).value)">
              <option v-for="a in ateliersList" :key="a.id" :value="a.id">{{ a.nom }}</option>
            </select>
          </div>
        </div>
      </UCard>

      <UCard>
        <template #header>
          <span style="font-size:14px;font-weight:700;color:var(--text-strong);">⚙ Session</span>
        </template>
        <div class="profile-grid">
          <div>
            <span class="lbl">Statut du compte</span>
            <span class="val">
              <span v-if="user.access_status === 'active'" class="status-badge" style="background:rgba(16,185,129,0.14);color:#6EE7B7;">Actif</span>
              <span v-else class="status-badge" style="background:rgba(245,158,11,0.14);color:#FCD34D;">{{ user.access_status || 'inconnu' }}</span>
            </span>
          </div>
        </div>
        <div style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap;">
          <button class="btn btn-ghost" @click="refreshMe">↻ Rafraîchir</button>
          <button class="btn" style="background:rgba(239,68,68,0.14);color:#FCA5A5;border:1px solid rgba(239,68,68,0.28);" @click="auth.logout()">Se déconnecter</button>
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
  } catch (e: any) {
    toast.add({ title: 'Changement refusé', description: e?.message || 'Atelier non autorisé.', color: 'error' })
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
