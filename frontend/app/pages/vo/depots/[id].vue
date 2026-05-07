<template>
  <div>
    <!-- En-tête compact -->
    <div class="vod-topbar">
      <NuxtLink to="/vo/depots" class="vod-back">← Dépôts</NuxtLink>
      <div class="vod-topbar-mid">
        <span class="vod-title">{{ detail?.vehicule?.marque || '—' }} {{ detail?.vehicule?.modele || '' }}</span>
        <span class="vod-plate">{{ detail?.vehicule?.plaque || 'Sans plaque' }}</span>
        <StatusBadge v-if="detail" :status="detail.status" />
        <span v-if="detail?.mandatExpire" class="vod-expire-pill">Mandat expiré</span>
        <span v-else-if="detail?.joursRestants !== undefined && Number(detail.joursRestants) <= 7" class="vod-warn-pill">{{ detail.joursRestants }}j restants</span>
      </div>
      <div class="vod-topbar-actions">
        <PitButton v-if="detail?.status === 'actif' && detail?.canSell && !showSale" color="primary" @click="showSale = true">
          Vendre
        </PitButton>
      </div>
    </div>

    <div v-if="!detail" class="vod-loading">Chargement...</div>
    <div v-else>

      <!-- KPI strip mandat -->
      <div class="vod-kpi-strip">
        <div class="vod-kpi-item">
          <span>Prix souhaité</span>
          <strong>{{ formatPrice(detail.prixVenteSouhaite) }}</strong>
        </div>
        <div class="vod-kpi-item">
          <span>Commission</span>
          <strong>{{ formatPrice(detail.commissionTtc || 0) }} TTC</strong>
        </div>
        <div class="vod-kpi-item">
          <span>Net déposant</span>
          <strong>{{ formatPrice(detail.deposantNet || 0) }}</strong>
        </div>
        <div class="vod-kpi-item">
          <span>Mandat</span>
          <strong :class="detail.mandatExpire ? 'text-danger' : Number(detail.joursRestants ?? 999) <= 7 ? 'text-warning' : ''">
            {{ detail.mandatExpire ? 'Expiré' : `${detail.joursRestants}j restants` }}
          </strong>
        </div>
        <div class="vod-kpi-item" :class="detail.mandatTravaux ? 'vod-kpi-verdict is-success' : ''">
          <span>Travaux autorisés</span>
          <strong>{{ detail.mandatTravaux ? (detail.mandatTravauxPlafond ? `Jusqu\'à ${formatPrice(detail.mandatTravauxPlafond)}` : 'Oui, sans plafond') : 'Non' }}</strong>
        </div>
        <div class="vod-kpi-item vod-kpi-verdict" :class="`is-${mandateState.tone}`">
          <span>Statut</span>
          <strong>{{ mandateState.label }}</strong>
        </div>
      </div>

      <!-- Blocages visibles -->
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

      <!-- ONGLET 1 : MANDAT -->
      <div v-show="activeTab === 'mandat'">
        <div class="vod-grid">
          <div class="vod-stack">
            <PitCard class="bg-white">
              <template #header>
                <div class="vod-card-head">
                  <div class="vod-card-title">Véhicule & déposant</div>
                  <div class="vod-doc-actions">
                    <PitButton size="xs" color="secondary" @click="downloadContrat">Contrat dépôt-vente</PitButton>
                    <PitButton size="xs" color="secondary" @click="downloadMandat">Mandat CERFA</PitButton>
                  </div>
                </div>
              </template>
              <div class="vod-kpi-grid">
                <div><span class="vod-k">Déposant</span><strong>{{ detail.deposant?.prenom }} {{ detail.deposant?.nom }}</strong></div>
                <div><span class="vod-k">Gestionnaire</span><strong>{{ detail.gestionnaire?.prenom || detail.gestionnaire?.username || 'Non assigné' }}</strong></div>
                <div><span class="vod-k">Début mandat</span><strong>{{ formatDate(detail.dateDebut) }}</strong></div>
                <div><span class="vod-k">Fin mandat</span><strong :class="detail.mandatExpire ? 'text-danger' : ''">{{ formatDate(detail.dateFin) }}</strong></div>
                <div><span class="vod-k">VIN</span><strong class="vod-mono">{{ detail.vehicule?.vin || 'Non renseigné' }}</strong></div>
                <div><span class="vod-k">Plaque</span><strong class="vod-mono">{{ detail.vehicule?.plaque || '—' }}</strong></div>
              </div>
              <div class="vod-note-box" v-if="detail.notes">{{ detail.notes }}</div>
            </PitCard>

            <VODossierMotoCard
              mode="depot"
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
            <div id="vo-companion-zone">
              <VOCompanionCard
                :companion="detail.companion"
                :generated-documents="detail.generatedDocuments || []"
              />
            </div>

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

      <!-- ONGLET 2 : DOSSIER & SIMULATION -->
      <div v-show="activeTab === 'dossier'">
        <div class="vod-grid">
          <div class="vod-stack">
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

            <PitCard class="bg-white">
              <template #header><div class="vod-card-title">Commission & reversement</div></template>
              <div class="vod-kpi-grid">
                <div><span class="vod-k">Type</span><strong>{{ detail.commissionType }}</strong></div>
                <div><span class="vod-k">Valeur</span><strong>{{ detail.commissionValeur }}</strong></div>
                <div><span class="vod-k">Commission HT</span><strong>{{ formatPrice(detail.commissionAmount || 0) }}</strong></div>
                <div><span class="vod-k">Commission TTC</span><strong>{{ formatPrice(detail.commissionTtc || 0) }}</strong></div>
                <div><span class="vod-k">Net déposant</span><strong class="text-success">{{ formatPrice(detail.deposantNet || 0) }}</strong></div>
              </div>
              <div class="vod-note-box" style="margin-top:10px">Reversement dans les 15 jours après vente (Art. 1915 CC).</div>
            </PitCard>
          </div>

          <div class="vod-stack">
            <PitCard class="bg-white">
              <template #header><div class="vod-card-title">Simulation de vente</div></template>
              <div class="vod-form-grid">
                <UFormField label="Prix de vente simulé" class="vod-field-full">
                  <PitInput v-model="simulationPrice" placeholder="Ex: 3500" />
                </UFormField>
              </div>
              <div class="vod-actions-end" style="margin-top:8px">
                <PitButton color="primary" @click="runDepotSimulation">{{ simulating ? 'Calcul…' : 'Calculer' }}</PitButton>
              </div>

              <div v-if="depotSimulation" class="vod-sim-result">
                <div class="vod-sim-row">
                  <span class="vod-k">Prix de vente</span>
                  <strong>{{ formatPrice(depotSimulation.prix_vente) }}</strong>
                </div>
                <div class="vod-sim-row">
                  <span class="vod-k">Commission atelier (TTC)</span>
                  <strong class="text-success">{{ formatPrice(depotSimulation.commission_ttc) }}</strong>
                </div>
                <div class="vod-sim-row">
                  <span class="vod-k">Net reversé au déposant</span>
                  <strong class="text-info">{{ formatPrice(depotSimulation.net_deposant) }}</strong>
                </div>
                <div class="vod-sim-bar">
                  <div class="vod-sim-bar-deposant" :style="{ width: deposantPct + '%' }">
                    <span v-if="deposantPct > 15">Déposant {{ deposantPct }}%</span>
                  </div>
                  <div class="vod-sim-bar-atelier" :style="{ width: atelierPct + '%' }">
                    <span v-if="atelierPct > 15">Atelier {{ atelierPct }}%</span>
                  </div>
                </div>
              </div>
            </PitCard>
          </div>
        </div>
      </div>

      <!-- ONGLET 3 : GESTION & VENTE -->
      <div v-show="activeTab === 'gestion'">
        <div class="vod-grid">
          <div class="vod-stack">
            <VORemiseEnEtatCard
              source-type="depot"
              :dossier-id="Number(route.params.id)"
              :remises-en-etat="detail.remisesEnEtat || []"
              :active-remise-en-etat="detail.activeRemiseEnEtat || null"
              :can-create="detail.canCreateRemiseEnEtat !== false"
              :reload-detail="loadDetail"
            />

            <PitCard class="bg-white">
              <template #header>
                <div class="vod-card-head">
                  <div class="vod-card-title">Interventions atelier</div>
                  <PitButton v-if="detail.status === 'actif'" size="xs" color="primary" @click="showRdvWizard = true">
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
            <PitCard class="bg-white" v-if="detail.status === 'actif'">
              <template #header><div class="vod-card-title">Actions mandat</div></template>

              <div v-if="detail.mandatExpire" class="vod-status-banner is-danger">
                <strong>Mandat expiré</strong>
                <span>Prolongez le mandat ou restituez le véhicule au déposant.</span>
              </div>

              <div class="vod-form-grid" style="margin-top:12px">
                <UFormField label="Prolonger de (jours)">
                  <PitInput v-model="extensionDays" type="number" placeholder="Ex: 30" />
                </UFormField>
                <UFormField label="Note de restitution" class="vod-field-full">
                  <PitTextarea v-model="restitutionNotes" :rows="2" placeholder="Motif de restitution…" />
                </UFormField>
              </div>
              <div class="vod-actions-split" style="margin-top:12px">
                <PitButton color="secondary" @click="extendMandate">Prolonger le mandat</PitButton>
                <PitButton color="danger" @click="restituerDepot">Restituer au déposant</PitButton>
              </div>
            </PitCard>

            <PitCard class="bg-white" v-if="detail.status === 'actif'">
              <template #header>
                <div class="vod-card-head">
                  <div class="vod-card-title">Vente du véhicule</div>
                  <PitButton v-if="detail.canSell && !showSale" size="xs" color="primary" @click="showSale = true">Ouvrir</PitButton>
                </div>
              </template>

              <div v-if="!detail.canSell && depotSaleVerdictMessages.length" class="vod-status-banner is-danger">
                <strong>Vente verrouillée</strong>
                <span>{{ detail.saleVerdict?.summary || depotSaleVerdictMessages.join(', ') }}</span>
              </div>
              <div v-else-if="!detail.canSell && detail.companion?.steps && !detail.companion.steps.allComplete" class="vod-status-banner is-warning">
                <strong>Parcours PDA incomplet</strong>
                <span>Le contrat PDA doit être finalisé avant de vendre le véhicule.</span>
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
                <div v-if="netDeposantPreview" class="vod-status-banner is-info" style="margin-top:8px">
                  <strong>Net déposant estimé : {{ formatPrice(netDeposantPreview) }}</strong>
                </div>
                <div class="vod-actions-end" style="margin-top:12px">
                  <PitButton color="primary" @click="sellVehicle">{{ selling ? 'Enregistrement…' : 'Enregistrer la vente' }}</PitButton>
                </div>
              </div>
            </PitCard>
          </div>
        </div>
      </div>

    </div>

    <VORdvWizard
      v-if="showRdvWizard"
      :open="showRdvWizard"
      vo-type="depot_vente"
      :vo-id="Number(route.params.id)"
      :vehicule="detail?.vehicule"
      :deposant="detail?.deposant"
      :mandat-travaux="detail?.mandatTravaux"
      :mandat-travaux-plafond="detail?.mandatTravauxPlafond"
      :atelier-id="detail?.atelierId"
      @update:open="showRdvWizard = $event"
      @created="onRdvCreated"
    />
  </div>
