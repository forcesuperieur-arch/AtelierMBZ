<template>
  <div>
    <div class="page-header vo-header">
      <div>
        <div class="page-title">Nouveau rachat VO</div>
        <div class="vo-subtitle">Wizard en 4 étapes pour constituer un dossier complet et prêt à confirmer.</div>
      </div>
      <NuxtLink to="/vo/rachats" class="topbar-new-btn vo-secondary-btn">Retour aux rachats</NuxtLink>
    </div>

    <VONav />

    <div class="vo-stepper">
      <button
        v-for="item in steps"
        :key="item.id"
        type="button"
        class="vo-step"
        :class="{ 'is-active': step === item.id, 'is-done': step > item.id }"
        @click="step = item.id"
      >
        <span>{{ item.id }}</span>
        <strong>{{ item.label }}</strong>
      </button>
    </div>

    <div class="vo-companion-banner">
      <strong>Mode compagnon PDA prêt dès l'ouverture</strong>
      <span>Le QR code du parcours VO apparaît directement. Tu peux soit remplir le dossier à la main, soit scanner tout de suite les documents vendeur et moto pour préremplir automatiquement le rachat.</span>
    </div>

    <div id="companion-qr-hero" class="vo-hero-qr">
      <div>
        <strong>QR compagnon immédiat</strong>
        <p>Ouvre le parcours PDA dès maintenant pour scanner les pièces et laisser le dossier se remplir tout seul.</p>
        <div class="vo-inline-actions" style="margin-top: 10px;">
          <button type="button" class="topbar-new-btn" :disabled="activatingCompanion" @click="activateCompanionNow()">
            {{ activatingCompanion ? 'Préparation...' : 'Régénérer le QR' }}
          </button>
          <a v-if="draftPublicUrl" :href="draftPublicUrl" target="_blank" class="vo-link-btn">Ouvrir le PDA</a>
          <button v-if="draftPublicUrl" type="button" class="vo-link-btn" @click="copyCompanionLink">Copier le lien</button>
        </div>
        <p v-if="qrLoadFailed && draftPublicUrl" class="vo-qr-fallback">L'image du QR n'a pas chargé. Le lien PDA reste utilisable immédiatement.</p>
      </div>

      <div class="vo-hero-qr-box">
        <img v-if="draftQrCodeUrl && !qrLoadFailed" :src="draftQrCodeUrl" alt="QR code compagnon" class="vo-hero-qr-image" @error="qrLoadFailed = true" @load="qrLoadFailed = false">
        <div v-else class="vo-hero-qr-placeholder">
          {{ activatingCompanion ? 'Préparation du QR…' : 'QR en attente…' }}
        </div>
      </div>
    </div>

    <div class="vo-wizard-grid">
      <div>
        <UCard v-if="step === 1">
          <template #header>
            <div class="vo-card-title">1. Vendeur</div>
          </template>

          <div class="vo-block">
            <label class="vo-field">
              <span>Rechercher un client existant</span>
              <UInput v-model="sellerSearch" placeholder="Nom, prénom, téléphone..." />
            </label>

            <div v-if="sellerResults.length" class="vo-search-list">
              <button
                v-for="client in sellerResults"
                :key="client.id"
                type="button"
                class="vo-search-item"
                @click="selectSeller(client)"
              >
                <strong>{{ client.prenom }} {{ client.nom }}</strong>
                <span>{{ client.telephone || 'Téléphone non renseigné' }}</span>
              </button>
            </div>

            <div class="vo-divider">ou créer rapidement</div>

            <div class="vo-form-grid">
              <label class="vo-field">
                <span>Prénom</span>
                <input v-model="sellerForm.prenom" class="vo-input" />
              </label>
              <label class="vo-field">
                <span>Nom</span>
                <input v-model="sellerForm.nom" class="vo-input" />
              </label>
              <label class="vo-field">
                <span>Téléphone</span>
                <input v-model="sellerForm.telephone" class="vo-input" />
              </label>
              <label class="vo-field">
                <span>Email</span>
                <input v-model="sellerForm.email" class="vo-input" />
              </label>
              <label class="vo-field vo-field-full">
                <span>Adresse</span>
                <input v-model="sellerForm.adresse" class="vo-input" />
              </label>
            </div>
          </div>
        </UCard>

        <UCard v-else-if="step === 2">
          <template #header>
            <div class="vo-card-title">2. Véhicule</div>
          </template>

          <div class="vo-block">
            <div class="vo-inline-search">
              <label class="vo-field grow">
                <span>Recherche par immatriculation ou VIN</span>
                <UInput v-model="vehicleSearch" placeholder="Ex: AB-123-CD ou VIN" />
              </label>
              <button class="topbar-new-btn" type="button" @click="lookupVehicle">Rechercher</button>
            </div>

            <div v-if="selectedVehicle" class="vo-selected-box">
              <strong>{{ selectedVehicle.marque || '—' }} {{ selectedVehicle.modele || '' }}</strong>
              <span>{{ selectedVehicle.plaque || 'Sans plaque' }} • {{ selectedVehicle.vin || 'VIN non renseigné' }}</span>
              <button type="button" class="vo-link-btn" @click="resetVehicle">Saisir un autre véhicule</button>
            </div>

            <div class="vo-divider">ou créer rapidement</div>

            <div class="vo-form-grid">
              <label class="vo-field">
                <span>Immatriculation</span>
                <input v-model="vehicleForm.plaque" class="vo-input" />
              </label>
              <label class="vo-field">
                <span>VIN</span>
                <input v-model="vehicleForm.vin" class="vo-input" />
              </label>
              <label class="vo-field">
                <span>Marque</span>
                <input v-model="vehicleForm.marque" class="vo-input" @input="onVehicleMarqueInput" @blur="hideVehicleMarqueSuggestions" />
                <div v-if="vehicleMarqueSuggestions.length" class="vo-search-list" style="margin-top:6px;">
                  <button
                    v-for="item in vehicleMarqueSuggestions"
                    :key="`purchase-brand-${item}`"
                    type="button"
                    class="vo-search-item"
                    @mousedown.prevent="selectVehicleMarque(item)"
                  >
                    <strong>{{ item }}</strong>
                  </button>
                </div>
              </label>
              <label class="vo-field">
                <span>Modèle</span>
                <input v-model="vehicleForm.modele" class="vo-input" @input="onVehicleModeleInput" @blur="hideVehicleModeleSuggestions" />
                <div v-if="vehicleModeleSuggestions.length" class="vo-search-list" style="margin-top:6px;">
                  <button
                    v-for="item in vehicleModeleSuggestions"
                    :key="`purchase-model-${item.id || item.modele}`"
                    type="button"
                    class="vo-search-item"
                    @mousedown.prevent="selectVehicleModele(item)"
                  >
                    <strong>{{ item.modele }}</strong>
                    <span>{{ vehicleSuggestionLabel(item) }}</span>
                  </button>
                </div>
              </label>
              <label class="vo-field">
                <span>Catégorie tarifaire</span>
                <select v-model="vehicleForm.categorieId" class="vo-select">
                  <option value="">Non renseignée</option>
                  <option v-for="category in categories" :key="category.id" :value="String(category.id)">{{ category.nom }}</option>
                </select>
              </label>
              <label class="vo-field">
                <span>Type véhicule atelier</span>
                <select v-model="vehicleForm.typeMoto" class="vo-select">
                  <option value="">Non renseigné</option>
                  <option value="moto">Moto</option>
                  <option value="scooter">Scooter</option>
                  <option value="tous">Tous</option>
                </select>
              </label>
              <label class="vo-field">
                <span>Cylindrée</span>
                <input v-model="vehicleForm.cylindree" class="vo-input" placeholder="ex: 750" />
              </label>
              <label class="vo-field">
                <span>Année</span>
                <input v-model="vehicleForm.annee" type="number" class="vo-input" />
              </label>
              <label class="vo-field">
                <span>Kilométrage</span>
                <input v-model="vehicleForm.mileage" type="number" class="vo-input" />
              </label>
              <label class="vo-field">
                <span>Couleur</span>
                <input v-model="vehicleForm.couleur" class="vo-input" />
              </label>
              <label class="vo-field">
                <span>1ère MEC</span>
                <input v-model="vehicleForm.datePremiereMiseEnCirculation" type="date" class="vo-input" />
              </label>
            </div>
          </div>
        </UCard>

        <UCard v-else-if="step === 3">
          <template #header>
            <div class="vo-card-title">3. Financier</div>
          </template>

          <div class="vo-form-grid">
            <label class="vo-field">
              <span>Prix d'achat</span>
              <input v-model="purchaseForm.purchasePrice" class="vo-input" placeholder="0.00" />
            </label>
            <label class="vo-field">
              <span>Prix de vente cible</span>
              <input v-model="purchaseForm.targetSalePrice" class="vo-input" placeholder="0.00" />
            </label>
            <label class="vo-field">
              <span>Régime TVA</span>
              <select v-model="purchaseForm.regimeTva" class="vo-select">
                <option value="marge">TVA sur marge</option>
                <option value="normal">TVA normale</option>
              </select>
            </label>
            <label class="vo-field">
              <span>Date d'achat</span>
              <input v-model="purchaseForm.purchaseDate" type="date" class="vo-input" />
            </label>
            <label class="vo-field">
              <span>Type pièce vendeur</span>
              <select v-model="purchaseForm.sellerIdType" class="vo-select">
                <option value="carte_identite">Carte d'identité</option>
                <option value="passeport">Passeport</option>
                <option value="permis">Permis</option>
              </select>
            </label>
            <label class="vo-field">
              <span>N° pièce vendeur</span>
              <input v-model="purchaseForm.sellerIdNumber" class="vo-input" />
            </label>
            <label class="vo-field">
              <span>Date pièce vendeur</span>
              <input v-model="purchaseForm.sellerIdDate" type="date" class="vo-input" />
            </label>
            <label class="vo-field">
              <span>Date non-gage</span>
              <input v-model="purchaseForm.nonGageDate" type="date" class="vo-input" />
            </label>
          </div>

          <div class="vo-inline-check">
            <input id="ct-ok" v-model="purchaseForm.controleTechniqueOk" type="checkbox" />
            <label for="ct-ok">Contrôle technique vérifié</label>
          </div>

          <div class="vo-subsection">
            <div class="vo-subtitle-row">
              <strong>Frais de remise en état</strong>
              <button type="button" class="vo-link-btn" @click="addFreItem">+ Ajouter une ligne</button>
            </div>
            <div class="vo-lines">
              <div v-for="(item, index) in freItems" :key="index" class="vo-line-row">
                <input v-model="item.label" class="vo-input" placeholder="Libellé" />
                <input v-model="item.amount" class="vo-input" placeholder="0.00" />
                <button type="button" class="vo-link-btn" @click="removeFreItem(index)">Supprimer</button>
              </div>
            </div>
          </div>

          <label class="vo-field vo-field-full">
            <span>Notes internes</span>
            <textarea v-model="purchaseForm.notes" class="vo-textarea" rows="4" />
          </label>
        </UCard>

        <UCard v-else>
          <template #header>
            <div class="vo-card-title">4. Documents & validation</div>
          </template>

          <div class="vo-form-grid">
            <label class="vo-field">
              <span>Expert / référent VO</span>
              <select v-model="purchaseForm.expertId" class="vo-select">
                <option :value="null">Non assigné</option>
                <option v-for="user in experts" :key="user.id" :value="user.id">{{ user.prenom || user.username }} {{ user.nom || '' }}</option>
              </select>
            </label>
          </div>

          <div class="vo-subsection">
            <div class="vo-subtitle-row">
              <strong>Documents à joindre</strong>
              <button type="button" class="vo-link-btn" @click="addDocumentRow">+ Ajouter un document</button>
            </div>
            <div class="vo-lines">
              <div v-for="(row, index) in documentRows" :key="index" class="vo-doc-row">
                <select v-model="row.type" class="vo-select">
                  <option value="">Type</option>
                  <option v-for="option in purchaseDocumentOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
                <input type="date" :value="row.dateExpiration" class="vo-input" @input="row.dateExpiration = ($event.target as HTMLInputElement).value" />
                <input type="file" class="vo-input" @change="onDocumentFileChange(index, $event)" />
                <button type="button" class="vo-link-btn" @click="removeDocumentRow(index)">Supprimer</button>
              </div>
            </div>
          </div>

          <div class="vo-warning-box" v-if="missingConfirmationDocs.length">
            <strong>Confirmation non disponible pour l’instant</strong>
            <span>Documents requis manquants: {{ missingConfirmationDocs.map(documentLabel).join(', ') }}</span>
          </div>

          <div class="vo-inline-check">
            <input id="confirm-after-create" v-model="purchaseForm.createAndConfirm" type="checkbox" :disabled="missingConfirmationDocs.length > 0" />
            <label for="confirm-after-create">Créer puis confirmer immédiatement</label>
          </div>

          <div class="vo-info-box">
            <strong>Parcours express après création</strong>
            <span>Le dossier t’ouvrira directement la zone QR compagnon pour scanner les documents du vendeur, vérifier l’OCR et signer sur le PDA.</span>
          </div>
        </UCard>

        <div class="vo-footer-actions">
          <button v-if="step > 1" type="button" class="vo-secondary-cta" @click="step -= 1">Retour</button>
          <button v-if="step < 4" type="button" class="topbar-new-btn" @click="nextStep">Continuer</button>
          <button v-else type="button" class="topbar-new-btn" :disabled="submitting" @click="submit">{{ submitting ? 'Création...' : 'Créer le dossier' }}</button>
        </div>
      </div>

      <div>
        <UCard class="vo-summary-panel">
          <template #header>
            <div class="vo-card-title">Résumé du dossier</div>
          </template>

          <div class="vo-summary-block">
            <span class="vo-summary-label">Étape en cours</span>
            <strong>{{ currentStepLabel }}</strong>
          </div>
          <div class="vo-summary-block">
            <span class="vo-summary-label">Vendeur</span>
            <strong>{{ sellerSummary }}</strong>
          </div>
          <div class="vo-summary-block">
            <span class="vo-summary-label">Véhicule</span>
            <strong>{{ vehicleSummary }}</strong>
          </div>
          <div class="vo-summary-block">
            <span class="vo-summary-label">Prix achat</span>
            <strong>{{ formatPrice(purchaseForm.purchasePrice || 0) }}</strong>
          </div>
          <div class="vo-summary-block">
            <span class="vo-summary-label">Prix vente cible</span>
            <strong>{{ formatPrice(purchaseForm.targetSalePrice || 0) }}</strong>
          </div>
          <div class="vo-summary-block">
            <span class="vo-summary-label">FRE total</span>
            <strong>{{ formatPrice(totalFre) }}</strong>
          </div>

          <div class="vo-summary-block">
            <span class="vo-summary-label">Pièces jointes</span>
            <strong>{{ attachedDocumentCount }}</strong>
          </div>

          <div v-if="missingConfirmationDocs.length" class="vo-warning-box">
            <strong>Confirmation encore bloquée</strong>
            <span>{{ missingConfirmationDocs.map(documentLabel).join(', ') }}</span>
          </div>

          <div v-if="marginSimulation" class="vo-sim-box">
            <div>
              <span class="vo-summary-label">Marge nette</span>
              <strong :style="{ color: marginSimulation.is_profitable ? '#22c55e' : '#ef4444' }">{{ formatPrice(marginSimulation.net_margin) }}</strong>
            </div>
            <div>
              <span class="vo-summary-label">Marge %</span>
              <strong>{{ marginSimulation.margin_pct }}%</strong>
            </div>
            <div>
              <span class="vo-summary-label">Coût total</span>
              <strong>{{ formatPrice(marginSimulation.total_cost) }}</strong>
            </div>
          </div>

          <div class="vo-info-box">
            <strong>Ce qui se passe ensuite</strong>
            <span>1. un seul QR en haut de page • 2. scan CG + identité sur PDA • 3. autoremplissage du dossier • 4. tu complètes seulement le nécessaire.</span>
          </div>

          <div class="vo-info-box">
            <strong>Tarifs atelier VO</strong>
            <span>Renseigne la catégorie tarifaire, le type moto et la cylindrée pour fiabiliser les prestations et prix proposés en remise en état.</span>
          </div>
        </UCard>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useVoStore } from '~/stores/vo'
