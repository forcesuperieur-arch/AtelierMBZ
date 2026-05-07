<template>
  <div>
    <PitPageHeader
      title="Planning"
      :subtitle="`${currentViewRange.label || 'Semaine en cours'} · ${normalizedRdvs.length} RDVs`"
    >
      <template #actions>
        <PitButton variant="ghost" size="sm" :loading="refreshing" @click="refreshPlanning">
          <UIcon name="i-heroicons-arrow-path" class="w-4 h-4" :class="{ 'animate-spin': refreshing }" />
          <span class="hidden sm:inline">Actualiser</span>
        </PitButton>
        <PitButton v-if="hasPerm('rdv', 'create')" @click="openQuickCreate()">
          <UIcon name="i-heroicons-plus" class="w-4 h-4 mr-1" />
          RDV rapide
        </PitButton>
      </template>
    </PitPageHeader>

    <template v-if="loading">
      <div class="pit-kpi-skeleton">
        <PitSkeleton type="kpi" />
      </div>
      <PitSkeleton type="table" :rows="6" :columns="7" />
    </template>

    <template v-else>
      <!-- KPIs -->
      <div class="pit-kpi-grid">
        <PitTachometer
          :value="`${normalizedRdvs.length}/${ponts.length * 5}`"
          label="Charge visible"
          :progress="Math.min(normalizedRdvs.length / (ponts.length * 5) * 100, 100)"
          icon="i-heroicons-calendar"
        />
        <PitTachometer
          :value="String(kpis.conflicts)"
          label="Conflits"
          :progress="kpis.conflicts > 0 ? 100 : 0"
          icon="i-heroicons-exclamation-triangle"
        />
        <PitTachometer
          :value="String(kpis.unassigned)"
          label="Sans affectation"
          :progress="Math.min(kpis.unassigned / 20 * 100, 100)"
          icon="i-heroicons-user-group"
        />
        <PitTachometer
          :value="String(kpis.late)"
          label="Retards"
          :progress="Math.min(kpis.late / 10 * 100, 100)"
          icon="i-heroicons-clock"
        />
      </div>

      <!-- Navigation semaine -->
      <div class="pit-planning-nav">
        <div class="pit-planning-nav-arrows">
          <button class="pit-planning-nav-btn" @click="goPrevWeek">
            <UIcon name="i-heroicons-chevron-left" class="w-5 h-5" />
          </button>
          <span class="pit-planning-nav-label">{{ currentViewRange.label }}</span>
          <button class="pit-planning-nav-btn" @click="goNextWeek">
            <UIcon name="i-heroicons-chevron-right" class="w-5 h-5" />
          </button>
        </div>
        <div class="pit-planning-nav-views">
          <button :class="['pit-planning-view-btn', viewMode === 'grid' && 'is-active']" @click="viewMode = 'grid'">
            <UIcon name="i-heroicons-table-cells" class="w-4 h-4" />
          </button>
          <button :class="['pit-planning-view-btn', viewMode === 'list' && 'is-active']" @click="viewMode = 'list'">
            <UIcon name="i-heroicons-list-bullet" class="w-4 h-4" />
          </button>
        </div>
      </div>

      <!-- Filtres mécaniciens -->
      <div v-if="mecaniciens.length" class="pit-planning-filters">
        <span class="pit-planning-filters-label">Mécaniciens :</span>
        <button
          v-for="m in mecaniciens"
          :key="m.id"
          :class="['pit-planning-meca-chip', activeMecas.includes(m.id) && 'is-active']"
          @click="toggleMeca(m.id)"
        >
          <span class="pit-planning-meca-dot" :style="{ background: m.couleur || '#8B5CF6' }" />
          {{ m.prenom }} {{ m.nom?.charAt(0) }}.
        </button>
        <button v-if="activeMecas.length" class="pit-planning-meca-chip pit-planning-meca-chip--reset" @click="activeMecas = []">
          <UIcon name="i-heroicons-x-mark" class="w-3 h-3" /> Tous
        </button>
      </div>

      <!-- Content -->
      <PlanningGrid
        v-if="viewMode === 'grid'"
        :ponts="ponts"
        :rdvs="activePlanningRdvs"
        :horaires="horaires"
        :mecaniciens="mecaniciens"
        :week-start="currentViewRange.start"
        :can-create="hasPerm('rdv', 'create')"
        :can-drag="hasPerm('rdv', 'edit')"
        @select-rdv="onSelectRdv"
        @move-rdv="onMoveRdv"
        @create-at="onCreateAt"
      />

      <PlanningList
        v-else
        :rdvs="activePlanningRdvs"
        @select-rdv="onSelectRdv"
      />
    </template>

    <!-- Modales conservées du script original -->
    <PitModal v-model:open="showQuickCreateModal" size="lg" title="Nouveau RDV">
      <template #body>
        <div class="pit-modal-sections">
          <!-- quick create form content -->
          <div class="pit-modal-section">
            <div class="pit-section-label">Client</div>
            <PitInput v-model="quickClientSearch" placeholder="Rechercher un client..." />
          </div>
          <div class="pit-modal-section">
            <div class="pit-section-label">Date & Heure</div>
            <div class="pit-modal-row">
              <PitInputDate v-model="quickForm.date_rdv" />
              <PitInputTime v-model="quickForm.heure_debut" />
            </div>
          </div>
          <div class="pit-modal-section">
            <div class="pit-section-label">Prestation</div>
            <PitInput v-model="quickForm.type_intervention" placeholder="Type d'intervention" />
          </div>
        </div>
      </template>
      <template #footer>
        <PitButton variant="ghost" @click="showQuickCreateModal = false">Annuler</PitButton>
        <PitButton variant="primary" :loading="quickSubmitting" @click="submitQuickCreate">Créer</PitButton>
      </template>
    </PitModal>

    <!-- Panneau détail RDV -->
    <PitSlideover v-model="showRdvModal" @update:model-value="v => { if (!v) showRdvModal = false }">
      <template #icon><UIcon name="i-heroicons-calendar-days" class="w-5 h-5 text-[var(--accent)]" /></template>
      <template #title>
        <span v-if="selectedRdv">{{ selectedRdv.type_intervention || 'Rendez-vous' }}</span>
        <span v-else>Détail RDV</span>
      </template>

      <template v-if="selectedRdv">
        <!-- Status + loading -->
        <div class="flex items-center gap-3 mb-5">
          <StatusBadge :status="selectedRdv.status ?? selectedRdv.statut" />
          <span v-if="modalLoading" class="text-xs text-[var(--text-muted)]">Chargement…</span>
        </div>

        <!-- Infos clés -->
        <div class="rdv-detail-grid mb-5">
          <div class="rdv-detail-item">
            <div class="rdv-detail-label">Date</div>
            <div class="rdv-detail-value">{{ formatDateDisplay(selectedRdv.date_rdv) }}</div>
          </div>
          <div class="rdv-detail-item">
            <div class="rdv-detail-label">Heure</div>
            <div class="rdv-detail-value">{{ selectedRdv.heure_debut?.slice(0,5) }} ({{ formatDuration(selectedRdv.temps_estime ?? selectedRdv.duree_estimee ?? 60) }})</div>
          </div>
          <div class="rdv-detail-item">
            <div class="rdv-detail-label">Mécanicien</div>
            <div class="rdv-detail-value">{{ selectedRdv.mecanicien_nom || '—' }}</div>
          </div>
          <div class="rdv-detail-item">
            <div class="rdv-detail-label">Pont</div>
            <div class="rdv-detail-value">{{ ponts.find((p: any) => p.id === selectedRdv.pont_id)?.nom || (selectedRdv.pont?.nom) || '—' }}</div>
          </div>
        </div>

        <!-- Client -->
        <div class="rdv-detail-section mb-4">
          <div class="rdv-detail-section-title">Client</div>
          <div class="rdv-detail-grid">
            <div class="rdv-detail-item rdv-detail-item--full">
              <div class="rdv-detail-label">Nom</div>
              <div class="rdv-detail-value font-medium">{{ selectedRdv.client_nom || '—' }}</div>
            </div>
            <div class="rdv-detail-item">
              <div class="rdv-detail-label">Téléphone</div>
              <div class="rdv-detail-value">
                <a v-if="selectedRdv.client?.telephone" :href="`tel:${selectedRdv.client.telephone}`" class="text-[var(--accent)]">{{ selectedRdv.client.telephone }}</a>
                <span v-else>—</span>
              </div>
            </div>
            <div class="rdv-detail-item">
              <div class="rdv-detail-label">Email</div>
              <div class="rdv-detail-value text-xs">{{ selectedRdv.client?.email || '—' }}</div>
            </div>
          </div>
        </div>

        <!-- Véhicule -->
        <div class="rdv-detail-section mb-4">
          <div class="rdv-detail-section-title">Véhicule</div>
          <div class="rdv-detail-grid">
            <div class="rdv-detail-item">
              <div class="rdv-detail-label">Modèle</div>
              <div class="rdv-detail-value">{{ selectedRdv.vehicule_info || (selectedRdv.vehicule ? [selectedRdv.vehicule.marque, selectedRdv.vehicule.modele].filter(Boolean).join(' ') : '') || '—' }}</div>
            </div>
            <div class="rdv-detail-item">
              <div class="rdv-detail-label">Plaque</div>
              <div class="rdv-detail-value font-mono">{{ selectedRdv.vehicule?.plaque || selectedRdv.vehicule_plaque || '—' }}</div>
            </div>
          </div>
        </div>

        <!-- Commentaire -->
        <div v-if="selectedRdv.commentaire" class="rdv-detail-section mb-4">
          <div class="rdv-detail-section-title">Commentaire client</div>
          <div class="rdv-detail-value text-sm leading-relaxed">{{ selectedRdv.commentaire }}</div>
        </div>

        <!-- OR status -->
        <div v-if="selectedRdv.ordresReparation?.length || selectedRdv.ordres_reparation?.length" class="rdv-detail-section mb-4">
          <div class="rdv-detail-section-title">Ordre de réparation</div>
          <div class="flex items-center gap-2">
            <StatusBadge :status="(selectedRdv.ordresReparation ?? selectedRdv.ordres_reparation)[0]?.statut" />
            <span class="text-xs text-[var(--text-muted)]">{{ (selectedRdv.ordresReparation ?? selectedRdv.ordres_reparation)[0]?.numeroOr }}</span>
          </div>
        </div>

        <!-- Hint workflow -->
        <div v-if="workflowStatusHint" class="mb-4 px-3 py-2 rounded text-xs text-[var(--text-muted)]" style="background: var(--bg-elevated)">
          {{ workflowStatusHint }}
        </div>

        <!-- Transitions primaires -->
        <div v-if="primaryTransitions.length" class="mb-4">
          <div class="rdv-detail-label mb-2">Actions</div>
          <div class="flex flex-wrap gap-2">
            <PitButton
              v-for="t in primaryTransitions"
              :key="t.name"
              size="sm"
              :variant="t.color === 'error' ? 'danger' : t.color === 'success' ? 'primary' : 'secondary'"
              :loading="transitioning === t.name"
              @click="handleTransitionClick(t)"
            >
              {{ transitionLabel(t) }}
            </PitButton>
          </div>
        </div>

        <!-- Transitions secondaires -->
        <div v-if="secondaryTransitions.length" class="mb-4">
          <div class="flex flex-wrap gap-2">
            <PitButton
              v-for="t in secondaryTransitions"
              :key="t.name"
              size="xs"
              variant="ghost"
              :loading="transitioning === t.name"
              @click="handleTransitionClick(t)"
            >
              {{ transitionLabel(t) }}
            </PitButton>
          </div>
        </div>

        <!-- Footer discret -->
        <div class="mt-6 pt-4 border-t flex items-center justify-end" style="border-color: var(--border-light)">
          <span class="text-xs text-[var(--text-muted)]">#{{ selectedRdv.id }}</span>
        </div>
      </template>

      <template v-else>
        <div class="text-sm text-[var(--text-muted)]">Aucun RDV sélectionné.</div>
      </template>
    </PitSlideover>

    <!-- Modale annulation RDV -->
    <PitModal v-model:open="showAnnulationModal" title="Annuler le RDV">
      <template #body>
        <div class="pit-modal-sections">
          <div class="pit-modal-section">
            <div class="pit-section-label">Motif</div>
            <select v-model="annulationForm.motif" class="pit-select w-full">
              <option v-for="m in ANNULATION_MOTIFS" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
          </div>
          <div class="pit-modal-section">
            <div class="pit-section-label">Commentaire (optionnel)</div>
            <PitTextarea v-model="annulationForm.commentaire" :rows="3" placeholder="Précisions…" />
          </div>
        </div>
      </template>
      <template #footer>
        <PitButton variant="ghost" @click="showAnnulationModal = false">Retour</PitButton>
        <PitButton variant="danger" @click="submitAnnulation">Confirmer l'annulation</PitButton>
      </template>
    </PitModal>

    <!-- Modale paiement -->
    <PitModal v-model:open="showPayerModal" title="Encaisser le RDV">
      <template #body>
        <div class="pit-modal-sections">
          <div class="pit-modal-section">
            <div class="pit-section-label">Mode de paiement</div>
            <div class="flex gap-2 flex-wrap">
              <PitButton
                v-for="m in PAIEMENT_MODES"
                :key="m.value"
                size="sm"
                :variant="payerForm.mode === m.value ? 'primary' : 'secondary'"
                @click="payerForm.mode = m.value"
              >{{ m.label }}</PitButton>
            </div>
          </div>
          <div class="pit-modal-section">
            <div class="pit-section-label">Montant encaissé (€)</div>
            <PitInput v-model="payerForm.montant" type="number" step="0.01" min="0" placeholder="0.00" />
          </div>
          <div v-if="payerForm.mode === 'cheque'" class="pit-modal-section">
            <div class="pit-section-label">N° chèque</div>
            <PitInput v-model="payerForm.reference" placeholder="Ex : 1234567" />
          </div>
          <div v-if="payerForm.mode === 'virement'" class="pit-modal-section">
            <div class="pit-section-label">Référence virement</div>
            <PitInput v-model="payerForm.reference" placeholder="Ex : VIR-20260506" />
          </div>
        </div>
      </template>
      <template #footer>
        <PitButton variant="ghost" @click="showPayerModal = false">Retour</PitButton>
        <PitButton variant="primary" :loading="transitioning === 'payer'" @click="submitPayer">Confirmer l'encaissement</PitButton>
      </template>
    </PitModal>

    <!-- Modale reporter -->
    <PitModal v-model:open="showReporterModal" title="Reporter le RDV">
      <template #body>
        <div class="pit-modal-sections">
          <div class="pit-modal-section">
            <div class="pit-section-label">Nouvelle date</div>
            <PitInputDate v-model="reporterForm.date_rdv" />
          </div>
          <div class="pit-modal-section">
            <div class="pit-section-label">Heure</div>
            <PitInputTime v-model="reporterForm.heure_debut" />
          </div>
          <div class="pit-modal-section">
            <div class="pit-section-label">Mécanicien</div>
            <select v-model="reporterForm.mecanicien_id" class="pit-select w-full">
              <option :value="null">— Non assigné</option>
              <option v-for="m in mecaniciens" :key="m.id" :value="m.id">{{ m.prenom }} {{ m.nom }}</option>
            </select>
          </div>
          <div class="pit-modal-section">
            <div class="pit-section-label">Pont</div>
            <select v-model="reporterForm.pont_id" class="pit-select w-full">
              <option :value="null">— Non assigné</option>
              <option v-for="p in ponts" :key="p.id" :value="p.id">{{ p.nom }}</option>
            </select>
          </div>
        </div>
      </template>
      <template #footer>
        <PitButton variant="ghost" @click="showReporterModal = false">Retour</PitButton>
        <PitButton variant="primary" :loading="transitioning === 'reporter'" @click="submitReporter">Confirmer le report</PitButton>
      </template>
    </PitModal>

    <!-- Modale confirmation simple (facturer, restituer, no-show) -->
    <PitModal v-model:open="showConfirmModal" :title="confirmModal.title">
      <template #body>
        <div class="pit-modal-section">
          <p class="text-sm text-[var(--text-secondary)] leading-relaxed">{{ confirmModal.message }}</p>
        </div>
      </template>
      <template #footer>
        <PitButton variant="ghost" @click="showConfirmModal = false">Annuler</PitButton>
        <PitButton
          :variant="confirmModal.danger ? 'danger' : 'primary'"
          :loading="transitioning === confirmModal.transitionName"
          @click="submitConfirm"
        >{{ confirmModal.cta }}</PitButton>
      </template>
    </PitModal>
  </div>
