<template>
  <div>
    <AppPageHeader title="Stock — Pièces détachées">
      <template #actions>
        <div style="display:flex;gap:8px;">
          <NuxtLink to="/stock/fournisseurs" class="topbar-new-btn" style="background:rgba(255,255,255,0.08);">Fournisseurs</NuxtLink>
          <NuxtLink to="/stock/commandes" class="topbar-new-btn" style="background:rgba(255,255,255,0.08);">Commandes</NuxtLink>
          <button class="topbar-new-btn" @click="resetForm(); showNew = true">+ Nouvelle pièce</button>
        </div>
      </template>
    </AppPageHeader>

    <!-- Alerts -->
    <UCard v-if="stockStore.alertes.length" style="margin-bottom:16px;border-color:rgba(239,68,68,0.2);">
      <template #header>
        <span style="font-size:15px;font-weight:700;color:#FCA5A5;">⚠ Alertes stock ({{ stockStore.alertes.length }})</span>
      </template>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
        <div v-for="p in stockStore.alertes" :key="p.id" style="display:flex;align-items:center;justify-content:space-between;font-size:13px;padding:8px 12px;border-radius:10px;border:1px solid rgba(239,68,68,0.15);background:rgba(239,68,68,0.05);">
          <span style="color:#D1D5DB;">{{ p.designation }}</span>
          <span class="status-badge" style="background:rgba(239,68,68,0.12);color:#FCA5A5;">{{ p.quantite_stock }} / {{ p.seuil_alerte }}</span>
        </div>
      </div>
    </UCard>

    <UCard style="margin-bottom:16px;">
      <UInput v-model="search" placeholder="Rechercher une pièce..." @input="debouncedFetch" />
    </UCard>

    <UCard>
      <UTable :data="stockStore.pieces" :columns="columns" :loading="stockStore.loading">
        <template #quantite_stock-cell="{ row }">
          <span :class="row.original.quantite_stock <= row.original.seuil_alerte ? 'text-red-500 font-bold' : ''">
            {{ row.original.quantite_stock }}
          </span>
        </template>
        <template #prix_vente_ht-cell="{ row }">
          <span class="text-sm">{{ formatCurrency(row.original.prix_vente_ht) }}</span>
        </template>
        <template #actions-cell="{ row }">
          <div style="display:flex;gap:8px;">
            <button style="color:#FFD200;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="editPiece(row.original)">✏ Modifier</button>
            <button style="color:#93C5FD;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="openMouvements(row.original)">📜 Mouvements</button>
          </div>
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
              <UFormField label="Réf. fournisseur"><UInput v-model="pieceForm.reference_fournisseur" /></UFormField>
              <UFormField label="Catégorie"><UInput v-model="pieceForm.categorie" /></UFormField>
              <UFormField label="Prix achat HT"><UInput v-model="pieceForm.prix_achat_ht" type="number" step="0.01" /></UFormField>
              <UFormField label="Prix vente HT"><UInput v-model="pieceForm.prix_vente_ht" type="number" step="0.01" /></UFormField>
              <UFormField label="Quantité stock"><UInput v-model="pieceForm.quantite_stock" type="number" /></UFormField>
              <UFormField label="Seuil alerte"><UInput v-model="pieceForm.seuil_alerte" type="number" /></UFormField>
              <UFormField label="Quantité max"><UInput v-model="pieceForm.quantite_maximale" type="number" /></UFormField>
              <UFormField label="Emplacement"><UInput v-model="pieceForm.emplacement" /></UFormField>
              <UFormField label="TVA (%)"><UInput v-model="pieceForm.tva_taux" type="number" step="0.01" /></UFormField>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;">
              <UButton label="Annuler" variant="outline" @click="showNew = false" />
              <UButton type="submit" :label="editId ? 'Modifier' : 'Créer'" :loading="saving" />
            </div>
          </form>
        </UCard>
      </template>
    </AppModal>

    <!-- Mouvements modal -->
    <AppModal v-model:open="showMouvements" size="lg">
      <UCard v-if="selectedPiece">
        <template #header>
          <span style="font-size:15px;font-weight:700;color:#E8E9ED;">Mouvements — {{ selectedPiece.designation }}</span>
        </template>
        <div v-if="stockStore.loadingMouvements" style="text-align:center;padding:20px;color:#9CA3AF;">Chargement…</div>
        <div v-else-if="!stockStore.mouvements.length" style="text-align:center;padding:20px;color:#9CA3AF;">Aucun mouvement</div>
        <div v-else style="display:flex;flex-direction:column;gap:8px;">
          <div v-for="m in stockStore.mouvements" :key="m.id" style="display:flex;justify-content:space-between;align-items:center;padding:8px 10px;border-radius:8px;background:rgba(255,255,255,0.03);">
            <div>
              <span class="text-sm font-bold" :style="mouvementColor(m.type)">{{ mouvementLabel(m.type) }}</span>
              <span class="text-sm" style="color:#9CA3AF;margin-left:6px;">{{ m.quantite }} unité(s)</span>
              <div v-if="m.motif" class="text-sm" style="color:#6B7280;margin-top:2px;">{{ m.motif }}</div>
            </div>
            <span class="text-sm" style="color:#6B7280;">{{ formatDate(m.created_at) }}</span>
          </div>
        </div>
        <div style="margin-top:12px;display:flex;justify-content:flex-end;gap:8px;">
          <UButton label="Ajustement manuel" @click="openAjustement" />
          <UButton label="Fermer" variant="outline" @click="showMouvements = false" />
        </div>
      </UCard>
    </AppModal>

    <!-- Ajustement modal -->
    <AppModal v-model:open="showAjustement" size="md">
      <UCard v-if="selectedPiece">
        <template #header><span style="font-size:15px;font-weight:700;color:#E8E9ED;">Ajustement — {{ selectedPiece.designation }}</span></template>
        <form @submit.prevent="saveAjustement" style="display:flex;flex-direction:column;gap:12px;">
          <div style="font-size:13px;color:#9CA3AF;">Stock actuel : <strong style="color:#E8E9ED;">{{ selectedPiece.quantite_stock }}</strong></div>
          <UFormField label="Nouvelle quantité"><UInput v-model="ajustementForm.quantite" type="number" required /></UFormField>
          <UFormField label="Motif"><UInput v-model="ajustementForm.motif" required placeholder="Inventaire, casse, correction…" /></UFormField>
          <div style="display:flex;justify-content:flex-end;gap:8px;">
            <UButton label="Annuler" variant="outline" @click="showAjustement = false" />
            <UButton type="submit" label="Valider" :loading="savingAjustement" />
          </div>
        </form>
      </UCard>
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
const showMouvements = ref(false)
const showAjustement = ref(false)
const savingAjustement = ref(false)
const selectedPiece = ref<any>(null)

