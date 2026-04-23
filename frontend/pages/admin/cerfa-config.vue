<template>
  <div>
    <AppPageHeader
      title="Configuration CERFA"
      subtitle="Ajustez les positions (x/y) et la taille de police de chaque champ des formulaires CERFA."
    />

    <UCard class="mb-4">
      <div class="cerfa-tabs">
        <button
          v-for="(libelle, ref) in cerfaRefs"
          :key="ref"
          :class="['cerfa-tab', { active: selectedRef === ref }]"
          @click="selectRef(ref)"
        >
          {{ libelle }}
        </button>
      </div>
    </UCard>

    <UCard v-if="selectedRef">
      <div class="cerfa-toolbar">
        <UInput v-model="search" placeholder="Filtrer par label ou field_key..." class="cerfa-search" />
        <span class="cerfa-count">{{ filteredFields.length }} champs</span>
      </div>

      <div v-if="loading" class="cerfa-loading">Chargement…</div>
      <div v-else-if="!filteredFields.length" class="cerfa-empty">Aucun champ trouvé.</div>
      <div v-else class="cerfa-table-wrap">
        <table class="cerfa-table">
          <thead>
            <tr>
              <th>Champ</th>
              <th>Type</th>
              <th>X (mm)</th>
              <th>Y (mm)</th>
              <th>Largeur (mm)</th>
              <th>Police (pt)</th>
              <th>Actif</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="field in filteredFields" :key="field.id" :class="{ 'row-inactive': !field.is_active }">
              <td>
                <div class="field-label">{{ field.label }}</div>
                <div class="field-key">{{ field.field_key }}</div>
                <div v-if="field.description" class="field-desc">{{ field.description }}</div>
              </td>
              <td><span :class="['type-badge', `type-${field.field_type}`]">{{ field.field_type }}</span></td>
              <td>
                <input
                  type="number"
                  step="0.1"
                  class="coord-input"
                  :value="field.x"
                  @change="queueUpdate(field, 'x', ($event.target as HTMLInputElement).value)"
                />
              </td>
              <td>
                <input
                  type="number"
                  step="0.1"
                  class="coord-input"
                  :value="field.y"
                  @change="queueUpdate(field, 'y', ($event.target as HTMLInputElement).value)"
                />
              </td>
              <td>
                <input
                  type="number"
                  step="0.5"
                  class="coord-input"
                  :value="field.width"
                  @change="queueUpdate(field, 'width', ($event.target as HTMLInputElement).value)"
                />
              </td>
              <td>
                <input
                  type="number"
                  step="0.5"
                  min="5"
                  max="14"
                  class="coord-input coord-input--sm"
                  :value="field.font_size"
                  @change="queueUpdate(field, 'font_size', ($event.target as HTMLInputElement).value)"
                />
              </td>
              <td>
                <input
                  type="checkbox"
                  :checked="field.is_active"
                  @change="queueUpdate(field, 'is_active', ($event.target as HTMLInputElement).checked)"
                />
              </td>
              <td class="actions-cell">
                <button
                  v-if="pendingFields.has(field.id)"
                  class="btn-save"
                  :disabled="savingId === field.id"
                  @click="saveField(field)"
                >{{ savingId === field.id ? '…' : 'Enregistrer' }}</button>
                <button
                  class="btn-reset"
                  :disabled="savingId === field.id"
                  @click="resetField(field)"
                  title="Réinitialiser aux valeurs d'origine"
                >↺</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </UCard>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ title: 'Configuration CERFA' })

const { $api } = useNuxtApp()
const toast = useToast()
const api = useApi()

interface CerfaField {
  id: number
  cerfa_ref: string
  field_key: string
  label: string
  x: string
  y: string
  width: string
  font_size: string
  field_type: string
  description: string | null
  is_active: boolean
}

const cerfaRefs = ref<Record<string, string>>({})
const fields = ref<CerfaField[]>([])
const selectedRef = ref<string>('')
const search = ref('')
const loading = ref(false)
const savingId = ref<number | null>(null)

// Suivi des champs modifiés localement (id → patch payload)
const pendingFields = ref<Map<number, Record<string, any>>>(new Map())

const filteredFields = computed(() => {
  if (!search.value) return fields.value
  const q = search.value.toLowerCase()
  return fields.value.filter(f =>
    f.label.toLowerCase().includes(q) || f.field_key.toLowerCase().includes(q)
  )
})

async function loadAll() {
  loading.value = true
  try {
    const res = await api.get('/admin/cerfa-config')
    cerfaRefs.value = res.cerfa_refs ?? {}
    if (!selectedRef.value && Object.keys(cerfaRefs.value).length) {
      selectedRef.value = Object.keys(cerfaRefs.value)[0]
    }
    filterByRef(res.items ?? [])
  } catch (e: any) {
    toast.add({ title: 'Erreur chargement CERFA', description: e?.message, color: 'error' })
  } finally {
    loading.value = false
  }
}