import { adoptDraftEntity, syncDraftBoolean, syncDraftField, type DraftSyncMemory } from '~/composables/voCompanionDraftSync'
import { applyVehicleToForm, buildVoVehiclePayload, extractVehicleCategoryId } from '~/composables/voVehicleForm'

definePageMeta({ title: 'Nouveau rachat VO' })

const voStore = useVoStore()
const toast = useToast()
const { validateClientFields } = useValidation()
const {
  searchClients,
  fetchExperts,
  fetchMotoCategories,
  createQuickClient,
  createQuickVehicule,
  updateQuickVehicule,
  findVehiculeByQuery,
  formatPrice,
  formatRegistrationOrVin,
  documentLabel,
  documentLabels,
} = useVoHelpers()

const { useQrCode } = await import('~/composables/useQrCode')

const steps = [
  { id: 1, label: 'Vendeur' },
  { id: 2, label: 'Véhicule' },
  { id: 3, label: 'Financier' },
  { id: 4, label: 'Documents' },
]

const requiredPurchaseDocs = ['cerfa_cession_achat', 'carte_grise', 'non_gage']
const purchaseDocumentOptions = [
  'cerfa_cession_achat',
  'carte_grise',
  'non_gage',
  'controle_technique',
  'notice_garantie',
  'autre',
].map((value) => ({ value, label: documentLabels[value] }))

