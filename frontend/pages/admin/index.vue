<template>
  <div>
    <div class="page-header">
      <div class="page-title">Administration</div>
    </div>

    <div class="admin-grid">
      <div class="admin-card" @click="navigateTo('/admin/users')">
        <div class="admin-card-icon">👥</div>
        <div class="admin-card-label">Utilisateurs</div>
        <div class="admin-card-desc">Gérer les comptes et rôles</div>
      </div>
      <div class="admin-card" @click="navigateTo('/admin/config')">
        <div class="admin-card-icon">⚙️</div>
        <div class="admin-card-label">Configuration</div>
        <div class="admin-card-desc">Paramètres de l'atelier</div>
      </div>
      <div class="admin-card" @click="navigateTo('/admin/absences')">
        <div class="admin-card-icon">📅</div>
        <div class="admin-card-label">Absences</div>
        <div class="admin-card-desc">Gérer les congés mécaniciens</div>
      </div>
      <div class="admin-card" @click="navigateTo('/admin/ponts')">
        <div class="admin-card-icon">🔧</div>
        <div class="admin-card-label">Ponts</div>
        <div class="admin-card-desc">Gérer les postes de travail</div>
      </div>
      <div class="admin-card" @click="navigateTo('/admin/prestations')">
        <div class="admin-card-icon">📋</div>
        <div class="admin-card-label">Prestations</div>
        <div class="admin-card-desc">Tarifs et grilles</div>
      </div>
      <div v-if="isSuperAdmin" class="admin-card" @click="navigateTo('/admin/roles')">
        <div class="admin-card-icon">🛡️</div>
        <div class="admin-card-label">Rôles</div>
        <div class="admin-card-desc">CRUD des rôles et permissions</div>
      </div>
      <div v-if="isSuperAdmin" class="admin-card" @click="navigateTo('/admin/roles-metier')">
        <div class="admin-card-icon">🎭</div>
        <div class="admin-card-label">Rôles métier</div>
        <div class="admin-card-desc">Permissions paramétrables par module</div>
      </div>
      <div class="admin-card" @click="navigateTo('/admin/audit')">
        <div class="admin-card-icon">🔍</div>
        <div class="admin-card-label">Audit</div>
        <div class="admin-card-desc">Journal des actions</div>
      </div>
      <div class="admin-card" @click="navigateTo('/admin/notifications/providers')">
        <div class="admin-card-icon">📡</div>
        <div class="admin-card-label">Notifications</div>
        <div class="admin-card-desc">Providers SMS/Email & templates</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const { user } = useAuth()

const isSuperAdmin = computed(() => {
  const roles = user.value?.roles ?? []
  return user.value?.role === 'super_admin' || roles.includes('ROLE_SUPER_ADMIN')
})
</script>