function filterByRef(allFields: CerfaField[]) {
  fields.value = allFields.filter(f => f.cerfa_ref === selectedRef.value)
}

async function selectRef(ref: string) {
  selectedRef.value = ref
  loading.value = true
  try {
    const res = await api.get(`/admin/cerfa-config?cerfa_ref=${ref}`)
    fields.value = res.items ?? []
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e?.message, color: 'error' })
  } finally {
    loading.value = false
  }
  pendingFields.value.clear()
}

function queueUpdate(field: CerfaField, key: string, value: any) {
  const patch = pendingFields.value.get(field.id) ?? {}
  patch[key] = value
  pendingFields.value.set(field.id, patch)
  // Mettre à jour la valeur locale immédiatement pour le rendu
  ;(field as any)[key] = value
}

async function saveField(field: CerfaField) {
  const patch = pendingFields.value.get(field.id)
  if (!patch) return
  savingId.value = field.id
  try {
    const updated = await api.patch(`/admin/cerfa-config/${field.id}`, patch)
    Object.assign(field, updated)
    pendingFields.value.delete(field.id)
    toast.add({ title: 'Enregistré', color: 'success' })
  } catch (e: any) {
    toast.add({ title: 'Erreur sauvegarde', description: e?.message, color: 'error' })
  } finally {
    savingId.value = null
  }
}

async function resetField(field: CerfaField) {
  savingId.value = field.id
  try {
    const updated = await api.post(`/admin/cerfa-config/${field.id}/reset`, {})
    Object.assign(field, updated)
    pendingFields.value.delete(field.id)
    toast.add({ title: 'Réinitialisé', color: 'success' })
  } catch (e: any) {
    toast.add({ title: 'Erreur reset', description: e?.message, color: 'error' })
  } finally {
    savingId.value = null
  }
}

onMounted(loadAll)
</script>

<style scoped>
.cerfa-tabs {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.cerfa-tab {
  padding: 6px 14px;
  border-radius: 6px;
  border: 1px solid #374151;
  background: #111827;
  color: #9ca3af;
  font-size: 12px;
  cursor: pointer;
  transition: all 0.15s;
}

.cerfa-tab:hover {
  background: #1f2937;
  color: #e5e7eb;
}

.cerfa-tab.active {
  background: #1d4ed8;
  border-color: #1d4ed8;
  color: #fff;
  font-weight: 600;
}

.cerfa-toolbar {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}

.cerfa-search {
  flex: 1;
  max-width: 320px;
}

.cerfa-count {
  font-size: 12px;
  color: #6b7280;
}

.cerfa-loading,
.cerfa-empty {
  text-align: center;
  color: #6b7280;
  padding: 40px;
  font-size: 13px;
}

.cerfa-table-wrap {
  overflow-x: auto;
}

.cerfa-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px;
}

.cerfa-table th {
  text-align: left;
  padding: 6px 10px;
  color: #6b7280;
  font-weight: 600;
  font-size: 11px;
  text-transform: uppercase;
  border-bottom: 1px solid #1f2937;
}

.cerfa-table td {
  padding: 6px 10px;
  border-bottom: 1px solid #111827;
  vertical-align: middle;
}

.cerfa-table tr:hover td {
  background: #0f172a;
}

.row-inactive td {
  opacity: 0.4;
}

.field-label {
  font-weight: 500;
  color: #e5e7eb;
}

.field-key {
  font-family: monospace;
  font-size: 10px;
  color: #6b7280;
  margin-top: 2px;
}

.field-desc {
  font-size: 10px;
  color: #4b5563;
  margin-top: 2px;
  font-style: italic;
}

.type-badge {
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
}

.type-text     { background: #1f2937; color: #9ca3af; }
.type-boxed    { background: #1e3a5f; color: #60a5fa; }
.type-date     { background: #14532d; color: #4ade80; }
.type-checkbox { background: #4c1d95; color: #c4b5fd; }

.coord-input {
  width: 70px;
  background: #1f2937;
  border: 1px solid #374151;
  border-radius: 4px;
  color: #e5e7eb;
  padding: 3px 6px;
  font-size: 12px;
  text-align: right;
}

.coord-input--sm {
  width: 52px;
}

.coord-input:focus {
  outline: none;
  border-color: #1d4ed8;
}

.actions-cell {
  display: flex;
  gap: 6px;
  align-items: center;
}

.btn-save {
  background: #1d4ed8;
  color: #fff;
  border: none;
  border-radius: 5px;
  padding: 3px 10px;
  font-size: 11px;
  cursor: pointer;
}

.btn-save:hover { background: #1e40af; }
.btn-save:disabled { opacity: 0.5; cursor: default; }

.btn-reset {
  background: none;
  border: 1px solid #374151;
  color: #9ca3af;
  border-radius: 5px;
  padding: 3px 8px;
  font-size: 13px;
  cursor: pointer;
}

.btn-reset:hover { background: #1f2937; color: #e5e7eb; }
.btn-reset:disabled { opacity: 0.4; cursor: default; }
</style>