const step = ref(1)
const submitting = ref(false)
const activatingCompanion = ref(false)
const draftPurchaseId = ref<number | null>(null)
const draftCompanion = ref<any | null>(null)
const sellerSearch = ref('')
const sellerResults = ref<any[]>([])
const selectedSeller = ref<any | null>(null)
const selectedVehicle = ref<any | null>(null)
const vehicleSearch = ref('')
const experts = ref<any[]>([])
const categories = ref<any[]>([])
const marginSimulation = ref<any | null>(null)
const qrLoadFailed = ref(false)
const sellerDraftSync = reactive<DraftSyncMemory>({})
const vehicleDraftSync = reactive<DraftSyncMemory>({})
const purchaseDraftSync = reactive<DraftSyncMemory>({})

const sellerForm = reactive({ prenom: '', nom: '', telephone: '', email: '', adresse: '' })
const vehicleForm = reactive({
  plaque: '',
  vin: '',
  marque: '',
  modele: '',
  categorieId: '',
  typeMoto: '',
  cylindree: '',
  annee: '',
  mileage: '',
  couleur: '',
  datePremiereMiseEnCirculation: '',
})

const {
  marqueSuggestions: vehicleMarqueSuggestions,
  modeleSuggestions: vehicleModeleSuggestions,
  onMarqueInput: onVehicleMarqueInput,
  onModeleInput: onVehicleModeleInput,
  selectMarque: selectVehicleMarque,
  selectModele: selectVehicleModele,
  deferHideMarqueSuggestions: hideVehicleMarqueSuggestions,
  deferHideModeleSuggestions: hideVehicleModeleSuggestions,
  suggestionLabel: vehicleSuggestionLabel,
} = useMotoAutocomplete({
  form: vehicleForm,
  marqueKey: 'marque',
  modeleKey: 'modele',
  cylindreeKey: 'cylindree',
  typeKey: 'typeMoto',
  anneeKey: 'annee',
  categorieKey: 'categorieId',
  typeTransform: (value: string) => value.toLowerCase().includes('scooter') ? 'scooter' : 'moto',
})
const purchaseForm = reactive({
  purchasePrice: '',
  targetSalePrice: '',
  regimeTva: 'marge',
  purchaseDate: new Date().toISOString().slice(0, 10),
  sellerIdType: 'carte_identite',
  sellerIdNumber: '',
  sellerIdDate: '',
  nonGageDate: new Date().toISOString().slice(0, 10),
  controleTechniqueOk: false,
  notes: '',
  expertId: null as number | null,
  createAndConfirm: false,
})

