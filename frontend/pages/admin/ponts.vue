<template>
  <div>
    <AppPageHeader title="Ponts & mécaniciens" subtitle="Pilotage atelier, affectations et cohérence login ↔ mécano" back-to="/admin">
      <template #actions>
        <button class="btn btn-ghost" @click="goToUsers">Logins atelier</button>
        <button class="btn btn-ghost" @click="goToPlanning">Voir planning</button>
        <button class="topbar-new-btn" @click="resetForm(); showModal = true">+ Nouveau pont</button>
      </template>
    </AppPageHeader>

    <div class="info-banner">
      <div class="info-banner-title">Cette page sert à structurer l'atelier.</div>
      <div class="info-banner-text">Les mécaniciens sont créés depuis les logins dans Utilisateurs. Le planning gère ensuite les RDV au quotidien.</div>
    </div>

    <div class="kpi-grid">
      <AppKpiCard label="Ponts actifs" :value="activePontsCount" variant="green" />
      <AppKpiCard label="Ponts sans mécano" :value="unassignedPonts.length" :variant="unassignedPonts.length ? 'red' : 'green'" />
      <AppKpiCard label="Mécanos actifs" :value="activeMecaniciensCount" variant="blue" />
      <AppKpiCard label="Mécanos sans pont" :value="mecaniciensWithoutPont.length" :variant="mecaniciensWithoutPont.length ? 'amber' : 'green'" />
    </div>

    <div v-if="alerts.length" class="alert-banner">
      <div class="alert-banner-title">Points à corriger</div>
      <ul class="alert-banner-list">
        <li v-for="alert in alerts" :key="alert">{{ alert }}</li>
      </ul>
    </div>

    <UCard class="mb-4">
      <template #header>
        <div class="card-header">
          <div>
            <div class="card-title">Équipe atelier</div>
            <div class="card-subtitle">Vue utile : login lié, pont affecté et charge du jour</div>
          </div>
        </div>
      </template>

      <div v-if="mecanicienCards.length" class="meca-grid">
        <div v-for="meca in mecanicienCards" :key="meca.id" class="meca-card">
          <div class="meca-card-header">
            <div>
              <div class="meca-name">{{ meca.prenom }} {{ meca.nom }}</div>
              <div class="meca-login">{{ meca.loginLabel }}</div>
            </div>
            <AppStatusBadge :status="meca.isActive ? 'confirme' : 'annule'" />
          </div>
          <div class="meca-info">Pont : <strong>{{ meca.pontNom }}</strong></div>
          <div class="meca-info">RDV du jour : <strong>{{ meca.rdvToday }}</strong></div>
          <div class="meca-actions">
            <button class="btn btn-ghost btn-sm" @click="goToPlanning">Planning</button>
            <button class="btn btn-ghost btn-sm" @click="goToUsers">Login</button>
          </div>
        </div>
      </div>
      <div v-else class="text-muted">Aucun mécanicien actif sur cet atelier.</div>
    </UCard>

    <UCard>
      <template #header>
        <div>
          <div class="card-title">Postes de travail</div>
          <div class="card-subtitle">Affectation structurelle des ponts, pas l'ordonnancement des RDV</div>
        </div>
      </template>

      <UTable :data="pontRows" :columns="columns" :loading="loading">
        <template #type_pont-cell="{ row }">
          <div class="type-cell">
            <span class="type-label">{{ row.original.type_pont || 'moto' }}</span>
            <span class="type-meta">{{ row.original.capacite_kg || 500 }} kg</span>
          </div>
        </template>
        <template #mecanicien-cell="{ row }">
          <span v-if="row.original.mecanicien_nom" class="text-light">{{ row.original.mecanicien_nom }}</span>
          <span v-else class="text-red">Non assigné</span>
        </template>
        <template #charge-cell="{ row }">
          <div class="charge-cell">
            <span class="charge-label">{{ row.original.rdv_today }} RDV</span>
            <span class="charge-meta">{{ row.original.load_minutes }} min planifiées</span>
          </div>
        </template>
        <template #est_actif-cell="{ row }">
          <AppStatusBadge :status="row.original.est_actif ? 'confirme' : 'annule'" />
        </template>
        <template #actions-cell="{ row }">
          <AppInlineActions>
            <button class="btn-action-primary" @click="editPont(row.original)">✏ Modifier</button>
            <button class="btn-action-info" @click="toggleActive(row.original)">
              {{ row.original.est_actif ? '🔒 Désactiver' : '🔓 Activer' }}
            </button>
            <button class="btn-action-danger" @click="deletePont(row.original)">🗄 Archiver</button>
          </AppInlineActions>
        </template>
      </UTable>
    </UCard>

    <AppModal v-model:open="showModal" size="lg">
      <template #content>
        <UCard>
          <template #header>
            <div class="modal-header">
              <span class="modal-title">{{ editId ? 'Modifier' : 'Nouveau' }} pont</span>
              <button class="modal-close" @click="showModal = false">✕</button>
            </div>
          </template>
          <form @submit.prevent="savePont" class="form-stack">
            <div class="form-group">
              <label class="form-label">Nom *</label>
              <input v-model="form.nom" class="form-input" required placeholder="Ex: Pont A" />
            </div>
            <div class="form-grid-2">
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
            <div class="checkbox-row">
              <input id="pont-actif" v-model="form.est_actif" type="checkbox" />
              <label for="pont-actif" class="checkbox-label">Pont actif</label>
            </div>
            <div class="form-footer">
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
const users = ref<any[]>([])
const rdvs = ref<any[]>([])

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
  { key: 'charge', label: 'Charge du jour' },
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

