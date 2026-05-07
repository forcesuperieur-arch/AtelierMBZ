<template>
  <div>
    <!-- En-tête compact -->
    <div class="vod-topbar">
      <NuxtLink to="/vo/rachats" class="vod-back">← Rachats</NuxtLink>
      <div class="vod-topbar-mid">
        <span class="vod-title">{{ detail?.vehicule?.marque || '—' }} {{ detail?.vehicule?.modele || '' }}</span>
        <span class="vod-plate">{{ detail?.vehicule?.plaque || 'Sans plaque' }}</span>
        <StatusBadge v-if="detail" :status="detail.status" />
      </div>
      <div class="vod-topbar-actions">
        <PitButton v-if="detail?.canConfirm" color="primary" @click="confirmPurchase">
          Confirmer l'entrée en stock
        </PitButton>
        <PitButton v-else-if="detail?.canSell && !showSale" color="primary" @click="showSale = true">
          Vendre
        </PitButton>
      </div>
    </div>

    <div v-if="!detail" class="vod-loading">Chargement...</div>
    <div v-else>

      <!-- Bande KPI financier -->
      <div class="vod-kpi-strip">
        <div class="vod-kpi-item">
          <span>Achat</span>
          <strong>{{ formatPrice(detail.purchasePrice) }}</strong>
        </div>
        <div class="vod-kpi-item">
          <span>Vente cible</span>
          <strong>{{ formatPrice(detail.targetSalePrice) }}</strong>
        </div>
        <div class="vod-kpi-item">
          <span>FRE</span>
          <strong>{{ formatPrice(detail.totalFre || 0) }}</strong>
        </div>
        <div class="vod-kpi-item">
          <span>Régime TVA</span>
          <strong>{{ detail.regimeTva }}</strong>
        </div>
        <div class="vod-kpi-item">
          <span>Marge nette</span>
          <strong :class="Number(detail.margin || 0) >= 0 ? 'text-success' : 'text-danger'">{{ formatPrice(detail.margin || 0) }}</strong>
        </div>
        <div class="vod-kpi-item vod-kpi-verdict" :class="`is-${readinessState.tone}`">
          <span>Statut</span>
          <strong>{{ readinessState.label }}</strong>
        </div>
      </div>

      <!-- Alerte blocages visibles si non vendable -->
      <div v-if="detail.saleVerdict?.status !== 'vendable' && detail.saleVerdict?.reasons?.length" class="vod-blocages">
        <div v-for="reason in detail.saleVerdict.reasons" :key="reason.code" class="vod-blocage-item" :class="['critical','high'].includes(reason.severity) ? 'is-critical' : 'is-warning'">
          <UIcon :name="['critical','high'].includes(reason.severity) ? 'i-heroicons-x-circle' : 'i-heroicons-exclamation-triangle'" class="w-4 h-4 shrink-0" />
          <span>{{ reason.message }}</span>
          <strong>{{ reason.label }}</strong>
        </div>
      </div>

      <!-- Onglets -->
      <div class="vod-tabs">
        <button v-for="tab in tabs" :key="tab.id" class="vod-tab" :class="{ active: activeTab === tab.id }" @click="activeTab = tab.id">
          {{ tab.label }}
          <span v-if="tab.badge" class="vod-tab-badge">{{ tab.badge }}</span>
        </button>
      </div>

      <!-- ═══ ONGLET 1 : ACQUISITION ═══ -->
      <div v-show="activeTab === 'acquisition'">
        <div class="vod-grid">
          <div class="vod-stack">

            <!-- Véhicule + vendeur -->
            <PitCard class="bg-white">
              <template #header>
                <div class="vod-card-head">
                  <div class="vod-card-title">Véhicule & vendeur</div>
                  <div class="vod-doc-actions">
                    <PitButton size="xs" color="secondary" @click="downloadPv">PV rachat</PitButton>
                    <PitButton size="xs" color="secondary" @click="downloadCerfaCession">CERFA 15776</PitButton>
                  </div>
                </div>
              </template>
              <div class="vod-kpi-grid">
                <div><span class="vod-k">Vendeur</span><strong>{{ detail.seller?.prenom }} {{ detail.seller?.nom }}</strong></div>
                <div><span class="vod-k">Expert</span><strong>{{ detail.expert?.prenom || detail.expert?.username || 'Non assigné' }}</strong></div>
                <div><span class="vod-k">Date d'achat</span><strong>{{ formatDate(detail.purchaseDate) }}</strong></div>
                <div><span class="vod-k">Contrôle technique</span><strong>{{ detail.controleTechniqueOk ? 'Oui' : 'Non' }}</strong></div>
                <div><span class="vod-k">VIN</span><strong class="vod-mono">{{ detail.vehicule?.vin || 'Non renseigné' }}</strong></div>
                <div><span class="vod-k">Plaque</span><strong class="vod-mono">{{ detail.vehicule?.plaque || '—' }}</strong></div>
              </div>
              <div class="vod-note-box" v-if="detail.notes">{{ detail.notes }}</div>
            </PitCard>

            <!-- Dossier moto -->
            <VODossierMotoCard
              mode="purchase"
              :dossier-id="Number(route.params.id)"
              :vehicule="detail.vehicule"
              :documents="detail.documents || []"
              :missing-documents="detail.missingDocuments || []"
              :legal-checklist="detail.legalChecklist || []"
              :sale-verdict="detail.saleVerdict || null"
              :reload-detail="loadDetail"
            />

          </div>
          <div class="vod-stack">

            <!-- Companion PDA -->
            <div id="vo-companion-zone">
              <VOCompanionCard
                :companion="detail.companion"
                :generated-documents="detail.generatedDocuments || []"
              />
            </div>

            <!-- Livre de police -->
            <PitCard class="bg-white" v-if="detail.livrePolice">
              <template #header><div class="vod-card-title">Livre de Police</div></template>
              <div class="vod-kpi-grid">
                <div><span class="vod-k">N° ordre</span><strong>{{ detail.livrePolice.numeroOrdre }}</strong></div>
                <div><span class="vod-k">Date acquisition</span><strong>{{ formatDate(detail.livrePolice.dateAcquisition) }}</strong></div>
                <div><span class="vod-k">Date vente</span><strong>{{ formatDate(detail.livrePolice.dateVente) }}</strong></div>
                <div><span class="vod-k">Prix vente</span><strong>{{ formatPrice(detail.livrePolice.prixVente || 0) }}</strong></div>
              </div>
            </PitCard>

          </div>
        </div>
      </div>

      <!-- ═══ ONGLET 2 : DOSSIER & DA SIV ═══ -->
      <div v-show="activeTab === 'dossier'">
        <div class="vod-grid">
          <div class="vod-stack">

            <!-- Checklist légale -->
            <PitCard class="bg-white">
              <template #header><div class="vod-card-title">Checklist légale</div></template>
              <div class="vod-checklist">
                <div v-for="item in legalChecklist" :key="item.key" class="vod-check-row">
                  <UIcon :name="item.completed ? 'i-heroicons-check-circle' : item.blocking ? 'i-heroicons-x-circle' : 'i-heroicons-clock'" class="w-4 h-4 shrink-0" :class="item.completed ? 'text-success' : item.blocking ? 'text-danger' : 'text-warning'" />
                  <span class="flex-1">{{ item.label }}</span>
                  <span class="vod-check-status" :class="item.completed ? 'is-ok' : item.blocking ? 'is-ko' : 'is-warn'">{{ item.completed ? 'OK' : item.blocking ? 'Bloquant' : 'À prévoir' }}</span>
                </div>
                <div v-if="!legalChecklist.length" class="vod-empty">Aucune checklist disponible.</div>
              </div>
            </PitCard>

          </div>
          <div class="vod-stack">

            <!-- DA SIV -->
            <PitCard class="bg-white">
              <template #header>
                <div class="vod-card-head">
                  <div class="vod-card-title">Déclaration d'achat SIV</div>
                  <div class="vod-doc-actions">
                    <PitButton size="xs" color="secondary" @click="prepareSivDossier">{{ preparingSiv ? '…' : 'Générer DA' }}</PitButton>
                    <PitButton size="xs" color="secondary" @click="downloadDaSiv">Voir</PitButton>
                    <PitButton size="xs" color="secondary" @click="downloadMandat">Mandat</PitButton>
                  </div>
                </div>
              </template>

              <div class="vod-status-banner" :class="detail?.siv?.isComplete ? 'is-success' : 'is-warning'">
                <strong>{{ detail?.siv?.label || 'À préparer' }}</strong>
                <span>{{ detail?.siv?.isComplete ? 'DA tracée — vente possible si le reste du dossier est conforme.' : "La moto reste invendable tant que la DA n'est pas enregistrée." }}</span>
              </div>

              <div class="vod-checklist" style="margin-top:12px">
                <div class="vod-check-row">
                  <UIcon :name="detail?.siv?.daDocumentGenerated ? 'i-heroicons-check-circle' : 'i-heroicons-clock'" class="w-4 h-4 shrink-0" :class="detail?.siv?.daDocumentGenerated ? 'text-success' : 'text-warning'" />
                  <span class="flex-1">DA SIV CERFA 13751</span>
                  <span class="vod-check-status" :class="detail?.siv?.daDocumentGenerated ? 'is-ok' : 'is-warn'">{{ detail?.siv?.daDocumentGenerated ? 'Prête' : 'À générer' }}</span>
                </div>
                <div class="vod-check-row">
                  <UIcon :name="detail?.siv?.recepisseUploaded ? 'i-heroicons-check-circle' : 'i-heroicons-clock'" class="w-4 h-4 shrink-0" :class="detail?.siv?.recepisseUploaded ? 'text-success' : 'text-warning'" />
                  <span class="flex-1">Récépissé DA</span>
                  <span class="vod-check-status" :class="detail?.siv?.recepisseUploaded ? 'is-ok' : 'is-warn'">{{ detail?.siv?.recepisseUploaded ? 'Archivé' : 'À déposer' }}</span>
                </div>
                <div class="vod-check-row">
                  <UIcon :name="detail?.siv?.mandatReady ? 'i-heroicons-check-circle' : 'i-heroicons-minus-circle'" class="w-4 h-4 shrink-0 text-text-tertiary" />
                  <span class="flex-1">Mandat CERFA 13757</span>
                  <span class="vod-check-status is-neutral">{{ detail?.siv?.mandatReady ? 'Généré' : 'Disponible à la vente' }}</span>
                </div>
              </div>

              <!-- Formulaire SIV -->
              <details class="vod-details" style="margin-top:16px">
                <summary class="vod-details-summary">Modifier l'état SIV</summary>
                <div class="vod-form-grid" style="margin-top:12px">
                  <UFormField label="Statut DA">
                    <PitSelect v-model="sivForm.status">
                      <option v-for="option in sivStatusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </PitSelect>
                  </UFormField>
                  <UFormField label="Référence DA">
                    <PitInput v-model="sivForm.reference" placeholder="Référence SIV / récépissé" />
                  </UFormField>
                  <UFormField label="Date enregistrement">
                    <PitInputDate v-model="sivForm.recordedAt" />
                  </UFormField>
                  <UFormField label="Note interne" class="vod-field-full">
                    <PitTextarea v-model="sivForm.notes" :rows="3" placeholder="Retour ANTS, précision opérateur…" />
                  </UFormField>
                </div>
                <div class="vod-actions-end" style="margin-top:12px">
                  <PitButton color="primary" @click="saveSivState">{{ savingSiv ? 'Enregistrement…' : 'Enregistrer' }}</PitButton>
                </div>
              </details>
            </PitCard>

          </div>
        </div>
      </div>

      <!-- ═══ ONGLET 3 : ATELIER & VENTE ═══ -->
      <div v-show="activeTab === 'vente'">
        <div class="vod-grid">
          <div class="vod-stack">

            <!-- Remise en état -->
            <VORemiseEnEtatCard
              source-type="purchase"
              :dossier-id="Number(route.params.id)"
              :remises-en-etat="detail.remisesEnEtat || []"
              :active-remise-en-etat="detail.activeRemiseEnEtat || null"
              :can-create="detail.canCreateRemiseEnEtat !== false"
              :reload-detail="loadDetail"
            />

            <!-- Interventions planifiées -->
            <PitCard class="bg-white">
              <template #header>
                <div class="vod-card-head">
                  <div class="vod-card-title">Interventions atelier</div>
                  <PitButton v-if="['en_stock','en_vente'].includes(detail.status)" size="xs" color="primary" @click="showRdvWizard = true">
                    <UIcon name="i-heroicons-calendar-plus" class="w-4 h-4 mr-1" />Planifier
                  </PitButton>
                </div>
              </template>
              <div v-if="loadingRdvs" class="vod-empty"><UIcon name="i-heroicons-arrow-path" class="w-4 h-4 animate-spin inline mr-1" />Chargement...</div>
              <div v-else-if="voRdvs.length === 0" class="vod-empty">Aucune intervention planifiée.</div>
              <div v-else class="vod-rdv-list">
                <div v-for="rdv in voRdvs" :key="rdv.id" class="vod-rdv-row">
                  <div class="flex-1 min-w-0">
                    <div class="vod-rdv-type">{{ rdv.type_intervention }}</div>
                    <div class="vod-rdv-meta">{{ formatDate(rdv.date_rdv) }} · {{ rdv.heure_rdv }}<span v-if="rdv.mecanicien"> · {{ rdv.mecanicien.nom }}</span></div>
                  </div>
                  <StatusBadge :status="rdv.statut" />
                  <NuxtLink :to="`/planning?openRdv=${rdv.id}`" class="vod-link">Voir →</NuxtLink>
                </div>
              </div>
            </PitCard>

          </div>
          <div class="vod-stack">

            <!-- Simulation + vente -->
            <PitCard class="bg-white" v-if="detail.canConfirm || detail.canSell || detail.status === 'vendu'">
              <template #header>
                <div class="vod-card-head">
                  <div class="vod-card-title">{{ detail.status === 'vendu' ? 'Vente clôturée' : 'Vendre le véhicule' }}</div>
                  <PitButton v-if="detail.canSell && !showSale" size="xs" color="primary" @click="showSale = true">Ouvrir</PitButton>
                </div>
              </template>

              <div v-if="detail.canConfirm" class="vod-status-banner is-info">
                <strong>Confirmation requise</strong>
                <span v-if="detail.confirmationMissingDocuments?.length">Documents bloquants : {{ detail.confirmationMissingDocuments.map(documentLabel).join(', ') }}</span>
                <span v-else>Le dossier est prêt pour l'entrée en stock.</span>
              </div>

              <div v-else-if="detail.confirmationMissingCompanionSteps?.length" class="vod-status-banner is-warning">
                <strong>Parcours PDA incomplet</strong>
                <span>Étapes restantes : {{ detail.confirmationMissingCompanionSteps.join(', ') }}</span>
              </div>

              <div v-else-if="purchaseSaleVerdictMessages.length" class="vod-status-banner is-danger">
                <strong>Vente verrouillée</strong>
                <span>{{ detail.saleVerdict?.summary || purchaseSaleVerdictMessages.join(', ') }}</span>
              </div>

              <div v-if="detail.status === 'vendu'" class="vod-status-banner is-success" style="margin-top:12px">
                <strong>Vendu le {{ formatDate(detail.saleDate) }}</strong>
              </div>

              <div v-if="showSale && detail.canSell" class="vod-sale-form">
                <UFormField label="Rechercher l'acheteur">
                  <PitInput v-model="buyerSearch" placeholder="Nom, prénom, téléphone..." />
                </UFormField>
                <div v-if="buyerResults.length" class="vod-search-list">
                  <button v-for="client in buyerResults" :key="client.id" type="button" class="vod-search-item" @click="selectedBuyer = client">
                    <strong>{{ client.prenom }} {{ client.nom }}</strong>
                    <span>{{ client.telephone || 'Téléphone non renseigné' }}</span>
                  </button>
                </div>
                <div v-if="selectedBuyer" class="vod-selected-box">
                  <UIcon name="i-heroicons-check-circle" class="w-4 h-4 text-success" />
                  <span><strong>{{ selectedBuyer.prenom }} {{ selectedBuyer.nom }}</strong> · {{ selectedBuyer.telephone || '—' }}</span>
                </div>
                <div class="vod-form-grid">
                  <UFormField label="Prix de vente">
                    <PitInput v-model="saleForm.salePrice" />
                  </UFormField>
                  <UFormField label="Notes" class="vod-field-full">
                    <PitTextarea v-model="saleForm.notes" :rows="2" />
                  </UFormField>
                </div>
                <div v-if="saleSimulation" class="vod-sim-strip">
                  <div><span class="vod-k">Marge nette</span><strong :class="saleSimulation.is_profitable ? 'text-success' : 'text-danger'">{{ formatPrice(saleSimulation.net_margin) }}</strong></div>
                  <div><span class="vod-k">Marge %</span><strong>{{ saleSimulation.margin_pct }}%</strong></div>
                </div>
                <div class="vod-actions-end" style="margin-top:12px">
                  <PitButton color="primary" @click="sellVehicle">{{ selling ? 'Enregistrement…' : 'Enregistrer la vente' }}</PitButton>
                </div>
              </div>
            </PitCard>

          </div>
        </div>
      </div>

    </div><!-- /v-else detail -->

    <!-- Wizard RDV -->
    <VORdvWizard
      v-if="showRdvWizard"
      :open="showRdvWizard"
      vo-type="rachat"
      :vo-id="Number(route.params.id)"
      :vehicule="detail?.vehicule"
      :atelier-id="detail?.atelierId"
      @update:open="showRdvWizard = $event"
      @created="onRdvCreated"
    />
  </div>
