<template>
  <div>
    <div class="page-header vo-header">
      <div>
        <NuxtLink to="/vo/rachats" class="vo-back-link">← Retour aux rachats</NuxtLink>
        <div class="page-title">Dossier rachat #{{ route.params.id }}</div>
        <div class="vo-subtitle">Vue complète du dossier, des documents obligatoires et du flux de vente.</div>
      </div>
      <div class="vo-header-actions">
        <button class="topbar-new-btn vo-secondary-btn" @click="downloadPv">PV de rachat</button>
        <button v-if="detail?.canConfirm" class="topbar-new-btn" @click="confirmPurchase">Confirmer</button>
      </div>
    </div>

    <VONav />

    <div v-if="!detail" class="vo-loading">Chargement du dossier...</div>

    <div v-else>
      <div v-if="route.query.companion === '1'" class="vo-companion-banner">
        <div>
          <strong>Parcours compagnon prêt</strong>
          <span>Scanne le QR code ci-dessous depuis le PDA pour charger la pièce d’identité, la carte grise, vérifier le préremplissage puis faire signer le vendeur.</span>
        </div>
        <button type="button" class="vo-link-btn" @click="focusCompanion">Voir le QR code</button>
      </div>

      <div class="vo-detail-grid">
      <div class="vo-detail-stack">
        <UCard>
          <template #header>
            <div class="vo-card-head">
              <div>
                <div class="vo-card-title">{{ detail.vehicule?.marque || '—' }} {{ detail.vehicule?.modele || '' }}</div>
                <div class="vo-card-subtitle">{{ detail.vehicule?.plaque || 'Sans plaque' }} • VIN {{ detail.vehicule?.vin || 'non renseigné' }}</div>
              </div>
              <StatusBadge :status="detail.status" />
            </div>
          </template>

          <div class="vo-kpi-grid">
            <div>
              <span class="vo-k">Vendeur</span>
              <strong>{{ detail.seller?.prenom }} {{ detail.seller?.nom }}</strong>
            </div>
            <div>
              <span class="vo-k">Expert</span>
              <strong>{{ detail.expert?.prenom || detail.expert?.username || 'Non assigné' }}</strong>
            </div>
            <div>
              <span class="vo-k">Date d'achat</span>
              <strong>{{ formatDate(detail.purchaseDate) }}</strong>
            </div>
            <div>
              <span class="vo-k">Contrôle technique</span>
              <strong>{{ detail.controleTechniqueOk ? 'Oui' : 'Non' }}</strong>
            </div>
          </div>

          <div class="vo-note-box" v-if="detail.notes">
            {{ detail.notes }}
          </div>
        </UCard>

        <VODossierMotoCard
          mode="purchase"
          :dossier-id="Number(route.params.id)"
          :vehicule="detail.vehicule"
          :documents="detail.documents || []"
          :missing-documents="detail.missingDocuments || []"
          :reload-detail="loadDetail"
        />

        <div id="vo-companion-zone">
          <VOCompanionCard
            :companion="detail.companion"
            :generated-documents="detail.generatedDocuments || []"
          />
        </div>

        <UCard v-if="detail.livrePolice">
          <template #header>
            <div class="vo-card-title">Livre de Police</div>
          </template>
          <div class="vo-kpi-grid">
            <div>
              <span class="vo-k">N° ordre</span>
              <strong>{{ detail.livrePolice.numeroOrdre }}</strong>
            </div>
            <div>
              <span class="vo-k">Date acquisition</span>
              <strong>{{ formatDate(detail.livrePolice.dateAcquisition) }}</strong>
            </div>
            <div>
              <span class="vo-k">Date vente</span>
              <strong>{{ formatDate(detail.livrePolice.dateVente) }}</strong>
            </div>
            <div>
              <span class="vo-k">Prix vente</span>
              <strong>{{ formatPrice(detail.livrePolice.prixVente || 0) }}</strong>
            </div>
          </div>
        </UCard>
      </div>

      <div class="vo-detail-stack">
        <UCard>
          <template #header>
            <div class="vo-card-title">État du dossier</div>
          </template>

          <div class="vo-health-box" :class="`is-${readinessState.tone}`">
            <strong>{{ readinessState.label }}</strong>
            <span>{{ readinessState.text }}</span>
          </div>

          <div class="vo-progress-block">
            <div class="vo-progress-head">
              <span>Conformité achat</span>
              <strong>{{ purchaseDocumentCompletion }}%</strong>
            </div>
            <div class="vo-progress-bar"><span :style="{ width: `${purchaseDocumentCompletion}%` }" /></div>
          </div>

          <div class="vo-progress-block">
            <div class="vo-progress-head">
              <span>Préparation vente</span>
              <strong>{{ saleDocumentCompletion }}%</strong>
            </div>
            <div class="vo-progress-bar is-secondary"><span :style="{ width: `${saleDocumentCompletion}%` }" /></div>
          </div>

          <div class="vo-workflow-strip">
            <div
              v-for="stepItem in workflowSteps"
              :key="stepItem.label"
              class="vo-workflow-step"
              :class="{ 'is-current': stepItem.state === 'current', 'is-done': stepItem.state === 'done' }"
            >
              {{ stepItem.label }}
            </div>
          </div>
        </UCard>

        <UCard>
          <template #header>
            <div class="vo-card-title">Financier</div>
          </template>

          <div class="vo-kpi-grid">
            <div>
              <span class="vo-k">Prix d'achat</span>
              <strong>{{ formatPrice(detail.purchasePrice) }}</strong>
            </div>
            <div>
              <span class="vo-k">Prix vente cible</span>
              <strong>{{ formatPrice(detail.targetSalePrice) }}</strong>
            </div>
            <div>
              <span class="vo-k">FRE total</span>
              <strong>{{ formatPrice(detail.totalFre || 0) }}</strong>
            </div>
            <div>
              <span class="vo-k">Régime TVA</span>
              <strong>{{ detail.regimeTva }}</strong>
            </div>
            <div>
              <span class="vo-k">Marge nette</span>
              <strong :style="{ color: Number(detail.margin || 0) >= 0 ? '#22c55e' : '#ef4444' }">{{ formatPrice(detail.margin || 0) }}</strong>
            </div>
          </div>

          <div v-if="detail.repairEstimates?.length" class="vo-lines">
            <div v-for="(item, index) in detail.repairEstimates" :key="index" class="vo-line-detail">
              <span>{{ item.label }}</span>
              <strong>{{ formatPrice(item.amount) }}</strong>
            </div>
          </div>
        </UCard>

        <UCard v-if="detail.canConfirm || detail.canSell || detail.status === 'vendu' || detail.confirmationMissingCompanionSteps?.length">
          <template #header>
            <div class="vo-card-head">
              <div class="vo-card-title">Flux de vente</div>
              <button v-if="detail.canSell && !showSale" class="vo-link-btn" @click="showSale = true">Ouvrir la vente</button>
            </div>
          </template>

          <div v-if="detail.canConfirm" class="vo-warning-box">
            <strong>Confirmation requise</strong>
            <span v-if="detail.confirmationMissingDocuments?.length">Documents bloquants: {{ detail.confirmationMissingDocuments.map(documentLabel).join(', ') }}</span>
            <span v-else>Le dossier est prêt pour l'entrée en stock.</span>
          </div>

          <div v-else-if="detail.confirmationMissingCompanionSteps?.length" class="vo-warning-box">
            <strong>Parcours PDA incomplet</strong>
            <span>Étapes restantes: {{ detail.confirmationMissingCompanionSteps.join(', ') }}</span>
          </div>

          <div v-if="showSale && detail.canSell" class="vo-sale-box">
            <label class="vo-field">
              <span>Rechercher l'acheteur</span>
              <UInput v-model="buyerSearch" placeholder="Nom, prénom, téléphone..." />
            </label>

            <div v-if="buyerResults.length" class="vo-search-list">
              <button v-for="client in buyerResults" :key="client.id" type="button" class="vo-search-item" @click="selectedBuyer = client">
                <strong>{{ client.prenom }} {{ client.nom }}</strong>
                <span>{{ client.telephone || 'Téléphone non renseigné' }}</span>
              </button>
            </div>

            <div v-if="selectedBuyer" class="vo-selected-box">
              <strong>{{ selectedBuyer.prenom }} {{ selectedBuyer.nom }}</strong>
              <span>{{ selectedBuyer.telephone || 'Téléphone non renseigné' }}</span>
            </div>

            <div class="vo-form-grid">
              <label class="vo-field">
                <span>Prix de vente</span>
                <input v-model="saleForm.salePrice" class="vo-input" />
              </label>
              <label class="vo-field vo-field-full">
                <span>Notes de vente</span>
                <textarea v-model="saleForm.notes" class="vo-textarea" rows="3" />
              </label>
            </div>

            <div v-if="saleSimulation" class="vo-sim-box">
              <div>
                <span class="vo-k">Marge nette</span>
                <strong :style="{ color: saleSimulation.is_profitable ? '#22c55e' : '#ef4444' }">{{ formatPrice(saleSimulation.net_margin) }}</strong>
              </div>
              <div>
                <span class="vo-k">Marge %</span>
                <strong>{{ saleSimulation.margin_pct }}%</strong>
              </div>
            </div>

            <div class="vo-inline-actions">
              <button class="topbar-new-btn" :disabled="selling" @click="sellVehicle">{{ selling ? 'Vente...' : 'Enregistrer la vente' }}</button>
            </div>
          </div>

          <div v-else-if="detail.status === 'vendu'" class="vo-info-box">
            <strong>Le véhicule a été vendu le {{ formatDate(detail.saleDate) }}.</strong>
          </div>
        </UCard>
      </div>
    </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useVoStore } from '~/stores/vo'