</template>

<script setup lang="ts">
import { useVoStore } from '~/stores/vo'

definePageMeta({
  layout: 'default', title: 'Dossier dépôt VO' })

const route = useRoute()
const voStore = useVoStore()
const toast = useToast()
const { apiBase, formatDate, formatPrice, searchClients } = useVoHelpers()
const { openPdf } = usePdfDownload()
const api = useApi()

const detail = ref<any | null>(null)
const showSale = ref(route.query.sell === '1')
const selling = ref(false)
const extensionDays = ref('30')
const restitutionNotes = ref('')
const buyerSearch = ref('')
const buyerResults = ref<any[]>([])
const selectedBuyer = ref<any | null>(null)
const simulationPrice = ref('')
const simulating = ref(false)
const depotSimulation = ref<any | null>(null)
const activeTab = ref<'mandat' | 'dossier' | 'gestion'>('mandat')
const tabs = computed(() => [
  { id: 'mandat' as const, label: 'Mandat', badge: null },
  { id: 'dossier' as const, label: 'Dossier & simulation', badge: null },
  { id: 'gestion' as const, label: 'Gestion & vente', badge: voRdvs.value.length || null },
])

const saleForm = reactive({
  salePrice: '',
  notes: '',
})

const depotRequiredDocs = ['contrat_depot_vente', 'carte_grise']
const legalChecklist = computed(() => detail.value?.legalChecklist || [])
const depotSaleVerdictMessages = computed(() => (detail.value?.saleVerdict?.reasons || []).map((reason: any) => reason?.message).filter(Boolean))

