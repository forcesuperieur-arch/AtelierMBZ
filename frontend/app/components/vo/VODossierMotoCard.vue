<template>
  <UCard>
    <template #header>
      <div class="vo-card-head">
        <div>
          <div class="vo-card-title">Dossier moto</div>
          <div class="vo-card-subtitle">Scan terrain, OCR local, validation manuelle et archivage au meme endroit.</div>
        </div>
        <span class="vo-doc-count">{{ sortedDocuments.length }} piece(s)</span>
      </div>
    </template>

    <div class="vo-dossier-stack">
      <div class="vo-flow-banner">
        <span class="vo-flow-pill">1. Capture</span>
        <span class="vo-flow-pill">2. OCR</span>
        <span class="vo-flow-pill">3. Validation</span>
        <span class="vo-flow-pill">4. Archive dossier</span>
      </div>

      <VODossierVerdict :sale-verdict="saleVerdict" />

      <VODossierIdentity :identity-checklist="identityChecklist" />

      <VODossierScanPanel
        :dossier-id="dossierId"
        :mode="mode"
        :vehicule="vehicule"
        :reload-detail="reloadDetail"
      />

      <VODossierUploadPanel
        :dossier-id="dossierId"
        :mode="mode"
        :document-options="documentOptions"
        @uploaded="refreshDetail"
      />

      <VODossierDocumentList :documents="sortedDocuments" />
    </div>
  </UCard>
</template>

<script setup lang="ts">
import type { VODossierDocument, VODossierChecklistItem, VODossierSaleVerdict } from './VODossier.types'

const props = withDefaults(defineProps<{
  mode: 'purchase' | 'depot'
  dossierId: number
  vehicule?: Record<string, any> | null
  documents?: VODossierDocument[]
  missingDocuments?: string[]
  legalChecklist?: VODossierChecklistItem[]
  saleVerdict?: VODossierSaleVerdict | null
  reloadDetail?: () => Promise<void> | void
}>(), {
  vehicule: null,
  documents: () => [],
  missingDocuments: () => [],
  legalChecklist: () => [],
  saleVerdict: null,
  reloadDetail: undefined,
})

const { documentLabels } = useVoHelpers()

const documentTypesByMode = {
  purchase: [
    'cerfa_cession_achat',
    'cerfa_cession_vente',
    'carte_grise',
    'non_gage',
    'controle_technique',
    'pv_rachat',
    'da_siv',
    'recepisse_da',
    'mandat_immatriculation',
    'facture_vo',
    'notice_garantie',
    'autre',
  ],
  depot: [
    'contrat_depot_vente',
    'carte_grise',
    'controle_technique',
    'mandat_immatriculation',
    'notice_garantie',
    'autre',
  ],
} as const

const documentOptions = computed(() =>
  documentTypesByMode[props.mode].map((value) => ({ value, label: documentLabels[value] || value })),
)

const sortedDocuments = computed(() =>
  [...props.documents].sort((left, right) => String(right.uploadedAt || '').localeCompare(String(left.uploadedAt || ''))),
)

const identityChecklist = computed(() =>
  props.legalChecklist.filter(item => ['seller_identity', 'deposant_identity', 'identity_storage'].includes(item.key)),
)

const saleVerdict = computed<VODossierSaleVerdict>(() =>
  props.saleVerdict || {
    status: 'non_vendable',
    label: 'Verdict indisponible',
    summary: 'Le verdict de vente n a pas encore ete calcule pour ce dossier.',
    reasons: [],
  },
)

async function refreshDetail() {
  await props.reloadDetail?.()
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
  color: var(--text-primary);
  font-weight: 700;
}

.vo-card-subtitle,
.vo-doc-count {
  color: var(--text-tertiary);
  font-size: 12px;
}

.vo-dossier-stack {
  display: grid;
  gap: 14px;
}

.vo-flow-banner {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.vo-flow-pill {
  padding: 7px 12px;
  border-radius: 999px;
  background: var(--info-bg);
  border: 1px solid var(--info-border);
  color: var(--info);
  font-size: 11px;
  font-weight: 700;
}
</style>
