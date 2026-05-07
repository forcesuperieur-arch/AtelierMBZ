<template>
  <div>
    <div class="page-header vo-header">
      <div>
        <div class="page-title">Nouveau dépôt-vente</div>
        <div class="vo-subtitle">Wizard de dépôt, mandat, commission et pièces associées.</div>
      </div>
      <NuxtLink to="/vo/depots" class="topbar-new-btn vo-secondary-btn">Retour aux dépôts</NuxtLink>
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
      <strong>Mode compagnon PDA disponible dès l'ouverture</strong>
      <span>Le QR code du parcours dépôt-vente apparaît directement. Tu peux scanner les pièces du déposant et la carte grise tout de suite pour proposer les données du mandat avant validation interne.</span>
    </div>

    <div id="companion-qr-hero" class="vo-hero-qr">
      <div>
        <strong>QR compagnon immédiat</strong>
        <p>Ouvre le parcours PDA dès maintenant pour scanner les pièces du déposant et de la moto, puis reprendre la saisie métier avec les informations déjà captées.</p>
        <div class="vo-inline-actions vo-mt-10">
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
            <div class="vo-card-title">1. Déposant</div>
          </template>

          <label class="vo-field">
            <span>Rechercher un client existant</span>
            <UInput v-model="deposantSearch" placeholder="Nom, prénom, téléphone..." />
          </label>

          <div v-if="deposantResults.length" class="vo-search-list">
            <button
              v-for="client in deposantResults"
              :key="client.id"
              type="button"
              class="vo-search-item"
              @click="selectDeposant(client)"
            >
              <strong>{{ client.prenom }} {{ client.nom }}</strong>
              <span>{{ client.telephone || 'Téléphone non renseigné' }}</span>
            </button>
          </div>

          <div class="vo-divider">ou créer rapidement</div>

          <div class="vo-form-grid">
            <label class="vo-field">
              <span>Prénom</span>
              <input v-model="deposantForm.prenom" class="vo-input" />
            </label>
            <label class="vo-field">
              <span>Nom</span>
              <input v-model="deposantForm.nom" class="vo-input" />
            </label>
            <label class="vo-field">
              <span>Téléphone</span>
              <input v-model="deposantForm.telephone" class="vo-input" />
            </label>
            <label class="vo-field">
              <span>Email</span>
              <input v-model="deposantForm.email" class="vo-input" />
            </label>
            <label class="vo-field vo-field-full">
              <span>Adresse</span>
              <input v-model="deposantForm.adresse" class="vo-input" />
            </label>
          </div>
        </UCard>

        <UCard v-else-if="step === 2">
          <template #header>
            <div class="vo-card-title">2. Véhicule</div>
          </template>

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
              <input v-model="vehicleForm.vin" class="vo-input" :class="{ 'vo-input-error': vehicleForm.vin && !isValidVin(vehicleForm.vin) }" />
              <span v-if="vehicleForm.vin && !isValidVin(vehicleForm.vin)" class="vo-field-error">{{ vinErrorMessage(vehicleForm.vin) }}</span>
            </label>
            <label class="vo-field">
              <span>Marque</span>
              <input v-model="vehicleForm.marque" class="vo-input" @input="onVehicleMarqueInput" @blur="hideVehicleMarqueSuggestions" />
              <div v-if="vehicleMarqueSuggestions.length" class="vo-search-list vo-mt-6">
                <button
                  v-for="item in vehicleMarqueSuggestions"
                  :key="`depot-brand-${item}`"
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
              <div v-if="vehicleModeleSuggestions.length" class="vo-search-list vo-mt-6">
                <button
                  v-for="item in vehicleModeleSuggestions"
                  :key="`depot-model-${item.id || item.modele}`"
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
        </UCard>

        <UCard v-else-if="step === 3">
          <template #header>
            <div class="vo-card-title">3. Mandat</div>
          </template>

          <div class="vo-form-grid">
            <label class="vo-field">
              <span>Prix de vente souhaité</span>
              <input v-model="depotForm.prixVenteSouhaite" class="vo-input" />
            </label>
            <label class="vo-field">
              <span>Type de commission</span>
              <select v-model="depotForm.commissionType" class="vo-select">
                <option value="pourcentage">Pourcentage</option>
                <option value="fixe">Forfait</option>
              </select>
            </label>
            <label class="vo-field">
              <span>Valeur de commission</span>
              <input v-model="depotForm.commissionValeur" class="vo-input" />
            </label>
            <label class="vo-field">
              <span>Début mandat</span>
              <input v-model="depotForm.dateDebut" type="date" class="vo-input" />
            </label>
            <label class="vo-field">
              <span>Durée du mandat (jours)</span>
              <input v-model="depotForm.dureeMandat" type="number" class="vo-input" />
            </label>
            <label class="vo-field">
              <span>Gestionnaire VO</span>
              <select v-model="depotForm.gestionnaireId" class="vo-select">
                <option :value="null">Non assigné</option>
                <option v-for="user in experts" :key="user.id" :value="user.id">{{ user.prenom || user.username }} {{ user.nom || '' }}</option>
              </select>
            </label>
            <label class="vo-field">
              <span>Type pièce déposant</span>
              <select v-model="depotForm.deposantIdType" class="vo-select">
                <option value="carte_identite">Carte d'identité</option>
                <option value="passeport">Passeport</option>
                <option value="permis">Permis</option>
              </select>
            </label>
            <label class="vo-field">
              <span>N° pièce déposant</span>
              <input v-model="depotForm.deposantIdNumber" class="vo-input" />
            </label>
            <label class="vo-field">
              <span>Date pièce déposant</span>
              <input v-model="depotForm.deposantIdDate" type="date" class="vo-input" />
            </label>
            <label class="vo-field vo-field-full">
              <span>Conditions de restitution</span>
              <textarea v-model="depotForm.conditionsRestitution" class="vo-textarea" rows="3" />
            </label>
            <label class="vo-field vo-field-full">
              <span>Assurance / infos complémentaires</span>
              <textarea v-model="depotForm.assuranceInfo" class="vo-textarea" rows="3" />
            </label>
            <label class="vo-field vo-field-full">
              <span>Notes internes</span>
              <textarea v-model="depotForm.notes" class="vo-textarea" rows="3" />
            </label>
          </div>
        </UCard>

        <UCard v-else>
          <template #header>
            <div class="vo-card-title">4. Documents</div>
          </template>

          <div class="vo-subsection">
            <div class="vo-subtitle-row">
              <strong>Pièces complémentaires</strong>
              <button type="button" class="vo-link-btn" @click="addDocumentRow">+ Ajouter un document</button>
            </div>
            <div class="vo-lines">
              <div v-for="(row, index) in documentRows" :key="index" class="vo-doc-row">
                <select v-model="row.type" class="vo-select">
                  <option value="">Type</option>
                  <option v-for="option in depotDocumentOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
                <input type="date" :value="row.dateExpiration" class="vo-input" @input="row.dateExpiration = ($event.target as HTMLInputElement).value" />
                <input type="file" class="vo-input" @change="onDocumentFileChange(index, $event)" />
                <button type="button" class="vo-link-btn" @click="removeDocumentRow(index)">Supprimer</button>
              </div>
            </div>
          </div>

          <div class="vo-info-box">
            <strong>Le contrat dépôt-vente est fiabilisé au fil du brouillon compagnon.</strong>
            <span>Le PDF archivé est rafraîchi quand le brouillon est finalisé et quand la signature PDA a été capturée sur un dossier complet.</span>
          </div>
        </UCard>

        <div class="vo-footer-actions">
          <button v-if="step > 1" type="button" class="vo-secondary-cta" @click="step -= 1">Retour</button>
          <button v-if="step < 4" type="button" class="topbar-new-btn" @click="nextStep">Continuer</button>
          <button v-else type="button" class="topbar-new-btn" :disabled="submitting" @click="submit">{{ submitting ? 'Création...' : 'Créer le dépôt' }}</button>
        </div>
      </div>

      <div>
        <UCard class="vo-summary-panel">
          <template #header>
            <div class="vo-card-title">Résumé du dépôt</div>
          </template>

          <div class="vo-summary-block">
            <span class="vo-summary-label">Étape en cours</span>
            <strong>{{ currentStepLabel }}</strong>
          </div>
          <div class="vo-summary-block">
            <span class="vo-summary-label">Déposant</span>
            <strong>{{ deposantSummary }}</strong>
          </div>
          <div class="vo-summary-block">
            <span class="vo-summary-label">Véhicule</span>
            <strong>{{ vehicleSummary }}</strong>
          </div>
          <div class="vo-summary-block">
            <span class="vo-summary-label">Prix souhaité</span>
            <strong>{{ formatPrice(depotForm.prixVenteSouhaite || 0) }}</strong>
          </div>
          <div class="vo-summary-block">
            <span class="vo-summary-label">Commission HT estimée</span>
            <strong>{{ formatPrice(commissionPreview.ht) }}</strong>
          </div>
          <div class="vo-summary-block">
            <span class="vo-summary-label">Commission TTC estimée</span>
            <strong>{{ formatPrice(commissionPreview.ttc) }}</strong>
          </div>
          <div class="vo-summary-block">
            <span class="vo-summary-label">Net déposant estimé</span>
            <strong>{{ formatPrice(commissionPreview.net) }}</strong>
          </div>

          <div class="vo-summary-block">
            <span class="vo-summary-label">Pièces jointes</span>
            <strong>{{ attachedDocumentCount }}</strong>
          </div>

          <div class="vo-info-box">
            <strong>Compagnon dès le début</strong>
            <span v-if="draftCompanion?.companion?.publicPath">Le brouillon dépôt-vente est actif. Tu peux déjà scanner les pièces sur le PDA pendant que tu termines le mandat.</span>
            <span v-else>Le brouillon PDA se prépare automatiquement pour lancer le scan sans attendre.</span>
            <div class="vo-inline-actions vo-mt-10">
              <button type="button" class="topbar-new-btn" :disabled="activatingCompanion" @click="activateCompanionNow()">
                {{ activatingCompanion ? 'Activation...' : (draftCompanion?.companion?.publicPath ? 'Rouvrir le QR compagnon' : 'Activer le compagnon maintenant') }}
              </button>
              <a v-if="draftPublicUrl" :href="draftPublicUrl" target="_blank" class="vo-link-btn">Ouvrir le PDA</a>
              <button v-if="draftPublicUrl" type="button" class="vo-link-btn" @click="copyCompanionLink">Copier le lien</button>
            </div>
            <img v-if="draftQrCodeUrl" :src="draftQrCodeUrl" alt="QR code compagnon" class="vo-companion-mini-qr">
          </div>

          <div class="vo-info-box">
            <strong>Contrat PDF + parcours compagnon</strong>
            <span>Le QR PDA reste le point d'entrée pour scanner les pièces, contrôler les données et faire signer le déposant sans recréer de dossier.</span>
          </div>
        </UCard>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useVoStore } from '~/stores/vo'