</template>



<script setup lang="ts">
import { normalizeRdv } from '~/composables/useNormalize'
import { toNumber, toNullableNumber } from '~/composables/useFormatNumber'
import { unwrapList } from '~/composables/useHydraHelpers'
import { normalizeDateValue, normalizeTimeValue } from '~/composables/useDateTime'
import { usePlanningModals } from '~/composables/usePlanningModals'
import { usePlanning } from '~/composables/usePlanning'

const api = useApi()
const { formatDuration } = useFormat()
const toast = useToast()
const rdvStore = useRdvStore()
const route = useRoute()
const router = useRouter()
const { hasPerm } = useAuth()

const planning = usePlanning()
const { currentViewRange, ponts, rawRdvs, mecaniciens, horaires, prestations, normalizedRdvs, loadPlanningData, loadWeekRdvs } = planning

const loading = ref(true)
const refreshing = ref(false)
const modalLoading = ref(false)
const quickSubmitting = ref(false)
const editSaving = ref(false)
const deleting = ref(false)
const transitioning = ref('')

const activeMecas = ref<number[]>([])
const availableTransitions = ref<Array<{ name: string; label: string; color: string }>>([])
const primaryTransitions = computed(() => availableTransitions.value.filter(t => !SECONDARY_TRANSITIONS.has(t.name)))
const secondaryTransitions = computed(() => availableTransitions.value.filter(t => SECONDARY_TRANSITIONS.has(t.name)))

const {
  showQuickCreateModal,
  showRdvModal,
  showAnnulationModal,
  selectedRdv,
  openRdvModal,
  openAnnulationModal,
  closeAllModals,
} = usePlanningModals()

const showOrModal = ref(false)
const selectedOrId = ref<string | number | null>(null)
function openOrModal(id: string | number) {
  selectedOrId.value = id
  showOrModal.value = true
}

