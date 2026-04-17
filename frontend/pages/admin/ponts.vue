<template>
  <div>
    <div class="page-header">
      <div style="display:flex;align-items:center;gap:12px;">
        <NuxtLink to="/admin" style="color:#6B7280;text-decoration:none;font-size:18px;">◀</NuxtLink>
        <div class="page-title">Gestion des ponts</div>
      </div>
      <button class="topbar-new-btn" @click="resetForm(); showModal = true">+ Nouveau pont</button>
    </div>

    <UCard>
      <UTable :data="ponts" :columns="columns" :loading="loading">
        <template #est_actif-cell="{ row }">
          <StatusBadge :status="row.original.est_actif ? 'confirme' : 'annule'" />
        </template>
        <template #mecanicien-cell="{ row }">
          <span v-if="row.original.mecanicien_nom" style="color:#E8E9ED;">{{ row.original.mecanicien_nom }}</span>
          <span v-else style="color:#6B7280;">Non assigné</span>
        </template>
        <template #actions-cell="{ row }">
          <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button style="color:#FFD200;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="editPont(row.original)">✏ Modifier</button>
            <button style="color:#93C5FD;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="toggleActive(row.original)">
              {{ row.original.est_actif ? '🔒 Désactiver' : '🔓 Activer' }}
            </button>
            <button style="color:#FCA5A5;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="deletePont(row.original)">🗄 Archiver</button>
          </div>
        </template>
      </UTable>
    </UCard>

    <AppModal v-model:open="showModal" size="lg">
      <template #content>
        <UCard>
          <template #header>
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <span style="font-weight:600;">{{ editId ? 'Modifier' : 'Nouveau' }} pont</span>
              <button @click="showModal = false" style="background:none;border:none;color:#9CA3AF;font-size:18px;cursor:pointer;">✕</button>
            </div>
          </template>
          <form @submit.prevent="savePont" style="display:flex;flex-direction:column;gap:12px;">
            <div class="form-group">
              <label class="form-label">Nom *</label>
              <input v-model="form.nom" class="form-input" required placeholder="Ex: Pont A" />
            </div>
            <div class="form-group">
              <label class="form-label">Description</label>
              <input v-model="form.description" class="form-input" placeholder="Description optionnelle" />
            </div>
            <div class="form-group">
              <label class="form-label">Mécanicien assigné</label>
              <select v-model="form.mecanicien_id" class="form-input">
                <option :value="null">Aucun</option>
                <option v-for="m in mecaniciens" :key="m.id" :value="m.id">{{ m.prenom }} {{ m.nom }}</option>
              </select>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
              <input type="checkbox" v-model="form.est_actif" id="pont-actif" />
              <label for="pont-actif" style="font-size:13px;color:#9CA3AF;">Pont actif</label>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:12px;">
              <button type="button" class="btn btn-ghost" @click="showModal = false">Annuler</button>
              <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? 'Enregistrement…' : editId ? 'Modifier' : 'Créer' }}</button>
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
const showModal = ref(false)
const editId = ref<number | null>(null)
const ponts = ref<any[]>([])
const mecaniciens = ref<any[]>([])

const form = reactive({ nom: '', description: '', mecanicien_id: null as number | null, est_actif: true })

const columns = [
  { key: 'nom', label: 'Nom' },
  { key: 'description', label: 'Description' },
  { key: 'mecanicien', label: 'Mécanicien assigné' },
  { key: 'est_actif', label: 'Actif' },
  { key: 'actions', label: '' },
]

function resetForm() {
  editId.value = null
  Object.assign(form, { nom: '', description: '', mecanicien_id: null, est_actif: true })
}

function editPont(p: any) {
  editId.value = p.id
  Object.assign(form, { nom: p.nom, description: p.description ?? '', mecanicien_id: p.mecanicien?.id ?? p.mecanicien_id ?? null, est_actif: p.est_actif !== false })
  showModal.value = true
}

async function toggleActive(p: any) {
  try {
    await api.patch(`/ponts/${p.id}`, {
      nom: p.nom,
      description: p.description ?? '',
      est_actif: !p.est_actif,
      is_active: !p.est_actif ? 1 : 0,
      mecanicien: p.mecanicien?.id ? `/api/mecaniciens/${p.mecanicien.id}` : null,
    })
    toast.add({ title: p.est_actif ? 'Pont désactivé' : 'Pont activé', color: 'success' })
    await fetchPonts()
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e?.message, color: 'error' })
  }
}

async function deletePont(p: any) {
  if (!confirm(`Archiver le pont ${p.nom} ?`)) return

  try {
    await api.del(`/ponts/${p.id}`)
    toast.add({ title: 'Pont archivé', color: 'success' })
    await fetchPonts()
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e?.message || 'Archivage impossible', color: 'error' })
  }
}

async function savePont() {
  saving.value = true
  try {
    const payload: any = {
      nom: form.nom,
      description: form.description,
      est_actif: form.est_actif,
      is_active: form.est_actif ? 1 : 0,
      mecanicien_id: form.mecanicien_id,
    }
    if (form.mecanicien_id) payload.mecanicien = `/api/mecaniciens/${form.mecanicien_id}`
    else payload.mecanicien = null
    if (editId.value) {
      await api.patch(`/ponts/${editId.value}`, payload)
    } else {
      await api.post('/ponts', payload)
    }
    showModal.value = false
    toast.add({ title: 'Pont sauvegardé', color: 'success' })
    await fetchPonts()
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e?.message, color: 'error' })
  } finally {
    saving.value = false
  }
}

async function fetchPonts() {
  const data = await api.get('/ponts')
  const raw = data?.['hydra:member'] ?? data?.member ?? (Array.isArray(data) ? data : [])
  ponts.value = raw.map((p: any) => ({
    ...p,
    mecanicien_nom: p.mecanicien ? `${p.mecanicien.prenom ?? ''} ${p.mecanicien.nom ?? ''}`.trim() : '',
  }))
}

onMounted(async () => {
  try {
    const [_, mecaData] = await Promise.all([
      fetchPonts(),
      api.get('/mecaniciens'),
    ])
    mecaniciens.value = mecaData?.['hydra:member'] ?? mecaData?.member ?? (Array.isArray(mecaData) ? mecaData : [])
  } finally {
    loading.value = false
  }
})
</script>
