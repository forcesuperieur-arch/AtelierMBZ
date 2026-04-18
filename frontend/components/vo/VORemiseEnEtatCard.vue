<template>
  <UCard>
    <template #header>
      <div class="vo-card-head">
        <div>
          <div class="vo-card-title">Remise en etat VO</div>
          <div class="vo-card-subtitle">Campagnes atelier, prestations catalogue, workflow pieces et blocage de vente.</div>
        </div>

        <div class="vo-inline-actions split compact">
          <NuxtLink to="/vo/remises-en-etat" class="vo-link-btn">File atelier VO</NuxtLink>
          <button v-if="canCreateCampaign" type="button" class="topbar-new-btn" :disabled="creatingCampaign" @click="createCampaign">
            {{ creatingCampaign ? 'Creation...' : 'Nouvelle campagne' }}
          </button>
        </div>
      </div>
    </template>

    <div v-if="!campaigns.length" class="vo-info-box">
      <strong>Aucune campagne active ou historique.</strong>
      <span>Crée une remise en etat VO dès qu'un chiffrage atelier doit être tracé et suivi.</span>
    </div>

    <div v-else class="vo-revo-stack">
      <div class="vo-revo-tabs">
        <button
          v-for="campaign in campaigns"
          :key="campaign.id"
          type="button"
          class="vo-revo-tab"
          :class="{ 'is-active': selectedCampaignId === campaign.id, 'is-blocking': campaign.isBlockingSale }"
          @click="selectedCampaignId = campaign.id"
        >
          <strong>{{ campaign.titre }}</strong>
          <span>{{ campaign.status }}</span>
        </button>
      </div>

      <div v-if="selectedCampaign" class="vo-revo-grid">
        <div class="vo-revo-main">
          <div v-if="selectedCampaign.isBlockingSale" class="vo-warning-box">
            <strong>Vente bloquée</strong>
            <span>La vente reste verrouillée tant que cette campagne n'est pas clôturée.</span>
          </div>

          <div class="vo-form-grid">
            <label class="vo-field">
              <span>Titre</span>
              <input v-model="campaignForm.titre" class="vo-input" />
            </label>
            <label class="vo-field">
              <span>Priorité</span>
              <select v-model="campaignForm.priority" class="vo-select">
                <option v-for="priority in priorities" :key="priority" :value="priority">{{ priority }}</option>
              </select>
            </label>
            <label class="vo-field">
              <span>Statut</span>
              <select v-model="campaignForm.status" class="vo-select">
                <option v-for="status in campaignStatuses" :key="status" :value="status">{{ status }}</option>
              </select>
            </label>
            <label class="vo-field">
              <span>Planifiée pour</span>
              <input v-model="campaignForm.plannedFor" type="datetime-local" class="vo-input" />
            </label>
            <label class="vo-field vo-field-full">
              <span>Diagnostic VO</span>
              <textarea v-model="campaignForm.diagnosticNotes" class="vo-textarea" rows="3" />
            </label>
            <label class="vo-field vo-field-full">
              <span>Notes atelier</span>
              <textarea v-model="campaignForm.workshopNotes" class="vo-textarea" rows="3" />
            </label>
            <label class="vo-field vo-field-full">
              <span>Arbitrage business</span>
              <textarea v-model="campaignForm.businessNotes" class="vo-textarea" rows="3" />
            </label>
          </div>

          <div class="vo-inline-actions">
            <button type="button" class="topbar-new-btn" :disabled="savingCampaign" @click="saveCampaign">
              {{ savingCampaign ? 'Enregistrement...' : 'Enregistrer la campagne' }}
            </button>
          </div>

          <div class="vo-sim-box vo-revo-summary-grid">
            <div>
              <span class="vo-summary-label">MO estimée</span>
              <strong>{{ formatPrice(selectedCampaign.costSummary.estimatedMoCost || 0) }}</strong>
            </div>
            <div>
              <span class="vo-summary-label">Pièces estimées</span>
              <strong>{{ formatPrice(selectedCampaign.costSummary.estimatedPartsCost || 0) }}</strong>
            </div>
            <div>
              <span class="vo-summary-label">Total estimé</span>
              <strong>{{ formatPrice(selectedCampaign.costSummary.estimatedTotalCost || 0) }}</strong>
            </div>
            <div>
              <span class="vo-summary-label">Total réel</span>
              <strong>{{ formatPrice(selectedCampaign.costSummary.actualTotalCost || 0) }}</strong>
            </div>
            <div>
              <span class="vo-summary-label">Écart</span>
              <strong :style="{ color: Number(selectedCampaign.costSummary.varianceTotal || 0) > 0 ? '#f59e0b' : '#22c55e' }">{{ formatPrice(selectedCampaign.costSummary.varianceTotal || 0) }}</strong>
            </div>
            <div>
              <span class="vo-summary-label">Pièces en attente</span>
              <strong>{{ selectedCampaign.pendingPiecesCount }}</strong>
            </div>
          </div>

          <div class="vo-subsection">
            <div class="vo-subtitle-row">
              <strong>Prestations atelier</strong>
              <span class="vo-hint">Tarifs issus du catalogue atelier applicables au véhicule.</span>
            </div>

            <div class="vo-inline-form vo-inline-form--3">
              <select v-model="newLine.prestationId" class="vo-select grow">
                <option :value="null">Choisir une prestation</option>
                <option v-for="prestation in applicablePrestations" :key="prestation.prestationId" :value="prestation.prestationId">
                  {{ prestation.nom }} • {{ formatPrice(prestation.prixHt || 0) }} HT • {{ prestation.tempsMinutes }} min
                </option>
              </select>
              <input v-model="newLine.quantity" type="number" min="1" class="vo-input" placeholder="Qté" />
              <button type="button" class="vo-link-btn" :disabled="addingLine" @click="addLine">Ajouter</button>
            </div>

            <div v-if="selectedCampaign.lignes.length" class="vo-lines">
              <div v-for="line in selectedCampaign.lignes" :key="line.id" class="vo-revo-row">
                <div class="vo-revo-row-head">
                  <strong>{{ line.libelle }}</strong>
                  <button type="button" class="vo-link-btn danger" :disabled="removingLineId === line.id" @click="removeLine(line.id)">Supprimer</button>
                </div>

                <div class="vo-form-grid compact-grid">
                  <label class="vo-field">
                    <span>Qté</span>
                    <input v-model="lineForms[line.id].quantity" type="number" min="1" class="vo-input" />
                  </label>
                  <label class="vo-field">
                    <span>Statut</span>
                    <select v-model="lineForms[line.id].status" class="vo-select">
                      <option v-for="status in lineStatuses" :key="status" :value="status">{{ status }}</option>
                    </select>
                  </label>
                  <label class="vo-field">
                    <span>Coût HT estimé</span>
                    <input :value="formatPrice(line.estimatedTotalHt || 0)" class="vo-input" disabled />
                  </label>
                  <label class="vo-field">
                    <span>Coût HT réel</span>
                    <input v-model="lineForms[line.id].actualTotalHt" class="vo-input" />
                  </label>
                  <label class="vo-field">
                    <span>Temps estimé</span>
                    <input :value="`${line.estimatedMinutes} min`" class="vo-input" disabled />
                  </label>
                  <label class="vo-field">
                    <span>Temps réel</span>
                    <input v-model="lineForms[line.id].actualMinutes" type="number" min="0" class="vo-input" />
                  </label>
                  <label class="vo-field vo-field-full">
                    <span>Notes</span>
                    <textarea v-model="lineForms[line.id].notes" class="vo-textarea" rows="2" />
                  </label>
                </div>

                <div class="vo-inline-actions">
                  <button type="button" class="vo-link-btn" :disabled="savingLineId === line.id" @click="saveLine(line.id)">
                    {{ savingLineId === line.id ? 'Enregistrement...' : 'Enregistrer la ligne' }}
                  </button>
                </div>
              </div>
            </div>
            <div v-else class="vo-empty-box">Aucune prestation ajoutée pour cette campagne.</div>
          </div>

          <div class="vo-subsection">
            <div class="vo-subtitle-row">
              <strong>Pièces</strong>
              <span class="vo-hint">Workflow : en stock, à commander, commandée, reçue, montée.</span>
            </div>

            <div class="vo-form-grid compact-grid">
              <label class="vo-field">
                <span>Libellé</span>
                <input v-model="newPiece.libelle" class="vo-input" />
              </label>
              <label class="vo-field">
                <span>Référence</span>
                <input v-model="newPiece.reference" class="vo-input" />
              </label>
              <label class="vo-field">
                <span>Qté</span>
                <input v-model="newPiece.quantity" type="number" min="1" class="vo-input" />
              </label>
              <label class="vo-field">
                <span>Fournisseur</span>
                <input v-model="newPiece.supplier" class="vo-input" />
              </label>
              <label class="vo-field">
                <span>Coût unitaire HT</span>
                <input v-model="newPiece.estimatedUnitCostHt" class="vo-input" />
              </label>
              <label class="vo-field">
                <span>Statut</span>
                <select v-model="newPiece.status" class="vo-select">
                  <option v-for="status in pieceStatuses" :key="status" :value="status">{{ status }}</option>
                </select>
              </label>
              <label class="vo-field vo-field-full">
                <span>Notes</span>
                <textarea v-model="newPiece.notes" class="vo-textarea" rows="2" />
              </label>
            </div>

            <div class="vo-inline-actions">
              <button type="button" class="vo-link-btn" :disabled="addingPiece" @click="addPiece">
                {{ addingPiece ? 'Ajout...' : 'Ajouter la pièce' }}
              </button>
            </div>

            <div v-if="selectedCampaign.pieces.length" class="vo-lines">
              <div v-for="piece in selectedCampaign.pieces" :key="piece.id" class="vo-revo-row">
                <div class="vo-revo-row-head">
                  <strong>{{ piece.libelle }}</strong>
                  <button type="button" class="vo-link-btn danger" :disabled="removingPieceId === piece.id" @click="removePiece(piece.id)">Supprimer</button>
                </div>

                <div class="vo-form-grid compact-grid">
                  <label class="vo-field">
                    <span>Référence</span>
                    <input v-model="pieceForms[piece.id].reference" class="vo-input" />
                  </label>
                  <label class="vo-field">
                    <span>Qté</span>
                    <input v-model="pieceForms[piece.id].quantity" type="number" min="1" class="vo-input" />
                  </label>
                  <label class="vo-field">
                    <span>Fournisseur</span>
                    <input v-model="pieceForms[piece.id].supplier" class="vo-input" />
                  </label>
                  <label class="vo-field">
                    <span>Statut</span>
                    <select v-model="pieceForms[piece.id].status" class="vo-select">
                      <option v-for="status in pieceStatuses" :key="status" :value="status">{{ status }}</option>
                    </select>
                  </label>
                  <label class="vo-field">
                    <span>Coût HT estimé</span>
                    <input :value="formatPrice(piece.estimatedTotalCostHt || 0)" class="vo-input" disabled />
                  </label>
                  <label class="vo-field">
                    <span>Coût HT réel</span>
                    <input v-model="pieceForms[piece.id].actualTotalCostHt" class="vo-input" />
                  </label>
                  <label class="vo-field vo-field-full">
                    <span>Notes</span>
                    <textarea v-model="pieceForms[piece.id].notes" class="vo-textarea" rows="2" />
                  </label>
                </div>

                <div class="vo-inline-actions">
                  <button type="button" class="vo-link-btn" :disabled="savingPieceId === piece.id" @click="savePiece(piece.id)">
                    {{ savingPieceId === piece.id ? 'Enregistrement...' : 'Enregistrer la pièce' }}
                  </button>
                </div>
              </div>
            </div>
            <div v-else class="vo-empty-box">Aucune pièce suivie dans cette campagne.</div>
          </div>
        </div>

        <div class="vo-revo-side">
          <div class="vo-info-box">
            <strong>Campagne #{{ selectedCampaign.campaignIndex }}</strong>
            <span>Demandée le {{ formatDate(selectedCampaign.requestedAt) }}</span>
            <span v-if="selectedCampaign.validatedAt">Validée le {{ formatDate(selectedCampaign.validatedAt) }}</span>
            <span v-if="selectedCampaign.closedAt">Clôturée le {{ formatDate(selectedCampaign.closedAt) }}</span>
          </div>

          <div class="vo-info-box" v-if="selectedCampaign.vehicle">
            <strong>Véhicule</strong>
            <span>{{ selectedCampaign.vehicle.marque || '—' }} {{ selectedCampaign.vehicle.modele || '' }}</span>
            <span>{{ selectedCampaign.vehicle.plaque || 'Sans plaque' }} • {{ selectedCampaign.vehicle.vin || 'VIN non renseigné' }}</span>
            <span v-if="selectedCampaign.vehicle.categorieNom">Catégorie tarifaire: {{ selectedCampaign.vehicle.categorieNom }}</span>
            <span v-if="selectedCampaign.vehicle.typeMoto">Type atelier: {{ selectedCampaign.vehicle.typeMoto }}</span>
          </div>

          <div class="vo-warning-box" v-if="selectedCampaign.vehicle && !selectedCampaign.vehicle.categorieId">
            <strong>Tarif catégorie non fiabilisé</strong>
            <span>Le véhicule n'a pas de catégorie tarifaire. Les prestations affichent la base catalogue ou le taux horaire, pas une grille catégorie spécifique.</span>
          </div>

          <div class="vo-info-box">
            <strong>Règle métier</strong>
            <span>Une seule campagne active par dossier VO. Pour relancer une préparation, il faut clôturer ou annuler la campagne courante.</span>
          </div>
        </div>
      </div>
    </div>
  </UCard>
