<template>
  <div>
    <div class="page-header">
      <div style="display:flex;align-items:center;gap:12px;">
        <NuxtLink to="/admin" style="color:#6B7280;text-decoration:none;font-size:18px;">◀</NuxtLink>
        <div class="page-title">Gestion des prestations</div>
      </div>
      <button class="topbar-new-btn" @click="resetForm(); showModal = true">+ Nouvelle prestation</button>
    </div>

    <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
      <button v-for="cat in categoryFilters" :key="cat.value" class="btn" :class="filterCat === cat.value ? 'btn-primary' : 'btn-ghost'" style="font-size:12px;padding:6px 14px;" @click="filterCat = cat.value">
        {{ cat.label }}
      </button>
    </div>

    <UCard>
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px;">
        <div style="font-size:13px;color:#D1D5DB;">La page prestation reprend maintenant la même logique de pop-in par type de moto.</div>
        <div style="font-size:12px;color:#9CA3AF;">{{ activeMotoCategories.length }} type(s) moto actif(s)</div>
      </div>

      <UTable :data="filteredPrestations" :columns="columns" :loading="loading">
        <template #categorie-cell="{ row }">
          <span style="font-size:11px;padding:3px 10px;border-radius:6px;background:rgba(139,92,246,0.1);color:#C4B5FD;">{{ row.original.categorie_nom || '—' }}</span>
        </template>

        <template #prix_ht-cell="{ row }">
          <span style="color:#FFD200;font-weight:600;">{{ formatCurrency(row.original.prix_ht) }}</span>
        </template>

        <template #temps_estime-cell="{ row }">
          {{ formatDuration(row.original.temps_estime) }}
        </template>

        <template #type_tarif-cell="{ row }">
          <span style="font-size:11px;padding:3px 10px;border-radius:999px;background:rgba(255,210,0,0.1);color:#FFD200;">{{ labelTypeTarif(row.original.type_tarif) }}</span>
        </template>

        <template #tarifs_moto-cell="{ row }">
          <div style="display:flex;flex-direction:column;gap:6px;">
            <span style="font-size:12px;color:#E8E9ED;">{{ row.original.enabledCount }} type(s) actif(s)</span>
            <div style="display:flex;gap:4px;flex-wrap:wrap;">
              <span v-for="mode in row.original.modes" :key="`${row.original.id}-${mode}`" style="font-size:10px;padding:2px 8px;border-radius:999px;background:rgba(139,92,246,0.14);color:#C4B5FD;">
                {{ labelTypeTarif(mode) }}
              </span>
              <span v-if="!row.original.modes.length" style="font-size:10px;color:#9CA3AF;">Non configuré</span>
            </div>
          </div>
        </template>

        <template #actions-cell="{ row }">
          <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button style="color:#FFD200;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="editPrestation(row.original)">✏ Modifier</button>
            <button style="color:#93C5FD;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="openTarifModal(row.original)">⚙ Tarifs moto</button>
            <button style="color:#FCA5A5;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="deletePrestation(row.original.id)">🗄 Archiver</button>
          </div>
        </template>
      </UTable>
    </UCard>

    <AppModal v-model:open="showModal" size="xl">
      <template #content>
        <UCard>
          <template #header>
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <span style="font-weight:600;">{{ editId ? 'Modifier' : 'Nouvelle' }} prestation</span>
              <button @click="showModal = false" style="background:none;border:none;color:#9CA3AF;font-size:18px;cursor:pointer;">✕</button>
            </div>
          </template>

          <form @submit.prevent="savePrestation" style="display:flex;flex-direction:column;gap:12px;">
            <div class="form-group">
              <label class="form-label">Nom *</label>
              <input v-model="form.nom" class="form-input" required placeholder="Ex: Vidange" />
            </div>

            <div class="form-group">
              <label class="form-label">Description</label>
              <textarea v-model="form.description" class="form-input" rows="2" placeholder="Description optionnelle" />
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <div class="form-group">
                <label class="form-label">Prix HT (€) *</label>
                <input v-model.number="form.prix_ht" type="number" step="0.01" class="form-input" required />
              </div>
              <div class="form-group">
                <label class="form-label">Temps estimé (min) *</label>
                <input v-model.number="form.temps_estime" type="number" class="form-input" required />
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Catégorie</label>
              <select v-model="form.categorie" class="form-input">
                <option value="">Aucune</option>
                <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Type tarif par défaut</label>
              <select v-model="form.type_tarif" class="form-input">
                <option value="forfait">Forfait</option>
                <option value="horaire">Horaire</option>
                <option value="devis">Sur devis</option>
              </select>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:12px;">
              <button type="button" class="btn btn-ghost" @click="showModal = false">Annuler</button>
              <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? 'Enregistrement…' : editId ? 'Modifier' : 'Créer' }}</button>
            </div>
          </form>
        </UCard>
      </template>
    </AppModal>

    <AppModal v-model:open="showTarifModal" size="xl">
      <template #content>
        <UCard>
          <template #header>
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
              <div>
                <div style="font-weight:700;color:#E8E9ED;">{{ activeTarifPrestation?.nom || 'Tarifs prestation' }}</div>
                <div style="font-size:12px;color:#9CA3AF;">Forfait, horaire ou sur devis selon chaque type de moto.</div>
              </div>
              <button type="button" @click="showTarifModal = false" style="background:none;border:none;color:#9CA3AF;font-size:18px;cursor:pointer;">✕</button>
            </div>
          </template>

          <div v-if="!activeMotoCategories.length" style="font-size:13px;color:#FCA5A5;">Aucun type moto actif n'est disponible pour cette configuration.</div>

          <div v-else style="display:flex;flex-direction:column;gap:12px;">
            <div v-for="row in tarifRows" :key="row.categorie_id" style="border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:12px;background:rgba(255,255,255,0.02);">
              <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                <div>
                  <div style="font-weight:700;color:#E8E9ED;">{{ row.categorie_label }}</div>
                  <div style="font-size:11px;color:#9CA3AF;">Active ou masque cette prestation pour ce type.</div>
                </div>
                <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:#D1D5DB;">
                  <input v-model="row.is_active" type="checkbox" :true-value="1" :false-value="0" />
                  Activée
                </label>
              </div>

              <div :style="{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '10px', marginTop: '12px', opacity: Number(row.is_active) === 1 ? 1 : 0.55 }">
                <div class="form-group">
                  <label class="form-label">Mode tarif</label>
                  <select v-model="row.type_tarif" class="form-input" :disabled="Number(row.is_active) !== 1">
                    <option value="forfait">Forfait</option>
                    <option value="horaire">Horaire</option>
                    <option value="devis">Sur devis</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">{{ row.type_tarif === 'horaire' ? 'Taux horaire TTC (€)' : 'Prix TTC (€)' }}</label>
                  <input v-model.number="row.prix_ttc" type="number" step="0.01" class="form-input" :disabled="Number(row.is_active) !== 1 || row.type_tarif === 'devis'" />
                </div>
                <div class="form-group">
                  <label class="form-label">Temps (min)</label>
                  <input v-model.number="row.temps_minutes" type="number" step="1" class="form-input" :disabled="Number(row.is_active) !== 1" />
                </div>
              </div>

              <div v-if="row.type_tarif === 'devis'" style="font-size:11px;color:#9CA3AF;margin-top:8px;">Le montant sera saisi au cas par cas sur le devis.</div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:8px;">
              <button type="button" class="btn btn-ghost" @click="showTarifModal = false">Annuler</button>
              <button type="button" class="btn btn-primary" :disabled="modalSaving" @click="saveTarifModal">{{ modalSaving ? 'Enregistrement…' : 'Enregistrer la pop-in' }}</button>
            </div>
          </div>
        </UCard>
      </template>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const toast = useToast()