const annulationTempName = ref("annuler")
const editTab = ref('0')
const viewMode = ref<'grid' | 'list'>('grid')
const selectedRdvHistory = ref<any[]>([])
const selectedRdvHistoryLoading = ref(false)

const ANNULATION_MOTIFS = [
  { label: "Client décommandé", value: "client_desiste" },
  { label: "Atelier indisponible", value: "atelier_indisponible" },
  { label: "Erreur / Doublon", value: "erreur_saisie" },
  { label: "Véhicule vendu", value: "vehicule_vendu" },
  { label: "Conditions météo", value: "meteo" },
  { label: "Autre", value: "autre" }
]

const annulationForm = reactive({
  motif: "autre",
  commentaire: "",
  proposer_alternatives: false,
  creneaux_alternatifs: ""
})

function confirmAnnulation(name: string) {
  const status = selectedRdv.value?.status ?? selectedRdv.value?.statut
  annulationTempName.value = name
  annulationForm.motif = status === "en_attente" ? "atelier_indisponible" : "client_desiste"
  annulationForm.commentaire = ""
  annulationForm.proposer_alternatives = false
  annulationForm.creneaux_alternatifs = ""
  openAnnulationModal(selectedRdv.value)
}

async function submitAnnulation() {
  await applyTransition(annulationTempName.value, {
    motif: annulationForm.motif,
    commentaire: annulationForm.commentaire,
    proposer_alternatives: annulationForm.proposer_alternatives,
    creneaux_alternatifs: annulationForm.creneaux_alternatifs
  })
  showAnnulationModal.value = false
}

// ─── Modale paiement ────────────────────────────────────────────────────────
const showPayerModal = ref(false)
const PAIEMENT_MODES = [
  { label: 'Carte bancaire', value: 'cb' },
  { label: 'Espèces', value: 'especes' },
  { label: 'Virement', value: 'virement' },
  { label: 'Chèque', value: 'cheque' },
]
const payerForm = reactive({ mode: 'cb', montant: '', reference: '' })

function openPayerModal() {
  payerForm.mode = 'cb'
  payerForm.montant = ''
  payerForm.reference = ''
  showPayerModal.value = true
}

async function submitPayer() {
  await applyTransition('payer', {
    mode_paiement: payerForm.mode,
    montant: payerForm.montant ? parseFloat(payerForm.montant) : undefined,
    reference: payerForm.reference || undefined,
  })
  showPayerModal.value = false
}

// ─── Modale reporter ────────────────────────────────────────────────────────
const showReporterModal = ref(false)
const reporterForm = reactive({
  date_rdv: '',
  heure_debut: '',
  mecanicien_id: null as number | null,
  pont_id: null as number | null,
})

function openReporterModal() {
  reporterForm.date_rdv = selectedRdv.value?.date_rdv ?? ''
  reporterForm.heure_debut = selectedRdv.value?.heure_debut?.slice(0, 5) ?? ''
  reporterForm.mecanicien_id = selectedRdv.value?.mecanicien_id ?? null
  reporterForm.pont_id = selectedRdv.value?.pont_id ?? null
  showReporterModal.value = true
}

async function submitReporter() {
  if (!selectedRdv.value?.id) return
  try {
    transitioning.value = 'reporter'
    // PATCH les nouvelles date/heure/mecanicien/pont
    await api.patch(`/rendez-vous/${selectedRdv.value.id}`, {
      dateRdv: reporterForm.date_rdv,
      heureRdv: reporterForm.heure_debut ? reporterForm.heure_debut + ':00' : undefined,
      mecanicien: reporterForm.mecanicien_id ? `/api/mecaniciens/${reporterForm.mecanicien_id}` : null,
      pont: reporterForm.pont_id ? `/api/ponts/${reporterForm.pont_id}` : null,
    })
    // Si le statut le permet, applique aussi la transition workflow reporter
    const status = selectedRdv.value?.status ?? selectedRdv.value?.statut
    if (['reception', 'no_show'].includes(status)) {
      await applyTransition('reporter', {})
    } else {
      await refreshPlanning()
      const updated = rawRdvs.value.find((r: any) => r.id === selectedRdv.value!.id)
      if (updated) selectedRdv.value = normalizeRdv(updated)
    }
    toast.add({ title: 'RDV reporté', color: 'success' })
    showReporterModal.value = false
  } catch {
    toast.add({ title: 'Erreur lors du report', color: 'error' })
  } finally {
    transitioning.value = ''
  }
}

// ─── Modale confirmation simple (facturer / restituer / no-show) ─────────────
const showConfirmModal = ref(false)
const confirmModal = reactive({
  title: '',
  message: '',
  cta: 'Confirmer',
  danger: false,
  transitionName: '',
})

function openConfirmModal(opts: { title: string; message: string; cta: string; danger?: boolean; transitionName: string }) {
  confirmModal.title = opts.title
  confirmModal.message = opts.message
  confirmModal.cta = opts.cta
  confirmModal.danger = opts.danger ?? false
  confirmModal.transitionName = opts.transitionName
  showConfirmModal.value = true
}

async function submitConfirm() {
  await applyTransition(confirmModal.transitionName)
  showConfirmModal.value = false
}

// ─── Dispatching central des boutons de transition ───────────────────────────
function handleTransitionClick(t: { name: string }) {
  switch (t.name) {
    case 'annuler':
      return confirmAnnulation(t.name)
    case 'declarer_no_show':
    case 'no_show':
      return openConfirmModal({
        title: 'Déclarer No-Show',
        message: 'Le client ne s\'est pas présenté. Cette action enregistre un no-show et libère le créneau.',
        cta: 'Déclarer No-Show',
        danger: true,
        transitionName: t.name,
      })
    case 'payer':
      return openPayerModal()
    case 'reporter':
      return openReporterModal()
    case 'facturer':
      return openConfirmModal({
        title: 'Facturer l\'intervention',
        message: 'Déclencher la facturation de cette intervention ? L\'OR sera finalisé et une facture sera générée.',
        cta: 'Facturer',
        transitionName: t.name,
      })
    case 'restituer':
      return openConfirmModal({
        title: 'Restituer le véhicule',
        message: 'Confirmer la restitution du véhicule au client ? Cette action clôt l\'intervention côté atelier.',
        cta: 'Confirmer la restitution',
        transitionName: t.name,
      })
    case 'restituer_partiel':
      return openConfirmModal({
        title: 'Restitution partielle',
        message: 'Confirmer une restitution partielle ? Le dossier restera ouvert en attente de clôture définitive.',
        cta: 'Restitution partielle',
        transitionName: t.name,
      })
    default:
      return applyTransition(t.name)
  }
}

const quickSelectedPrestas = ref<number[]>([])
const quickClientSearch = ref('')
const quickClientResults = ref<any[]>([])
const quickSelectedClient = ref<any | null>(null)
const quickVehicleSearch = ref('')
const quickVehicleFound = ref(false)

const HISTORY_STATUSES = ['termine', 'restitue', 'facture', 'paye', 'annule']
const PRESTATION_LOCK_STATUSES = ['reception', 'en_cours', 'termine', 'restitue', 'facture', 'paye']
const DAY_LABELS = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim']
const MOTO_TYPES = ['Roadster', 'Sportive', 'Trail', 'Custom', 'Scooter', 'Enduro', 'Adventure', 'GT']

const editForm = reactive({
  date_rdv: '',
  heure_debut: '10:00',
  type_intervention: '',
  temps_estime: 60,
  commentaire: '',
  mecanicien_id: null as number | null,
  pont_id: null as number | null,
  priorite: 'normale' as 'haute' | 'normale' | 'basse',
  tags: [] as string[],
})

const editDureeHHMM = computed({
  get(): string {
    const total = Math.max(0, Math.round(Number(editForm.temps_estime ?? 60)))
    const h = Math.floor(total / 60)
    const m = total % 60
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
  },
  set(val: string) {
    const [h, m] = val.split(':').map(Number)
    editForm.temps_estime = ((h ?? 0) * 60) + (m ?? 0) || 15
  },
})

const editTagsInput = computed({
  get: () => (editForm.tags || []).join(', '),
  set: (val: string) => { editForm.tags = val.split(',').map(s => s.trim()).filter(Boolean) }
})

const receptionForm = reactive({
  kilometrage: '',
  etat_vehicule: '',
  notes_reception: '',
})

const quickForm = reactive({
  date_rdv: new Date().toISOString().slice(0, 10),
  heure_debut: '10:00',
  client_id: null as number | null,
  client_prenom: '',
  client_nom: '',
  client_telephone: '',
  client_email: '',
  vehicule_marque: '',
  vehicule_modele: '',
  vehicule_plaque: '',
  vehicule_cylindree: '',
  vehicule_type: '',
  type_intervention: '',
  commentaire: '',
  mecanicien_id: null as number | null,
  pont_id: null as number | null,
})

const {
  marqueSuggestions: quickMarqueSuggestions,
  modeleSuggestions: quickModeleSuggestions,
  onMarqueInput: onQuickMarqueInput,
  onModeleInput: onQuickModeleInput,
  selectMarque: selectQuickMarque,
  selectModele: selectQuickModele,
  deferHideMarqueSuggestions: hideQuickMarqueSuggestions,
  deferHideModeleSuggestions: hideQuickModeleSuggestions,
  suggestionLabel: quickSuggestionLabel,
} = useMotoAutocomplete({
  form: quickForm,
  marqueKey: 'vehicule_marque',
  modeleKey: 'vehicule_modele',
  cylindreeKey: 'vehicule_cylindree',
  typeKey: 'vehicule_type',
})

