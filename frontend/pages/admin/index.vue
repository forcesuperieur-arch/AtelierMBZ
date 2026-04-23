<template>
  <div>
    <AppPageHeader title="Administration" />

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
        <div class="admin-card-label">Ponts & mécanos</div>
        <div class="admin-card-desc">Affectations atelier et postes de travail</div>
      </div>
      <div class="admin-card" @click="navigateTo('/admin/prestations')">
        <div class="admin-card-icon">📋</div>
        <div class="admin-card-label">Prestations</div>
        <div class="admin-card-desc">Tarifs et grilles</div>
      </div>
      <div v-if="isSuperAdmin" class="admin-card" @click="navigateTo('/admin/ateliers')">
        <div class="admin-card-icon">🏢</div>
        <div class="admin-card-label">Ateliers</div>
        <div class="admin-card-desc">Créer et piloter les sites multiatelier</div>
      </div>
      <div v-if="isSuperAdmin" class="admin-card" @click="navigateTo('/admin/roles')">
        <div class="admin-card-icon">🛡️</div>
        <div class="admin-card-label">Profils d'accès</div>
        <div class="admin-card-desc">Rôles système simples pour les comptes</div>
      </div>
      <div v-if="isSuperAdmin" class="admin-card" @click="navigateTo('/admin/roles-metier')">
        <div class="admin-card-icon">🎭</div>
        <div class="admin-card-label">Rôles métier avancés</div>
        <div class="admin-card-desc">Matrice détaillée par module et action</div>
      </div>
      <div v-if="isSuperAdmin" class="admin-card" @click="navigateTo('/admin/audit')">
        <div class="admin-card-icon">🔍</div>
        <div class="admin-card-label">Audit</div>
        <div class="admin-card-desc">Journal des actions</div>
      </div>
      <div class="admin-card" @click="navigateTo('/admin/notifications/providers')">
        <div class="admin-card-icon">📡</div>
        <div class="admin-card-label">Notifications</div>
        <div class="admin-card-desc">Providers SMS/Email & templates</div>
      </div>
      <div class="admin-card" @click="navigateTo('/admin/demandes-travaux-supp')">
        <div class="admin-card-icon">🛠️</div>
        <div class="admin-card-label">Demandes complémentaires</div>
        <div class="admin-card-desc">Travaux supplémentaires à arbitrer</div>
      </div>
      <div class="admin-card" @click="navigateTo('/admin/clauses-legales')">
        <div class="admin-card-icon">⚖️</div>
        <div class="admin-card-label">Clauses légales</div>
        <div class="admin-card-desc">CGV, mandat, RGPD, gardiennage…</div>
      </div>
      <div class="admin-card" @click="navigateTo('/admin/templates-documents')">
        <div class="admin-card-icon">📄</div>
        <div class="admin-card-label">Templates documents</div>
        <div class="admin-card-desc">Prévisualiser les PDF par nature: opposables, CERFA, synthèses</div>
      </div>
      <div class="admin-card" @click="navigateTo('/admin/cerfa-config')">
        <div class="admin-card-icon">🗂️</div>
        <div class="admin-card-label">Config CERFA</div>
        <div class="admin-card-desc">Positions des champs sur les formulaires CERFA (DA, mandat, cession)</div>
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
