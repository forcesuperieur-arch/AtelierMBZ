<template>
  <div>
    <div class="motos-header">
      <h1 style="font-size:20px;font-weight:800;">Mes motos</h1>
      <button class="add-moto-btn" @click="showAddForm = !showAddForm">
        {{ showAddForm ? 'Annuler' : '+ Ajouter une moto' }}
      </button>
    </div>

    <form v-if="showAddForm" class="add-moto-card" @submit.prevent="submitAdd">
      <div class="add-moto-grid">
        <div class="moto-field autocomplete-field">
          <label>Marque *</label>
          <input
            v-model="addForm.marque"
            required
            placeholder="Yamaha"
            autocomplete="off"
            @input="onMarqueInput"
            @focus="onMarqueInput"
            @blur="deferHideMarqueSuggestions"
          />
          <ul v-if="marqueSuggestions.length" class="autocomplete-list">
            <li v-for="m in marqueSuggestions" :key="m" @mousedown.prevent="selectMarque(m)">{{ m }}</li>
          </ul>
        </div>
        <div class="moto-field autocomplete-field">
          <label>Modèle *</label>
          <input
            v-model="addForm.modele"
            required
            placeholder="MT-07"
            autocomplete="off"
            @input="onModeleInput"
            @focus="onModeleInput"
            @blur="deferHideModeleSuggestions"
          />
          <ul v-if="modeleSuggestions.length" class="autocomplete-list">
            <li v-for="item in modeleSuggestions" :key="item.id" @mousedown.prevent="selectModele(item)">
              {{ suggestionLabel(item) }}
            </li>
          </ul>
        </div>
        <div class="moto-field">
          <label>Plaque</label>
          <input v-model="addForm.plaque" placeholder="AB-123-CD" />
        </div>
        <div class="moto-field">
          <label>Type</label>
          <select v-model="addForm.type_moto">
            <option value="">— Choisir —</option>
            <option v-for="t in motoTypes" :key="t" :value="t">{{ t }}</option>
          </select>
        </div>
        <div class="moto-field">
          <label>Cylindrée</label>
          <input v-model="addForm.cylindree" type="number" placeholder="700" />
        </div>
        <div class="moto-field">
          <label>Année</label>
          <input v-model="addForm.annee" type="number" placeholder="2022" />
        </div>
      </div>
      <p class="moto-hint">Choisissez une suggestion pour préremplir type, cylindrée et année automatiquement.</p>
      <p v-if="addError" class="moto-error">{{ addError }}</p>
      <button class="moto-save" type="submit" :disabled="addSaving">{{ addSaving ? 'Ajout…' : 'Ajouter' }}</button>
    </form>

    <div v-if="pending" style="color:var(--content-3)">Chargement…</div>
    <div v-else-if="error" style="color:var(--error-content)">Impossible de charger vos motos pour le moment. Réessayez plus tard.</div>
    <div v-else-if="motos.length === 0" style="color:var(--content-3)">Aucune moto enregistrée.</div>
    <div v-else style="display:flex;flex-direction:column;gap:12px;">
      <div v-for="moto in motos" :key="moto.id" class="moto-card">
        <div class="moto-name">{{ moto.marque }} {{ moto.modele }}</div>
        <div class="moto-meta">
          <span v-if="moto.plaque">{{ moto.plaque }}</span>
          <span v-if="moto.annee">{{ moto.annee }}</span>
          <span v-if="moto.cylindree">{{ moto.cylindree }}</span>
        </div>

        <div class="moto-field">
          <label>Kilométrage</label>
          <input
            v-model.number="edits[moto.id].kilometrage"
            type="number"
            min="0"
            placeholder="Non renseigné"
          />
        </div>
        <div class="moto-field">
          <label>Notes</label>
          <textarea
            v-model="edits[moto.id].notes"
            rows="2"
            placeholder="Entretiens perso, particularités…"
          />
        </div>

        <button
          class="moto-save"
          :disabled="saving[moto.id]"
          @click="save(moto.id)"
        >{{ saving[moto.id] ? 'Enregistrement…' : 'Enregistrer' }}</button>
        <div v-if="saveError[moto.id]" class="moto-error">{{ saveError[moto.id] }}</div>
        <div v-if="saved[moto.id]" class="moto-saved">Enregistré.</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const auth = useAuthStore()
const { apiFetch } = useClientApi()

// default: () => [] + error → pas d'écran blanc si l'API échoue.
const { data: motos, pending, error } = useAsyncData('client-motos', async () => {
  if (!auth.isAuthenticated) return []
  return await apiFetch('/api/client/vehicules')
}, { default: () => [] })

// Brouillon d'édition par moto, initialisé une fois les données chargées :
// évite qu'un v-model direct sur `motos` réécrive un état serveur avant l'enregistrement.
const edits = reactive<Record<number, { kilometrage: number | null; notes: string }>>({})
const saving = reactive<Record<number, boolean>>({})
const saveError = reactive<Record<number, string>>({})
const saved = reactive<Record<number, boolean>>({})

watch(motos, (list) => {
  for (const m of list || []) {
    if (!(m.id in edits)) {
      edits[m.id] = { kilometrage: m.kilometrage ?? null, notes: m.notes ?? '' }
    }
  }
}, { immediate: true })