const transitionCatalog: Record<string, { label: string; color: string }> = {
  reserver: { label: 'Réserver le créneau', color: 'neutral' },
  confirmer: { label: 'Valider et confirmer', color: 'primary' },
  reception: { label: 'Réceptionner', color: 'warning' },
  start_travail: { label: 'Démarrer', color: 'warning' },
  mettre_en_pause: { label: 'Mettre en pause', color: 'warning' },
  reprendre: { label: 'Reprendre', color: 'warning' },
  mettre_en_attente_pieces: { label: 'Attente pièces', color: 'warning' },
  reprendre_apres_pieces: { label: 'Reprendre (pièces reçues)', color: 'warning' },
  mettre_en_attente_reprise: { label: 'Attente reprise', color: 'neutral' },
  reprendre_demain: { label: 'Reprendre demain', color: 'neutral' },
  terminer: { label: 'Terminer', color: 'success' },
  restituer: { label: 'Restituer', color: 'info' },
  restituer_partiel: { label: 'Restitution partielle', color: 'info' },
  facturer: { label: 'Facturer', color: 'primary' },
  payer: { label: 'Encaisser', color: 'success' },
  annuler: { label: 'Annuler', color: 'error' },
  declarer_no_show: { label: 'Déclarer No Show', color: 'error' },
  no_show: { label: 'No Show', color: 'error' },
  reporter: { label: 'Reporter', color: 'neutral' },
  mettre_en_gardiennage: { label: 'Mise en gardiennage', color: 'warning' },
  passer_gardiennage: { label: 'Gardiennage', color: 'warning' },
  sortir_gardiennage: { label: 'Sortir du gardiennage', color: 'success' },
}

const HIDDEN_RECEPTION_TRANSITIONS = [
  'start_travail', 
  'mettre_en_pause', 
  'reprendre', 
  'terminer',
  'mettre_en_attente_pieces', 
  'reprendre_apres_pieces', 
  'attendre_pieces', 
  'mettre_en_attente_reprise', 
  'reprendre_demain',
  'no_show' // using declarer_no_show instead
]

const SECONDARY_TRANSITIONS = new Set(['annuler', 'declarer_no_show', 'reporter', 'mettre_en_gardiennage', 'passer_gardiennage'])

const canCreateRdv = computed(() => hasPerm('rdv', 'create'))
const canEditRdv = computed(() => hasPerm('rdv', 'edit'))
const canDeleteRdv = computed(() => hasPerm('rdv', 'delete'))
const selectedIsHistorical = computed(() => isHistoricalStatus(selectedRdv.value?.status ?? selectedRdv.value?.statut))
const prestationLocked = computed(() => PRESTATION_LOCK_STATUSES.includes(selectedRdv.value?.status ?? selectedRdv.value?.statut ?? ''))
const canDeleteSelected = computed(() => canDeleteRdv.value && !!selectedRdv.value && !selectedIsHistorical.value)
const selectedReceptionState = computed(() => parseReceptionState(selectedRdv.value?.etat_vehicule_reception ?? selectedRdv.value?.etat_vehicule ?? selectedRdv.value?.etatVehicule))
const hasSchedulingEdits = computed(() => {
  if (!selectedRdv.value) return false
  return editForm.date_rdv !== (selectedRdv.value.date_rdv || '')
    || editForm.heure_debut !== (selectedRdv.value.heure_debut || '')
    || toNullableNumber(editForm.pont_id) !== toNullableNumber(selectedRdv.value.pont?.id ?? selectedRdv.value.pont_id)
})
const workflowStatusHint = computed(() => {
  switch (selectedRdv.value?.status ?? selectedRdv.value?.statut) {
    case 'en_attente':
      return 'Demande reçue, créneau pas encore bloqué'
    case 'reserve':
      return 'Créneau bloqué, validation comptoir encore requise'
    case 'confirme':
      return 'Disponibilité validée, prêt pour la réception'
    case 'reception':
      return 'Véhicule réceptionné, intervention prête à démarrer'
    default:
      return 'Suivi atelier en cours'
  }
})
const workflowTransitionHint = computed(() => {
  switch (selectedRdv.value?.status ?? selectedRdv.value?.statut) {
    case 'en_attente':
      return 'Demande entrante: ajuste le jour ou l’heure si nécessaire, puis réserve le créneau. Si la demande n’est pas retenue, refuse-la explicitement.'
    case 'reserve':
      return 'Le créneau est bloqué. Le réceptionnaire confirme si la disponibilité atelier est validée, ou reprogramme avant validation.'
    case 'confirme':
      return 'Le rendez-vous est validé. L’étape suivante normale est la réception physique du véhicule.'
    default:
      return 'Seules les transitions autorisées par le workflow sont proposées ici.'
  }
})

function normalizeText(value: unknown): string {
  return String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim()
}

function sanitizeAlphaNum(value: string): string {
  return normalizeText(value).toUpperCase().replace(/[^A-Z0-9]/g, '')
}

function formatRegistrationOrVin(value: string): string {
  const cleaned = sanitizeAlphaNum(value)
  if (!cleaned) return ''
  if (cleaned.length >= 11) return cleaned
  if (/^[A-Z]{2}\d{3}[A-Z]{2}$/.test(cleaned)) {
    return `${cleaned.slice(0, 2)}-${cleaned.slice(2, 5)}-${cleaned.slice(5, 7)}`
  }
  return cleaned
}