const activeAtelierCookie = useCookie<string | null>('active_atelier_id', { default: () => null })

const loading = ref(true)
const saving = ref(false)
const modalSaving = ref(false)
const showModal = ref(false)
const showTarifModal = ref(false)
const editId = ref<number | null>(null)
const filterCat = ref('all')
const prestations = ref<any[]>([])
const motoCategories = ref<any[]>([])
const grilles = ref<any[]>([])
const activeTarifPrestation = ref<any | null>(null)
const tarifRows = ref<any[]>([])
const tvaMo = ref(20)

const form = reactive({
  code: '',
  nom: '',
  description: '',
  prix_ht: 0,
  temps_estime: 30,
  categorie: '',
  type_tarif: 'forfait',
})

const categories = computed(() => {
  const cats = new Set(prestations.value.map((p: any) => p.categorie_nom || p.categorie).filter(Boolean))
  return Array.from(cats).sort()
})

const activeMotoCategories = computed(() => motoCategories.value.filter((cat: any) => Number(cat.is_active ?? 1) === 1))

const categoryFilters = computed(() => [
  { value: 'all', label: 'Toutes' },
  ...categories.value.map((c) => ({ value: c, label: c })),
])

const filteredPrestations = computed(() => {
  const enriched = prestations.value.map((p: any) => {
    const rows = grilles.value.filter((g: any) => g.prestation_id === p.id && activeMotoCategories.value.some((cat: any) => cat.id === g.categorie_id))
    const enabledRows = rows.filter((g: any) => Number(g.is_active ?? 1) === 1)
    const modes = Array.from(new Set(enabledRows.map((g: any) => g.type_tarif || p.type_tarif || 'forfait')))

    return {
      ...p,
      enabledCount: enabledRows.length,
      modes,
    }
  })

  if (filterCat.value === 'all') return enriched
  return enriched.filter((p: any) => (p.categorie_nom || p.categorie) === filterCat.value)
})