import { adoptDraftEntity, syncDraftField, type DraftSyncMemory } from '~/composables/voCompanionDraftSync'
import { applyVehicleToForm, buildVoVehiclePayload, extractVehicleCategoryId, isValidVin, vinErrorMessage } from '~/composables/voVehicleForm'

definePageMeta({ title: 'Nouveau dépôt VO' })

const voStore = useVoStore()
const toast = useToast()
const {
  searchClients,
  fetchConfigAtelier,
  fetchExperts,
  fetchMotoCategories,
  createQuickClient,
  createQuickVehicule,
  updateQuickVehicule,
  findVehiculeByQuery,
  formatPrice,
  formatRegistrationOrVin,
  documentLabels,
} = useVoHelpers()

const { useQrCode } = await import('~/composables/useQrCode')

const steps = [
  { id: 1, label: 'Déposant' },
  { id: 2, label: 'Véhicule' },
  { id: 3, label: 'Mandat' },
  { id: 4, label: 'Documents' },
]

const depotDocumentOptions = [
  'carte_grise',
  'controle_technique',
  'notice_garantie',
  'autre',
].map((value) => ({ value, label: documentLabels[value] }))

const step = ref(1)
const submitting = ref(false)
const activatingCompanion = ref(false)
const draftDepotId = ref<number | null>(null)
const draftCompanion = ref<any | null>(null)
const deposantSearch = ref('')
const deposantResults = ref<any[]>([])
const selectedDeposant = ref<any | null>(null)
const selectedVehicle = ref<any | null>(null)
const vehicleSearch = ref('')
const experts = ref<any[]>([])
const categories = ref<any[]>([])
const qrLoadFailed = ref(false)
const deposantDraftSync = reactive<DraftSyncMemory>({})
const vehicleDraftSync = reactive<DraftSyncMemory>({})
const depotDraftSync = reactive<DraftSyncMemory>({})

