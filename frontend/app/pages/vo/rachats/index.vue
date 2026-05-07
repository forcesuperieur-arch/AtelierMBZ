<template>
  <div>
    <AppPageHeader title="Rachats VO" subtitle="Tous les dossiers de rachat, de la constitution à la vente.">
      <template #actions>
        <NuxtLink to="/vo/rachats/new" class="topbar-new-btn">+ Nouveau rachat</NuxtLink>
      </template>
    </AppPageHeader>

    <VONav />

    <div class="vo-summary-grid">
      <div class="vo-summary-card">
        <span>Rachats suivis</span>
        <strong>{{ purchaseSummary.total }}</strong>
        <small>{{ purchaseSummary.drafts }} brouillon(x)</small>
      </div>
      <div class="vo-summary-card">
        <span>Prêts à vendre</span>
        <strong>{{ purchaseSummary.live }}</strong>
        <small>{{ purchaseSummary.confirmed }} confirmé(s)</small>
      </div>
      <div class="vo-summary-card">
        <span>Docs à compléter</span>
        <strong>{{ purchaseSummary.documentsAlert }}</strong>
        <small>{{ purchaseSummary.total ? Math.round((purchaseSummary.documentsAlert / purchaseSummary.total) * 100) : 0 }}% du parc</small>
      </div>
      <div class="vo-summary-card">
        <span>Vendus</span>
        <strong>{{ purchaseSummary.sold }}</strong>
        <small>Historique disponible</small>
      </div>
    </div>

    <PitCard class="bg-white">
      <div class="vo-filters">
        <PitInput v-model="search" placeholder="Plaque, marque, modèle, vendeur..." />
        <PitSelect v-model="status">
          <option value="all">Tous les statuts</option>
          <option value="brouillon">Brouillon</option>
          <option value="en_stock">En stock</option>
          <option value="en_vente">En vente</option>
          <option value="reserve">Réservé</option>
          <option value="vendu">Vendu</option>
        </PitSelect>
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

      <div v-if="!filteredPurchases.length && !voStore.loading" class="vo-empty-state">
        <strong>Aucun rachat ne correspond aux filtres.</strong>
        <span>Élargissez la recherche ou créez un nouveau dossier pour alimenter le stock VO.</span>
      </div>

      <PitTable v-else :data="filteredPurchases" :columns="columns" :loading="voStore.loading" sticky>
        <template #purchasePrice-cell="{ row }">
          {{ formatPrice(row.original.purchasePrice) }}
        </template>
        <template #targetSalePrice-cell="{ row }">
          {{ formatPrice(row.original.targetSalePrice) }}
        </template>
        <template #margin-cell="{ row }">
          <span :style="{ color: Number(row.original.margin || 0) >= 0 ? '#22c55e' : '#ef4444', fontWeight: '700' }">
            {{ formatPrice(row.original.margin || 0) }}
          </span>
        </template>
        <template #status-cell="{ row }">
          <StatusBadge :status="row.original.status" />
        </template>
        <template #documents-cell="{ row }">
          <span :class="['vo-doc-pill', (row.original.missingDocuments || []).length ? 'is-warning' : 'is-ok']">
            {{ (row.original.missingDocuments || []).length }} manquant(s)
          </span>
        </template>
        <template #actions-cell="{ row }">
          <div class="vo-actions">
            <NuxtLink :to="`/vo/rachats/${row.original.id}`" class="vo-link-btn">Voir</NuxtLink>
            <PitButton v-if="row.original.status === 'brouillon'"  class="vo-link-btn" color="primary" is-success>Confirmer</PitButton>
            <PitButton v-if="['en_stock', 'en_vente', 'reserve'].includes(row.original.status)"  class="vo-link-btn" color="primary" is-warning>Vendre</PitButton>
            <PitButton  class="vo-link-btn" color="primary">PDF</PitButton>
          </div>
        </template>
      </PitTable>
    </PitCard>
  </div>
</template>

<script setup lang="ts">
import { useVoStore } from '~/stores/vo'

definePageMeta({
  layout: 'default', title: 'Rachats VO' })

const voStore = useVoStore()
const toast = useToast()
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
  { accessorKey: 'purchasePrice', header: 'Prix achat' },
  { accessorKey: 'targetSalePrice', header: 'Prix vente cible' },
  { accessorKey: 'margin', header: 'Marge' },
  { accessorKey: 'status', header: 'Statut' },
  { accessorKey: 'documents', header: 'Docs' },
  { accessorKey: 'actions', header: '' },
]