definePageMeta({ title: 'Dossier rachat VO' })

const route = useRoute()
const voStore = useVoStore()
const toast = useToast()
const { apiBase, formatDate, formatPrice, documentLabel, searchClients } = useVoHelpers()

const detail = ref<any | null>(null)
const showSale = ref(route.query.sell === '1')
const selling = ref(false)
const buyerSearch = ref('')
const buyerResults = ref<any[]>([])
const selectedBuyer = ref<any | null>(null)
const saleSimulation = ref<any | null>(null)

const saleForm = reactive({
  salePrice: '',
  notes: '',
})

const purchaseRequiredDocs = ['cerfa_cession_achat', 'carte_grise', 'non_gage', 'piece_identite', 'pv_rachat']
const saleRequiredDocs = ['cerfa_cession_vente', 'facture_vo', 'notice_garantie']

const presentDocumentTypes = computed(() => new Set((detail.value?.documents || []).map((document: any) => document.type)))

const purchaseDocumentCompletion = computed(() => {
  const completed = purchaseRequiredDocs.filter(type => presentDocumentTypes.value.has(type)).length
  return Math.round((completed / purchaseRequiredDocs.length) * 100)
})

const saleDocumentCompletion = computed(() => {
  const completed = saleRequiredDocs.filter(type => presentDocumentTypes.value.has(type)).length
  return Math.round((completed / saleRequiredDocs.length) * 100)
})

