<template>
  <div>
    <div class="page-header">
      <div style="display:flex;align-items:center;gap:12px;">
        <NuxtLink to="/admin" style="color:#6B7280;text-decoration:none;font-size:18px;">◀</NuxtLink>
        <div class="page-title">Absences mécaniciens</div>
      </div>
      <button class="topbar-new-btn" @click="resetForm(); showNew = true">+ Nouvelle absence</button>
    </div>

    <UCard>
      <UTable :data="absences" :columns="columns" :loading="loading">
        <template #motif-cell="{ row }">
          <div style="display:flex;align-items:center;gap:8px;">
            <span v-if="row.original.type_motif" :style="{ display:'inline-block',padding:'2px 10px',borderRadius:'12px',fontSize:'12px',fontWeight:600,color: (typeMotifLabels[row.original.type_motif]||typeMotifLabels.autre).color, background: (typeMotifLabels[row.original.type_motif]||typeMotifLabels.autre).bg }">
              {{ (typeMotifLabels[row.original.type_motif]||typeMotifLabels.autre).label }}
            </span>
            <span v-if="row.original.motif" style="color:#9CA3AF;font-size:12px;">{{ row.original.motif }}</span>
          </div>
        </template>
        <template #actions-cell="{ row }">
          <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button style="color:#FFD200;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="editAbsence(row.original)">✏ Modifier</button>
            <button style="color:#FCA5A5;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="deleteAbsence(row.original.id)">✖ Supprimer</button>
          </div>
        </template>
      </UTable>
    </UCard>

    <AppModal v-model:open="showNew" size="lg">
      <template #default>
        <UCard>
          <template #header><span style="font-size:15px;font-weight:700;color:#E8E9ED;">{{ editId ? 'Modifier' : 'Nouvelle' }} absence</span></template>
          <form @submit.prevent="saveAbsence" style="display:flex;flex-direction:column;gap:12px;">
            <UFormField label="Mécanicien">
              <USelect v-model="absForm.mecanicien_id" :options="mecaOptions" required />
            </UFormField>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
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
            <div style="display:flex;justify-content:flex-end;gap:8px;">
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

const typeMotifLabels: Record<string, { label: string; color: string; bg: string }> = {
  conge: { label: '🏖️ Congé', color: '#60A5FA', bg: 'rgba(96,165,250,0.15)' },
  maladie: { label: '🏥 Maladie', color: '#F87171', bg: 'rgba(248,113,113,0.15)' },
  formation: { label: '📚 Formation', color: '#A78BFA', bg: 'rgba(167,139,250,0.15)' },
  autre: { label: '📋 Autre', color: '#9CA3AF', bg: 'rgba(156,163,175,0.15)' },
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
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e?.message || 'Échec de sauvegarde', color: 'error' })
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
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e?.message || 'Suppression impossible', color: 'error' })
  }
}

async function fetchAbsences() {
  const absData = await api.get('/absences')
  const rawAbs = absData?.['hydra:member'] ?? absData?.member ?? (Array.isArray(absData) ? absData : [])
  absences.value = rawAbs.map((a: any) => normalizeAbsence(a))
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
    const rawMecas = mecasData?.['hydra:member'] ?? mecasData?.member ?? (Array.isArray(mecasData) ? mecasData : [])
    mecaOptions.value = rawMecas.map((m: any) => ({ value: m.id, label: `${m.prenom} ${m.nom}` }))
  } finally {
    loading.value = false
  }
})
</script>
