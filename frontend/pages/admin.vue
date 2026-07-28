<template>
  <div class="admin-shell">
    <!-- Barre d'onglets : chaque onglet est un vrai lien, donc l'URL reste
         partageable et rechargeable (contrairement à un simple v-if local). -->
    <div class="tabstrip" role="tablist" aria-label="Sections d'administration">
      <div class="tabstrip-scroll">
        <NuxtLink
          v-for="onglet in onglets"
          :key="onglet.to"
          :to="onglet.to"
          class="tab"
          :class="{ 'tab--active': estActif(onglet) }"
          :aria-current="estActif(onglet) ? 'page' : undefined"
          :data-testid="`admin-tab-${onglet.cle}`"
        >
          <span class="tab-icon" aria-hidden="true">{{ onglet.icone }}</span>
          <span class="tab-label">{{ onglet.label }}</span>
        </NuxtLink>
      </div>
    </div>

    <!-- Contenu de l'onglet actif -->
    <div class="tab-panel">
      <NuxtPage />
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Cadre de la zone Administration : une barre d'onglets persistante (façon
 * navigateur) au-dessus du contenu, au lieu d'un menu de cartes à traverser.
 * Les pages `pages/admin/*.vue` restent inchangées et s'affichent dans le
 * panneau via les routes imbriquées de Nuxt.
 */
const route = useRoute()
const { user } = useAuth()

const isSuperAdmin = computed(() => {
  const roles = user.value?.roles ?? []
  return user.value?.role === 'super_admin' || roles.includes('ROLE_SUPER_ADMIN')
})

interface OngletAdmin {
  cle: string
  to: string
  icone: string
  label: string
  superAdminSeulement?: boolean
}

const TOUS_LES_ONGLETS: OngletAdmin[] = [
  { cle: 'config', to: '/admin/config', icone: '⚙️', label: 'Configuration' },
  { cle: 'users', to: '/admin/users', icone: '👥', label: 'Utilisateurs' },
  { cle: 'ponts', to: '/admin/ponts', icone: '🔧', label: 'Ponts & mécanos' },
  { cle: 'absences', to: '/admin/absences', icone: '📅', label: 'Absences' },
  { cle: 'prestations', to: '/admin/prestations', icone: '📋', label: 'Prestations' },
  { cle: 'notifications', to: '/admin/notifications/providers', icone: '📡', label: 'Notifications' },
  { cle: 'clauses', to: '/admin/clauses-legales', icone: '⚖️', label: 'Clauses légales' },
  { cle: 'templates', to: '/admin/templates-documents', icone: '📄', label: 'Documents' },
  { cle: 'audit', to: '/admin/audit', icone: '🔍', label: 'Audit' },
  { cle: 'ateliers', to: '/admin/ateliers', icone: '🏢', label: 'Ateliers', superAdminSeulement: true },
  { cle: 'roles', to: '/admin/roles', icone: '🛡️', label: 'Profils d\'accès', superAdminSeulement: true },
]

const onglets = computed(() =>
  TOUS_LES_ONGLETS.filter(o => !o.superAdminSeulement || isSuperAdmin.value),
)

/** Premier onglet accessible : cible de la redirection depuis /admin. */
const premierOnglet = computed(() => onglets.value[0]?.to ?? '/admin/config')

// Match par préfixe : une page de détail (ex. le designer de documents) garde
// son onglet parent actif.
function estActif(onglet: OngletAdmin): boolean {
  return route.path === onglet.to || route.path.startsWith(onglet.to + '/')
}

provide('adminPremierOnglet', premierOnglet)
</script>

<style scoped>
.admin-shell {
  display: flex;
  flex-direction: column;
}

.tabstrip {
  position: sticky;
  /* Le collage d'un élément sticky se mesure sur le PADDING BOX du conteneur de
     défilement : la zone de contenu ayant 24px de padding, un `top: 0` laissait une
     bande de 24px où le contenu continuait de défiler au-dessus de la barre.
     `top: -24px` compense ce padding, les marges négatives font déborder la barre
     sur toute la largeur, et le padding interne rétablit l'espacement visuel. */
  top: -24px;
  z-index: 30;
  margin: -24px -24px 0;
  padding: 24px 24px 0;
  background: var(--surface-1);
  border-bottom: 1px solid var(--border-2);
}

.tabstrip-scroll {
  display: flex;
  align-items: flex-end;
  gap: 2px;
  overflow-x: auto;
  scrollbar-width: thin;
  padding: 0 4px;
}

/* Masque la barre de défilement tout en gardant le scroll utilisable. */
.tabstrip-scroll::-webkit-scrollbar {
  height: 4px;
}

.tabstrip-scroll::-webkit-scrollbar-thumb {
  background: var(--surface-3);
  border-radius: 999px;
}

.tab {
  display: flex;
  align-items: center;
  gap: 7px;
  flex: 0 0 auto;
  max-width: 220px;
  padding: 9px 16px;
  border: 1px solid transparent;
  border-bottom: none;
  border-radius: 10px 10px 0 0;
  background: var(--overlay-soft);
  color: var(--content-3);
  font-size: 12.5px;
  font-weight: 600;
  text-decoration: none;
  white-space: nowrap;
  transition: background 0.15s, color 0.15s;
}

.tab:hover {
  background: var(--overlay-hover);
  color: var(--content-1);
}

/* Onglet actif : même fond que le panneau, il s'y « raccroche ».
   L'état actif est porté par le LISERÉ jaune, comme au design system ; le
   libellé reste en encre franche — un jaune de texte sur ce fond ne tenait
   pas 4,5:1. */
.tab--active {
  background: var(--overlay-hover);
  border-color: var(--border-2);
  color: var(--content-1);
  font-weight: var(--w-semi);
  box-shadow: inset 0 2px 0 var(--accent);
}

.tab--active:hover {
  background: var(--overlay-hover);
  color: var(--content-1);
}

.tab-icon {
  font-size: 13px;
}

.tab-label {
  overflow: hidden;
  text-overflow: ellipsis;
}

.tab-panel {
  padding: 20px 0 0;
  min-height: 60vh;
}

@media (max-width: 640px) {
  .tab {
    padding: 8px 12px;
  }

  .tab-label {
    max-width: 110px;
  }
}
</style>