const freItems = ref([{ label: 'Remise en état', amount: '' }])
const documentRows = ref([{ type: '', dateExpiration: '', file: null as File | null }])

const sellerSummary = computed(() => {
  if (selectedSeller.value) return `${selectedSeller.value.prenom || ''} ${selectedSeller.value.nom || ''}`.trim()
  if (sellerForm.prenom || sellerForm.nom) return `${sellerForm.prenom} ${sellerForm.nom}`.trim()
  return 'Aucun vendeur sélectionné'
})

const currentStepLabel = computed(() => steps.find(item => item.id === step.value)?.label || 'Préparation')

const vehicleSummary = computed(() => {
  if (selectedVehicle.value) return `${selectedVehicle.value.marque || ''} ${selectedVehicle.value.modele || ''} • ${selectedVehicle.value.plaque || 'sans plaque'}`.trim()
  if (vehicleForm.marque || vehicleForm.modele || vehicleForm.plaque) return `${vehicleForm.marque || ''} ${vehicleForm.modele || ''} • ${vehicleForm.plaque || 'sans plaque'}`.trim()
  return 'Aucun véhicule sélectionné'
})

const totalFre = computed(() => {
  return freItems.value.reduce((sum, item) => sum + Number.parseFloat((item.amount || '0').replace(',', '.')), 0)
})