const columns = [
  { key: 'nom', label: 'Prestation' },
  { key: 'categorie', label: 'Catégorie' },
  { key: 'temps_estime', label: 'Durée' },
  { key: 'prix_ht', label: 'Prix HT' },
  { key: 'type_tarif', label: 'Type' },
  { key: 'tarifs_moto', label: 'Tarifs moto' },
  { key: 'actions', label: '' },
]

function toNumber(value: any, fallback = 0) {
  const parsed = Number(value ?? fallback)
  return Number.isFinite(parsed) ? parsed : fallback
}

function formatCurrency(v: number) {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(toNumber(v))
}

function formatDuration(min: number) {
  const total = toNumber(min, 0)
  if (!total) return '—'
  const h = Math.floor(total / 60)
  const m = total % 60
  return h > 0 ? `${h}h${m > 0 ? ` ${m}min` : ''}` : `${m}min`
}

function labelTypeTarif(value: string) {
  if (value === 'horaire') return 'Horaire'
  if (value === 'devis') return 'Sur devis'
  return 'Forfait'
}

function unwrapList(data: any) {
  return data?.['hydra:member'] ?? data?.member ?? (Array.isArray(data) ? data : [])
}

function extractId(value: any): number | null {
  if (value == null) return null
  if (typeof value === 'number') return value
  if (typeof value === 'string') {
    const id = Number(value.split('/').pop())
    return Number.isFinite(id) ? id : null
  }
  const id = Number(value.id ?? value['@id']?.split('/').pop())
  return Number.isFinite(id) ? id : null
}

