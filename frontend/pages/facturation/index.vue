<template>
  <div>
    <div class="page-header">
      <div class="page-title">Facturation</div>
    </div>

    <UCard style="margin-bottom:16px;">
      <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
        <UFormField label="Statut">
          <USelect v-model="filter" :options="statusOptions" />
        </UFormField>
        <UFormField label="Recherche">
          <UInput v-model="search" placeholder="N° facture, client..." />
        </UFormField>
      </div>
    </UCard>

    <UCard>
      <UTable :data="filtered" :columns="columns" :loading="loading">
        <template #statut-cell="{ row }">
          <StatusBadge :status="row.original.statut === 'payee' ? 'paye' : row.original.statut === 'partielle' ? 'en_cours' : row.original.statut === 'annulee' ? 'a_regulariser' : row.original.statut" />
        </template>
        <template #total_ttc-cell="{ row }">
          {{ formatCurrency(row.original.total_ttc) }}
        </template>
        <template #reste-cell="{ row }">
          <span :style="{ color: row.original.reste_a_payer > 0 ? '#FCA5A5' : '#6EE7B7', fontWeight: '600' }">
            {{ formatCurrency(row.original.reste_a_payer ?? row.original.total_ttc) }}
          </span>
        </template>
        <template #actions-cell="{ row }">
          <div style="display:flex;gap:8px;">
            <button style="color:#FFD200;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="billingStore.downloadPdf(row.original.id)">📄 PDF</button>
            <button v-if="row.original.statut !== 'payee' && row.original.statut !== 'annulee'" style="color:#6EE7B7;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="openEncaissement(row.original)">💶 Encaisser</button>
            <button style="color:#93C5FD;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="sendFactureEmail(row.original)">📧 Email</button>
          </div>
        </template>
      </UTable>
    </UCard>

    <!-- Encaissement Modal -->
    <AppModal v-model:open="showEncaissement" size="lg">
      <template #content>
        <UCard>
          <template #header>
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <span style="font-weight:600;">💶 Encaissement — {{ selectedFacture?.numero_facture }}</span>
              <button @click="showEncaissement = false" style="background:none;border:none;color:#9CA3AF;font-size:18px;cursor:pointer;">✕</button>
            </div>
          </template>

          <div v-if="selectedFacture" style="display:flex;flex-direction:column;gap:16px;">
            <!-- Résumé facture -->
            <div style="display:flex;justify-content:space-between;padding:12px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:10px;">
              <div>
                <div style="font-size:12px;color:#6B7280;">Total TTC</div>
                <div style="font-size:18px;font-weight:700;color:#FFD200;">{{ formatCurrency(selectedFacture.total_ttc) }}</div>
              </div>
              <div v-if="selectedFacture.montant_paye > 0">
                <div style="font-size:12px;color:#6B7280;">Déjà payé</div>
                <div style="font-size:18px;font-weight:700;color:#6EE7B7;">{{ formatCurrency(selectedFacture.montant_paye) }}</div>
              </div>
              <div>
                <div style="font-size:12px;color:#6B7280;">Reste à payer</div>
                <div style="font-size:18px;font-weight:700;color:#FCA5A5;">{{ formatCurrency(resteAPayer) }}</div>
              </div>
            </div>

            <!-- Mode de paiement -->
            <div class="form-group">
              <label class="form-label">Mode de paiement</label>
              <select v-model="paiement.mode" class="form-input">
                <option value="carte_bancaire">Carte bancaire</option>
                <option value="especes">Espèces</option>
                <option value="cheque">Chèque</option>
                <option value="virement">Virement bancaire</option>
                <option value="differe">Paiement différé</option>
              </select>
            </div>

            <!-- Référence (chèque/virement) -->
            <div v-if="['cheque', 'virement'].includes(paiement.mode)" class="form-group">
              <label class="form-label">Référence</label>
              <input v-model="paiement.reference" class="form-input" placeholder="N° de chèque ou référence virement" />
            </div>

            <!-- Montant -->
            <div class="form-group">
              <label class="form-label">Montant (€)</label>
              <input v-model.number="paiement.montant" type="number" step="0.01" class="form-input" :max="resteAPayer" />
            </div>

            <!-- Notes -->
            <div class="form-group">
              <label class="form-label">Notes</label>
              <input v-model="paiement.notes" class="form-input" placeholder="Optionnel" />
            </div>
          </div>

          <template #footer>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
              <button class="btn btn-ghost" @click="showEncaissement = false">Annuler</button>
              <button class="btn btn-primary" style="background:#10B981 !important;border-color:#10B981 !important;" @click="submitPaiement" :disabled="paying">
                {{ paying ? 'Enregistrement…' : 'Enregistrer le paiement' }}
              </button>
            </div>
          </template>
        </UCard>
      </template>
    </AppModal>

    <!-- Invoice Preview Modal -->
    <AppModal v-model:open="showPreview" size="xl">
      <template #content>
        <UCard style="max-width:700px;">
          <template #header>
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <span style="font-weight:600;">📋 Aperçu facture</span>
              <button @click="showPreview = false" style="background:none;border:none;color:#9CA3AF;font-size:18px;cursor:pointer;">✕</button>
            </div>
          </template>

          <div v-if="previewData" style="display:flex;flex-direction:column;gap:16px;">
            <!-- Client info -->
            <div style="display:flex;justify-content:space-between;padding:12px;background:rgba(255,255,255,0.03);border-radius:10px;border:1px solid rgba(255,255,255,0.06);">
              <div><div style="font-size:12px;color:#6B7280;">Client</div><div style="font-size:14px;font-weight:600;color:#E8E9ED;">{{ previewData.client_nom }}</div></div>
              <div><div style="font-size:12px;color:#6B7280;">Véhicule</div><div style="font-size:14px;font-weight:600;color:#E8E9ED;">{{ previewData.vehicule_info }}</div></div>
            </div>

            <!-- MO section -->
            <div v-if="previewData.lignes_mo?.length">
              <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#F59E0B;margin-bottom:6px;">Main d'œuvre</div>
              <div v-for="(l, i) in previewData.lignes_mo" :key="'mo'+i" style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:13px;">
                <span style="color:#D1D5DB;">{{ l.label }}</span>
                <span style="color:#FFD200;font-weight:600;">{{ formatCurrency(l.montant_ht) }} HT</span>
              </div>
            </div>

            <!-- Pièces section -->
            <div v-if="previewData.lignes_pieces?.length">
              <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#14B8A6;margin-bottom:6px;">Pièces</div>
              <div v-for="(l, i) in previewData.lignes_pieces" :key="'p'+i" style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:13px;">
                <span style="color:#D1D5DB;">{{ l.label }} {{ l.ref ? `(${l.ref})` : '' }} x{{ l.qty }}</span>
                <span style="color:#FFD200;font-weight:600;">{{ formatCurrency(l.montant_ht) }} HT</span>
              </div>
            </div>

            <!-- Remise slider -->
            <div style="padding:12px;background:rgba(255,255,255,0.03);border-radius:10px;border:1px solid rgba(255,255,255,0.06);">
              <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                <label style="font-size:12px;color:#9CA3AF;">Remise (%)</label>
                <span style="font-size:14px;font-weight:700;color:#10B981;">{{ previewRemise }}%</span>
              </div>
              <input type="range" v-model.number="previewRemise" min="0" max="100" style="width:100%;accent-color:#FFD200;" />
            </div>

            <!-- Totals -->
            <div style="padding:14px;background:var(--dark3,#171B24);border-radius:12px;display:grid;gap:8px;font-size:13px;">
              <div style="display:flex;justify-content:space-between;"><span style="color:#9CA3AF;">Total HT</span><span style="color:#E8E9ED;font-weight:600;">{{ formatCurrency(previewTotalHT) }}</span></div>
              <div v-if="previewRemise > 0" style="display:flex;justify-content:space-between;"><span style="color:#10B981;">Remise {{ previewRemise }}%</span><span style="color:#10B981;font-weight:600;">-{{ formatCurrency(previewTotalHT * previewRemise / 100) }}</span></div>
              <div v-if="previewRemise > 0" style="display:flex;justify-content:space-between;"><span style="color:#9CA3AF;">Total HT après remise</span><span style="color:#E8E9ED;font-weight:600;">{{ formatCurrency(previewHTAfterRemise) }}</span></div>
              <div style="display:flex;justify-content:space-between;"><span style="color:#9CA3AF;">TVA MO ({{ previewData.tva_mo_taux || 20 }}%)</span><span style="color:#E8E9ED;">{{ formatCurrency(previewTvaMO) }}</span></div>
              <div v-if="previewTvaPieces > 0" style="display:flex;justify-content:space-between;"><span style="color:#9CA3AF;">TVA Pièces ({{ previewData.tva_pieces_taux || 20 }}%)</span><span style="color:#E8E9ED;">{{ formatCurrency(previewTvaPieces) }}</span></div>
              <div style="border-top:2px solid rgba(255,255,255,0.08);padding-top:8px;display:flex;justify-content:space-between;">
                <span style="font-weight:700;color:#E8E9ED;font-size:15px;">TOTAL TTC</span>
                <span style="font-weight:900;color:#FFD200;font-size:18px;">{{ formatCurrency(previewTotalTTC) }}</span>
              </div>
            </div>

            <!-- Notes -->
            <div class="form-group">
              <label class="form-label">Notes</label>
              <textarea v-model="previewNotes" class="form-input" rows="2" placeholder="Notes facture (optionnel)"></textarea>
            </div>
          </div>

          <template #footer>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
              <button class="btn btn-ghost" @click="showPreview = false">Annuler</button>
              <button class="btn btn-primary" @click="generateInvoice" :disabled="generatingInvoice">
                {{ generatingInvoice ? 'Génération…' : '✅ Générer la facture' }}
              </button>
            </div>
          </template>
        </UCard>
      </template>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