const attachedDocumentCount = computed(() => documentRows.value.filter(row => row.file).length)
const draftPublicUrl = computed(() => {
  const path = String(draftCompanion.value?.companion?.publicPath || '').trim()
  if (!path || !import.meta.client) return ''
  return new URL(path, window.location.origin).toString()
})
const { dataUrl: draftQrCodeUrl } = useQrCode(draftPublicUrl, 180)

const uploadedDocTypes = computed(() => new Set(documentRows.value.filter(row => row.type && row.file).map(row => row.type)))
const missingConfirmationDocs = computed(() => requiredPurchaseDocs.filter(type => !uploadedDocTypes.value.has(type)))

let sellerSearchTimer: ReturnType<typeof setTimeout> | null = null
let simulationTimer: ReturnType<typeof setTimeout> | null = null
let companionPollTimer: number | null = null

function refreshOnFocus() {
  refreshDraftCompanion(true)
}

watch(sellerSearch, (value) => {
  if (sellerSearchTimer) clearTimeout(sellerSearchTimer)
  if (value.trim().length < 2) {
    sellerResults.value = []
    return
  }

  sellerSearchTimer = setTimeout(async () => {
    sellerResults.value = await searchClients(value)
  }, 250)
})

watch([
  () => purchaseForm.purchasePrice,
  () => purchaseForm.targetSalePrice,
  () => purchaseForm.regimeTva,
  freItems,
], () => {
  if (simulationTimer) clearTimeout(simulationTimer)
  simulationTimer = setTimeout(() => {
    runMarginSimulation()
  }, 250)
}, { deep: true })

function selectSeller(client: any) {
  selectedSeller.value = client
  Object.assign(sellerForm, {
    prenom: client.prenom || '',
    nom: client.nom || '',
    telephone: client.telephone || '',
    email: client.email || '',
    adresse: client.adresse || '',
  })
}

async function lookupVehicle() {
  if (!vehicleSearch.value.trim()) return

  const found = await findVehiculeByQuery(vehicleSearch.value)
  if (!found) {
    toast.add({ title: 'Aucun véhicule trouvé', description: 'Complétez la création rapide ci-dessous.', color: 'warning' })
    vehicleForm.plaque = formatRegistrationOrVin(vehicleSearch.value)
    selectedVehicle.value = null
    return
  }

  selectedVehicle.value = found
  applyVehicleToForm(vehicleForm, found)
}

function resetVehicle() {
  selectedVehicle.value = null
}

function addFreItem() {
  freItems.value.push({ label: '', amount: '' })
}

function removeFreItem(index: number) {
  freItems.value.splice(index, 1)
}

function addDocumentRow() {
  documentRows.value.push({ type: '', dateExpiration: '', file: null })
}

function removeDocumentRow(index: number) {
  documentRows.value.splice(index, 1)
}

function onDocumentFileChange(index: number, event: Event) {
  const target = event.target as HTMLInputElement
  documentRows.value[index].file = target.files?.[0] ?? null
}

function canGoToNextStep() {
  if (step.value === 1) {
    return !!selectedSeller.value || (!!sellerForm.prenom.trim() && !!sellerForm.nom.trim() && !!sellerForm.telephone.trim())
  }
  if (step.value === 2) {
    return !!selectedVehicle.value || !!vehicleForm.plaque.trim()
  }
  if (step.value === 3) {
    return !!purchaseForm.purchasePrice.trim() && !!purchaseForm.targetSalePrice.trim()
  }
  return true
}