const netDeposantPreview = computed(() => {
  const salePrice = Number.parseFloat(String(saleForm.salePrice || '0').replace(',', '.')) || 0
  const commissionType = detail.value?.commissionType || 'pourcentage'
  const commissionValue = Number.parseFloat(String(detail.value?.commissionValeur || '0').replace(',', '.')) || 0
  const commissionHt = commissionType === 'pourcentage' ? (salePrice * commissionValue) / 100 : commissionValue
  const commissionTtc = commissionHt * 1.2
  return salePrice - commissionTtc
})

const deposantPct = computed(() => {
  if (!depotSimulation.value) return 0
  const total = Number.parseFloat(depotSimulation.value.prix_vente) || 0
  if (total <= 0) return 0
  return Math.round((Number.parseFloat(depotSimulation.value.net_deposant) / total) * 100)
})

const atelierPct = computed(() => {
  if (!depotSimulation.value) return 0
  const total = Number.parseFloat(depotSimulation.value.prix_vente) || 0
  if (total <= 0) return 0
  return Math.round((Number.parseFloat(depotSimulation.value.commission_ttc) / total) * 100)
})

const presentDocumentTypes = computed(() => new Set((detail.value?.documents || []).map((document: any) => document.type)))

const documentCompletion = computed(() => {
  const completed = depotRequiredDocs.filter(type => presentDocumentTypes.value.has(type)).length
  return Math.round((completed / depotRequiredDocs.length) * 100)
})