</template>

<script setup lang="ts">
import { useVoStore } from '~/stores/vo'
import { buildRefurbishmentLineForms, buildRefurbishmentPieceForms, selectRefurbishmentCampaignId, toRefurbishmentDateTimeLocal } from '~/composables/voRefurbishmentCard'

const props = withDefaults(defineProps<{
  sourceType: 'purchase' | 'depot'
  dossierId: number
  remisesEnEtat?: Array<Record<string, any>>
  activeRemiseEnEtat?: Record<string, any> | null
  canCreate?: boolean
  reloadDetail?: () => Promise<void> | void
}>(), {
  remisesEnEtat: () => [],
  activeRemiseEnEtat: null,
  canCreate: true,
  reloadDetail: undefined,
})

const voStore = useVoStore()
const toast = useToast()
const { formatDate, formatPrice } = useVoHelpers()

const campaignStatuses = [
  'a_chiffrer',
  'a_valider',
  'validee',
  'pieces_a_commander',
  'en_attente_pieces',
  'planifiee_atelier',
  'en_cours',
  'terminee',
  'cloturee',
  'annulee',
]

const priorities = ['basse', 'normale', 'haute', 'urgente']
const lineStatuses = ['proposee', 'validee', 'en_cours', 'terminee', 'annulee']
const pieceStatuses = ['en_stock', 'a_commander', 'commandee', 'recue', 'montee', 'annulee']