</template>

<script setup lang="ts">
import { useVoStore } from '~/stores/vo'

definePageMeta({
  layout: 'default', title: 'Dossier rachat VO' })

const route = useRoute()
const voStore = useVoStore()
const toast = useToast()
const { apiBase, formatDate, formatPrice, documentLabel, searchClients } = useVoHelpers()
const { openPdf } = usePdfDownload()
const api = useApi()

const detail = ref<any | null>(null)
const showSale = ref(route.query.sell === '1')
const selling = ref(false)
const savingSiv = ref(false)
const preparingSiv = ref(false)
const buyerSearch = ref('')
const buyerResults = ref<any[]>([])
const selectedBuyer = ref<any | null>(null)
const saleSimulation = ref<any | null>(null)
const activeTab = ref<'acquisition' | 'dossier' | 'vente'>('acquisition')
const tabs = computed(() => [
  { id: 'acquisition' as const, label: 'Acquisition', badge: null },
  {
    id: 'dossier' as const,
    label: 'Dossier & DA SIV',
    badge: detail.value?.siv?.isComplete === false && ['en_stock', 'en_vente', 'reserve'].includes(detail.value?.status) ? '!' : null,
  },
  { id: 'vente' as const, label: 'Atelier & Vente', badge: voRdvs.value.length || null },
])

