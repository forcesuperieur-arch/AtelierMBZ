<template>
  <div>
    <AppPageHeader title="Absences mécaniciens" back-to="/admin">
      <template #actions>
        <button class="topbar-new-btn" @click="resetForm(); showNew = true">+ Nouvelle absence</button>
      </template>
    </AppPageHeader>

    <UCard>
      <UTable :data="absences" :columns="columns" :loading="loading">
        <template #motif-cell="{ row }">
          <div class="motif-cell">
            <AppStatusBadge v-if="row.original.type_motif" :variant="typeMotifVariant(row.original.type_motif)" size="sm">
              {{ (typeMotifLabels[row.original.type_motif]||typeMotifLabels.autre).label }}
            </AppStatusBadge>
            <span v-if="row.original.motif" class="motif-detail">{{ row.original.motif }}</span>
          </div>
        </template>
        <template #actions-cell="{ row }">
          <AppInlineActions>
            <button class="btn-action-primary" @click="editAbsence(row.original)">✏ Modifier</button>
            <button class="btn-action-danger" @click="deleteAbsence(row.original.id)">✖ Supprimer</button>
          </AppInlineActions>
        </template>
      </UTable>
    </UCard>

    <AppModal v-model:open="showNew" size="lg">
      <template #default>
        <UCard>
          <template #header><span class="modal-title">{{ editId ? 'Modifier' : 'Nouvelle' }} absence</span></template>
          <form @submit.prevent="saveAbsence" class="form-stack">
            <UFormField label="Mécanicien">
              <USelect v-model="absForm.mecanicien_id" :options="mecaOptions" required />
            </UFormField>
            <div class="form-grid-2">
              <UFormField label="Date début"><UInput v-model="absForm.date_debut" type="date" required /></UFormField>
              <UFormField label="Date fin"><UInput v-model="absForm.date_fin" type="date" required /></UFormField>
            </div>
            <UFormField label="Type de motif">
              <select v-model="absForm.type_motif" class="form-input">
                <option value="conge">🏖️ Congé</option>
                <option value="maladie">🏥 Maladie</option>
                <option value="formation">📚 Formation</option>
                <option value="autre">📋 Autre</option>
              </select>
            </UFormField>
            <UFormField label="Détail motif"><UInput v-model="absForm.motif" placeholder="Précisions optionnelles…" /></UFormField>
            <div class="form-footer">
              <UButton label="Annuler" variant="outline" @click="showNew = false" />
              <UButton type="submit" :label="editId ? 'Modifier' : 'Créer'" :loading="saving" />
            </div>
          </form>
        </UCard>
      </template>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const toast = useToast()
const loading = ref(true)
const saving = ref(false)
const absences = ref<any[]>([])
const mecaOptions = ref<any[]>([])
const showNew = ref(false)
const editId = ref<number | null>(null)

const absForm = reactive({ mecanicien_id: null as number | null, date_debut: '', date_fin: '', type_motif: 'conge', motif: '' })

const typeMotifLabels: Record<string, { label: string; variant: 'info' | 'error' | 'warning' | 'neutral' }> = {
  conge: { label: '🏖️ Congé', variant: 'info' },
  maladie: { label: '🏥 Maladie', variant: 'error' },
  formation: { label: '📚 Formation', variant: 'warning' },
  autre: { label: '📋 Autre', variant: 'neutral' },
}

function typeMotifVariant(type: string): 'info' | 'error' | 'warning' | 'neutral' {
  return typeMotifLabels[type]?.variant || 'neutral'
}

const columns = [
  { key: 'mecanicien_nom', label: 'Mécanicien' },
  { key: 'date_debut', label: 'Début' },
  { key: 'date_fin', label: 'Fin' },
  { key: 'motif', label: 'Motif' },
  { key: 'actions', label: '' },
]

function resetForm() {
  editId.value = null
  Object.assign(absForm, { mecanicien_id: null, date_debut: '', date_fin: '', type_motif: 'conge', motif: '' })
}