const mandateProgress = computed(() => {
  const total = Number(detail.value?.dureeMandat || 0)
  if (!total) return 0

  const rest = Math.max(0, Number(detail.value?.joursRestants ?? total))
  const elapsed = Math.min(total, Math.max(0, total - rest))
  return Math.round((elapsed / total) * 100)
})

const mandateState = computed(() => {
  if (!detail.value) {
    return { tone: 'neutral', label: 'Chargement', text: 'Préparation du mandat.' }
  }

  if (detail.value.status === 'vendu') {
    return { tone: 'success', label: 'Vendu', text: 'Le dépôt a été transformé en vente et facturé.' }
  }

  if (detail.value.status === 'restitue') {
    return { tone: 'neutral', label: 'Restitué', text: 'Le véhicule a été restitué au déposant.' }
  }

  if (detail.value.mandatExpire) {
    return { tone: 'warning', label: 'Mandat expiré', text: 'Le mandat doit être prolongé ou le véhicule restitué.' }
  }

  if (depotSaleVerdictMessages.value.length) {
    return {
      tone: 'warning',
      label: detail.value.saleVerdict?.label || 'Dossier à régulariser',
      text: detail.value.saleVerdict?.summary || depotSaleVerdictMessages.value.join(' '),
    }
  }

  if (detail.value.companion?.steps && !detail.value.companion.steps.allComplete) {
    return {
      tone: 'warning',
      label: 'Parcours PDA à finir',
      text: 'Le contrat, les pièces et la signature déposant doivent être validés avant la vente.',
    }
  }

  if (Number(detail.value.joursRestants ?? 999) <= 7) {
    return { tone: 'accent', label: 'Échéance proche', text: `${detail.value.joursRestants} jour(s) restants avant expiration.` }
  }

  return { tone: 'success', label: 'Mandat actif', text: 'Le véhicule peut être proposé à la vente ou suivi normalement.' }
})

