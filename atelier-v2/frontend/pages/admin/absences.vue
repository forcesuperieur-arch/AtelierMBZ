<template>
  <div>
    <div class="flex items-center gap-3 mb-6">
      <UButton icon="i-heroicons-arrow-left" variant="ghost" to="/admin" />
      <h1 class="text-2xl font-bold">Absences mécaniciens</h1>
      <UButton label="Nouvelle absence" icon="i-heroicons-plus" size="sm" @click="showNew = true" class="ml-auto" />
    </div>

    <UCard>
      <UTable :data="absences" :columns="columns" :loading="loading">
        <template #actions-cell="{ row }">
          <UButton size="xs" variant="ghost" icon="i-heroicons-trash" color="error" @click="deleteAbsence(row.original.id)" />
        </template>
      </UTable>
    </UCard>

    <UModal v-model:open="showNew">
      <template #default>
        <UCard>
          <template #header><h2 class="font-semibold">Nouvelle absence</h2></template>
          <form @submit.prevent="createAbsence" class="space-y-3">
            <UFormField label="Mécanicien">
              <USelect v-model="absForm.mecanicien_id" :options="mecaOptions" required />
            </UFormField>
            <div class="grid grid-cols-2 gap-3">
              <UFormField label="Date début"><UInput v-model="absForm.date_debut" type="date" required /></UFormField>
              <UFormField label="Date fin"><UInput v-model="absForm.date_fin" type="date" required /></UFormField>
            </div>
            <UFormField label="Motif"><UInput v-model="absForm.motif" /></UFormField>
            <div class="flex justify-end gap-2">
              <UButton label="Annuler" variant="outline" @click="showNew = false" />
              <UButton type="submit" label="Créer" :loading="saving" />
            </div>
          </form>
        </UCard>
      </template>
    </UModal>
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

const absForm = reactive({ mecanicien_id: null, date_debut: '', date_fin: '', motif: '' })

const columns = [
  { key: 'mecanicien_nom', label: 'Mécanicien' },
  { key: 'date_debut', label: 'Début' },
  { key: 'date_fin', label: 'Fin' },
  { key: 'motif', label: 'Motif' },
  { key: 'actions', label: '' },
]

async function createAbsence() {
  saving.value = true
  try {
    const a = await api.post('/absences', absForm)
    absences.value.unshift(a)
    showNew.value = false
    toast.add({ title: 'Absence créée', color: 'success' })
  } finally {
    saving.value = false
  }
}

async function deleteAbsence(id: number) {
  await api.del(`/absences/${id}`)
  absences.value = absences.value.filter(a => a.id !== id)
  toast.add({ title: 'Absence supprimée', color: 'success' })
}

onMounted(async () => {
  try {
    const [absData, mecasData] = await Promise.all([
      api.get('/absences'),
      api.get('/mecaniciens'),
    ])
    const rawAbs = absData?.['hydra:member'] ?? absData?.member ?? (Array.isArray(absData) ? absData : [])
    absences.value = rawAbs.map((a: any) => ({
      ...a,
      mecanicien_nom: a.mecanicien ? `${a.mecanicien.prenom} ${a.mecanicien.nom}` : (a.mecanicien_nom ?? ''),
    }))
    const rawMecas = mecasData?.['hydra:member'] ?? mecasData?.member ?? (Array.isArray(mecasData) ? mecasData : [])
    mecaOptions.value = rawMecas.map((m: any) => ({ value: m.id, label: `${m.prenom} ${m.nom}` }))
  } finally {
    loading.value = false
  }
})
</script>
