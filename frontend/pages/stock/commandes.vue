<template>
  <div>
    <AppPageHeader title="Commandes fournisseurs">
      <template #actions>
        <UButton to="/stock/fournisseurs" icon="i-heroicons-plus">Nouvelle commande</UButton>
      </template>
    </AppPageHeader>

    <UCard class="mb-4">
      <div class="flex gap-2 flex-wrap">
        <UButton
          v-for="f in filters"
          :key="f.value"
          size="sm"
          :variant="filter === f.value ? 'solid' : 'ghost'"
          @click="filter = f.value; stockStore.fetchCommandes(f.value || undefined)"
        >
          {{ f.label }}
        </UButton>
      </div>
    </UCard>

    <UCard>
      <AppEmptyState
        v-if="!stockStore.loadingCommandes && !stockStore.commandes.length"
        icon="i-heroicons-clipboard-document-list"
        title="Aucune commande"
        description="Créez une commande depuis la page Fournisseurs."
      >
        <UButton to="/stock/fournisseurs" icon="i-heroicons-plus">Nouvelle commande</UButton>
      </AppEmptyState>
      <UTable
        v-else
        :data="stockStore.commandes"
        :columns="columns"
        :loading="stockStore.loadingCommandes"
      >
        <template #fournisseur-cell="{ row }">
          <span class="text-sm">{{ row.original.fournisseur?.nom ?? '—' }}</span>
        </template>
        <template #statut-cell="{ row }">
          <AppStatusBadge :variant="statusVariant(row.original.statut)">
            {{ statusLabel(row.original.statut) }}
          </AppStatusBadge>
        </template>
        <template #total_ttc-cell="{ row }">
          <span class="text-sm font-bold">{{ formatCurrency(row.original.total_ttc) }}</span>
        </template>
        <template #actions-cell="{ row }">
          <AppInlineActions>
            <UButton
              v-if="row.original.statut === 'en_attente'"
              size="xs"
              icon="i-heroicons-arrow-down-tray"
              @click="openReception(row.original)"
            >
              Réceptionner
            </UButton>
            <UButton
              v-if="row.original.statut === 'en_attente'"
              size="xs"
              variant="ghost"
              color="error"
              icon="i-heroicons-x-mark"
              @click="annulerCommande(row.original)"
            >
              Annuler
            </UButton>
            <span v-if="row.original.statut === 'recue'" class="text-sm text-emerald-400">
              Reçue le {{ formatDate(row.original.date_reception) }}
            </span>
            <span v-if="row.original.statut === 'annulee'" class="text-sm text-gray-500">
              Annulée
            </span>
          </AppInlineActions>
        </template>
      </UTable>
    </UCard>

    <AppModal v-model:open="showReception" size="lg">
      <UCard v-if="selectedCommande">
        <template #header>
          <h3 class="text-base font-bold text-text-primary">Réception {{ selectedCommande.numero_commande }}</h3>
        </template>
        <div class="flex flex-col gap-3">
          <div
            v-for="l in selectedCommande.lignes"
            :key="l.id"
            class="flex items-center justify-between gap-3 px-2.5 py-2.5 rounded-lg bg-white/5"
          >
            <div>
              <div class="text-sm font-bold">{{ l.piece?.nom ?? l.piece?.designation ?? 'Pièce #' + l.piece_id }}</div>
              <div class="text-sm text-gray-400">
                Commandée : {{ l.quantite_demandee }} — Déjà reçue : {{ l.quantite_recue ?? 0 }}
              </div>
            </div>
            <UFormField label="Qté reçue">
              <UInput
                v-model="receptionMap[l.id]"
                type="number"
                min="0"
                :max="l.quantite_demandee - (l.quantite_recue ?? 0)"
                class="w-24"
              />
            </UFormField>
          </div>
          <div class="flex justify-end gap-2">
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

const filters = [
  { value: '', label: 'Toutes' },
  { value: 'en_attente', label: 'En attente' },
  { value: 'recue', label: 'Reçues' },
]

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
function statusVariant(s: string) {
  if (s === 'recue') return 'success'
  if (s === 'en_attente') return 'warning'
  return 'default'
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
