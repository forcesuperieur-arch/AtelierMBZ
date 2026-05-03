<template>
  <div>
    <AppPageHeader title="Fournisseurs">
      <template #actions>
        <UButton icon="i-heroicons-plus" @click="showNew = true">Nouveau fournisseur</UButton>
      </template>
    </AppPageHeader>

    <UCard>
      <AppEmptyState
        v-if="!stockStore.loading && !stockStore.fournisseurs.length"
        icon="i-heroicons-truck"
        title="Aucun fournisseur"
        description="Ajoutez votre premier fournisseur de pièces détachées."
      >
        <UButton icon="i-heroicons-plus" @click="showNew = true">Créer un fournisseur</UButton>
      </AppEmptyState>
      <UTable
        v-else
        :data="stockStore.fournisseurs"
        :columns="columns"
        :loading="stockStore.loading"
      >
        <template #delai_livraison_jours-cell="{ row }">
          <span class="text-sm">{{ row.original.delai_livraison_jours ?? 3 }} j</span>
        </template>
        <template #actions-cell="{ row }">
          <AppInlineActions>
            <AppActionLink variant="primary" @click="selectFournisseur(row.original)">Commander →</AppActionLink>
          </AppInlineActions>
        </template>
      </UTable>
    </UCard>

    <AppModal v-model:open="showNew" size="lg">
      <UCard>
        <template #header>
          <h3 class="text-base font-bold text-text-primary">Nouveau fournisseur</h3>
        </template>
        <form @submit.prevent="saveFournisseur" class="flex flex-col gap-3">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <UFormField label="Nom"><UInput v-model="form.nom" required /></UFormField>
            <UFormField label="Contact"><UInput v-model="form.contact" /></UFormField>
            <UFormField label="Téléphone"><UInput v-model="form.telephone" /></UFormField>
            <UFormField label="Email"><UInput v-model="form.email" type="email" /></UFormField>
            <UFormField label="Délai livraison (j)"><UInput v-model="form.delai_livraison_jours" type="number" /></UFormField>
            <UFormField label="SIRET"><UInput v-model="form.siret" /></UFormField>
          </div>
          <UFormField label="Adresse"><UInput v-model="form.adresse" /></UFormField>
          <UFormField label="Notes"><UInput v-model="form.notes" /></UFormField>
          <div class="flex justify-end gap-2">
            <UButton label="Annuler" variant="outline" @click="showNew = false" />
            <UButton type="submit" label="Créer" :loading="saving" />
          </div>
        </form>
      </UCard>
    </AppModal>

    <AppModal v-model:open="showCommande" size="lg">
      <UCard v-if="selectedFournisseur">
        <template #header>
          <h3 class="text-base font-bold text-text-primary">Commande {{ selectedFournisseur.nom }}</h3>
        </template>
        <form @submit.prevent="saveCommande" class="flex flex-col gap-3">
          <div
            v-for="(l, i) in lignes"
            :key="i"
            class="grid grid-cols-1 md:grid-cols-[2fr_1fr_1fr_auto] gap-2 items-end"
          >
            <UFormField :label="i === 0 ? 'Pièce' : ''">
              <USelectMenu v-model="l.piece_id" :options="pieceOptions" placeholder="Choisir..." />
            </UFormField>
            <UFormField :label="i === 0 ? 'Qté' : ''"><UInput v-model="l.quantite" type="number" min="1" /></UFormField>
            <UFormField :label="i === 0 ? 'Prix U. HT' : ''"><UInput v-model="l.prix_unitaire_ht" type="number" step="0.01" /></UFormField>
            <UButton
              type="button"
              variant="ghost"
              color="error"
              icon="i-heroicons-x-mark"
              class="mb-1"
              @click="lignes.splice(i, 1)"
            />
          </div>
          <UButton
            type="button"
            variant="ghost"
            icon="i-heroicons-plus"
            class="self-start"
            @click="lignes.push({ piece_id: null, quantite: 1, prix_unitaire_ht: 0 })"
          >
            Ajouter une ligne
          </UButton>
          <UFormField label="Date prévue livraison"><UInput v-model="commandeForm.date_prevue_livraison" type="date" /></UFormField>
          <UFormField label="Notes"><UInput v-model="commandeForm.notes" /></UFormField>
          <div class="flex justify-end gap-2">
            <UButton label="Annuler" variant="outline" @click="showCommande = false" />
            <UButton type="submit" label="Commander" :loading="savingCommande" />
          </div>
        </form>
      </UCard>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ title: 'Fournisseurs' })
const stockStore = useStockStore()
const toast = useToast()
const router = useRouter()
const showNew = ref(false)
const saving = ref(false)
const showCommande = ref(false)
const savingCommande = ref(false)
const selectedFournisseur = ref<any>(null)

const form = reactive({ nom: '', contact: '', telephone: '', email: '', adresse: '', siret: '', delai_livraison_jours: 3, notes: '' })
const commandeForm = reactive({ date_prevue_livraison: '', notes: '' })
const lignes = ref([{ piece_id: null as any, quantite: 1, prix_unitaire_ht: 0 }])

const columns = [
  { key: 'nom', label: 'Nom' },
  { key: 'contact', label: 'Contact' },
  { key: 'telephone', label: 'Téléphone' },
  { key: 'email', label: 'Email' },
  { key: 'delai_livraison_jours', label: 'Délai' },
  { key: 'actions', label: '' },
]

const pieceOptions = computed(() => stockStore.pieces.map(p => ({ label: `${p.reference} — ${p.designation}`, value: p.id })))

onMounted(() => {
  stockStore.fetchFournisseurs()
  stockStore.fetchPieces()
})

async function saveFournisseur() {
  saving.value = true
  try {
    await stockStore.createFournisseur({ ...form })
    toast.add({ title: 'Fournisseur créé', color: 'success' })
    showNew.value = false
    Object.assign(form, { nom: '', contact: '', telephone: '', email: '', adresse: '', siret: '', delai_livraison_jours: 3, notes: '' })
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
  } finally {
    saving.value = false
  }
}

function selectFournisseur(f: any) {
  selectedFournisseur.value = f
  lignes.value = [{ piece_id: null, quantite: 1, prix_unitaire_ht: 0 }]
  showCommande.value = true
}

async function saveCommande() {
  savingCommande.value = true
  try {
    await stockStore.createCommande({
      fournisseur_id: selectedFournisseur.value.id,
      lignes: lignes.value.filter(l => l.piece_id && l.quantite > 0).map(l => ({ piece_id: l.piece_id, quantite: Number(l.quantite), prix_unitaire_ht: String(l.prix_unitaire_ht) })),
      date_prevue_livraison: commandeForm.date_prevue_livraison || null,
      notes: commandeForm.notes || null,
    })
    toast.add({ title: 'Commande créée', color: 'success' })
    showCommande.value = false
    router.push('/stock/commandes')
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
  } finally {
    savingCommande.value = false
  }
}
</script>
