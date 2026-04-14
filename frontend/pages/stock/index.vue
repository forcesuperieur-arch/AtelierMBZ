<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Stock — Pièces détachées</h1>
      <UButton label="Nouvelle pièce" icon="i-heroicons-plus" @click="resetForm(); showNew = true" />
    </div>

    <UCard class="mb-4">
      <UInput v-model="search" placeholder="Rechercher une pièce..." icon="i-heroicons-magnifying-glass" @input="debouncedFetch" />
    </UCard>

    <!-- Alerts -->
    <UCard v-if="stockStore.alertes.length" class="mb-4 border-warning">
      <template #header>
        <h2 class="font-semibold text-warning">
          <UIcon name="i-heroicons-exclamation-triangle" /> Alertes stock ({{ stockStore.alertes.length }})
        </h2>
      </template>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
        <div v-for="p in stockStore.alertes" :key="p.id" class="flex items-center justify-between text-sm p-2 bg-warning-50 dark:bg-warning-950 rounded">
          <span>{{ p.designation }}</span>
          <UBadge color="error" variant="subtle">{{ p.quantite_stock }} / {{ p.seuil_alerte }}</UBadge>
        </div>
      </div>
    </UCard>

    <UCard>
      <UTable :data="stockStore.pieces" :columns="columns" :loading="stockStore.loading">
        <template #quantite_stock-cell="{ row }">
          <span :class="row.original.quantite_stock <= row.original.seuil_alerte ? 'text-red-500 font-bold' : ''">
            {{ row.original.quantite_stock }}
          </span>
        </template>
        <template #actions-cell="{ row }">
          <UButton size="xs" variant="ghost" icon="i-heroicons-pencil" @click="editPiece(row.original)" />
        </template>
      </UTable>
    </UCard>

    <!-- New/Edit modal -->
    <UModal v-model:open="showNew">
      <template #default>
        <UCard>
          <template #header><h2 class="font-semibold">{{ editId ? 'Modifier' : 'Nouvelle' }} pièce</h2></template>
          <form @submit.prevent="savePiece" class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
              <UFormField label="Référence"><UInput v-model="pieceForm.reference" required /></UFormField>
              <UFormField label="Désignation"><UInput v-model="pieceForm.designation" required /></UFormField>
              <UFormField label="Prix achat HT"><UInput v-model="pieceForm.prix_achat_ht" type="number" step="0.01" /></UFormField>
              <UFormField label="Prix vente HT"><UInput v-model="pieceForm.prix_vente_ht" type="number" step="0.01" /></UFormField>
              <UFormField label="Quantité stock"><UInput v-model="pieceForm.quantite_stock" type="number" /></UFormField>
              <UFormField label="Seuil alerte"><UInput v-model="pieceForm.seuil_alerte" type="number" /></UFormField>
            </div>
            <div class="flex justify-end gap-2">
              <UButton label="Annuler" variant="outline" @click="showNew = false" />
              <UButton type="submit" :label="editId ? 'Modifier' : 'Créer'" :loading="saving" />
            </div>
          </form>
        </UCard>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
const stockStore = useStockStore()
const toast = useToast()
const search = ref('')
const showNew = ref(false)
const saving = ref(false)
const editId = ref<number | null>(null)

const pieceForm = reactive({
  reference: '', designation: '', prix_achat_ht: 0, prix_vente_ht: 0,
  quantite_stock: 0, seuil_alerte: 5,
})

function resetForm() {
  editId.value = null
  Object.assign(pieceForm, { reference: '', designation: '', prix_achat_ht: 0, prix_vente_ht: 0, quantite_stock: 0, seuil_alerte: 5 })
}

const columns = [
  { key: 'reference', label: 'Réf.' },
  { key: 'designation', label: 'Désignation' },
  { key: 'prix_vente_ht', label: 'Prix HT' },
  { key: 'quantite_stock', label: 'Stock' },
  { key: 'seuil_alerte', label: 'Seuil' },
  { key: 'actions', label: '' },
]

let timeout: ReturnType<typeof setTimeout>
function debouncedFetch() {
  clearTimeout(timeout)
  timeout = setTimeout(() => stockStore.fetchPieces(search.value), 300)
}

function editPiece(p: any) {
  editId.value = p.id
  Object.assign(pieceForm, p)
  showNew.value = true
}

async function savePiece() {
  saving.value = true
  try {
    if (editId.value) {
      await stockStore.updatePiece(editId.value, pieceForm)
    } else {
      await stockStore.createPiece(pieceForm)
    }
    showNew.value = false
    editId.value = null
    toast.add({ title: 'Pièce sauvegardée', color: 'success' })
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  } finally {
    saving.value = false
  }
}

onMounted(() => stockStore.fetchPieces())
</script>
