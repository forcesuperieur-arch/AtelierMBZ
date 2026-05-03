<template>
  <div>
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
      <div class="page-title">Facturation</div>
      <button class="topbar-new-btn" style="font-size:12px;padding:6px 12px;" @click="exportFec">📊 Export FEC</button>
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
          <StatusBadge :status="billingStatus(row.original)" />
        </template>
        <template #total_ttc-cell="{ row }">
          {{ formatCurrency(displayTotal(row.original)) }}
        </template>
        <template #reste-cell="{ row }">
          <span :style="{ color: parseAmount(row.original.reste_a_payer) > 0 ? '#FCA5A5' : '#6EE7B7', fontWeight: '600' }">
            {{ formatCurrency(parseAmount(row.original.reste_a_payer)) }}
          </span>
        </template>
        <template #actions-cell="{ row }">
          <div style="display:flex;gap:8px;">
            <button style="color:#FFD200;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="billingStore.downloadPdf(row.original.id)">📄 PDF</button>
            <button v-if="canManageBilling && canCollectPayment(row.original)" style="color:#6EE7B7;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="openEncaissement(row.original)">💶 Encaisser</button>
            <button v-if="canManageBilling && canRefundPayment(row.original)" style="color:#60A5FA;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="openRemboursement(row.original)">↪ Rembourser</button>
            <button v-if="canManageBilling && canIssueAvoir(row.original)" style="color:#FCA5A5;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="openAvoir(row.original)">↩ Avoir</button>
            <button style="color:#C4B5FD;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="openJournal(row.original)">📚 Journal</button>
            <button v-if="canManageBilling" style="color:#93C5FD;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="sendFactureEmail(row.original)">📧 Email</button>
          </div>
        </template>
      </UTable>
    </UCard>

    <AppModal v-model:open="showAvoir" size="lg">
      <template #content>
        <UCard>
          <template #header>
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <span style="font-weight:600;">↩ Émettre un avoir — {{ selectedFacture?.numero_facture }}</span>
              <button @click="showAvoir = false" style="background:none;border:none;color:#9CA3AF;font-size:18px;cursor:pointer;">✕</button>
            </div>
          </template>

          <div style="display:flex;flex-direction:column;gap:12px;">
            <div style="font-size:13px;color:#9CA3AF;">Cet avoir corrigera la facture d'origine. La facture source passera en statut corrigé et ne sera plus encaissable.</div>
            <div class="form-group">
              <label class="form-label">Motif de l'avoir</label>
              <textarea v-model="avoirMotif" class="form-input" rows="3" placeholder="Erreur de facturation, geste commercial, annulation par avoir..." />
            </div>
          </div>

          <template #footer>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
              <button class="btn btn-ghost" @click="showAvoir = false">Annuler</button>
              <button class="btn btn-primary" style="background:#EF4444 !important;border-color:#EF4444 !important;" @click="submitAvoir" :disabled="crediting">
                {{ crediting ? 'Émission…' : 'Émettre l\'avoir' }}
              </button>
            </div>
          </template>
        </UCard>
      </template>
    </AppModal>

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
                <div style="font-size:12px;color:#6B7280;">{{ moneyActionType === 'remboursement' ? 'Total à rembourser' : 'Total TTC' }}</div>
                <div style="font-size:18px;font-weight:700;color:#FFD200;">{{ formatCurrency(displayTotal(selectedFacture)) }}</div>
              </div>
              <div v-if="parseAmount(selectedFacture.montant_paye) > 0">
                <div style="font-size:12px;color:#6B7280;">{{ moneyActionType === 'remboursement' ? 'Déjà remboursé' : 'Déjà encaissé' }}</div>
                <div style="font-size:18px;font-weight:700;color:#6EE7B7;">{{ formatCurrency(parseAmount(selectedFacture.montant_paye)) }}</div>
              </div>
              <div>
                <div style="font-size:12px;color:#6B7280;">{{ moneyActionType === 'remboursement' ? 'Reste à rembourser' : 'Reste à payer' }}</div>
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
                {{ paying ? 'Enregistrement…' : moneyActionType === 'remboursement' ? 'Enregistrer le remboursement' : 'Enregistrer le paiement' }}
              </button>
            </div>
          </template>
        </UCard>
      </template>
    </AppModal>

    <AppModal v-model:open="showJournal" size="lg">
      <template #content>
        <UCard>
          <template #header>
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <span style="font-weight:600;">📚 Journal des mouvements — {{ journalFacture?.numero_facture }}</span>
              <button @click="showJournal = false" style="background:none;border:none;color:#9CA3AF;font-size:18px;cursor:pointer;">✕</button>
            </div>
          </template>

          <div style="display:flex;flex-direction:column;gap:10px;max-height:420px;overflow:auto;">
            <div v-if="journalEntries.length === 0" style="font-size:13px;color:#9CA3AF;">Aucun mouvement enregistré.</div>
            <div v-for="entry in journalEntries" :key="entry.id" style="display:grid;grid-template-columns:140px 1fr auto;gap:12px;align-items:center;padding:12px;border:1px solid rgba(255,255,255,0.06);border-radius:10px;background:rgba(255,255,255,0.03);">
              <div style="font-size:12px;color:#9CA3AF;">{{ formatDateTime(entry.date_paiement || entry.created_at || entry.createdAt) }}</div>
              <div style="display:flex;flex-direction:column;gap:4px;">
                <div style="font-size:13px;font-weight:600;color:#E8E9ED;">{{ entry.type_operation === 'remboursement' ? 'Remboursement' : 'Encaissement' }} • {{ paymentModeLabel(entry.mode_paiement) }}</div>
                <div style="font-size:12px;color:#9CA3AF;">{{ entry.reference || entry.notes || 'Sans référence ni note.' }}</div>
              </div>
              <div :style="{ fontSize: '14px', fontWeight: '700', color: entry.type_operation === 'remboursement' ? '#60A5FA' : '#6EE7B7' }">
                {{ entry.type_operation === 'remboursement' ? '-' : '+' }}{{ formatCurrency(parseAmount(entry.montant)) }}
              </div>
            </div>
          </div>
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
definePageMeta({ title: 'Facturation' })
const billingStore = useBillingStore()
const api = useApi()
const toast = useToast()
const auth = useAuth()
const loading = ref(true)
const filter = ref('')
const search = ref('')
const showEncaissement = ref(false)
const showJournal = ref(false)
const paying = ref(false)
const showAvoir = ref(false)
const crediting = ref(false)
const selectedFacture = ref<any>(null)
const journalFacture = ref<any>(null)
const avoirMotif = ref('')
const moneyActionType = ref<'encaissement' | 'remboursement'>('encaissement')