const billingStore = useBillingStore()
const api = useApi()
const toast = useToast()
const loading = ref(true)
const filter = ref('')
const search = ref('')
const showEncaissement = ref(false)
const paying = ref(false)
const selectedFacture = ref<any>(null)

const paiement = reactive({
  mode: 'carte_bancaire',
  montant: 0,
  reference: '',
  notes: '',
})

const resteAPayer = computed(() => {
  if (!selectedFacture.value) return 0
  return (selectedFacture.value.total_ttc || 0) - (selectedFacture.value.montant_paye || 0)
})

const statusOptions = [
  { value: '', label: 'Toutes' },
  { value: 'brouillon', label: 'Brouillon' },
  { value: 'emise', label: 'Émise' },
  { value: 'partielle', label: 'Partielle' },
  { value: 'payee', label: 'Payée' },
]

const columns = [
  { key: 'numero_facture', label: 'N°' },
  { key: 'date_creation', label: 'Date' },
  { key: 'client_nom', label: 'Client' },
  { key: 'total_ttc', label: 'Total TTC' },
  { key: 'reste', label: 'Reste à payer' },
  { key: 'statut', label: 'Statut' },
  { key: 'actions', label: '' },
]

const filtered = computed(() => {
  let list = billingStore.factures
  if (filter.value) list = list.filter(f => f.statut === filter.value)
  if (search.value) {
    const s = search.value.toLowerCase()
    list = list.filter(f =>
      f.numero_facture?.toLowerCase().includes(s) ||
      f.client_nom?.toLowerCase().includes(s)
    )
  }
  return list
})