const today = computed(() => new Date().toISOString().slice(0, 10))
const activePontsCount = computed(() => ponts.value.filter((p) => isActiveFlag(p.est_actif)).length)
const activeMecaniciens = computed(() => mecaniciens.value.filter((m) => isActiveFlag(m.is_active ?? m.isActive)))
const activeMecaniciensCount = computed(() => activeMecaniciens.value.length)
const linkedMecaniciensCount = computed(() => activeMecaniciens.value.filter((m) => asId(m.userId ?? m.user_id)).length)

const unassignedPonts = computed(() => ponts.value.filter((p) => isActiveFlag(p.est_actif) && !asId(p.mecanicien?.id ?? p.mecanicien_id)))
const mecaniciensWithoutPont = computed(() => activeMecaniciens.value.filter((m) => !ponts.value.some((p) => isActiveFlag(p.est_actif) && asId(p.mecanicien?.id ?? p.mecanicien_id) === asId(m.id))))

const alerts = computed(() => {
  const items: string[] = []
  if (unassignedPonts.value.length) items.push(`${unassignedPonts.value.length} pont(s) actif(s) sans mécanicien assigné.`)
  if (mecaniciensWithoutPont.value.length) items.push(`${mecaniciensWithoutPont.value.length} mécanicien(s) actif(s) sans pont de rattachement.`)
  if (linkedMecaniciensCount.value < activeMecaniciensCount.value) items.push(`Certains mécaniciens n'ont pas encore de login lié visible.`)
  return items
})

const userById = computed(() => {
  const map = new Map<number, any>()
  for (const user of users.value) {
    const id = asId(user.id)
    if (id) map.set(id, user)
  }
  return map
})

const pontRows = computed(() => {
  return ponts.value.map((p) => {
    const pontId = asId(p.id)
    const todayRdvs = rdvs.value.filter((r) => r.date_rdv === today.value && r.pont_id === pontId && !['annule', 'non_presente'].includes(r.status))
    const loadMinutes = todayRdvs.reduce((sum, r) => sum + Number(r.temps_estime ?? r.tempsEstime ?? r.duree_estimee ?? 60), 0)

    return {
      ...p,
      rdv_today: todayRdvs.length,
      load_minutes: loadMinutes,
    }
  })
})

const mecanicienCards = computed(() => {
  return mecaniciens.value.map((meca) => {
    const mecaId = asId(meca.id)
    const linkedUser = asId(meca.userId ?? meca.user_id) ? userById.value.get(asId(meca.userId ?? meca.user_id) as number) : null
    const assignedPont = ponts.value.find((p) => asId(p.mecanicien?.id ?? p.mecanicien_id) === mecaId && isActiveFlag(p.est_actif))
    const todayRdvs = rdvs.value.filter((r) => r.date_rdv === today.value && r.mecanicien_id === mecaId && !['annule', 'non_presente'].includes(r.status))

    return {
      ...meca,
      isActive: isActiveFlag(meca.is_active ?? meca.isActive),
      loginLabel: linkedUser ? `${linkedUser.username || linkedUser.email || 'login lié'}` : 'Aucun login lié',
      pontNom: assignedPont?.nom || 'Non affecté',
      rdvToday: todayRdvs.length,
    }
  }).sort((a, b) => Number(b.isActive) - Number(a.isActive) || String(a.nom || '').localeCompare(String(b.nom || '')))
})

function normalizeRdv(item: any) {
  return {
    ...item,
    date_rdv: item.date_rdv ?? item.dateRdv ?? '',
    status: item.statut ?? item.status ?? '',
    pont_id: asId(item.pont?.id ?? item.pont_id),
    mecanicien_id: asId(item.mecanicien?.id ?? item.mecanicien_id),
  }
}

function goToPlanning() {
  navigateTo('/planning')
}

function goToUsers() {
  navigateTo('/admin/users')
}

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
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
  }
}

