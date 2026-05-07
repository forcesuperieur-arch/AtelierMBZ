<template>
  <div>
    <AppPageHeader title="Documents VO" subtitle="Vue legacy des pieces archivees. La gestion courante se fait maintenant dans chaque dossier moto." />

    <VONav />

    <div class="vo-page-grid">
      <PitCard class="bg-white">
        <template #header>
          <div class="vo-card-title">Alertes documentaires</div>
        </template>

        <div v-if="!voStore.alerts.length" class="vo-empty">Aucune alerte active.</div>
        <div v-else class="vo-alert-list">
          <div v-for="(alert, index) in voStore.alerts" :key="index" class="vo-alert-item">
            <div class="vo-alert-title">{{ alert.type === 'expired' ? 'Document expiré' : 'Document manquant' }}</div>
            <div class="vo-alert-text">{{ alert.message }}</div>
          </div>
        </div>
      </PitCard>

      <PitCard class="bg-white">
        <template #header>
          <div class="vo-card-title">Téléverser un document</div>
        </template>

        <div class="vo-form-grid">
          <UFormField label="Type de dossier">
            <PitSelect v-model="uploadForm.targetType">
              <option value="purchase">Rachat</option>
              <option value="depot">Dépôt</option>
            </PitSelect>
          </UFormField>

          <UFormField label="ID dossier">
            <PitInput v-model="uploadForm.targetId" placeholder="Ex: 12" />
          </UFormField>

          <UFormField label="Type de document">
            <PitSelect v-model="uploadForm.type">
              <option v-for="option in documentOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </PitSelect>
          </UFormField>

          <UFormField label="Date d’expiration">
            <PitInputDate v-model="uploadForm.dateExpiration" />
          </UFormField>

          <UFormField label="Fichier" class="vo-field-full">
            <PitInput type="file" @change="onFileChange" />
          </UFormField>
        </div>

        <div class="vo-inline-actions">
          <PitButton  class="topbar-new-btn" color="primary">{{ uploading ? 'Envoi...' : 'Téléverser' }}</PitButton>
        </div>
      </PitCard>
    </div>

    <PitCard class="bg-white">
      <div class="vo-filters">
        <PitInput v-model="search" placeholder="Type, fichier, date..." />
      </div>

      <PitTable :data="filteredDocuments" :columns="columns">
        <template #type-cell="{ row }">
          {{ documentLabel(row.original.type) }}
        </template>
        <template #uploadedAt-cell="{ row }">
          {{ formatDate(row.original.uploadedAt) }}
        </template>
        <template #dateExpiration-cell="{ row }">
          {{ formatDate(row.original.dateExpiration) }}
        </template>
        <template #actions-cell="{ row }">
          <a :href="buildVoDocumentUrl(row.original)" target="_blank" class="vo-link-btn">Ouvrir</a>
        </template>
      </PitTable>
    </PitCard>
  </div>
</template>

<script setup lang="ts">
import { useVoStore } from '~/stores/vo'

definePageMeta({
  layout: 'default', title: 'Documents VO' })

const voStore = useVoStore()
const toast = useToast()
const { formatDate, documentLabel, normalizeText, buildVoDocumentUrl, documentLabels } = useVoHelpers()

const search = ref('')
const uploading = ref(false)

const uploadForm = reactive({
  targetType: 'purchase',
  targetId: '',
  type: 'carte_grise',
  dateExpiration: '',
  file: null as File | null,
})

const documentOptions = Object.entries(documentLabels).map(([value, label]) => ({ value, label }))

const columns = [
  { accessorKey: 'type', header: 'Type' },
  { accessorKey: 'originalFilename', header: 'Fichier' },
  { accessorKey: 'mimeType', header: 'Mime' },
  { accessorKey: 'uploadedAt', header: 'Déposé le' },
  { accessorKey: 'dateExpiration', header: 'Expiration' },
  { accessorKey: 'actions', header: '' },
]

const filteredDocuments = computed(() => {
  return voStore.documents.filter((document: any) => {
    const haystack = normalizeText([
      document.type,
      document.originalFilename,
      document.mimeType,
      document.uploadedAt,
    ].filter(Boolean).join(' '))

    return !search.value || haystack.includes(normalizeText(search.value))
  })
})

function onFileChange(event: Event) {
  const target = event.target as HTMLInputElement
  uploadForm.file = target.files?.[0] ?? null
}

async function upload() {
  if (!uploadForm.file || !uploadForm.targetId.trim()) {
    toast.add({ title: 'Erreur', description: 'Dossier, type et fichier requis', color: 'error' })
    return
  }

  uploading.value = true
  try {
    const formData = new FormData()
    formData.append('file', uploadForm.file)
    formData.append('type', uploadForm.type)
    formData.append(uploadForm.targetType === 'purchase' ? 'purchaseId' : 'depotId', uploadForm.targetId.trim())

    if (uploadForm.dateExpiration) {
      formData.append('dateExpiration', uploadForm.dateExpiration)
    }

    await voStore.uploadDocument(formData)
    toast.add({ title: 'Document téléversé', color: 'success' })
    uploadForm.file = null
    uploadForm.targetId = ''
    uploadForm.dateExpiration = ''
    await Promise.all([voStore.fetchDocuments(), voStore.fetchAlerts()])
  } catch (error: unknown) {
    toast.add({ title: 'Erreur', description: error instanceof Error ? error.message : 'Erreur inconnue', color: 'error' })
  } finally {
    uploading.value = false
  }
}

onMounted(async () => {
  await Promise.all([voStore.fetchDocuments(), voStore.fetchAlerts()])
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

.vo-page-grid {
  display: grid;
  grid-template-columns: minmax(320px, 0.9fr) minmax(360px, 1.1fr);
  gap: 16px;
  margin-bottom: 16px;
}

.vo-card-title {
  color: var(--text-primary);
  font-weight: 700;
}

.vo-empty {
  color: #6b7280;
}

.vo-alert-list {
  display: grid;
  gap: 10px;
}

.vo-alert-item {
  padding: 12px;
  border-radius: 12px;
  background: rgba(239, 68, 68, 0.05);
  border: 1px solid rgba(239, 68, 68, 0.16);
}

.vo-alert-title {
  color: var(--danger-fg);
  font-size: 13px;
  font-weight: 700;
  margin-bottom: 4px;
}

.vo-alert-text {
  color: var(--text-secondary);
  font-size: 12px;
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

.vo-field span {
  font-size: 12px;
  color: var(--text-secondary);
  font-weight: 700;
}

.vo-field-full {
  grid-column: 1 / -1;
}

.vo-input,
.vo-select {
  width: 100%;
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--bg-surface);
  border: 1px solid var(--border-default);
  color: var(--text-primary);
}

.vo-inline-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: 14px;
}

.vo-filters {
  margin-bottom: 14px;
}

.vo-link-btn {
  color: var(--accent);
  text-decoration: none;
  font-size: 12px;
  font-weight: 700;
}

@media (max-width: 1024px) {
  .vo-page-grid,
  .vo-form-grid {
    grid-template-columns: 1fr;
  }
}
</style>