const purchaseSummary = computed(() => {
  const purchases = voStore.purchases

  return {
    total: purchases.length,
    drafts: purchases.filter((purchase: any) => purchase.status === 'brouillon').length,
    confirmed: purchases.filter((purchase: any) => purchase.status === 'en_stock').length,
    live: purchases.filter((purchase: any) => ['en_stock', 'en_vente', 'reserve'].includes(purchase.status)).length,
    sold: purchases.filter((purchase: any) => purchase.status === 'vendu').length,
    documentsAlert: purchases.filter((purchase: any) => (purchase.missingDocuments || []).length > 0).length,
  }
})

const quickFilters = computed(() => ([
  { value: 'all', label: 'Tous', count: purchaseSummary.value.total },
  { value: 'brouillon', label: 'Brouillons', count: purchaseSummary.value.drafts },
  { value: 'en_stock', label: 'En stock', count: purchaseSummary.value.confirmed },
  { value: 'en_vente', label: 'En vente', count: voStore.purchases.filter((purchase: any) => purchase.status === 'en_vente').length },
  { value: 'reserve', label: 'Réservés', count: voStore.purchases.filter((purchase: any) => purchase.status === 'reserve').length },
  { value: 'vendu', label: 'Vendus', count: purchaseSummary.value.sold },
]))

const filteredPurchases = computed(() => {
  return voStore.purchases.filter((purchase: any) => {
    const haystack = normalizeText([
      purchase.vehicule?.plaque,
      purchase.vehicule?.marque,
      purchase.vehicule?.modele,
      purchase.seller?.nom,
      purchase.seller?.prenom,
    ].filter(Boolean).join(' '))

    const matchesSearch = !search.value || haystack.includes(normalizeText(search.value))
    const matchesStatus = status.value === 'all' || purchase.status === status.value

    return matchesSearch && matchesStatus
  })
})

async function confirm(id: number) {
  try {
    await voStore.confirmPurchase(id)
    toast.add({ title: 'Rachat confirmé', color: 'success' })
    await Promise.all([voStore.fetchPurchases(), voStore.fetchStats()])
  } catch (error: unknown) {
    toast.add({ title: 'Erreur', description: error instanceof Error ? error.message : 'Erreur inconnue', color: 'error' })
  }
}

function openSale(id: number) {
  navigateTo(`/vo/rachats/${id}?sell=1`)
}

function downloadPv(id: number) {
  openPdf(`/vo/purchases/${id}/pv-rachat/pdf`)
}

onMounted(async () => {
  await voStore.fetchPurchases()
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
  color: var(--text-tertiary);
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
  border: 1px solid var(--border-default);
  background: var(--bg-elevated);
}

.vo-summary-card span,
.vo-summary-card small {
  color: var(--text-tertiary);
  font-size: 12px;
}

.vo-summary-card strong {
  color: var(--text-primary);
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
  border: 1px solid var(--border-default);
  background: var(--bg-surface);
  color: var(--text-secondary);
  font-size: 12px;
  font-weight: 700;
}

.vo-chip strong {
  color: var(--text-primary);
}

.vo-chip.is-active {
  border-color: var(--accent);
  background: var(--accent-dim);
  color: var(--accent);
}

.vo-select {
  width: 100%;
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--bg-surface);
  border: 1px solid var(--border-default);
  color: var(--text-primary);
}

.vo-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
}

.vo-link-btn {
  background: none;
  border: none;
  color: var(--accent);
  cursor: pointer;
  font-size: 12px;
  text-decoration: none;
  font-weight: 700;
}

.vo-link-btn.is-success { color: var(--success); }
.vo-link-btn.is-warning { color: var(--warning); }

.vo-doc-pill {
  display: inline-flex;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
}

.vo-doc-pill.is-ok {
  background: var(--success-bg);
  color: var(--success);
}

.vo-doc-pill.is-warning {
  background: var(--danger-bg);
  color: var(--danger-fg);
}

.vo-empty-state {
  display: grid;
  gap: 6px;
  padding: 18px;
  border-radius: 14px;
  border: 1px dashed var(--border-default);
  color: var(--text-secondary);
}

.vo-empty-state strong {
  color: var(--text-primary);
}

@media (max-width: 900px) {
  .vo-summary-grid,
  .vo-filters {
    grid-template-columns: 1fr;
  }
}
</style>