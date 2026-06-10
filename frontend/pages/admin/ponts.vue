<template>
  <div>
    <div class="page-header">
      <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <NuxtLink to="/admin" style="color:#6B7280;text-decoration:none;font-size:18px;">◀</NuxtLink>
        <div>
          <div class="page-title">Ponts & mécaniciens</div>
          <div class="page-sub">Configuration des postes de travail et affectations</div>
        </div>
      </div>
      <button class="topbar-new-btn" @click="resetForm(); showModal = true">+ Nouveau pont</button>
    </div>

    <UCard>
      <template #header>
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
          <span style="font-size:15px;font-weight:700;color:#E8E9ED;">Postes de travail</span>
          <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <UInput
              v-model="searchQuery"
              placeholder="Rechercher un pont ou mécano..."
              icon="i-heroicons-magnifying-glass"
              style="min-width:220px;"
            />
            <select v-model="filterMeca" class="form-input" style="min-width:160px;background:#171B24;color:#E8E9ED;">
              <option value="all">Tous les ponts</option>
              <option value="assigned">Avec mécanicien</option>
              <option value="unassigned">Sans mécanicien</option>
            </select>
          </div>
        </div>
      </template>

      <UTable :data="filteredPontRows" :columns="columns" :loading="loading">
        <template #type_pont-cell="{ row }">
          <div style="display:grid;gap:2px;">
            <span style="color:#E8E9ED;font-weight:600;">{{ row.original.type_pont || 'moto' }}</span>
            <span style="color:#9CA3AF;font-size:11px;">{{ row.original.capacite_kg || 500 }} kg</span>
          </div>
        </template>
        <template #mecanicien-cell="{ row }">
          <span v-if="row.original.mecanicien_nom" style="color:#E8E9ED;">{{ row.original.mecanicien_nom }}</span>
          <span v-else style="color:#FCA5A5;">Non assigné</span>
        </template>
        <template #est_actif-cell="{ row }">
          <StatusBadge :status="row.original.est_actif ? 'confirme' : 'annule'" />
        </template>
        <template #actions-cell="{ row }">
          <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button class="btn btn-ghost" style="font-size:12px;color:#FFD200;" @click="editPont(row.original)">✏ Modifier</button>
            <button class="btn btn-ghost" style="font-size:12px;color:#93C5FD;" @click="toggleActive(row.original)">
              {{ row.original.est_actif ? '🔒 Désactiver' : '🔓 Activer' }}
            </button>
            <button class="btn btn-ghost" style="font-size:12px;color:#FCA5A5;" @click="deletePont(row.original)">🗄 Archiver</button>
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
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <div class="form-group">
                <label class="form-label">Type de pont</label>
                <select v-model="form.type_pont" class="form-input">
                  <option value="moto">Moto</option>
                  <option value="diagnostic">Diagnostic</option>
                  <option value="lavage">Lavage</option>
                  <option value="livraison">Livraison</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Capacité (kg)</label>
                <input v-model.number="form.capacite_kg" type="number" min="100" step="10" class="form-input" />
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Description</label>
              <input v-model="form.description" class="form-input" placeholder="Ex: pont principal atelier rapide" />
            </div>
            <div class="form-group">
              <label class="form-label">Mécanicien assigné</label>
              <select v-model="form.mecanicien_id" class="form-input">
                <option :value="null">Aucun</option>
                <option v-for="m in activeMecaniciens" :key="m.id" :value="m.id">{{ m.prenom }} {{ m.nom }}</option>
              </select>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
              <input id="pont-actif" v-model="form.est_actif" type="checkbox" />
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

const form = reactive({
  nom: '',
  description: '',
  mecanicien_id: null as number | null,
  est_actif: true,
  type_pont: 'moto',
  capacite_kg: 500,
})

const columns = [
  { key: 'nom', label: 'Pont' },
  { key: 'type_pont', label: 'Type' },
  { key: 'mecanicien', label: 'Mécanicien' },
  { key: 'est_actif', label: 'Actif' },
  { key: 'actions', label: '' },
]

function asId(value: any): number | null {
  const raw = typeof value === 'object' ? value?.id ?? value?.['@id']?.split('/').pop() : value
  const parsed = Number(raw)
  return Number.isFinite(parsed) && parsed > 0 ? parsed : null
}

function isActiveFlag(value: any): boolean {
  return value !== false && Number(value ?? 1) !== 0
}