const creatingCampaign = ref(false)
const savingCampaign = ref(false)
const addingLine = ref(false)
const addingPiece = ref(false)
const savingLineId = ref<number | null>(null)
const savingPieceId = ref<number | null>(null)
const removingLineId = ref<number | null>(null)
const removingPieceId = ref<number | null>(null)
const selectedCampaignId = ref<number | null>(null)
const applicablePrestations = ref<Array<Record<string, any>>>([])

const campaignForm = reactive({
  titre: '',
  status: 'a_chiffrer',
  priority: 'normale',
  plannedFor: '',
  diagnosticNotes: '',
  workshopNotes: '',
  businessNotes: '',
})

const newLine = reactive({
  prestationId: null as number | null,
  quantity: 1,
  notes: '',
})

const newPiece = reactive({
  libelle: '',
  reference: '',
  quantity: 1,
  supplier: '',
  estimatedUnitCostHt: '0.00',
  status: 'a_commander',
  notes: '',
})

const lineForms = ref<Record<number, any>>({})
const pieceForms = ref<Record<number, any>>({})

const campaigns = computed(() => props.remisesEnEtat || [])
const canCreateCampaign = computed(() => props.canCreate)
const selectedCampaign = computed(() => campaigns.value.find(campaign => campaign.id === selectedCampaignId.value) || null)

