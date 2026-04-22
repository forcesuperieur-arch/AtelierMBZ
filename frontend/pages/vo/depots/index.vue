<template>
  <div>
    <AppPageHeader title="Dépôts-vente" subtitle="Suivi des mandats, commissions et restitutions.">
      <template #actions>
        <NuxtLink to="/vo/depots/new" class="topbar-new-btn">+ Nouveau dépôt</NuxtLink>
      </template>
    </AppPageHeader>

    <VONav />

    <div class="vo-summary-grid">
      <div class="vo-summary-card">
        <span>Dossiers suivis</span>
        <strong>{{ depotSummary.total }}</strong>
        <small>{{ depotSummary.active }} actif(s)</small>
      </div>
      <div class="vo-summary-card">
        <span>Mandats à surveiller</span>
        <strong>{{ depotSummary.expiringSoon }}</strong>
        <small>moins de 7 jours restants</small>
      </div>
      <div class="vo-summary-card">
        <span>Mandats expirés</span>
        <strong>{{ depotSummary.expired }}</strong>
        <small>à traiter ou restituer</small>
      </div>
      <div class="vo-summary-card">
        <span>Dossiers clôturés</span>
        <strong>{{ depotSummary.closed }}</strong>
        <small>vendus ou restitués</small>
      </div>
    </div>

    <UCard>
      <div class="vo-filters">
        <UInput v-model="search" placeholder="Plaque, marque, modèle, déposant..." />
        <select v-model="status" class="vo-select">
          <option value="all">Tous les statuts</option>
          <option value="actif">Actif</option>
          <option value="vendu">Vendu</option>
          <option value="restitue">Restitué</option>
        </select>
      </div>

      <div class="vo-chip-row">
        <button
          v-for="chip in quickFilters"
          :key="chip.value"
          type="button"
          class="vo-chip"
          :class="{ 'is-active': status === chip.value }"
          @click="status = chip.value"
        >
          <span>{{ chip.label }}</span>
          <strong>{{ chip.count }}</strong>
        </button>
      </div>

      <div v-if="!filteredDepots.length && !voStore.loading" class="vo-empty-state">
        <strong>Aucun dépôt ne correspond aux filtres.</strong>
        <span>Utilisez cette vue pour suivre les mandats actifs, les restitutions et les expirations à venir.</span>
      </div>

      <UTable v-else :data="filteredDepots" :columns="columns" :loading="voStore.loading">
        <template #prixVenteSouhaite-cell="{ row }">
          {{ formatPrice(row.original.prixVenteSouhaite) }}
        </template>
        <template #commissionAmount-cell="{ row }">
          {{ formatPrice(row.original.commissionAmount || 0) }}
        </template>
        <template #joursRestants-cell="{ row }">
          <span :style="{ color: row.original.mandatExpire ? '#ef4444' : Number(row.original.joursRestants ?? 999) <= 7 ? '#f59e0b' : '#d1d5db', fontWeight: '700' }">
            {{ row.original.mandatExpire ? 'Expiré' : `${row.original.joursRestants ?? 0} j` }}
          </span>
        </template>
        <template #status-cell="{ row }">
          <StatusBadge :status="row.original.status" />
        </template>
        <template #actions-cell="{ row }">
          <div class="vo-actions">
            <NuxtLink :to="`/vo/depots/${row.original.id}`" class="vo-link-btn">Voir</NuxtLink>
            <button v-if="row.original.status === 'actif'" class="vo-link-btn is-warning" @click="openSale(row.original.id)">Vendre</button>
            <button class="vo-link-btn" @click="downloadContrat(row.original.id)">PDF</button>
          </div>
        </template>
      </UTable>
    </UCard>
  </div>
</template>

<script setup lang="ts">
import { useVoStore } from '~/stores/vo'

definePageMeta({ title: 'Dépôts VO' })

const voStore = useVoStore()
const config = useRuntimeConfig()
const { formatPrice, normalizeText } = useVoHelpers()
const apiBase = config.public.apiBase as string
const { openPdf } = usePdfDownload()

const search = ref('')
const status = ref('all')