const deposantForm = reactive({ prenom: '', nom: '', telephone: '', email: '', adresse: '' })
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
const depotForm = reactive({
  prixVenteSouhaite: '',
  commissionType: 'pourcentage',
  commissionValeur: '',
  dateDebut: new Date().toISOString().slice(0, 10),
  dureeMandat: 90,
  gestionnaireId: null as number | null,
  deposantIdType: 'carte_identite',
  deposantIdNumber: '',
  deposantIdDate: '',
  conditionsRestitution: '',
  assuranceInfo: '',
  notes: '',
})
const documentRows = ref([{ type: '', dateExpiration: '', file: null as File | null }])

const deposantSummary = computed(() => {
  if (selectedDeposant.value) return `${selectedDeposant.value.prenom || ''} ${selectedDeposant.value.nom || ''}`.trim()
  if (deposantForm.prenom || deposantForm.nom) return `${deposantForm.prenom} ${deposantForm.nom}`.trim()
  return 'Aucun déposant sélectionné'
})

const currentStepLabel = computed(() => steps.find(item => item.id === step.value)?.label || 'Préparation')

const vehicleSummary = computed(() => {
  if (selectedVehicle.value) return `${selectedVehicle.value.marque || ''} ${selectedVehicle.value.modele || ''} • ${selectedVehicle.value.plaque || 'sans plaque'}`.trim()
  if (vehicleForm.marque || vehicleForm.modele || vehicleForm.plaque) return `${vehicleForm.marque || ''} ${vehicleForm.modele || ''} • ${vehicleForm.plaque || 'sans plaque'}`.trim()
  return 'Aucun véhicule sélectionné'
})