const sivForm = reactive({
  status: 'a_preparer',
  reference: '',
  recordedAt: '',
  notes: '',
})

const saleForm = reactive({
  salePrice: '',
  notes: '',
})

const purchaseRequiredDocs = ['cerfa_cession_achat', 'carte_grise', 'non_gage', 'pv_rachat']
const saleRequiredDocs = ['cerfa_cession_vente', 'facture_vo', 'notice_garantie']
const sivStatusOptions = [
  { value: 'a_preparer', label: 'À préparer' },
  { value: 'en_cours', label: 'En cours de saisie' },
  { value: 'enregistree', label: 'Enregistrée' },
  { value: 'rejetee', label: 'Rejetée' },
  { value: 'expiree', label: 'Expirée' },
]

const presentDocumentTypes = computed(() => new Set((detail.value?.documents || []).map((document: any) => document.type)))
const legalChecklist = computed(() => detail.value?.legalChecklist || [])
const purchaseSaleVerdictMessages = computed(() => (detail.value?.saleVerdict?.reasons || []).map((reason: any) => reason?.message).filter(Boolean))
const incompleteCompanionStepsCount = computed(() => {
  const steps = detail.value?.companion?.steps
  if (!steps || steps.allComplete) return 0

  return ['seller', 'vehicle', 'documents', 'signature'].reduce((count, key) => {
    return count + (steps[key]?.completed ? 0 : 1)
  }, 0)
})