const readinessState = computed(() => {
  if (!detail.value) {
    return { tone: 'neutral', label: 'Chargement', text: 'Préparation du dossier VO.' }
  }

  if (detail.value.status === 'vendu') {
    return { tone: 'success', label: 'Vendu', text: 'La vente est clôturée et la facture a été générée.' }
  }

  if (detail.value.canConfirm) {
    return { tone: 'success', label: 'Prêt à confirmer', text: 'Le dossier a toutes les pièces nécessaires pour entrer en stock.' }
  }

  if (detail.value.confirmationMissingCompanionSteps?.length) {
    return {
      tone: 'warning',
      label: 'Signature PDA attendue',
      text: `${detail.value.confirmationMissingCompanionSteps.length} étape(s) du parcours vendeur restent à valider.`,
    }
  }

  if (detail.value.confirmationMissingDocuments?.length) {
    return {
      tone: 'warning',
      label: 'Incomplet',
      text: `${detail.value.confirmationMissingDocuments.length} document(s) bloquent encore la confirmation.`,
    }
  }

  if (detail.value.canSell) {
    return { tone: 'accent', label: 'Prêt à vendre', text: 'Le dossier est en circulation commerciale et peut être vendu immédiatement.' }
  }

  return { tone: 'neutral', label: 'En préparation', text: 'Le dossier est encore en construction.' }
})