const commissionPreview = computed(() => {
  const price = Number.parseFloat(String(depotForm.prixVenteSouhaite || '0').replace(',', '.')) || 0
  const value = Number.parseFloat(String(depotForm.commissionValeur || '0').replace(',', '.')) || 0
  const ht = depotForm.commissionType === 'pourcentage' ? (price * value) / 100 : value
  const ttc = ht * 1.2
  const net = price - ttc

  return { ht, ttc, net }
})

const attachedDocumentCount = computed(() => documentRows.value.filter(row => row.file).length)
const draftPublicUrl = computed(() => {
  const path = String(draftCompanion.value?.companion?.publicPath || '').trim()
  if (!path || !process.client) return ''
  const origin = window.location.origin
  return new URL(path, origin).toString()
})
const { dataUrl: draftQrCodeUrl } = useQrCode(draftPublicUrl, 180)

const debouncedSearchDeposants = useDebounceFn(async (value: string) => {
  deposantResults.value = await searchClients(value)
}, 250)

let companionPollTimer: number | null = null

function refreshOnFocus() {
  refreshDraftCompanion(true)
}

watch(deposantSearch, (value) => {
  if (value.trim().length < 2) {
    deposantResults.value = []
    debouncedSearchDeposants.cancel()
    return
  }
  debouncedSearchDeposants(value)
})