function generateCode(name: string) {
  const base = (name || 'prestation')
    .normalize('NFD')
    .replace(/\p{Diacritic}/gu, '')
    .replace(/[^a-zA-Z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')
    .toUpperCase()

  return `PRESTA_${base || 'ATELIER'}`
}

function getActiveAtelierId(): number | null {
  const parsed = Number(activeAtelierCookie.value ?? 0)
  return Number.isFinite(parsed) && parsed > 0 ? parsed : null
}

function withAtelierContext(path: string) {
  const atelierId = getActiveAtelierId()
  if (!atelierId) return path
  return `${path}${path.includes('?') ? '&' : '?'}atelier_id=${atelierId}`
}

async function syncAtelierContext() {
  const atelierId = getActiveAtelierId()
  if (!atelierId) return

  try {
    await api.post('/auth/switch-atelier', { atelier_id: atelierId })
  } catch {
    // on garde quand même le chargement explicite via atelier_id en query string
  }
}

function normalizePrestations(items: any[]) {
  return items.map((p: any) => ({
    ...p,
    categorie_nom: p.categorie_moto?.nom ?? p.categorie ?? '',
    prix_ht: toNumber(p.prix_ht ?? p.prix_base_ht, 0),
    temps_estime: toNumber(p.temps_estime ?? p.temps_estime_minutes, 30),
    type_tarif: p.type_tarif ?? p.typeTarif ?? 'forfait',
  }))
}

function normalizeCategories(items: any[]) {
  return items.map((cat: any) => ({
    id: Number(cat.id),
    nom: cat.nom,
    description: cat.description ?? '',
    is_active: Number(cat.is_active ?? cat.isActive ?? 1),
  }))
}

function normalizeGrilles(items: any[]) {
  return items.map((g: any) => ({
    id: Number(g.id),
    prestation_id: extractId(g.prestation_id ?? g.prestation),
    categorie_id: extractId(g.categorie_moto_id ?? g.categorie_moto ?? g.categorieMoto),
    prix_ttc: toNumber(g.prix_ttc ?? g.prixTtc, 0),
    temps_minutes: toNumber(g.temps_minutes ?? g.tempsMinutes, 30),
    type_tarif: g.type_tarif ?? g.typeTarif ?? 'forfait',
    is_active: Number(g.is_active ?? g.isActive ?? 1),
  }))
}

function resetForm() {
  editId.value = null
  Object.assign(form, { code: '', nom: '', description: '', prix_ht: 0, temps_estime: 30, categorie: '', type_tarif: 'forfait' })
}

function editPrestation(p: any) {
  editId.value = p.id
  Object.assign(form, {
    code: p.code ?? '',
    nom: p.nom,
    description: p.description ?? '',
    prix_ht: p.prix_ht,
    temps_estime: p.temps_estime,
    categorie: p.categorie_nom || p.categorie || '',
    type_tarif: p.type_tarif || 'forfait',
  })
  showModal.value = true
}

function buildGrillePayload(entry: any) {
  const prixTtc = entry.type_tarif === 'devis' ? 0 : toNumber(entry.prix_ttc, 0)
  const prixHt = prixTtc / (1 + (toNumber(tvaMo.value, 20) / 100))

  return {
    prestation: `/api/prestations/${entry.prestation_id}`,
    categorie_moto: `/api/motos/categories/${entry.categorie_id}`,
    type_vehicule: 'tous',
    prix_ht: prixHt.toFixed(2),
    prix_ttc: prixTtc.toFixed(2),
    temps_minutes: toNumber(entry.temps_minutes, 30),
    type_tarif: entry.type_tarif || 'forfait',
    delai_jours: 1,
    is_active: Number(entry.is_active ?? 1) === 1 ? 1 : 0,
  }
}

function openTarifModal(prestation: any) {
  if (!activeMotoCategories.value.length) {
    toast.add({ title: 'Aucun type moto actif', description: 'Active d’abord un type de moto dans la configuration atelier.', color: 'warning' })
    return
  }

  activeTarifPrestation.value = prestation
  const byCategorie = new Map(
    grilles.value
      .filter((g: any) => g.prestation_id === prestation.id)
      .map((g: any) => [g.categorie_id, g]),
  )

  tarifRows.value = activeMotoCategories.value.map((cat: any) => {
    const existing = byCategorie.get(cat.id)
    return {
      id: existing?.id ?? null,
      prestation_id: prestation.id,
      categorie_id: cat.id,
      categorie_label: cat.nom,
      prix_ttc: toNumber(existing?.prix_ttc ?? prestation.prix_ht * 1.2, 0),
      temps_minutes: toNumber(existing?.temps_minutes ?? prestation.temps_estime, 30),
      type_tarif: existing?.type_tarif ?? prestation.type_tarif ?? 'forfait',
      is_active: Number(existing?.is_active ?? 1),
    }
  })

  showTarifModal.value = true
}

async function deletePrestation(id: number) {
  try {
    await api.del(`/prestations/${id}`)
    toast.add({ title: 'Prestation archivée', color: 'success' })
    await fetchData()
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e?.message, color: 'error' })
  }
}

