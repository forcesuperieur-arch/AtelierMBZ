<template>
  <div>
    <div class="page-header vo-header">
      <div>
        <NuxtLink to="/vo/depots" class="vo-back-link">← Retour aux dépôts</NuxtLink>
        <div class="page-title">Dossier dépôt #{{ route.params.id }}</div>
        <div class="vo-subtitle">Vue complète du mandat, des documents, de la restitution et de la vente.</div>
      </div>
      <div class="vo-header-actions">
        <button class="topbar-new-btn vo-secondary-btn" @click="downloadContrat">Contrat PDF</button>
        <button v-if="detail?.status === 'actif'" class="topbar-new-btn" :disabled="!detail?.canSell" @click="showSale = true">Vendre</button>
      </div>
    </div>

    <VONav />

    <div v-if="!detail" class="vo-loading">Chargement du dossier...</div>

    <div v-else class="vo-detail-grid">
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
              <span class="vo-k">Déposant</span>
              <strong>{{ detail.deposant?.prenom }} {{ detail.deposant?.nom }}</strong>
            </div>
            <div>
              <span class="vo-k">Gestionnaire</span>
              <strong>{{ detail.gestionnaire?.prenom || detail.gestionnaire?.username || 'Non assigné' }}</strong>
            </div>
            <div>
              <span class="vo-k">Début mandat</span>
              <strong>{{ formatDate(detail.dateDebut) }}</strong>
            </div>
            <div>
              <span class="vo-k">Fin mandat</span>
              <strong>{{ formatDate(detail.dateFin) }}</strong>
            </div>
          </div>

          <div class="vo-note-box" v-if="detail.notes">
            {{ detail.notes }}
          </div>
        </UCard>

        <VODossierMotoCard
          mode="depot"
          :dossier-id="Number(route.params.id)"
          :vehicule="detail.vehicule"
          :documents="detail.documents || []"
          :missing-documents="detail.missingDocuments || []"
          :reload-detail="loadDetail"
        />

        <VOCompanionCard
          :companion="detail.companion"
          :generated-documents="detail.generatedDocuments || []"
        />

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
            <div class="vo-card-title">État du mandat</div>
          </template>

          <div class="vo-health-box" :class="`is-${mandateState.tone}`">
            <strong>{{ mandateState.label }}</strong>
            <span>{{ mandateState.text }}</span>
          </div>

          <div class="vo-progress-block">
            <div class="vo-progress-head">
              <span>Conformité dépôt</span>
              <strong>{{ documentCompletion }}%</strong>
            </div>
            <div class="vo-progress-bar"><span :style="{ width: `${documentCompletion}%` }" /></div>
          </div>

          <div class="vo-progress-block">
            <div class="vo-progress-head">
              <span>Cycle du mandat</span>
              <strong>{{ mandateProgress }}%</strong>
            </div>
            <div class="vo-progress-bar is-secondary"><span :style="{ width: `${mandateProgress}%` }" /></div>
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
            <div class="vo-card-title">Mandat & commission</div>
          </template>

          <div class="vo-kpi-grid">
            <div>
              <span class="vo-k">Prix souhaité</span>
              <strong>{{ formatPrice(detail.prixVenteSouhaite) }}</strong>
            </div>
            <div>
              <span class="vo-k">Commission</span>
              <strong>{{ detail.commissionType }} • {{ detail.commissionValeur }}</strong>
            </div>
            <div>
              <span class="vo-k">Commission HT</span>
              <strong>{{ formatPrice(detail.commissionAmount || 0) }}</strong>
            </div>
            <div>
              <span class="vo-k">Commission TTC</span>
              <strong>{{ formatPrice(detail.commissionTtc || 0) }}</strong>
            </div>
            <div>
              <span class="vo-k">Net déposant</span>
              <strong>{{ formatPrice(detail.deposantNet || 0) }}</strong>
            </div>
            <div>
              <span class="vo-k">Mandat</span>
              <strong :style="{ color: detail.mandatExpire ? '#ef4444' : Number(detail.joursRestants ?? 999) <= 7 ? '#f59e0b' : '#e8e9ed' }">{{ detail.mandatExpire ? 'Expiré' : `${detail.joursRestants} jour(s) restants` }}</strong>
            </div>
          </div>
        </UCard>

        <UCard v-if="detail.status === 'actif'">
          <template #header>
            <div class="vo-card-title">Actions mandat</div>
          </template>

          <div v-if="!detail.canSell && detail.companion?.steps && !detail.companion.steps.allComplete" class="vo-warning-box">
            <strong>Vente verrouillée</strong>
            <span>Le contrat PDA doit être finalisé avant de vendre le véhicule.</span>
          </div>

          <div class="vo-form-grid">
            <label class="vo-field">
              <span>Prolonger le mandat de (jours)</span>
              <input v-model="extensionDays" type="number" class="vo-input" />
            </label>
            <label class="vo-field vo-field-full">
              <span>Note de restitution</span>
              <textarea v-model="restitutionNotes" class="vo-textarea" rows="3" />
            </label>
          </div>

          <div class="vo-inline-actions split">
            <button class="vo-secondary-cta" @click="extendMandate">Prolonger</button>
            <button class="topbar-new-btn vo-danger-btn" @click="restituerDepot">Restituer le véhicule</button>
          </div>
        </UCard>

        <UCard v-if="showSale && detail.canSell">
          <template #header>
            <div class="vo-card-title">Vendre le véhicule</div>
          </template>

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

          <div class="vo-info-box">
            <span>Net déposant estimé: <strong>{{ formatPrice(netDeposantPreview) }}</strong></span>
          </div>

          <div class="vo-inline-actions">
            <button class="topbar-new-btn" :disabled="selling" @click="sellVehicle">{{ selling ? 'Vente...' : 'Enregistrer la vente' }}</button>
          </div>
        </UCard>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useVoStore } from '~/stores/vo'