const workflowSteps = computed(() => {
  const status = detail.value?.status
  const phase = status === 'vendu' ? 'vendu' : status === 'restitue' ? 'restitue' : 'actif'

  return [
    { label: 'Actif', state: phase === 'actif' ? 'current' : ['vendu', 'restitue'].includes(phase) ? 'done' : 'idle' },
    { label: 'Clôture', state: phase === 'vendu' ? 'current' : 'idle' },
    { label: 'Restitution', state: phase === 'restitue' ? 'current' : 'idle' },
  ]
})

let buyerTimer: ReturnType<typeof setTimeout> | null = null

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

async function loadDetail() {
  detail.value = await voStore.fetchDepotFull(Number(route.params.id))
  saleForm.salePrice = detail.value?.prixVenteSouhaite || ''
  simulationPrice.value = detail.value?.prixVenteSouhaite || ''
}

async function runDepotSimulation() {
  if (!detail.value) return
  simulating.value = true
  try {
    depotSimulation.value = await voStore.simulateDepotSale(Number(route.params.id), {
      salePrice: simulationPrice.value || detail.value.prixVenteSouhaite,
    })
  } catch (error: unknown) {
    toast.add({ title: 'Erreur simulation', description: error instanceof Error ? error.message : 'Erreur inconnue', color: 'error' })
  } finally {
    simulating.value = false
  }
}

