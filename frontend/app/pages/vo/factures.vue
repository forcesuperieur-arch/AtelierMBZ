<template>
  <div>
    <AppPageHeader title="Factures VO" subtitle="Historique des factures générées sur les ventes VO." />

    <VONav />

    <PitCard class="bg-white">
      <div class="vo-filters">
        <PitInput v-model="search" placeholder="Numéro, client, véhicule..." />
      </div>

      <PitTable :data="filteredFactures" :columns="columns">
        <template #dateCreation-cell="{ row }">
          {{ formatDate(row.original.dateCreation) }}
        </template>
        <template #totalTtc-cell="{ row }">
          {{ formatPrice(row.original.totalTtc) }}
        </template>
        <template #actions-cell="{ row }">
          <PitButton class="vo-link-btn" color="primary" @click="downloadFacture(row.original.id)">PDF</PitButton>
        </template>
      </PitTable>
    </PitCard>
  </div>
</template>

<script setup lang="ts">
import { useVoStore } from '~/stores/vo'

definePageMeta({
  layout: 'default', title: 'Factures VO' })

const voStore = useVoStore()
const { formatPrice, formatDate, normalizeText, apiBase } = useVoHelpers()
const { openPdf } = usePdfDownload()

const search = ref('')

const columns = [
  { accessorKey: 'numeroFacture', header: 'Numéro' },
  { accessorKey: 'dateCreation', header: 'Date' },
  { accessorKey: 'snapClientNom', header: 'Client' },
  { accessorKey: 'snapVehiculeModele', header: 'Véhicule' },
  { accessorKey: 'immatriculation', header: 'Immat.' },
  { accessorKey: 'regimeTva', header: 'Régime TVA' },
  { accessorKey: 'statut', header: 'Statut' },
  { accessorKey: 'totalTtc', header: 'Total TTC' },
  { accessorKey: 'actions', header: '' },
]

const filteredFactures = computed(() => {
  return voStore.factures.filter((facture: any) => {
    const haystack = normalizeText([
      facture.numeroFacture,
      facture.snapClientNom,
      facture.snapClientPrenom,
      facture.snapVehiculeMarque,
      facture.snapVehiculeModele,
      facture.immatriculation,
    ].filter(Boolean).join(' '))

    return !search.value || haystack.includes(normalizeText(search.value))
  })
})

function downloadFacture(id: number) {
  openPdf(`/vo/factures/${id}/pdf`)
}

onMounted(async () => {
  await voStore.fetchFactures()
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
  color: var(--text-secondary);
  font-size: 13px;
}

.vo-filters {
  margin-bottom: 14px;
}

.vo-link-btn {
  background: none;
  border: none;
  color: var(--accent);
  cursor: pointer;
  font-size: 12px;
  font-weight: 700;
}
</style>