watch(campaigns, () => {
  if (!campaigns.value.length) {
    selectedCampaignId.value = null
    return
  }

  selectedCampaignId.value = selectRefurbishmentCampaignId(
    campaigns.value,
    props.activeRemiseEnEtat?.id || null,
    selectedCampaignId.value,
  )
}, { immediate: true, deep: true })

watch(selectedCampaign, async (campaign) => {
  if (!campaign) {
    applicablePrestations.value = []
    return
  }

  campaignForm.titre = campaign.titre || ''
  campaignForm.status = campaign.status || 'a_chiffrer'
  campaignForm.priority = campaign.priority || 'normale'
  campaignForm.plannedFor = toRefurbishmentDateTimeLocal(campaign.plannedFor)
  campaignForm.diagnosticNotes = campaign.diagnosticNotes || ''
  campaignForm.workshopNotes = campaign.workshopNotes || ''
  campaignForm.businessNotes = campaign.businessNotes || ''

  lineForms.value = buildRefurbishmentLineForms(campaign.lignes || [])
  pieceForms.value = buildRefurbishmentPieceForms(campaign.pieces || [])

  try {
    const payload = await voStore.fetchApplicablePrestationsForRefurbishment(campaign.id)
    applicablePrestations.value = payload.items || []
  } catch {
    applicablePrestations.value = []
  }
}, { immediate: true })

