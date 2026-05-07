<template>
  <div>
    <AppPageHeader title="Stock — Pièces détachées">
      <template #actions>
        <div class="flex gap-2 flex-wrap">
          <UButton variant="ghost" icon="i-heroicons-arrow-down-tray" @click="exportCsv">
            Export CSV
          </UButton>
          <UButton to="/stock/fournisseurs" variant="ghost" icon="i-heroicons-truck">
            Fournisseurs
          </UButton>
          <UButton to="/stock/commandes" variant="ghost" icon="i-heroicons-clipboard-document-list">
            Commandes
          </UButton>
          <UButton icon="i-heroicons-plus" @click="resetForm(); showNew = true">
            Nouvelle pièce
          </UButton>
        </div>
      </template>
    </AppPageHeader>

    <!-- KPIs -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 mb-4">
      <AppKpiCard label="Références" :value="totalReferences" />
      <AppKpiCard label="Valeur stock (achat)" :value="formatCurrency(totalStockValue)" />
      <AppKpiCard label="Alertes" :value="alertesCount" variant="danger" />
      <AppKpiCard label="Cmds en attente" :value="commandesEnAttente" variant="warning" />
      <AppKpiCard label="Mouvements aujourd'hui" :value="mouvementsAujourdhui" variant="success" />
    </div>

    <!-- Alerts -->
    <AppAlertCard v-if="stockStore.alertes.length">
      <template #title>Alertes stock ({{ stockStore.alertes.length }})</template>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
        <div
          v-for="p in stockStore.alertes"
          :key="p.id"
          class="flex items-center justify-between text-sm px-3 py-2 rounded-lg border border-red-500/15 bg-red-500/5"
        >
          <span class="text-gray-300">{{ p.designation }}</span>
          <AppStatusBadge variant="danger">{{ p.quantite_stock }} / {{ p.seuil_alerte }}</AppStatusBadge>
        </div>
      </div>
    </AppAlertCard>

    <!-- Filters -->
    <UCard class="mb-4">
      <div class="flex gap-3 flex-wrap items-end">
        <UInput
          v-model="search"
          placeholder="Rechercher une pièce..."
          icon="i-heroicons-magnifying-glass"
          class="flex-1 min-w-[200px]"
          @input="debouncedFetch"
        />
        <USelectMenu
          v-model="categorieFilter"
          :options="categories"
          placeholder="Toutes catégories"
          clearable
          class="min-w-[180px]"
        />
      </div>
    </UCard>

    <!-- Table -->
    <UCard>
      <AppEmptyState
        v-if="!stockStore.loading && !filteredPieces.length"
        icon="i-heroicons-cube"
        title="Aucune pièce"
        description="Commencez par créer une nouvelle pièce détachée."
      >
        <UButton icon="i-heroicons-plus" @click="resetForm(); showNew = true">Créer une pièce</UButton>
      </AppEmptyState>
      <UTable
        v-else
        :data="filteredPieces"
        :columns="columns"
        :loading="stockStore.loading"
      >
        <template #quantite_stock-cell="{ row }">
          <span :class="row.original.quantite_stock <= row.original.seuil_alerte ? 'text-red-400 font-bold' : ''">
            {{ row.original.quantite_stock }}
          </span>
        </template>
        <template #prix_vente_ht-cell="{ row }">
          <span class="text-sm">{{ formatCurrency(row.original.prix_vente_ht) }}</span>
        </template>
        <template #actions-cell="{ row }">
          <AppInlineActions>
            <AppActionLink variant="primary" @click="editPiece(row.original)">Modifier</AppActionLink>
            <AppActionLink variant="secondary" @click="openMouvements(row.original)">Mouvements</AppActionLink>
            <AppActionLink variant="muted" @click="togglePiece(row.original)">
              {{ row.original.is_active === false ? 'Activer' : 'Désactiver' }}
            </AppActionLink>
          </AppInlineActions>
        </template>
      </UTable>
    </UCard>

    <!-- New/Edit modal -->
    <AppModal v-model:open="showNew" size="lg">
      <template #default>
        <UCard>
          <template #header>
            <h3 class="text-base font-bold text-text-primary">{{ editId ? 'Modifier' : 'Nouvelle' }} pièce</h3>
          </template>
          <form @submit.prevent="savePiece" class="flex flex-col gap-3">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
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
            <div class="flex justify-end gap-2">
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
          <h3 class="text-base font-bold text-text-primary">Mouvements — {{ selectedPiece.designation }}</h3>
        </template>
        <AppLoadingState v-if="stockStore.loadingMouvements" title="Chargement des mouvements…" />
        <AppEmptyState
          v-else-if="!stockStore.mouvements.length"
          icon="i-heroicons-arrows-right-left"
          title="Aucun mouvement"
          description="Cette pièce n'a pas encore d'historique de mouvements."
        />
        <div v-else class="flex flex-col gap-2">
          <div
            v-for="m in stockStore.mouvements"
            :key="m.id"
            class="flex justify-between items-center px-2.5 py-2 rounded-lg bg-white/5"
          >
            <div>
              <span class="text-sm font-bold" :class="mouvementColorClass(m.type)">{{ mouvementLabel(m.type) }}</span>
              <span class="text-sm text-gray-400 ml-1.5">{{ m.quantite }} unité(s)</span>
              <div v-if="m.motif" class="text-sm text-gray-500 mt-0.5">{{ m.motif }}</div>
            </div>
            <span class="text-sm text-gray-500">{{ formatDate(m.created_at) }}</span>
          </div>
        </div>
        <div class="mt-3 flex justify-end gap-2">
          <UButton label="Ajustement manuel" @click="openAjustement" />
          <UButton label="Fermer" variant="outline" @click="showMouvements = false" />
        </div>
      </UCard>
    </AppModal>

    <!-- Ajustement modal -->
    <AppModal v-model:open="showAjustement" size="md">
      <UCard v-if="selectedPiece">
        <template #header>
          <h3 class="text-base font-bold text-text-primary">Ajustement — {{ selectedPiece.designation }}</h3>
        </template>
        <form @submit.prevent="saveAjustement" class="flex flex-col gap-3">
          <p class="text-sm text-gray-400">
            Stock actuel : <strong class="text-text-primary">{{ selectedPiece.quantite_stock }}</strong>
          </p>
          <UFormField label="Nouvelle quantité"><UInput v-model="ajustementForm.quantite" type="number" required /></UFormField>
          <UFormField label="Motif"><UInput v-model="ajustementForm.motif" required placeholder="Inventaire, casse, correction…" /></UFormField>
          <div class="flex justify-end gap-2">
            <UButton label="Annuler" variant="outline" @click="showAjustement = false" />
            <UButton type="submit" label="Valider" :loading="savingAjustement" />
          </div>
        </form>
      </UCard>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ title: 'Stock' })