const columns = [
  { accessorKey: 'vehicule.plaque', header: 'Immat.' },
  { accessorKey: 'vehicule.marque', header: 'Marque' },
  { accessorKey: 'vehicule.modele', header: 'Modèle' },
  { accessorKey: 'deposant.nom', header: 'Déposant' },
  { accessorKey: 'prixVenteSouhaite', header: 'Prix souhaité' },
  { accessorKey: 'commissionAmount', header: 'Commission HT' },
  { accessorKey: 'joursRestants', header: 'Mandat' },
  { accessorKey: 'status', header: 'Statut' },
  { accessorKey: 'actions', header: '' },
]

const depotSummary = computed(() => {
  const depots = voStore.depots
  const active = depots.filter((depot: any) => depot.status === 'actif')

  return {
    total: depots.length,
    active: active.length,
    expiringSoon: active.filter((depot: any) => !depot.mandatExpire && Number(depot.joursRestants ?? 999) <= 7).length,
    expired: active.filter((depot: any) => depot.mandatExpire).length,
    closed: depots.filter((depot: any) => ['vendu', 'restitue'].includes(depot.status)).length,
  }
})

const quickFilters = computed(() => ([
  { value: 'all', label: 'Tous', count: depotSummary.value.total },
  { value: 'actif', label: 'Actifs', count: depotSummary.value.active },
  { value: 'vendu', label: 'Vendus', count: voStore.depots.filter((depot: any) => depot.status === 'vendu').length },
  { value: 'restitue', label: 'Restitués', count: voStore.depots.filter((depot: any) => depot.status === 'restitue').length },
]))

const filteredDepots = computed(() => {
  return voStore.depots.filter((depot: any) => {
    const haystack = normalizeText([
      depot.vehicule?.plaque,
      depot.vehicule?.marque,
      depot.vehicule?.modele,
      depot.deposant?.nom,
      depot.deposant?.prenom,
    ].filter(Boolean).join(' '))

    const matchesSearch = !search.value || haystack.includes(normalizeText(search.value))
    const matchesStatus = status.value === 'all' || depot.status === status.value

    return matchesSearch && matchesStatus
  })
})

function openSale(id: number) {
  navigateTo(`/vo/depots/${id}?sell=1`)
}

function downloadContrat(id: number) {
  openPdf(`/vo/depots/${id}/contrat/pdf`)
}

onMounted(async () => {
  await voStore.fetchDepots()
})
</script>

<style scoped>
.vo-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.vo-subtitle {
  margin-top: 6px;
  color: #9ca3af;
  font-size: 13px;
}

.vo-summary-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}

.vo-summary-card {
  display: grid;
  gap: 6px;
  padding: 14px;
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.06);
  background: rgba(255, 255, 255, 0.02);
}

.vo-summary-card span,
.vo-summary-card small {
  color: #9ca3af;
  font-size: 12px;
}

.vo-summary-card strong {
  color: #f9fafb;
  font-size: 24px;
  line-height: 1;
}

.vo-filters {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 220px;
  gap: 12px;
  margin-bottom: 14px;
}

.vo-chip-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 14px;
}

.vo-chip {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border-radius: 999px;
  border: 1px solid rgba(255, 255, 255, 0.06);
  background: rgba(255, 255, 255, 0.02);
  color: #9ca3af;
  font-size: 12px;
  font-weight: 700;
}

.vo-chip strong {
  color: #f9fafb;
}

.vo-chip.is-active {
  border-color: rgba(245, 158, 11, 0.35);
  background: rgba(245, 158, 11, 0.12);
  color: #fcd34d;
}

.vo-select {
  width: 100%;
  padding: 10px 12px;
  border-radius: 10px;
  background: #1a1a2e;
  border: 1px solid #374151;
  color: #e8e9ed;
}

.vo-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
}

.vo-link-btn {
  background: none;
  border: none;
  color: #9ca3af;
  cursor: pointer;
  font-size: 12px;
  text-decoration: none;
  font-weight: 700;
}

.vo-link-btn.is-warning { color: #f59e0b; }

.vo-empty-state {
  display: grid;
  gap: 6px;
  padding: 18px;
  border-radius: 14px;
  border: 1px dashed rgba(255, 255, 255, 0.1);
  color: #9ca3af;
}

.vo-empty-state strong {
  color: #f9fafb;
}

@media (max-width: 900px) {
  .vo-summary-grid,
  .vo-filters {
    grid-template-columns: 1fr;
  }
}
</style>