const purchaseDocumentCompletion = computed(() => {
  const completed = purchaseRequiredDocs.filter(type => presentDocumentTypes.value.has(type)).length
  return Math.round((completed / purchaseRequiredDocs.length) * 100)
})

const saleDocumentCompletion = computed(() => {
  const completed = saleRequiredDocs.filter(type => presentDocumentTypes.value.has(type)).length + (detail.value?.siv?.isComplete ? 1 : 0)
  return Math.round((completed / (saleRequiredDocs.length + 1)) * 100)
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

  if (['en_stock', 'en_vente', 'reserve'].includes(detail.value.status) && detail.value?.siv?.isComplete === false) {
    return {
      tone: 'warning',
      label: 'DA SIV à faire',
      text: 'Le rachat est en stock mais la vente reste bloquée tant que la DA n’est pas enregistrée.',
    }
  }

  if (incompleteCompanionStepsCount.value) {
    return {
      tone: 'warning',
      label: 'Signature PDA attendue',
      text: `${incompleteCompanionStepsCount.value} étape(s) du parcours vendeur restent à valider.`,
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
  sivForm.status = detail.value?.siv?.status || detail.value?.sivStatus || 'a_preparer'
  sivForm.reference = detail.value?.siv?.reference || detail.value?.sivReference || ''
  sivForm.recordedAt = String(detail.value?.siv?.recordedAt || detail.value?.sivRecordedAt || '').slice(0, 10)
  sivForm.notes = detail.value?.siv?.notes || detail.value?.sivNotes || ''
  await runSaleSimulation()
}

function focusCompanion() {
  if (!import.meta.client) return
  document.getElementById('vo-companion-zone')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

async function saveSivState() {
  if (!detail.value) return

  savingSiv.value = true
  try {
    await voStore.updatePurchase(Number(route.params.id), {
      sivStatus: sivForm.status,
      sivReference: sivForm.reference || null,
      sivRecordedAt: sivForm.recordedAt || null,
      sivNotes: sivForm.notes || null,
    })
    toast.add({ title: 'État SIV enregistré', color: 'success' })
    await loadDetail()
  } catch (error: unknown) {
    toast.add({ title: 'Erreur', description: error instanceof Error ? error.message : 'Erreur inconnue', color: 'error' })
  } finally {
    savingSiv.value = false
  }
}

async function confirmPurchase() {
  try {
    await voStore.confirmPurchase(Number(route.params.id))
    toast.add({ title: 'Rachat confirmé', color: 'success' })
    await loadDetail()
  } catch (error: unknown) {
    toast.add({ title: 'Erreur', description: error instanceof Error ? error.message : 'Erreur inconnue', color: 'error' })
  }
}

function downloadPv() {
  openPdf(`/vo/purchases/${route.params.id}/pv-rachat/pdf`)
}

function downloadCerfaCession() {
  openPdf(`/vo/purchases/${route.params.id}/cerfa-cession-achat/pdf`)
}

function downloadDaSiv() {
  openPdf(`/vo/purchases/${route.params.id}/da-siv/pdf`)
}

function downloadMandat() {
  const buyerId = selectedBuyer.value?.id ? `?buyerId=${selectedBuyer.value.id}` : ''
  openPdf(`/vo/purchases/${route.params.id}/mandat-immat/pdf${buyerId}`)
}

async function prepareSivDossier() {
  preparingSiv.value = true
  try {
    const response = await voStore.preparePurchaseSiv(Number(route.params.id))
    toast.add({
      title: response?.ready ? 'DA CERFA générée' : 'DA CERFA générée avec réserves',
      description: response?.blockers?.length ? response.blockers.join(' · ') : 'Le rendu réglementaire a été archivé dans le dossier.',
      color: response?.ready ? 'success' : 'warning',
    })

    if (response?.pdfUrl && import.meta.client) {
      openPdf(response.pdfUrl)
    }

    await loadDetail()
  } catch (error: unknown) {
    toast.add({ title: 'Erreur', description: error instanceof Error ? error.message : 'Erreur inconnue', color: 'error' })
  } finally {
    preparingSiv.value = false
  }
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
  } catch (error: unknown) {
    toast.add({ title: 'Erreur', description: error instanceof Error ? error.message : 'Erreur inconnue', color: 'error' })
  } finally {
    selling.value = false
  }
}

onMounted(async () => {
  await loadDetail()
  await loadVoRdvs()
  if (route.query.companion === '1') {
    nextTick(() => focusCompanion())
  }
})

// ─── [VO-RDV] ────────────────────────────────────────────────────────────────
const showRdvWizard = ref(false)
const voRdvs = ref<any[]>([])
const loadingRdvs = ref(false)

async function loadVoRdvs() {
  loadingRdvs.value = true
  try {
    voRdvs.value = await api.get(`/vo/purchases/${route.params.id}/rdv`)
  } catch {
    voRdvs.value = []
  } finally {
    loadingRdvs.value = false
  }
}

function onRdvCreated() {
  void loadVoRdvs()
}
</script>

<style scoped>
/* ── Topbar ── */
.vod-topbar {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 12px 0 10px;
  flex-wrap: wrap;
}
.vod-back {
  color: var(--text-secondary);
  font-size: 13px;
  text-decoration: none;
  white-space: nowrap;
}
.vod-back:hover { color: var(--accent); }
.vod-topbar-mid {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 1;
  min-width: 0;
  flex-wrap: wrap;
}
.vod-title {
  font-weight: 700;
  font-size: 17px;
  color: var(--text-primary);
}
.vod-plate {
  font-size: 12px;
  font-weight: 700;
  letter-spacing: .05em;
  background: var(--bg-elevated);
  border: 1px solid var(--border-default);
  border-radius: 6px;
  padding: 2px 8px;
  color: var(--text-secondary);
}
.vod-topbar-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.vod-loading {
  padding: 48px;
  text-align: center;
  color: var(--text-secondary);
}

/* ── KPI strip ── */
.vod-kpi-strip {
  display: flex;
  flex-wrap: wrap;
  gap: 0;
  background: var(--bg-surface);
  border: 1px solid var(--border-default);
  border-radius: 12px;
  margin-bottom: 12px;
  overflow: hidden;
}
.vod-kpi-item {
  display: flex;
  flex-direction: column;
  gap: 3px;
  padding: 10px 18px;
  flex: 1;
  min-width: 100px;
  border-right: 1px solid var(--border-default);
}
.vod-kpi-item:last-child { border-right: none; }
.vod-kpi-item span {
  font-size: 11px;
  color: var(--text-tertiary);
  text-transform: uppercase;
  letter-spacing: .04em;
}
.vod-kpi-item strong {
  font-size: 15px;
  font-weight: 700;
  color: var(--text-primary);
}
.vod-kpi-verdict.is-success { background: rgba(34, 197, 94, 0.07); }
.vod-kpi-verdict.is-warning { background: rgba(245, 158, 11, 0.07); }
.vod-kpi-verdict.is-accent  { background: rgba(59, 130, 246, 0.07); }
.vod-kpi-verdict.is-neutral { background: var(--bg-elevated); }

/* ── Blocages rapides ── */
.vod-blocages {
  display: grid;
  gap: 6px;
  margin-bottom: 12px;
}
.vod-blocage-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border-radius: 8px;
  font-size: 13px;
  border: 1px solid transparent;
}
.vod-blocage-item.is-critical {
  background: rgba(239, 68, 68, 0.06);
  border-color: rgba(239, 68, 68, 0.18);
  color: #dc2626;
}
.vod-blocage-item.is-warning {
  background: rgba(245, 158, 11, 0.06);
  border-color: rgba(245, 158, 11, 0.18);
  color: #b45309;
}
.vod-blocage-item span { flex: 1; }
.vod-blocage-item strong { margin-left: auto; white-space: nowrap; }

/* ── Onglets ── */
.vod-tabs {
  display: flex;
  gap: 2px;
  border-bottom: 2px solid var(--border-default);
  margin-bottom: 20px;
}
.vod-tab {
  padding: 10px 20px;
  font-size: 14px;
  font-weight: 600;
  color: var(--text-secondary);
  background: none;
  border: none;
  cursor: pointer;
  border-bottom: 3px solid transparent;
  margin-bottom: -2px;
  display: flex;
  align-items: center;
  gap: 6px;
  transition: color .15s, border-color .15s;
}
.vod-tab:hover { color: var(--text-primary); }
.vod-tab.active {
  color: var(--accent);
  border-bottom-color: var(--accent);
}
.vod-tab-badge {
  background: var(--accent);
  color: #fff;
  font-size: 10px;
  font-weight: 700;
  border-radius: 999px;
  min-width: 18px;
  height: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 4px;
}

/* ── Grid + stack ── */
.vod-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.2fr) minmax(280px, 0.9fr);
  gap: 16px;
  align-items: start;
}
.vod-stack {
  display: grid;
  gap: 16px;
}