definePageMeta({ title: 'Dossier dépôt VO' })

const route = useRoute()
const voStore = useVoStore()
const toast = useToast()
const { apiBase, formatDate, formatPrice, searchClients } = useVoHelpers()

const detail = ref<any | null>(null)
const showSale = ref(route.query.sell === '1')
const selling = ref(false)
const extensionDays = ref('30')
const restitutionNotes = ref('')
const buyerSearch = ref('')
const buyerResults = ref<any[]>([])
const selectedBuyer = ref<any | null>(null)

const saleForm = reactive({
  salePrice: '',
  notes: '',
})

const depotRequiredDocs = ['contrat_depot_vente', 'carte_grise', 'piece_identite']

const netDeposantPreview = computed(() => {
  const salePrice = Number.parseFloat(String(saleForm.salePrice || '0').replace(',', '.')) || 0
  const commissionType = detail.value?.commissionType || 'pourcentage'
  const commissionValue = Number.parseFloat(String(detail.value?.commissionValeur || '0').replace(',', '.')) || 0
  const commissionHt = commissionType === 'pourcentage' ? (salePrice * commissionValue) / 100 : commissionValue
  const commissionTtc = commissionHt * 1.2
  return salePrice - commissionTtc
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
}

function downloadContrat() {
  window.open(`${apiBase}/vo/depots/${route.params.id}/contrat/pdf`, '_blank')
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
  } catch (error: any) {
    toast.add({ title: 'Erreur', description: error.message, color: 'error' })
  }
}

async function restituerDepot() {
  try {
    await voStore.restituerDepot(Number(route.params.id), {
      notes: restitutionNotes.value || undefined,
    })
    toast.add({ title: 'Dépôt restitué', color: 'success' })
    await loadDetail()
  } catch (error: any) {
    toast.add({ title: 'Erreur', description: error.message, color: 'error' })
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
  } catch (error: any) {
    toast.add({ title: 'Erreur', description: error.message, color: 'error' })
  } finally {
    selling.value = false
  }
}

onMounted(loadDetail)
</script>

<style scoped>
.vo-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
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

.vo-danger-btn {
  background: #dc2626;
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
.vo-search-list {
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

.vo-inline-actions.split {
  justify-content: space-between;
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

.vo-link-btn {
  background: none;
  border: none;
  color: #f59e0b;
  cursor: pointer;
  font-size: 12px;
  font-weight: 700;
  padding: 0;
}

.vo-secondary-cta {
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid #374151;
  background: #111827;
  color: #e8e9ed;
}

@media (max-width: 1100px) {
  .vo-detail-grid,
  .vo-kpi-grid,
  .vo-form-grid {
    grid-template-columns: 1fr;
  }

  .vo-inline-actions.split {
    flex-direction: column;
  }
}
</style>