const workflowSteps = computed(() => {
  const status = detail.value?.status
  const phase = status === 'vendu'
    ? 'vendu'
    : ['en_stock', 'en_vente', 'reserve'].includes(status)
      ? 'stock'
      : 'brouillon'

  return [
    { label: 'Brouillon', state: phase === 'brouillon' ? 'current' : ['stock', 'vendu'].includes(phase) ? 'done' : 'idle' },
    { label: 'Stock / vente', state: phase === 'stock' ? 'current' : phase === 'vendu' ? 'done' : 'idle' },
    { label: 'Vendu', state: phase === 'vendu' ? 'current' : 'idle' },
  ]
})

let buyerTimer: ReturnType<typeof setTimeout> | null = null
let saleSimulationTimer: ReturnType<typeof setTimeout> | null = null

watch(buyerSearch, (value) => {
  if (buyerTimer) clearTimeout(buyerTimer)
  if (value.trim().length < 2) {
    buyerResults.value = []
    return
  }

  buyerTimer = setTimeout(async () => {
    buyerResults.value = await searchClients(value)
  }, 250)
})

watch(() => saleForm.salePrice, () => {
  if (saleSimulationTimer) clearTimeout(saleSimulationTimer)
  saleSimulationTimer = setTimeout(() => {
    runSaleSimulation()
  }, 250)
})

async function loadDetail() {
  detail.value = await voStore.fetchPurchaseFull(Number(route.params.id))
  saleForm.salePrice = detail.value?.targetSalePrice || ''
  await runSaleSimulation()
}

function focusCompanion() {
  if (!import.meta.client) return
  document.getElementById('vo-companion-zone')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

async function confirmPurchase() {
  try {
    await voStore.confirmPurchase(Number(route.params.id))
    toast.add({ title: 'Rachat confirmé', color: 'success' })
    await loadDetail()
  } catch (error: any) {
    toast.add({ title: 'Erreur', description: error.message, color: 'error' })
  }
}

function downloadPv() {
  window.open(`${apiBase}/vo/purchases/${route.params.id}/pv-rachat/pdf`, '_blank')
}

async function runSaleSimulation() {
  if (!detail.value || !saleForm.salePrice.trim()) {
    saleSimulation.value = null
    return
  }

  try {
    saleSimulation.value = await voStore.simulateMargin({
      purchasePrice: detail.value.purchasePrice,
      salePrice: saleForm.salePrice,
      regime: detail.value.regimeTva,
      freItems: detail.value.repairEstimates || [],
    })
  } catch {
    saleSimulation.value = null
  }
}

async function sellVehicle() {
  if (!selectedBuyer.value?.id) {
    toast.add({ title: 'Erreur', description: 'Sélectionnez un acheteur', color: 'error' })
    return
  }

  selling.value = true
  try {
    await voStore.sellPurchase(Number(route.params.id), {
      buyerId: selectedBuyer.value.id,
      salePrice: saleForm.salePrice,
      notes: saleForm.notes || undefined,
    })
    toast.add({ title: 'Vente enregistrée', color: 'success' })
    await loadDetail()
    showSale.value = false
  } catch (error: any) {
    toast.add({ title: 'Erreur', description: error.message, color: 'error' })
  } finally {
    selling.value = false
  }
}

onMounted(async () => {
  await loadDetail()
  if (route.query.companion === '1') {
    nextTick(() => focusCompanion())
  }
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

.vo-companion-banner {
  margin-bottom: 16px;
  padding: 14px 16px;
  border-radius: 16px;
  border: 1px solid rgba(251, 191, 36, 0.32);
  background: rgba(245, 158, 11, 0.08);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.vo-companion-banner strong {
  display: block;
  color: #f8fafc;
  margin-bottom: 4px;
}

.vo-companion-banner span {
  color: #d1d5db;
  font-size: 13px;
}

.vo-back-link {
  display: inline-block;
  margin-bottom: 8px;
  color: #9ca3af;
  text-decoration: none;
  font-size: 12px;
  font-weight: 700;
}

.vo-subtitle {
  margin-top: 6px;
  color: #9ca3af;
  font-size: 13px;
}

.vo-header-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.vo-secondary-btn {
  background: #1f2937;
}

.vo-loading {
  padding: 24px;
  color: #9ca3af;
}

.vo-detail-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.25fr) minmax(320px, 0.85fr);
  gap: 16px;
}

.vo-detail-stack,
.vo-document-list,
.vo-search-list,
.vo-lines {
  display: grid;
  gap: 16px;
}

.vo-card-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.vo-card-title {
  color: #e8e9ed;
  font-weight: 700;
}

.vo-card-subtitle,
.vo-doc-count,
.vo-k,
.vo-field span {
  color: #9ca3af;
  font-size: 12px;
}

.vo-kpi-grid,
.vo-form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.vo-kpi-grid strong {
  display: block;
  color: #e8e9ed;
  margin-top: 4px;
}

.vo-note-box,
.vo-warning-box,
.vo-info-box,
.vo-sim-box,
.vo-health-box,
.vo-selected-box {
  display: grid;
  gap: 6px;
  padding: 12px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.06);
}

.vo-warning-box {
  background: rgba(239, 68, 68, 0.05);
  border-color: rgba(239, 68, 68, 0.18);
}

.vo-health-box.is-success {
  background: rgba(34, 197, 94, 0.08);
  border-color: rgba(34, 197, 94, 0.2);
}

.vo-health-box.is-warning {
  background: rgba(245, 158, 11, 0.08);
  border-color: rgba(245, 158, 11, 0.2);
}

.vo-health-box.is-accent {
  background: rgba(59, 130, 246, 0.08);
  border-color: rgba(59, 130, 246, 0.18);
}

.vo-progress-block {
  display: grid;
  gap: 8px;
}

.vo-progress-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  color: #9ca3af;
  font-size: 12px;
  font-weight: 700;
}