function formatCurrency(v: number) {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(v || 0)
}

function openEncaissement(facture: any) {
  selectedFacture.value = facture
  paiement.mode = 'carte_bancaire'
  paiement.montant = (facture.total_ttc || 0) - (facture.montant_paye || 0)
  paiement.reference = ''
  paiement.notes = ''
  showEncaissement.value = true
}

async function submitPaiement() {
  if (!selectedFacture.value || paiement.montant <= 0) return
  paying.value = true
  try {
    await api.post(`/facturation/${selectedFacture.value.id}/paiement`, {
      mode_paiement: paiement.mode,
      montant: paiement.montant,
      reference: paiement.reference || undefined,
      notes: paiement.notes || undefined,
    })
    toast.add({ title: 'Paiement enregistré', color: 'success' })
    showEncaissement.value = false
    await billingStore.fetchFactures()
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e?.message || 'Échec', color: 'error' })
  } finally {
    paying.value = false
  }
}

// Invoice Preview
const showPreview = ref(false)
const previewData = ref<any>(null)
const previewRemise = ref(0)
const previewNotes = ref('')
const generatingInvoice = ref(false)

const previewTotalHT = computed(() => {
  if (!previewData.value) return 0
  const mo = (previewData.value.lignes_mo || []).reduce((s: number, l: any) => s + (Number(l.montant_ht) || 0), 0)
  const pieces = (previewData.value.lignes_pieces || []).reduce((s: number, l: any) => s + (Number(l.montant_ht) || 0), 0)
  return mo + pieces
})