async function save(id: number) {
  saving[id] = true
  saveError[id] = ''
  saved[id] = false
  try {
    await apiFetch(`/api/client/vehicules/${id}`, {
      method: 'PATCH',
      body: { kilometrage: edits[id].kilometrage, notes: edits[id].notes },
    })
    saved[id] = true
  } catch (e: any) {
    saveError[id] = e?.data?.error || 'Impossible d\'enregistrer. Réessayez.'
  } finally {
    saving[id] = false
  }
}

const motoTypes = ['Roadster', 'Sportive', 'Trail', 'Touring', 'Custom', 'Scooter', 'Enduro', 'Supermotard', 'Vintage', 'Électrique']
const showAddForm = ref(false)
const addSaving = ref(false)
const addError = ref('')
const addForm = reactive({ marque: '', modele: '', plaque: '', type_moto: '', cylindree: '', annee: '' })

const {
  marqueSuggestions,
  modeleSuggestions,
  onMarqueInput,
  onModeleInput,
  selectMarque,
  selectModele,
  deferHideMarqueSuggestions,
  deferHideModeleSuggestions,
  suggestionLabel,
} = useMotoAutocomplete({
  form: addForm,
  marqueKey: 'marque',
  modeleKey: 'modele',
  cylindreeKey: 'cylindree',
  typeKey: 'type_moto',
  anneeKey: 'annee',
})

async function submitAdd() {
  addSaving.value = true
  addError.value = ''
  try {
    const created: any = await apiFetch('/api/client/vehicules', {
      method: 'POST',
      body: {
        marque: addForm.marque,
        modele: addForm.modele,
        plaque: addForm.plaque || null,
        type_moto: addForm.type_moto || null,
        cylindree: addForm.cylindree || null,
        annee: addForm.annee || null,
      },
    })
    motos.value = [...motos.value, {
      id: created.id,
      plaque: created.plaque ?? addForm.plaque ?? null,
      marque: created.marque ?? addForm.marque,
      modele: created.modele ?? addForm.modele,
      type_moto: created.type_moto ?? addForm.type_moto ?? null,
      cylindree: created.cylindree ?? addForm.cylindree ?? null,
      annee: created.annee ?? addForm.annee ?? null,
      kilometrage: created.kilometrage ?? null,
      notes: created.notes ?? null,
      prochaine_vidange: created.prochaine_vidange ?? null,
    }]
    Object.assign(addForm, { marque: '', modele: '', plaque: '', type_moto: '', cylindree: '', annee: '' })
    showAddForm.value = false
  } catch (e: any) {
    addError.value = e?.data?.error || 'Impossible d\'ajouter cette moto. Réessayez.'
  } finally {
    addSaving.value = false
  }
}
</script>

<style scoped>
.motos-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}
.add-moto-btn {
  padding: 8px 14px;
  border-radius: 8px;
  border: 1px solid var(--border-1);
  background: transparent;
  color: var(--content-1);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
}
.add-moto-card {
  padding: 16px;
  margin-bottom: 16px;
  background: var(--surface-1);
  border: 1px solid var(--border-2);
  border-radius: 12px;
}
.add-moto-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 10px;
  margin-bottom: 12px;
}
.add-moto-grid select {
  width: 100%;
  padding: 8px 10px;
  background: var(--overlay-hover);
  border: 1px solid var(--border-1);
  border-radius: 8px;
  color: var(--content-1);
  font-family: inherit;
  font-size: 14px;
}
.autocomplete-field {
  position: relative;
}
.autocomplete-list {
  position: absolute;
  z-index: 10;
  top: 100%;
  left: 0;
  right: 0;
  margin: 4px 0 0;
  padding: 4px;
  list-style: none;
  background: var(--surface-1);
  border: 1px solid var(--border-2);
  border-radius: 8px;
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
  max-height: 220px;
  overflow-y: auto;
}
.autocomplete-list li {
  padding: 8px 10px;
  border-radius: 6px;
  font-size: 13px;
  cursor: pointer;
}
.autocomplete-list li:hover {
  background: var(--overlay-hover);
}
.moto-hint {
  margin: -4px 0 12px;
  font-size: 11px;
  color: var(--content-3);
}
.moto-card {
  padding: 16px;
  background: var(--surface-1);
  border: 1px solid var(--border-2);
  border-radius: 12px;
}
.moto-name {
  font-weight: 800;
  font-size: 16px;
  margin-bottom: 4px;
}
.moto-meta {
  display: flex;
  gap: 12px;
  font-size: 13px;
  color: var(--content-3);
  margin-bottom: 14px;
}
.moto-field {
  margin-bottom: 10px;
}
.moto-field label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: var(--content-3);
  margin-bottom: 4px;
}
.moto-field input,
.moto-field textarea {
  width: 100%;
  padding: 8px 10px;
  background: var(--overlay-hover);
  border: 1px solid var(--border-1);
  border-radius: 8px;
  color: var(--content-1);
  font-family: inherit;
  font-size: 14px;
  resize: vertical;
}
.moto-field input:focus,
.moto-field textarea:focus {
  border-color: var(--accent-graphic);
}
.moto-save {
  padding: 8px 16px;
  border: none;
  border-radius: 8px;
  background: var(--accent);
  color: var(--accent-ink);
  font-weight: 700;
  font-size: 13px;
  cursor: pointer;
}
.moto-save:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.moto-error {
  margin-top: 8px;
  font-size: 12px;
  color: var(--error-content);
}
.moto-saved {
  margin-top: 8px;
  font-size: 12px;
  color: var(--success-content);
}
</style>
