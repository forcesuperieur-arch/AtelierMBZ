<template>
  <nav
    v-if="items.length > 1"
    class="breadcrumbs"
    aria-label="Fil d'Ariane"
  >
    <ol class="breadcrumbs-list">
      <li
        v-for="(item, idx) in items"
        :key="idx"
        class="breadcrumbs-item"
      >
        <NuxtLink
          v-if="idx < items.length - 1"
          :to="item.to"
          class="breadcrumbs-link"
        >
          {{ item.label }}
        </NuxtLink>
        <span
          v-else
          class="breadcrumbs-current"
          aria-current="page"
        >
          {{ item.label }}
        </span>
        <span
          v-if="idx < items.length - 1"
          class="breadcrumbs-separator"
          aria-hidden="true"
        >
          ›
        </span>
      </li>
    </ol>
  </nav>
</template>

<script setup lang="ts">
const route = useRoute()

const routeLabels: Record<string, string> = {
  '': 'Accueil',
  'rdv': 'Rendez-vous',
  'planning': 'Planning',
  'clients': 'Clients',
  'workshop': 'Atelier',
  'ordres': 'Dossiers atelier',
  'devis': 'Devis',
  'facturation': 'Facturation',
  'stock': 'Stock',
  'motos': 'Fiches moto',
  'suivi': 'Suivi Live',
  'tarifs': 'Tarifs',
  'mecanicien': 'Espace Mécanicien',
  'admin': 'Administration',
  'vo': 'VO',
  'profile': 'Mon profil',
  'rachats': 'Rachats',
  'depots': 'Dépôts',
  'new': 'Nouveau',
  'edit': 'Édition',
  'documents': 'Documents',
  'factures': 'Factures',
  'livre-police': 'Livre de police',
  'remises-en-etat': 'Remises en état',
  'absences': 'Absences',
  'ateliers': 'Ateliers',
  'audit': 'Audit',
  'cerfa-config': 'Config CERFA',
  'clauses-legales': 'Clauses légales',
  'config': 'Configuration',
  'demandes-travaux-supp': 'Demandes travaux supp.',
  'notifications': 'Notifications',
  'providers': 'Providers',
  'ponts': 'Ponts',
  'prestations': 'Prestations',
  'roles-metier': 'Rôles métier',
  'roles': 'Rôles',
  'templates-documents': 'Templates documents',
  'users': 'Utilisateurs',
  'companion': 'Companion',
  'booking': 'Réservation',
  'demande': 'Demande',
  'mentions-legales': 'Mentions légales',
  'politique-confidentialite': 'Confidentialité',
  'vo-companion': 'Companion VO',
  'rapport': 'Rapport',
}

function isIdSegment(segment: string): boolean {
  return /^\d+$/.test(segment) || /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(segment)
}

function formatLabel(segment: string): string {
  if (isIdSegment(segment)) {
    return `Dossier #${segment.slice(0, 8)}`
  }
  return routeLabels[segment] || (segment.charAt(0).toUpperCase() + segment.slice(1))
}

const items = computed(() => {
  const segments = route.path.split('/').filter(Boolean)
  const crumbs: { label: string; to: string }[] = []

  // Always start with Home
  crumbs.push({ label: routeLabels[''] || 'Accueil', to: '/' })

  let path = ''
  for (const segment of segments) {
    path += '/' + segment
    crumbs.push({ label: formatLabel(segment), to: path })
  }

  return crumbs
})
</script>

<style scoped>
.breadcrumbs {
  display: flex;
  align-items: center;
  min-width: 0;
}
.breadcrumbs-list {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 4px 0;
  list-style: none;
  margin: 0;
  padding: 0;
  font-size: 12px;
  line-height: 1.4;
}
.breadcrumbs-item {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.breadcrumbs-link {
  color: var(--text-muted);
  text-decoration: none;
  transition: color var(--transition);
  white-space: nowrap;
}
.breadcrumbs-link:hover {
  color: var(--text);
}
.breadcrumbs-separator {
  color: var(--text-muted);
  opacity: 0.6;
  padding: 0 4px;
  user-select: none;
}
.breadcrumbs-current {
  color: var(--text-strong);
  font-weight: 600;
  white-space: nowrap;
}
</style>