const pieceForm = reactive({
  reference: '', designation: '', reference_fournisseur: '', categorie: '',
  prix_achat_ht: 0, prix_vente_ht: 0, quantite_stock: 0, seuil_alerte: 5,
  quantite_maximale: 50, emplacement: '', tva_taux: 20,
})
const ajustementForm = reactive({ quantite: 0, motif: '' })

const columns = [
  { key: 'reference', label: 'Réf.' },
  { key: 'designation', label: 'Désignation' },
  { key: 'categorie', label: 'Catégorie' },
  { key: 'prix_vente_ht', label: 'Prix HT' },
  { key: 'quantite_stock', label: 'Stock' },
  { key: 'seuil_alerte', label: 'Seuil' },
  { key: 'actions', label: '' },
]

function resetForm() {
  editId.value = null
  Object.assign(pieceForm, { reference: '', designation: '', reference_fournisseur: '', categorie: '', prix_achat_ht: 0, prix_vente_ht: 0, quantite_stock: 0, seuil_alerte: 5, quantite_maximale: 50, emplacement: '', tva_taux: 20 })
}

let timeout: ReturnType<typeof setTimeout>
function debouncedFetch() {
  clearTimeout(timeout)
  timeout = setTimeout(() => stockStore.fetchPieces(search.value), 300)
}

function editPiece(p: any) {
  editId.value = p.id
  Object.assign(pieceForm, {
    reference: p.reference, designation: p.designation, reference_fournisseur: p.reference_fournisseur ?? '',
    categorie: p.categorie ?? '', prix_achat_ht: p.prix_achat_ht ?? 0, prix_vente_ht: p.prix_vente_ht ?? 0,
    quantite_stock: p.quantite_stock, seuil_alerte: p.seuil_alerte,
    quantite_maximale: p.quantite_maximale ?? 50, emplacement: p.emplacement ?? '', tva_taux: p.tva_taux ?? 20,
  })
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
    toast.add({ title: 'Pièce sauvegardée', color: 'success' })
    showNew.value = false
    editId.value = null
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
  } finally {
    saving.value = false
  }
}

function openMouvements(p: any) {
  selectedPiece.value = p
  stockStore.fetchMouvements(p.id)
  showMouvements.value = true
}

function openAjustement() {
  ajustementForm.quantite = selectedPiece.value?.quantite_stock ?? 0
  ajustementForm.motif = ''
  showAjustement.value = true
}

async function saveAjustement() {
  savingAjustement.value = true
  try {
    await stockStore.createMouvement({
      piece_id: selectedPiece.value.id,
      type: 'ajustement',
      quantite: Number(ajustementForm.quantite),
      motif: ajustementForm.motif,
    })
    toast.add({ title: 'Ajustement enregistré', color: 'success' })
    showAjustement.value = false
    showMouvements.value = false
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
  } finally {
    savingAjustement.value = false
  }
}

function formatCurrency(v: number) {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(v || 0)
}
function formatDate(d: string) {
  if (!d) return ''
  try { return new Date(d).toLocaleDateString('fr-FR') } catch { return d }
}
function mouvementLabel(t: string) {
  const map: Record<string, string> = { entree: 'Entrée', sortie: 'Sortie', ajustement: 'Ajustement', reception: 'Réception', commande: 'Commande' }
  return map[t] ?? t
}
function mouvementColor(t: string) {
  if (t === 'entree' || t === 'reception') return 'color:#10B981;'
  if (t === 'sortie' || t === 'commande') return 'color:#EF4444;'
  return 'color:#FBBF24;'
}

onMounted(() => stockStore.fetchPieces())
</script>