function addMinutesToTime(time: string, minutesToAdd: number): string {
  const [hours, minutes] = String(time || '00:00').split(':').map(Number)
  const total = ((hours || 0) * 60) + (minutes || 0) + Math.max(15, toNumber(minutesToAdd, 60))
  return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`
}

function getRelationId(value: any): number | null {
  if (typeof value === 'number' && Number.isFinite(value)) return value
  if (typeof value === 'string') {
    const parsed = Number(value.split('/').pop())
    return Number.isFinite(parsed) ? parsed : null
  }
  if (value && typeof value === 'object') {
    return getRelationId(value.id ?? value['@id'])
  }
  return null
}

function findPontById(value: any) {
  const id = toNullableNumber(value)
  if (!id) return null
  return ponts.value.find((pont: any) => Number(pont.id) === id) ?? null
}

function resolvePontMecanicienId(pontOrId: any): number | null {
  const pont = typeof pontOrId === 'object' ? pontOrId : findPontById(pontOrId)
  return toNullableNumber(pont?.mecanicien?.id ?? pont?.mecanicien_id ?? getRelationId(pont?.mecanicien))
}

function getMecanicienLabelById(value: any): string {
  const id = toNullableNumber(value)
  if (!id) return 'Non assigné'
  const mecanicien = mecaniciens.value.find((item: any) => Number(item.id) === id)
  return mecanicien ? `${mecanicien.prenom ?? ''} ${mecanicien.nom ?? ''}`.trim() : 'Non assigné'
}

function getPontMecanicienLabel(pontOrId: any): string {
  const pont = typeof pontOrId === 'object' ? pontOrId : findPontById(pontOrId)
  if (!pont) return 'Non assigné'
  if (pont.mecanicien && typeof pont.mecanicien === 'object') {
    const label = `${pont.mecanicien.prenom ?? ''} ${pont.mecanicien.nom ?? ''}`.trim()
    if (label) return label
  }
  return getMecanicienLabelById(resolvePontMecanicienId(pont))
}

function syncMecanicienFromPont(target: { pont_id: any; mecanicien_id: any }) {
  target.mecanicien_id = resolvePontMecanicienId(target.pont_id)
}

function prestationMatchesVehicle(prestation: any, source: any = quickForm) {
  const vehicleType = normalizeText(source.vehicule_type)
  const rawType = normalizeText(prestation.type_vehicule ?? prestation.typeVehicule ?? 'tous')
  const allowedTypes = rawType.split(/[;,/|]+/).map((item: string) => item.trim()).filter(Boolean)
  const typeMatches = !vehicleType
    || !allowedTypes.length
    || allowedTypes.includes('tous')
    || allowedTypes.includes('tout')
    || allowedTypes.includes('all')
    || allowedTypes.some((item: string) => item === vehicleType || item.includes(vehicleType) || vehicleType.includes(item))

  const cylindree = toNumber(source.vehicule_cylindree)
  const min = toNumber(prestation.cylindree_min ?? prestation.cylindreeMin)
  const max = toNumber(prestation.cylindree_max ?? prestation.cylindreeMax)
  const cylindreeMatches = (!min || !cylindree || cylindree >= min) && (!max || !cylindree || cylindree <= max)

  return typeMatches && cylindreeMatches
}

const assignablePonts = computed(() => {
  return [...ponts.value]
    .filter((pont: any) => Number(pont.isActive ?? pont.is_active ?? 1) !== 0)
    .sort((a: any, b: any) => String(a.nom || '').localeCompare(String(b.nom || '')))
})

const filteredQuickPrestations = computed(() => {
  return prestations.value.filter((presta: any) => prestationMatchesVehicle(presta, quickForm))
})

const quickSelectedPrestations = computed(() => filteredQuickPrestations.value.filter((presta: any) => quickSelectedPrestas.value.includes(Number(presta.id))))
const quickEstimateTotal = computed(() => quickSelectedPrestations.value.reduce((sum: number, presta: any) => sum + toNumber(presta.prix_base_ttc ?? presta.prix_base_ht), 0))
const quickEstimateDuration = computed(() => quickSelectedPrestations.value.reduce((sum: number, presta: any) => sum + toNumber(presta.temps_estime_minutes, 60), 0) || 60)
const quickEstimatedEnd = computed(() => addMinutesToTime(quickForm.heure_debut, quickEstimateDuration.value || 60))
const quickAssignedMecanicienLabel = computed(() => quickForm.pont_id ? getPontMecanicienLabel(quickForm.pont_id) : 'Affectation via le pont')
const editAssignedMecanicienLabel = computed(() => editForm.pont_id ? getPontMecanicienLabel(editForm.pont_id) : getMecanicienLabelById(editForm.mecanicien_id))

async function withTimeout<T>(promise: Promise<T>, label: string, ms = 12000): Promise<T> {
  let timeoutId: ReturnType<typeof setTimeout> | null = null
  const timeoutPromise = new Promise<T>((_, reject) => {
    timeoutId = setTimeout(() => reject(new Error(`${label}_timeout`)), ms)
  })

  try {
    return await Promise.race([promise, timeoutPromise])
  } finally {
    if (timeoutId) clearTimeout(timeoutId)
  }
}

function isHistoricalStatus(status?: string) {
  return HISTORY_STATUSES.includes(String(status || ''))
}

const activePlanningRdvs = computed(() => {
  const active = normalizedRdvs.value
  if (!activeMecas.value.length) return active
  return active.filter((rdv: any) => !rdv.mecanicien_id || activeMecas.value.includes(rdv.mecanicien_id))
})

const openDaysLabel = computed(() => {
  const openDays = horaires.value.filter((horaire: any) => Number(horaire.is_ouvert) === 1).map((horaire: any) => DAY_LABELS[horaire.jour_semaine])
  return openDays.length ? openDays.join(' · ') : 'jours non configurés'
})

const hourRangeLabel = computed(() => {
  const openHours = horaires.value.filter((horaire: any) => Number(horaire.is_ouvert) === 1)
  if (!openHours.length) return '10:00 – 19:00'
  const starts = openHours.map((horaire: any) => String(horaire.heure_ouverture || '10:00'))
  const ends = openHours.map((horaire: any) => String(horaire.heure_fermeture || '19:00'))
  return `${starts.sort()[0]} – ${ends.sort().slice(-1)[0]}`
})

const isPastWeek = computed(() => {
  const end = currentViewRange.value.end
  return end && end < new Date().toISOString().slice(0, 10)
})

const rdvEditTabItems = computed(() => [
  { label: 'Général', value: '0', icon: 'i-heroicons-user' },
  { label: 'Détails', value: '1', icon: 'i-heroicons-document-text' },
  { label: 'Planning', value: '2', icon: 'i-heroicons-calendar' },
  ...(selectedRdv.value?.id ? [{ label: 'Historique', value: '3', icon: 'i-heroicons-clock' }] : []),
])

const slideoverAccordionItems = computed(() => [
  { label: 'Infos RDV', value: 'infos', icon: 'i-heroicons-information-circle' },
  { label: 'Véhicule', value: 'vehicule', icon: 'i-heroicons-truck' },
  { label: 'Client', value: 'client', icon: 'i-heroicons-user' },
  { label: 'Actions rapides', value: 'actions', icon: 'i-heroicons-bolt' },
])

function prevTab() {
  const idx = Number(editTab.value)
  if (idx > 0) editTab.value = String(idx - 1)
}

function nextTab() {
  const max = (rdvEditTabItems.value.length || 4) - 1
  const idx = Number(editTab.value)
  if (idx < max) editTab.value = String(idx + 1)
}

function onListTransition(payload: { rdv: any; name: string }) {
  selectedRdv.value = normalizeRdv(payload.rdv)
  hydrateEditForms(selectedRdv.value)
  applyTransition(payload.name)
}

const kpis = computed(() => {
  const rdvs = activePlanningRdvs.value
  const today = new Date().toISOString().slice(0, 10)
  const todayRdvs = rdvs.filter((rdv: any) => rdv.date_rdv === today)
  const enCours = todayRdvs.filter((rdv: any) => rdv.status === 'en_cours').length
  const total = todayRdvs.length
  const unassigned = rdvs.filter((rdv: any) => !rdv.mecanicien_id || !rdv.pont_id).length
  const late = todayRdvs.filter((rdv: any) => {
    if (!['confirme', 'reserve', 'reception'].includes(rdv.status)) return false
    const [hour, minute] = String(rdv.heure_debut || '00:00').split(':').map(Number)
    const scheduled = new Date()
    scheduled.setHours(hour || 0, minute || 0, 0, 0)
    return Date.now() - scheduled.getTime() > 10 * 60 * 1000
  }).length

  let conflicts = 0
  for (let i = 0; i < rdvs.length; i++) {
    for (let j = i + 1; j < rdvs.length; j++) {
      const a = rdvs[i]
      const b = rdvs[j]
      if (a.date_rdv !== b.date_rdv) continue
      const startA = timeToMin(a.heure_debut)
      const startB = timeToMin(b.heure_debut)
      const endA = startA + toNumber(a.temps_estime, 60)
      const endB = startB + toNumber(b.temps_estime, 60)
      const overlap = startA < endB && startB < endA
      if (!overlap) continue
      if ((a.pont_id && a.pont_id === b.pont_id) || (a.mecanicien_id && a.mecanicien_id === b.mecanicien_id)) conflicts += 1
    }
  }

  return {
    charge: `${enCours}/${total}`,
    chargeDetail: `${total} RDV actifs du jour`,
    conflicts,
    unassigned,
    late,
  }
})

function transitionButtonStyle(color: string) {
  if (color === 'error') return 'background:rgba(239,68,68,0.14);color:#FCA5A5;border-color:rgba(239,68,68,0.28);'
  if (color === 'success') return 'background:rgba(16,185,129,0.14);color:#6EE7B7;border-color:rgba(16,185,129,0.28);'
  if (color === 'warning') return 'background:rgba(245,158,11,0.14);color:#FBBF24;border-color:rgba(245,158,11,0.28);'
  if (color === 'info') return 'background:rgba(59,130,246,0.14);color:#93C5FD;border-color:rgba(59,130,246,0.28);'
  return 'background:var(--bg-elevated);color:var(--text-primary);border-color:var(--border-default);'
}

function transitionLabel(transition: { name: string; label: string }) {
  const status = selectedRdv.value?.status ?? selectedRdv.value?.statut
  if (transition.name === 'annuler' && status === 'en_attente') return 'Refuser la demande'
  if (transition.name === 'reserver' && status === 'en_attente' && hasSchedulingEdits.value) return 'Déplacer puis réserver'
  if (transition.name === 'reserver' && status === 'en_attente') return 'Réserver ce créneau'
  if (transition.name === 'confirmer' && status === 'reserve') return 'Confirmer le RDV'
  return transition.label
}

function formatCurrency(value: any) {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(toNumber(value))
}

function formatDateDisplay(value: string) {
  if (!value) return '—'
  return new Date(`${value}T00:00:00`).toLocaleDateString('fr-FR', { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric' })
}

function timeToMin(value: string) {
  const [hours, minutes] = String(value || '00:00').split(':').map(Number)
  return (hours || 0) * 60 + (minutes || 0)
}

function toggleMeca(id: number) {
  const idx = activeMecas.value.indexOf(id)
  if (idx >= 0) activeMecas.value.splice(idx, 1)
  else activeMecas.value.push(id)
}

function fallbackTransitionsForStatus(status?: string) {
  const byStatus: Record<string, string[]> = {
    en_attente: ['reserver', 'confirmer', 'annuler'],
    reserve: ['confirmer', 'annuler'],
    confirme: ['reception', 'annuler'],
    reception: ['start_travail'],
    en_cours: ['terminer'],
    termine: ['restituer', 'facturer'],
    restitue: ['facturer'],
    facture: ['payer'],
  }

  return (byStatus[String(status || '')] || []).map((name) => ({
    name,
    label: transitionCatalog[name]?.label ?? name,
    color: transitionCatalog[name]?.color ?? 'neutral',
  }))
}

function hydrateEditForms(rdv: any) {
  if (!rdv) return
  editForm.date_rdv = rdv.date_rdv || new Date().toISOString().slice(0, 10)
  editForm.heure_debut = rdv.heure_debut || '09:00'
  editForm.type_intervention = rdv.type_intervention || ''
  editForm.temps_estime = toNumber(rdv.temps_estime, 60)
  editForm.commentaire = rdv.commentaire || ''
  editForm.mecanicien_id = toNullableNumber(rdv.mecanicien?.id ?? rdv.mecanicien_id)
  editForm.pont_id = toNullableNumber(rdv.pont?.id ?? rdv.pont_id)
  editForm.priorite = rdv.priorite || 'normale'
  editForm.tags = Array.isArray(rdv.tags) ? rdv.tags : []
  receptionForm.kilometrage = rdv.kilometrage ? String(rdv.kilometrage) : ''
  const receptionState = parseReceptionState(rdv.etat_vehicule_reception ?? rdv.etat_vehicule ?? rdv.etatVehicule)
  receptionForm.etat_vehicule = receptionState.observations || ''
  receptionForm.notes_reception = receptionState.reception_notes || ''
}

function parseReceptionState(raw: any) {
  if (!raw) return {}
  if (typeof raw === 'string') {
    try {
      const parsed = JSON.parse(raw)
      return parsed && typeof parsed === 'object' ? parsed : { observations: raw }
    } catch {
      return { observations: raw }
    }
  }
  return typeof raw === 'object' ? raw : {}
}

function resetQuickForm(prefill?: { date?: string; time?: string; pontId?: number | null }) {
  quickForm.date_rdv = prefill?.date || new Date().toISOString().slice(0, 10)
  quickForm.heure_debut = prefill?.time || '10:00'
  quickForm.client_id = null
  quickForm.client_prenom = ''
  quickForm.client_nom = ''
  quickForm.client_telephone = ''
  quickForm.client_email = ''
  quickForm.vehicule_marque = ''
  quickForm.vehicule_modele = ''
  quickForm.vehicule_plaque = ''
  quickForm.vehicule_cylindree = ''
  quickForm.vehicule_type = ''
  quickForm.type_intervention = ''
  quickForm.commentaire = ''
  quickForm.mecanicien_id = null
  quickForm.pont_id = prefill?.pontId ?? null
  quickSelectedPrestas.value = []
  quickClientSearch.value = ''
  quickClientResults.value = []
  quickSelectedClient.value = null
  quickVehicleSearch.value = ''
  quickVehicleFound.value = false
}

function openQuickCreate(prefill?: { date?: string; time?: string; pontId?: number | null }) {
  resetQuickForm(prefill)
  syncMecanicienFromPont(quickForm)
  showQuickCreateModal.value = true
}

function consumeWorkshopQuickCreateQuery() {
  if (String(route.query.create ?? '') !== '1') return

  openQuickCreate({
    date: normalizeDateValue(route.query.date) || new Date().toISOString().slice(0, 10),
    time: normalizeTimeValue(route.query.time) || '10:00',
    pontId: toNullableNumber(route.query.pontId),
  })

  const nextQuery = { ...route.query }
  delete nextQuery.create
  delete nextQuery.date
  delete nextQuery.time
  delete nextQuery.pontId
  router.replace({ query: nextQuery }).catch(() => {})
}

async function openRdvFromQuery() {
  const requestedId = Number(route.query.openRdv || 0)
  if (!Number.isFinite(requestedId) || requestedId <= 0) return

  const existing = normalizedRdvs.value.find((rdv: any) => Number(rdv.id) === requestedId)
  try {
    await withTimeout(onSelectRdv(existing || { id: requestedId }), 'planning_open_rdv', 8000)
  } catch {
    // Keep planning usable even if RDV modal hydration times out.
  }

  const nextQuery = { ...route.query }
  delete nextQuery.openRdv
  router.replace({ query: nextQuery }).catch(() => {})
}

let quickClientSearchTimeout: ReturnType<typeof setTimeout>
function searchQuickClients() {
  clearTimeout(quickClientSearchTimeout)
  if (quickClientSearch.value.trim().length < 2) {
    quickClientResults.value = []
    return
  }

  quickClientSearchTimeout = setTimeout(async () => {
    try {
      const data = await api.get(`/clients?search=${encodeURIComponent(quickClientSearch.value.trim())}`)
      quickClientResults.value = unwrapList(data)
    } catch {
      quickClientResults.value = []
    }
  }, 250)
}

function selectQuickClient(client: any) {
  quickSelectedClient.value = client
  quickClientSearch.value = `${client?.prenom ?? ''} ${client?.nom ?? ''}`.trim()
  quickClientResults.value = []

  quickForm.client_id = toNullableNumber(client?.id)
  quickForm.client_prenom = client?.prenom ?? ''
  quickForm.client_nom = client?.nom ?? ''
  quickForm.client_telephone = client?.telephone ?? ''
  quickForm.client_email = client?.email ?? ''

  if (Array.isArray(client?.vehicules) && client.vehicules.length) {
    const vehicle = client.vehicules[0]
    quickForm.vehicule_marque = vehicle?.marque ?? ''
    quickForm.vehicule_modele = vehicle?.modele ?? ''
    quickForm.vehicule_plaque = formatRegistrationOrVin(vehicle?.plaque ?? '')
    quickForm.vehicule_cylindree = vehicle?.cylindree ?? ''
    quickForm.vehicule_type = vehicle?.type_moto ?? vehicle?.typeMoto ?? vehicle?.univers ?? ''
    quickVehicleSearch.value = quickForm.vehicule_plaque
    quickVehicleFound.value = !!(quickForm.vehicule_marque || quickForm.vehicule_modele || quickForm.vehicule_plaque)
  }
}

function clearQuickClient() {
  quickSelectedClient.value = null
  quickClientSearch.value = ''
  quickClientResults.value = []
  quickForm.client_id = null
  quickForm.client_prenom = ''
  quickForm.client_nom = ''
  quickForm.client_telephone = ''
  quickForm.client_email = ''
}

async function searchQuickVehicle() {
  const query = formatRegistrationOrVin(quickVehicleSearch.value || quickForm.vehicule_plaque || '')
  quickVehicleSearch.value = query
  quickForm.vehicule_plaque = query

  if (!query) {
    quickVehicleFound.value = false
    return
  }

  try {
    let data: any = null
    try {
      data = await api.get(`/vehicule/${encodeURIComponent(query)}`)
    } catch {
      const collection = await api.get(`/vehicules?plaque=${encodeURIComponent(query)}`).catch(() => null)
      data = unwrapList(collection)[0] ?? null
    }

    if (data && (data.marque || data.modele || data.plaque)) {
      quickForm.vehicule_marque = data.marque || ''
      quickForm.vehicule_modele = data.modele || ''
      quickForm.vehicule_plaque = formatRegistrationOrVin(data.plaque || query)
      quickForm.vehicule_cylindree = data.cylindree || ''
      quickForm.vehicule_type = data.type_moto || data.typeMoto || data.univers || ''
      quickVehicleFound.value = true
    } else {
      quickVehicleFound.value = false
    }
  } catch {
    quickVehicleFound.value = false
  }
}

async function refreshPlanning() {
  refreshing.value = true
  try {
    await loadWeekRdvs(currentViewRange.value)
    if (selectedRdv.value?.id) {
      await reloadSelectedRdv(selectedRdv.value.id)
    }
  } finally {
    refreshing.value = false
  }
}

function addDaysToDate(dateStr: string, days: number): string {
  const d = new Date(dateStr + 'T00:00:00')
  d.setDate(d.getDate() + days)
  return d.toISOString().slice(0, 10)
}

function formatRangeLabel(startStr: string, endStr: string): string {
  const start = new Date(startStr + 'T00:00:00')
  const end = new Date(endStr + 'T00:00:00')
  const months = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.']
  const sameMonth = start.getMonth() === end.getMonth()
  if (sameMonth) {
    return `${start.getDate()} – ${end.getDate()} ${months[end.getMonth()]} ${end.getFullYear()}`
  }
  return `${start.getDate()} ${months[start.getMonth()]} – ${end.getDate()} ${months[end.getMonth()]} ${end.getFullYear()}`
}

function goPrevWeek() {
  const start = addDaysToDate(currentViewRange.value.start, -7)
  const end = addDaysToDate(currentViewRange.value.end, -7)
  onWeekChanged({ start, end, label: formatRangeLabel(start, end) })
}

function goNextWeek() {
  const start = addDaysToDate(currentViewRange.value.start, 7)
  const end = addDaysToDate(currentViewRange.value.end, 7)
  onWeekChanged({ start, end, label: formatRangeLabel(start, end) })
}

async function onWeekChanged(range: { start: string; end: string }) {
  currentViewRange.value = range
  if (!ponts.value.length && !mecaniciens.value.length) {
    // Initial load will handle RDVs inside loadPlanningData
  } else {
    // Week changed — mise à jour silencieuse des RDVs : NE PAS toucher `loading`
    refreshing.value = true
    try {
      await loadWeekRdvs(range)
    } finally {
      refreshing.value = false
    }
  }
}

async function loadAvailableTransitions(id: number) {
  try {
    const data = await api.get(`/rendez-vous/${id}/transitions`)
    const transitions = Array.isArray(data?.transitions) ? data.transitions : []
    availableTransitions.value = transitions
      .filter((name: string) => !HIDDEN_RECEPTION_TRANSITIONS.includes(name))
      .map((name: string) => ({
        name,
        label: transitionCatalog[name]?.label ?? name,
        color: transitionCatalog[name]?.color ?? 'neutral',
      }))
  } catch {
    availableTransitions.value = fallbackTransitionsForStatus(selectedRdv.value?.status)
      .filter((t: any) => !HIDDEN_RECEPTION_TRANSITIONS.includes(t.name))
  }
}

async function reloadSelectedRdv(id: number) {
  await rdvStore.fetchRdv(id)
  const fresh = normalizeRdv(rdvStore.currentRdv)
  if (fresh) {
    selectedRdv.value = fresh
    hydrateEditForms(fresh)
    await loadAvailableTransitions(id)
    selectedRdvHistory.value = []
    selectedRdvHistoryLoading.value = false
    if (editTab.value === '3') {
      await loadSelectedRdvHistory(id)
    }
  }
}

async function loadSelectedRdvHistory(id: number) {
  selectedRdvHistoryLoading.value = true
  try {
    const data = await api.get(`/rendez-vous/${id}/history`)
    selectedRdvHistory.value = Array.isArray(data?.items) ? data.items : []
  } catch {
    selectedRdvHistory.value = []
  } finally {
    selectedRdvHistoryLoading.value = false
  }
}

async function onSelectRdv(rdv: any) {
  editTab.value = '0'
  openRdvModal(normalizeRdv(rdv))
  modalLoading.value = true
  hydrateEditForms(selectedRdv.value)
  selectedRdvHistory.value = []
  selectedRdvHistoryLoading.value = false
  try {
    await reloadSelectedRdv(Number(rdv.id))
  } finally {
    modalLoading.value = false
  }
}

async function onMoveRdv(payload: { id: number; date: string; time: string }) {
  if (!canEditRdv.value) return
  try {
    await rdvStore.updateRdv(payload.id, {
      date_rdv: payload.date,
      dateRdv: payload.date,
      heure_rdv: payload.time,
      heure_debut: payload.time,
      heureRdv: `${payload.time}:00`,
    })
    toast.add({ title: 'RDV déplacé', color: 'success' })
    await refreshPlanning()
  } catch (e: unknown) {
    toast.add({ title: 'Déplacement impossible', description: (e instanceof Error ? e.message : 'Erreur inconnue') || 'Erreur inconnue', color: 'error' })
  }
}

function onCreateAt(payload: { date: string; time: string }) {
  if (!canCreateRdv.value) return
  openQuickCreate(payload)
}

async function submitQuickCreate() {
  if (!quickSelectedClient.value && !quickForm.client_nom.trim()) {
    toast.add({ title: 'Nom client requis', color: 'warning' })
    return
  }

  if (!toNullableNumber(quickForm.pont_id)) {
    toast.add({ title: 'Pont requis', description: 'Choisissez un pont pour affecter automatiquement le mécanicien.', color: 'warning' })
    return
  }

  quickSubmitting.value = true
  try {
    const typeIntervention = quickSelectedPrestations.value.map((item: any) => item.nom).join(', ') || quickForm.type_intervention || 'entretien'
    const resolvedPontId = toNullableNumber(quickForm.pont_id)
    const resolvedMecanicienId = resolvePontMecanicienId(resolvedPontId)

    const payload = {
      client_id: toNullableNumber(quickForm.client_id),
      date_rdv: quickForm.date_rdv,
      heure_debut: quickForm.heure_debut,
      client_prenom: quickForm.client_prenom.trim(),
      client_nom: quickForm.client_nom.trim(),
      client_telephone: quickForm.client_telephone.trim(),
      client_email: quickForm.client_email.trim(),
      vehicule_marque: quickForm.vehicule_marque.trim(),
      vehicule_modele: quickForm.vehicule_modele.trim(),
      vehicule_plaque: formatRegistrationOrVin(quickForm.vehicule_plaque),
      vehicule_cylindree: String(quickForm.vehicule_cylindree || '').trim(),
      vehicule_type: String(quickForm.vehicule_type || '').trim(),
      type_intervention: typeIntervention,
      description_probleme: quickForm.commentaire,
      commentaire: quickForm.commentaire,
      duree_estimee: quickEstimateDuration.value,
      temps_estime: quickEstimateDuration.value,
      prix_estime: quickEstimateTotal.value || null,
      mecanicien_id: resolvedMecanicienId,
      pont_id: resolvedPontId,
    }

    const created = await rdvStore.createRdv(payload)
    showQuickCreateModal.value = false
    toast.add({ title: 'Créneau réservé', color: 'success' })
    await refreshPlanning()
    if (created?.id) await onSelectRdv(created)
  } catch (e: unknown) {
    toast.add({ title: 'Création impossible', description: (e instanceof Error ? e.message : 'Erreur inconnue') || 'Erreur inconnue', color: 'error' })
  } finally {
    quickSubmitting.value = false
  }
}

async function saveRdvChanges() {
  if (!selectedRdv.value?.id || !canEditRdv.value || selectedIsHistorical.value) return

  editSaving.value = true
  try {
    const resolvedPontId = toNullableNumber(editForm.pont_id)
    const resolvedMecanicienId = resolvePontMecanicienId(resolvedPontId) ?? toNullableNumber(editForm.mecanicien_id)

    const payload: any = {
      date_rdv: editForm.date_rdv,
      dateRdv: editForm.date_rdv,
      heure_rdv: editForm.heure_debut,
      heure_debut: editForm.heure_debut,
      heureRdv: `${editForm.heure_debut}:00`,
      commentaire: editForm.commentaire,
      pont_id: resolvedPontId,
      mecanicien_id: resolvedMecanicienId,
      pont: resolvedPontId ? `/api/ponts/${resolvedPontId}` : null,
      mecanicien: resolvedMecanicienId ? `/api/mecaniciens/${resolvedMecanicienId}` : null,
    }

    if (!prestationLocked.value) {
      payload.type_intervention = editForm.type_intervention
      payload.typeIntervention = editForm.type_intervention
      payload.temps_estime = toNumber(editForm.temps_estime, 60)
      payload.tempsEstime = toNumber(editForm.temps_estime, 60)
    }

    await rdvStore.updateRdv(selectedRdv.value.id, payload)
    toast.add({ title: 'RDV mis à jour', color: 'success' })
    await refreshPlanning()
  } catch (e: unknown) {
    toast.add({ title: 'Modification impossible', description: (e instanceof Error ? e.message : 'Erreur inconnue') || 'Erreur inconnue', color: 'error' })
  } finally {
    editSaving.value = false
  }
}

async function applyTransition(name: string, options: any = {}) {
  if (!selectedRdv.value?.id) return
  const status = selectedRdv.value?.status ?? selectedRdv.value?.statut
  if (name === "annuler" && !options.motif) {
    confirmAnnulation(name)
    return
  }

  transitioning.value = name
  try {
    const resolvedPontId = toNullableNumber(editForm.pont_id)
    const resolvedMecanicienId = resolvePontMecanicienId(resolvedPontId) ?? toNullableNumber(editForm.mecanicien_id)

    if (name === 'reserver' && hasSchedulingEdits.value) {
      await rdvStore.updateRdv(selectedRdv.value.id, {
        date_rdv: editForm.date_rdv,
        dateRdv: editForm.date_rdv,
        heure_rdv: editForm.heure_debut,
        heure_debut: editForm.heure_debut,
        heureRdv: `${editForm.heure_debut}:00`,
        pont_id: resolvedPontId,
        mecanicien_id: resolvedMecanicienId,
        pont: resolvedPontId ? `/api/ponts/${resolvedPontId}` : null,
        mecanicien: resolvedMecanicienId ? `/api/mecaniciens/${resolvedMecanicienId}` : null,
      })
    }

    const payload: any = {
      pont_id: resolvedPontId,
      mecanicien_id: resolvedMecanicienId,
      ...options
    }

    if (name === 'reception') {
      if (receptionForm.kilometrage) payload.kilometrage = toNumber(receptionForm.kilometrage)
      const receptionState = parseReceptionState(selectedRdv.value?.etat_vehicule_reception ?? selectedRdv.value?.etat_vehicule ?? selectedRdv.value?.etatVehicule)
      if (receptionForm.etat_vehicule.trim()) receptionState.observations = receptionForm.etat_vehicule.trim()
      if (receptionForm.notes_reception.trim()) receptionState.reception_notes = receptionForm.notes_reception.trim()
      if (Object.keys(receptionState).length) payload.etat_vehicule = receptionState
    }

    await api.post(`/rendez-vous/${selectedRdv.value.id}/transition/${name}`, payload)
    const title = name === 'annuler' && status === 'en_attente'
      ? 'Demande refusée'
      : name === 'reserver' && hasSchedulingEdits.value
        ? 'Demande déplacée et créneau réservé'
        : 'Transition effectuée'
    toast.add({ title, color: 'success' })
    await refreshPlanning()
  } catch (e: unknown) {
    const err = e as any
    const data = err?.data
    // Erreur photos manquantes — message contextuel
    if (data?.missing_photos) {
      const mp = data.missing_photos
      const typeLabels: Record<string, string> = {
        reception: 'réception (via Companion)',
        apres_travaux: 'après travaux',
        restitution: 'restitution',
      }
      const typeLabel = typeLabels[mp.type] ?? mp.type
      toast.add({
        title: `Photos ${typeLabel} manquantes`,
        description: `${mp.missing} photo(s) requise(s) — demandez au client de les prendre via le Companion avant de passer à cette étape.`,
        color: 'error',
        duration: 8000,
      })
    } else {
      toast.add({ title: 'Transition impossible', description: (e instanceof Error ? e.message : 'Erreur inconnue') || 'Erreur inconnue', color: 'error' })
    }
  } finally {
    transitioning.value = ''
  }
}

const currentMecanicienRdvs = computed(() => {
  const mecaId = editForm.mecanicien_id ?? selectedRdv.value?.mecanicien_id
  if (!mecaId) return []
  const start = currentViewRange.value.start
  const end = currentViewRange.value.end
  return normalizedRdvs.value.filter((r: any) =>
    r.mecanicien_id === mecaId && r.date_rdv >= start && r.date_rdv <= end
  ).sort((a: any, b: any) => String(a.date_rdv + a.heure_debut).localeCompare(String(b.date_rdv + b.heure_debut)))
})

async function deleteSelectedRdv() {
  if (!selectedRdv.value?.id || !canDeleteSelected.value) return
  const confirmed = globalThis.confirm?.('Supprimer définitivement ce rendez-vous ?')
  if (confirmed === false) return

  deleting.value = true
  try {
    await api.del(`/rendez-vous/${selectedRdv.value.id}`)
    showRdvModal.value = false
    selectedRdv.value = null
    toast.add({ title: 'RDV supprimé', color: 'success' })
    await refreshPlanning()
  } catch (e: unknown) {
    toast.add({ title: 'Suppression impossible', description: (e instanceof Error ? e.message : 'Erreur inconnue') || 'Erreur inconnue', color: 'error' })
  } finally {
    deleting.value = false
  }
}

watch(() => quickForm.pont_id, () => {
  syncMecanicienFromPont(quickForm)
})

watch(() => editForm.pont_id, () => {
  syncMecanicienFromPont(editForm)
})

watch(() => `${quickForm.vehicule_type}|${quickForm.vehicule_cylindree}`, () => {
  quickSelectedPrestas.value = quickSelectedPrestas.value.filter((id: number) => filteredQuickPrestations.value.some((presta: any) => Number(presta.id) === Number(id)))
})

// --- Companion / Reception ---
const companionStatus = reactive({
  photos_count: 0,
  checkup_done: 0,
  has_signature: false,
})

const isReceptionEligible = computed(() => {
  const s = selectedRdv.value?.status ?? selectedRdv.value?.statut
  return ['confirme', 'reception'].includes(s)
})

const companionUrl = computed(() => {
  const token = selectedRdv.value?.token_suivi ?? selectedRdv.value?.tokenSuivi
  if (!token) return ''
  const origin = globalThis.location?.origin || ''
  return `${origin}/companion/reception/${token}`
})

const companionQrUrl = ref('')
watch(companionUrl, async (url: string) => {
  if (!url) { companionQrUrl.value = ''; return }
  const { generateQrDataUrl } = await import('~/composables/useQrCode')
  companionQrUrl.value = await generateQrDataUrl(url, 200)
}, { immediate: true })

function copyCompanionUrl() {
  if (companionUrl.value) {
    navigator.clipboard?.writeText(companionUrl.value)
    toast.add({ title: 'Lien PDA copié', color: 'success' })
  }
}

async function refreshCompanionStatus() {
  const token = selectedRdv.value?.token_suivi ?? selectedRdv.value?.tokenSuivi
  if (!token) return
  try {
    const data = await api.get(`/companion/${token}/status`)
    companionStatus.photos_count = data.photos_count || 0
    companionStatus.checkup_done = data.checkup_done || 0
    companionStatus.has_signature = !!data.has_signature
  } catch {}
}

let companionPollInterval: ReturnType<typeof setInterval>

watch(showRdvModal, (open: boolean) => {
  clearInterval(companionPollInterval)
  if (open && isReceptionEligible.value) {
    // Petit délai pour laisser selectedRdv être hydraté par reloadSelectedRdv
    setTimeout(() => {
      if (showRdvModal.value && isReceptionEligible.value) {
        refreshCompanionStatus()
        companionPollInterval = setInterval(refreshCompanionStatus, 4000)
      }
    }, 300)
  }
})

watch([editTab, () => selectedRdv.value?.id], async ([tab, id]) => {
  if (tab !== '3' || !id || selectedRdvHistoryLoading.value) {
    return
  }

  await loadSelectedRdvHistory(Number(id))
})

watch(isReceptionEligible, (eligible: boolean) => {
  clearInterval(companionPollInterval)
  if (eligible && showRdvModal.value) {
    refreshCompanionStatus()
    companionPollInterval = setInterval(refreshCompanionStatus, 4000)
  }
})

onMounted(async () => {
  try {
    await withTimeout(loadPlanningData(), 'planning_init', 15000)
    consumeWorkshopQuickCreateQuery()
    await openRdvFromQuery()
  } catch {
    toast.add({
      title: 'Chargement partiel du planning',
      description: 'Certaines données ont mis trop de temps à répondre. Le planning reste accessible.',
      color: 'warning',
    })
  } finally {
    loading.value = false
  }
})

watch(() => route.query.openRdv, async () => {
  if (!loading.value) {
    await openRdvFromQuery()
  }
})

onUnmounted(() => {
  clearInterval(companionPollInterval)
})
</script>

<style scoped>
.rdv-detail-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.rdv-detail-item--full {
  grid-column: 1 / -1;
}
.rdv-detail-label {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--text-muted, #6b7280);
  margin-bottom: 2px;
}
.rdv-detail-value {
  font-size: 14px;
  font-weight: 500;
  color: var(--text-primary, #111827);
}
.rdv-detail-section {
  padding-bottom: 12px;
  border-bottom: 1px solid var(--border-light);
  margin-bottom: 12px;
}
.rdv-detail-section-title {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--text-muted);
  margin-bottom: 8px;
}

.pit-kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: var(--space-4);
  margin-bottom: var(--space-6);
}

@media (min-width: 1024px) {
  .pit-kpi-grid {
    grid-template-columns: repeat(4, 1fr);
  }
}

.pit-kpi-skeleton {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: var(--space-4);
  margin-bottom: var(--space-6);
}

.pit-skeleton-card {
  height: 140px;
  border-radius: var(--radius-lg);
  background: var(--bg-surface);
}

.pit-skeleton-table {
  height: 400px;
  border-radius: var(--radius-lg);
  background: var(--bg-surface);
}

/* Planning nav */
.pit-planning-nav {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--space-4);
  flex-wrap: wrap;
  gap: var(--space-3);
}

.pit-planning-nav-arrows {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.pit-planning-nav-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  background: var(--bg-surface);
  border: 1px solid var(--border-default);
  border-radius: var(--radius);
  color: var(--text-secondary);
  cursor: pointer;
  transition: all var(--transition);
}

.pit-planning-nav-btn:hover {
  background: var(--bg-elevated);
  color: var(--text-primary);
}

.pit-planning-nav-label {
  font-family: var(--font-mono);
  font-size: 14px;
  font-weight: 600;
  color: var(--text-primary);
  padding: 0 var(--space-3);
}

.pit-planning-nav-views {
  display: flex;
  gap: var(--space-1);
}

.pit-planning-view-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  background: var(--bg-surface);
  border: 1px solid var(--border-default);
  border-radius: var(--radius);
  color: var(--text-tertiary);
  cursor: pointer;
  transition: all var(--transition);
}

.pit-planning-view-btn:hover {
  color: var(--text-secondary);
}

.pit-planning-view-btn.is-active {
  background: var(--accent-dim);
  border-color: var(--border-accent);
  color: var(--accent);
}

/* Filtres mécaniciens */
.pit-planning-filters {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: var(--space-2);
  margin-bottom: var(--space-4);
  padding: var(--space-3) var(--space-4);
  background: var(--bg-surface);
  border: 1px solid var(--border-default);
  border-radius: var(--radius-lg);
}

.pit-planning-filters-label {
  font-family: var(--font-mono);
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--text-tertiary);
  margin-right: var(--space-2);
}

.pit-planning-meca-chip {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  padding: 5px 12px;
  background: var(--bg-base);
  border: 1px solid var(--border-default);
  border-radius: var(--radius-full);
  font-size: 12px;
  font-weight: 500;
  color: var(--text-secondary);
  cursor: pointer;
  transition: all var(--transition);
}

.pit-planning-meca-chip:hover {
  border-color: var(--border-hover);
  color: var(--text-primary);
}

.pit-planning-meca-chip.is-active {
  background: var(--accent-dim);
  border-color: var(--border-accent);
  color: var(--accent);
}

.pit-planning-meca-chip--reset {
  background: transparent;
  border-color: var(--border-subtle);
  color: var(--text-tertiary);
}

.pit-planning-meca-dot {
  width: 8px;
  height: 8px;
  border-radius: var(--radius-full);
}

/* Header buttons */
.pit-header-action-btn {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: 7px 14px;
  background: var(--bg-base);
  border: 1px solid var(--border-default);
  border-radius: var(--radius);
  color: var(--text-secondary);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all var(--transition);
}

.pit-header-action-btn:hover:not(:disabled) {
  border-color: var(--border-hover);
  color: var(--text-primary);
}

.pit-header-action-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.pit-header-cta {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: 8px 16px;
  background: var(--accent);
  color: var(--text-inverse);
  font-size: 13px;
  font-weight: 600;
  border-radius: var(--radius);
  border: none;
  cursor: pointer;
  transition: all var(--transition);
}

.pit-header-cta:hover {
  background: var(--accent-bright);
  transform: translateY(-1px);
}

/* Modal sections */
.pit-modal-sections {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.pit-modal-section {
  background: var(--bg-base);
  border: 1px solid var(--border-subtle);
  border-radius: var(--radius-lg);
  padding: var(--space-4);
}

.pit-modal-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-3);
}

.pit-section-label {
  font-family: var(--font-mono);
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: var(--text-tertiary);
  margin-bottom: var(--space-3);
}
</style>