async function deletePont(p: any) {
  if (!confirm(`Archiver le pont ${p.nom} ?`)) return

  try {
    await api.del(`/ponts/${p.id}`)
    toast.add({ title: 'Pont archivé', color: 'success' })
    await refreshData()
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: (e instanceof Error ? e.message : 'Erreur inconnue') || 'Archivage impossible', color: 'error' })
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
      mecanicien_id: form.mecanicien_id,
      mecanicien: form.mecanicien_id ? `/api/mecaniciens/${form.mecanicien_id}` : null,
    }

    if (editId.value) await api.patch(`/ponts/${editId.value}`, payload)
    else await api.post('/ponts', payload)

    showModal.value = false
    toast.add({ title: 'Pont sauvegardé', color: 'success' })
    await refreshData()
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
  } finally {
    saving.value = false
  }
}

async function refreshData() {
  const [pontData, mecaData, userData, rdvData] = await Promise.all([
    api.get('/ponts').catch(() => []),
    api.get('/mecaniciens').catch(() => []),
    api.get('/users').catch(() => []),
    api.get('/rendez-vous?itemsPerPage=2000&order[createdAt]=desc').catch(() => []),
  ])

  const rawPonts = unwrapHydraOrEmpty(pontData)
  const rawMecas = unwrapHydraOrEmpty(mecaData)
  const rawUsers = unwrapHydraOrEmpty(userData)
  const rawRdvs = unwrapHydraOrEmpty(rdvData)

  ponts.value = rawPonts.map((p: any) => ({
    ...p,
    mecanicien_id: asId(p.mecanicien?.id ?? p.mecanicien_id),
    mecanicien_nom: p.mecanicien ? `${p.mecanicien.prenom ?? ''} ${p.mecanicien.nom ?? ''}`.trim() : '',
    est_actif: isActiveFlag(p.est_actif ?? p.isActive ?? p.is_active),
  }))
  mecaniciens.value = rawMecas
  users.value = rawUsers
  rdvs.value = rawRdvs.map(normalizeRdv)
}

onMounted(async () => {
  try {
    await refreshData()
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.info-banner { margin-bottom:16px; padding:12px 14px; border:1px solid rgba(255,210,0,0.18); border-radius:12px; background:rgba(255,210,0,0.05); }
.info-banner-title { font-size:13px; font-weight:700; color:#FCD34D; }
.info-banner-text { font-size:12px; color:#E5E7EB; margin-top:4px; }
.alert-banner { margin-bottom:16px; padding:12px 14px; border:1px solid rgba(245,158,11,0.2); border-radius:12px; background:rgba(245,158,11,0.06); }
.alert-banner-title { font-size:13px; font-weight:700; color:#FBBF24; }
.alert-banner-list { margin:8px 0 0 18px; padding:0; color:#E5E7EB; font-size:12px; display:grid; gap:4px; }
.card-header { display:flex; align-items:center; justify-content:space-between; gap:8px; flex-wrap:wrap; }
.card-title { font-size:15px; font-weight:700; color:#E8E9ED; }
.card-subtitle { font-size:12px; color:#9CA3AF; }
.meca-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:12px; }
.meca-card { padding:12px; border-radius:12px; border:1px solid rgba(255,255,255,0.06); background:rgba(255,255,255,0.02); display:grid; gap:8px; }
.meca-card-header { display:flex; align-items:flex-start; justify-content:space-between; gap:8px; }
.meca-name { font-size:14px; font-weight:700; color:#E8E9ED; }
.meca-login { font-size:12px; color:#9CA3AF; }
.meca-info { font-size:12px; color:#D1D5DB; }
.meca-actions { display:flex; gap:8px; flex-wrap:wrap; }
.type-cell { display:grid; gap:2px; }
.type-label { color:#E8E9ED; font-weight:600; }
.type-meta { color:#9CA3AF; font-size:11px; }
.text-light { color:#E8E9ED; }
.text-red { color:#FCA5A5; }
.charge-cell { display:grid; gap:2px; }
.charge-label { color:#E8E9ED; font-weight:600; }
.charge-meta { color:#9CA3AF; font-size:11px; }
.btn-action-primary { color:#FFD200; font-size:12px; font-weight:600; background:none; border:none; cursor:pointer; }
.btn-action-info { color:#93C5FD; font-size:12px; font-weight:600; background:none; border:none; cursor:pointer; }
.btn-action-danger { color:#FCA5A5; font-size:12px; font-weight:600; background:none; border:none; cursor:pointer; }
.modal-header { display:flex; justify-content:space-between; align-items:center; }
.modal-title { font-weight:600; }
.modal-close { background:none; border:none; color:#9CA3AF; font-size:18px; cursor:pointer; }
.form-stack { display:flex; flex-direction:column; gap:12px; }
.form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.checkbox-row { display:flex; align-items:center; gap:8px; }
.checkbox-label { font-size:13px; color:#9CA3AF; }
.form-footer { display:flex; gap:10px; justify-content:flex-end; margin-top:12px; }
.btn-sm { font-size:12px; padding:6px 10px; }
.text-muted { padding:12px; color:#9CA3AF; }
.mb-4 { margin-bottom:16px; }
</style>