function normalizeAbsence(a: any) {
  const motifRaw = a.motif ?? ''
  const knownTypes = ['conge', 'maladie', 'formation', 'autre']
  const parts = motifRaw.split(' — ')
  const type_motif = knownTypes.includes(parts[0]) ? parts[0] : 'autre'
  const detail = knownTypes.includes(parts[0]) ? parts.slice(1).join(' — ') : motifRaw

  return {
    ...a,
    type_motif,
    motif: detail,
    mecanicien_id: a.mecanicien?.id ?? a.mecanicien_id ?? null,
    mecanicien_nom: a.mecanicien ? `${a.mecanicien.prenom} ${a.mecanicien.nom}` : (a.mecanicien_nom ?? ''),
    date_debut: (a.date_debut ?? a.dateDebut ?? '').slice(0, 10),
    date_fin: (a.date_fin ?? a.dateFin ?? '').slice(0, 10),
  }
}

function buildAbsencePayload() {
  if (!absForm.mecanicien_id) throw new Error('Le mécanicien est requis')

  const motifStr = [absForm.type_motif, absForm.motif].filter(Boolean).join(' — ')

  return {
    mecanicien: `/api/mecaniciens/${absForm.mecanicien_id}`,
    mecanicien_id: absForm.mecanicien_id,
    date_debut: absForm.date_debut,
    date_fin: absForm.date_fin,
    dateDebut: absForm.date_debut,
    dateFin: absForm.date_fin,
    motif: motifStr,
  }
}

function editAbsence(absence: any) {
  const a = normalizeAbsence(absence)
  editId.value = a.id
  Object.assign(absForm, {
    mecanicien_id: a.mecanicien_id,
    date_debut: a.date_debut,
    date_fin: a.date_fin,
    type_motif: a.type_motif,
    motif: a.motif,
  })
  showNew.value = true
}

async function saveAbsence() {
  saving.value = true
  try {
    const payload = buildAbsencePayload()

    if (editId.value) {
      await api.patch(`/absences/${editId.value}`, payload)
      toast.add({ title: 'Absence modifiée', color: 'success' })
    } else {
      await api.post('/absences', payload)
      toast.add({ title: 'Absence créée', color: 'success' })
    }

    showNew.value = false
    resetForm()
    await fetchAbsences()
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: (e instanceof Error ? e.message : 'Erreur inconnue') || 'Échec de sauvegarde', color: 'error' })
  } finally {
    saving.value = false
  }
}

async function deleteAbsence(id: number) {
  if (!confirm('Supprimer cette absence ?')) return

  try {
    await api.del(`/absences/${id}`)
    absences.value = absences.value.filter(a => a.id !== id)
    toast.add({ title: 'Absence supprimée', color: 'success' })
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: (e instanceof Error ? e.message : 'Erreur inconnue') || 'Suppression impossible', color: 'error' })
  }
}

async function fetchAbsences() {
  const absData = await api.get('/absences')
  absences.value = unwrapHydraOrEmpty(absData).map((a: any) => normalizeAbsence(a))
}

watch(showNew, (open) => {
  if (!open) resetForm()
})

onMounted(async () => {
  try {
    const [_, mecasData] = await Promise.all([
      fetchAbsences(),
      api.get('/mecaniciens'),
    ])
    mecaOptions.value = unwrapHydraOrEmpty(mecasData).map((m: any) => ({ value: m.id, label: `${m.prenom} ${m.nom}` }))
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.motif-cell { display:flex; align-items:center; gap:8px; }
.motif-detail { color:#9CA3AF; font-size:12px; }
.btn-action-primary { color:#FFD200; font-size:12px; font-weight:600; background:none; border:none; cursor:pointer; }
.btn-action-danger { color:#FCA5A5; font-size:12px; font-weight:600; background:none; border:none; cursor:pointer; }
.modal-title { font-size:15px; font-weight:700; color:#E8E9ED; }
.form-stack { display:flex; flex-direction:column; gap:12px; }
.form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.form-footer { display:flex; justify-content:flex-end; gap:8px; }
</style>