const searchQuery = ref('')
const filterMeca = ref('all')

const activeMecaniciens = computed(() => mecaniciens.value.filter((m) => isActiveFlag(m.is_active ?? m.isActive)))

const pontRows = computed(() => {
  return ponts.value.map((p) => ({
    ...p,
  }))
})

const filteredPontRows = computed(() => {
  let rows = pontRows.value

  if (filterMeca.value === 'assigned') {
    rows = rows.filter((p) => !!p.mecanicien_nom)
  } else if (filterMeca.value === 'unassigned') {
    rows = rows.filter((p) => !p.mecanicien_nom)
  }

  const q = searchQuery.value.trim().toLowerCase()
  if (q) {
    rows = rows.filter((p) => {
      const text = `${p.nom || ''} ${p.mecanicien_nom || ''} ${p.type_pont || ''}`.toLowerCase()
      return text.includes(q)
    })
  }

  return rows
})

function resetForm() {
  editId.value = null
  Object.assign(form, {
    nom: '',
    description: '',
    mecanicien_id: null,
    est_actif: true,
    type_pont: 'moto',
    capacite_kg: 500,
  })
}

function editPont(p: any) {
  editId.value = p.id
  Object.assign(form, {
    nom: p.nom,
    description: p.description ?? '',
    mecanicien_id: asId(p.mecanicien?.id ?? p.mecanicien_id),
    est_actif: isActiveFlag(p.est_actif),
    type_pont: p.type_pont || 'moto',
    capacite_kg: Number(p.capacite_kg || 500),
  })
  showModal.value = true
}

async function toggleActive(p: any) {
  try {
    const mecanicienId = asId(p.mecanicien?.id ?? p.mecanicien_id)
    await api.patch(`/ponts/${p.id}`, {
      nom: p.nom,
      description: p.description ?? '',
      type_pont: p.type_pont || 'moto',
      capacite_kg: Number(p.capacite_kg || 500),
      est_actif: !p.est_actif,
      is_active: !p.est_actif ? 1 : 0,
      mecanicien_id: mecanicienId,
      mecanicien: mecanicienId ? `/api/mecaniciens/${mecanicienId}` : null,
    })
    toast.add({ title: p.est_actif ? 'Pont désactivé' : 'Pont activé', color: 'success' })
    await refreshData()
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e?.message, color: 'error' })
  }
}

async function deletePont(p: any) {
  if (!confirm(`Archiver le pont ${p.nom} ?`)) return

  try {
    await api.del(`/ponts/${p.id}`)
    toast.add({ title: 'Pont archivé', color: 'success' })
    await refreshData()
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
      type_pont: form.type_pont,
      capacite_kg: Number(form.capacite_kg || 500),
      est_actif: form.est_actif,
      is_active: form.est_actif ? 1 : 0,
      mecanicien: form.mecanicien_id ? `/api/mecaniciens/${form.mecanicien_id}` : null,
    }

    if (editId.value) await api.patch(`/ponts/${editId.value}`, payload)
    else await api.post('/ponts', payload)

    showModal.value = false
    toast.add({ title: 'Pont sauvegardé', color: 'success' })
    await refreshData()
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e?.message, color: 'error' })
  } finally {
    saving.value = false
  }
}

async function refreshData() {
  const [pontData, mecaData] = await Promise.all([
    api.get('/ponts?itemsPerPage=200').catch(() => []),
    api.get('/mecaniciens?itemsPerPage=200').catch(() => []),
  ])

  const rawPonts = pontData?.['hydra:member'] ?? pontData?.member ?? (Array.isArray(pontData) ? pontData : [])
  const rawMecas = mecaData?.['hydra:member'] ?? mecaData?.member ?? (Array.isArray(mecaData) ? mecaData : [])

  ponts.value = rawPonts.map((p: any) => ({
    ...p,
    mecanicien_id: asId(p.mecanicien?.id ?? p.mecanicien_id),
    mecanicien_nom: p.mecanicien ? `${p.mecanicien.prenom ?? ''} ${p.mecanicien.nom ?? ''}`.trim() : '',
    est_actif: isActiveFlag(p.est_actif ?? p.isActive ?? p.is_active),
  }))
  mecaniciens.value = [...new Map(rawMecas.map((m: any) => [Number(m.id), m])).values()]
}

onMounted(async () => {
  try {
    await refreshData()
  } finally {
    loading.value = false
  }
})
</script>