function nextStep() {
  if (!canGoToNextStep()) {
    toast.add({ title: 'Étape incomplète', description: 'Renseignez les informations minimales avant de continuer.', color: 'warning' })
    return
  }
  step.value += 1
}

async function runMarginSimulation() {
  if (!purchaseForm.purchasePrice.trim() || !purchaseForm.targetSalePrice.trim()) {
    marginSimulation.value = null
    return
  }

  try {
    marginSimulation.value = await voStore.simulateMargin({
      purchasePrice: purchaseForm.purchasePrice || '0',
      salePrice: purchaseForm.targetSalePrice || '0',
      regime: purchaseForm.regimeTva,
      freItems: freItems.value.filter(item => item.label.trim() || item.amount.trim()),
    })
  } catch {
    marginSimulation.value = null
  }
}

async function ensureSeller() {
  if (selectedSeller.value?.id) return selectedSeller.value.id
  const created = await createQuickClient({ ...sellerForm })
  selectedSeller.value = created
  return created.id
}

async function ensureVehicle(sellerId: number) {
  if (selectedVehicle.value?.id) {
    selectedVehicle.value = await updateQuickVehicule(selectedVehicle.value.id, buildVoVehiclePayload(vehicleForm))
    return selectedVehicle.value.id
  }

  const created = await createQuickVehicule(buildVoVehiclePayload(vehicleForm, `/api/clients/${sellerId}`))

  selectedVehicle.value = created
  return created.id
}

async function uploadDocuments(purchaseId: number) {
  for (const row of documentRows.value) {
    if (!row.file || !row.type) continue

    const formData = new FormData()
    formData.append('file', row.file)
    formData.append('type', row.type)
    formData.append('purchaseId', String(purchaseId))
    if (row.dateExpiration) formData.append('dateExpiration', row.dateExpiration)
    await voStore.uploadDocument(formData)
  }
}

async function copyCompanionLink() {
  if (!draftPublicUrl.value || !import.meta.client) return

  await navigator.clipboard.writeText(draftPublicUrl.value)
  toast.add({ title: 'Lien compagnon copié', color: 'success' })
}

function hydrateFromDraft(full: any) {
  draftCompanion.value = full

  selectedSeller.value = adoptDraftEntity(selectedSeller.value, full?.seller || null)
  selectedVehicle.value = adoptDraftEntity(selectedVehicle.value, full?.vehicule || null)

  syncDraftField(sellerForm, 'prenom', full?.seller?.prenom, sellerDraftSync)
  syncDraftField(sellerForm, 'nom', full?.seller?.nom, sellerDraftSync)
  syncDraftField(sellerForm, 'telephone', full?.seller?.telephone, sellerDraftSync)
  syncDraftField(sellerForm, 'email', full?.seller?.email, sellerDraftSync)
  syncDraftField(sellerForm, 'adresse', full?.seller?.adresse, sellerDraftSync)

  syncDraftField(vehicleForm, 'plaque', full?.vehicule?.plaque, vehicleDraftSync)
  syncDraftField(vehicleForm, 'vin', full?.vehicule?.vin, vehicleDraftSync)
  syncDraftField(vehicleForm, 'marque', full?.vehicule?.marque, vehicleDraftSync)
  syncDraftField(vehicleForm, 'modele', full?.vehicule?.modele, vehicleDraftSync)
  syncDraftField(vehicleForm, 'categorieId', extractVehicleCategoryId(full?.vehicule), vehicleDraftSync)
  syncDraftField(vehicleForm, 'typeMoto', full?.vehicule?.typeMoto, vehicleDraftSync)
  syncDraftField(vehicleForm, 'cylindree', full?.vehicule?.cylindree, vehicleDraftSync)
  syncDraftField(vehicleForm, 'annee', full?.vehicule?.annee ? String(full.vehicule.annee) : '', vehicleDraftSync)
  syncDraftField(vehicleForm, 'mileage', full?.vehicule?.mileage ? String(full.vehicule.mileage) : '', vehicleDraftSync)
  syncDraftField(vehicleForm, 'couleur', full?.vehicule?.couleur, vehicleDraftSync)
  syncDraftField(
    vehicleForm,
    'datePremiereMiseEnCirculation',
    full?.vehicule?.datePremiereMiseEnCirculation ? String(full.vehicule.datePremiereMiseEnCirculation).slice(0, 10) : '',
    vehicleDraftSync,
  )

  syncDraftField(purchaseForm as unknown as Record<string, any>, 'sellerIdType', full?.sellerIdType, purchaseDraftSync)
  syncDraftField(purchaseForm as unknown as Record<string, any>, 'sellerIdNumber', full?.sellerIdNumber, purchaseDraftSync)
  syncDraftField(
    purchaseForm as unknown as Record<string, any>,
    'sellerIdDate',
    full?.sellerIdDate ? String(full.sellerIdDate).slice(0, 10) : '',
    purchaseDraftSync,
  )
  syncDraftField(
    purchaseForm as unknown as Record<string, any>,
    'nonGageDate',
    full?.nonGageDate ? String(full.nonGageDate).slice(0, 10) : '',
    purchaseDraftSync,
  )
  syncDraftBoolean(purchaseForm as unknown as Record<string, any>, 'controleTechniqueOk', full?.controleTechniqueOk, purchaseDraftSync)
}