const paiement = reactive({
  mode: 'carte_bancaire',
  montant: 0,
  reference: '',
  notes: '',
})

function exportFec() {
  const url = `${(useRuntimeConfig().public.apiBase || '')}/facturation/fec`
  window.open(url, '_blank')
}

const resteAPayer = computed(() => {
  if (!selectedFacture.value) return 0
  return parseAmount(selectedFacture.value.reste_a_payer)
})

const journalEntries = computed(() => {
  const entries = Array.isArray(journalFacture.value?.paiements) ? [...journalFacture.value.paiements] : []
  return entries.sort((a, b) => String(b.date_paiement || b.created_at || '').localeCompare(String(a.date_paiement || a.created_at || '')))
})

const statusOptions = [
  { value: '', label: 'Toutes' },
  { value: 'brouillon', label: 'Brouillon' },
  { value: 'emise', label: 'Émise' },
  { value: 'partiellement_payee', label: 'Partiellement payée' },
  { value: 'payee', label: 'Payée' },
  { value: 'corrigee', label: 'Corrigée par avoir' },
]

const canManageBilling = computed(() => auth.canManageBilling())

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

function parseAmount(value: unknown): number {
  const parsed = Number.parseFloat(String(value ?? '0').replace(',', '.'))
  return Number.isFinite(parsed) ? parsed : 0
}

function displayTotal(facture: any): number {
  return Math.abs(parseAmount(facture?.total_ttc))
}