async function savePrestation() {
  saving.value = true
  try {
    const prixHt = toNumber(form.prix_ht, 0)
    const payload = {
      code: form.code || generateCode(form.nom),
      nom: form.nom,
      description: form.description || null,
      categorie: form.categorie || 'entretien',
      prix_base_ht: prixHt,
      prix_base_ttc: Number((prixHt * 1.2).toFixed(2)),
      temps_estime_minutes: toNumber(form.temps_estime, 30),
      type_tarif: form.type_tarif || 'forfait',
      is_active: 1,
    }

    if (editId.value) {
      await api.put(`/prestations/${editId.value}`, payload)
    } else {
      await api.post('/prestations', payload)
    }

    showModal.value = false
    toast.add({ title: 'Prestation sauvegardée', color: 'success' })
    await fetchData()
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e?.message, color: 'error' })
  } finally {
    saving.value = false
  }
}

async function saveTarifModal() {
  if (!activeTarifPrestation.value) return

  modalSaving.value = true
  try {
    await Promise.all(
      tarifRows.value.map(async (row: any) => {
        const payload = buildGrillePayload(row)

        if (row.id) {
          await api.patch(`/grille_tarifaires/${row.id}`, payload)
          return
        }

        if (Number(row.is_active ?? 1) === 1) {
          await api.post('/grille_tarifaires', payload)
        }
      }),
    )

    showTarifModal.value = false
    toast.add({ title: 'Tarifs prestation enregistrés', color: 'success' })
    await fetchData()
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e?.message, color: 'error' })
  } finally {
    modalSaving.value = false
  }
}

async function fetchData() {
  await syncAtelierContext()

  const [prestationsResult, categoriesResult, grillesResult, configResult] = await Promise.allSettled([
    api.get(withAtelierContext('/prestations?itemsPerPage=200')),
    api.get(withAtelierContext('/motos/categories?itemsPerPage=200')),
    api.get(withAtelierContext('/grille_tarifaires?itemsPerPage=400')),
    api.get(withAtelierContext('/config')),
  ])

  const prestationData = prestationsResult.status === 'fulfilled' ? prestationsResult.value : []
  const categoriesData = categoriesResult.status === 'fulfilled' ? categoriesResult.value : []
  const grillesData = grillesResult.status === 'fulfilled' ? grillesResult.value : []
  const configData = configResult.status === 'fulfilled' ? configResult.value : null

  prestations.value = normalizePrestations(unwrapList(prestationData))
  motoCategories.value = normalizeCategories(unwrapList(categoriesData))
  grilles.value = normalizeGrilles(unwrapList(grillesData))
  tvaMo.value = toNumber(configData?.tva_mo_taux, 20)

  if (!prestations.value.length) {
    try {
      const atelierId = getActiveAtelierId()
      await api.post('/config/prestations/bootstrap', atelierId ? { atelier_id: atelierId } : {})
      const [reloadedPrestations, reloadedGrilles] = await Promise.all([
        api.get(withAtelierContext('/prestations?itemsPerPage=200')),
        api.get(withAtelierContext('/grille_tarifaires?itemsPerPage=400')),
      ])
      prestations.value = normalizePrestations(unwrapList(reloadedPrestations))
      grilles.value = normalizeGrilles(unwrapList(reloadedGrilles))
    } catch {
      // garde l'état vide si aucun catalogue source n'est disponible
    }
  }
}

watch(showTarifModal, (open) => {
  if (!open) {
    activeTarifPrestation.value = null
    tarifRows.value = []
  }
})

watch(() => activeAtelierCookie.value, async (next, previous) => {
  if (next === previous) return
  loading.value = true
  try {
    await fetchData()
  } finally {
    loading.value = false
  }
})

onMounted(async () => {
  try {
    await fetchData()
  } finally {
    loading.value = false
  }
})
</script>
