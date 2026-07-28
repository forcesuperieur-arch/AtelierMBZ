<template>
  <div>
    <div class="page-header">
      <div style="display:flex;align-items:center;gap:12px;">
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
            <span v-if="row.original.motif" style="color:var(--content-3);font-size:12px;">{{ row.original.motif }}</span>
          </div>
        </template>
        <template #actions-cell="{ row }">
          <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button style="color:var(--accent-content);font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="editAbsence(row.original)">✏ Modifier</button>
            <button style="color:var(--error-content);font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="deleteAbsence(row.original.id)">✖ Supprimer</button>
          </div>
        </template>
      </UTable>
    </UCard>

    <AppModal v-model:open="showNew" size="lg">
      <template #default>
        <UCard>
          <template #header><span style="font-size:15px;font-weight:700;color:var(--content-1);">{{ editId ? 'Modifier' : 'Nouvelle' }} absence</span></template>
          <form @submit.prevent="saveAbsence" style="display:flex;flex-direction:column;gap:12px;">
            <UFormField label="Mécanicien">
              <USelect v-model="absForm.mecanicien_id" :options="mecaOptions" required />
            </UFormField>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
              <UFormField label="Date début"><UInput v-model="absForm.date_debut" type="date" required /></UFormField>
              <UFormField label="Date fin"><UInput v-model="absForm.date_fin" type="date" required /></UFormField>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
              <UFormField label="Heure début (optionnel)"><UInput v-model="absForm.heure_debut" type="time" /></UFormField>
              <UFormField label="Heure fin (optionnel)"><UInput v-model="absForm.heure_fin" type="time" /></UFormField>
            </div>
            <p style="font-size:12px;color:var(--content-3);margin:-4px 0 0;">Heures vides = absence sur la journée entière. Renseigner les deux = seule cette plage bloque le mécanicien (ex. rendez-vous médical le matin).</p>
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

const absForm = reactive({ mecanicien_id: null as number | null, date_debut: '', date_fin: '', heure_debut: '', heure_fin: '', type_motif: 'conge', motif: '' })

const typeMotifLabels: Record<string, { label: string; color: string; bg: string }> = {
  conge: { label: '🏖️ Congé', color: 'var(--info-content)', bg: 'var(--info-soft)' },
  maladie: { label: '🏥 Maladie', color: 'var(--error-content)', bg: 'var(--error-soft)' },
  formation: { label: '📚 Formation', color: 'var(--info-content)', bg: 'var(--info-soft)' },
  autre: { label: '📋 Autre', color: 'var(--content-3)', bg: 'var(--surface-3)' },
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
  Object.assign(absForm, { mecanicien_id: null, date_debut: '', date_fin: '', heure_debut: '', heure_fin: '', type_motif: 'conge', motif: '' })
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
    heure_debut: toHM(a.heure_debut ?? a.heureDebut),
    heure_fin: toHM(a.heure_fin ?? a.heureFin),
  }
}

// Extrait HH:MM depuis n'importe quel format d'heure renvoyé par l'API.
function toHM(v: any): string {
  const m = String(v ?? '').match(/(\d{2}:\d{2})/)
  return m ? m[1] : ''
}

function buildAbsencePayload() {
  if (!absForm.mecanicien_id) throw new Error('Le mécanicien est requis')

  const motifStr = [absForm.type_motif, absForm.motif].filter(Boolean).join(' — ')

  // Absence partielle : les deux heures ensemble, ou aucune (= journée entière).
  const hd = absForm.heure_debut || null
  const hf = absForm.heure_fin || null
  if ((hd && !hf) || (!hd && hf)) throw new Error('Renseignez les deux heures (début et fin), ou laissez-les vides pour une absence sur la journée entière.')
  if (hd && hf && hf <= hd) throw new Error("L'heure de fin doit être après l'heure de début.")

  return {
    mecanicien: `/api/mecaniciens/${absForm.mecanicien_id}`,
    mecanicien_id: absForm.mecanicien_id,
    date_debut: absForm.date_debut,
    date_fin: absForm.date_fin,
    dateDebut: absForm.date_debut,
    dateFin: absForm.date_fin,
    heureDebut: hd,
    heureFin: hf,
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
    heure_debut: a.heure_debut ?? '',
    heure_fin: a.heure_fin ?? '',
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
  const absData = await api.getAll('/absences?order[id]=asc')
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
    const uniqueMecas = [...new Map(rawMecas.map((m: any) => [Number(m.id), m])).values()]
    mecaOptions.value = uniqueMecas.map((m: any) => ({ value: m.id, label: `${m.prenom} ${m.nom}` }))
  } finally {
    loading.value = false
  }
})
</script>