function selectDeposant(client: any) {
  selectedDeposant.value = client
  Object.assign(deposantForm, {
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
    return !!selectedDeposant.value || (!!deposantForm.prenom.trim() && !!deposantForm.nom.trim() && !!deposantForm.telephone.trim())
  }
  if (step.value === 2) {
    return !!selectedVehicle.value || !!vehicleForm.plaque.trim()
  }
  if (step.value === 3) {
    return !!depotForm.prixVenteSouhaite.trim() && !!String(depotForm.dureeMandat).trim()
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

async function ensureDeposant() {
  if (selectedDeposant.value?.id) return selectedDeposant.value.id
  const created = await createQuickClient({ ...deposantForm })
  selectedDeposant.value = created
  return created.id
}

async function ensureVehicle(deposantId: number) {
  if (selectedVehicle.value?.id) {
    selectedVehicle.value = await updateQuickVehicule(selectedVehicle.value.id, buildVoVehiclePayload(vehicleForm))
    return selectedVehicle.value.id
  }

  const created = await createQuickVehicule(buildVoVehiclePayload(vehicleForm, `/api/clients/${deposantId}`))

  selectedVehicle.value = created
  return created.id
}

async function uploadDocuments(depotId: number) {
  for (const row of documentRows.value) {
    if (!row.file || !row.type) continue

    const formData = new FormData()
    formData.append('file', row.file)
    formData.append('type', row.type)
    formData.append('depotId', String(depotId))
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

  selectedDeposant.value = adoptDraftEntity(selectedDeposant.value, full?.deposant || null)
  selectedVehicle.value = adoptDraftEntity(selectedVehicle.value, full?.vehicule || null)

  syncDraftField(deposantForm, 'prenom', full?.deposant?.prenom, deposantDraftSync)
  syncDraftField(deposantForm, 'nom', full?.deposant?.nom, deposantDraftSync)
  syncDraftField(deposantForm, 'telephone', full?.deposant?.telephone, deposantDraftSync)
  syncDraftField(deposantForm, 'email', full?.deposant?.email, deposantDraftSync)
  syncDraftField(deposantForm, 'adresse', full?.deposant?.adresse, deposantDraftSync)

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

  syncDraftField(depotForm as unknown as Record<string, any>, 'deposantIdType', full?.deposantIdType, depotDraftSync)
  syncDraftField(depotForm as unknown as Record<string, any>, 'deposantIdNumber', full?.deposantIdNumber, depotDraftSync)
  syncDraftField(
    depotForm as unknown as Record<string, any>,
    'deposantIdDate',
    full?.deposantIdDate ? String(full.deposantIdDate).slice(0, 10) : '',
    depotDraftSync,
  )
}

async function refreshDraftCompanion(silent = true) {
  if (!draftDepotId.value) return

  try {
    const full = await voStore.fetchDepotFull(draftDepotId.value)
    hydrateFromDraft(full)
  } catch (error: unknown) {
    if (!silent) {
      toast.add({ title: 'Erreur', description: error instanceof Error ? error.message : 'Erreur inconnue', color: 'error' })
    }
  }
}

async function activateCompanionNow(showToast = true) {
  activatingCompanion.value = true
  try {
    if (!draftDepotId.value) {
      const draft = await voStore.createDepot({
        prixVenteSouhaite: depotForm.prixVenteSouhaite || '0',
        commissionType: depotForm.commissionType,
        commissionValeur: depotForm.commissionValeur || '0',
        dateDebut: depotForm.dateDebut,
        dureeMandat: Number(depotForm.dureeMandat || 90),
        gestionnaireId: depotForm.gestionnaireId,
        deposantIdType: depotForm.deposantIdType,
        deposantIdNumber: depotForm.deposantIdNumber,
        deposantIdDate: depotForm.deposantIdDate || null,
        conditionsRestitution: depotForm.conditionsRestitution || null,
        assuranceInfo: depotForm.assuranceInfo || null,
        notes: depotForm.notes || null,
        status: 'brouillon',
      })
      draftDepotId.value = draft.id
    }

    await refreshDraftCompanion(!showToast)

    if (showToast) {
      toast.add({ title: 'Parcours compagnon activé', description: 'Le QR code dépôt-vente est prêt.', color: 'success' })
    }
  } catch (error: unknown) {
    toast.add({ title: 'Erreur', description: error instanceof Error ? error.message : 'Erreur inconnue', color: 'error' })
  } finally {
    activatingCompanion.value = false
  }
}

async function submit() {
  // Guard VIN (ISO 3779)
  if (vehicleForm.vin && !isValidVin(vehicleForm.vin)) {
    toast.add({ title: 'VIN invalide', description: vinErrorMessage(vehicleForm.vin) ?? 'VIN non conforme ISO 3779', color: 'error' })
    return
  }

  submitting.value = true
  try {
    const deposantId = await ensureDeposant()
    const vehiculeId = await ensureVehicle(deposantId)

    let depotId = draftDepotId.value

    if (depotId) {
      await voStore.updateDepot(depotId, {
        deposantId,
        vehiculeId,
        prixVenteSouhaite: depotForm.prixVenteSouhaite,
        commissionType: depotForm.commissionType,
        commissionValeur: depotForm.commissionValeur,
        dateDebut: depotForm.dateDebut,
        dureeMandat: Number(depotForm.dureeMandat),
        gestionnaireId: depotForm.gestionnaireId,
        deposantIdType: depotForm.deposantIdType,
        deposantIdNumber: depotForm.deposantIdNumber,
        deposantIdDate: depotForm.deposantIdDate || null,
        conditionsRestitution: depotForm.conditionsRestitution || null,
        assuranceInfo: depotForm.assuranceInfo || null,
        notes: depotForm.notes || null,
        status: 'actif',
        finalizeCompanionDraft: true,
      })
    } else {
      const depot = await voStore.createDepot({
        deposantId,
        vehiculeId,
        prixVenteSouhaite: depotForm.prixVenteSouhaite,
        commissionType: depotForm.commissionType,
        commissionValeur: depotForm.commissionValeur,
        dateDebut: depotForm.dateDebut,
        dureeMandat: Number(depotForm.dureeMandat),
        gestionnaireId: depotForm.gestionnaireId,
        deposantIdType: depotForm.deposantIdType,
        deposantIdNumber: depotForm.deposantIdNumber,
        deposantIdDate: depotForm.deposantIdDate || null,
        conditionsRestitution: depotForm.conditionsRestitution || null,
        assuranceInfo: depotForm.assuranceInfo || null,
        notes: depotForm.notes || null,
      })
      depotId = depot.id
    }

    await uploadDocuments(depotId)

    toast.add({
      title: draftDepotId.value ? 'Brouillon finalisé' : 'Dépôt créé',
      description: 'Le parcours compagnon PDA reste actif avec son QR code.',
      color: 'success',
    })
    navigateTo(`/vo/depots/${depotId}?companion=1`)
  } catch (error: unknown) {
    toast.add({ title: 'Erreur', description: error instanceof Error ? error.message : 'Erreur inconnue', color: 'error' })
  } finally {
    submitting.value = false
  }
}

onMounted(async () => {
  experts.value = await fetchExperts()
  categories.value = await fetchMotoCategories()
  // [SPRINT-5] I24 — Initialiser la durée mandat depuis ConfigAtelier.dureeDefautMandatJours
  const config = await fetchConfigAtelier()
  if (config?.duree_defaut_mandat_jours) {
    depotForm.dureeMandat = Number(config.duree_defaut_mandat_jours)
  }
  await activateCompanionNow(false)

  if (import.meta.client) {
    companionPollTimer = window.setInterval(() => {
      refreshDraftCompanion(true)
    }, 4000)
    window.addEventListener('focus', refreshOnFocus)
  }
})

onBeforeUnmount(() => {
  debouncedSearchDeposants.cancel()
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

.vo-search-list,
.vo-lines,
.vo-summary-block {
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
  margin: 14px 0;
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
  margin-bottom: 14px;
}

.grow {
  flex: 1;
}

.vo-selected-box,
.vo-info-box {
  display: grid;
  gap: 6px;
  padding: 12px;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.06);
  background: rgba(255, 255, 255, 0.03);
  margin-bottom: 14px;
}

.vo-subtitle-row,
.vo-footer-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: 16px;
}

.vo-doc-row {
  display: grid;
  grid-template-columns: 1fr 0.8fr 1.2fr auto;
  gap: 10px;
  align-items: center;
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

.vo-input-error {
  border-color: #EF4444 !important;
  box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2);
}

.vo-field-error {
  display: block;
  margin-top: 4px;
  font-size: 11px;
  color: #EF4444;
}

.vo-mt-6 { margin-top: 6px; }
.vo-mt-10 { margin-top: 10px; }
</style>