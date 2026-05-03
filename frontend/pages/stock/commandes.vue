<template>
  <div>
    <AppPageHeader title="Commandes fournisseurs">
      <template #actions>
        <NuxtLink to="/stock/fournisseurs" class="topbar-new-btn">+ Nouvelle commande</NuxtLink>
      </template>
    </AppPageHeader>

    <UCard style="margin-bottom:16px;">
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <button class="btn" :class="filter === '' ? 'btn-primary' : 'btn-ghost'" style="font-size:12px;" @click="filter = ''; stockStore.fetchCommandes()">Toutes</button>
        <button class="btn" :class="filter === 'en_attente' ? 'btn-primary' : 'btn-ghost'" style="font-size:12px;" @click="filter = 'en_attente'; stockStore.fetchCommandes('en_attente')">En attente</button>
        <button class="btn" :class="filter === 'recue' ? 'btn-primary' : 'btn-ghost'" style="font-size:12px;" @click="filter = 'recue'; stockStore.fetchCommandes('recue')">Reçues</button>
      </div>
    </UCard>

    <UCard>
      <UTable :data="stockStore.commandes" :columns="columns" :loading="stockStore.loadingCommandes">
        <template #fournisseur-cell="{ row }">
          <span class="text-sm">{{ row.original.fournisseur?.nom ?? '—' }}</span>
        </template>
        <template #statut-cell="{ row }">
          <span class="status-badge" :style="statusStyle(row.original.statut)">{{ statusLabel(row.original.statut) }}</span>
        </template>
        <template #total_ttc-cell="{ row }">
          <span class="text-sm font-bold">{{ formatCurrency(row.original.total_ttc) }}</span>
        </template>
        <template #actions-cell="{ row }">
          <div style="display:flex;gap:6px;">
            <button v-if="row.original.statut === 'en_attente'" class="btn btn-primary" style="font-size:12px;padding:4px 10px;" @click="openReception(row.original)">
              📥 Réceptionner
            </button>
            <button v-if="row.original.statut === 'en_attente'" class="btn btn-ghost" style="font-size:12px;padding:4px 10px;color:#FCA5A5;" @click="annulerCommande(row.original)">
              ✕ Annuler
            </button>
            <span v-if="row.original.statut === 'recue'" class="text-sm" style="color:#10B981;">✅ Reçue le {{ formatDate(row.original.date_reception) }}</span>
            <span v-if="row.original.statut === 'annulee'" class="text-sm" style="color:#9CA3AF;">❌ Annulée</span>
          </div>
        </template>
      </UTable>
    </UCard>

    <AppModal v-model:open="showReception" size="lg">
      <UCard v-if="selectedCommande">
        <template #header><span style="font-size:15px;font-weight:700;color:#E8E9ED;">Réception {{ selectedCommande.numero_commande }}</span></template>
        <div style="display:flex;flex-direction:column;gap:12px;">
          <div v-for="l in selectedCommande.lignes" :key="l.id" style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px;border-radius:8px;background:rgba(255,255,255,0.04);">
            <div>
              <div class="text-sm font-bold">{{ l.piece?.nom ?? l.piece?.designation ?? 'Pièce #' + l.piece_id }}</div>
              <div class="text-sm" style="color:#9CA3AF;">Commandée : {{ l.quantite_demandee }} — Déjà reçue : {{ l.quantite_recue ?? 0 }}</div>
            </div>
            <UFormField label="Qté reçue">
              <UInput v-model="receptionMap[l.id]" type="number" min="0" :max="l.quantite_demandee - (l.quantite_recue ?? 0)" style="width:100px;" />
            </UFormField>
          </div>
          <div style="display:flex;justify-content:flex-end;gap:8px;">
            <UButton label="Annuler" variant="outline" @click="showReception = false" />
            <UButton label="Valider la réception" :loading="savingReception" @click="saveReception" />
          </div>
        </div>
      </UCard>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ title: 'Commandes fournisseurs' })
const stockStore = useStockStore()
const toast = useToast()
const filter = ref('')
const showReception = ref(false)
const savingReception = ref(false)
const selectedCommande = ref<any>(null)
const receptionMap = reactive<Record<number, number>>({})

const columns = [
  { key: 'numero_commande', label: 'N°' },
  { key: 'fournisseur', label: 'Fournisseur' },
  { key: 'date_commande', label: 'Date' },
  { key: 'statut', label: 'Statut' },
  { key: 'total_ttc', label: 'Total TTC' },
  { key: 'actions', label: '' },
]

function statusLabel(s: string) {
  return { en_attente: 'En attente', recue: 'Reçue', annulee: 'Annulée' }[s] ?? s
}
function statusStyle(s: string) {
  if (s === 'recue') return 'background:rgba(16,185,129,0.15);color:#10B981;'
  if (s === 'en_attente') return 'background:rgba(251,191,36,0.15);color:#FBBF24;'
  return 'background:rgba(255,255,255,0.06);color:#9CA3AF;'
}
function formatCurrency(v: number) {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(v || 0)
}
function formatDate(d: string) {
  if (!d) return ''
  try { return new Date(d).toLocaleDateString('fr-FR') } catch { return d }
}

onMounted(() => stockStore.fetchCommandes())

function openReception(cmd: any) {
  selectedCommande.value = cmd
  Object.keys(receptionMap).forEach(k => delete receptionMap[+k])
  cmd.lignes?.forEach((l: any) => { receptionMap[l.id] = 0 })
  showReception.value = true
}

async function saveReception() {
  savingReception.value = true
  try {
    const lignes = selectedCommande.value.lignes
      .filter((l: any) => (receptionMap[l.id] || 0) > 0)
      .map((l: any) => ({ ligne_id: l.id, quantite_recue: Number(receptionMap[l.id]) }))

    if (lignes.length === 0) {
      toast.add({ title: 'Aucune quantité à réceptionner', color: 'warning' })
      return
    }

    await stockStore.receiveCommande(selectedCommande.value.id, lignes)
    toast.add({ title: 'Réception enregistrée', color: 'success' })
    showReception.value = false
  } catch (e: unknown) {
    toast.add({ title: 'Erreur réception', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
  } finally {
    savingReception.value = false
  }
}

async function annulerCommande(cmd: any) {
  if (!confirm(`Annuler la commande ${cmd.numero_commande} ?`)) return
  try {
    const api = useApi()
    await api.post(`/stock/commandes/${cmd.id}/annuler`)
    toast.add({ title: 'Commande annulée', color: 'success' })
    await stockStore.fetchCommandes()
  } catch (e: unknown) {
    toast.add({ title: 'Erreur annulation', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
  }
}
</script>