function billingStatus(facture: any): string {
  if (facture?.nature === 'avoir') {
    if (facture?.statut === 'payee') return 'avoir_rembourse'
    if (facture?.statut === 'partiellement_payee') return 'avoir_partiellement_rembourse'
    return 'avoir'
  }

  if (facture?.statut === 'payee') return 'paye'
  return facture?.statut || 'emise'
}

function paymentModeLabel(mode: string): string {
  const labels: Record<string, string> = {
    carte_bancaire: 'Carte bancaire',
    especes: 'Espèces',
    cheque: 'Chèque',
    virement: 'Virement bancaire',
    differe: 'Paiement différé',
  }

  return labels[mode] || mode || 'Mode inconnu'
}

function formatDateTime(value: string | null | undefined): string {
  if (!value) return 'Date inconnue'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return String(value)
  return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'short', timeStyle: 'short' }).format(date)
}

function openEncaissement(facture: any) {
  selectedFacture.value = facture
  moneyActionType.value = 'encaissement'
  paiement.mode = 'carte_bancaire'
  paiement.montant = parseAmount(facture.reste_a_payer)
  paiement.reference = ''
  paiement.notes = ''
  showEncaissement.value = true
}

function openRemboursement(facture: any) {
  selectedFacture.value = facture
  moneyActionType.value = 'remboursement'
  paiement.mode = 'virement'
  paiement.montant = parseAmount(facture.reste_a_payer)
  paiement.reference = ''
  paiement.notes = ''
  showEncaissement.value = true
}

function openAvoir(facture: any) {
  selectedFacture.value = facture
  avoirMotif.value = ''
  showAvoir.value = true
}

function openJournal(facture: any) {
  journalFacture.value = facture
  showJournal.value = true
}

function canCollectPayment(facture: any) {
  return facture?.nature !== 'avoir' && !['payee', 'corrigee'].includes(String(facture?.statut || ''))
}

function canRefundPayment(facture: any) {
  return facture?.nature === 'avoir' && !['payee'].includes(String(facture?.statut || ''))
}

function canIssueAvoir(facture: any) {
  return facture?.nature !== 'avoir' && String(facture?.statut || '') !== 'corrigee'
}

async function submitPaiement() {
  if (!selectedFacture.value || paiement.montant <= 0) return
  paying.value = true
  try {
    const endpoint = moneyActionType.value === 'remboursement'
      ? `/facturation/${selectedFacture.value.id}/remboursement`
      : `/facturation/${selectedFacture.value.id}/paiement`

    await api.post(endpoint, {
      mode_paiement: paiement.mode,
      montant: paiement.montant,
      reference: paiement.reference || undefined,
      notes: paiement.notes || undefined,
    })
    toast.add({ title: moneyActionType.value === 'remboursement' ? 'Remboursement enregistré' : 'Paiement enregistré', color: 'success' })
    showEncaissement.value = false
    await billingStore.fetchFactures()
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: (e instanceof Error ? e.message : 'Erreur inconnue') || 'Échec', color: 'error' })
  } finally {
    paying.value = false
  }
}

async function submitAvoir() {
  if (!selectedFacture.value) return
  if (!avoirMotif.value.trim()) {
    toast.add({ title: 'Motif requis', description: 'Le motif de l\'avoir est obligatoire.', color: 'error' })
    return
  }

  crediting.value = true
  try {
    await billingStore.createAvoir(selectedFacture.value.id, avoirMotif.value.trim())
    toast.add({ title: 'Avoir émis', color: 'success' })
    showAvoir.value = false
    await billingStore.fetchFactures()
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: (e instanceof Error ? e.message : 'Erreur inconnue') || 'Échec émission avoir', color: 'error' })
  } finally {
    crediting.value = false
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
  } catch (e: unknown) {
    toast.add({ title: 'Erreur aperçu', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
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
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
  } finally {
    generatingInvoice.value = false
  }
}

async function sendFactureEmail(facture: any) {
  try {
    await api.post(`/facturation/${facture.id}/email`, {})
    toast.add({ title: 'Facture envoyée par email', color: 'success' })
  } catch (e: unknown) {
    toast.add({ title: 'Erreur envoi email', description: (e instanceof Error ? e.message : 'Erreur inconnue') || 'Vérifiez l\'adresse email du client', color: 'error' })
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