async function createCampaign() {
  creatingCampaign.value = true
  try {
    const result = props.sourceType === 'purchase'
      ? await voStore.createPurchaseRefurbishment(props.dossierId)
      : await voStore.createDepotRefurbishment(props.dossierId)
    selectedCampaignId.value = result.id
    await props.reloadDetail?.()
    toast.add({ title: 'Campagne créée', color: 'success' })
  } catch (error: any) {
    toast.add({ title: 'Erreur', description: error.message, color: 'error' })
  } finally {
    creatingCampaign.value = false
  }
}

async function saveCampaign() {
  if (!selectedCampaign.value) return

  savingCampaign.value = true
  try {
    await voStore.updateRefurbishment(selectedCampaign.value.id, {
      titre: campaignForm.titre,
      status: campaignForm.status,
      priority: campaignForm.priority,
      plannedFor: campaignForm.plannedFor || null,
      diagnosticNotes: campaignForm.diagnosticNotes || null,
      workshopNotes: campaignForm.workshopNotes || null,
      businessNotes: campaignForm.businessNotes || null,
    })
    await props.reloadDetail?.()
    toast.add({ title: 'Campagne enregistrée', color: 'success' })
  } catch (error: any) {
    toast.add({ title: 'Erreur', description: error.message, color: 'error' })
  } finally {
    savingCampaign.value = false
  }
}

async function addLine() {
  if (!selectedCampaign.value || !newLine.prestationId) return

  addingLine.value = true
  try {
    await voStore.addRefurbishmentLine(selectedCampaign.value.id, {
      prestationId: newLine.prestationId,
      quantity: Number.parseInt(String(newLine.quantity || 1), 10) || 1,
      notes: newLine.notes || null,
    })
    newLine.prestationId = null
    newLine.quantity = 1
    newLine.notes = ''
    await props.reloadDetail?.()
    toast.add({ title: 'Prestation ajoutée', color: 'success' })
  } catch (error: any) {
    toast.add({ title: 'Erreur', description: error.message, color: 'error' })
  } finally {
    addingLine.value = false
  }
}

async function saveLine(lineId: number) {
  savingLineId.value = lineId
  try {
    const form = lineForms.value[lineId] || {}
    await voStore.updateRefurbishmentLine(lineId, {
      quantity: Number.parseInt(String(form.quantity || 1), 10) || 1,
      status: form.status,
      actualTotalHt: form.actualTotalHt || null,
      actualMinutes: form.actualMinutes || null,
      notes: form.notes || null,
    })
    await props.reloadDetail?.()
    toast.add({ title: 'Ligne enregistrée', color: 'success' })
  } catch (error: any) {
    toast.add({ title: 'Erreur', description: error.message, color: 'error' })
  } finally {
    savingLineId.value = null
  }
}

async function removeLine(lineId: number) {
  removingLineId.value = lineId
  try {
    await voStore.deleteRefurbishmentLine(lineId)
    await props.reloadDetail?.()
    toast.add({ title: 'Ligne supprimée', color: 'success' })
  } catch (error: any) {
    toast.add({ title: 'Erreur', description: error.message, color: 'error' })
  } finally {
    removingLineId.value = null
  }
}

async function addPiece() {
  if (!selectedCampaign.value || !newPiece.libelle.trim()) return

  addingPiece.value = true
  try {
    await voStore.addRefurbishmentPiece(selectedCampaign.value.id, {
      libelle: newPiece.libelle,
      reference: newPiece.reference || null,
      quantity: Number.parseInt(String(newPiece.quantity || 1), 10) || 1,
      supplier: newPiece.supplier || null,
      estimatedUnitCostHt: newPiece.estimatedUnitCostHt || '0.00',
      status: newPiece.status,
      notes: newPiece.notes || null,
    })
    newPiece.libelle = ''
    newPiece.reference = ''
    newPiece.quantity = 1
    newPiece.supplier = ''
    newPiece.estimatedUnitCostHt = '0.00'
    newPiece.status = 'a_commander'
    newPiece.notes = ''
    await props.reloadDetail?.()
    toast.add({ title: 'Pièce ajoutée', color: 'success' })
  } catch (error: any) {
    toast.add({ title: 'Erreur', description: error.message, color: 'error' })
  } finally {
    addingPiece.value = false
  }
}