function focusCompanion() {
  if (!import.meta.client) return
  document.getElementById('vo-companion-zone')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

function downloadContrat() {
  openPdf(`/vo/depots/${route.params.id}/contrat/pdf`)
}

function downloadMandat() {
  const buyerId = selectedBuyer.value?.id ? `?buyerId=${selectedBuyer.value.id}` : ''
  openPdf(`/vo/depots/${route.params.id}/mandat-immat/pdf${buyerId}`)
}

async function extendMandate() {
  if (!detail.value) return
  const additional = Number.parseInt(extensionDays.value, 10)
  if (!Number.isFinite(additional) || additional <= 0) {
    toast.add({ title: 'Erreur', description: 'Renseignez un nombre de jours valide', color: 'error' })
    return
  }

  try {
    await voStore.prolongerMandat(Number(route.params.id), {
      dureeSupplementaire: Number(detail.value.dureeMandat || 0) + additional,
    })
    toast.add({ title: 'Mandat prolongé', color: 'success' })
    await loadDetail()
  } catch (error: unknown) {
    toast.add({ title: 'Erreur', description: error instanceof Error ? error.message : 'Erreur inconnue', color: 'error' })
  }
}

async function restituerDepot() {
  try {
    await voStore.restituerDepot(Number(route.params.id), {
      notes: restitutionNotes.value || undefined,
    })
    toast.add({ title: 'Dépôt restitué', color: 'success' })
    await loadDetail()
  } catch (error: unknown) {
    toast.add({ title: 'Erreur', description: error instanceof Error ? error.message : 'Erreur inconnue', color: 'error' })
  }
}

async function sellVehicle() {
  if (!selectedBuyer.value?.id) {
    toast.add({ title: 'Erreur', description: 'Sélectionnez un acheteur', color: 'error' })
    return
  }

  selling.value = true
  try {
    await voStore.sellDepot(Number(route.params.id), {
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
    voRdvs.value = await api.get(`/vo/depot-ventes/${route.params.id}/rdv`)
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
.vod-topbar { display:flex; align-items:center; gap:16px; padding:12px 0 10px; flex-wrap:wrap; }
.vod-back { color:var(--text-secondary); font-size:13px; text-decoration:none; white-space:nowrap; }
.vod-back:hover { color:var(--accent); }
.vod-topbar-mid { display:flex; align-items:center; gap:10px; flex:1; min-width:0; flex-wrap:wrap; }
.vod-title { font-weight:700; font-size:17px; color:var(--text-primary); }
.vod-plate { font-size:12px; font-weight:700; letter-spacing:.05em; background:var(--bg-elevated); border:1px solid var(--border-default); border-radius:6px; padding:2px 8px; color:var(--text-secondary); }
.vod-expire-pill { background:rgba(239,68,68,.12); color:#dc2626; font-size:11px; font-weight:700; border-radius:999px; padding:2px 10px; }
.vod-warn-pill { background:rgba(245,158,11,.12); color:#b45309; font-size:11px; font-weight:700; border-radius:999px; padding:2px 10px; }
.vod-topbar-actions { display:flex; gap:8px; flex-wrap:wrap; }
.vod-loading { padding:48px; text-align:center; color:var(--text-secondary); }

.vod-kpi-strip { display:flex; flex-wrap:wrap; background:var(--bg-surface); border:1px solid var(--border-default); border-radius:12px; margin-bottom:12px; overflow:hidden; }
.vod-kpi-item { display:flex; flex-direction:column; gap:3px; padding:10px 18px; flex:1; min-width:100px; border-right:1px solid var(--border-default); }
.vod-kpi-item:last-child { border-right:none; }
.vod-kpi-item span { font-size:11px; color:var(--text-tertiary); text-transform:uppercase; letter-spacing:.04em; }
.vod-kpi-item strong { font-size:15px; font-weight:700; color:var(--text-primary); }
.vod-kpi-verdict.is-success { background:rgba(34,197,94,.07); }
.vod-kpi-verdict.is-warning { background:rgba(245,158,11,.07); }
.vod-kpi-verdict.is-accent  { background:rgba(59,130,246,.07); }
.vod-kpi-verdict.is-neutral { background:var(--bg-elevated); }

.vod-blocages { display:grid; gap:6px; margin-bottom:12px; }
.vod-blocage-item { display:flex; align-items:center; gap:8px; padding:8px 12px; border-radius:8px; font-size:13px; border:1px solid transparent; }
.vod-blocage-item.is-critical { background:rgba(239,68,68,.06); border-color:rgba(239,68,68,.18); color:#dc2626; }
.vod-blocage-item.is-warning  { background:rgba(245,158,11,.06); border-color:rgba(245,158,11,.18); color:#b45309; }
.vod-blocage-item span { flex:1; }
.vod-blocage-item strong { margin-left:auto; white-space:nowrap; }

.vod-tabs { display:flex; gap:2px; border-bottom:2px solid var(--border-default); margin-bottom:20px; }
.vod-tab { padding:10px 20px; font-size:14px; font-weight:600; color:var(--text-secondary); background:none; border:none; cursor:pointer; border-bottom:3px solid transparent; margin-bottom:-2px; display:flex; align-items:center; gap:6px; transition:color .15s, border-color .15s; }
.vod-tab:hover { color:var(--text-primary); }
.vod-tab.active { color:var(--accent); border-bottom-color:var(--accent); }
.vod-tab-badge { background:var(--accent); color:#fff; font-size:10px; font-weight:700; border-radius:999px; min-width:18px; height:18px; display:flex; align-items:center; justify-content:center; padding:0 4px; }

.vod-grid { display:grid; grid-template-columns:minmax(0,1.2fr) minmax(280px,.9fr); gap:16px; align-items:start; }
.vod-stack { display:grid; gap:16px; }

.vod-card-head { display:flex; align-items:center; justify-content:space-between; gap:12px; }
.vod-card-title { font-weight:700; color:var(--text-primary); font-size:14px; }
.vod-doc-actions { display:flex; gap:6px; flex-wrap:wrap; }
.vod-kpi-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
.vod-kpi-grid > div { display:grid; gap:3px; }
.vod-k { font-size:11px; color:var(--text-tertiary); text-transform:uppercase; letter-spacing:.04em; }
.vod-kpi-grid strong { font-size:14px; font-weight:600; color:var(--text-primary); }
.vod-mono { font-family:monospace; font-size:13px; }
.vod-note-box { margin-top:10px; padding:10px 12px; border-radius:8px; background:var(--bg-elevated); border:1px solid var(--border-default); font-size:13px; color:var(--text-secondary); }
.vod-form-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
.vod-field-full { grid-column:1/-1; }
.vod-actions-end { display:flex; justify-content:flex-end; gap:8px; }
.vod-actions-split { display:flex; gap:8px; flex-wrap:wrap; }

.vod-status-banner { display:grid; gap:4px; padding:10px 14px; border-radius:10px; border:1px solid transparent; font-size:13px; }
.vod-status-banner strong { font-size:14px; }
.vod-status-banner.is-success { background:rgba(34,197,94,.08); border-color:rgba(34,197,94,.2); color:#166534; }
.vod-status-banner.is-warning { background:rgba(245,158,11,.08); border-color:rgba(245,158,11,.2); color:#92400e; }
.vod-status-banner.is-danger  { background:rgba(239,68,68,.08); border-color:rgba(239,68,68,.2); color:#991b1b; }
.vod-status-banner.is-info    { background:rgba(59,130,246,.08); border-color:rgba(59,130,246,.2); color:#1e40af; }

.vod-checklist { display:grid; gap:8px; }
.vod-check-row { display:flex; align-items:center; gap:8px; font-size:13px; color:var(--text-primary); }
.vod-check-status { font-size:11px; font-weight:700; padding:2px 7px; border-radius:999px; white-space:nowrap; }
.vod-check-status.is-ok      { background:rgba(34,197,94,.12); color:#166534; }
.vod-check-status.is-ko      { background:rgba(239,68,68,.12); color:#991b1b; }
.vod-check-status.is-warn    { background:rgba(245,158,11,.12); color:#92400e; }
.vod-check-status.is-neutral { background:var(--bg-elevated); color:var(--text-tertiary); }
.vod-empty { color:var(--text-tertiary); font-size:13px; padding:8px 0; }

.vod-rdv-list { display:grid; gap:8px; }
.vod-rdv-row { display:flex; align-items:center; gap:12px; padding:10px 12px; border-radius:10px; background:var(--bg-elevated); border:1px solid var(--border-default); }
.vod-rdv-type { font-size:13px; font-weight:600; color:var(--text-primary); }
.vod-rdv-meta { font-size:12px; color:var(--text-tertiary); }
.vod-link { font-size:12px; color:var(--accent); text-decoration:none; white-space:nowrap; }

.vod-sale-form { display:grid; gap:12px; margin-top:12px; }
.vod-search-list { display:grid; gap:4px; margin-top:4px; }
.vod-search-item { display:grid; gap:2px; text-align:left; padding:8px 12px; border-radius:8px; background:var(--bg-elevated); border:1px solid var(--border-default); cursor:pointer; font-size:13px; color:var(--text-primary); }
.vod-search-item:hover { border-color:var(--accent); }
.vod-search-item span { font-size:11px; color:var(--text-tertiary); }
.vod-selected-box { display:flex; align-items:center; gap:8px; padding:8px 12px; border-radius:8px; background:rgba(34,197,94,.07); border:1px solid rgba(34,197,94,.2); font-size:13px; }

.vod-sim-result { display:grid; gap:0; margin-top:12px; border:1px solid var(--border-default); border-radius:10px; overflow:hidden; }
.vod-sim-row { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:8px 12px; border-bottom:1px solid var(--border-default); font-size:13px; }
.vod-sim-row:last-child { border-bottom:none; }
.vod-sim-bar { display:flex; height:28px; border-radius:0; overflow:hidden; font-size:10px; font-weight:700; }
.vod-sim-bar-deposant { background:rgba(59,130,246,.7); color:#fff; display:flex; align-items:center; justify-content:center; min-width:30px; transition:width .3s ease; }
.vod-sim-bar-atelier  { background:rgba(34,197,94,.7); color:#fff; display:flex; align-items:center; justify-content:center; min-width:30px; transition:width .3s ease; }

.text-success { color:#16a34a; }
.text-danger  { color:#dc2626; }
.text-warning { color:#d97706; }
.text-info    { color:#2563eb; }

@media (max-width:1000px) {
  .vod-grid { grid-template-columns:1fr; }
  .vod-kpi-strip { display:grid; grid-template-columns:repeat(3, 1fr); }
  .vod-kpi-item { padding:8px 10px; border-right:1px solid var(--border-default); border-bottom:1px solid var(--border-default); }
  .vod-kpi-item:nth-child(3n) { border-right:none; }
  .vod-kpi-item:nth-last-child(-n+3) { border-bottom:none; }
}
</style>