const stockStore = useStockStore()
const toast = useToast()
const search = ref('')
const categorieFilter = ref('')
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

const stats = ref<any>(null)

const totalStockValue = computed(() => stats.value?.valeur_achat ?? 0)
const totalReferences = computed(() => stats.value?.total_references ?? stockStore.pieces.length)
const alertesCount = computed(() => stats.value?.alertes ?? stockStore.alertes.length)
const commandesEnAttente = computed(() => stats.value?.commandes_en_attente ?? 0)
const mouvementsAujourdhui = computed(() => stats.value?.mouvements_aujourdhui ?? 0)

const categories = computed(() => {
  const set = new Set<string>()
  stockStore.pieces.forEach((p: any) => { if (p.categorie) set.add(p.categorie) })
  return Array.from(set).sort()
})

const filteredPieces = computed(() => {
  if (!categorieFilter.value) return stockStore.pieces
  return stockStore.pieces.filter((p: any) => p.categorie === categorieFilter.value)
})

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

const debouncedFetch = useDebounceFn(() => stockStore.fetchPieces(search.value), 300)

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

async function togglePiece(p: any) {
  try {
    await stockStore.togglePiece(p.id)
    toast.add({ title: p.is_active === false ? 'Pièce activée' : 'Pièce désactivée', color: 'success' })
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
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
function mouvementColorClass(t: string) {
  if (t === 'entree' || t === 'reception') return 'text-emerald-400'
  if (t === 'sortie' || t === 'commande') return 'text-red-400'
  return 'text-amber-400'
}

function exportCsv() {
  const pieces = stockStore.pieces
  const headers = ['Référence', 'Désignation', 'Catégorie', 'Stock', 'Seuil', 'Prix achat HT', 'Prix vente HT', 'Emplacement']
  const rows = pieces.map(p => [
    p.reference,
    p.designation,
    p.categorie || '',
    p.quantite_stock,
    p.seuil_alerte,
    p.prix_achat_ht,
    p.prix_vente_ht,
    p.emplacement || '',
  ])
  const csv = [headers, ...rows].map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(';')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `inventaire-stock-${new Date().toISOString().slice(0, 10)}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

onMounted(async () => {
  await stockStore.fetchPieces()
  stats.value = await stockStore.fetchStats()
})
</script>