async function savePiece(pieceId: number) {
  savingPieceId.value = pieceId
  try {
    const form = pieceForms.value[pieceId] || {}
    await voStore.updateRefurbishmentPiece(pieceId, {
      reference: form.reference || null,
      quantity: Number.parseInt(String(form.quantity || 1), 10) || 1,
      supplier: form.supplier || null,
      status: form.status,
      actualTotalCostHt: form.actualTotalCostHt || null,
      notes: form.notes || null,
    })
    await props.reloadDetail?.()
    toast.add({ title: 'Pièce enregistrée', color: 'success' })
  } catch (error: any) {
    toast.add({ title: 'Erreur', description: error.message, color: 'error' })
  } finally {
    savingPieceId.value = null
  }
}

async function removePiece(pieceId: number) {
  removingPieceId.value = pieceId
  try {
    await voStore.deleteRefurbishmentPiece(pieceId)
    await props.reloadDetail?.()
    toast.add({ title: 'Pièce supprimée', color: 'success' })
  } catch (error: any) {
    toast.add({ title: 'Erreur', description: error.message, color: 'error' })
  } finally {
    removingPieceId.value = null
  }
}

</script>

<style scoped>
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
.vo-summary-label,
.vo-field span,
.vo-hint {
  color: #9ca3af;
  font-size: 12px;
}

.vo-revo-stack,
.vo-lines {
  display: grid;
  gap: 16px;
}

.vo-revo-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.vo-revo-tab {
  display: grid;
  gap: 4px;
  padding: 10px 12px;
  border-radius: 12px;
  border: 1px solid rgba(255,255,255,0.08);
  background: rgba(255,255,255,0.03);
  color: #d1d5db;
  text-align: left;
}

.vo-revo-tab.is-active {
  border-color: rgba(245, 158, 11, 0.35);
  background: rgba(245, 158, 11, 0.08);
}

.vo-revo-tab.is-blocking strong {
  color: #fbbf24;
}

.vo-revo-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.35fr) minmax(260px, 0.65fr);
  gap: 16px;
}

.vo-revo-main,
.vo-revo-side,
.vo-subsection {
  display: grid;
  gap: 14px;
}

.vo-subtitle-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.vo-form-grid,
.compact-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.vo-inline-form {
  display: grid;
  gap: 10px;
}

.vo-inline-form--3 {
  grid-template-columns: minmax(0, 1fr) 100px 120px;
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
  border-radius: 10px;
  border: 1px solid rgba(255,255,255,0.08);
  background: rgba(255,255,255,0.03);
  color: #f8fafc;
  padding: 10px 12px;
}

.vo-textarea {
  resize: vertical;
}

.vo-inline-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.vo-inline-actions.split.compact {
  justify-content: flex-end;
}

.topbar-new-btn,
.vo-link-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 8px 12px;
  border-radius: 10px;
  border: 0;
  cursor: pointer;
  text-decoration: none;
}

.topbar-new-btn {
  background: #f59e0b;
  color: #090b10;
  font-weight: 700;
}

.vo-link-btn {
  background: rgba(255,255,255,0.06);
  color: #f8fafc;
}

.vo-link-btn.danger {
  color: #fecaca;
}

.vo-info-box,
.vo-warning-box,
.vo-sim-box,
.vo-empty-box,
.vo-revo-row {
  display: grid;
  gap: 6px;
  padding: 12px;
  border-radius: 12px;
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.06);
}

.vo-warning-box {
  background: rgba(239, 68, 68, 0.05);
  border-color: rgba(239, 68, 68, 0.18);
}

.vo-revo-summary-grid {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.vo-revo-summary-grid strong {
  display: block;
  color: #f8fafc;
  margin-top: 4px;
}

.vo-revo-row-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

@media (max-width: 960px) {
  .vo-revo-grid,
  .vo-revo-summary-grid,
  .vo-form-grid,
  .compact-grid,
  .vo-inline-form--3 {
    grid-template-columns: 1fr;
  }

  .vo-card-head {
    flex-direction: column;
  }
}
</style>