/* ── Éléments intra-card ── */
.vod-card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.vod-card-title {
  font-weight: 700;
  color: var(--text-primary);
  font-size: 14px;
}
.vod-doc-actions {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}
.vod-kpi-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}
.vod-kpi-grid > div { display: grid; gap: 3px; }
.vod-k {
  font-size: 11px;
  color: var(--text-tertiary);
  text-transform: uppercase;
  letter-spacing: .04em;
}
.vod-kpi-grid strong {
  font-size: 14px;
  font-weight: 600;
  color: var(--text-primary);
}
.vod-mono { font-family: monospace; font-size: 13px; }
.vod-note-box {
  margin-top: 10px;
  padding: 10px 12px;
  border-radius: 8px;
  background: var(--bg-elevated);
  border: 1px solid var(--border-default);
  font-size: 13px;
  color: var(--text-secondary);
}
.vod-form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}
.vod-field-full { grid-column: 1 / -1; }
.vod-actions-end {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

/* ── Status banner ── */
.vod-status-banner {
  display: grid;
  gap: 4px;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid transparent;
  font-size: 13px;
}
.vod-status-banner strong { font-size: 14px; }
.vod-status-banner.is-success { background: rgba(34, 197, 94, 0.08); border-color: rgba(34, 197, 94, 0.2); color: #166534; }
.vod-status-banner.is-warning { background: rgba(245, 158, 11, 0.08); border-color: rgba(245, 158, 11, 0.2); color: #92400e; }
.vod-status-banner.is-danger  { background: rgba(239, 68, 68, 0.08); border-color: rgba(239, 68, 68, 0.2); color: #991b1b; }
.vod-status-banner.is-info    { background: rgba(59, 130, 246, 0.08); border-color: rgba(59, 130, 246, 0.2); color: #1e40af; }

/* ── Checklist ── */
.vod-checklist { display: grid; gap: 8px; }
.vod-check-row {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: var(--text-primary);
}
.vod-check-status {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 999px;
  white-space: nowrap;
}
.vod-check-status.is-ok      { background: rgba(34, 197, 94, 0.12); color: #166534; }
.vod-check-status.is-ko      { background: rgba(239, 68, 68, 0.12); color: #991b1b; }
.vod-check-status.is-warn    { background: rgba(245, 158, 11, 0.12); color: #92400e; }
.vod-check-status.is-neutral { background: var(--bg-elevated); color: var(--text-tertiary); }
.vod-empty { color: var(--text-tertiary); font-size: 13px; padding: 8px 0; }

/* ── Details / accordéon ── */
.vod-details { border-top: 1px solid var(--border-default); padding-top: 12px; }
.vod-details-summary {
  cursor: pointer;
  font-size: 13px;
  font-weight: 600;
  color: var(--accent);
  user-select: none;
}

/* ── RDV list ── */
.vod-rdv-list { display: grid; gap: 8px; }
.vod-rdv-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--bg-elevated);
  border: 1px solid var(--border-default);
}
.vod-rdv-type { font-size: 13px; font-weight: 600; color: var(--text-primary); }
.vod-rdv-meta { font-size: 12px; color: var(--text-tertiary); }
.vod-link {
  font-size: 12px;
  color: var(--accent);
  text-decoration: none;
  white-space: nowrap;
}

/* ── Vente / simulation ── */
.vod-sale-form { display: grid; gap: 12px; margin-top: 12px; }
.vod-search-list { display: grid; gap: 4px; margin-top: 4px; }
.vod-search-item {
  display: grid;
  gap: 2px;
  text-align: left;
  padding: 8px 12px;
  border-radius: 8px;
  background: var(--bg-elevated);
  border: 1px solid var(--border-default);
  cursor: pointer;
  font-size: 13px;
  color: var(--text-primary);
}
.vod-search-item:hover { border-color: var(--accent); }
.vod-search-item span { font-size: 11px; color: var(--text-tertiary); }
.vod-selected-box {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border-radius: 8px;
  background: rgba(34, 197, 94, 0.07);
  border: 1px solid rgba(34, 197, 94, 0.2);
  font-size: 13px;
}
.vod-sim-strip {
  display: flex;
  gap: 24px;
  padding: 8px 12px;
  border-radius: 8px;
  background: var(--bg-elevated);
  border: 1px solid var(--border-default);
}
.vod-sim-strip > div { display: flex; flex-direction: column; gap: 2px; }

/* ── Couleurs sémantiques ── */
.text-success { color: #16a34a; }
.text-danger  { color: #dc2626; }
.text-warning { color: #d97706; }

@media (max-width: 1000px) {
  .vod-grid { grid-template-columns: 1fr; }
  .vod-kpi-strip { display: grid; grid-template-columns: repeat(3, 1fr); }
  .vod-kpi-item { padding: 8px 10px; border-right: 1px solid var(--border-default); border-bottom: 1px solid var(--border-default); }
  .vod-kpi-item:nth-child(3n) { border-right: none; }
  .vod-kpi-item:nth-last-child(-n+3) { border-bottom: none; }
}
</style>