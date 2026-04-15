<template>
  <div>
    <div class="page-header">
      <div class="page-title">Stock — Pièces détachées</div>
      <button class="topbar-new-btn" @click="resetForm(); showNew = true">+ Nouvelle pièce</button>
    </div>

    <UCard style="margin-bottom:16px;">
      <UInput v-model="search" placeholder="Rechercher une pièce..." @input="debouncedFetch" />
    </UCard>

    <!-- Alerts -->
    <UCard v-if="stockStore.alertes.length" style="margin-bottom:16px;border-color:rgba(239,68,68,0.2);">
      <template #header>
        <span style="font-size:15px;font-weight:700;color:#FCA5A5;">
          ⚠ Alertes stock ({{ stockStore.alertes.length }})
        </span>
      </template>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
        <div v-for="p in stockStore.alertes" :key="p.id" style="display:flex;align-items:center;justify-content:space-between;font-size:13px;padding:8px 12px;border-radius:10px;border:1px solid rgba(239,68,68,0.15);background:rgba(239,68,68,0.05);">
          <span style="color:#D1D5DB;">{{ p.designation }}</span>
          <span class="status-badge" style="background:rgba(239,68,68,0.12);color:#FCA5A5;">{{ p.quantite_stock }} / {{ p.seuil_alerte }}</span>
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
          <button style="color:#FFD200;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="editPiece(row.original)">✏ Modifier</button>
        </template>
      </UTable>
    </UCard>

    <!-- New/Edit modal -->
    <AppModal v-model:open="showNew" size="lg">
      <template #default>
        <UCard>
          <template #header><span style="font-size:15px;font-weight:700;color:#E8E9ED;">{{ editId ? 'Modifier' : 'Nouvelle' }} pièce</span></template>
          <form @submit.prevent="savePiece" style="display:flex;flex-direction:column;gap:12px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <UFormField label="Référence"><UInput v-model="pieceForm.reference" required /></UFormField>
              <UFormField label="Désignation"><UInput v-model="pieceForm.designation" required /></UFormField>
              <UFormField label="Prix achat HT"><UInput v-model="pieceForm.prix_achat_ht" type="number" step="0.01" /></UFormField>
              <UFormField label="Prix vente HT"><UInput v-model="pieceForm.prix_vente_ht" type="number" step="0.01" /></UFormField>
              <UFormField label="Quantité stock"><UInput v-model="pieceForm.quantite_stock" type="number" /></UFormField>
              <UFormField label="Seuil alerte"><UInput v-model="pieceForm.seuil_alerte" type="number" /></UFormField>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;">
              <UButton label="Annuler" variant="outline" @click="showNew = false" />
              <UButton type="submit" :label="editId ? 'Modifier' : 'Créer'" :loading="saving" />
            </div>
          </form>
        </UCard>
      </template>
    </AppModal>
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
