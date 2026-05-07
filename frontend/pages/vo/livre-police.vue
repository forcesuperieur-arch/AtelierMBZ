<template>
  <div>
    <AppPageHeader title="Livre de Police" subtitle="Registre atelier des acquisitions et ventes VO.">
      <template #actions>
        <button class="topbar-new-btn" :disabled="pdfLoading" @click="downloadPdf">
          {{ pdfLoading ? 'Génération…' : 'Télécharger le PDF' }}
        </button>
      </template>
    </AppPageHeader>

    <VONav />

    <UCard>
      <div class="vo-filters">
        <UInput v-model="search" placeholder="N° ordre, immatriculation, vendeur, acheteur..." />
      </div>

      <UTable :data="filteredEntries" :columns="columns">
        <template #dateAcquisition-cell="{ row }">
          {{ formatDate(row.original.dateAcquisition) }}
        </template>
        <template #dateVente-cell="{ row }">
          {{ formatDate(row.original.dateVente) }}
        </template>
        <template #prixAchat-cell="{ row }">
          {{ formatPrice(row.original.prixAchat) }}
        </template>
        <template #prixVente-cell="{ row }">
          {{ formatPrice(row.original.prixVente || 0) }}
        </template>
      </UTable>
    </UCard>
  </div>
</template>

<script setup lang="ts">
import { useVoStore } from '~/stores/vo'

definePageMeta({ title: 'Livre de Police VO' })

const voStore = useVoStore()
const { formatPrice, formatDate, normalizeText, apiBase } = useVoHelpers()
const { downloadPdf: downloadPdfFile } = usePdfDownload()
const toast = useToast()
const pdfLoading = ref(false)

const search = ref('')

const columns = [
  { accessorKey: 'numeroOrdre', header: 'N° ordre' },
  { accessorKey: 'type', header: 'Type' },
  { accessorKey: 'dateAcquisition', header: 'Acquisition' },
  { accessorKey: 'immatriculation', header: 'Immat.' },
  { accessorKey: 'descriptionBien', header: 'Véhicule' },
  { accessorKey: 'vendeurNom', header: 'Vendeur' },
  { accessorKey: 'prixAchat', header: 'Prix achat' },
  { accessorKey: 'dateVente', header: 'Vente' },
  { accessorKey: 'acheteurNom', header: 'Acheteur' },
  { accessorKey: 'prixVente', header: 'Prix vente' },
]

const filteredEntries = computed(() => {
  return voStore.livrePolice.filter((entry: any) => {
    const haystack = normalizeText([
      entry.numeroOrdre,
      entry.immatriculation,
      entry.descriptionBien,
      entry.vendeurNom,
      entry.vendeurPrenom,
      entry.acheteurNom,
      entry.acheteurPrenom,
    ].filter(Boolean).join(' '))

    return !search.value || haystack.includes(normalizeText(search.value))
  })
})

async function downloadPdf() {
  if (pdfLoading.value) return
  pdfLoading.value = true
  try {
    await downloadPdfFile('/vo/livre-de-police/export-pdf', 'livre-de-police-export.pdf')
  } catch (e: unknown) {
    toast.add({ title: 'Erreur PDF', description: e instanceof Error ? e.message : 'Erreur inconnue' ?? 'Impossible de générer le PDF', color: 'error' })
  } finally {
    pdfLoading.value = false
  }
}

onMounted(async () => {
  await voStore.fetchLivrePolice()
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

.vo-filters {
  margin-bottom: 14px;
}
</style>