async function refreshDraftCompanion(silent = true) {
  if (!draftPurchaseId.value) return

  try {
    const full = await voStore.fetchPurchaseFull(draftPurchaseId.value)
    hydrateFromDraft(full)
  } catch (error: any) {
    if (!silent) {
      toast.add({ title: 'Erreur', description: error.message, color: 'error' })
    }
  }
}

async function activateCompanionNow(showToast = true) {
  activatingCompanion.value = true
  try {
    if (!draftPurchaseId.value) {
      const draft = await voStore.createPurchase({
        purchasePrice: purchaseForm.purchasePrice || '0',
        targetSalePrice: purchaseForm.targetSalePrice || '0',
        regimeTva: purchaseForm.regimeTva,
        purchaseDate: purchaseForm.purchaseDate,
        sellerIdType: purchaseForm.sellerIdType,
        sellerIdNumber: purchaseForm.sellerIdNumber || null,
        sellerIdDate: purchaseForm.sellerIdDate || null,
        nonGageDate: purchaseForm.nonGageDate || null,
        controleTechniqueOk: purchaseForm.controleTechniqueOk,
        expertId: purchaseForm.expertId,
        notes: purchaseForm.notes || null,
        repairEstimates: freItems.value.filter(item => item.label.trim() || item.amount.trim()),
        status: 'brouillon',
      })
      draftPurchaseId.value = draft.id
    }

    await refreshDraftCompanion(!showToast)

    if (showToast) {
      toast.add({ title: 'QR compagnon prêt', description: 'Le scan PDA peut démarrer immédiatement.', color: 'success' })
    }
  } catch (error: any) {
    toast.add({ title: 'Erreur', description: error.message, color: 'error' })
  } finally {
    activatingCompanion.value = false
  }
}

async function submit() {
  if (purchaseForm.createAndConfirm && missingConfirmationDocs.value.length > 0) {
    toast.add({ title: 'Documents manquants', description: 'Ajoutez les documents obligatoires avant confirmation.', color: 'error' })
    return
  }

  const formatErrors = validateClientFields({
    telephone: sellerForm.telephone,
    email: sellerForm.email,
    plaque: vehicleForm.plaque,
  })
  if (formatErrors.length) {
    toast.add({ title: 'Format invalide', description: formatErrors.join(' — '), color: 'error' })
    return
  }

  submitting.value = true
  try {
    const sellerId = await ensureSeller()
    const vehiculeId = await ensureVehicle(sellerId)

    let purchaseId = draftPurchaseId.value

    if (purchaseId) {
      await voStore.updatePurchase(purchaseId, {
        sellerId,
        vehiculeId,
        purchasePrice: purchaseForm.purchasePrice,
        targetSalePrice: purchaseForm.targetSalePrice,
        regimeTva: purchaseForm.regimeTva,
        purchaseDate: purchaseForm.purchaseDate,
        sellerIdType: purchaseForm.sellerIdType,
        sellerIdNumber: purchaseForm.sellerIdNumber,
        sellerIdDate: purchaseForm.sellerIdDate || null,
        nonGageDate: purchaseForm.nonGageDate || null,
        controleTechniqueOk: purchaseForm.controleTechniqueOk,
        expertId: purchaseForm.expertId,
        notes: purchaseForm.notes || null,
        repairEstimates: freItems.value.filter(item => item.label.trim() || item.amount.trim()),
      })
    } else {
      const purchase = await voStore.createPurchase({
        sellerId,
        vehiculeId,
        purchasePrice: purchaseForm.purchasePrice,
        targetSalePrice: purchaseForm.targetSalePrice,
        regimeTva: purchaseForm.regimeTva,
        purchaseDate: purchaseForm.purchaseDate,
        sellerIdType: purchaseForm.sellerIdType,
        sellerIdNumber: purchaseForm.sellerIdNumber,
        sellerIdDate: purchaseForm.sellerIdDate || null,
        nonGageDate: purchaseForm.nonGageDate || null,
        controleTechniqueOk: purchaseForm.controleTechniqueOk,
        expertId: purchaseForm.expertId,
        notes: purchaseForm.notes || null,
        repairEstimates: freItems.value.filter(item => item.label.trim() || item.amount.trim()),
      })
      purchaseId = purchase.id
    }

    await uploadDocuments(purchaseId)

    if (purchaseForm.createAndConfirm) {
      await voStore.confirmPurchase(purchaseId)
    }

    toast.add({
      title: draftPurchaseId.value ? 'Brouillon finalisé' : 'Dossier créé',
      description: 'Le parcours compagnon PDA reste actif avec son QR code.',
      color: 'success',
    })
    navigateTo(`/vo/rachats/${purchaseId}?companion=1`)
  } catch (error: any) {
    toast.add({ title: 'Erreur', description: error.message, color: 'error' })
  } finally {
    submitting.value = false
  }
}