const previewHTAfterRemise = computed(() => previewTotalHT.value * (1 - previewRemise.value / 100))

const previewMOHT = computed(() => {
  if (!previewData.value) return 0
  return (previewData.value.lignes_mo || []).reduce((s: number, l: any) => s + (Number(l.montant_ht) || 0), 0) * (1 - previewRemise.value / 100)
})

const previewPiecesHT = computed(() => {
  if (!previewData.value) return 0
  return (previewData.value.lignes_pieces || []).reduce((s: number, l: any) => s + (Number(l.montant_ht) || 0), 0) * (1 - previewRemise.value / 100)
})

const previewTvaMO = computed(() => previewMOHT.value * (previewData.value?.tva_mo_taux || 20) / 100)
const previewTvaPieces = computed(() => previewPiecesHT.value * (previewData.value?.tva_pieces_taux || 20) / 100)
const previewTotalTTC = computed(() => previewHTAfterRemise.value + previewTvaMO.value + previewTvaPieces.value)

async function openPreviewFacture(rdvId: number) {
  try {
    const data = await api.get(`/rendez-vous/${rdvId}/preview-facture`)
    previewData.value = data
    previewRemise.value = data.remise || 0
    previewNotes.value = ''
    showPreview.value = true
  } catch (e: any) {
    toast.add({ title: 'Erreur aperçu', description: e.message, color: 'error' })
  }
}

async function generateInvoice() {
  if (!previewData.value?.rdv_id) return
  generatingInvoice.value = true
  try {
    await api.post(`/rendez-vous/${previewData.value.rdv_id}/facturer`, {
      remise_pourcent: previewRemise.value,
      notes: previewNotes.value || undefined,
    })
    toast.add({ title: 'Facture générée', color: 'success' })
    showPreview.value = false
    await billingStore.fetchFactures()
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  } finally {
    generatingInvoice.value = false
  }
}

async function sendFactureEmail(facture: any) {
  try {
    await api.post(`/facturation/${facture.id}/email`, {})
    toast.add({ title: 'Facture envoyée par email', color: 'success' })
  } catch (e: any) {
    toast.add({ title: 'Erreur envoi email', description: e?.message || 'Vérifiez l\'adresse email du client', color: 'error' })
  }
}

// Expose for external use
defineExpose({ openPreviewFacture })

onMounted(async () => {
  try {
    await billingStore.fetchFactures()
  } finally {
    loading.value = false
  }
})
</script>