.vo-progress-head strong {
  color: #f9fafb;
}

.vo-progress-bar {
  width: 100%;
  height: 9px;
  border-radius: 999px;
  overflow: hidden;
  background: rgba(255, 255, 255, 0.06);
}

.vo-progress-bar span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: linear-gradient(90deg, #22c55e, #86efac);
}

.vo-progress-bar.is-secondary span {
  background: linear-gradient(90deg, #f59e0b, #fcd34d);
}

.vo-workflow-strip {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.vo-workflow-step {
  padding: 7px 12px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.04);
  color: #9ca3af;
  font-size: 11px;
  font-weight: 700;
}

.vo-workflow-step.is-current {
  background: rgba(245, 158, 11, 0.12);
  color: #fcd34d;
}

.vo-workflow-step.is-done {
  background: rgba(34, 197, 94, 0.12);
  color: #86efac;
}

.vo-field {
  display: grid;
  gap: 6px;
}

.vo-field-full {
  grid-column: 1 / -1;
}

.vo-input,
.vo-select,
.vo-textarea {
  width: 100%;
  padding: 10px 12px;
  border-radius: 10px;
  background: #1a1a2e;
  border: 1px solid #374151;
  color: #e8e9ed;
}

.vo-inline-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: 14px;
}

.vo-document-item,
.vo-search-item {
  display: grid;
  gap: 4px;
  text-decoration: none;
  padding: 12px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid rgba(255, 255, 255, 0.06);
  color: #e8e9ed;
  text-align: left;
}

.vo-line-detail {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.vo-sale-box {
  display: grid;
  gap: 12px;
}

.vo-link-btn {
  background: none;
  border: none;
  color: #f59e0b;
  cursor: pointer;
  font-size: 12px;
  font-weight: 700;
  padding: 0;
}

@media (max-width: 1100px) {
  .vo-detail-grid,
  .vo-kpi-grid,
  .vo-form-grid {
    grid-template-columns: 1fr;
  }
}
</style>