onMounted(async () => {
  experts.value = await fetchExperts()
  categories.value = await fetchMotoCategories()
  await activateCompanionNow(false)

  if (import.meta.client) {
    companionPollTimer = window.setInterval(() => {
      refreshDraftCompanion(true)
    }, 4000)
    window.addEventListener('focus', refreshOnFocus)
  }
})

onBeforeUnmount(() => {
  if (sellerSearchTimer) clearTimeout(sellerSearchTimer)
  if (simulationTimer) clearTimeout(simulationTimer)
  if (companionPollTimer) clearInterval(companionPollTimer)
  if (import.meta.client) {
    window.removeEventListener('focus', refreshOnFocus)
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

.vo-subtitle {
  margin-top: 6px;
  color: #9ca3af;
  font-size: 13px;
}

.vo-companion-banner {
  margin-bottom: 16px;
  display: grid;
  gap: 6px;
  padding: 14px 16px;
  border-radius: 16px;
  border: 1px solid rgba(251, 191, 36, 0.32);
  background: rgba(245, 158, 11, 0.08);
  color: #f3f4f6;
}

.vo-companion-banner span {
  color: #d1d5db;
  font-size: 13px;
}

.vo-hero-qr {
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  flex-wrap: wrap;
  padding: 16px;
  border-radius: 16px;
  border: 1px solid rgba(59, 130, 246, 0.28);
  background: rgba(15, 23, 42, 0.72);
}

.vo-hero-qr p {
  margin: 6px 0 0;
  color: #d1d5db;
  font-size: 13px;
}

.vo-hero-qr-box {
  min-width: 180px;
  min-height: 180px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.vo-hero-qr-image {
  width: 180px;
  height: 180px;
  padding: 8px;
  border-radius: 14px;
  background: #fff;
}

.vo-hero-qr-placeholder {
  width: 180px;
  height: 180px;
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 12px;
  border-radius: 14px;
  border: 1px dashed rgba(148, 163, 184, 0.35);
  color: #cbd5e1;
  background: rgba(15, 23, 42, 0.45);
}

.vo-qr-fallback {
  color: #fbbf24 !important;
}

.vo-companion-mini-qr {
  margin-top: 12px;
  width: 150px;
  max-width: 100%;
  padding: 8px;
  border-radius: 14px;
  background: #fff;
}

.vo-secondary-btn {
  background: #1f2937;
}

.vo-stepper {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
  margin-bottom: 16px;
}

.vo-step {
  display: grid;
  gap: 6px;
  padding: 12px;
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(255, 255, 255, 0.03);
  color: #9ca3af;
  text-align: left;
}

.vo-step span {
  width: 28px;
  height: 28px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.06);
  font-size: 12px;
  font-weight: 700;
}

.vo-step.is-active,
.vo-step.is-done {
  border-color: rgba(245, 158, 11, 0.35);
}

.vo-step.is-active span,
.vo-step.is-done span {
  background: #f59e0b;
  color: #090b10;
}

.vo-wizard-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.45fr) minmax(320px, 0.75fr);
  gap: 16px;
}

.vo-summary-panel {
  position: sticky;
  top: 88px;
}

.vo-card-title {
  color: #e8e9ed;
  font-weight: 700;
}

.vo-block,
.vo-subsection,
.vo-summary-block {
  display: grid;
  gap: 12px;
}

.vo-search-list,
.vo-lines {
  display: grid;
  gap: 10px;
}

.vo-search-item {
  display: grid;
  gap: 4px;
  text-align: left;
  padding: 12px;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.06);
  background: rgba(255, 255, 255, 0.02);
  color: #e8e9ed;
}

.vo-search-item span,
.vo-summary-label,
.vo-field span {
  color: #9ca3af;
  font-size: 12px;
  font-weight: 700;
}

.vo-divider {
  text-transform: uppercase;
  font-size: 11px;
  color: #6b7280;
  letter-spacing: 0.08em;
}

.vo-form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
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

.vo-inline-search {
  display: flex;
  gap: 12px;
  align-items: end;
}

.grow {
  flex: 1;
}

.vo-selected-box,
.vo-sim-box,
.vo-warning-box {
  display: grid;
  gap: 6px;
  padding: 12px;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.06);
  background: rgba(255, 255, 255, 0.03);
}

.vo-warning-box {
  background: rgba(239, 68, 68, 0.05);
  border-color: rgba(239, 68, 68, 0.18);
}

.vo-inline-check {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 14px 0;
  color: #d1d5db;
}

.vo-subtitle-row,
.vo-footer-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: 16px;
}

.vo-line-row,
.vo-doc-row {
  display: grid;
  grid-template-columns: 1.3fr 0.7fr auto;
  gap: 10px;
  align-items: center;
}

.vo-doc-row {
  grid-template-columns: 1fr 0.8fr 1.2fr auto;
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
  .vo-wizard-grid,
  .vo-stepper,
  .vo-form-grid,
  .vo-line-row,
  .vo-doc-row {
    grid-template-columns: 1fr;
  }

  .vo-summary-panel {
    position: static;
  }

  .vo-inline-search,
  .vo-footer-actions,
  .vo-subtitle-row {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>